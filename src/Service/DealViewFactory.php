<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Model\Deal;
use App\Domain\Model\DealView;
use App\Service\Crm\CrmServiceInterface;
use DateTimeImmutable;
use RuntimeException;

/**
 * Assembles the presentation-ready DealView (deal + company + contact +
 * owner + computed metrics) that controllers pass to templates.
 */
final class DealViewFactory
{
    public function __construct(
        private readonly CrmServiceInterface $crm,
        private readonly DealMetricsCalculator $calculator,
    ) {
    }

    public function build(Deal $deal, DateTimeImmutable $now): DealView
    {
        $company = $this->crm->getCompany($deal->companyId);
        $contact = $this->crm->getContact($deal->contactId);
        $owner = $this->crm->getUser($deal->ownerId);

        if ($company === null || $contact === null || $owner === null) {
            throw new RuntimeException("Incomplete related data for deal {$deal->id}");
        }

        $activities = $this->crm->getDealActivities($deal->id);
        $metrics = $this->calculator->calculate($deal, $activities, $now);

        return new DealView($deal, $company, $contact, $owner, $metrics);
    }

    /**
     * @return list<DealView>
     */
    public function buildAll(DateTimeImmutable $now): array
    {
        return array_map(
            fn (Deal $deal) => $this->build($deal, $now),
            $this->crm->getDeals(),
        );
    }
}
