<?php
/**
 * @var list<array{activity: \App\Domain\Model\Activity, deal: \App\Domain\Model\Deal}> $rows
 * @var int $totalCount
 * @var list<\App\Domain\Enum\ActivityType> $activityTypes
 * @var string $selectedType
 * @var \DateTimeImmutable $now
 */
?>
<div class="filters-bar">
    <a class="quick-filter <?= $selectedType === '' ? 'is-active' : '' ?>" href="?activity_type=">Wszystkie</a>
    <?php foreach ($activityTypes as $type): ?>
        <a class="quick-filter <?= $selectedType === $type->value ? 'is-active' : '' ?>" href="?activity_type=<?= e($type->value) ?>"><?= $type->icon() ?> <?= e($type->label()) ?></a>
    <?php endforeach; ?>
</div>

<div class="results-meta">Pokazano <?= e(count($rows)) ?> z <?= e($totalCount) ?> aktywności (najnowsze najpierw)</div>

<div class="table-wrap">
<table class="deals-table">
    <thead>
    <tr>
        <th>Data</th>
        <th>Rodzaj</th>
        <th>Opis</th>
        <th>Deal</th>
        <th>Osoba</th>
        <th>Status</th>
    </tr>
    </thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="6" class="empty-state">Brak aktywności dla wybranego filtra.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $row): ?>
        <?php $activity = $row['activity']; $deal = $row['deal']; ?>
        <tr>
            <td><?= e(\App\Support\TimeFormatter::dateTime($activity->timelineAt())) ?></td>
            <td><?= $activity->type->icon() ?> <?= e($activity->type->label()) ?></td>
            <td class="cell-activity">
                „<?= e($activity->title) ?>”
                <?php if ($activity->description !== null): ?><div class="activity-desc"><?= e($activity->description) ?></div><?php endif; ?>
            </td>
            <td class="cell-deal"><a class="deal-link" href="/deals/<?= e($deal->id) ?>"><?= e($deal->title) ?></a></td>
            <td><?= e($activity->performedBy->name) ?></td>
            <td>
                <?php if ($activity->isCompleted()): ?>
                    <span class="flag flag-normal">Zakończone</span>
                <?php else: ?>
                    <span class="flag flag-warning">Zaplanowane</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
