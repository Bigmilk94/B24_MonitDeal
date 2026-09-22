<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Enum\ActivityType;
use App\Service\Crm\CrmServiceInterface;
use App\Support\View;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Cross-deal activity feed — every logged activity across every deal,
 * newest first, with a type filter. Complements the per-deal timeline
 * on the deal detail page.
 */
final class ActivitiesController extends AbstractController
{
    public function __construct(
        private readonly CrmServiceInterface $crm,
        private readonly ClockInterface $clock,
        private readonly View $view,
    ) {
    }

    #[Route('/activities', name: 'activities', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $now = $this->clock->now();

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

        $typeFilter = (string) $request->query->get('activity_type', '');
        if ($typeFilter !== '') {
            $activities = array_values(array_filter(
                $activities,
                static fn (array $row) => $row['activity']->type->value === $typeFilter,
            ));
        }

        usort($activities, static fn (array $a, array $b) => $b['activity']->timelineAt() <=> $a['activity']->timelineAt());

        $html = $this->view->renderPage('activities/index', [
            'rows' => array_slice($activities, 0, 150),
            'totalCount' => count($activities),
            'activityTypes' => ActivityType::cases(),
            'selectedType' => $typeFilter,
            'now' => $now,
        ], 'activities', 'Aktywności');

        return new Response($html);
    }
}
