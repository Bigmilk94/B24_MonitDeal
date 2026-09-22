<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum ActivityType: string
{
    case EMAIL = 'email';
    case TASK = 'task';
    case CALL = 'call';
    case MEETING = 'meeting';
    case COMMENT = 'comment';
    case NOTE = 'note';
    case STAGE_CHANGE = 'stage_change';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'E-mail',
            self::TASK => 'Zadanie',
            self::CALL => 'Telefon',
            self::MEETING => 'Spotkanie',
            self::COMMENT => 'Komentarz',
            self::NOTE => 'Notatka',
            self::STAGE_CHANGE => 'Zmiana etapu',
            self::OTHER => 'Inna aktywność CRM',
        };
    }

    /**
     * Emoji/icon glyph used across the UI as a lightweight, dependency-free icon.
     */
    public function icon(): string
    {
        return match ($this) {
            self::EMAIL => '✉️',
            self::TASK => '✅',
            self::CALL => '📞',
            self::MEETING => '👥',
            self::COMMENT => '💬',
            self::NOTE => '📝',
            self::STAGE_CHANGE => '🔀',
            self::OTHER => '🔔',
        };
    }
}
