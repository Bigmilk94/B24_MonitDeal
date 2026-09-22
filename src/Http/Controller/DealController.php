<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Domain\Enum\ActivityType;
use App\Service\Crm\CrmServiceInterface;
use App\Service\DealViewFactory;
use App\Support\Request;
use App\Support\View;
use DateTimeImmutable;

final class DealController
{
    public function __construct(
        private readonly CrmServiceInterface $crm,
        private readonly DealViewFactory $dealViewFactory,
        private readonly View $view,
        private readonly DateTimeImmutable $now,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params): void
    {
        $deal = $this->crm->getDeal($params['id'] ?? '');
        if ($deal === null) {
            http_response_code(404);
            $this->view->renderPage('errors/not_found', [], 'deals', 'Nie znaleziono deala');

            return;
        }

        $dealView = $this->dealViewFactory->build($deal, $this->now);
        $timeline = $this->crm->getDealTimeline($deal->id);

        $typeFilter = (string) $request->query('activity_type', '');
        if ($typeFilter !== '') {
            $timeline = array_values(array_filter(
                $timeline,
                static fn ($activity) => $activity->type->value === $typeFilter,
            ));
        }

        $this->view->renderPage('deals/show', [
            'view' => $dealView,
            'timeline' => $timeline,
            'activityTypes' => ActivityType::cases(),
            'selectedType' => $typeFilter,
            'now' => $this->now,
        ], 'deals', $dealView->deal->title);
    }
}
