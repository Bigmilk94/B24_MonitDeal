<?php

declare(strict_types=1);

namespace App\Domain\Model;

use DateTimeImmutable;

/**
 * Core deal record as it would come from a CRM (e.g. Bitrix24's crm.deal.list).
 * Deliberately holds only raw CRM fields — everything derived (days on stage,
 * activity stats, flags, next action, ...) lives in DealMetrics so this model
 * stays a faithful, swappable mirror of the source system.
 */
final class Deal
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $companyId,
        public readonly string $contactId,
        public readonly string $ownerId,
        public readonly DealStage $stage,
        public readonly float $value,
        public readonly string $currency,
        public readonly DateTimeImmutable $createdAt,
        public readonly ?DateTimeImmutable $expectedCloseAt,
    ) {
    }
}
