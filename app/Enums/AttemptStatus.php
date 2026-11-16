<?php

declare(strict_types=1);

namespace App\Enums;

enum AttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Expired = 'expired';

    /** An attempt that can still accept answers. */
    public function isOpen(): bool
    {
        return $this === self::InProgress;
    }

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'Sedang dikerjakan',
            self::Submitted => 'Selesai',
            self::Expired => 'Waktu habis',
        };
    }
}
