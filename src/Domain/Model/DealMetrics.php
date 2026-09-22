<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Enum\RecencySeverity;

/**
 * Everything about a deal that is *derived* from its activity history rather
 * than stored directly on the CRM record. Computed by DealMetricsCalculator
 * and never mutated afterwards.
 *
 * @phpstan-type ActivityCounts array{total: int, completed: int, open: int}
 */
final class DealMetrics
{
    /**
     * @param array{total:int, sent:int, received:int} $emailStats
     * @param array{total:int, completed:int, open:int, overdue:int} $taskStats
     * @param array{total:int, done:int, planned:int} $callStats
     * @param array{total:int, done:int, planned:int} $meetingStats
     * @param array{total:int, completed:int, planned:int} $crmActivityStats
     * @param list<string> $flags
     */
    public function __construct(
        public readonly int $daysSinceCreation,
        public readonly int $daysOnCurrentStage,
        public readonly ?Activity $lastActivity,
        public readonly string $lastActivityRelative,
        public readonly RecencySeverity $lastActivitySeverity,
        public readonly int $daysSinceLastActivity,
        public readonly array $emailStats,
        public readonly array $taskStats,
        public readonly array $callStats,
        public readonly array $meetingStats,
        public readonly array $crmActivityStats,
        public readonly int $totalActivities,
        public readonly int $completedActivities,
        public readonly int $openActivities,
        public readonly ?Activity $nextAction,
        public readonly array $flags,
    ) {
    }

    public function progressPercent(): ?int
    {
        if ($this->totalActivities === 0) {
            return null;
        }

        return (int) round(($this->completedActivities / $this->totalActivities) * 100);
    }

    public function hasFlag(string $flag): bool
    {
        return in_array($flag, $this->flags, true);
    }
}
