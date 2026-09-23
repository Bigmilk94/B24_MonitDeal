<?php
/**
 * @var \App\Domain\Model\DealView $view
 * @var list<\App\Domain\Model\Activity> $timeline
 * @var list<\App\Domain\Enum\ActivityType> $activityTypes
 * @var string $selectedType
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
<a class="back-link" href="<?= e(base_path()) ?>/deals">← Wróć do listy deali</a>

<div class="deal-header">
    <div>
        <h2><?= e($deal->title) ?></h2>
        <div class="cell-sub">
            #<?= e($deal->id) ?> · <?= e($view->company->name) ?> · <?= e($view->contact->name) ?>
        </div>
    </div>
    <div class="attention-flags">
        <span class="stage-pill <?= $stageClass ?>"><?= e($deal->stage->label()) ?></span>
        <?= $this->partial('flags', ['flags' => $m->flags]) ?>
    </div>
</div>

<div class="section">
    <h3 class="section-title">Informacje podstawowe</h3>
    <div class="info-grid">
        <div class="info-item"><div class="info-label">Firma</div><div class="info-value"><?= e($view->company->name) ?></div></div>
        <div class="info-item"><div class="info-label">Kontakt</div><div class="info-value"><?= e($view->contact->name) ?></div></div>
        <div class="info-item"><div class="info-label">Osoba odpowiedzialna</div><div class="info-value"><?= e($view->owner->name) ?></div></div>
        <div class="info-item"><div class="info-label">Etap</div><div class="info-value"><?= e($deal->stage->label()) ?> (<?= e($m->daysOnCurrentStage) ?> dni)</div></div>
        <div class="info-item"><div class="info-label">Wartość</div><div class="info-value"><?= e(money($deal->value, $deal->currency)) ?></div></div>
        <div class="info-item"><div class="info-label">Data utworzenia</div><div class="info-value"><?= e(\App\Support\TimeFormatter::date($deal->createdAt)) ?> (<?= e($m->daysSinceCreation) ?> dni temu)</div></div>
        <div class="info-item"><div class="info-label">Przewidywane zamknięcie</div><div class="info-value"><?= $deal->expectedCloseAt !== null ? e(\App\Support\TimeFormatter::date($deal->expectedCloseAt)) : '—' ?></div></div>
        <div class="info-item"><div class="info-label">E-mail kontaktu</div><div class="info-value"><?= e($view->contact->email) ?></div></div>
        <div class="info-item"><div class="info-label">Telefon kontaktu</div><div class="info-value"><?= e($view->contact->phone) ?></div></div>
    </div>
</div>

<div class="two-col">
    <div class="last-activity-box">
        <div class="box-label">Ostatnia aktywność</div>
        <?php if ($m->lastActivity !== null): ?>
            <div class="timeline-title"><?= $m->lastActivity->type->icon() ?> <?= e($m->lastActivity->type->label()) ?></div>
            <div class="timeline-desc">„<?= e($m->lastActivity->title) ?>”</div>
            <div class="timeline-author"><?= e(\App\Support\TimeFormatter::dateTime($m->lastActivity->completedAt())) ?> · <?= e($m->lastActivity->performedBy->name) ?></div>
            <div class="cell-sub" style="margin-top:6px;">
                <span class="badge-<?= e($m->lastActivitySeverity->value) ?>"><?= e($m->lastActivityRelative) ?></span>
            </div>
        <?php else: ?>
            <div class="empty-state">Brak jakiejkolwiek aktywności.</div>
        <?php endif; ?>
    </div>
    <div class="next-action-box">
        <div class="box-label">Następne działanie</div>
        <?php if ($m->nextAction !== null): ?>
            <div class="timeline-title"><?= $m->nextAction->type->icon() ?> <?= e($m->nextAction->type->label()) ?></div>
            <div class="timeline-desc">„<?= e($m->nextAction->title) ?>”</div>
            <div class="timeline-author"><?= e(\App\Support\TimeFormatter::dateTime($m->nextAction->plannedAt())) ?> · <?= e($m->nextAction->performedBy->name) ?></div>
        <?php else: ?>
            <div class="empty-state">Brak zaplanowanego działania</div>
        <?php endif; ?>
    </div>
</div>

<div class="section">
    <h3 class="section-title">Statystyki aktywności</h3>
    <?php if ($progress !== null): ?>
        <div class="progress-label">Aktywności: <?= e($m->completedActivities) ?> / <?= e($m->totalActivities) ?> (<?= e($progress) ?>%)</div>
        <div class="progress-track" style="width: 220px;"><div class="progress-fill" style="width: <?= e($progress) ?>%"></div></div>
    <?php endif; ?>
    <div class="stats-grid" style="margin-top:16px;">
        <div class="stat-card">
            <div class="stat-title">E-maile</div>
            <div class="stat-total"><?= e($m->emailStats['total']) ?></div>
            <div class="stat-breakdown"><span>Wysłane: <?= e($m->emailStats['sent']) ?></span><span>Odebrane: <?= e($m->emailStats['received']) ?></span></div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Zadania</div>
            <div class="stat-total"><?= e($m->taskStats['total']) ?></div>
            <div class="stat-breakdown">
                <span>✓ <?= e($m->taskStats['completed']) ?> zakończone</span>
                <span>○ <?= e($m->taskStats['open']) ?> otwarte</span>
                <?php if ($m->taskStats['overdue'] > 0): ?><span class="badge-attention">! <?= e($m->taskStats['overdue']) ?> przeterminowane</span><?php endif; ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Telefony</div>
            <div class="stat-total"><?= e($m->callStats['total']) ?></div>
            <div class="stat-breakdown"><span>Wykonane: <?= e($m->callStats['done']) ?></span><span>Zaplanowane: <?= e($m->callStats['planned']) ?></span></div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Spotkania</div>
            <div class="stat-total"><?= e($m->meetingStats['total']) ?></div>
            <div class="stat-breakdown"><span>Zakończone: <?= e($m->meetingStats['done']) ?></span><span>Zaplanowane: <?= e($m->meetingStats['planned']) ?></span></div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Aktywności CRM</div>
            <div class="stat-total"><?= e($m->crmActivityStats['total']) ?></div>
            <div class="stat-breakdown"><span>Zakończone: <?= e($m->crmActivityStats['completed']) ?></span><span>Zaplanowane: <?= e($m->crmActivityStats['planned']) ?></span></div>
        </div>
    </div>
</div>

<div class="section">
    <h3 class="section-title">Historia aktywności</h3>
    <div class="timeline-filters">
        <a class="quick-filter <?= $selectedType === '' ? 'is-active' : '' ?>" href="?activity_type=">Wszystkie</a>
        <?php foreach ($activityTypes as $type): ?>
            <a class="quick-filter <?= $selectedType === $type->value ? 'is-active' : '' ?>" href="?activity_type=<?= e($type->value) ?>"><?= $type->icon() ?> <?= e($type->label()) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($timeline === []): ?>
        <div class="empty-state">Brak aktywności dla wybranego filtra.</div>
    <?php else: ?>
        <div class="timeline">
            <?php foreach ($timeline as $activity): ?>
                <div class="timeline-item <?= $activity->isCompleted() ? '' : 'is-planned' ?>">
                    <div class="timeline-date">
                        <?= e(\App\Support\TimeFormatter::dateTime($activity->timelineAt())) ?>
                        <?= $activity->isCompleted() ? '' : '<span class="cell-sub">(zaplanowane)</span>' ?>
                    </div>
                    <div class="timeline-title"><?= $activity->type->icon() ?> <?= e($activity->type->label()) ?></div>
                    <div class="timeline-desc">„<?= e($activity->title) ?>”<?= $activity->description !== null ? ' — ' . e($activity->description) : '' ?></div>
                    <div class="timeline-author"><?= e($activity->performedBy->name) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
