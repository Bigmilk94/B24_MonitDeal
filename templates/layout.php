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
