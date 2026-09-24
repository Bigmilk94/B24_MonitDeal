<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — MonitDeal</title>
<link rel="stylesheet" href="<?= e(base_path()) ?>/assets/css/app.css">
<script>
(function () {
    try {
        var theme = localStorage.getItem('monitdeal-theme');
        if (theme === 'light' || theme === 'dark') {
            document.documentElement.setAttribute('data-theme', theme);
        }
    } catch (e) {}
})();
</script>
<?php if (!empty($bitrix24Embedded)): ?>
<script src="https://api.bitrix24.com/api/v1/"></script>
<script>
(function () {
    // Bitrix24 trzyma ekran "Ładowanie aplikacji" w oknie nadrzędnym,
    // dopóki strona w iframe nie zawoła BX24.init() — bez tego aplikacja
    // działa poprawnie, ale nigdy się "nie pokazuje".
    if (typeof BX24 !== 'undefined') {
        BX24.init(function () {
            try { BX24.fitWindow(); } catch (e) {}
        });
    }
})();
</script>
<?php endif; ?>
</head>
<body>
<div class="app-shell">
    <?= $this->partial('sidebar', ['activeNav' => $activeNav]) ?>
    <div class="main-area">
        <?= $this->partial('topbar', ['pageTitle' => $pageTitle]) ?>
        <main class="content">
            <?= $content ?>
        </main>
    </div>
</div>
<script src="<?= e(base_path()) ?>/assets/js/app.js"></script>
</body>
</html>
