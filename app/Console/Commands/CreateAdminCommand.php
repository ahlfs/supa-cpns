<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Creates the first administrator. Deliberately not a seeder: a seeder ships a
 * default password that survives on a server, and this one either prompts for the
 * password or takes it from the command line.
 */
final class CreateAdminCommand extends Command
{
    protected $signature = 'cpns:admin
        {email : Alamat email akun admin}
        {--name= : Nama yang ditampilkan}
        {--password= : Password awal; kalau kosong akan ditanyakan}';

    protected $description = 'Create or promote an admin account for the CPNS admin panel';

    public function handle(): int
    {
        // Normalised exactly as registration, login and the participants screen normalise
        // an address: lowercased and trimmed. This command is the documented way back in
        // for a locked-out administrator, so a difference of case must not be able to
        // create a second row beside the account it was meant to recover — an account
        // whose mixed-case spelling could not be signed in to in the first place.
        $email = Str::lower(trim((string) $this->argument('email')));
        $password = (string) ($this->option('password') ?? '');

        if ($password === '') {
            $password = (string) $this->secret('Password untuk akun admin');
        }

        if (strlen($password) < (int) config('cpns.password.min_length')) {
            $this->error('Password minimal '.config('cpns.password.min_length').' karakter.');

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing === null) {
            $user = User::create([
                'name' => (string) ($this->option('name') ?? Str::before($email, '@')),
                'email' => $email,
                'password' => $password,
                'role' => UserRole::Admin,
            ]);

            $user->assignParticipantNumber();

            $this->info("Admin [{$email}] dibuat.");

            return self::SUCCESS;
        }

        $existing->forceFill([
            'role' => UserRole::Admin,
            'password' => $password,
            // A password typed into a shell is temporary by nature, so the account
            // is held at the change-password form.
            'must_change_password' => true,
        ])->save();

        $this->info("Akun [{$email}] dijadikan admin dan passwordnya diganti.");

        return self::SUCCESS;
    }
}
