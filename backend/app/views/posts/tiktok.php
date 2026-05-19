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
$videoUnscheduled = array_filter($approvedUnscheduled, static fn($c) => !empty($c['video_url']));

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
                <th>Media Preview</th>
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
                    <td data-label="Media Preview">
                        <div class="media-gallery" style="gap:4px;flex-direction:column">
                            <?php if ($hasImage): ?>
                                <img class="media-thumb clickable-media" src="<?= e((string)$content['image_url']) ?>" alt="" data-type="image" data-url="<?= e((string)$content['image_url']) ?>" style="height:40px;width:40px;object-fit:cover;border-radius:4px;border:1px solid var(--border)">
                            <?php endif; ?>
                            <?php if ($hasVideo): ?>
                                <video class="media-thumb clickable-media" src="<?= e((string)$content['video_url']) ?>" data-type="video" data-url="<?= e((string)$content['video_url']) ?>" style="height:40px;width:40px;object-fit:cover;border-radius:4px;border:1px solid var(--accent)" muted></video>
                            <?php endif; ?>
                            <?php if (!$hasImage && !$hasVideo): ?>
                                <span class="text-muted" style="font-size:11px">—</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td data-label="Media">
                        <?php
                        $postMediaType = $post['media_type'] ?? 'auto';
                        $hasImage = $content && !empty($content['image_url']);
                        $hasVideo = $content && !empty($content['video_url']);
                        ?>
                        <?php if ($isScheduled): ?>
                            <form data-ajax method="POST" action="<?= url('/posts/update-media-type') ?>" style="display:inline-flex;gap:4px;align-items:center">
                                <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                                <select name="media_type" style="padding:3px 6px;border-radius:4px;font-size:11px" onchange="this.form.submit()">
                                    <option value="auto" <?= $postMediaType === 'auto' ? 'selected' : '' ?>>Auto</option>
                                    <option value="video" <?= $postMediaType === 'video' ? 'selected' : '' ?> <?= !$hasVideo ? 'disabled' : '' ?>>Video <?= !$hasVideo ? '(❌)' : '' ?></option>
                                    <option value="image" <?= $postMediaType === 'image' ? 'selected' : '' ?> <?= !$hasImage ? 'disabled' : '' ?>>Ảnh <?= !$hasImage ? '(❌)' : '' ?></option>
                                </select>
                            </form>
                            <div style="margin-top:4px;font-size:10px;color:var(--text-muted)">
                                <?php if ($hasImage): ?><span>🖼️ IMG</span><?php endif; ?>
                                <?php if ($hasVideo): ?><span>🎬 VID</span><?php endif; ?>
                            </div>
                        <?php else: ?>
                            <span class="badge"><?= $postMediaType === 'auto' ? 'Auto' : ucfirst($postMediaType) ?></span>
                            <div style="font-size:10px;color:var(--text-muted)">
                                <?php if ($hasImage): ?><span>🖼️</span><?php endif; ?>
                                <?php if ($hasVideo): ?><span>🎬</span><?php endif; ?>
                            </div>
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