<?php
$defaultProvider = (string)($automationSettings['default_content_provider'] ?? 'gemini');
if ($defaultProvider === '') $defaultProvider = 'gemini';

$platform = 'facebook';
$platformLabel = 'Facebook';

$contentsPlatform = array_filter($contents ?? [], static fn($c) => ($c['platform'] ?? 'general') === $platform);
$contentByProductId = [];
foreach ($contentsPlatform as $content) {
    $contentByProductId[(int)($content['product_id'] ?? 0)] = $content;
}
$productsNeedContent = array_values(array_filter($products ?? [], static function (array $product) use ($contentByProductId): bool {
    return !empty($product['affiliate_url'] ?? '') && !isset($contentByProductId[(int)($product['id'] ?? 0)]);
}));
$contentsNeedImage = array_filter($contentsPlatform, static fn($c) => empty($c['image_url'] ?? '') || ($c['image_status'] ?? '') === 'failed');
$contentsNeedVideo = array_filter($contentsPlatform, static fn($c) => empty($c['video_url'] ?? '') || ($c['video_status'] ?? '') === 'failed');

$stats = [
    'total' => count($contentsPlatform),
    'draft' => count(array_filter($contentsPlatform, static fn($c) => ($c['status'] ?? '') === 'draft')),
    'approved' => count(array_filter($contentsPlatform, static fn($c) => ($c['status'] ?? '') === 'approved')),
    'needs_image' => count($contentsNeedImage),
    'needs_video' => count($contentsNeedVideo),
];
$productMap = [];
foreach ($products ?? [] as $p) {
    $productMap[(int)($p['id'] ?? 0)] = $p['product_name'] ?? '';
}
?>

<div class="page-header">
    <div>
        <div class="page-kicker">📘 <?= $platformLabel ?></div>
        <h2>Tạo Content <?= $platformLabel ?></h2>
        <p>Sinh content, ảnh, video cho <?= $platformLabel ?>. Duyệt & lên lịch sang trang Đăng bài.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-success" href="<?= url('/posts/facebook') ?>">Đăng bài</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card accent"><div class="label">Tổng</div><div class="value"><?= $stats['total'] ?></div></div>
    <div class="stat-card warning"><div class="label">Bản nháp</div><div class="value"><?= $stats['draft'] ?></div></div>
    <div class="stat-card success"><div class="label">Đã duyệt</div><div class="value"><?= $stats['approved'] ?></div></div>
    <div class="stat-card"><div class="label">Chờ ảnh</div><div class="value"><?= $stats['needs_image'] ?></div></div>
    <div class="stat-card"><div class="label">Chờ video</div><div class="value"><?= $stats['needs_video'] ?></div></div>
</div>

<div class="product-action-grid">
    <div class="card product-action-card">
        <div class="action-card-head">
            <div class="action-icon purple">✍️</div>
            <div>
                <div class="card-title">Sinh Content</div>
                <p class="section-note">Tạo bản nháp cho sản phẩm chưa có content <?= $platformLabel ?>.</p>
            </div>
        </div>
        <div class="mini-stats">
            <span><strong><?= count($productsNeedContent) ?></strong> chờ viết</span>
        </div>
        <form data-ajax method="POST" action="<?= url("/contents/generate-all-{$platform}") ?>">
            <input type="hidden" name="platform" value="<?= $platform ?>">
            <div class="form-group">
                <label class="form-label">Số sản phẩm</label>
                <input class="form-control" name="limit" type="number" min="1" max="50" value="10">
            </div>
            <button type="submit" class="btn btn-purple btn-full">Sinh Content</button>
        </form>
    </div>

    <div class="card product-action-card">
        <div class="action-card-head">
            <div class="action-icon success">🖼️</div>
            <div>
                <div class="card-title">Tạo Ảnh</div>
                <p class="section-note">Tạo ảnh AI cho content chưa có media.</p>
            </div>
        </div>
        <div class="mini-stats">
            <span><strong><?= $stats['needs_image'] ?></strong> chờ ảnh</span>
        </div>
        <form data-ajax method="POST" action="<?= url("/contents/generate-images-{$platform}") ?>">
            <input type="hidden" name="platform" value="<?= $platform ?>">
            <div class="form-group">
                <label class="form-label">Số content</label>
                <input class="form-control" name="limit" type="number" min="1" max="50" value="5">
            </div>
            <button type="submit" class="btn btn-success btn-full">Tạo Ảnh</button>
        </form>
    </div>

    <div class="card product-action-card">
        <div class="action-card-head">
            <div class="action-icon warning">🎬</div>
            <div>
                <div class="card-title">Tạo Video</div>
                <p class="section-note">Tạo video dọc cho <?= $platformLabel ?>.</p>
            </div>
        </div>
        <div class="mini-stats">
            <span><strong><?= $stats['needs_video'] ?></strong> chờ video</span>
        </div>
        <form data-ajax method="POST" action="<?= url("/contents/generate-videos-{$platform}") ?>">
            <input type="hidden" name="platform" value="<?= $platform ?>">
            <div class="form-group">
                <label class="form-label">Số content</label>
                <input class="form-control" name="limit" type="number" min="1" max="20" value="3">
            </div>
            <button type="submit" class="btn btn-accent btn-full">Tạo Video</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="section-heading">
        <div class="card-title">Danh sách Content <?= $platformLabel ?></div>
    </div>
    <?php if (empty($contentsPlatform)): ?>
        <div class="empty-state"><p>Chưa có content <?= $platformLabel ?>.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table-main table-compact">
                <thead><tr><th>Mã</th><th>Sản phẩm</th><th>Nội dung</th><th>Media</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                <tbody>
                <?php foreach ($contentsPlatform as $content): ?>
                    <tr>
                        <td data-label="Mã">#<?= (int)$content['id'] ?><div class="sub">SP #<?= (int)$content['product_id'] ?></div></td>
                        <td data-label="Sản phẩm"><span style="font-size:12px;word-break:break-word"><?= e($productMap[(int)($content['product_id'] ?? 0)] ?? '—') ?></span></td>
                        <td data-label="Nội dung">
                            <strong><?= e((string)$content['title']) ?></strong>
                            <div class="sub"><?= e((string)$content['hashtags']) ?></div>
                            <div class="mono mt-8" style="max-height:60px;overflow:hidden"><?= e(mb_substr((string)$content['body'], 0, 100)) ?>…</div>
                        </td>
                        <td data-label="Media">
                            <div class="media-gallery" style="gap:4px;flex-direction:column">
                                <?php if (!empty($content['image_url'] ?? '')): ?>
                                    <div>
                                        <img class="media-thumb clickable-media" src="<?= e((string)$content['image_url']) ?>" alt="" data-type="image" data-url="<?= e((string)$content['image_url']) ?>" style="height:48px;width:48px;object-fit:cover;border-radius:4px;border:1px solid var(--border)">
                                        <span style="font-size:10px;color:var(--text-muted)">🖼️ IMG</span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($content['video_url'] ?? '')): ?>
                                    <div>
                                        <video class="media-thumb clickable-media" src="<?= e((string)$content['video_url']) ?>" data-type="video" data-url="<?= e((string)$content['video_url']) ?>" style="height:48px;width:48px;object-fit:cover;border-radius:4px;border:1px solid var(--accent)" muted></video>
                                        <span style="font-size:10px;color:var(--text-muted)">🎬 VID</span>
                                    </div>
                                <?php endif; ?>
                                <?php if (empty($content['image_url']) && empty($content['video_url'])): ?>
                                    <span class="sub">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Trạng thái"><?= status_badge((string)$content['status']) ?></td>
                        <td data-label="Thao tác">
                            <div class="btn-group" style="display:flex;flex-direction:column;gap:4px;align-items:flex-start">
                                <div style="display:flex;gap:4px">
                                    <form data-ajax method="POST" action="<?= url("/contents/generate-text-{$platform}") ?>">
                                        <input type="hidden" name="content_id" value="<?= (int)$content['id'] ?>">
                                        <input type="hidden" name="platform" value="<?= $platform ?>">
                                        <button type="submit" class="btn btn-sm <?= !empty($content['body'] ?? '') ? 'btn-outline' : 'btn-primary' ?>">📝 Content</button>
                                    </form>
                                    <form data-ajax method="POST" action="<?= url("/contents/generate-image-{$platform}") ?>">
                                        <input type="hidden" name="content_id" value="<?= (int)$content['id'] ?>">
                                        <input type="hidden" name="platform" value="<?= $platform ?>">
                                        <button type="submit" class="btn btn-sm <?= !empty($content['image_url'] ?? '') ? 'btn-outline' : 'btn-purple' ?>">🖼️ Ảnh</button>
                                    </form>
                                    <form data-ajax method="POST" action="<?= url("/contents/generate-video-{$platform}") ?>">
                                        <input type="hidden" name="content_id" value="<?= (int)$content['id'] ?>">
                                        <input type="hidden" name="platform" value="<?= $platform ?>">
                                        <button type="submit" class="btn btn-sm <?= !empty($content['video_url'] ?? '') ? 'btn-outline' : 'btn-accent' ?>">🎬 Video</button>
                                    </form>
                                </div>
                                <?php if (($content['status'] ?? '') === 'draft'): ?>
                                    <form data-ajax method="POST" action="<?= url("/contents/approve-{$platform}") ?>">
                                        <input type="hidden" name="content_id" value="<?= (int)$content['id'] ?>">
                                        <input type="hidden" name="platform" value="<?= $platform ?>">
                                        <button type="submit" class="btn btn-success btn-sm">✓ Duyệt</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
.media-gallery{display:flex;gap:6px;flex-wrap:wrap}
.media-thumb{height:48px;width:48px;object-fit:cover;border-radius:6px;border:1px solid var(--border);cursor:pointer;transition:transform .2s}
.media-thumb:hover{transform:scale(1.05);border-color:var(--accent)}
.clickable-media{cursor:pointer}
/* Lightbox */
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