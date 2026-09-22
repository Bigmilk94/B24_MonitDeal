<aside class="sidebar">
    <div class="sidebar-brand">
        <span class="sidebar-brand-mark">MD</span>
        <span class="sidebar-brand-name">MonitDeal</span>
    </div>
    <nav class="sidebar-nav">
        <a href="/" class="nav-item <?= $activeNav === 'dashboard' ? 'is-active' : '' ?>">
            <span class="nav-icon">📊</span> Dashboard
        </a>
        <a href="/deals" class="nav-item <?= $activeNav === 'deals' ? 'is-active' : '' ?>">
            <span class="nav-icon">📁</span> Deale
        </a>
        <a href="/activities" class="nav-item <?= $activeNav === 'activities' ? 'is-active' : '' ?>">
            <span class="nav-icon">🕘</span> Aktywności
        </a>
        <a href="/tasks" class="nav-item <?= $activeNav === 'tasks' ? 'is-active' : '' ?>">
            <span class="nav-icon">✅</span> Zadania
        </a>
    </nav>
</aside>
