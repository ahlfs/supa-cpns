<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ContentRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

final class RegisterController extends Controller
{
    public function create(ContentRepository $content): View
    {
        return view('auth.register', ['site' => $content->site()] + $content->chrome());
    }

    public function store(Request $request): RedirectResponse
    {
        // Mobile keyboards capitalise the first letter, so a mixed-case address is
        // the common case. Lowercasing (and trimming) before validation makes the
        // `unique` rule, the stored row and the login lookup see one spelling;
        // otherwise two accounts differ only in case and neither signs in with the
        // other.
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

        // Registration is open to anyone and every accepted request costs a bcrypt
        // hash and a users row, while `unique:users,email` answers "does this
        // account exist?" for free. Throttled per client IP, the way login is
        // throttled per email+IP, and counted on every attempt that reaches here
        // (before validation) so the enumeration oracle is blunted too.
        $throttleKey = (string) $request->ip();
        $limit = (int) config('cpns.password.register_attempts_per_minute');

        if (RateLimiter::tooManyAttempts($throttleKey, $limit)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan pendaftaran. Coba lagi dalam '
                    .RateLimiter::availableIn($throttleKey).' detik.',
            ]);
        }

        RateLimiter::hit($throttleKey, 60);

        $minLength = (int) config('cpns.password.min_length');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min($minLength)->letters()->numbers()],
        ]);

        // Email is deliberately asked for once: with no mailer an address cannot be
        // verified, and a typo is fixed faster from the admin panel than it is
        // prevented by making everyone type it twice.
        $user = User::create($data);

        $user->assignParticipantNumber();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
