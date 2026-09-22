<?php
/** @var list<string> $flags */
$flagClass = [
    'Aktywny deal' => 'flag-normal',
    'Ostatnia aktywność dzisiaj' => 'flag-normal',
    'Brak aktywności od 7 dni' => 'flag-attention',
    'Zadanie przeterminowane' => 'flag-attention',
    'Brak zaplanowanego następnego działania' => 'flag-warning',
    'Duża liczba otwartych zadań' => 'flag-warning',
];
?>
<?php foreach ($flags as $flag): ?>
    <span class="flag <?= e($flagClass[$flag] ?? 'flag-neutral') ?>"><?= e($flag) ?></span>
<?php endforeach; ?>
