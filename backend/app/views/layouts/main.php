<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Dashboard') ?> — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= asset('/css/app.css') ?>">
</head>
<body>

<button class="mobile-toggle" id="mobile-toggle"><span></span></button>
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="logo">A</div>
        <div>
            <h1>Affiliate MVP</h1>
            <small>Laptop Pipeline</small>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Tổng quan</div>
            <a href="<?= url('/') ?>" class="nav-item <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
                Dashboard
            </a>
        </div>
        <div class="nav-section">
            <div class="nav-section-title">Pipeline</div>
            <a href="<?= url('/products') ?>" class="nav-item <?= ($currentPage ?? '') === 'products' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                Sản phẩm
            </a>
            <a href="<?= url('/scraper') ?>" class="nav-item <?= ($currentPage ?? '') === 'scraper' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                Cào dữ liệu
            </a>
            <a href="<?= url('/links') ?>" class="nav-item <?= ($currentPage ?? '') === 'links' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                Affiliate Links
            </a>
            <a href="<?= url('/contents') ?>" class="nav-item <?= ($currentPage ?? '') === 'contents' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                Nội dung
            </a>
            <a href="<?= url('/posts') ?>" class="nav-item <?= ($currentPage ?? '') === 'posts' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                Đăng bài
            </a>
        </div>
        <div class="nav-section">
            <div class="nav-section-title">Hệ thống</div>
            <a href="<?= url('/settings') ?>" class="nav-item <?= ($currentPage ?? '') === 'settings' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 .6 1.65 1.65 0 0 0-.33 1V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1-.6 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-.6-1 1.65 1.65 0 0 0-1-.33H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0 .6-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-.6 1.65 1.65 0 0 0 .33-1V3a2 2 0 1 1 4 0v.09A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1 .6 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.36.3.57.72.6 1.19V10a1.65 1.65 0 0 0 1 .33H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51.67z"/></svg>
                Tự động hóa
            </a>
            <a href="<?= url('/logs') ?>" class="nav-item <?= ($currentPage ?? '') === 'logs' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Nhật ký
            </a>
        </div>
    </nav>
    <div style="padding: 16px 20px; border-top: 1px solid var(--border); font-size: 11px; color: var(--text-muted);">
        <?= e(APP_NAME) ?> · v1.0
    </div>
</aside>

<main class="main fade-in">
    <?php
    $flash = get_flash();
    if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>" data-auto-dismiss>
            <?= e($flash['text']) ?>
        </div>
    <?php endif; ?>

    <?= $__content ?>
</main>

<div class="toast-container" id="toast-container"></div>
<script src="<?= asset('/js/app.js') ?>"></script>
</body>
</html>
