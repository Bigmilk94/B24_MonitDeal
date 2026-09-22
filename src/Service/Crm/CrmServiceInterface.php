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

/**
 * Adapter boundary between the app and whatever CRM actually holds the data.
 *
 * Every controller and service in this app talks to a CRMServiceInterface,
 * never to a concrete implementation. Today that's MockCrmService with
 * generated demo data; swapping in a real Bitrix24 (or other CRM) client
 * later means writing one new class that implements this interface —
 * no UI, controller, or business-logic code needs to change.
 */
interface CrmServiceInterface
{
    /**
     * @return list<Deal>
     */
    public function getDeals(): array;

    public function getDeal(string $id): ?Deal;

    public function getCompany(string $id): ?Company;

    public function getContact(string $id): ?Contact;

    public function getUser(string $id): ?User;

    /**
     * @return list<User>
     */
    public function getUsers(): array;

    /**
     * All activities recorded against a deal, of any type, unsorted.
     *
     * @return list<Activity>
     */
    public function getDealActivities(string $dealId): array;

    /**
     * @return list<TaskActivity>
     */
    public function getDealTasks(string $dealId): array;

    /**
     * @return list<EmailActivity>
     */
    public function getDealEmails(string $dealId): array;

    /**
     * @return list<CallActivity>
     */
    public function getDealCalls(string $dealId): array;

    /**
     * @return list<MeetingActivity>
     */
    public function getDealMeetings(string $dealId): array;

    /**
     * Full activity history for a deal, sorted chronologically (newest first).
     *
     * @return list<Activity>
     */
    public function getDealTimeline(string $dealId): array;
}
