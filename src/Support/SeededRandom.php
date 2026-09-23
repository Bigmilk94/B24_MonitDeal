<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Minimal, dependency-free deterministic PRNG (xorshift32). Used instead of
 * PHP 8.2's Random\Randomizer so demo data generation only needs plain
 * integer/bitwise ops and stays compatible with older PHP 8.1 hosts.
 */
final class SeededRandom
{
    private int $state;

    public function __construct(int $seed)
    {
        // xorshift needs a non-zero seed to ever produce non-zero output.
        $this->state = $seed !== 0 ? $seed : 1;
    }

    /**
     * Uniformly-ish distributed integer in [$min, $max] (inclusive), good
     * enough for generating varied-looking demo data.
     */
    public function getInt(int $min, int $max): int
    {
        $x = $this->state;
        $x ^= ($x << 13) & 0xFFFFFFFF;
        $x ^= ($x >> 17);
        $x ^= ($x << 5) & 0xFFFFFFFF;
        $this->state = $x & 0xFFFFFFFF;

        $range = $max - $min + 1;

        return $min + ($this->state % $range);
    }
}
