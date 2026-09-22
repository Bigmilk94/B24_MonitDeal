<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Enum\ActivityType;
use App\Domain\Enum\EmailDirection;
use DateTimeImmutable;

final class EmailActivity extends Activity
{
    public function __construct(
        string $id,
        string $dealId,
        User $performedBy,
        string $title,
        ?string $description,
        public readonly EmailDirection $direction,
        public readonly DateTimeImmutable $sentAt,
    ) {
        parent::__construct($id, $dealId, ActivityType::EMAIL, $performedBy, $title, $description);
    }

    public function isCompleted(): bool
    {
        // A logged email has, by definition, already been sent or received.
        return true;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function plannedAt(): ?DateTimeImmutable
    {
        return null;
    }
}
