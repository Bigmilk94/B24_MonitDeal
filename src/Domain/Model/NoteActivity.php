<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Enum\ActivityType;
use DateTimeImmutable;

final class NoteActivity extends Activity
{
    public function __construct(
        string $id,
        string $dealId,
        User $performedBy,
        string $title,
        ?string $description,
        public readonly DateTimeImmutable $createdAt,
    ) {
        parent::__construct($id, $dealId, ActivityType::NOTE, $performedBy, $title, $description);
    }

    public function isCompleted(): bool
    {
        return true;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function plannedAt(): ?DateTimeImmutable
    {
        return null;
    }
}
