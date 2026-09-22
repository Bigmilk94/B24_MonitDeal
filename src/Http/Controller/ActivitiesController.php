<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Domain\Enum\ActivityType;
use App\Service\Crm\CrmServiceInterface;
use App\Support\Request;
use App\Support\View;
use DateTimeImmutable;

/**
 * Cross-deal activity feed — every logged activity across every deal,
 * newest first, with a type filter. Complements the per-deal timeline
 * on the deal detail page.
 */
final class ActivitiesController
{
    public function __construct(
        private readonly CrmServiceInterface $crm,
        private readonly View $view,
        private readonly DateTimeImmutable $now,
    ) {
    }

    public function index(Request $request): void
    {
        $dealsById = [];
        foreach ($this->crm->getDeals() as $deal) {
            $dealsById[$deal->id] = $deal;
        }

        $activities = [];
        foreach ($dealsById as $deal) {
            foreach ($this->crm->getDealActivities($deal->id) as $activity) {
                $activities[] = ['activity' => $activity, 'deal' => $deal];
            }
        }

        $typeFilter = (string) $request->query('activity_type', '');
        if ($typeFilter !== '') {
            $activities = array_values(array_filter(
                $activities,
                static fn (array $row) => $row['activity']->type->value === $typeFilter,
            ));
        }

        usort($activities, static fn (array $a, array $b) => $b['activity']->timelineAt() <=> $a['activity']->timelineAt());

        $this->view->renderPage('activities/index', [
            'rows' => array_slice($activities, 0, 150),
            'totalCount' => count($activities),
            'activityTypes' => ActivityType::cases(),
            'selectedType' => $typeFilter,
            'now' => $this->now,
        ], 'activities', 'Aktywności');
    }
}
