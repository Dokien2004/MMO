<?php
$postsByContentId = [];
foreach (($posts ?? []) as $post) {
    $postsByContentId[(int)($post['content_id'] ?? 0)] = $post;
}
$approvedUnscheduled = array_values(array_filter($contents ?? [], static function (array $content) use ($postsByContentId): bool {
    return ($content['status'] ?? '') === 'approved' && !isset($postsByContentId[(int)($content['id'] ?? 0)]);
}));

// Filter only facebook posts
$facebookPosts = array_filter($posts ?? [], static fn($p) => in_array($p['channel'] ?? '', ['fanpage_api', 'fanpage_manual', 'facebook_group'], true));
$fanpageApiPosts = array_filter($facebookPosts, static fn($p) => ($p['channel'] ?? '') === 'fanpage_api');
$fanpageManualPosts = array_filter($facebookPosts, static fn($p) => ($p['channel'] ?? '') === 'fanpage_manual');
$fbGroupPosts = array_filter($facebookPosts, static fn($p) => ($p['channel'] ?? '') === 'facebook_group');

$defaultChannel = 'fanpage_api';
$defaultStartLocal = date('Y-m-d\TH:i', time() + 3600);
$defaultInterval = (int)($automationSettings['publish_interval_minutes'] ?? 15);
?>

<div class="page-header">
    <div>
        <div class="page-kicker">Facebook</div>
        <h2>Quản lý đăng bài Facebook</h2>
        <p>Đăng tự động lên Fanpage hoặc Facebook Group bằng lịch trình.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-ghost" href="<?= url('/settings') ?>">Cấu hình Facebook</a>
        <a class="btn btn-ghost" href="<?= url('/posts') ?>">Tất cả nền tảng</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card accent"><div class="label">Tổng FB</div><div class="value"><?= count($facebookPosts) ?></div></div>
    <div class="stat-card success"><div class="label">Fanpage API</div><div class="value"><?= count($fanpageApiPosts) ?></div></div>
    <div class="stat-card warning"><div class="label">Thủ công</div><div class="value"><?= count($fanpageManualPosts) ?></div></div>
    <div class="stat-card accent"><div class="label">Groups</div><div class="value"><?= count($fbGroupPosts) ?></div></div>
</div>

<?php if ($fanpageApiReady): ?>
<div class="card mt-16">
    <h3 class="card-title mb-16">🚀 Đăng tự động Fanpage</h3>
    <div class="status-stack compact-status">
        <div class="status-line"><span>Fanpage API</span><?= status_badge($fanpageApiReady ? 'success' : 'failed') ?></div>
        <div class="status-line"><span>Bài chờ đăng</span><strong><?= count($fanpageApiPosts) ?></strong></div>
    </div>
    <form data-ajax method="POST" action="<?= url('/posts/publish-due') ?>" class="mt-16">
        <input type="hidden" name="limit" value="5">
        <button type="submit" class="btn btn-success">Đăng ngay các bài đến hạn</button>
    </form>
</div>
<?php else: ?>
<div class="hint-box mt-16">Chưa cấu hình Facebook Page Access Token. <a href="<?= url('/settings') ?>">Vào Settings</a> để cấu hình.</div>
<?php endif; ?>

<div class="card mt-16">
    <h3 class="card-title mb-16">🗓️ Lên lịch đăng Facebook</h3>
    <div class="mini-stats mb-16">
        <span><strong><?= count($approvedUnscheduled) ?></strong> content đã duyệt chưa lên lịch</span>
    </div>
    <form data-ajax method="POST" action="<?= url('/posts/schedule-all') ?>">
        <input type="hidden" name="channel" value="fanpage_api">
        <div class="grid-2 compact-grid">
            <div class="form-group">
                <label class="form-label">Số bài</label>
                <input class="form-control" name="limit" type="number" min="1" max="20" value="5">
            </div>
            <div class="form-group">
                <label class="form-label">Cách nhau (phút)</label>
                <input class="form-control" type="number" min="1" max="1440" name="interval_minutes" value="<?= max(1, $defaultInterval) ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Giờ bắt đầu</label>
            <input class="form-control" type="datetime-local" name="scheduled_at" value="<?= e($defaultStartLocal) ?>">
        </div>
        <button type="submit" class="btn btn-purple mt-8">Lên lịch cho Fanpage</button>
    </form>
</div>

<div class="card mt-16">
    <h3 class="card-title mb-16">📋 Danh sách bài đăng Facebook</h3>
    <?php if (empty($facebookPosts)): ?>
        <div class="empty-state"><p>Chưa có bài đăng nào trên Facebook.</p></div>
    <?php else: ?>
        <table class="data-table">
            <thead><tr>
                <th>Nội dung</th>
                <th>Kênh</th>
                <th>Lịch / Kết quả</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
            </tr></thead>
            <tbody>
            <?php foreach ($facebookPosts as $post): ?>
                <?php
                $content = $postContents[(int)($post['content_id'] ?? 0)] ?? null;
                $isApi = ($post['channel'] ?? '') === 'fanpage_api';
                $isScheduled = ($post['status'] ?? '') === 'scheduled';
                ?>
                <tr>
                    <td data-label="Nội dung">
                        <strong><?= $content ? e((string)$content['title']) : ('Content #' . (int)$post['content_id']) ?></strong>
                        <div class="sub">Post #<?= (int)$post['id'] ?></div>
                        <?php if ($content): ?>
                            <div class="post-preview-text"><?= e(mb_substr((string)($content['body'] ?? ''), 0, 120)) ?></div>
                        <?php endif; ?>
                    </td>
                    <td data-label="Kênh">
                        <span class="badge <?= $isApi ? 'badge-success' : 'badge-pending' ?>"><?= e((string)$post['channel']) ?></span>
                    </td>
                    <td data-label="Lịch / kết quả">
                        <div class="text-sm"><span class="text-muted">Lên lịch:</span> <?= e((string)$post['scheduled_at']) ?></div>
                        <?php if (!empty($post['posted_at'])): ?>
                            <div class="text-sm"><span class="text-muted">Đã đăng:</span> <?= e((string)$post['posted_at']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($post['result_note'])): ?>
                            <div class="sub mt-4"><?= e((string)$post['result_note']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td data-label="Trạng thái"><?= status_badge((string)$post['status']) ?></td>
                    <td data-label="Thao tác">
                        <?php if ($isScheduled && $isApi): ?>
                            <form data-ajax method="POST" action="<?= url('/posts/publish') ?>" class="inline">
                                <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                                <button type="submit" class="btn btn-success btn-sm">Đăng ngay</button>
                            </form>
                        <?php endif; ?>
                        <?php if (($post['status'] ?? '') === 'scheduled'): ?>
                            <form data-ajax method="POST" action="<?= url('/posts/mark-success') ?>" class="inline">
                                <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                                <button type="submit" class="btn btn-accent btn-sm">Đã đăng</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
