<?php

declare(strict_types=1);

/**
 * Lightweight, dependency-free test runner (no PHPUnit needed).
 * Run with: php tests/run-tests.php
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Domain\Model\DealView;
use App\Service\Crm\MockCrmService;
use App\Service\DealMetricsCalculator;
use App\Service\DealQueryService;
use App\Service\DealViewFactory;

$now = new DateTimeImmutable('2026-09-22 12:00:00');

$failures = [];
$passed = 0;

/**
 * @param callable(): void $fn
 */
function check(string $name, callable $fn, array &$failures, int &$passed): void
{
    try {
        $fn();
        $passed++;
        echo "  ok  - {$name}\n";
    } catch (\Throwable $e) {
        $failures[] = "{$name}: {$e->getMessage()}";
        echo "FAIL  - {$name}: {$e->getMessage()}\n";
    }
}

function assertTrue(bool $cond, string $message): void
{
    if (!$cond) {
        throw new RuntimeException($message);
    }
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        $exp = var_export($expected, true);
        $act = var_export($actual, true);
        throw new RuntimeException("{$message} (expected {$exp}, got {$act})");
    }
}

// ---------------------------------------------------------------------
echo "== Spójność liczb aktywności dla każdego deala ==\n";

$crm = new MockCrmService($now);
$calculator = new DealMetricsCalculator();
$factory = new DealViewFactory($crm, $calculator);
$allViews = $factory->buildAll($now);

assertTrue(count($allViews) >= 20, 'demo dataset ma mieć min. 20 deali');

foreach ($allViews as $view) {
    $m = $view->metrics;
    $dealLabel = "{$view->deal->id} ({$view->deal->title})";

    check("completed+open = total dla {$dealLabel}", function () use ($m) {
        assertSame($m->totalActivities, $m->completedActivities + $m->openActivities, 'completed + open musi dać total');
    }, $failures, $passed);

    check("email sent+received = total dla {$dealLabel}", function () use ($m) {
        assertSame($m->emailStats['total'], $m->emailStats['sent'] + $m->emailStats['received'], 'emaile: sent+received=total');
    }, $failures, $passed);

    check("task completed+open+overdue = total dla {$dealLabel}", function () use ($m) {
        assertSame(
            $m->taskStats['total'],
            $m->taskStats['completed'] + $m->taskStats['open'] + $m->taskStats['overdue'],
            'zadania: completed+open+overdue=total',
        );
    }, $failures, $passed);

    check("call done+planned = total dla {$dealLabel}", function () use ($m) {
        assertSame($m->callStats['total'], $m->callStats['done'] + $m->callStats['planned'], 'telefony: done+planned=total');
    }, $failures, $passed);

    check("meeting done+planned = total dla {$dealLabel}", function () use ($m) {
        assertSame($m->meetingStats['total'], $m->meetingStats['done'] + $m->meetingStats['planned'], 'spotkania: done+planned=total');
    }, $failures, $passed);

    check("crm activity completed+planned = total dla {$dealLabel}", function () use ($m) {
        assertSame(
            $m->crmActivityStats['total'],
            $m->crmActivityStats['completed'] + $m->crmActivityStats['planned'],
            'aktywności CRM: completed+planned=total',
        );
    }, $failures, $passed);

    check("total = suma podkategorii dla {$dealLabel}", function () use ($m) {
        $sum = $m->emailStats['total'] + $m->taskStats['total'] + $m->callStats['total']
            + $m->meetingStats['total'] + $m->crmActivityStats['total'];
        assertSame($m->totalActivities, $sum, 'total musi być sumą e-maili+zadań+telefonów+spotkań+aktywności CRM');
    }, $failures, $passed);
}

// ---------------------------------------------------------------------
echo "\n== Ostatnia aktywność faktycznie jest najnowszą aktywnością ==\n";

foreach ($allViews as $view) {
    $dealId = $view->deal->id;
    $activities = $crm->getDealActivities($dealId);
    $m = $view->metrics;

    check("last activity dla {$dealId}", function () use ($activities, $m) {
        $expected = null;
        foreach ($activities as $activity) {
            if (!$activity->isCompleted()) {
                continue;
            }
            if ($expected === null || $activity->completedAt() > $expected) {
                $expected = $activity->completedAt();
            }
        }

        if ($expected === null) {
            assertTrue($m->lastActivity === null, 'brak aktywności powinien dać lastActivity=null');

            return;
        }

        assertTrue($m->lastActivity !== null, 'lastActivity nie powinien być null');
        assertTrue($m->lastActivity->completedAt() == $expected, 'lastActivity musi mieć najnowszą datę completedAt spośród zakończonych aktywności');
    }, $failures, $passed);
}

// ---------------------------------------------------------------------
echo "\n== Następne działanie jest najbliższą przyszłą zaplanowaną aktywnością ==\n";

foreach ($allViews as $view) {
    $dealId = $view->deal->id;
    $activities = $crm->getDealActivities($dealId);
    $m = $view->metrics;

    check("next action dla {$dealId}", function () use ($activities, $m, $now) {
        $expected = null;
        foreach ($activities as $activity) {
            if ($activity->isCompleted()) {
                continue;
            }
            $plannedAt = $activity->plannedAt();
            if ($plannedAt === null || $plannedAt < $now) {
                continue;
            }
            if ($expected === null || $plannedAt < $expected) {
                $expected = $plannedAt;
            }
        }

        if ($expected === null) {
            assertTrue($m->nextAction === null, 'brak przyszłych zaplanowanych działań powinien dać nextAction=null');

            return;
        }

        assertTrue($m->nextAction !== null, 'nextAction nie powinien być null');
        assertTrue($m->nextAction->plannedAt() == $expected, 'nextAction musi być najwcześniejszą przyszłą zaplanowaną aktywnością');
    }, $failures, $passed);
}

// ---------------------------------------------------------------------
echo "\n== Żadna \"zakończona\" aktywność nie leży w przyszłości ==\n";

foreach ($crm->getDeals() as $deal) {
    check("completedAt <= now dla wszystkich aktywności {$deal->id}", function () use ($crm, $deal, $now) {
        foreach ($crm->getDealActivities($deal->id) as $activity) {
            if (!$activity->isCompleted()) {
                continue;
            }
            assertTrue(
                $activity->completedAt() <= $now,
                "aktywność {$activity->id} oznaczona jako zakończona ma datę w przyszłości",
            );
        }
    }, $failures, $passed);
}

// ---------------------------------------------------------------------
echo "\n== Reguły flag ==\n";

// Attention flags are only computed for open (non-won/lost) deals by design
// (see DealMetricsCalculator::calculate) — closed deals always get flags=[].
foreach ($allViews as $view) {
    if (!$view->deal->stage->isOpen()) {
        continue;
    }
    $m = $view->metrics;
    $dealId = $view->deal->id;

    check("flaga 'Zadanie przeterminowane' dla {$dealId}", function () use ($m) {
        assertSame($m->taskStats['overdue'] > 0, $m->hasFlag('Zadanie przeterminowane'), 'flaga overdue niespójna z licznikiem');
    }, $failures, $passed);

    check("flaga 'Brak zaplanowanego następnego działania' dla {$dealId}", function () use ($m) {
        assertSame($m->nextAction === null, $m->hasFlag('Brak zaplanowanego następnego działania'), 'flaga next action niespójna');
    }, $failures, $passed);

    check("flaga 'Duża liczba otwartych zadań' dla {$dealId}", function () use ($m) {
        $expected = ($m->taskStats['open'] + $m->taskStats['overdue']) >= 3;
        assertSame($expected, $m->hasFlag('Duża liczba otwartych zadań'), 'próg dużej liczby zadań niespójny');
    }, $failures, $passed);
}

foreach ($allViews as $view) {
    if ($view->deal->stage->isOpen()) {
        continue;
    }
    check("zamknięty deal {$view->deal->id} nie ma flag operacyjnych", function () use ($view) {
        assertSame([], $view->metrics->flags, 'zamknięte deale (won/lost) nie powinny mieć flag wymagających uwagi');
    }, $failures, $passed);
}

// ---------------------------------------------------------------------
echo "\n== Wymagane scenariusze danych demonstracyjnych ==\n";

check('jest min. 1 deal z dużą liczbą aktywności (>=15)', function () use ($allViews) {
    $count = count(array_filter($allViews, static fn (DealView $v) => $v->metrics->totalActivities >= 15));
    assertTrue($count >= 1, 'brak deala z dużą liczbą aktywności');
}, $failures, $passed);

check('jest min. 1 deal bez żadnej aktywności', function () use ($allViews) {
    $count = count(array_filter($allViews, static fn (DealView $v) => $v->metrics->totalActivities === 0));
    assertTrue($count >= 1, 'brak deala bez aktywności');
}, $failures, $passed);

check('jest min. 1 deal z przeterminowanymi zadaniami', function () use ($allViews) {
    $count = count(array_filter($allViews, static fn (DealView $v) => $v->metrics->taskStats['overdue'] > 0));
    assertTrue($count >= 1, 'brak deala z przeterminowanymi zadaniami');
}, $failures, $passed);

check('jest min. 1 deal bez zaplanowanego następnego działania', function () use ($allViews) {
    $count = count(array_filter($allViews, static fn (DealView $v) => $v->metrics->nextAction === null));
    assertTrue($count >= 1, 'brak deala bez next action');
}, $failures, $passed);

check('reprezentowanych jest min. 5 klientów', function () use ($allViews) {
    $companies = array_unique(array_map(static fn (DealView $v) => $v->company->id, $allViews));
    assertTrue(count($companies) >= 5, 'za mało unikalnych klientów');
}, $failures, $passed);

check('reprezentowanych jest kilku opiekunów (owners)', function () use ($allViews) {
    $owners = array_unique(array_map(static fn (DealView $v) => $v->owner->id, $allViews));
    assertTrue(count($owners) >= 3, 'za mało unikalnych opiekunów');
}, $failures, $passed);

// ---------------------------------------------------------------------
echo "\n== Timeline jest chronologiczny (malejąco) ==\n";

foreach ($crm->getDeals() as $deal) {
    check("timeline malejący dla {$deal->id}", function () use ($crm, $deal) {
        $timeline = $crm->getDealTimeline($deal->id);
        for ($i = 1; $i < count($timeline); $i++) {
            assertTrue($timeline[$i - 1]->timelineAt() >= $timeline[$i]->timelineAt(), 'timeline musi być posortowany malejąco');
        }
    }, $failures, $passed);
}

// ---------------------------------------------------------------------
echo "\n== Filtrowanie, wyszukiwanie i sortowanie ==\n";

$queryService = new DealQueryService();

check('szybki filtr overdue_tasks zwraca tylko deale z przeterminowanymi zadaniami', function () use ($queryService, $allViews, $now) {
    $result = $queryService->apply($allViews, ['quick' => 'overdue_tasks'], $now);
    assertTrue(count($result) > 0, 'oczekiwano co najmniej jednego wyniku');
    foreach ($result as $view) {
        assertTrue($view->metrics->taskStats['overdue'] > 0, 'każdy wynik musi mieć przeterminowane zadanie');
    }
}, $failures, $passed);

check('szybki filtr no_next_action zwraca tylko deale bez next action', function () use ($queryService, $allViews, $now) {
    $result = $queryService->apply($allViews, ['quick' => 'no_next_action'], $now);
    assertTrue(count($result) > 0, 'oczekiwano co najmniej jednego wyniku');
    foreach ($result as $view) {
        assertTrue($view->metrics->nextAction === null, 'każdy wynik musi nie mieć next action');
    }
}, $failures, $passed);

check('wyszukiwanie po ID deala zwraca dokładnie ten deal', function () use ($queryService, $allViews, $now) {
    $result = $queryService->apply($allViews, ['q' => 'd1'], $now);
    $ids = array_map(static fn (DealView $v) => $v->deal->id, $result);
    assertTrue(in_array('d1', $ids, true), 'wynik wyszukiwania powinien zawierać deal d1');
}, $failures, $passed);

check('wyszukiwanie po nazwie firmy działa', function () use ($queryService, $allViews, $now) {
    $result = $queryService->apply($allViews, ['q' => 'technova'], $now);
    assertTrue(count($result) > 0, 'oczekiwano wyników dla TechNova');
    foreach ($result as $view) {
        assertTrue(str_contains(mb_strtolower($view->company->name), 'technova'), 'każdy wynik powinien dotyczyć TechNova');
    }
}, $failures, $passed);

check('sortowanie po wartości rosnąco jest niemalejące', function () use ($queryService, $allViews, $now) {
    $result = $queryService->apply($allViews, ['sort' => 'value', 'dir' => 'asc'], $now);
    for ($i = 1; $i < count($result); $i++) {
        assertTrue($result[$i - 1]->deal->value <= $result[$i]->deal->value, 'wartości powinny rosnąć');
    }
}, $failures, $passed);

check('filtry można łączyć (etap + osoba odpowiedzialna)', function () use ($queryService, $allViews, $now) {
    $owner = $allViews[0]->owner->id;
    $stage = $allViews[0]->deal->stage->value;
    $result = $queryService->apply($allViews, ['stage' => [$stage], 'owner' => [$owner]], $now);
    foreach ($result as $view) {
        assertTrue($view->deal->stage->value === $stage, 'wynik musi pasować do wybranego etapu');
        assertTrue($view->owner->id === $owner, 'wynik musi pasować do wybranego opiekuna');
    }
}, $failures, $passed);

// ---------------------------------------------------------------------
echo "\n== Szczegóły deala pokazują właściwe dane ==\n";

check('getDeal zwraca poprawny deal dla danego ID', function () use ($crm) {
    $deal = $crm->getDeal('d5');
    assertTrue($deal !== null, 'deal d5 powinien istnieć');
    assertSame('d5', $deal->id, 'zwrócony deal musi mieć to samo ID');
}, $failures, $passed);

check('getDeal zwraca null dla nieistniejącego ID', function () use ($crm) {
    assertTrue($crm->getDeal('nope') === null, 'nieistniejący deal powinien dać null');
}, $failures, $passed);

// ---------------------------------------------------------------------
echo "\n---\n";
echo "Zaliczone: {$passed}\n";
echo 'Nieudane: ' . count($failures) . "\n";

if ($failures !== []) {
    echo "\nSzczegóły błędów:\n";
    foreach ($failures as $f) {
        echo " - {$f}\n";
    }
    exit(1);
}

echo "\nWszystkie testy przeszły pomyślnie.\n";
exit(0);
