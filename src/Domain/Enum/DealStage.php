<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum DealStage: string
{
    case NEW = 'new';
    case QUALIFICATION = 'qualification';
    case PROPOSAL = 'proposal';
    case NEGOTIATION = 'negotiation';
    case WON = 'won';
    case LOST = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'Nowy',
            self::QUALIFICATION => 'Kwalifikacja',
            self::PROPOSAL => 'Oferta wysłana',
            self::NEGOTIATION => 'Negocjacje',
            self::WON => 'Wygrany',
            self::LOST => 'Przegrany',
        };
    }

    /**
     * Deal is still being actively worked (not closed won/lost).
     */
    public function isOpen(): bool
    {
        return $this !== self::WON && $this !== self::LOST;
    }

    /**
     * @return list<self>
     */
    public static function openStages(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $s) => $s->isOpen()));
    }
}
