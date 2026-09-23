<?php

declare(strict_types=1);

namespace App\Service\Crm;

use App\Bitrix24\Bitrix24Client;
use App\Bitrix24\CurrentPortalResolver;
use App\Domain\Model\Activity;
use App\Domain\Model\CallActivity;
use App\Domain\Model\Company;
use App\Domain\Model\Contact;
use App\Domain\Model\Deal;
use App\Domain\Model\EmailActivity;
use App\Domain\Model\MeetingActivity;
use App\Domain\Model\TaskActivity;
use App\Domain\Model\User;
use Psr\Clock\ClockInterface;

/**
 * The CrmServiceInterface binding actually wired into the container (see
 * config/services.yaml). Picks the real Bitrix24CrmService when the current
 * request belongs to an installed portal, otherwise falls back to the demo
 * MockCrmService — so the same URL works both as a live Bitrix24 app and as
 * a standalone preview/showcase.
 */
final class PortalAwareCrmService implements CrmServiceInterface
{
    private readonly CrmServiceInterface $delegate;

    public function __construct(
        CurrentPortalResolver $portalResolver,
        Bitrix24Client $bitrix24Client,
        MockCrmService $mockCrmService,
        ClockInterface $clock,
    ) {
        $portal = $portalResolver->current();

        $this->delegate = $portal !== null
            ? new Bitrix24CrmService($bitrix24Client, $portal, $clock)
            : $mockCrmService;
    }

    public function getDeals(): array
    {
        return $this->delegate->getDeals();
    }

    public function getDeal(string $id): ?Deal
    {
        return $this->delegate->getDeal($id);
    }

    public function getCompany(string $id): ?Company
    {
        return $this->delegate->getCompany($id);
    }

    public function getContact(string $id): ?Contact
    {
        return $this->delegate->getContact($id);
    }

    public function getUser(string $id): ?User
    {
        return $this->delegate->getUser($id);
    }

    public function getUsers(): array
    {
        return $this->delegate->getUsers();
    }

    public function getDealActivities(string $dealId): array
    {
        return $this->delegate->getDealActivities($dealId);
    }

    public function getDealTasks(string $dealId): array
    {
        return $this->delegate->getDealTasks($dealId);
    }

    public function getDealEmails(string $dealId): array
    {
        return $this->delegate->getDealEmails($dealId);
    }

    public function getDealCalls(string $dealId): array
    {
        return $this->delegate->getDealCalls($dealId);
    }

    public function getDealMeetings(string $dealId): array
    {
        return $this->delegate->getDealMeetings($dealId);
    }

    public function getDealTimeline(string $dealId): array
    {
        return $this->delegate->getDealTimeline($dealId);
    }
}
