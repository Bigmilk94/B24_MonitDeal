<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Model\DealView;
use App\Service\Crm\CrmServiceInterface;
use App\Service\DealQueryService;
use App\Service\DealViewFactory;
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

        return $this->render('deals/index.html.twig', [
            'activeNav' => 'deals',
            'pageTitle' => 'Deale',
            'deals' => $filtered,
            'totalCount' => count($allDeals),
            'filteredCount' => count($filtered),
            'owners' => $owners,
            'companies' => $companies,
            'stages' => $this->query->dealStages(),
            'activityTypes' => $this->query->activityTypes(),
            'params' => $request->query->all(),
        ]);
    }
}
