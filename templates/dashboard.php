<?php
/**
 * @var int $activeDealsCount
 * @var float $totalActiveValue
 * @var int $staleCount
 * @var int $noNextActionCount
 * @var int $overdueDealsCount
 * @var int $doneToday
 * @var int $plannedToday
 * @var list<\App\Domain\Model\DealView> $needsAttention
 */
?>
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Aktywne deale</div>
        <div class="kpi-value"><?= e($activeDealsCount) ?></div>
        <div class="kpi-sub">w toku (bez wygranych/przegranych)</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Wartość aktywnych deali</div>
        <div class="kpi-value"><?= e(money($totalActiveValue, 'PLN')) ?></div>
        <div class="kpi-sub">suma wartości otwartych deali</div>
    </div>
    <div class="kpi-card <?= $staleCount > 0 ? 'is-attention' : '' ?>">
        <div class="kpi-label">Bez aktywności 7+ dni</div>
        <div class="kpi-value"><?= e($staleCount) ?></div>
        <div class="kpi-sub">deale wymagające kontaktu</div>
    </div>
    <div class="kpi-card <?= $noNextActionCount > 0 ? 'is-warning' : '' ?>">
        <div class="kpi-label">Brak następnego działania</div>
        <div class="kpi-value"><?= e($noNextActionCount) ?></div>
        <div class="kpi-sub">nic nie zaplanowano</div>
    </div>
    <div class="kpi-card <?= $overdueDealsCount > 0 ? 'is-attention' : '' ?>">
        <div class="kpi-label">Z przeterminowanymi zadaniami</div>
        <div class="kpi-value"><?= e($overdueDealsCount) ?></div>
        <div class="kpi-sub">wymaga interwencji</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Działania dziś — wykonane</div>
        <div class="kpi-value"><?= e($doneToday) ?></div>
        <div class="kpi-sub">wszystkie deale</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Działania dziś — zaplanowane</div>
        <div class="kpi-value"><?= e($plannedToday) ?></div>
        <div class="kpi-sub">na dzisiaj</div>
    </div>
</div>

<div class="section">
    <h2 class="section-title">Deale wymagające uwagi</h2>
    <p class="section-subtitle">Brak aktywności od dłuższego czasu, brak zaplanowanego działania lub przeterminowane zadania.</p>

    <?php if ($needsAttention === []): ?>
        <div class="empty-state">Świetnie — żaden aktywny deal nie wymaga obecnie szczególnej uwagi.</div>
    <?php else: ?>
        <div class="attention-list">
            <?php foreach ($needsAttention as $view): ?>
                <div class="attention-row">
                    <div class="attention-row-main">
                        <a class="attention-row-title" href="/deals/<?= e($view->deal->id) ?>"><?= e($view->deal->title) ?></a>
                        <span class="attention-row-sub">
                            <?= e($view->company->name) ?> · <?= e($view->owner->name) ?> ·
                            ostatnia aktywność: <?= e($view->metrics->lastActivityRelative) ?>
                        </span>
                    </div>
                    <div class="attention-flags">
                        <?= $this->partial('flags', ['flags' => $view->metrics->flags]) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
