<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Peserta = 'peserta';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Peserta => 'Peserta',
            self::Admin => 'Administrator',
        };
    }
}
