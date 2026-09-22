<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Model\DealView;
use App\Service\Crm\CrmServiceInterface;
use App\Service\DealViewFactory;
use App\Support\DateHelper;
use App\Support\View;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    private const ATTENTION_FLAGS = [
        'Brak aktywności od 7 dni',
        'Brak zaplanowanego następnego działania',
        'Zadanie przeterminowane',
        'Duża liczba otwartych zadań',
    ];

    public function __construct(
        private readonly CrmServiceInterface $crm,
        private readonly DealViewFactory $dealViewFactory,
        private readonly ClockInterface $clock,
        private readonly View $view,
    ) {
    }

    #[Route('/', name: 'dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $now = $this->clock->now();

        $allDeals = $this->dealViewFactory->buildAll($now);
        $activeDeals = array_values(array_filter($allDeals, static fn (DealView $v) => $v->deal->stage->isOpen()));

        $totalActiveValue = array_sum(array_map(static fn (DealView $v) => $v->deal->value, $activeDeals));
        $staleCount = count(array_filter($activeDeals, static fn (DealView $v) => $v->metrics->daysSinceLastActivity > 7));
        $noNextActionCount = count(array_filter($activeDeals, static fn (DealView $v) => $v->metrics->nextAction === null));
        $overdueDealsCount = count(array_filter($activeDeals, static fn (DealView $v) => $v->metrics->taskStats['overdue'] > 0));

        $doneToday = 0;
        $plannedToday = 0;
        foreach ($allDeals as $view) {
            foreach ($this->crm->getDealActivities($view->deal->id) as $activity) {
                $completedAt = $activity->completedAt();
                if ($completedAt !== null && DateHelper::daysBetween($completedAt, $now) === 0) {
                    $doneToday++;
                }
                $plannedAt = $activity->plannedAt();
                if ($plannedAt !== null && DateHelper::daysBetween($plannedAt, $now) === 0) {
                    $plannedToday++;
                }
            }
        }

        $needsAttention = array_values(array_filter(
            $activeDeals,
            static fn (DealView $v) => array_intersect($v->metrics->flags, self::ATTENTION_FLAGS) !== [],
        ));
        usort($needsAttention, static function (DealView $a, DealView $b): int {
            $bySeverity = count(array_intersect($b->metrics->flags, self::ATTENTION_FLAGS))
                <=> count(array_intersect($a->metrics->flags, self::ATTENTION_FLAGS));

            return $bySeverity !== 0 ? $bySeverity : $b->metrics->daysSinceLastActivity <=> $a->metrics->daysSinceLastActivity;
        });

        $html = $this->view->renderPage('dashboard', [
            'activeDealsCount' => count($activeDeals),
            'totalActiveValue' => $totalActiveValue,
            'staleCount' => $staleCount,
            'noNextActionCount' => $noNextActionCount,
            'overdueDealsCount' => $overdueDealsCount,
            'doneToday' => $doneToday,
            'plannedToday' => $plannedToday,
            'needsAttention' => array_slice($needsAttention, 0, 12),
        ], 'dashboard', 'Dashboard');

        return new Response($html);
    }
}
