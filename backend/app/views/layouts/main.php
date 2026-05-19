<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle ?? 'Dashboard') ?> — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('/css/app.css') ?>?v=<?= filemtime(__DIR__.'/../../../public/css/app.css') ?>">
    <?php foreach (($pageCss ?? []) as $cssFile): ?>
        <link rel="stylesheet" href="<?= asset($cssFile) ?>">
    <?php endforeach; ?>
    <script src="<?= asset('/js/theme-init.js') ?>"></script>
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
    <nav class="sidebar-nav<?= ($currentPage ?? '') === 'posts' ? ' posts-active' : '' ?>">
        <?php
        $sidebarModules = [
            'DASHBOARD' => ['url' => '/',            'page' => 'dashboard', 'label' => 'Tổng quan',           'svg' => '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>'],
            'SCRAPER'   => ['url' => '/scraper',    'page' => 'scraper',   'label' => 'Product Radar',        'svg' => '<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35M11 8v6M8 11h6" stroke-width="2" fill="none" stroke="currentColor"/></svg>'],
            'ANALYTICS' => ['url' => '/analytics',  'page' => 'analytics', 'label' => 'Phân tích AI',         'svg' => '<svg viewBox="0 0 24 24"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>'],

            'PRODUCTS'  => ['url' => '/products',   'page' => 'products',  'label' => 'Sản phẩm',           'svg' => '<svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>'],
    'CONTENTS'      => ['url' => '/contents',        'page' => 'contents',  'label' => 'Tạo Content',       'svg' => '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>'],
    'CONTENTS_LIST' => ['url' => '/contents/list',    'page' => 'contents_list',   'label' => 'Dashboard',    'sub' => true],
    'CONTENTS_FB'   => ['url' => '/contents/facebook', 'page' => 'contents_facebook', 'label' => 'Facebook',     'sub' => true],
    'CONTENTS_TT'   => ['url' => '/contents/tiktok',   'page' => 'contents_tiktok',   'label' => 'TikTok',       'sub' => true],
    'CONTENTS_IG'   => ['url' => '/contents/instagram','page' => 'contents_instagram','label' => 'Instagram',    'sub' => true],
    'CONTENTS_THR'  => ['url' => '/contents/threads',  'page' => 'contents_threads', 'label' => 'Threads',      'sub' => true],
            'POSTS'     => ['url' => '/posts', 'page' => 'posts',  'label' => 'Đăng bài', 'svg' => '<svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>'],
            'POSTS_DASH' => ['url' => '/posts',       'page' => 'posts',  'label' => 'Dashboard',    'sub' => true],
            'POSTS_FB'  => ['url' => '/posts/facebook',   'page' => 'posts',   'label' => 'Facebook',    'sub' => true],
            'POSTS_TT'  => ['url' => '/posts/tiktok',     'page' => 'posts',   'label' => 'TikTok',      'sub' => true],
            'POSTS_IG'  => ['url' => '/posts/instagram',  'page' => 'posts',   'label' => 'Instagram',   'sub' => true],
            'POSTS_THR' => ['url' => '/posts/threads',    'page' => 'posts',   'label' => 'Threads',     'sub' => true],
        ];

        $systemModules = [
            'CHANNELS' => ['url' => '/channels', 'page' => 'channels', 'label' => 'Quản lý kênh', 'svg' => '<svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>'],
            'SETTINGS' => ['url' => '/settings', 'page' => 'settings', 'label' => 'Tự động hóa', 'svg' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 .6 1.65 1.65 0 0 0-.33 1V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1-.6 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-.6-1 1.65 1.65 0 0 0-1-.33H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0 .6-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-.6 1.65 1.65 0 0 0 .33-1V3a2 2 0 1 1 4 0v.09A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1 .6 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.36.3.57.72.6 1.19V10a1.65 1.65 0 0 0 1 .33H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51.67z"/></svg>'],
            'PROMPTS'  => ['url' => '/prompts',  'page' => 'prompts',  'label' => 'Prompt AI', 'svg' => '<svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>'],
            'AI_ASSISTANT' => ['url' => '/ai-assistant', 'page' => 'ai_assistant', 'label' => 'Trợ lý AI', 'svg' => '<svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/></svg>'],
            'LOGS'     => ['url' => '/logs',     'page' => 'logs',     'label' => 'Nhật ký',     'svg' => '<svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>'],
            'SERVER_INFO' => ['url' => '/server-info', 'page' => 'server_info', 'label' => 'Server Info', 'svg' => '<svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>'],
        ];

        $currentPage = $currentPage ?? '';
        $enabledModules = cached_enabled_modules();
        ?>

        <div class="nav-section">
            <div class="nav-section-title">Khám phá</div>
            <?php foreach ($sidebarModules as $modCode => $m): ?>
                <?php
                $isSub = !empty($m['sub']);
                $isEnabled = $isSub ? true : in_array($modCode, $enabledModules);
                $isActive = $currentPage === $m['page'];
                ?>
                <?php if ($isEnabled): ?>
                    <?php if ($isSub): ?>
                    <?php
                    $subGroup = null;
                    if (str_starts_with($modCode, 'POSTS')) $subGroup = 'posts';
                    elseif (str_starts_with($modCode, 'CONTENTS')) $subGroup = 'contents';
                    ?>
                    <a href="<?= url($m['url']) ?>" class="nav-item nav-item-sub nav-item-sub-<?= $subGroup ?>">
                        <?= e($m['label']) ?>
                        <span class="sub-arrow">›</span>
                    </a>
                    <?php else: ?>
                    <?php if ($modCode === 'POSTS'): ?>
                    <button class="nav-item nav-main <?= $isActive ? 'active' : '' ?>" onclick="togglePostsSubmenu(this)">
                        <?= isset($m['svg']) ? $m['svg'] : '' ?>
                        <span><?= e($m['label']) ?></span>
                        <span class="sub-arrow">▾</span>
                    </button>
                    <?php elseif ($modCode === 'CONTENTS'): ?>
                    <button class="nav-item nav-main <?= $isActive ? 'active' : '' ?>" onclick="toggleContentsSubmenu(this)">
                        <?= isset($m['svg']) ? $m['svg'] : '' ?>
                        <span><?= e($m['label']) ?></span>
                        <span class="sub-arrow">▾</span>
                    </button>
                    <?php else: ?>
                    <a href="<?= url($m['url']) ?>" class="nav-item <?= $isActive ? 'active' : '' ?>">
                        <?= isset($m['svg']) ? $m['svg'] : '' ?>
                        <?= e($m['label']) ?>
                    </a>
                    <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div class="nav-section">
            <div class="nav-section-title">Hệ thống</div>
            <?php foreach ($systemModules as $modCode => $m): ?>
                <?php if ($modCode === 'PROMPTS' || $modCode === 'AI_ASSISTANT' || $modCode === 'CHANNELS' || in_array($modCode, $enabledModules)): ?>
                    <a href="<?= url($m['url']) ?>" class="nav-item <?= $currentPage === $m['page'] ? 'active' : '' ?>">
                        <?= $m['svg'] ?>
                        <?= e($m['label']) ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if (hasPermission('admin.modules') || hasPermission('admin.users') || hasPermission('admin.sites')): ?>
                <div class="nav-section-title mt-3">Quản trị Hệ thống</div>
                <?php if (hasPermission('admin.modules')): ?>
                    <a href="<?= url('/admin/modules') ?>" class="nav-item <?= $currentPage === 'admin_modules' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        Modules
                    </a>
                <?php endif; ?>
                <?php if (hasPermission('admin.sites')): ?>
                    <a href="<?= url('/admin/sites') ?>" class="nav-item <?= $currentPage === 'admin_sites' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16M9 21v-4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v4M9 7h6M9 11h6"/></svg>
                        Sites & Chi nhánh
                    </a>
                    <a href="<?= url('/admin/telegram') ?>" class="nav-item <?= $currentPage === 'admin_telegram' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                        Telegram Bot
                    </a>
                <?php endif; ?>
                <?php if (hasPermission('admin.users')): ?>
                    <a href="<?= url('/admin/users') ?>" class="nav-item <?= $currentPage === 'admin_users' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Người dùng
                    </a>
                <?php endif; ?>
                <?php if (hasPermission('admin.permissions')): ?>
                    <a href="<?= url('/admin/permissions') ?>" class="nav-item <?= $currentPage === 'admin_permissions' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Phân quyền
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </nav>

    <?php
    $user = currentUser();
    $activeSite = currentSite();
    ?>
    <div class="sidebar-user">
        <a href="<?= url('/profile') ?>" class="sidebar-user-avatar">
            <?= $user ? strtoupper(mb_substr($user['name'], 0, 1)) : '?' ?>
        </a>
        <div class="sidebar-user-meta">
            <a href="<?= url('/profile') ?>" class="sidebar-user-name">
                <?= $user ? e($user['name']) : 'Guest' ?>
            </a>
            <div class="sidebar-user-role"><?= $user ? e($user['role_name']) : '' ?></div>
        </div>
        <a href="<?= url('/logout') ?>" title="Đăng xuất" class="sidebar-logout">⏻</a>
    </div>
</aside>

<main class="main fade-in">
    <?php
    $siteOptions = [];
    if ($user && hasPermission('admin.sites')) {
        try {
            $siteOptions = cached_active_sites_for_admin();
        } catch (Throwable $e) {
            $siteOptions = [];
        }
    }
    $canSwitchSite = count($siteOptions) > 1;
    ?>
    <div class="topbar">
        <div class="topbar-spacer"></div>
        <div class="topbar-actions">
            <button type="button" class="theme-toggle" id="themeToggle" title="Chuyển giao diện sáng/tối">🌙</button>
            <?php if ($user && hasPermission('admin.sites')): ?>
                <div class="site-switcher" id="siteSwitcher">
                    <button
                        type="button"
                        class="site-badge"
                        id="siteSwitcherButton"
                        aria-haspopup="true"
                        aria-expanded="false"
                        <?= $canSwitchSite ? '' : 'disabled'; ?>
                    >
                        <span><?= e($activeSite['name'] ?? ($activeSite['code'] ?? 'Trụ sở chính')); ?></span>
                        <?php if ($canSwitchSite): ?>
                            <span aria-hidden="true">▾</span>
                        <?php endif; ?>
                    </button>
                    <?php if ($canSwitchSite): ?>
                        <div class="site-dropdown" id="siteSwitcherMenu">
                            <div class="site-dropdown-title">Chuyển đổi site</div>
                            <?php foreach ($siteOptions as $site): ?>
                                <?php $isActiveSite = (int)($activeSite['id'] ?? 0) === (int)$site['id']; ?>
                                <a
                                    href="<?= url('/admin/sites/change/' . (int)$site['id']); ?>"
                                    class="site-dropdown-item <?= $isActiveSite ? 'active' : ''; ?>"
                                >
                                    <span><?= e((string)$site['name']); ?></span>
                                    <?php if ($isActiveSite): ?>
                                        <span class="small">✓</span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                            <a href="<?= url('/admin/sites'); ?>" class="site-dropdown-footer">Quản lý sites</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
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
<?php foreach (($pageJs ?? []) as $jsFile): ?>
    <script src="<?= asset($jsFile) ?>"></script>
<?php endforeach; ?>
<script>
(function() {
    var path = window.location.pathname || '';
    var nav = document.querySelector('.sidebar-nav');
    if (nav) {
        if (path.indexOf('/posts') === 0 && path.indexOf('/contents') !== 0) {
            nav.classList.add('posts-expanded');
        }
        if (path.indexOf('/contents') === 0) {
            nav.classList.add('contents-expanded');
        }
    }
})();

function togglePostsSubmenu(btn) {
    var nav = document.querySelector('.sidebar-nav');
    if (nav) {
        nav.classList.toggle('posts-expanded');
    }
}

function toggleContentsSubmenu(btn) {
    var nav = document.querySelector('.sidebar-nav');
    if (nav) {
        nav.classList.toggle('contents-expanded');
    }
}
</script>
</body>
</html>
