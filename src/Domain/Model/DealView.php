<?php

declare(strict_types=1);

namespace App\Domain\Model;

/**
 * Presentation-ready aggregate: a Deal plus its related Company/Contact/owner
 * and its computed DealMetrics. This is what controllers hand to templates —
 * templates never reach back into the CRM service themselves.
 */
final class DealView
{
    public function __construct(
        public readonly Deal $deal,
        public readonly Company $company,
        public readonly Contact $contact,
        public readonly User $owner,
        public readonly DealMetrics $metrics,
    ) {
    }
}
