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
        <div class="form-group">
            <label class="form-label">Đăng kèm</label>
            <div style="display:flex;gap:12px;margin-top:6px">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
                    <input type="radio" name="media_type" value="auto" checked> <span>Tự động</span>
                </label>
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
                    <input type="radio" name="media_type" value="image"> <span>Chỉ ảnh</span>
                </label>
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
                    <input type="radio" name="media_type" value="video"> <span>Chỉ video</span>
                </label>
            </div>
        </div>
        <button type="submit" class="btn btn-purple mt-8">Lên lịch cho Fanpage</button>
    </form>
</div>

<div class="card mt-16">
    <h3 class="card-title mb-16">📋 Danh sách bài đăng Facebook</h3>
    <?php
    // Build a unified list: scheduled posts + approved content not yet scheduled
    $allItems = [];

    // Add scheduled posts (from $facebookPosts)
    foreach ($facebookPosts as $post) {
        $content = $postContents[(int)($post['content_id'] ?? 0)] ?? null;
        $allItems[] = [
            'type' => 'post',
            'post' => $post,
            'content' => $content,
            'is_scheduled' => ($post['status'] ?? '') === 'scheduled',
        ];
    }

    // Add approved content not yet scheduled
    foreach ($approvedUnscheduled as $content) {
        $allItems[] = [
            'type' => 'unscheduled',
            'post' => null,
            'content' => $content,
            'is_scheduled' => false,
        ];
    }

    // Sort: scheduled first, then by scheduled_at
    usort($allItems, function ($a, $b) {
        if ($a['is_scheduled'] !== $b['is_scheduled']) return $a['is_scheduled'] ? -1 : 1;
        return strcmp((string)($b['post']['scheduled_at'] ?? ''), (string)($a['post']['scheduled_at'] ?? ''));
    });
    ?>
    <?php if (empty($allItems)): ?>
        <div class="empty-state"><p>Chưa có bài đăng nào. Hãy duyệt content trong mục Contents trước.</p></div>
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
            <?php foreach ($allItems as $item): ?>
                <?php
                $post = $item['post'] ?? null;
                $content = $item['content'];
                $isPost = $item['type'] === 'post';
                $isScheduled = $item['is_scheduled'];
                $isApi = $post && ($post['channel'] ?? '') === 'fanpage_api';
                $hasImage = $content && !empty($content['image_url']);
                $hasVideo = $content && !empty($content['video_url']);
                $postMediaType = $post['media_type'] ?? 'auto';
                ?>
                <tr>
                    <td data-label="Nội dung">
                        <?php if ($isPost && $post): ?>
                            <strong><?= $content ? e((string)$content['title']) : ('Content #' . (int)$post['content_id']) ?></strong>
                            <div class="sub">Post #<?= (int)$post['id'] ?></div>
                        <?php else: ?>
                            <strong><?= e((string)$content['title']) ?></strong>
                            <div class="sub">Content #<?= (int)$content['id'] ?></div>
                        <?php endif; ?>
                        <?php if ($content && !empty($content['body'])): ?>
                            <div class="post-preview-text"><?= e(mb_substr((string)($content['body'] ?? ''), 0, 100)) ?>…</div>
                        <?php endif; ?>
                    </td>
                    <td data-label="Media Preview">
                        <div class="media-gallery" style="gap:4px">
                            <?php if ($hasImage): ?>
                                <img class="media-thumb clickable-media" src="<?= e((string)$content['image_url']) ?>" alt="" data-type="image" data-url="<?= e((string)$content['image_url']) ?>" style="height:40px;width:40px;object-fit:cover;border-radius:4px;border:1px solid var(--border)">
                            <?php endif; ?>
                            <?php if ($hasVideo): ?>
                                <video class="media-thumb clickable-media" src="<?= e((string)$content['video_url']) ?>" data-type="video" data-url="<?= e((string)$content['video_url']) ?>" style="height:40px;width:40px;object-fit:cover;border-radius:4px;border:1px solid var(--accent)" muted></video>
                            <?php endif; ?>
                            <?php if (!$hasImage && !$hasVideo): ?>
                                <span class="text-muted" style="font-size:11px">— chưa tạo</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td data-label="Media">
                        <?php if ($isScheduled && $isPost && $post): ?>
                            <form data-ajax method="POST" action="<?= url('/posts/update-media-type') ?>" style="display:flex;flex-direction:column;gap:4px">
                                <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                                <select name="media_type" style="padding:3px 6px;border-radius:4px;font-size:11px" onchange="this.form.submit()">
                                    <option value="auto" <?= $postMediaType === 'auto' ? 'selected' : '' ?>>Tự động</option>
                                    <option value="image" <?= $postMediaType === 'image' ? 'selected' : '' ?> <?= !$hasImage ? 'disabled' : '' ?>>Ảnh <?= !$hasImage ? '(❌)' : '' ?></option>
                                    <option value="video" <?= $postMediaType === 'video' ? 'selected' : '' ?> <?= !$hasVideo ? 'disabled' : '' ?>>Video <?= !$hasVideo ? '(❌)' : '' ?></option>
                                </select>
                            </form>
                        <?php elseif (!$isScheduled): ?>
                            <form data-ajax method="POST" action="<?= url('/posts/schedule') ?>" style="display:flex;flex-direction:column;gap:4px">
                                <select name="media_type" style="padding:3px 6px;border-radius:4px;font-size:11px">
                                    <option value="auto" <?= !$hasVideo ? 'selected' : '' ?>>Tự động</option>
                                    <option value="image" <?= $hasVideo && $hasImage ? '' : '' ?> <?= !$hasImage ? 'disabled' : '' ?>>Ảnh <?= !$hasImage ? '(❌)' : '' ?></option>
                                    <option value="video" <?= $hasVideo ? 'selected' : '' ?> <?= !$hasVideo ? 'disabled' : '' ?>>Video <?= !$hasVideo ? '(❌)' : '' ?></option>
                                </select>
                                <input type="hidden" name="content_id" value="<?= (int)$content['id'] ?>">
                                <input type="hidden" name="channel" value="fanpage_api">
                                <input type="hidden" name="scheduled_at" value="<?= e($defaultStartLocal) ?>">
                                <button type="submit" class="btn btn-sm btn-purple">📅 Lên lịch</button>
                            </form>
                        <?php else: ?>
                            <span class="badge"><?= $postMediaType === 'auto' ? 'Auto' : ucfirst($postMediaType) ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Lịch / kết quả">
                        <?php if ($isPost && $post): ?>
                            <div class="text-sm"><span class="text-muted">Lên lịch:</span> <?= e((string)($post['scheduled_at'] ?? '—')) ?></div>
                            <?php if (!empty($post['posted_at'])): ?>
                                <div class="text-sm"><span class="text-muted">Đã đăng:</span> <?= e((string)$post['posted_at']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($post['result_note'])): ?>
                                <div class="sub mt-4"><?= e((string)$post['result_note']) ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="sub">— chưa lên lịch</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Trạng thái">
                        <?php if ($isPost && $post): ?>
                            <?= status_badge((string)$post['status']) ?>
                        <?php else: ?>
                            <span class="badge badge-pending">Chờ lên lịch</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Thao tác">
                        <?php if ($isScheduled && $isApi): ?>
                            <form data-ajax method="POST" action="<?= url('/posts/publish') ?>" class="inline">
                                <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                                <button type="submit" class="btn btn-success btn-sm">Đăng ngay</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($isScheduled && ($post['status'] ?? '') === 'scheduled'): ?>
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
