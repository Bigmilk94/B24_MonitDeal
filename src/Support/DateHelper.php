<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

final class DateHelper
{
    private function __construct()
    {
    }

    /**
     * Whole calendar days between two instants (always >= 0; $to is assumed
     * to be "now" or later than $from in normal use).
     */
    public static function daysBetween(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        $fromDate = $from->setTime(0, 0);
        $toDate = $to->setTime(0, 0);

        $diff = $fromDate->diff($toDate);

        return (int) $diff->days * ($toDate < $fromDate ? -1 : 1);
    }

    /**
     * Simplified Polish plural selection for counted nouns.
     */
    public static function pluralPl(int $n, string $one, string $few, string $many): string
    {
        $n = abs($n);
        if ($n === 1) {
            return $one;
        }

        $lastDigit = $n % 10;
        $lastTwoDigits = $n % 100;

        if ($lastDigit >= 2 && $lastDigit <= 4 && !($lastTwoDigits >= 12 && $lastTwoDigits <= 14)) {
            return $few;
        }

        return $many;
    }
}
