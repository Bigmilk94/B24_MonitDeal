<?php
/**
 * @var \App\Domain\Model\DealView $view
 * @var \DateTimeImmutable $now
 */
$deal = $view->deal;
$m = $view->metrics;
$progress = $m->progressPercent();
$stageClass = match (true) {
    $deal->stage === \App\Domain\Enum\DealStage::WON => 'is-won',
    $deal->stage === \App\Domain\Enum\DealStage::LOST => 'is-lost',
    default => '',
};
?>
<tr>
    <td class="cell-deal">
        <a class="deal-link" href="/deals/<?= e($deal->id) ?>"><?= e($deal->title) ?></a>
        <div class="cell-sub">#<?= e($deal->id) ?> · <?= e($view->contact->name) ?></div>
    </td>
    <td>
        <?= e($view->company->name) ?>
        <div class="cell-sub"><?= e($view->owner->name) ?></div>
    </td>
    <td><span class="stage-pill <?= $stageClass ?>"><?= e($deal->stage->label()) ?></span></td>
    <td><?= e(money($deal->value, $deal->currency)) ?></td>
    <td>
        <?= e(\App\Support\TimeFormatter::date($deal->createdAt)) ?>
        <div class="cell-sub"><?= e($m->daysSinceCreation) ?> dni temu</div>
    </td>
    <td>
        <?= $deal->expectedCloseAt !== null ? e(\App\Support\TimeFormatter::date($deal->expectedCloseAt)) : '—' ?>
    </td>
    <td>
        <span class="badge-<?= e($m->lastActivitySeverity->value) ?>"><?= e($m->lastActivityRelative) ?></span>
        <?php if ($m->lastActivity !== null): ?>
            <div class="cell-sub"><?= e(\App\Support\TimeFormatter::dateTime($m->lastActivity->completedAt())) ?></div>
        <?php endif; ?>
    </td>
    <td class="cell-activity">
        <?php if ($m->lastActivity !== null): ?>
            <span class="activity-icon"><?= $m->lastActivity->type->icon() ?></span><?= e($m->lastActivity->type->label()) ?>
            <span class="activity-desc"><?= e($m->lastActivity->title) ?></span>
            <span class="activity-meta"><?= e($m->lastActivity->performedBy->name) ?></span>
        <?php else: ?>
            <span class="cell-sub">Brak aktywności</span>
        <?php endif; ?>
    </td>
    <td>
        <?= e($m->daysOnCurrentStage) ?> dni
    </td>
    <td class="stat-mini">
        <b><?= e($m->emailStats['total']) ?></b>
        <div class="stat-sub">wys. <?= e($m->emailStats['sent']) ?> / odb. <?= e($m->emailStats['received']) ?></div>
    </td>
    <td class="stat-mini">
        <b><?= e($m->taskStats['total']) ?></b>
        <div class="stat-sub">
            ✓<?= e($m->taskStats['completed']) ?> ○<?= e($m->taskStats['open']) ?>
            <?php if ($m->taskStats['overdue'] > 0): ?><span class="badge-attention">!<?= e($m->taskStats['overdue']) ?></span><?php endif; ?>
        </div>
    </td>
    <td class="progress-cell">
        <?php if ($progress === null): ?>
            <span class="cell-sub">Brak aktywności</span>
        <?php else: ?>
            <div class="progress-label"><?= e($m->completedActivities) ?> / <?= e($m->totalActivities) ?> (<?= e($progress) ?>%)</div>
            <div class="progress-track"><div class="progress-fill" style="width: <?= e($progress) ?>%"></div></div>
        <?php endif; ?>
    </td>
    <td class="cell-activity">
        <?php if ($m->nextAction !== null): ?>
            <span class="activity-icon"><?= $m->nextAction->type->icon() ?></span><?= e($m->nextAction->type->label()) ?>
            <span class="activity-desc"><?= e($m->nextAction->title) ?></span>
            <span class="activity-meta">
                <?= e(\App\Support\TimeFormatter::dateTime($m->nextAction->plannedAt())) ?> · <?= e($m->nextAction->performedBy->name) ?>
            </span>
        <?php else: ?>
            <span class="cell-sub">Brak zaplanowanego działania</span>
        <?php endif; ?>
    </td>
    <td class="cell-flags"><?= $this->partial('flags', ['flags' => $m->flags]) ?></td>
</tr>
