<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum EmailDirection: string
{
    case SENT = 'sent';
    case RECEIVED = 'received';

    public function label(): string
    {
        return match ($this) {
            self::SENT => 'Wysłany',
            self::RECEIVED => 'Odebrany',
        };
    }
}
