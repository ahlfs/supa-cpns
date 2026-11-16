<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_admin_account(): void
    {
        $this->artisan('cpns:admin', [
            'email' => 'admin@example.test',
            '--name' => 'Petugas',
            '--password' => 'rahasia123',
        ])->assertSuccessful();

        $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();

        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertSame('Petugas', $admin->name);
        $this->assertNotNull($admin->participant_number);
        $this->assertTrue(Hash::check('rahasia123', $admin->password));
        $this->assertFalse($admin->must_change_password);
    }

    public function test_promoting_an_existing_account_forces_a_password_change(): void
    {
        $user = User::factory()->create(['email' => 'naik@example.test']);

        $this->artisan('cpns:admin', ['email' => 'naik@example.test', '--password' => 'rahasia123'])
            ->assertSuccessful();

        $user->refresh();

        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->must_change_password);
    }

    public function test_a_short_password_is_refused(): void
    {
        $this->artisan('cpns:admin', ['email' => 'admin@example.test', '--password' => 'pendek'])
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }

    /**
     * This command is the documented recovery path for a locked-out administrator, so
     * it has to agree with the rest of the application about what "the same address"
     * means: registration, login and the participants screen all lowercase and trim
     * before they look an address up or store one.
     */
    public function test_a_mixed_case_email_cannot_create_a_second_account(): void
    {
        $user = User::factory()->create(['email' => 'petugas@example.test']);

        $this->artisan('cpns:admin', ['email' => '  Petugas@Example.TEST ', '--password' => 'rahasia123'])
            ->assertSuccessful();

        // One row, promoted — not a second administrator whose address differs only
        // in case from the first.
        $this->assertSame(1, User::query()->count());

        $user->refresh();

        $this->assertTrue($user->isAdmin());
        $this->assertSame('petugas@example.test', $user->email);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }
}
