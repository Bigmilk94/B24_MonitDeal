<?php
/**
 * @var list<\App\Domain\Model\DealView> $deals
 * @var int $totalCount
 * @var int $filteredCount
 * @var list<\App\Domain\Model\User> $owners
 * @var list<\App\Domain\Model\Company> $companies
 * @var list<\App\Domain\Enum\DealStage> $stages
 * @var list<\App\Domain\Enum\ActivityType> $activityTypes
 * @var array<string, mixed> $params
 * @var \DateTimeImmutable $now
 */

$currentSort = (string) ($params['sort'] ?? 'last_activity');
$currentDir = (string) ($params['dir'] ?? 'desc');

$sortLink = static function (string $field, string $label) use ($currentSort, $currentDir): string {
    $nextDir = ($currentSort === $field && $currentDir === 'desc') ? 'asc' : 'desc';
    $cls = $currentSort === $field ? 'is-sorted' : '';
    $arrow = $currentSort === $field ? ($currentDir === 'desc' ? ' ↓' : ' ↑') : '';

    return '<a class="' . $cls . '" href="' . qs(['sort' => $field, 'dir' => $nextDir]) . '">' . e($label) . $arrow . '</a>';
};

$quick = (string) ($params['quick'] ?? '');
$quickFilters = [
    '' => 'Wszystkie',
    'active_today' => 'Aktywne dzisiaj',
    'stale_3' => 'Brak aktywności 3+ dni',
    'stale_7' => 'Brak aktywności 7+ dni',
    'no_next_action' => 'Brak następnego działania',
    'overdue_tasks' => 'Przeterminowane zadania',
];

$sel = static fn (mixed $value, mixed $needle): string => (string) $value === (string) $needle ? 'selected' : '';
$asArray = static fn (mixed $v): array => is_array($v) ? $v : ($v !== null && $v !== '' ? [$v] : []);
$selectedStages = $asArray($params['stage'] ?? null);
$selectedOwners = $asArray($params['owner'] ?? null);
$selectedCompanies = $asArray($params['company'] ?? null);
?>

<div class="filters-bar">
    <?php foreach ($quickFilters as $key => $label): ?>
        <a class="quick-filter <?= $quick === $key ? 'is-active' : '' ?>" href="<?= qs(['quick' => $key ?: null]) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<form class="filters-panel" method="get" action="/deals">
    <input type="hidden" name="q" value="<?= e($params['q'] ?? '') ?>">
    <input type="hidden" name="sort" value="<?= e($currentSort) ?>">
    <input type="hidden" name="dir" value="<?= e($currentDir) ?>">
    <input type="hidden" name="quick" value="<?= e($quick) ?>">

    <div class="field">
        <label>Etap</label>
        <select name="stage[]" multiple size="4">
            <?php foreach ($stages as $stage): ?>
                <option value="<?= e($stage->value) ?>" <?= in_array($stage->value, $selectedStages, true) ? 'selected' : '' ?>><?= e($stage->label()) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label>Osoba odpowiedzialna</label>
        <select name="owner[]" multiple size="4">
            <?php foreach ($owners as $owner): ?>
                <option value="<?= e($owner->id) ?>" <?= in_array($owner->id, $selectedOwners, true) ? 'selected' : '' ?>><?= e($owner->name) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label>Klient</label>
        <select name="company[]" multiple size="4">
            <?php foreach ($companies as $company): ?>
                <option value="<?= e($company->id) ?>" <?= in_array($company->id, $selectedCompanies, true) ? 'selected' : '' ?>><?= e($company->name) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label>Utworzono od</label>
        <input type="date" name="created_from" value="<?= e($params['created_from'] ?? '') ?>">
    </div>
    <div class="field">
        <label>Utworzono do</label>
        <input type="date" name="created_to" value="<?= e($params['created_to'] ?? '') ?>">
    </div>
    <div class="field">
        <label>Zamknięcie od</label>
        <input type="date" name="close_from" value="<?= e($params['close_from'] ?? '') ?>">
    </div>
    <div class="field">
        <label>Zamknięcie do</label>
        <input type="date" name="close_to" value="<?= e($params['close_to'] ?? '') ?>">
    </div>
    <div class="field">
        <label>Ostatnia aktywność od</label>
        <input type="date" name="last_activity_from" value="<?= e($params['last_activity_from'] ?? '') ?>">
    </div>
    <div class="field">
        <label>Ostatnia aktywność do</label>
        <input type="date" name="last_activity_to" value="<?= e($params['last_activity_to'] ?? '') ?>">
    </div>
    <div class="field">
        <label>Rodzaj ostatniej aktywności</label>
        <select name="last_activity_type">
            <option value="">Dowolny</option>
            <?php foreach ($activityTypes as $type): ?>
                <option value="<?= e($type->value) ?>" <?= $sel($params['last_activity_type'] ?? '', $type->value) ?>><?= e($type->label()) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label>Otwarte zadania</label>
        <select name="has_open_tasks">
            <option value="">Dowolnie</option>
            <option value="1" <?= $sel($params['has_open_tasks'] ?? '', '1') ?>>Tak</option>
            <option value="0" <?= $sel($params['has_open_tasks'] ?? '', '0') ?>>Nie</option>
        </select>
    </div>
    <div class="field">
        <label>Przeterminowane zadania</label>
        <select name="has_overdue_tasks">
            <option value="">Dowolnie</option>
            <option value="1" <?= $sel($params['has_overdue_tasks'] ?? '', '1') ?>>Tak</option>
            <option value="0" <?= $sel($params['has_overdue_tasks'] ?? '', '0') ?>>Nie</option>
        </select>
    </div>
    <div class="field">
        <label>Ma zaplanowane działanie</label>
        <select name="has_next_action">
            <option value="">Dowolnie</option>
            <option value="1" <?= $sel($params['has_next_action'] ?? '', '1') ?>>Tak</option>
            <option value="0" <?= $sel($params['has_next_action'] ?? '', '0') ?>>Nie</option>
        </select>
    </div>
    <div class="field">
        <label>Brak aktywności od (dni)</label>
        <input type="number" min="0" name="stale_days" value="<?= e($params['stale_days'] ?? '') ?>" placeholder="np. 5">
    </div>

    <div class="filters-actions">
        <button type="submit" class="btn btn-primary">Zastosuj filtry</button>
        <a class="btn" href="/deals">Wyczyść</a>
    </div>
</form>

<div class="results-meta">
    Pokazano <?= e($filteredCount) ?> z <?= e($totalCount) ?> deali
    <?php if (!empty($params['q'])): ?> · szukano: „<?= e($params['q']) ?>”<?php endif; ?>
</div>

<div class="table-wrap">
<table class="deals-table">
    <thead>
    <tr>
        <th>Deal</th>
        <th>Klient</th>
        <th>Etap</th>
        <th><?= $sortLink('value', 'Wartość') ?></th>
        <th><?= $sortLink('created_at', 'Utworzono') ?></th>
        <th><?= $sortLink('expected_close_at', 'Przewidywane zamknięcie') ?></th>
        <th><?= $sortLink('last_activity', 'Ostatnia aktywność') ?></th>
        <th>Rodzaj aktywności</th>
        <th><?= $sortLink('days_on_stage', 'Dni na etapie') ?></th>
        <th>E-maile</th>
        <th><?= $sortLink('open_tasks', 'Zadania') ?></th>
        <th><?= $sortLink('total_activities', 'Postęp') ?></th>
        <th>Następne działanie</th>
        <th>Status</th>
    </tr>
    </thead>
    <tbody>
    <?php if ($deals === []): ?>
        <tr><td colspan="14" class="empty-state">Brak deali spełniających wybrane kryteria.</td></tr>
    <?php else: ?>
        <?php foreach ($deals as $view): ?>
            <?= $this->partial('deal_row', ['view' => $view, 'now' => $now]) ?>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>
</div>
