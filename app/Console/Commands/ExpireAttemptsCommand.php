<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Exam\AttemptManager;
use Illuminate\Console\Command;

/**
 * A complement, not a dependency. Expiry is already evaluated on the read path, so
 * nothing in the application needs this to be correct — it exists so that an
 * attempt abandoned forever does not sit in the database as "in progress" and
 * distort the admin dashboard's attempt count.
 */
final class ExpireAttemptsCommand extends Command
{
    protected $signature = 'cpns:expire-attempts';

    protected $description = 'Grade and close attempts whose time ran out';

    public function handle(AttemptManager $manager): int
    {
        $closed = $manager->expireAllOverdue();

        $this->info($closed === 0
            ? 'Tidak ada percobaan yang perlu ditutup.'
            : "{$closed} percobaan ditutup dan dinilai.");

        return self::SUCCESS;
    }
}
