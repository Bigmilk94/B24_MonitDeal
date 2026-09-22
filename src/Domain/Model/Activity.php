<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Enum\ActivityType;
use DateTimeImmutable;

/**
 * Common base for every kind of thing that can happen on a deal
 * (email, task, call, meeting, comment, note, stage change, other).
 *
 * Concrete subclasses carry their own type-specific fields (direction,
 * due date, duration, ...) but all expose the same three timestamps so
 * the timeline, "last activity" and "next action" logic can treat any
 * activity uniformly without knowing its concrete class.
 */
abstract class Activity
{
    public function __construct(
        public readonly string $id,
        public readonly string $dealId,
        public readonly ActivityType $type,
        public readonly User $performedBy,
        public readonly string $title,
        public readonly ?string $description,
    ) {
    }

    abstract public function isCompleted(): bool;

    /**
     * When this activity actually happened, or null if it's still only planned.
     */
    abstract public function completedAt(): ?DateTimeImmutable;

    /**
     * When this activity is due/scheduled, or null if it already happened
     * (or has no schedule, e.g. a comment).
     */
    abstract public function plannedAt(): ?DateTimeImmutable;

    /**
     * Single sortable timestamp used to place this activity on the timeline.
     */
    public function timelineAt(): DateTimeImmutable
    {
        return $this->completedAt() ?? $this->plannedAt();
    }
}
