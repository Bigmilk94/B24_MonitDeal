<?php

declare(strict_types=1);

namespace App\Service\Crm;

use App\Bitrix24\Bitrix24Client;
use App\Bitrix24\Bitrix24FunnelService;
use App\Bitrix24\Model\Portal;
use App\Domain\Enum\DealSemantic;
use App\Domain\Enum\EmailDirection;
use App\Domain\Enum\SchedulableState;
use App\Domain\Enum\TaskState;
use App\Domain\Model\Activity;
use App\Domain\Model\CallActivity;
use App\Domain\Model\CommentActivity;
use App\Domain\Model\Company;
use App\Domain\Model\Contact;
use App\Domain\Model\Deal;
use App\Domain\Model\DealStage;
use App\Domain\Model\EmailActivity;
use App\Domain\Model\MeetingActivity;
use App\Domain\Model\NoteActivity;
use App\Domain\Model\OtherActivity;
use App\Domain\Model\TaskActivity;
use App\Domain\Model\User;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Real Bitrix24 CRM adapter: maps crm.deal.list / crm.company.get /
 * crm.contact.get / user.get / crm.activity.list / tasks.task.list into our
 * domain models for one specific installed portal.
 *
 * Field names and enum codes below are verified against Bitrix24's official
 * REST API documentation (github.com/bitrix24/b24restdocs), not guessed.
 * Known simplifications, worth revisiting later:
 * - Uses the single CONTACT_ID field (Bitrix24 docs mark it "deprecated,
 *   retained for compatibility" in favour of multi-contact CONTACT_IDS via
 *   crm.item.list) — fine for the common one-contact-per-deal case, but a
 *   deal with several linked contacts will only show the primary one here.
 * - crm.company.get/crm.contact.get are likewise marked deprecated in
 *   favour of the universal crm.item.get — still fully functional, just not
 *   Bitrix24's forward-looking recommendation.
 * - Every portal can still have custom fields or unusual funnel setups
 *   that don't show up in general documentation — if something looks off
 *   after a real install, that's the first place to check.
 */
final class Bitrix24CrmService implements CrmServiceInterface
{
    private const OWNER_TYPE_DEAL = 2;

    // Bitrix24 crm.activity TYPE_ID codes (crm_enum_activitytype).
    private const ACTIVITY_TYPE_MEETING = 1;
    private const ACTIVITY_TYPE_CALL = 2;
    private const ACTIVITY_TYPE_EMAIL = 4;

    private const PLACEHOLDER_COMPANY_ID = '__none_company__';
    private const PLACEHOLDER_CONTACT_ID = '__none_contact__';

    /** @var list<Deal>|null */
    private ?array $dealsCache = null;

    /** @var array<string, Company> */
    private array $companiesCache = [];

    /** @var array<string, Contact> */
    private array $contactsCache = [];

    /** @var array<string, User> */
    private array $usersCache = [];

    /** @var array<string, list<Activity>> */
    private array $activitiesCache = [];

    /** @var array<string, DealStage>|null */
    private ?array $stagesByStatusId = null;

    private readonly Bitrix24FunnelService $funnels;

    public function __construct(
        private readonly Bitrix24Client $client,
        private readonly Portal $portal,
        private readonly ClockInterface $clock,
    ) {
        $this->funnels = new Bitrix24FunnelService($this->client);
    }

    public function getDeals(): array
    {
        if ($this->dealsCache !== null) {
            return $this->dealsCache;
        }

        $deals = [];
        foreach ($this->funnels->listFunnels($this->portal) as $funnel) {
            if (!$this->portal->config->includesFunnel($funnel['id'])) {
                continue;
            }

            $rows = $this->client->callList($this->portal, 'crm.deal.list', [
                'filter' => ['CATEGORY_ID' => $funnel['id']],
                'select' => [
                    'ID', 'TITLE', 'COMPANY_ID', 'CONTACT_ID', 'ASSIGNED_BY_ID',
                    'STAGE_ID', 'CATEGORY_ID', 'OPPORTUNITY', 'CURRENCY_ID',
                    'DATE_CREATE', 'CLOSEDATE',
                ],
            ]);

            foreach ($rows as $row) {
                $deals[] = $this->mapDeal($row);
            }
        }

        return $this->dealsCache = $deals;
    }

    public function getDeal(string $id): ?Deal
    {
        foreach ($this->getDeals() as $deal) {
            if ($deal->id === $id) {
                return $deal;
            }
        }

        return null;
    }

    public function getCompany(string $id): ?Company
    {
        if ($id === self::PLACEHOLDER_COMPANY_ID) {
            return new Company($id, 'Brak firmy', '—');
        }
        if (isset($this->companiesCache[$id])) {
            return $this->companiesCache[$id];
        }

        $row = $this->client->call($this->portal, 'crm.company.get', ['id' => $id]);
        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->companiesCache[$id] = new Company(
            $id,
            (string) ($row['TITLE'] ?? "Firma #{$id}"),
            (string) ($row['INDUSTRY'] ?? '—'),
        );
    }

    public function getContact(string $id): ?Contact
    {
        if ($id === self::PLACEHOLDER_CONTACT_ID) {
            return new Contact($id, 'Brak kontaktu', self::PLACEHOLDER_COMPANY_ID, '', '');
        }
        if (isset($this->contactsCache[$id])) {
            return $this->contactsCache[$id];
        }

        $row = $this->client->call($this->portal, 'crm.contact.get', ['id' => $id]);
        if (!is_array($row) || $row === []) {
            return null;
        }

        $name = trim(($row['NAME'] ?? '') . ' ' . ($row['LAST_NAME'] ?? ''));

        return $this->contactsCache[$id] = new Contact(
            $id,
            $name !== '' ? $name : "Kontakt #{$id}",
            isset($row['COMPANY_ID']) && (int) $row['COMPANY_ID'] > 0 ? (string) $row['COMPANY_ID'] : self::PLACEHOLDER_COMPANY_ID,
            (string) ($row['EMAIL'][0]['VALUE'] ?? ''),
            (string) ($row['PHONE'][0]['VALUE'] ?? ''),
        );
    }

    public function getUser(string $id): ?User
    {
        if (isset($this->usersCache[$id])) {
            return $this->usersCache[$id];
        }

        $rows = $this->client->call($this->portal, 'user.get', ['FILTER' => ['ID' => $id]]);
        $row = is_array($rows) ? ($rows[0] ?? null) : null;
        if ($row === null) {
            return null;
        }

        $name = trim(($row['NAME'] ?? '') . ' ' . ($row['LAST_NAME'] ?? ''));
        $initials = implode('', array_map(
            static fn (string $p): string => mb_substr($p, 0, 1),
            array_filter(explode(' ', $name)),
        ));

        return $this->usersCache[$id] = new User($id, $name !== '' ? $name : "Użytkownik #{$id}", mb_strtoupper($initials));
    }

    public function getUsers(): array
    {
        $ids = [];
        foreach ($this->getDeals() as $deal) {
            $ids[$deal->ownerId] = true;
        }

        $users = [];
        foreach (array_keys($ids) as $id) {
            $user = $this->getUser($id);
            if ($user !== null) {
                $users[] = $user;
            }
        }

        return $users;
    }

    public function getDealActivities(string $dealId): array
    {
        if (isset($this->activitiesCache[$dealId])) {
            return $this->activitiesCache[$dealId];
        }

        $activities = [];

        // Bitrix24's own tutorial for "activities by deal" filters via the
        // BINDINGS array (an activity can be linked to several CRM entities
        // at once) rather than a flat OWNER_TYPE_ID/OWNER_ID pair.
        $activityRows = $this->client->callList($this->portal, 'crm.activity.list', [
            'filter' => [
                'BINDINGS' => [
                    ['OWNER_TYPE_ID' => self::OWNER_TYPE_DEAL, 'OWNER_ID' => (int) $dealId],
                ],
            ],
        ]);
        foreach ($activityRows as $row) {
            $activity = $this->mapCrmActivity($dealId, $row);
            if ($activity !== null) {
                $activities[] = $activity;
            }
        }

        $taskRows = $this->client->callList($this->portal, 'tasks.task.list', [
            'filter' => ['UF_CRM_TASK' => "D_{$dealId}"],
        ]);
        foreach ($taskRows as $row) {
            $activities[] = $this->mapTask($dealId, $row);
        }

        return $this->activitiesCache[$dealId] = $activities;
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

    private function stages(): array
    {
        return $this->stagesByStatusId ??= $this->funnels->allStagesByStatusId($this->portal);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapDeal(array $row): Deal
    {
        $stageId = (string) ($row['STAGE_ID'] ?? '');
        $stage = $this->stages()[$stageId] ?? new DealStage($stageId, $stageId !== '' ? $stageId : 'Nieznany etap', DealSemantic::OPEN);

        $companyId = (int) ($row['COMPANY_ID'] ?? 0) > 0 ? (string) $row['COMPANY_ID'] : self::PLACEHOLDER_COMPANY_ID;
        $contactId = (int) ($row['CONTACT_ID'] ?? 0) > 0 ? (string) $row['CONTACT_ID'] : self::PLACEHOLDER_CONTACT_ID;

        return new Deal(
            (string) $row['ID'],
            (string) ($row['TITLE'] ?? "Deal #{$row['ID']}"),
            $companyId,
            $contactId,
            (string) ($row['ASSIGNED_BY_ID'] ?? '0'),
            $stage,
            (float) ($row['OPPORTUNITY'] ?? 0),
            (string) ($row['CURRENCY_ID'] ?? 'PLN'),
            $this->parseDate((string) ($row['DATE_CREATE'] ?? 'now')) ?? $this->clock->now(),
            $this->parseDate((string) ($row['CLOSEDATE'] ?? '')),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapCrmActivity(string $dealId, array $row): ?Activity
    {
        $owner = $this->getUser((string) ($row['RESPONSIBLE_ID'] ?? '0')) ?? new User('0', 'Bitrix24', 'B');
        $title = (string) ($row['SUBJECT'] ?? 'Aktywność CRM');
        $description = isset($row['DESCRIPTION']) && $row['DESCRIPTION'] !== '' ? (string) $row['DESCRIPTION'] : null;
        $completed = (string) ($row['COMPLETED'] ?? 'N') === 'Y';
        // START_TIME is the moment the call/meeting actually happened (or is
        // scheduled for); END_TIME is derived from it (start + duration),
        // so START_TIME is the more meaningful "when" for our timeline.
        $at = $this->parseDate((string) ($row['START_TIME'] ?? $row['END_TIME'] ?? 'now')) ?? $this->clock->now();
        $id = (string) ($row['ID'] ?? uniqid('act_', true));

        return match ((int) ($row['TYPE_ID'] ?? 0)) {
            self::ACTIVITY_TYPE_CALL => new CallActivity(
                $id,
                $dealId,
                $owner,
                $title,
                $description,
                $completed ? SchedulableState::DONE : SchedulableState::PLANNED,
                $at,
            ),
            self::ACTIVITY_TYPE_MEETING => new MeetingActivity(
                $id,
                $dealId,
                $owner,
                $title,
                $description,
                $completed ? SchedulableState::DONE : SchedulableState::PLANNED,
                $at,
            ),
            self::ACTIVITY_TYPE_EMAIL => new EmailActivity(
                $id,
                $dealId,
                $owner,
                $title,
                $description,
                // crm_enum_activitydirection: 1 = incoming, 2 = outgoing.
                ((int) ($row['DIRECTION'] ?? 2)) === 2 ? EmailDirection::SENT : EmailDirection::RECEIVED,
                $at,
            ),
            default => $completed
                ? new CommentActivity($id, $dealId, $owner, $title, $description, $at)
                : new OtherActivity($id, $dealId, $owner, $title, $description, SchedulableState::PLANNED, $at),
        };
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapTask(string $dealId, array $row): TaskActivity
    {
        $owner = $this->getUser((string) ($row['responsibleId'] ?? $row['RESPONSIBLE_ID'] ?? '0')) ?? new User('0', 'Bitrix24', 'B');
        $title = (string) ($row['title'] ?? $row['TITLE'] ?? 'Zadanie');
        $description = $row['description'] ?? $row['DESCRIPTION'] ?? null;

        // tasks.task.list uses numeric STATUS; 5 = completed is the
        // documented value, everything else is treated as still pending.
        $status = (int) ($row['status'] ?? $row['STATUS'] ?? 2);
        $isCompleted = $status === 5;

        $deadline = $this->parseDate((string) ($row['deadline'] ?? $row['DEADLINE'] ?? '')) ?? $this->clock->now()->modify('+3 days');
        $closedDate = $this->parseDate((string) ($row['closedDate'] ?? $row['CLOSED_DATE'] ?? ''));

        return new TaskActivity(
            (string) ($row['id'] ?? $row['ID']),
            $dealId,
            $owner,
            $title,
            is_string($description) && $description !== '' ? $description : null,
            $isCompleted ? TaskState::COMPLETED : TaskState::PENDING,
            $deadline,
            $isCompleted ? ($closedDate ?? $this->clock->now()) : null,
        );
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
