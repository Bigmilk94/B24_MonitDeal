<header class="topbar">
    <h1 class="page-title"><?= e($pageTitle) ?></h1>
    <form class="topbar-search" action="/deals" method="get">
        <input type="search" name="q" placeholder="Szukaj deala, firmy, kontaktu…" value="<?= e($_GET['q'] ?? '') ?>">
    </form>
    <button type="button" id="theme-toggle" class="theme-toggle" aria-label="Przełącz motyw jasny/ciemny">
        <span class="theme-toggle-icon">🌙</span>
    </button>
</header>
