<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Domain\Model\DealView;
use App\Service\Crm\CrmServiceInterface;
use App\Service\DealQueryService;
use App\Service\DealViewFactory;
use App\Support\Request;
use App\Support\View;
use DateTimeImmutable;

final class DealsController
{
    public function __construct(
        private readonly CrmServiceInterface $crm,
        private readonly DealViewFactory $dealViewFactory,
        private readonly DealQueryService $query,
        private readonly View $view,
        private readonly DateTimeImmutable $now,
    ) {
    }

    public function index(Request $request): void
    {
        $allDeals = $this->dealViewFactory->buildAll($this->now);
        $filtered = $this->query->apply($allDeals, $request->query, $this->now);

        $owners = $this->crm->getUsers();
        $companiesById = [];
        foreach ($allDeals as $view) {
            $companiesById[$view->company->id] = $view->company;
        }
        $companies = array_values($companiesById);
        usort($companies, static fn ($a, $b) => $a->name <=> $b->name);

        $this->view->renderPage('deals/index', [
            'deals' => $filtered,
            'totalCount' => count($allDeals),
            'filteredCount' => count($filtered),
            'owners' => $owners,
            'companies' => $companies,
            'stages' => $this->query->dealStages(),
            'activityTypes' => $this->query->activityTypes(),
            'params' => $request->query,
            'now' => $this->now,
        ], 'deals', 'Deale');
    }
}
