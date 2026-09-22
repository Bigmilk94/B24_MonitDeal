<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Enum\ActivityType;
use App\Domain\Enum\SchedulableState;
use DateTimeImmutable;

/**
 * Catch-all CRM activity that isn't an email/task/call/meeting
 * (e.g. a generic CRM event), still able to be either completed or planned.
 */
final class OtherActivity extends Activity
{
    public function __construct(
        string $id,
        string $dealId,
        User $performedBy,
        string $title,
        ?string $description,
        public readonly SchedulableState $state,
        public readonly DateTimeImmutable $at,
    ) {
        parent::__construct($id, $dealId, ActivityType::OTHER, $performedBy, $title, $description);
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
