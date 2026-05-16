<?php
$postsByContentId = [];
foreach (($posts ?? []) as $post) {
    $postsByContentId[(int)($post['content_id'] ?? 0)] = $post;
}
$approvedUnscheduled = array_values(array_filter($contents ?? [], static function (array $content) use ($postsByContentId): bool {
    return ($content['status'] ?? '') === 'approved' && !isset($postsByContentId[(int)($content['id'] ?? 0)]);
}));

// Filter only tiktok posts
$tiktokPosts = array_filter($posts ?? [], static fn($p) => ($p['channel'] ?? '') === 'tiktok');
$videoUnscheduled = array_filter($approvedUnscheduled, static fn($c) => ($c['media_type'] ?? '') === 'video');

$defaultChannel = 'tiktok';
$defaultStartLocal = date('Y-m-d\TH:i', time() + 3600);
$defaultInterval = (int)($automationSettings['publish_interval_minutes'] ?? 15);
?>

<div class="page-header">
    <div>
        <div class="page-kicker">TikTok</div>
        <h2>Đăng bài TikTok</h2>
        <p>Đăng video tự động lên TikTok bằng Playwright browser automation.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-ghost" href="<?= url('/posts/facebook') ?>">Tất cả nền tảng</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card accent"><div class="label">Tổng TikTok</div><div class="value"><?= count($tiktokPosts) ?></div></div>
    <div class="stat-card warning"><div class="label">Chờ đăng</div><div class="value"><?= count(array_filter($tiktokPosts, static fn($p) => ($p['status'] ?? '') === 'scheduled')) ?></div></div>
    <div class="stat-card success"><div class="label">Thành công</div><div class="value"><?= count(array_filter($tiktokPosts, static fn($p) => ($p['status'] ?? '') === 'success')) ?></div></div>
    <div class="stat-card danger"><div class="label">Thất bại</div><div class="value"><?= count(array_filter($tiktokPosts, static fn($p) => ($p['status'] ?? '') === 'failed')) ?></div></div>
</div>

<div class="card mt-16">
    <h3 class="card-title mb-16">🗓️ Lên lịch đăng TikTok</h3>
    <div class="mini-stats mb-16">
        <span><strong><?= count($videoUnscheduled) ?></strong> video content đã duyệt chưa lên lịch</span>
        <span class="text-muted ml-8">(TikTok cần video 9:16)</span>
    </div>
    <form data-ajax method="POST" action="<?= url('/posts/schedule-all') ?>">
        <input type="hidden" name="channel" value="tiktok">
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
        <button type="submit" class="btn btn-purple mt-8">Lên lịch cho TikTok</button>
    </form>
</div>

<div class="card mt-16">
    <h3 class="card-title mb-16">📋 Danh sách bài đăng TikTok</h3>
    <?php if (empty($tiktokPosts)): ?>
        <div class="empty-state">
            <p>Chưa có bài đăng nào trên TikTok.</p>
            <p class="section-note">Cần có video (9:16 vertical) và cookie đăng nhập TikTok.</p>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead><tr>
                <th>Nội dung</th>
                <th>Media</th>
                <th>Lịch / Kết quả</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
            </tr></thead>
            <tbody>
            <?php foreach ($tiktokPosts as $post): ?>
                <?php
                $content = $postContents[(int)($post['content_id'] ?? 0)] ?? null;
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
                    <td data-label="Media">
                        <?php if ($content && !empty($content['media_url'])): ?>
                            <?php if (($content['media_type'] ?? '') === 'video'): ?>
                                <video class="media-thumb" src="<?= e((string)$content['media_url']) ?>" controls></video>
                            <?php elseif (($content['media_type'] ?? '') === 'image'): ?>
                                <img class="media-thumb" src="<?= e((string)$content['media_url']) ?>" alt="Media">
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
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
                        <?php if ($isScheduled): ?>
                            <form data-ajax method="POST" action="<?= url('/posts/publish') ?>" class="inline">
                                <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                                <button type="submit" class="btn btn-success btn-sm">Đăng ngay</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card mt-16">
    <h3 class="card-title mb-16">⚠️ Lưu ý TikTok</h3>
    <ul class="text-sm">
        <li>Cần có Node.js + Playwright cài sẵn trên server</li>
        <li>Video phải ở định dạng 9:16 (vertical) để upload lên TikTok</li>
        <li>Cần cookie đăng nhập TikTok hợp lệ (không expired)</li>
        <li>Script: <code>backend/scripts/tiktok_upload.js</code></li>
    </ul>
</div>