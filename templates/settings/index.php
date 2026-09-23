<?php
/**
 * @var list<array{id:int, name:string}> $funnels
 * @var array<int, list<\App\Domain\Model\DealStage>> $stagesByFunnel
 * @var \App\Bitrix24\Model\PortalConfig $config
 * @var list<\App\Domain\Enum\DealSemantic> $semantics
 * @var bool $saved
 */
$semanticLabels = [
    'open' => 'W toku',
    'won' => 'Wygrany',
    'lost' => 'Przegrany',
];
?>
<?php if ($saved): ?>
    <div class="flag flag-normal" style="margin-bottom: 16px; display: inline-flex;">Zapisano ustawienia.</div>
<?php endif; ?>

<form method="post" action="<?= e(base_path()) ?>/ustawienia">
    <div class="section">
        <h2 class="section-title">Lejki („funnels")</h2>
        <p class="section-subtitle">
            Wybierz, które lejki sprzedażowe mają być widoczne na dashboardzie
            i liście deali. Jeśli nic nie zaznaczysz, pokazywane będą
            wszystkie.
        </p>
        <?php foreach ($funnels as $funnel): ?>
            <label style="display:flex; align-items:center; gap:8px; padding:8px 0; border-bottom:1px solid var(--border);">
                <input
                    type="checkbox"
                    name="funnels[]"
                    value="<?= e($funnel['id']) ?>"
                    <?= $config->includesFunnel($funnel['id']) ? 'checked' : '' ?>
                >
                <strong><?= e($funnel['name']) ?></strong>
            </label>
        <?php endforeach; ?>
    </div>

    <?php foreach ($funnels as $funnel): ?>
        <div class="section">
            <h3 class="section-title"><?= e($funnel['name']) ?> — etapy</h3>
            <p class="section-subtitle">
                Bitrix24 sam oznacza etapy jako „w toku"/„wygrany"/„przegrany" —
                popraw to tutaj tylko jeśli w Twoim lejku jest inaczej.
            </p>
            <div class="table-wrap">
                <table class="deals-table">
                    <thead>
                    <tr>
                        <th>Etap</th>
                        <th>Traktuj jako</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($stagesByFunnel[$funnel['id']] ?? [] as $stage): ?>
                        <tr>
                            <td><?= e($stage->label()) ?></td>
                            <td>
                                <select name="semantic_<?= e($stage->value) ?>">
                                    <?php foreach ($semantics as $semantic): ?>
                                        <option value="<?= e($semantic->value) ?>" <?= $stage->semantic === $semantic ? 'selected' : '' ?>>
                                            <?= e($semanticLabels[$semantic->value] ?? $semantic->value) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (($stagesByFunnel[$funnel['id']] ?? []) === []): ?>
                        <tr><td colspan="2" class="empty-state">Brak etapów do pokazania.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>

    <button type="submit" class="btn btn-primary">Zapisz ustawienia</button>
</form>
