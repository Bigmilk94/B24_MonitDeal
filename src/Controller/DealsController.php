<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Model\DealView;
use App\Service\Crm\CrmServiceInterface;
use App\Service\DealQueryService;
use App\Service\DealViewFactory;
use App\Support\View;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DealsController extends AbstractController
{
    public function __construct(
        private readonly CrmServiceInterface $crm,
        private readonly DealViewFactory $dealViewFactory,
        private readonly DealQueryService $query,
        private readonly ClockInterface $clock,
        private readonly View $view,
    ) {
    }

    #[Route('/deals', name: 'deals', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $now = $this->clock->now();

        $allDeals = $this->dealViewFactory->buildAll($now);
        $filtered = $this->query->apply($allDeals, $request->query->all(), $now);

        $owners = $this->crm->getUsers();
        $companiesById = [];
        foreach ($allDeals as $view) {
            $companiesById[$view->company->id] = $view->company;
        }
        $companies = array_values($companiesById);
        usort($companies, static fn ($a, $b) => $a->name <=> $b->name);

        // Stages aren't a fixed universe once real CRM data is involved
        // (Bitrix24 funnels have arbitrary, portal-specific stages) — the
        // filter dropdown offers whatever stages actually occur right now.
        $stagesByValue = [];
        foreach ($allDeals as $view) {
            $stagesByValue[$view->deal->stage->value] = $view->deal->stage;
        }
        $stages = array_values($stagesByValue);

        $html = $this->view->renderPage('deals/index', [
            'deals' => $filtered,
            'totalCount' => count($allDeals),
            'filteredCount' => count($filtered),
            'owners' => $owners,
            'companies' => $companies,
            'stages' => $stages,
            'activityTypes' => $this->query->activityTypes(),
            'params' => $request->query->all(),
            'now' => $now,
        ], 'deals', 'Deale');

        return new Response($html);
    }
}
