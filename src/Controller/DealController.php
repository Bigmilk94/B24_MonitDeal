<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Enum\ActivityType;
use App\Service\Crm\CrmServiceInterface;
use App\Service\DealViewFactory;
use App\Support\View;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DealController extends AbstractController
{
    public function __construct(
        private readonly CrmServiceInterface $crm,
        private readonly DealViewFactory $dealViewFactory,
        private readonly ClockInterface $clock,
        private readonly View $view,
    ) {
    }

    #[Route('/deals/{id}', name: 'deal_show', methods: ['GET'])]
    public function show(string $id, Request $request): Response
    {
        $now = $this->clock->now();

        $deal = $this->crm->getDeal($id);
        if ($deal === null) {
            $html = $this->view->renderPage('errors/not_found', [], 'deals', 'Nie znaleziono deala');

            return new Response($html, 404);
        }

        $dealView = $this->dealViewFactory->build($deal, $now);
        $timeline = $this->crm->getDealTimeline($deal->id);

        $typeFilter = (string) $request->query->get('activity_type', '');
        if ($typeFilter !== '') {
            $timeline = array_values(array_filter(
                $timeline,
                static fn ($activity) => $activity->type->value === $typeFilter,
            ));
        }

        $html = $this->view->renderPage('deals/show', [
            'view' => $dealView,
            'timeline' => $timeline,
            'activityTypes' => ActivityType::cases(),
            'selectedType' => $typeFilter,
            'now' => $now,
        ], 'deals', $dealView->deal->title);

        return new Response($html);
    }
}
