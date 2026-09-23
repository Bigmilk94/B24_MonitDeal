<?php
/**
 * @var list<array{task: \App\Domain\Model\TaskActivity, deal: \App\Domain\Model\Deal}> $rows
 * @var string $selectedStatus
 * @var \DateTimeImmutable $now
 */
$statuses = ['' => 'Wszystkie', 'open' => 'Otwarte', 'overdue' => 'Przeterminowane', 'completed' => 'Zakończone'];
?>
<div class="filters-bar">
    <?php foreach ($statuses as $key => $label): ?>
        <a class="quick-filter <?= $selectedStatus === $key ? 'is-active' : '' ?>" href="?status=<?= e($key) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<div class="results-meta">Pokazano <?= e(count($rows)) ?> zadań</div>

<div class="table-wrap">
<table class="deals-table">
    <thead>
    <tr>
        <th>Termin / wykonano</th>
        <th>Zadanie</th>
        <th>Deal</th>
        <th>Osoba</th>
        <th>Status</th>
    </tr>
    </thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="5" class="empty-state">Brak zadań dla wybranego filtra.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $row): ?>
        <?php
        /** @var \App\Domain\Model\TaskActivity $task */
        $task = $row['task'];
        $deal = $row['deal'];
        $isOverdue = $task->isOverdue($now);
        ?>
        <tr>
            <td>
                <?= $task->isCompleted()
                    ? e(\App\Support\TimeFormatter::dateTime($task->completedAt()))
                    : e(\App\Support\TimeFormatter::dateTime($task->dueAt)) ?>
            </td>
            <td class="cell-activity">„<?= e($task->title) ?>”</td>
            <td class="cell-deal"><a class="deal-link" href="<?= e(base_path()) ?>/deals/<?= e($deal->id) ?>"><?= e($deal->title) ?></a></td>
            <td><?= e($task->performedBy->name) ?></td>
            <td>
                <?php if ($task->isCompleted()): ?>
                    <span class="flag flag-normal">✓ Zakończone</span>
                <?php elseif ($isOverdue): ?>
                    <span class="flag flag-attention">! Przeterminowane</span>
                <?php else: ?>
                    <span class="flag flag-warning">○ Otwarte</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
