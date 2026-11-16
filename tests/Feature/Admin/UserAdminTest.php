<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use Tests\TestCase;

/**
 * The participants screen: the two remedies that exist because this application
 * has no working mailer — fixing a mistyped address by hand, and handing out a
 * temporary password that is shown once and cannot be kept.
 *
 * Every refusal is checked against the database as well as the response: a
 * refusal that still wrote is the failure this file exists to catch.
 */
final class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function updateUrl(User $user): string
    {
        return route('admin.users.update', ['user' => $user]);
    }

    private function resetUrl(User $user): string
    {
        return route('admin.users.reset-password', ['user' => $user]);
    }

    /**
     * The update payload the screen itself sends.
     *
     * @return array<string, string>
     */
    private function payload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            // A freshly created row carries the column's default in the database, not
            // in the model, so the fallback is the same default.
            'role' => ($user->role ?? UserRole::Peserta)->value,
        ], $overrides);
    }

    public function test_a_peserta_cannot_open_the_participants_screen(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/pengguna')->assertForbidden();
    }

    public function test_a_peserta_cannot_write_through_the_participants_screen(): void
    {
        $peserta = User::factory()->create();
        $victim = User::factory()->create(['name' => 'Korban', 'password' => 'rahasia123']);

        $this->actingAs($peserta)
            ->put($this->updateUrl($victim), $this->payload($victim, ['role' => UserRole::Admin->value]))
            ->assertForbidden();

        $this->actingAs($peserta)->post($this->resetUrl($victim))->assertForbidden();

        $victim->refresh();

        $this->assertSame('Korban', $victim->name);
        $this->assertFalse($victim->isAdmin());
        $this->assertFalse($victim->must_change_password);
        $this->assertTrue(Hash::check('rahasia123', $victim->password));
    }

    public function test_the_participants_menu_entry_actually_renders(): void
    {
        // The layout drops nav items whose route name it cannot resolve, so a typo
        // in a route name costs a menu entry without failing anything. Asserting on
        // the dashboard is what makes the entry's absence a failure.
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Pengguna')
            ->assertSee(route('admin.users.index'));
    }

    public function test_the_screen_searches_by_name_email_and_participant_number(): void
    {
        $budi = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.test']);
        $budi->assignParticipantNumber();

        User::factory()->create(['name' => 'Siti Aminah', 'email' => 'siti@example.test']);

        $admin = $this->admin();

        foreach (['budi@example.test', 'Santoso', (string) $budi->participant_number] as $term) {
            $this->actingAs($admin)
                ->get('/admin/pengguna?q='.urlencode($term))
                ->assertOk()
                ->assertSee('budi@example.test')
                ->assertDontSee('siti@example.test');
        }
    }

    public function test_an_admin_can_fix_a_mistyped_email(): void
    {
        $user = User::factory()->create(['email' => 'salah@example.tets']);

        $this->actingAs($this->admin())->put($this->updateUrl($user), [
            'name' => $user->name,
            'email' => 'benar@example.test',
            'role' => UserRole::Peserta->value,
        ])->assertRedirect();

        $this->assertSame('benar@example.test', $user->refresh()->email);
    }

    public function test_an_email_already_in_use_is_refused(): void
    {
        User::factory()->create(['email' => 'dipakai@example.test']);
        $user = User::factory()->create(['email' => 'milikku@example.test']);

        $this->actingAs($this->admin())->put($this->updateUrl($user), [
            'name' => $user->name,
            'email' => 'dipakai@example.test',
            'role' => UserRole::Peserta->value,
        ])->assertSessionHasErrors('email');

        // The address already in use belongs to someone else, so the row must not
        // have moved onto it.
        $this->assertSame('milikku@example.test', $user->refresh()->email);

        // Case alone is not a second account: the request is normalised before the
        // unique rule runs, so a differently-cased spelling of a taken address is
        // refused too.
        $this->put($this->updateUrl($user), [
            'name' => $user->name,
            'email' => 'DIPAKAI@Example.test',
            'role' => UserRole::Peserta->value,
        ])->assertSessionHasErrors('email');

        $this->assertSame('milikku@example.test', $user->refresh()->email);
    }

    public function test_a_corrected_email_is_stored_the_way_the_login_form_looks_it_up(): void
    {
        $user = User::factory()->create(['email' => 'salah@example.tets']);

        $this->actingAs($this->admin())->put($this->updateUrl($user), [
            'name' => $user->name,
            'email' => '  Baru@Example.TEST ',
            'role' => UserRole::Peserta->value,
        ])->assertRedirect();

        // Login lowercases and trims before it looks an address up, so an address
        // corrected here has to be stored in that same spelling — otherwise the
        // correction produces an account nobody can sign in to.
        $this->assertSame('baru@example.test', $user->refresh()->email);

        $this->assertTrue(
            Auth::attempt(['email' => 'baru@example.test', 'password' => 'password']),
            'The corrected address has to sign in.',
        );
    }

    public function test_a_participant_can_be_promoted_to_administrator(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin())
            ->put($this->updateUrl($user), $this->payload($user, ['role' => UserRole::Admin->value]))
            ->assertRedirect();

        $this->assertTrue($user->refresh()->isAdmin());

        // The other direction is refused when the target is an administrator —
        // see the two tests below. A password reset is not offered from this
        // screen for an administrator either, and demoting a peer was the three
        // clicks that used to make that button appear.
    }

    /**
     * The 403 lived on resetPassword while `update` wrote `role`, and the role
     * select is rendered on every row including an administrator's. So a peer
     * could demote an administrator to Peserta, reload, and find the reset button
     * where it had been refused — the rule was one form submission deep.
     *
     * The comparison is against the target's stored role, not the request: a form
     * that re-submits the same role is not a change and must keep working.
     */
    public function test_demoting_another_administrator_is_refused_and_writes_nothing(): void
    {
        $target = User::factory()->admin()->create(['name' => 'Admin Lain', 'email' => 'lain@example.test']);
        $other = User::factory()->admin()->create();

        $this->actingAs($other)
            ->put($this->updateUrl($target), $this->payload($target, [
                'name' => 'Nama yang dicoba diubah',
                'role' => UserRole::Peserta->value,
            ]))
            ->assertSessionHasErrors('role');

        $target->refresh();

        $this->assertSame(UserRole::Admin, $target->role);
        $this->assertSame('Admin Lain', $target->name, 'A refused role change must not write the other fields either.');
        $this->assertSame('lain@example.test', $target->email);
    }

    /**
     * And the panel cannot be left without one: the sole administrator editing
     * their own row down to Peserta is refused, because there is no self-service
     * path back in — recovery is `php artisan cpns:admin`.
     */
    public function test_the_last_administrator_cannot_be_demoted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertSame(1, User::query()->where('role', UserRole::Admin->value)->count());

        $this->actingAs($admin)
            ->put($this->updateUrl($admin), $this->payload($admin, ['role' => UserRole::Peserta->value]))
            ->assertSessionHasErrors('role');

        $admin->refresh();

        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertSame(1, User::query()->where('role', UserRole::Admin->value)->count());
    }

    /**
     * The refusal is about the role, not the row: an administrator's name and
     * address stay editable from the same form, which is what keeps the guard from
     * becoming a "this screen is broken" for every admin account.
     */
    public function test_an_administrators_other_fields_stay_editable(): void
    {
        $target = User::factory()->admin()->create(['name' => 'Admin Lain', 'email' => 'lain@example.test']);

        $this->actingAs($target)
            ->put($this->updateUrl($target), $this->payload($target, [
                'name' => 'Nama Admin Diperbaiki',
                'email' => 'diperbaiki@example.test',
            ]))
            ->assertRedirect();

        $target->refresh();

        $this->assertSame('Nama Admin Diperbaiki', $target->name);
        $this->assertSame('diperbaiki@example.test', $target->email);
        $this->assertSame(UserRole::Admin, $target->role);
    }

    public function test_a_role_is_never_changed_without_being_stated(): void
    {
        $user = User::factory()->create(['email' => 'tetap@example.test']);

        // No `role` key: an edit that forgets it must fail loudly rather than fall
        // back to the account's current role — or, worse, to a promoted one.
        $this->actingAs($this->admin())->put($this->updateUrl($user), [
            'name' => 'Nama Baru',
            'email' => 'baru@example.test',
        ])->assertSessionHasErrors('role');

        $user->refresh();

        $this->assertSame(UserRole::Peserta, $user->role);
        $this->assertSame('tetap@example.test', $user->email);
    }

    public function test_the_update_route_writes_nothing_outside_its_whitelist(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);
        $user->forceFill([
            'participant_number' => '2026-000777',
            'remember_token' => 'token-asli',
        ])->save();

        $this->actingAs($this->admin())->put($this->updateUrl($user), $this->payload($user, [
            'participant_number' => 'PALSU-000001',
            'password' => 'diganti123',
            'must_change_password' => '1',
            'remember_token' => 'token-palsu',
            'email_verified_at' => '2030-01-01 00:00:00',
        ]))->assertRedirect();

        $user->refresh();

        // Only name, email and role are accepted. A participant number is derived
        // from the row id, and a password is only ever replaced by the reset route
        // — never as a side effect of an edit.
        $this->assertSame('2026-000777', $user->participant_number);
        $this->assertSame('token-asli', $user->remember_token);
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
        $this->assertFalse(Hash::check('diganti123', $user->password));
    }

    public function test_resetting_a_password_shows_one_temporary_password_and_forces_a_change(): void
    {
        $user = User::factory()->create(['password' => 'lupa12345']);

        $response = $this->actingAs($this->admin())
            ->post($this->resetUrl($user))
            ->assertRedirect();

        $temporary = session('temporary_password');

        $this->assertIsArray($temporary);
        $this->assertSame($user->email, $temporary['email']);
        $this->assertNotSame('lupa12345', $temporary['password']);

        $user->refresh();

        $this->assertTrue(Hash::check($temporary['password'], $user->password));
        $this->assertTrue($user->must_change_password);

        // The generated password has to satisfy the same rule the change-password
        // form enforces, or the holder is handed something they cannot use.
        $minLength = (int) config('cpns.password.min_length');

        $this->assertTrue(
            Validator::make(
                ['password' => $temporary['password']],
                ['password' => ['required', 'string', Password::min($minLength)->letters()->numbers()]],
            )->passes(),
            'The temporary password must pass the password rule the app validates with.',
        );

        // A role is not a thing a reset touches.
        $this->assertSame(UserRole::Peserta, $user->role);

        $this->assertIsString($response->headers->get('Location'));

        // The temporary password cannot be used for anything until it is replaced.
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('password.edit'));
    }

    public function test_the_temporary_password_is_displayed_once(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin())
            ->from(route('admin.users.index'))
            ->post($this->resetUrl($user))
            ->assertRedirect(route('admin.users.index'));

        $password = session('temporary_password')['password'];

        // Flashed, so exactly one further request carries it: the admin can read it
        // off the screen, and it is gone from the next page they open.
        $this->get(route('admin.users.index'))->assertOk()->assertSee($password);
        $this->get(route('admin.users.index'))->assertOk()->assertDontSee($password);
    }

    public function test_the_temporary_password_is_never_written_to_the_log(): void
    {
        // Captured, not spied on: the point is that no log call anywhere in the
        // request carries the password, whatever channel it went to.
        $handler = new TestHandler;

        Log::swap(new MonologLogger('testing', [$handler]));

        $user = User::factory()->create();

        $this->actingAs($this->admin())->post($this->resetUrl($user))->assertRedirect();

        $password = session('temporary_password')['password'];

        // Positive control: if the swap were not capturing, "nothing in the log"
        // would pass for the wrong reason.
        Log::info('kontrol-uji', ['kunci' => 'nilai']);

        $this->assertTrue($handler->hasInfoThatContains('kontrol-uji'), 'The test log handler must see log calls.');

        foreach ($handler->getRecords() as $record) {
            $this->assertStringNotContainsString($password, $record->message);
            $this->assertStringNotContainsString($password, (string) json_encode($record->context));
        }

        $this->assertTrue($user->refresh()->must_change_password);
    }

    public function test_the_temporary_password_is_never_emailed(): void
    {
        Mail::fake();
        Notification::fake();

        $user = User::factory()->create();

        $this->actingAs($this->admin())->post($this->resetUrl($user))->assertRedirect();

        $this->assertIsArray(session('temporary_password'));

        // There is no MTA on this host and no mailer configured, so a reset that
        // "sends" the password would fail silently and leave the account locked.
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
    }

    public function test_another_administrators_password_is_not_reset_from_this_screen(): void
    {
        $target = User::factory()->admin()->create(['password' => 'rahasia123']);
        $other = User::factory()->admin()->create();

        $hash = $target->password;

        $this->actingAs($other)->post($this->resetUrl($target))->assertForbidden();

        $target->refresh();

        $this->assertSame($hash, $target->password);
        $this->assertFalse($target->must_change_password);
        $this->assertNull(session('temporary_password'));
    }

    public function test_the_list_shows_no_password_hash_and_no_remember_token(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['remember_token' => 'token-rahasia'])->save();

        $response = $this->actingAs($this->admin())->get('/admin/pengguna');

        $response->assertOk();
        $response->assertSee($user->email);
        $response->assertDontSee($user->password);
        $response->assertDontSee('token-rahasia');
    }

    /**
     * A participant's name is whatever they typed at a public registration form, and
     * this screen puts a name inside an inline `onsubmit` handler. Blade escapes the
     * quote to `&#039;`, but an HTML parser decodes that entity before the JavaScript
     * parser ever sees the attribute — so the quote comes back and closes the string
     * literal the handler is built from. Reading the attribute through a real parser
     * and asserting on the decoded value is therefore the only assertion that touches
     * the text the JS engine would actually receive.
     */
    public function test_a_participant_name_cannot_break_out_of_the_reset_confirmation(): void
    {
        $payload = "x',fetch('/admin/pengguna/1')</script>";

        $attacker = User::factory()->create(['name' => $payload]);

        $response = $this->actingAs($this->admin())->get(route('admin.users.index'));

        $response->assertOk();

        $html = (string) $response->getContent();

        // The row rendered at all, and the name is on the page: without this, "the
        // payload is absent" would pass for the wrong reason on an empty list.
        $response->assertSee($attacker->email);
        $this->assertStringContainsString('x&#039;,fetch(', $html, 'The name must still be shown, escaped for HTML.');

        $attributes = $this->renderedHandler($html);

        // The string the JavaScript parser is handed once entities are decoded.
        $handler = $attributes['onsubmit'] ?? '';

        $this->assertStringContainsString('Buat password sementara untuk', $handler);
        $this->assertStringContainsString('confirm(', $handler);

        // The attack needs a raw quote followed by the call. Decoded, there is none:
        // the quote in the name survived only as the JS escape `\u0027`.
        $this->assertStringNotContainsString("',fetch(", $handler);
        $this->assertStringNotContainsString('</script', $handler);
        $this->assertStringContainsString('\u0027,fetch(', $handler, 'The quote must reach the handler escaped, not raw.');
    }

    /**
     * @return array<string, string>
     */
    private function renderedHandler(string $html): array
    {
        $document = new \DOMDocument;
        @$document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        $forms = (new \DOMXPath($document))->query('//form[@onsubmit]');
        $found = [];

        foreach ($forms as $form) {
            /** @var \DOMElement $form */
            $found['onsubmit'] = (string) $form->getAttribute('onsubmit');
        }

        return $found;
    }

    public function test_a_non_scalar_search_term_is_treated_as_no_search(): void
    {
        $user = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.test']);

        // A hand-typed admin URL: `?q[]=x` gives the controller an array where it
        // expects a string. That is a bad request from an administrator, not a crash.
        $response = $this->actingAs($this->admin())->get('/admin/pengguna?q[]=x');

        $response->assertOk();
        $response->assertSee('budi@example.test');
        $response->assertSee($user->name);
        $response->assertViewHas('search', '');
    }

    public function test_resetting_a_password_withdraws_the_remember_me_cookie(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['remember_token' => 'token-lama'])->save();
        $user->refresh();

        $this->assertSame('token-lama', $user->remember_token);

        $this->actingAs($this->admin())->post($this->resetUrl($user))->assertRedirect();

        $rotated = $user->refresh()->remember_token;

        // A reset that only rewrites the password leaves a remember-me cookie working
        // on any device its owner has lost — the account is not actually withdrawn.
        $this->assertNotSame('token-lama', $rotated);
        $this->assertNotNull($rotated);
    }

    /**
     * Rotating `remember_token` withdraws the remember-me cookies and nothing
     * else. Under the database session driver (what this app uses) a device that
     * is already signed in holds a row in `sessions`, and the session's own
     * lifetime — 1440 minutes here — keeps it signed in for up to a day however
     * many times the password changes. A reset exists to end exactly that access,
     * so its rows go with it, in the same write.
     */
    public function test_resetting_a_password_ends_the_sittings_it_exists_to_end(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        DB::table('sessions')->insert([
            ['id' => 'perangkat-a', 'user_id' => $user->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'uji', 'payload' => '', 'last_activity' => now()->getTimestamp()],
            ['id' => 'perangkat-b', 'user_id' => $user->id, 'ip_address' => '10.0.0.2', 'user_agent' => 'uji', 'payload' => '', 'last_activity' => now()->getTimestamp()],
            // Someone else's session is not this reset's business.
            ['id' => 'perangkat-lain', 'user_id' => $other->id, 'ip_address' => '10.0.0.3', 'user_agent' => 'uji', 'payload' => '', 'last_activity' => now()->getTimestamp()],
        ]);

        $this->assertSame(2, DB::table('sessions')->where('user_id', $user->id)->count());

        $this->actingAs($this->admin())->post($this->resetUrl($user))->assertRedirect();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());
    }

    public function test_the_temporary_password_generator_always_emits_a_letter_and_a_digit(): void
    {
        // The controller's comment rests on this: this Laravel's Str::password shuffles
        // the enabled character POOLS and pushes one character from each before it
        // pads, so a letters-and-numbers draw can never miss either class. Asserted
        // rather than assumed, because it is the reason the rule check never redraws.
        $length = (int) config('cpns.password.min_length');

        $draws = [];

        for ($draw = 0; $draw < 200; $draw++) {
            $draws[] = Str::password($length, symbols: false);
        }

        $this->assertSame([], array_values(array_filter($draws, fn (string $p): bool => preg_match('/[A-Za-z]/', $p) !== 1)),
            'Every draw must carry a letter.');
        $this->assertSame([], array_values(array_filter($draws, fn (string $p): bool => preg_match('/[0-9]/', $p) !== 1)),
            'Every draw must carry a digit.');
    }
}
