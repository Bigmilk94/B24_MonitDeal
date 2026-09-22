<?php

declare(strict_types=1);

namespace App\Domain\Enum;

/**
 * Non-aggressive classification of "how long since something happened",
 * used both for last-activity recency and for days-on-stage.
 */
enum RecencySeverity: string
{
    case NORMAL = 'normal';       // 0-2 days
    case WARNING = 'warning';     // 3-7 days
    case ATTENTION = 'attention'; // > 7 days

    public static function fromDays(int $days): self
    {
        return match (true) {
            $days <= 2 => self::NORMAL,
            $days <= 7 => self::WARNING,
            default => self::ATTENTION,
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::NORMAL => 'badge-normal',
            self::WARNING => 'badge-warning',
            self::ATTENTION => 'badge-attention',
        };
    }
}
