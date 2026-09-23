<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Enum\DealSemantic;

/**
 * A deal stage as an actual CRM defines it: an arbitrary id and label
 * (custom per Bitrix24 portal/funnel — there is no fixed universe of
 * stages once real data is involved) plus its fixed DealSemantic.
 *
 * Deliberately a plain value object, not a native PHP enum: enums require
 * a fixed set of cases known at compile time, but real stages are only
 * known once a portal's funnels are fetched at runtime. Two DealStage
 * instances for the "same" stage are therefore compared by value
 * (`->value ===`), never by object identity (`===`).
 */
final class DealStage
{
    public function __construct(
        public readonly string $value,
        private readonly string $labelText,
        public readonly DealSemantic $semantic,
    ) {
    }

    public function label(): string
    {
        return $this->labelText;
    }

    public function isOpen(): bool
    {
        return $this->semantic === DealSemantic::OPEN;
    }

    public function isWon(): bool
    {
        return $this->semantic === DealSemantic::WON;
    }

    public function isLost(): bool
    {
        return $this->semantic === DealSemantic::LOST;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
