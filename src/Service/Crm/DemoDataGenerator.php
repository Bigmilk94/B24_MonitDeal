<?php

declare(strict_types=1);

namespace App\Service\Crm;

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
use App\Domain\Model\StageChangeActivity;
use App\Domain\Model\TaskActivity;
use App\Domain\Model\User;
use App\Support\SeededRandom;
use DateTimeImmutable;

/**
 * Builds a deterministic, realistic-looking demo dataset: companies,
 * contacts, owners, deals and their full activity history.
 *
 * The dataset is generated fresh on every request (no storage), but always
 * produces the same output because it is driven by a seeded RNG — so
 * dashboard counts stay stable while still being *computed*, not hardcoded,
 * from deal + activity records exactly the way a real CRM adapter would.
 */
final class DemoDataGenerator
{
    private const SEED = 20260916;

    private readonly SeededRandom $rng;
    private readonly DateTimeImmutable $now;

    /** @var list<User> */
    private array $users = [];

    /** @var list<Company> */
    private array $companies = [];

    /** @var list<Contact> */
    private array $contacts = [];

    /** @var list<Deal> */
    private array $deals = [];

    /** @var array<string, list<Activity>> */
    private array $activitiesByDeal = [];

    private int $activitySeq = 1;

    /** @var array<string, DealStage> Demo funnel stages, keyed by their (fake) STATUS_ID. */
    private readonly array $stages;

    public function __construct(?DateTimeImmutable $now = null)
    {
        $this->rng = new SeededRandom(self::SEED);
        $this->now = $now ?? new DateTimeImmutable('now');
        $this->stages = [
            'new' => new DealStage('new', 'Nowy', DealSemantic::OPEN),
            'qualification' => new DealStage('qualification', 'Kwalifikacja', DealSemantic::OPEN),
            'proposal' => new DealStage('proposal', 'Oferta wysłana', DealSemantic::OPEN),
            'negotiation' => new DealStage('negotiation', 'Negocjacje', DealSemantic::OPEN),
            'won' => new DealStage('won', 'Wygrany', DealSemantic::WON),
            'lost' => new DealStage('lost', 'Przegrany', DealSemantic::LOST),
        ];
    }

    /**
     * @return array{
     *     users: list<User>,
     *     companies: list<Company>,
     *     contacts: list<Contact>,
     *     deals: list<Deal>,
     *     activitiesByDeal: array<string, list<Activity>>,
     * }
     */
    public function generate(): array
    {
        $this->buildUsers();
        $this->buildCompaniesAndContacts();
        $this->buildDealsWithActivities();

        return [
            'users' => $this->users,
            'companies' => $this->companies,
            'contacts' => $this->contacts,
            'deals' => $this->deals,
            'activitiesByDeal' => $this->activitiesByDeal,
        ];
    }

    private function buildUsers(): void
    {
        $names = [
            'Anna Kowalska',
            'Jan Nowak',
            'Piotr Wiśniewski',
            'Katarzyna Lewandowska',
            'Marek Zieliński',
            'Tomasz Dąbrowski',
        ];

        foreach ($names as $i => $name) {
            $initials = implode('', array_map(static fn (string $p) => mb_substr($p, 0, 1), explode(' ', $name)));
            $this->users[] = new User('u' . ($i + 1), $name, $initials);
        }
    }

    private function buildCompaniesAndContacts(): void
    {
        $companies = [
            ['TechNova Sp. z o.o.', 'IT i oprogramowanie'],
            ['BudMax S.A.', 'Budownictwo'],
            ['FreshFood Logistics Sp. z o.o.', 'Logistyka spożywcza'],
            ['MediCare Group', 'Ochrona zdrowia'],
            ['GreenEnergy Polska', 'Energetyka odnawialna'],
            ['StalPro Przemysł S.A.', 'Przemysł ciężki'],
            ['EduSmart Sp. z o.o.', 'Edukacja'],
            ['FinLine Consulting', 'Usługi finansowe'],
        ];

        foreach ($companies as $i => [$name, $industry]) {
            $this->companies[] = new Company('c' . ($i + 1), $name, $industry);
        }

        $contactDefs = [
            ['ct1', 'Michał Grabowski', 'c1'],
            ['ct2', 'Aleksandra Wójcik', 'c1'],
            ['ct3', 'Robert Kamiński', 'c2'],
            ['ct4', 'Magdalena Szymańska', 'c3'],
            ['ct5', 'Krzysztof Woźniak', 'c4'],
            ['ct6', 'Ewa Kozłowska', 'c5'],
            ['ct7', 'Paweł Jankowski', 'c6'],
            ['ct8', 'Natalia Mazur', 'c6'],
            ['ct9', 'Grzegorz Kwiatkowski', 'c7'],
            ['ct10', 'Joanna Krawczyk', 'c8'],
        ];

        foreach ($contactDefs as [$id, $name, $companyId]) {
            $slug = $this->slugify($name);
            $companyName = $this->companyById($companyId)->name;
            $domain = strtolower(preg_replace('/[^a-z0-9]+/i', '', explode(' ', $companyName)[0])) . '.pl';
            $this->contacts[] = new Contact(
                $id,
                $name,
                $companyId,
                "{$slug}@{$domain}",
                '+48 ' . $this->rng->getInt(500, 799) . ' ' . $this->rng->getInt(100, 999) . ' ' . $this->rng->getInt(100, 999),
            );
        }
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: DealStage, 4: string, int}
     */
    private function dealBlueprints(): array
    {
        $s = $this->stages;
        // [title, companyId, contactId, stage, ownerIndex]
        return [
            ['Wdrożenie systemu ERP', 'c1', 'ct1', $s['negotiation'], 0],
            ['Modernizacja infrastruktury IT', 'c1', 'ct2', $s['proposal'], 1],
            ['Budowa hali magazynowej', 'c2', 'ct3', $s['qualification'], 2],
            ['Remont biurowca przy ul. Długiej', 'c2', 'ct3', $s['new'], 3],
            ['Rozbudowa floty chłodni', 'c3', 'ct4', $s['negotiation'], 4],
            ['Wdrożenie WMS w centrum dystrybucji', 'c3', 'ct4', $s['proposal'], 5],
            ['Dostawa sprzętu diagnostycznego', 'c4', 'ct5', $s['qualification'], 0],
            ['System zarządzania dokumentacją medyczną', 'c4', 'ct5', $s['new'], 1],
            ['Instalacja paneli fotowoltaicznych', 'c5', 'ct6', $s['negotiation'], 2],
            ['Farma wiatrowa — etap konsultacji', 'c5', 'ct6', $s['qualification'], 3],
            ['Modernizacja linii produkcyjnej', 'c6', 'ct7', $s['proposal'], 4],
            ['Audyt bezpieczeństwa maszyn', 'c6', 'ct8', $s['new'], 5],
            ['Wdrożenie platformy e-learningowej', 'c7', 'ct9', $s['negotiation'], 0],
            ['Licencje na oprogramowanie edukacyjne', 'c7', 'ct9', $s['proposal'], 1],
            ['Doradztwo podatkowe na 2026 rok', 'c8', 'ct10', $s['qualification'], 2],
            ['Restrukturyzacja finansowa spółki', 'c8', 'ct10', $s['new'], 3],
            ['Integracja CRM z systemem księgowym', 'c1', 'ct1', $s['qualification'], 4],
            ['Zakup serwerów dla data center', 'c1', 'ct2', $s['negotiation'], 5],
            ['Budowa parkingu wielopoziomowego', 'c2', 'ct3', $s['proposal'], 0],
            ['Transport chłodniczy — kontrakt roczny', 'c3', 'ct4', $s['new'], 1],
            ['Sprzęt do sal operacyjnych', 'c4', 'ct5', $s['negotiation'], 2],
            ['Magazyn energii dla zakładu produkcyjnego', 'c5', 'ct6', $s['won'], 3],
            ['Przegląd techniczny pieców hutniczych', 'c6', 'ct7', $s['won'], 4],
            ['Program szkoleń wewnętrznych', 'c7', 'ct9', $s['lost'], 5],
        ];
    }

    /**
     * Per-deal activity profile: how busy the deal is, how long ago its
     * last activity happened (null = no activity at all), how many overdue
     * tasks it should carry, and whether it should have a future-dated
     * next action. Indexes line up 1:1 with dealBlueprints().
     *
     * @return list<array{level:string, lastActivityDaysAgo:?int, overdueTasks:int, nextAction:bool}>
     */
    private function activityProfiles(): array
    {
        return [
            ['level' => 'high',   'lastActivityDaysAgo' => 0,  'overdueTasks' => 0, 'nextAction' => true],
            ['level' => 'medium', 'lastActivityDaysAgo' => 1,  'overdueTasks' => 0, 'nextAction' => true],
            ['level' => 'medium', 'lastActivityDaysAgo' => 4,  'overdueTasks' => 1, 'nextAction' => true],
            ['level' => 'low',    'lastActivityDaysAgo' => 9,  'overdueTasks' => 0, 'nextAction' => false],
            ['level' => 'high',   'lastActivityDaysAgo' => 0,  'overdueTasks' => 0, 'nextAction' => true],
            ['level' => 'medium', 'lastActivityDaysAgo' => 2,  'overdueTasks' => 0, 'nextAction' => true],
            ['level' => 'low',    'lastActivityDaysAgo' => 15, 'overdueTasks' => 0, 'nextAction' => false],
            ['level' => 'medium', 'lastActivityDaysAgo' => 6,  'overdueTasks' => 2, 'nextAction' => true],
            ['level' => 'high',   'lastActivityDaysAgo' => 0,  'overdueTasks' => 0, 'nextAction' => true],
            ['level' => 'none',   'lastActivityDaysAgo' => null, 'overdueTasks' => 0, 'nextAction' => false],
            ['level' => 'medium', 'lastActivityDaysAgo' => 3,  'overdueTasks' => 1, 'nextAction' => true],
            ['level' => 'low',    'lastActivityDaysAgo' => 20, 'overdueTasks' => 0, 'nextAction' => false],
            ['level' => 'high',   'lastActivityDaysAgo' => 1,  'overdueTasks' => 0, 'nextAction' => true],
            ['level' => 'medium', 'lastActivityDaysAgo' => 5,  'overdueTasks' => 0, 'nextAction' => false],
            ['level' => 'medium', 'lastActivityDaysAgo' => 0,  'overdueTasks' => 0, 'nextAction' => true],
            ['level' => 'low',    'lastActivityDaysAgo' => 10, 'overdueTasks' => 3, 'nextAction' => true],
            ['level' => 'none',   'lastActivityDaysAgo' => null, 'overdueTasks' => 0, 'nextAction' => false],
            ['level' => 'high',   'lastActivityDaysAgo' => 0,  'overdueTasks' => 0, 'nextAction' => true],
            ['level' => 'medium', 'lastActivityDaysAgo' => 7,  'overdueTasks' => 0, 'nextAction' => true],
            ['level' => 'medium', 'lastActivityDaysAgo' => 2,  'overdueTasks' => 0, 'nextAction' => false],
            ['level' => 'low',    'lastActivityDaysAgo' => 13, 'overdueTasks' => 1, 'nextAction' => false],
            ['level' => 'low',    'lastActivityDaysAgo' => 32, 'overdueTasks' => 0, 'nextAction' => false],
            ['level' => 'low',    'lastActivityDaysAgo' => 46, 'overdueTasks' => 0, 'nextAction' => false],
            ['level' => 'low',    'lastActivityDaysAgo' => 61, 'overdueTasks' => 0, 'nextAction' => false],
        ];
    }

    private function buildDealsWithActivities(): void
    {
        $blueprints = $this->dealBlueprints();
        $profiles = $this->activityProfiles();

        $emailSubjects = [
            'Przesłanie oferty handlowej',
            'Przesłano klientowi poprawioną ofertę',
            'Pytania techniczne do specyfikacji',
            'Potwierdzenie warunków płatności',
            'Harmonogram wdrożenia — projekt',
            'Prośba o dodatkowe materiały',
            'Podsumowanie rozmowy telefonicznej',
            'Aktualizacja kosztorysu',
            'Umowa do podpisu',
            'Pytanie o termin realizacji',
        ];
        $taskSubjects = [
            'Przygotowanie kalkulacji kosztów',
            'Weryfikacja specyfikacji technicznej',
            'Przygotowanie umowy',
            'Kontakt z działem prawnym',
            'Aktualizacja karty klienta w CRM',
            'Przygotowanie prezentacji dla zarządu',
            'Zebranie referencji od klienta',
            'Przygotowanie harmonogramu wdrożenia',
        ];
        $callSubjects = [
            'Rozmowa dot. warunków handlowych',
            'Telefon w sprawie terminu wdrożenia',
            'Rozmowa z klientem dotycząca warunków wdrożenia',
            'Ustalenie zakresu prac',
            'Telefon do klienta — status realizacji',
        ];
        $meetingSubjects = [
            'Spotkanie prezentujące ofertę',
            'Spotkanie negocjacyjne',
            'Spotkanie wdrożeniowe u klienta',
            'Warsztat wymagań',
        ];

        foreach ($blueprints as $i => [$title, $companyId, $contactId, $stage, $ownerIdx]) {
            $profile = $profiles[$i];
            $dealId = 'd' . ($i + 1);
            $owner = $this->users[$ownerIdx];

            $lastActivityDaysAgo = $profile['lastActivityDaysAgo'];
            $isClosed = !$stage->isOpen();

            $createdDaysAgo = ($lastActivityDaysAgo ?? 30) + $this->rng->getInt(20, 150);
            $createdAt = $this->daysAgo($createdDaysAgo, $this->rng->getInt(8, 17), $this->rng->getInt(0, 59));

            $closeOffset = $this->rng->getInt(30, 120);
            $expectedCloseAt = $isClosed
                ? $this->daysAgo(max(($lastActivityDaysAgo ?? 30) - $this->rng->getInt(0, 10), 1))
                : $createdAt->modify("+{$closeOffset} days");

            $value = (float) $this->rng->getInt(8, 460) * 1000 + $this->rng->getInt(0, 9) * 100;

            $deal = new Deal(
                $dealId,
                $title,
                $companyId,
                $contactId,
                $owner->id,
                $stage,
                $value,
                'PLN',
                $createdAt,
                $expectedCloseAt,
            );
            $this->deals[] = $deal;

            $activities = [];

            // Stage-change history: give every deal that isn't brand new a
            // believable "moved to current stage N days ago" event.
            $openStages = [
                $this->stages['new'],
                $this->stages['qualification'],
                $this->stages['proposal'],
                $this->stages['negotiation'],
            ];
            $currentStageOrdinal = array_search($stage, $openStages, true);
            if ($currentStageOrdinal === false) {
                $currentStageOrdinal = count($openStages) - 1;
            }
            if ($profile['level'] !== 'none' && ($currentStageOrdinal > 0 || $isClosed)) {
                $maxDaysAgo = max($createdDaysAgo - 1, 1);
                $stageChangedDaysAgo = min($maxDaysAgo, $this->rng->getInt(2, 45));
                $fromStage = $currentStageOrdinal > 0 ? $openStages[$currentStageOrdinal - 1] : $this->stages['new'];
                $activities[] = new StageChangeActivity(
                    $this->nextActivityId(),
                    $dealId,
                    $owner,
                    "Zmiana etapu: {$fromStage->label()} → {$stage->label()}",
                    null,
                    $fromStage,
                    $stage,
                    $this->daysAgo($stageChangedDaysAgo, $this->rng->getInt(8, 17)),
                );
            }

            if ($profile['level'] !== 'none') {
                $counts = match ($profile['level']) {
                    'high' => ['emails' => [8, 14], 'tasks' => [5, 8], 'calls' => [2, 4], 'meetings' => [1, 3]],
                    'medium' => ['emails' => [3, 7], 'tasks' => [2, 5], 'calls' => [1, 3], 'meetings' => [0, 2]],
                    default => ['emails' => [0, 3], 'tasks' => [1, 3], 'calls' => [0, 1], 'meetings' => [0, 1]],
                };

                $floorDaysAgo = $lastActivityDaysAgo ?? 0;
                $ceilDaysAgo = max($createdDaysAgo - 1, $floorDaysAgo + 1);

                $numEmails = $this->rng->getInt(...$counts['emails']);
                for ($e = 0; $e < $numEmails; $e++) {
                    $daysAgo = $e === 0 ? $floorDaysAgo : $this->rng->getInt($floorDaysAgo, $ceilDaysAgo);
                    $direction = $this->rng->getInt(0, 1) === 0 ? EmailDirection::SENT : EmailDirection::RECEIVED;
                    $subject = $emailSubjects[$this->rng->getInt(0, count($emailSubjects) - 1)];
                    $activities[] = new EmailActivity(
                        $this->nextActivityId(),
                        $dealId,
                        $owner,
                        $subject,
                        $direction === EmailDirection::SENT
                            ? "Wysłano wiadomość do klienta: \"{$subject}\""
                            : "Otrzymano wiadomość od klienta: \"{$subject}\"",
                        $direction,
                        $this->daysAgo($daysAgo, $this->rng->getInt(8, 18), $this->rng->getInt(0, 59)),
                    );
                }

                $numTasks = max($this->rng->getInt(...$counts['tasks']), $profile['overdueTasks']);
                $overdueLeft = $profile['overdueTasks'];
                for ($t = 0; $t < $numTasks; $t++) {
                    $subject = $taskSubjects[$this->rng->getInt(0, count($taskSubjects) - 1)];
                    if ($overdueLeft > 0) {
                        $overdueLeft--;
                        $dueDaysAgo = $this->rng->getInt(1, 12);
                        $activities[] = new TaskActivity(
                            $this->nextActivityId(),
                            $dealId,
                            $owner,
                            $subject,
                            null,
                            TaskState::PENDING,
                            $this->daysAgo($dueDaysAgo, 12),
                        );
                        continue;
                    }

                    $makeFutureOpen = $profile['nextAction'] && $t === $numTasks - 1;
                    if ($makeFutureOpen) {
                        $dueInDays = $this->rng->getInt(1, 10);
                        $activities[] = new TaskActivity(
                            $this->nextActivityId(),
                            $dealId,
                            $owner,
                            $subject,
                            null,
                            TaskState::PENDING,
                            $this->now->modify("+{$dueInDays} days")->setTime($this->rng->getInt(8, 16), 0),
                        );
                        continue;
                    }

                    $completedDaysAgo = $this->rng->getInt($floorDaysAgo, $ceilDaysAgo);
                    $activities[] = new TaskActivity(
                        $this->nextActivityId(),
                        $dealId,
                        $owner,
                        $subject,
                        null,
                        TaskState::COMPLETED,
                        $this->daysAgo($completedDaysAgo + 1, 10),
                        $this->daysAgo($completedDaysAgo, $this->rng->getInt(8, 17)),
                    );
                }

                $numCalls = $this->rng->getInt(...$counts['calls']);
                for ($c = 0; $c < $numCalls; $c++) {
                    $subject = $callSubjects[$this->rng->getInt(0, count($callSubjects) - 1)];
                    $planFuture = $profile['nextAction'] && $c === 0 && $numCalls === 1 && $this->rng->getInt(0, 1) === 1;
                    if ($planFuture) {
                        $inDays = $this->rng->getInt(1, 7);
                        $activities[] = new CallActivity(
                            $this->nextActivityId(),
                            $dealId,
                            $owner,
                            $subject,
                            null,
                            SchedulableState::PLANNED,
                            $this->now->modify("+{$inDays} days")->setTime($this->rng->getInt(9, 15), 0),
                        );
                        continue;
                    }
                    $daysAgo = $this->rng->getInt($floorDaysAgo, $ceilDaysAgo);
                    $activities[] = new CallActivity(
                        $this->nextActivityId(),
                        $dealId,
                        $owner,
                        $subject,
                        null,
                        SchedulableState::DONE,
                        $this->daysAgo($daysAgo, $this->rng->getInt(8, 17)),
                        $this->rng->getInt(5, 35),
                    );
                }

                $numMeetings = $this->rng->getInt(...$counts['meetings']);
                for ($m = 0; $m < $numMeetings; $m++) {
                    $subject = $meetingSubjects[$this->rng->getInt(0, count($meetingSubjects) - 1)];
                    $planFuture = $profile['nextAction'] && $m === $numMeetings - 1 && $this->rng->getInt(0, 2) === 0;
                    if ($planFuture) {
                        $inDays = $this->rng->getInt(2, 12);
                        $activities[] = new MeetingActivity(
                            $this->nextActivityId(),
                            $dealId,
                            $owner,
                            $subject,
                            null,
                            SchedulableState::PLANNED,
                            $this->now->modify("+{$inDays} days")->setTime($this->rng->getInt(9, 14), 0),
                            'Siedziba klienta',
                        );
                        continue;
                    }
                    $daysAgo = $this->rng->getInt($floorDaysAgo, $ceilDaysAgo);
                    $activities[] = new MeetingActivity(
                        $this->nextActivityId(),
                        $dealId,
                        $owner,
                        $subject,
                        null,
                        SchedulableState::DONE,
                        $this->daysAgo($daysAgo, $this->rng->getInt(9, 16)),
                        'Siedziba klienta',
                    );
                }

                // A handful of comments/notes for texture on well-worked deals.
                if ($profile['level'] === 'high' || $profile['level'] === 'medium') {
                    $noteDaysAgo = $this->rng->getInt($floorDaysAgo, $ceilDaysAgo);
                    $activities[] = new CommentActivity(
                        $this->nextActivityId(),
                        $dealId,
                        $owner,
                        'Notatka wewnętrzna',
                        'Klient priorytetowo traktuje termin realizacji, warto pilnować harmonogramu.',
                        $this->daysAgo($noteDaysAgo, $this->rng->getInt(8, 18)),
                    );
                }

                // Force the exact "last activity N days ago" anchor even if
                // the random picks above didn't happen to land exactly there.
                if ($lastActivityDaysAgo !== null) {
                    $activities[] = new NoteActivity(
                        $this->nextActivityId(),
                        $dealId,
                        $owner,
                        'Aktualizacja statusu',
                        'Zaktualizowano status współpracy z klientem.',
                        $this->daysAgo($lastActivityDaysAgo, $this->rng->getInt(8, 17), $this->rng->getInt(0, 59)),
                    );
                }
            }

            $this->activitiesByDeal[$dealId] = $activities;
        }
    }

    private function daysAgo(int $days, int $hour = 10, int $minute = 0): DateTimeImmutable
    {
        $candidate = $this->now->modify("-{$days} days")->setTime($hour, $minute);
        if ($candidate <= $this->now) {
            return $candidate;
        }

        // A "days ago" timestamp must never land in the future — e.g. "0 days
        // ago at 18:00" would be later today than a request made at 12:00.
        // Jitter slightly instead of clamping every such case to the exact
        // same instant, so same-day activities across different deals don't
        // all collapse onto one identical timestamp.
        return $this->now->modify('-' . $this->rng->getInt(1, 180) . ' minutes');
    }

    private function companyById(string $id): Company
    {
        foreach ($this->companies as $company) {
            if ($company->id === $id) {
                return $company;
            }
        }

        throw new \RuntimeException("Unknown demo company: {$id}");
    }

    private function slugify(string $name): string
    {
        $map = [
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
            'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
        ];
        $ascii = strtr(mb_strtolower($name), $map);
        $parts = explode(' ', $ascii);

        return $parts[0] . '.' . ($parts[1] ?? '');
    }

    private function nextActivityId(): string
    {
        return 'a' . $this->activitySeq++;
    }
}
