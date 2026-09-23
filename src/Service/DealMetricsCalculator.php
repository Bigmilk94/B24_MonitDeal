<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Enum\RecencySeverity;
use App\Domain\Model\Activity;
use App\Domain\Model\CallActivity;
use App\Domain\Model\CommentActivity;
use App\Domain\Model\Deal;
use App\Domain\Model\DealMetrics;
use App\Domain\Model\EmailActivity;
use App\Domain\Model\MeetingActivity;
use App\Domain\Model\NoteActivity;
use App\Domain\Model\OtherActivity;
use App\Domain\Model\StageChangeActivity;
use App\Domain\Model\TaskActivity;
use App\Domain\Enum\EmailDirection;
use App\Support\DateHelper;
use App\Support\TimeFormatter;
use DateTimeImmutable;

/**
 * Pure business logic: turns a Deal plus its raw activity list into a
 * DealMetrics value object. Contains every derived-data rule described in
 * the product spec (days on stage, recency buckets, per-type activity
 * stats, next action, attention flags) in one auditable place, decoupled
 * from both the CRM adapter and the presentation layer.
 */
final class DealMetricsCalculator
{
    private const STALE_AFTER_DAYS = 7;
    private const MANY_OPEN_TASKS_THRESHOLD = 3;

    /**
     * @param list<Activity> $activities
     */
    public function calculate(Deal $deal, array $activities, DateTimeImmutable $now): DealMetrics
    {
        $daysSinceCreation = DateHelper::daysBetween($deal->createdAt, $now);
        $daysOnCurrentStage = DateHelper::daysBetween($this->currentStageEnteredAt($deal, $activities), $now);

        $lastActivity = $this->findLastActivity($activities);
        $daysSinceLastActivity = $lastActivity !== null
            ? DateHelper::daysBetween($lastActivity->completedAt(), $now)
            : $daysSinceCreation;
        $lastActivityRelative = $lastActivity !== null
            ? TimeFormatter::relative($lastActivity->completedAt(), $now)
            : 'Brak aktywności';
        $lastActivitySeverity = RecencySeverity::fromDays($daysSinceLastActivity);

        $emailStats = $this->emailStats($activities);
        $taskStats = $this->taskStats($activities, $now);
        $callStats = $this->schedulableStats($activities, CallActivity::class);
        $meetingStats = $this->schedulableStats($activities, MeetingActivity::class);
        $crmActivityStats = $this->crmActivityStats($activities);

        $totalActivities = $emailStats['total'] + $taskStats['total'] + $callStats['total']
            + $meetingStats['total'] + $crmActivityStats['total'];
        $completedActivities = $emailStats['total'] + $taskStats['completed'] + $callStats['done']
            + $meetingStats['done'] + $crmActivityStats['completed'];
        $openActivities = $taskStats['open'] + $taskStats['overdue'] + $callStats['planned']
            + $meetingStats['planned'] + $crmActivityStats['planned'];

        $nextAction = $this->findNextAction($activities, $now);

        $flags = $deal->stage->isOpen()
            ? $this->buildFlags($lastActivity, $daysSinceLastActivity, $taskStats, $nextAction, $now)
            : [];

        return new DealMetrics(
            $daysSinceCreation,
            $daysOnCurrentStage,
            $lastActivity,
            $lastActivityRelative,
            $lastActivitySeverity,
            $daysSinceLastActivity,
            $emailStats,
            $taskStats,
            $callStats,
            $meetingStats,
            $crmActivityStats,
            $totalActivities,
            $completedActivities,
            $openActivities,
            $nextAction,
            $flags,
        );
    }

    /**
     * @param list<Activity> $activities
     */
    private function currentStageEnteredAt(Deal $deal, array $activities): DateTimeImmutable
    {
        $latest = null;
        foreach ($activities as $activity) {
            if (!$activity instanceof StageChangeActivity || !$activity->toStage->equals($deal->stage)) {
                continue;
            }
            if ($latest === null || $activity->changedAt > $latest) {
                $latest = $activity->changedAt;
            }
        }

        return $latest ?? $deal->createdAt;
    }

    /**
     * @param list<Activity> $activities
     */
    private function findLastActivity(array $activities): ?Activity
    {
        $last = null;
        foreach ($activities as $activity) {
            if (!$activity->isCompleted()) {
                continue;
            }
            if ($last === null || $activity->completedAt() > $last->completedAt()) {
                $last = $activity;
            }
        }

        return $last;
    }

    /**
     * @param list<Activity> $activities
     */
    private function findNextAction(array $activities, DateTimeImmutable $now): ?Activity
    {
        $next = null;
        foreach ($activities as $activity) {
            if ($activity->isCompleted()) {
                continue;
            }
            $plannedAt = $activity->plannedAt();
            if ($plannedAt === null || $plannedAt < $now) {
                continue;
            }
            if ($next === null || $plannedAt < $next->plannedAt()) {
                $next = $activity;
            }
        }

        return $next;
    }

    /**
     * @param list<Activity> $activities
     * @return array{total:int, sent:int, received:int}
     */
    private function emailStats(array $activities): array
    {
        $total = 0;
        $sent = 0;
        $received = 0;
        foreach ($activities as $activity) {
            if (!$activity instanceof EmailActivity) {
                continue;
            }
            $total++;
            if ($activity->direction === EmailDirection::SENT) {
                $sent++;
            } else {
                $received++;
            }
        }

        return ['total' => $total, 'sent' => $sent, 'received' => $received];
    }

    /**
     * @param list<Activity> $activities
     * @return array{total:int, completed:int, open:int, overdue:int}
     */
    private function taskStats(array $activities, DateTimeImmutable $now): array
    {
        $total = 0;
        $completed = 0;
        $open = 0;
        $overdue = 0;
        foreach ($activities as $activity) {
            if (!$activity instanceof TaskActivity) {
                continue;
            }
            $total++;
            if ($activity->isCompleted()) {
                $completed++;
            } elseif ($activity->isOverdue($now)) {
                $overdue++;
            } else {
                $open++;
            }
        }

        return ['total' => $total, 'completed' => $completed, 'open' => $open, 'overdue' => $overdue];
    }

    /**
     * @param list<Activity> $activities
     * @param class-string<CallActivity|MeetingActivity> $class
     * @return array{total:int, done:int, planned:int}
     */
    private function schedulableStats(array $activities, string $class): array
    {
        $total = 0;
        $done = 0;
        $planned = 0;
        foreach ($activities as $activity) {
            if (!$activity instanceof $class) {
                continue;
            }
            $total++;
            if ($activity->isCompleted()) {
                $done++;
            } else {
                $planned++;
            }
        }

        return ['total' => $total, 'done' => $done, 'planned' => $planned];
    }

    /**
     * @param list<Activity> $activities
     * @return array{total:int, completed:int, planned:int}
     */
    private function crmActivityStats(array $activities): array
    {
        $total = 0;
        $completed = 0;
        $planned = 0;
        foreach ($activities as $activity) {
            if ($activity instanceof CommentActivity || $activity instanceof NoteActivity || $activity instanceof StageChangeActivity) {
                $total++;
                $completed++;
                continue;
            }
            if ($activity instanceof OtherActivity) {
                $total++;
                if ($activity->isCompleted()) {
                    $completed++;
                } else {
                    $planned++;
                }
            }
        }

        return ['total' => $total, 'completed' => $completed, 'planned' => $planned];
    }

    /**
     * @param array{total:int, completed:int, open:int, overdue:int} $taskStats
     * @return list<string>
     */
    private function buildFlags(
        ?Activity $lastActivity,
        int $daysSinceLastActivity,
        array $taskStats,
        ?Activity $nextAction,
        DateTimeImmutable $now,
    ): array {
        $flags = [];

        if ($lastActivity === null || $daysSinceLastActivity > self::STALE_AFTER_DAYS) {
            $flags[] = 'Brak aktywności od 7 dni';
        } elseif ($daysSinceLastActivity <= 2) {
            $flags[] = 'Aktywny deal';
        }

        if ($lastActivity !== null && DateHelper::daysBetween($lastActivity->completedAt(), $now) === 0) {
            $flags[] = 'Ostatnia aktywność dzisiaj';
        }

        if ($nextAction === null) {
            $flags[] = 'Brak zaplanowanego następnego działania';
        }

        if ($taskStats['overdue'] > 0) {
            $flags[] = 'Zadanie przeterminowane';
        }

        if (($taskStats['open'] + $taskStats['overdue']) >= self::MANY_OPEN_TASKS_THRESHOLD) {
            $flags[] = 'Duża liczba otwartych zadań';
        }

        return $flags;
    }
}
