<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Model\TaskActivity;
use App\Service\Crm\CrmServiceInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Cross-deal task list, filterable by status, so a user can jump straight
 * to "everything overdue" without opening each deal individually.
 */
final class TasksController extends AbstractController
{
    public function __construct(
        private readonly CrmServiceInterface $crm,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/tasks', name: 'tasks', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $now = $this->clock->now();

        $rows = [];
        foreach ($this->crm->getDeals() as $deal) {
            foreach ($this->crm->getDealTasks($deal->id) as $task) {
                $rows[] = ['task' => $task, 'deal' => $deal];
            }
        }

        $status = (string) $request->query->get('status', '');
        if ($status !== '') {
            $rows = array_values(array_filter($rows, function (array $row) use ($status, $now): bool {
                /** @var TaskActivity $task */
                $task = $row['task'];
                $current = match (true) {
                    $task->isCompleted() => 'completed',
                    $task->isOverdue($now) => 'overdue',
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

        return $this->render('tasks/index.html.twig', [
            'activeNav' => 'tasks',
            'pageTitle' => 'Zadania',
            'rows' => $rows,
            'selectedStatus' => $status,
            'now' => $now,
        ]);
    }
}
