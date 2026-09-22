<?php

declare(strict_types=1);

namespace App\Service\Crm;

use App\Domain\Model\Activity;
use App\Domain\Model\CallActivity;
use App\Domain\Model\Company;
use App\Domain\Model\Contact;
use App\Domain\Model\Deal;
use App\Domain\Model\EmailActivity;
use App\Domain\Model\MeetingActivity;
use App\Domain\Model\TaskActivity;
use App\Domain\Model\User;
use DateTimeImmutable;

/**
 * Demo CRM adapter: generates a deterministic, realistic dataset in memory
 * instead of calling a real CRM's API. Implements the same contract a
 * Bitrix24 (or other) adapter would, so it is a drop-in stand-in for
 * production use — see CrmServiceInterface for the swap point.
 */
final class MockCrmService implements CrmServiceInterface
{
    /** @var list<User> */
    private array $users;

    /** @var array<string, Company> */
    private array $companiesById = [];

    /** @var array<string, Contact> */
    private array $contactsById = [];

    /** @var list<Deal> */
    private array $deals;

    /** @var array<string, Deal> */
    private array $dealsById = [];

    /** @var array<string, list<Activity>> */
    private array $activitiesByDeal;

    public function __construct(?DateTimeImmutable $now = null)
    {
        $data = (new DemoDataGenerator($now))->generate();

        $this->users = $data['users'];
        $this->deals = $data['deals'];
        $this->activitiesByDeal = $data['activitiesByDeal'];

        foreach ($data['companies'] as $company) {
            $this->companiesById[$company->id] = $company;
        }
        foreach ($data['contacts'] as $contact) {
            $this->contactsById[$contact->id] = $contact;
        }
        foreach ($this->deals as $deal) {
            $this->dealsById[$deal->id] = $deal;
        }
    }

    public function getDeals(): array
    {
        return $this->deals;
    }

    public function getDeal(string $id): ?Deal
    {
        return $this->dealsById[$id] ?? null;
    }

    public function getCompany(string $id): ?Company
    {
        return $this->companiesById[$id] ?? null;
    }

    public function getContact(string $id): ?Contact
    {
        return $this->contactsById[$id] ?? null;
    }

    public function getUser(string $id): ?User
    {
        foreach ($this->users as $user) {
            if ($user->id === $id) {
                return $user;
            }
        }

        return null;
    }

    public function getUsers(): array
    {
        return $this->users;
    }

    public function getDealActivities(string $dealId): array
    {
        return $this->activitiesByDeal[$dealId] ?? [];
    }

    public function getDealTasks(string $dealId): array
    {
        return array_values(array_filter(
            $this->getDealActivities($dealId),
            static fn (Activity $a): bool => $a instanceof TaskActivity,
        ));
    }

    public function getDealEmails(string $dealId): array
    {
        return array_values(array_filter(
            $this->getDealActivities($dealId),
            static fn (Activity $a): bool => $a instanceof EmailActivity,
        ));
    }

    public function getDealCalls(string $dealId): array
    {
        return array_values(array_filter(
            $this->getDealActivities($dealId),
            static fn (Activity $a): bool => $a instanceof CallActivity,
        ));
    }

    public function getDealMeetings(string $dealId): array
    {
        return array_values(array_filter(
            $this->getDealActivities($dealId),
            static fn (Activity $a): bool => $a instanceof MeetingActivity,
        ));
    }

    public function getDealTimeline(string $dealId): array
    {
        $activities = $this->getDealActivities($dealId);

        usort($activities, static fn (Activity $a, Activity $b): int => $b->timelineAt() <=> $a->timelineAt());

        return $activities;
    }
}
