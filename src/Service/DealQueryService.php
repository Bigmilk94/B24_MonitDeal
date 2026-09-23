<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Enum\ActivityType;
use App\Domain\Model\DealView;
use DateTimeImmutable;

/**
 * Combinable filtering, free-text search and sorting over a list of
 * DealView, driven by a plain associative array of query-string params
 * (see DealsController for the param names).
 */
final class DealQueryService
{
    private const QUICK_FILTERS = [
        'active_today',
        'stale_3',
        'stale_7',
        'no_next_action',
        'overdue_tasks',
    ];

    /**
     * @param list<DealView> $deals
     * @param array<string, mixed> $params
     * @return list<DealView>
     */
    public function apply(array $deals, array $params, DateTimeImmutable $now): array
    {
        $result = $this->filter($deals, $params, $now);
        $result = $this->search($result, (string) ($params['q'] ?? ''));

        return $this->sort($result, (string) ($params['sort'] ?? 'last_activity'), (string) ($params['dir'] ?? 'desc'));
    }

    /**
     * @param list<DealView> $deals
     * @param array<string, mixed> $params
     * @return list<DealView>
     */
    private function filter(array $deals, array $params, DateTimeImmutable $now): array
    {
        $stages = $this->stringList($params['stage'] ?? null);
        $owners = $this->stringList($params['owner'] ?? null);
        $companies = $this->stringList($params['company'] ?? null);
        $lastActivityType = trim((string) ($params['last_activity_type'] ?? ''));
        $createdFrom = $this->parseDate($params['created_from'] ?? null);
        $createdTo = $this->parseDate($params['created_to'] ?? null);
        $closeFrom = $this->parseDate($params['close_from'] ?? null);
        $closeTo = $this->parseDate($params['close_to'] ?? null);
        $lastActivityFrom = $this->parseDate($params['last_activity_from'] ?? null);
        $lastActivityTo = $this->parseDate($params['last_activity_to'] ?? null);
        $hasOpenTasks = $this->parseBool($params['has_open_tasks'] ?? null);
        $hasOverdueTasks = $this->parseBool($params['has_overdue_tasks'] ?? null);
        $hasNextAction = $this->parseBool($params['has_next_action'] ?? null);
        $staleDays = isset($params['stale_days']) && $params['stale_days'] !== ''
            ? max(0, (int) $params['stale_days'])
            : null;
        $quick = in_array($params['quick'] ?? null, self::QUICK_FILTERS, true) ? $params['quick'] : null;

        return array_values(array_filter($deals, function (DealView $view) use (
            $stages, $owners, $companies, $lastActivityType,
            $createdFrom, $createdTo, $closeFrom, $closeTo,
            $lastActivityFrom, $lastActivityTo,
            $hasOpenTasks, $hasOverdueTasks, $hasNextAction, $staleDays, $quick, $now,
        ): bool {
            if ($stages !== [] && !in_array($view->deal->stage->value, $stages, true)) {
                return false;
            }
            if ($owners !== [] && !in_array($view->owner->id, $owners, true)) {
                return false;
            }
            if ($companies !== [] && !in_array($view->company->id, $companies, true)) {
                return false;
            }
            if ($lastActivityType !== '' && (
                $view->metrics->lastActivity === null
                || $view->metrics->lastActivity->type->value !== $lastActivityType
            )) {
                return false;
            }
            if (!$this->inRange($view->deal->createdAt, $createdFrom, $createdTo)) {
                return false;
            }
            if (!$this->inRange($view->deal->expectedCloseAt, $closeFrom, $closeTo)) {
                return false;
            }
            if (!$this->inRange($view->metrics->lastActivity?->completedAt(), $lastActivityFrom, $lastActivityTo)) {
                return false;
            }
            if ($hasOpenTasks !== null) {
                $openCount = $view->metrics->taskStats['open'] + $view->metrics->taskStats['overdue'];
                if (($openCount > 0) !== $hasOpenTasks) {
                    return false;
                }
            }
            if ($hasOverdueTasks !== null && ($view->metrics->taskStats['overdue'] > 0) !== $hasOverdueTasks) {
                return false;
            }
            if ($hasNextAction !== null && ($view->metrics->nextAction !== null) !== $hasNextAction) {
                return false;
            }
            if ($staleDays !== null && $view->metrics->daysSinceLastActivity < $staleDays) {
                return false;
            }
            if ($quick !== null && !$this->matchesQuickFilter($view, $quick, $now)) {
                return false;
            }

            return true;
        }));
    }

    private function matchesQuickFilter(DealView $view, string $quick, DateTimeImmutable $now): bool
    {
        return match ($quick) {
            'active_today' => $view->metrics->lastActivity !== null
                && $view->metrics->hasFlag('Ostatnia aktywność dzisiaj'),
            'stale_3' => $view->metrics->daysSinceLastActivity >= 3,
            'stale_7' => $view->metrics->daysSinceLastActivity >= 7,
            'no_next_action' => $view->metrics->nextAction === null,
            'overdue_tasks' => $view->metrics->taskStats['overdue'] > 0,
            default => true,
        };
    }

    /**
     * @param list<DealView> $deals
     * @return list<DealView>
     */
    private function search(array $deals, string $query): array
    {
        $query = mb_strtolower(trim($query));
        if ($query === '') {
            return $deals;
        }

        return array_values(array_filter($deals, static function (DealView $view) use ($query): bool {
            $haystacks = [
                $view->deal->id,
                mb_strtolower($view->deal->title),
                mb_strtolower($view->company->name),
                mb_strtolower($view->contact->name),
                mb_strtolower($view->owner->name),
            ];

            foreach ($haystacks as $haystack) {
                if (str_contains(mb_strtolower((string) $haystack), $query)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param list<DealView> $deals
     * @return list<DealView>
     */
    private function sort(array $deals, string $field, string $dir): array
    {
        $dir = $dir === 'asc' ? 'asc' : 'desc';
        $extractor = $this->sortValueExtractor($field);

        usort($deals, static function (DealView $a, DealView $b) use ($extractor, $dir): int {
            $result = $extractor($a) <=> $extractor($b);

            return $dir === 'asc' ? $result : -$result;
        });

        return $deals;
    }

    /**
     * @return callable(DealView): (int|float)
     */
    private function sortValueExtractor(string $field): callable
    {
        return match ($field) {
            'value' => static fn (DealView $v) => $v->deal->value,
            'created_at' => static fn (DealView $v) => $v->deal->createdAt->getTimestamp(),
            'expected_close_at' => static fn (DealView $v) => $v->deal->expectedCloseAt?->getTimestamp() ?? 0,
            'open_tasks' => static fn (DealView $v) => $v->metrics->taskStats['open'] + $v->metrics->taskStats['overdue'],
            'overdue_tasks' => static fn (DealView $v) => $v->metrics->taskStats['overdue'],
            'total_activities' => static fn (DealView $v) => $v->metrics->totalActivities,
            'days_on_stage' => static fn (DealView $v) => $v->metrics->daysOnCurrentStage,
            default => static fn (DealView $v) => $v->metrics->lastActivity?->completedAt()->getTimestamp() ?? 0,
        };
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value), static fn (string $v) => $v !== ''));
        }

        return [(string) $value];
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false ? $date->setTime(0, 0) : null;
    }

    private function parseBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value === '1' || $value === 1 || $value === true;
    }

    private function inRange(?DateTimeImmutable $value, ?DateTimeImmutable $from, ?DateTimeImmutable $to): bool
    {
        if ($from === null && $to === null) {
            return true;
        }
        if ($value === null) {
            return false;
        }
        if ($from !== null && $value < $from) {
            return false;
        }
        if ($to !== null && $value > $to->modify('+1 day')) {
            return false;
        }

        return true;
    }

    /**
     * @return list<ActivityType>
     */
    public function activityTypes(): array
    {
        return ActivityType::cases();
    }
}
