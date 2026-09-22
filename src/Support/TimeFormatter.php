<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

/**
 * Human-friendly, Polish relative timestamps ("15 minut temu", "wczoraj", ...).
 */
final class TimeFormatter
{
    private function __construct()
    {
    }

    public static function relative(DateTimeImmutable $from, DateTimeImmutable $now): string
    {
        $seconds = $now->getTimestamp() - $from->getTimestamp();

        if ($seconds < 0) {
            return self::relativeFuture(-$seconds);
        }

        if ($seconds < 60) {
            return 'przed chwilą';
        }

        $minutes = intdiv($seconds, 60);
        if ($minutes < 60) {
            return $minutes . ' ' . DateHelper::pluralPl($minutes, 'minutę', 'minuty', 'minut') . ' temu';
        }

        $hours = intdiv($minutes, 60);
        if ($hours < 24) {
            return $hours . ' ' . DateHelper::pluralPl($hours, 'godzinę', 'godziny', 'godzin') . ' temu';
        }

        $dayDiff = DateHelper::daysBetween($from, $now);
        if ($dayDiff <= 0) {
            return 'dzisiaj';
        }
        if ($dayDiff === 1) {
            return 'wczoraj';
        }

        return $dayDiff . ' ' . DateHelper::pluralPl($dayDiff, 'dzień', 'dni', 'dni') . ' temu';
    }

    private static function relativeFuture(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);
        if ($minutes < 60) {
            return 'za ' . $minutes . ' ' . DateHelper::pluralPl($minutes, 'minutę', 'minuty', 'minut');
        }

        $hours = intdiv($minutes, 60);
        if ($hours < 24) {
            return 'za ' . $hours . ' ' . DateHelper::pluralPl($hours, 'godzinę', 'godziny', 'godzin');
        }

        $days = intdiv($hours, 24);
        if ($days === 1) {
            return 'jutro';
        }

        return 'za ' . $days . ' ' . DateHelper::pluralPl($days, 'dzień', 'dni', 'dni');
    }

    public static function dateTime(DateTimeImmutable $dt): string
    {
        return $dt->format('d.m.Y, H:i');
    }

    public static function date(DateTimeImmutable $dt): string
    {
        return $dt->format('d.m.Y');
    }
}
