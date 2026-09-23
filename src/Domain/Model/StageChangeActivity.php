<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Enum\ActivityType;
use DateTimeImmutable;

final class StageChangeActivity extends Activity
{
    public function __construct(
        string $id,
        string $dealId,
        User $performedBy,
        string $title,
        ?string $description,
        public readonly DealStage $fromStage,
        public readonly DealStage $toStage,
        public readonly DateTimeImmutable $changedAt,
    ) {
        parent::__construct($id, $dealId, ActivityType::STAGE_CHANGE, $performedBy, $title, $description);
    }

    public function isCompleted(): bool
    {
        return true;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function plannedAt(): ?DateTimeImmutable
    {
        return null;
    }
}
