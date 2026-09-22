<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Enum\ActivityType;
use App\Domain\Enum\TaskState;
use DateTimeImmutable;

final class TaskActivity extends Activity
{
    public function __construct(
        string $id,
        string $dealId,
        User $performedBy,
        string $title,
        ?string $description,
        public readonly TaskState $state,
        public readonly DateTimeImmutable $dueAt,
        public readonly ?DateTimeImmutable $completedAtValue = null,
    ) {
        parent::__construct($id, $dealId, ActivityType::TASK, $performedBy, $title, $description);
    }

    public function isCompleted(): bool
    {
        return $this->state === TaskState::COMPLETED;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->isCompleted() ? $this->completedAtValue : null;
    }

    public function plannedAt(): ?DateTimeImmutable
    {
        return $this->isCompleted() ? null : $this->dueAt;
    }

    public function isOverdue(DateTimeImmutable $now): bool
    {
        return !$this->isCompleted() && $this->dueAt < $now;
    }
}
