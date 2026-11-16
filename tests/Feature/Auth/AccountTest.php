<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AccountTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Peserta Uji',
            'email' => 'peserta@example.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ];
    }

    public function test_the_landing_page_is_public(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_registering_creates_a_peserta_with_a_participant_number_and_signs_them_in(): void
    {
        $response = $this->post('/daftar', $this->payload());

        $response->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'peserta@example.test')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->isAdmin());
        $this->assertNotNull($user->participant_number);
        $this->assertStringStartsWith(now()->format('Y').'-', $user->participant_number);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }

    /**
     * `role` is deliberately still fillable (the admin-creation command writes it)
     * while registration is the only participant-facing write to `users` and is
     * open to anyone. The guard is the explicit key list in `validate()`, so an
     * extra field in the payload must be dropped rather than saved.
     */
    public function test_a_registration_cannot_promote_itself_to_admin(): void
    {
        $this->post('/daftar', $this->payload(['role' => UserRole::Admin->value]))
            ->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'peserta@example.test')->firstOrFail();

        $this->assertSame(UserRole::Peserta, $user->role);
        $this->assertFalse($user->isAdmin());
    }

    public function test_registration_rejects_a_weak_password_and_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'peserta@example.test']);

        $this->post('/daftar', $this->payload(['password' => 'pendek', 'password_confirmation' => 'pendek']))
            ->assertSessionHasErrors('password');

        $this->post('/daftar', $this->payload())
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::query()->count());
    }

    public function test_the_login_and_register_pages_render_the_site_chrome(): void
    {
        $this->get('/masuk')
            ->assertOk()
            ->assertSee(route('login'), escape: false);

        $this->get('/daftar')
            ->assertOk()
            ->assertSee(route('register'), escape: false);
    }

    public function test_a_guest_is_sent_to_the_login_route(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_login_works_and_the_session_is_regenerated(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        $response = $this->post('/masuk', ['email' => $user->email, 'password' => 'rahasia123']);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_throttled_after_five_failures_for_the_same_email_and_ip(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/masuk', ['email' => $user->email, 'password' => 'salah-sekali'])
                ->assertSessionHasErrors('email');
        }

        $this->post('/masuk', ['email' => $user->email, 'password' => 'rahasia123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/keluar')
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_a_participant_can_change_their_password(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        $this->actingAs($user)->put('/akun/password', [
            'current_password' => 'rahasia123',
            'password' => 'passwordbaru1',
            'password_confirmation' => 'passwordbaru1',
        ])->assertRedirect(route('dashboard'));

        $this->assertTrue(Hash::check('passwordbaru1', $user->refresh()->password));
    }

    public function test_changing_the_password_needs_the_current_one(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        $this->actingAs($user)->put('/akun/password', [
            'current_password' => 'bukan-ini',
            'password' => 'passwordbaru1',
            'password_confirmation' => 'passwordbaru1',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('rahasia123', $user->refresh()->password));
    }

    public function test_a_temporary_password_blocks_every_page_until_it_is_changed(): void
    {
        $user = User::factory()->create([
            'password' => 'sementara1',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('password.edit'));
        $this->actingAs($user)->get('/akun/password')->assertOk();

        $this->actingAs($user)->put('/akun/password', [
            'current_password' => 'sementara1',
            'password' => 'passwordbaru1',
            'password_confirmation' => 'passwordbaru1',
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse($user->refresh()->must_change_password);
        $this->actingAs($user->refresh())->get('/dashboard')->assertOk();
    }

    /**
     * Registration is unauthenticated and each accepted request costs a bcrypt
     * hash plus a users row, so it is throttled per client IP like login is.
     */
    public function test_registration_is_throttled_per_client_ip(): void
    {
        $limit = (int) config('cpns.password.register_attempts_per_minute');

        for ($attempt = 0; $attempt < $limit; $attempt++) {
            $this->post('/daftar', $this->payload(['email' => "peserta{$attempt}@example.test"]))
                ->assertRedirect(route('dashboard'));

            // Sign out so the next attempt reaches the guest-only registration
            // route instead of being bounced to the dashboard.
            $this->post('/keluar');
        }

        $this->post('/daftar', $this->payload(['email' => 'peserta-akhir@example.test']))
            ->assertSessionHasErrors('email');

        $this->assertSame($limit, User::query()->count());
        $this->assertGuest();
    }

    public function test_the_dashboard_offers_a_working_logout_form(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();

        // A POST form, not a link: signing out changes state and stays CSRF-protected.
        $this->assertMatchesRegularExpression(
            '/<form\b[^>]*\bmethod="POST"[^>]*\baction="'.preg_quote(route('logout'), '/').'"/',
            $response->getContent(),
        );

        // And it is wired to the real route: posting to it ends the session.
        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_a_mixed_case_registration_signs_in_with_the_lowercase_address(): void
    {
        $this->post('/daftar', $this->payload(['email' => 'Budi@Example.com']))
            ->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'budi@example.com')->firstOrFail();

        $this->assertSame('budi@example.com', $user->email);

        $this->post('/keluar');

        $this->post('/masuk', ['email' => 'budi@example.com', 'password' => 'rahasia123'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_mixed_case_registration_cannot_be_duplicated_by_a_differently_cased_address(): void
    {
        $this->post('/daftar', $this->payload(['email' => 'Budi@Example.com']))
            ->assertRedirect(route('dashboard'));

        $this->post('/keluar');

        $this->post('/daftar', $this->payload(['email' => 'budi@example.com']))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::query()->count());
    }
}
