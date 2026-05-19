<?php
/** @var array $postSummary */
/** @var array $posts */
/** @var array $contents */
/** @var array $postContents */
/** @var bool $fanpageApiReady */
/** @var array $integrationStatus */

$totalPosts = (int)($postSummary['total'] ?? 0);
$scheduledCount = (int)($postSummary['scheduled'] ?? 0);
$successCount = (int)($postSummary['success'] ?? 0);
$failedCount = (int)($postSummary['failed'] ?? 0);

// Build post counts by channel
$byChannel = [];
foreach ($posts as $p) {
    $ch = $p['channel'] ?? 'unknown';
    if (!isset($byChannel[$ch])) $byChannel[$ch] = 0;
    $byChannel[$ch]++;
}

// Count approved content not yet scheduled
$postsByContentId = [];
foreach ($posts as $p) {
    $postsByContentId[(int)($p['content_id'] ?? 0)] = true;
}
$unscheduledApproved = array_filter($contents, static fn($c) =>
    ($c['status'] ?? '') === 'approved' && !isset($postsByContentId[(int)($c['id'] ?? 0)])
);

// Build a unified list: scheduled posts + unscheduled approved content
$allItems = [];
foreach ($posts as $post) {
    $content = $postContents[(int)($post['content_id'] ?? 0)] ?? null;
    $allItems[] = [
        'type' => 'post',
        'post' => $post,
        'content' => $content,
        'is_scheduled' => ($post['status'] ?? '') === 'scheduled',
    ];
}
foreach ($unscheduledApproved as $content) {
    $allItems[] = [
        'type' => 'unscheduled',
        'post' => null,
        'content' => $content,
        'is_scheduled' => false,
    ];
}
usort($allItems, function ($a, $b) {
    if ($a['is_scheduled'] !== $b['is_scheduled']) return $a['is_scheduled'] ? -1 : 1;
    return strcmp((string)($b['post']['scheduled_at'] ?? ''), (string)($a['post']['scheduled_at'] ?? ''));
});

$channelLabels = [
    'fanpage_api' => '📘 Facebook Fanpage API',
    'fanpage_manual' => '📘 Facebook thủ công',
    'facebook_group' => '📘 Facebook Group',
    'tiktok' => '🎵 TikTok',
    'instagram' => '📷 Instagram',
    'threads' => '💬 Threads',
];
$channelIcons = [
    'fanpage_api' => '📘',
    'fanpage_manual' => '📘',
    'facebook_group' => '📘',
    'tiktok' => '🎵',
    'instagram' => '📷',
    'threads' => '💬',
];
?>

<div class="page-header">
    <div>
        <div class="page-kicker">Quản lý đăng bài</div>
        <h2>Dashboard Đăng bài</h2>
        <p>Theo dõi & lên lịch đăng content lên các nền tảng.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-ghost" href="<?= url('/contents/list') ?>">📝 Contents</a>
        <a class="btn btn-ghost" href="<?= url('/prompts') ?>">⚙️ Prompts</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card accent">
        <div class="label">Tổng bài đăng</div>
        <div class="value"><?= $totalPosts ?></div>
    </div>
    <div class="stat-card warning">
        <div class="label">Chờ đăng</div>
        <div class="value"><?= $scheduledCount ?></div>
    </div>
    <div class="stat-card success">
        <div class="label">Thành công</div>
        <div class="value"><?= $successCount ?></div>
    </div>
    <div class="stat-card danger">
        <div class="label">Thất bại</div>
        <div class="value"><?= $failedCount ?></div>
    </div>
</div>

<!-- Channel quick stats -->
<div class="card mt-16">
    <h3 class="card-title mb-16">📡 Nền tảng đã kết nối</h3>
    <div class="grid-4" style="gap:12px">
        <?php foreach ($byChannel as $ch => $count): ?>
            <div class="stat-card" style="padding:12px">
                <div class="label"><?= $channelIcons[$ch] ?? '📡' ?> <?= ucfirst(str_replace('_', ' ', $ch)) ?></div>
                <div class="value"><?= $count ?></div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($byChannel)): ?>
            <div class="empty-state"><p>Chưa có bài đăng nào.</p></div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Schedule Section -->
<div class="grid-2 mt-16" style="gap:16px">
    <!-- Facebook Quick Schedule -->
    <div class="card">
        <h3 class="card-title mb-16">📘 Facebook Fanpage</h3>
        <?php if ($fanpageApiReady): ?>
            <div class="mini-stats mb-12">
                <span class="badge badge-success">✅ API ready</span>
                <span class="text-muted ml-8"><?= count(array_filter($posts, static fn($p) => ($p['channel'] ?? '') === 'fanpage_api' && ($p['status'] ?? '') === 'scheduled')) ?> bài chờ</span>
            </div>
            <form data-ajax method="POST" action="<?= url('/posts/schedule-all') ?>">
                <input type="hidden" name="channel" value="fanpage_api">
                <div class="grid-2 compact-grid mb-8">
                    <div class="form-group">
                        <label class="form-label">Số bài</label>
                        <input class="form-control" name="limit" type="number" min="1" max="20" value="3">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cách (phút)</label>
                        <input class="form-control" type="number" min="1" max="1440" name="interval_minutes" value="15">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Bắt đầu lúc</label>
                    <input class="form-control" type="datetime-local" name="scheduled_at" value="<?= date('Y-m-d\TH:i', time() + 3600) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Đăng kèm</label>
                    <div style="display:flex;gap:10px;margin-top:4px">
                        <label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:13px">
                            <input type="radio" name="media_type" value="auto" checked> Tự động
                        </label>
                        <label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:13px">
                            <input type="radio" name="media_type" value="image"> Ảnh
                        </label>
                        <label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:13px">
                            <input type="radio" name="media_type" value="video"> Video
                        </label>
                    </div>
                </div>
                <button type="submit" class="btn btn-purple mt-8">📅 Lên lịch Facebook</button>
            </form>
            <div class="mt-12">
                <a class="btn btn-ghost btn-sm" href="<?= url('/posts/facebook') ?>">Xem chi tiết →</a>
            </div>
        <?php else: ?>
            <div class="hint-box">Chưa cấu hình Facebook Page Access Token. <a href="<?= url('/settings') ?>">Vào Settings</a></div>
        <?php endif; ?>
    </div>

    <!-- TikTok Quick Schedule -->
    <div class="card">
        <h3 class="card-title mb-16">🎵 TikTok</h3>
        <div class="mini-stats mb-12">
            <span class="badge badge-warning">⚠️ Browser</span>
            <span class="text-muted ml-8"><?= count(array_filter($posts, static fn($p) => ($p['channel'] ?? '') === 'tiktok' && ($p['status'] ?? '') === 'scheduled')) ?> bài chờ</span>
        </div>
        <form data-ajax method="POST" action="<?= url('/posts/schedule-all') ?>">
            <input type="hidden" name="channel" value="tiktok">
            <div class="grid-2 compact-grid mb-8">
                <div class="form-group">
                    <label class="form-label">Số bài</label>
                    <input class="form-control" name="limit" type="number" min="1" max="20" value="3">
                </div>
                <div class="form-group">
                    <label class="form-label">Cách (phút)</label>
                    <input class="form-control" type="number" min="1" max="1440" name="interval_minutes" value="20">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Bắt đầu lúc</label>
                <input class="form-control" type="datetime-local" name="scheduled_at" value="<?= date('Y-m-d\TH:i', time() + 3600) ?>">
            </div>
            <button type="submit" class="btn btn-purple mt-8">📅 Lên lịch TikTok</button>
        </form>
        <div class="mt-12">
            <a class="btn btn-ghost btn-sm" href="<?= url('/posts/tiktok') ?>">Xem chi tiết →</a>
        </div>
    </div>
</div>

<!-- Full Posts Table -->
<div class="card mt-16">
    <h3 class="card-title mb-16">📋 Tất cả bài đăng</h3>
    <?php if (empty($allItems)): ?>
        <div class="empty-state">
            <p>Chưa có bài đăng nào.</p>
            <p class="section-note">Hãy <a href="<?= url('/contents/list') ?>">sinh content</a> và duyệt rồi lên lịch để đăng.</p>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nội dung</th>
                    <th>Nền tảng</th>
                    <th>Media</th>
                    <th>Lịch / Kết quả</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (array_slice($allItems, 0, 50) as $item): ?>
                <?php
                $post = $item['post'] ?? null;
                $content = $item['content'];
                $isPost = $item['type'] === 'post';
                $isScheduled = $item['is_scheduled'];
                $isApi = $post && in_array($post['channel'] ?? '', ['fanpage_api', 'tiktok'], true);
                $hasImage = $content && !empty($content['image_url']);
                $hasVideo = $content && !empty($content['video_url']);
                $channel = $post['channel'] ?? 'facebook';
                $postMediaType = $post['media_type'] ?? 'auto';
                $contentPlatform = $content['platform'] ?? 'general';
                ?>
                <tr>
                    <td data-label="Nội dung">
                        <strong><?= e((string)($content['title'] ?? ('Content #' . ($content['id'] ?? $post['content_id'] ?? '?')))) ?></strong>
                        <?php if ($content && !empty($content['body'])): ?>
                            <div class="post-preview-text"><?= e(mb_substr((string)$content['body'], 0, 100)) ?>…</div>
                        <?php endif; ?>
                    </td>
                    <td data-label="Nền tảng">
                        <span class="platform-badge"><?= $channelIcons[$channel] ?? '📡' ?> <?= ucfirst(str_replace('_', ' ', $channel)) ?></span>
                    </td>
                    <td data-label="Media">
                        <div class="media-gallery" style="gap:4px;flex-direction:column">
                            <?php if ($hasImage): ?>
                                <img class="media-thumb clickable-media" src="<?= e((string)$content['image_url']) ?>" alt="" data-type="image" data-url="<?= e((string)$content['image_url']) ?>" style="height:36px;width:36px;object-fit:cover;border-radius:4px;border:1px solid var(--border)">
                            <?php endif; ?>
                            <?php if ($hasVideo): ?>
                                <video class="media-thumb clickable-media" src="<?= e((string)$content['video_url']) ?>" data-type="video" data-url="<?= e((string)$content['video_url']) ?>" style="height:36px;width:36px;object-fit:cover;border-radius:4px;border:1px solid var(--accent)" muted></video>
                            <?php endif; ?>
                            <?php if (!$hasImage && !$hasVideo): ?>
                                <span class="sub">—</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td data-label="Lịch / Kết quả">
                        <?php if ($isPost && $post): ?>
                            <div class="text-sm"><span class="text-muted">Lên:</span> <?= e((string)($post['scheduled_at'] ?? '—')) ?></div>
                            <?php if (!empty($post['posted_at'])): ?>
                                <div class="text-sm"><span class="text-muted">Đã:</span> <?= e((string)$post['posted_at']) ?></div>
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
                            <?php if ($postMediaType !== 'auto'): ?>
                                <div class="sub" style="font-size:10px"><?= $postMediaType === 'image' ? '🖼️' : '🎬' ?> <?= ucfirst($postMediaType) ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge badge-pending">Chờ lên lịch</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Thao tác">
                        <?php if ($isScheduled && $isApi): ?>
                            <form data-ajax method="POST" action="<?= url('/posts/publish') ?>" class="inline">
                                <input type="hidden" name="post_id" value="<?= (int)($post['id'] ?? 0) ?>">
                                <button type="submit" class="btn btn-success btn-sm">Đăng ngay</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($isPost && $post && ($post['status'] ?? '') === 'scheduled'): ?>
                            <form data-ajax method="POST" action="<?= url('/posts/mark-success') ?>" class="inline">
                                <input type="hidden" name="post_id" value="<?= (int)($post['id'] ?? 0) ?>">
                                <button type="submit" class="btn btn-accent btn-sm">✓ Đã đăng</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($item['type'] === 'unscheduled'): ?>
                            <form data-ajax method="POST" action="<?= url('/posts/schedule') ?>" class="inline">
                                <input type="hidden" name="content_id" value="<?= (int)($content['id'] ?? 0) ?>">
                                <input type="hidden" name="channel" value="<?= $channel ?>">
                                <input type="hidden" name="media_type" value="auto">
                                <input type="hidden" name="scheduled_at" value="<?= date('Y-m-d\TH:i', time() + 3600) ?>">
                                <button type="submit" class="btn btn-purple btn-sm">📅 Lên lịch</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (count($allItems) > 50): ?>
            <div class="sub mt-8 text-center">Hiển thị 50 / <?= count($allItems) ?> bài</div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.grid-4{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px}
.mt-8{margin-top:8px}.mt-12{margin-top:12px}.mt-16{margin-top:16px}.mb-8{margin-bottom:8px}.mb-12{margin-bottom:12px}.mb-16{margin-bottom:16px}.ml-8{margin-left:8px}
.btn-ghost{background:transparent;border:1px solid var(--border);color:var(--text);padding:6px 12px;border-radius:6px;cursor:pointer;text-decoration:none;display:inline-block}
.btn-ghost:hover{background:var(--bg-hover)}
.post-preview-text{font-size:11px;color:var(--text-muted);margin-top:4px;max-height:32px;overflow:hidden;line-height:1.4}
.inline{display:inline}

/* Lightbox for posts/list */
.lightbox-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:9999;align-items:center;justify-content:center;flex-direction:column}
.lightbox-overlay.active{display:flex}
.lightbox-overlay img,.lightbox-overlay video{max-width:90vw;max-height:85vh;object-fit:contain;border-radius:8px}
.lightbox-overlay .lb-close{position:absolute;top:16px;right:20px;color:#fff;font-size:28px;cursor:pointer;background:none;border:none;line-height:1}
.lightbox-overlay .lb-close:hover{color:var(--accent)}
.lightbox-overlay .lb-type{color:#fff;font-size:13px;margin-top:12px;opacity:.7}
</style>

<!-- Lightbox Modal -->
<div class="lightbox-overlay" id="lightbox" onclick="if(event.target.id==='lightbox')closeLightbox()">
    <button class="lb-close" onclick="closeLightbox()">✕</button>
    <div id="lb-content"></div>
    <div class="lb-type" id="lb-type"></div>
</div>

<script>
function openLightbox(url, type) {
    const lb = document.getElementById('lightbox');
    const content = document.getElementById('lb-content');
    const typeLabel = document.getElementById('lb-type');
    content.innerHTML = '';
    if (type === 'video') {
        const video = document.createElement('video');
        video.src = url;
        video.controls = true;
        video.autoplay = true;
        content.appendChild(video);
        typeLabel.textContent = '🎬 Video';
    } else {
        const img = document.createElement('img');
        img.src = url;
        content.appendChild(img);
        typeLabel.textContent = '🖼️ Ảnh';
    }
    lb.classList.add('active');
}

function closeLightbox() {
    document.getElementById('lightbox').classList.remove('active');
    document.getElementById('lb-content').innerHTML = '';
}

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('clickable-media')) {
        const url = e.target.dataset.url || e.target.src;
        const type = e.target.dataset.type || (e.target.tagName === 'VIDEO' ? 'video' : 'image');
        openLightbox(url, type);
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLightbox();
});
</script>