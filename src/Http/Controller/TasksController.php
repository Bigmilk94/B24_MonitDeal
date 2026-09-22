<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Domain\Model\TaskActivity;
use App\Service\Crm\CrmServiceInterface;
use App\Support\Request;
use App\Support\View;
use DateTimeImmutable;

/**
 * Cross-deal task list, filterable by status, so a user can jump straight
 * to "everything overdue" without opening each deal individually.
 */
final class TasksController
{
    public function __construct(
        private readonly CrmServiceInterface $crm,
        private readonly View $view,
        private readonly DateTimeImmutable $now,
    ) {
    }

    public function index(Request $request): void
    {
        $rows = [];
        foreach ($this->crm->getDeals() as $deal) {
            foreach ($this->crm->getDealTasks($deal->id) as $task) {
                $rows[] = ['task' => $task, 'deal' => $deal];
            }
        }

        $status = (string) $request->query('status', '');
        if ($status !== '') {
            $rows = array_values(array_filter($rows, function (array $row) use ($status): bool {
                /** @var TaskActivity $task */
                $task = $row['task'];
                $current = match (true) {
                    $task->isCompleted() => 'completed',
                    $task->isOverdue($this->now) => 'overdue',
                    default => 'open',
                };

                return $current === $status;
            }));
        }

        usort($rows, function (array $a, array $b): int {
            /** @var TaskActivity $ta */
            $ta = $a['task'];
            /** @var TaskActivity $tb */
            $tb = $b['task'];
            $aDate = $ta->isCompleted() ? $ta->completedAt() : $ta->dueAt;
            $bDate = $tb->isCompleted() ? $tb->completedAt() : $tb->dueAt;

            return $aDate <=> $bDate;
        });

        $this->view->renderPage('tasks/index', [
            'rows' => $rows,
            'selectedStatus' => $status,
            'now' => $this->now,
        ], 'tasks', 'Zadania');
    }
}
