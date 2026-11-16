<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Support\ContentRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

/**
 * The accounts list, and the two remedies that exist only because this application
 * has no working mailer: an address corrected by hand, and a temporary password
 * handed over in person.
 */
final class UserController extends Controller
{
    /** One page of accounts, matching the question list's page size. */
    private const PER_PAGE = 20;

    /**
     * How many times a temporary password may be drawn before the generator is
     * declared broken. The rule and the generator agree today, so the first draw
     * always wins — see temporaryPassword(); the cap is what keeps a future
     * disagreement a loud failure instead of a request that never returns.
     */
    private const MAX_PASSWORD_DRAWS = 25;

    public function index(Request $request, ContentRepository $content): View
    {
        // Read the way the question list reads it, so the two screens behave the same —
        // except that a query string can carry anything: `?q[]=x` hands the controller an
        // array where it expects a string, and casting that is a 500 for an administrator
        // who mistyped a URL. A term that is not a string is no term at all.
        $term = $request->query('q');
        $search = is_scalar($term) ? (string) $term : '';

        $users = User::query()
            ->withCount('attempts')
            // The three ORs are wrapped in their own group: were they left loose, a
            // constraint added to this query later would be defeated by the search.
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')
                ->orWhere('participant_number', 'like', '%'.$search.'%')))
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.users.index', [
            'site' => $content->site(),
            'title' => 'Pengguna',
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $role = UserRole::from($data['role']);

        // The panel's own rule was one form submission deep: a 403 lived on
        // resetPassword while this method wrote `role`, and the role select is
        // rendered on every row — so a peer could demote an administrator to
        // Peserta, reload, and find the reset button that had just been refused.
        // The rule is enforced where it can actually be broken.
        if ($role !== $user->role) {
            // The target's STORED role decides, never the request: an edit that
            // repeats the current role is not a change and keeps working.
            if ($user->isAdmin()) {
                // An administrator whose account is the only one cannot be taken
                // out of the panel from the panel — there is no self-service path
                // back in, so recovery would mean the command line.
                if ($role !== UserRole::Admin && User::query()->where('role', UserRole::Admin->value)->count() <= 1) {
                    return back()->withErrors([
                        'role' => 'Ini administrator terakhir, jadi perannya tidak bisa diturunkan — '
                            .'panel tidak boleh ditinggal tanpa administrator. Tambah administrator lain dulu, '
                            .'lalu ubah peran akun ini.',
                    ]);
                }

                return back()->withErrors([
                    'role' => 'Peran akun administrator tidak diubah dari layar ini. Kalau akun ini memang '
                        .'harus berhenti menjadi administrator, ubah perannya dari command line (php artisan) — '
                        .'begitu juga sebaliknya, akun administrator baru dibuat di sana.',
                ]);
            }
        }

        // Only what the request validated. Nothing else on the row is reachable from
        // the browser, however the form is rewritten.
        $user->update($data);

        return back()->with('status', 'Data pengguna diperbarui.');
    }

    /**
     * The reset that exists because email does not. The temporary password is shown
     * once on screen — not emailed, not written to a log — and the account is held at
     * the change-password form until it is replaced. Without that flag a temporary
     * password becomes permanent, which is the classic hole in an application with no
     * mailer.
     */
    public function resetPassword(User $user): RedirectResponse
    {
        // An administrator's credentials are not handled from a list. A peer admin
        // must not be able to aim a reset at another admin's account, and an admin who
        // has lost their own password is recovered with `php artisan cpns:admin` — the
        // documented path for exactly that. Everyone else on this screen is a
        // participant, and this is the only way they get in again.
        abort_if($user->isAdmin(), 403);

        $password = $this->temporaryPassword();

        $user->forceFill([
            'password' => $password,
            'must_change_password' => true,
            // A remember-me cookie is a second key to the account, and the reason to
            // reset a password is usually that the first one is no longer only the
            // owner's. Rotating the token withdraws the remember-me cookies — the
            // devices that ticked "remember me" — and nothing else.
            'remember_token' => Str::random(60),
        ])->save();

        // The rest of the withdrawal is here. A device that is already signed in
        // holds a row in `sessions`, and that row is what keeps it signed in for
        // the session's whole lifetime (1440 minutes on this host) however many
        // times the password changes — with the database driver the password is
        // only read again at the next login. So the rows go, in the same reset:
        // otherwise the reset does not end the access it exists to end. The table
        // is the configured one, never a literal.
        DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();

        // Flashed, so it is on the very next screen and gone from the one after that.
        return back()->with('temporary_password', [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $password,
        ]);
    }

    /**
     * A temporary password the change-password form will accept — one its holder
     * cannot use is not a reset, it is a second lockout.
     *
     * The draw is still checked against the application's own password rule, because
     * the rule is what the change-password form enforces and a generator that drifts
     * from it hands out passwords nobody can use. The check is a belt, not a
     * necessity: this Laravel's `Str::password` shuffles the ENABLED CHARACTER POOLS
     * and pushes one character from each of them before it pads, so a draw from
     * letters and numbers always contains at least one of each and cannot fail the
     * rule today. (The earlier comment here claimed a ~17% miss rate; that was
     * written for a version that drew every character from one alphabet, and is not
     * true of this one.)
     *
     * The retry cap is there for the day the rule gains a class the generator cannot
     * emit: bounded, the method fails loudly instead of spinning on a request thread
     * forever. The length is the configured minimum, so there is no second number to
     * keep in step with the rule.
     */
    private function temporaryPassword(): string
    {
        $length = (int) config('cpns.password.min_length');
        $requirement = Password::min($length)->letters()->numbers();

        for ($draw = 0; $draw < self::MAX_PASSWORD_DRAWS; $draw++) {
            $password = Str::password($length, symbols: false);

            if (Validator::make(['password' => $password], ['password' => [$requirement]])->passes()) {
                return $password;
            }
        }

        throw new RuntimeException(
            self::MAX_PASSWORD_DRAWS.' draws produced no temporary password the application\'s password rule accepts; '
            .'the rule and the generator have drifted apart.'
        );
    }
}
