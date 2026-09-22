<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Enum\ActivityType;
use App\Domain\Enum\SchedulableState;
use DateTimeImmutable;

final class MeetingActivity extends Activity
{
    public function __construct(
        string $id,
        string $dealId,
        User $performedBy,
        string $title,
        ?string $description,
        public readonly SchedulableState $state,
        public readonly DateTimeImmutable $at,
        public readonly ?string $location = null,
    ) {
        parent::__construct($id, $dealId, ActivityType::MEETING, $performedBy, $title, $description);
    }

    public function isCompleted(): bool
    {
        return $this->state === SchedulableState::DONE;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->isCompleted() ? $this->at : null;
    }

    public function plannedAt(): ?DateTimeImmutable
    {
        return $this->isCompleted() ? null : $this->at;
    }
}
