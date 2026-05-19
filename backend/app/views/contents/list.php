<?php
$contentStatuses = ['draft' => 'Bản nháp', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối', 'used' => 'Đã đăng'];
$platforms = ['facebook' => '📘 Facebook', 'tiktok' => '🎵 TikTok', 'instagram' => '📷 Instagram', 'threads' => '💬 Threads'];

// Build product name lookup
$productMap = [];
foreach ($products ?? [] as $p) {
    $productMap[(int)($p['id'] ?? 0)] = $p['product_name'] ?? '';
}

// Stats
$draftCount = (int)($contentSummary['draft'] ?? 0);
$approvedCount = (int)($contentSummary['approved'] ?? 0);
$rejectedCount = (int)($contentSummary['rejected'] ?? 0);
$totalCount = (int)($contentSummary['total'] ?? 0);
$mediaPending = count(array_filter($contents ?? [], fn($c) => ($c['media_status'] ?? '') === 'pending' && ($c['media_type'] ?? 'none') !== 'none'));

// Count by platform
$byPlatform = [];
foreach ($contents ?? [] as $c) {
    $p = $c['platform'] ?? 'general';
    if (!isset($byPlatform[$p])) $byPlatform[$p] = 0;
    $byPlatform[$p]++;
}
?>

<style>
.dashboard-header{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;margin-bottom:24px}
.dashboard-title{margin:0;font-size:22px;font-weight:700}
.dashboard-sub{margin:4px 0 0;color:var(--text-muted);font-size:13px}
.dashboard-actions{display:flex;gap:8px;flex-wrap:wrap}
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px}
.stat-box{border:1px solid var(--border);border-radius:12px;padding:16px;background:var(--bg-card)}
.stat-box .stat-label{font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.stat-box .stat-value{font-size:28px;font-weight:700}
.stat-box .stat-breakdown{font-size:11px;color:var(--text-muted);margin-top:2px}
.stat-draft .stat-value{color:var(--warning)}
.stat-approved .stat-value{color:var(--success)}
.stat-rejected .stat-value{color:var(--danger)}
.stat-media .stat-value{color:var(--accent)}
.platform-row{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px}
.platform-badge{display:flex;align-items:center;gap:6px;padding:6px 14px;border:1px solid var(--border);border-radius:20px;font-size:12px;font-weight:600;background:var(--bg-card)}
.platform-badge .count{background:var(--accent);color:#fff;border-radius:10px;padding:1px 7px;font-size:11px}
.card-section{border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:16px;background:var(--bg-card)}
.card-section-title{font-size:14px;font-weight:700;margin:0 0 16px;color:var(--text)}
.filter-bar{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
.filter-bar select,#filter-status,#filter-platform,#filter-search,.filter-bar input{padding:7px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text);font-size:13px}
.table-main{width:100%;border-collapse:collapse}
.table-main th{text-align:left;padding:8px 12px;font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border)}
.table-main td{padding:10px 12px;border-bottom:1px solid var(--border);vertical-align:middle}
.table-main td:nth-child(3){max-width:300px;word-break:break-word;overflow-wrap:break-word}
.table-main td:nth-child(3) strong{word-break:break-word;overflow-wrap:break-word}
.table-main td:nth-child(3) .sub{overflow:hidden;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical}
@media(max-width:768px){
.table-main{font-size:12px}
.table-main th:nth-child(6),.table-main td:nth-child(6){display:none}
.table-main td:nth-child(2){max-width:120px}
.table-main td:nth-child(3){max-width:160px}
.btn-group button{padding:4px 8px;font-size:11px}
}
</style>

<div class="page-header">
    <div>
        <div class="page-kicker">AI Content Studio</div>
        <h2>Dashboard Tạo Content</h2>
        <p>Tổng quan trạng thái content — sinh mới, duyệt, tạo ảnh/video.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-outline" href="<?= url('/contents') ?>">← Tạo Content</a>
        <a class="btn btn-success" href="<?= url('/posts/facebook') ?>">📤 Đăng bài</a>
    </div>
</div>

<!-- Stats -->
<div class="stats-row">
    <div class="stat-box stat-draft">
        <div class="stat-label">Bản nháp</div>
        <div class="stat-value"><?= $draftCount ?></div>
        <div class="stat-breakdown">Chờ duyệt</div>
    </div>
    <div class="stat-box stat-approved">
        <div class="stat-label">Đã duyệt</div>
        <div class="stat-value"><?= $approvedCount ?></div>
        <div class="stat-breakdown">Sẵn sàng đăng</div>
    </div>
    <div class="stat-box stat-rejected">
        <div class="stat-label">Từ chối</div>
        <div class="stat-value"><?= $rejectedCount ?></div>
        <div class="stat-breakdown">Cần sửa lại</div>
    </div>
    <div class="stat-box">
        <div class="stat-label">Tổng content</div>
        <div class="stat-value"><?= $totalCount ?></div>
        <div class="stat-breakdown">Tất cả platforms</div>
    </div>
    <div class="stat-box stat-media">
        <div class="stat-label">Chờ media</div>
        <div class="stat-value"><?= $mediaPending ?></div>
        <div class="stat-breakdown">ảnh/video chưa tạo</div>
    </div>
</div>

<!-- Platform breakdown -->
<div class="platform-row">
    <?php foreach ($platforms as $key => $label): ?>
        <?php $count = $byPlatform[$key] ?? 0; ?>
        <div class="platform-badge">
            <span><?= $label ?></span>
            <span class="count"><?= $count ?></span>
        </div>
    <?php endforeach; ?>
</div>

<!-- Quick actions -->
<div class="card-section">
    <div class="card-section-title">⚡ Thao tác nhanh</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <form data-ajax method="POST" action="<?= url('/contents/generate-all-facebook') ?>">
            <button type="submit" class="btn btn-accent">🎵 Sinh Content Facebook</button>
        </form>
        <form data-ajax method="POST" action="<?= url('/contents/generate-all-tiktok') ?>">
            <button type="submit" class="btn btn-accent">📘 Sinh Content TikTok</button>
        </form>
        <form data-ajax method="POST" action="<?= url('/contents/generate-all-instagram') ?>">
            <button type="submit" class="btn btn-accent">📷 Sinh Content Instagram</button>
        </form>
        <form data-ajax method="POST" action="<?= url('/contents/generate-all-threads') ?>">
            <button type="submit" class="btn btn-accent">💬 Sinh Content Threads</button>
        </form>
        <form data-ajax method="POST" action="<?= url('/contents/generate-images') ?>">
            <button type="submit" class="btn btn-purple">🖼️ Tạo ảnh hàng loạt</button>
        </form>
        <form data-ajax method="POST" action="<?= url('/contents/generate-videos') ?>">
            <button type="submit" class="btn btn-purple">🎬 Tạo video hàng loạt</button>
        </form>
    </div>
</div>

<!-- Content list with filters -->
<div class="card-section">
    <div class="card-section-title">📋 Danh sách Content</div>

    <div class="filter-bar">
        <select id="filter-status" onchange="applyFilters()">
            <option value="">Tất cả trạng thái</option>
            <?php foreach ($contentStatuses as $key => $label): ?>
                <option value="<?= e($key) ?>"><?= $label ?></option>
            <?php endforeach; ?>
        </select>
        <select id="filter-platform" onchange="applyFilters()">
            <option value="">Tất cả platform</option>
            <?php foreach ($platforms as $key => $label): ?>
                <option value="<?= e($key) ?>"><?= $label ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" id="filter-search" placeholder="Tìm tiêu đề..." onkeyup="applyFilters()">
        <span class="sub" style="align-self:center" id="row-count"></span>
    </div>

    <div class="table-wrap">
        <table class="table-main" id="content-table">
            <thead><tr>
                <th>Mã</th>
                <th>Sản phẩm</th>
                <th>Nội dung</th>
                <th>Platform</th>
                <th>Media</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
            </tr></thead>
            <tbody id="content-tbody">
                <?php foreach ($contents as $content): ?>
                    <tr data-status="<?= e($content['status'] ?? '') ?>" data-platform="<?= e($content['platform'] ?? '') ?>">
                        <td>#<?= (int)$content['id'] ?><div class="sub">SP <?= (int)$content['product_id'] ?></div></td>
                        <td style="max-width:160px">
                            <span style="word-break:break-word"><?= e($productMap[(int)($content['product_id'] ?? 0)] ?? '—') ?></span>
                        </td>
                        <td style="max-width:300px;cursor:pointer" class="content-modal-trigger" data-id="<?= (int)$content['id'] ?>" data-title="<?= e((string)$content['title']) ?>" data-body="<?= e((string)($content['body'] ?? '')) ?>" data-hashtags="<?= e((string)($content['hashtags'] ?? '')) ?>" data-platform="<?= e((string)($content['platform'] ?? '')) ?>" data-product="<?= e($productMap[(int)($content['product_id'] ?? 0)] ?? '') ?>" data-image="<?= e($content['image_url'] ?? '') ?>" data-video="<?= e($content['video_url'] ?? '') ?>">
                            <strong style="word-break:break-word"><?= e((string)$content['title']) ?></strong>
                            <div class="sub" style="margin-top:4px;word-break:break-word;overflow:hidden;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical"><?= e(mb_substr((string)($content['body'] ?? ''), 0, 500)) ?></div>
                            <div class="sub" style="margin-top:2px"><?= e((string)$content['hashtags']) ?></div>
                        </td>
                        <td>
                            <?php
                            $p = $content['platform'] ?? 'general';
                            $icon = match($p){
                                'facebook' => '📘',
                                'tiktok' => '🎵',
                                'instagram' => '📷',
                                'threads' => '💬',
                                default => '🌐'
                            };
                            ?>
                            <span class="platform-badge" style="font-size:11px;padding:3px 10px"><?= $icon ?> <?= ucfirst($p) ?></span>
                        </td>
                        <td>
                            <?php if (!empty($content['image_url'])): ?>
                                <div class="media-gallery">
                                    <div class="media-item">
                                        <img class="media-thumb clickable-media" src="<?= e($content['image_url']) ?>" alt="" data-type="image" data-url="<?= e($content['image_url']) ?>">
                                        <span class="media-label">🖼️ IMG</span>
                                    </div>
                                    <?php if (!empty($content['video_url'])): ?>
                                        <div class="media-item">
                                            <video class="media-thumb clickable-media" src="<?= e($content['video_url']) ?>" data-type="video" data-url="<?= e($content['video_url']) ?>" muted></video>
                                            <span class="media-label">🎬 VID</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php elseif (!empty($content['video_url'])): ?>
                                <div class="media-gallery">
                                    <div class="media-item">
                                        <video class="media-thumb clickable-media" src="<?= e($content['video_url']) ?>" data-type="video" data-url="<?= e($content['video_url']) ?>" muted></video>
                                        <span class="media-label">🎬 VID</span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span class="sub">— Chưa tạo</span>
                            <?php endif; ?>
                        </td>
                        <td><?= status_badge((string)($content['status'] ?? '')) ?></td>
                        <td>
                            <div class="btn-group" style="display:flex;flex-direction:column;gap:4px;align-items:flex-start">
                                <div style="display:flex;gap:4px">
                                    <?php if (!empty($content['body'] ?? '')): ?>
                                        <form data-ajax method="POST" action="<?= url('/contents/regenerate-text') ?>">
                                            <input type="hidden" name="content_id" value="<?= (int)$content['id'] ?>">
                                            <input type="hidden" name="platform" value="<?= $content['platform'] ?? 'facebook' ?>">
                                            <button type="submit" class="btn btn-sm btn-primary">📝 Content</button>
                                        </form>
                                    <?php endif; ?>
                                    <form data-ajax method="POST" action="<?= url('/contents/generate-image') ?>">
                                        <input type="hidden" name="content_id" value="<?= (int)$content['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-purple">🖼️ Ảnh</button>
                                    </form>
                                    <form data-ajax method="POST" action="<?= url('/contents/generate-video') ?>">
                                        <input type="hidden" name="content_id" value="<?= (int)$content['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-accent">🎬 Video</button>
                                    </form>
                                </div>
                                <?php if (($content['status'] ?? '') === 'draft'): ?>
                                    <form data-ajax method="POST" action="<?= url('/contents/approve') ?>">
                                        <input type="hidden" name="content_id" value="<?= (int)$content['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm">✓ Duyệt</button>
                                    </form>
                                <?php elseif (($content['status'] ?? '') === 'approved'): ?>
                                    <form data-ajax method="POST" action="<?= url('/contents/reject') ?>">
                                        <input type="hidden" name="content_id" value="<?= (int)$content['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">✕</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function applyFilters() {
    const status = document.getElementById('filter-status').value;
    const platform = document.getElementById('filter-platform').value;
    const search = document.getElementById('filter-search').value.toLowerCase();
    const rows = document.querySelectorAll('#content-tbody tr');
    let visible = 0;

    rows.forEach(row => {
        const matchStatus = !status || row.dataset.status === status;
        const matchPlatform = !platform || row.dataset.platform === platform;
        const text = row.textContent.toLowerCase();
        const matchSearch = !search || text.includes(search);
        row.style.display = (matchStatus && matchPlatform && matchSearch) ? '' : 'none';
        if (matchStatus && matchPlatform && matchSearch) visible++;
    });

    document.getElementById('row-count').textContent = visible + ' / ' + rows.length + ' content';
}

applyFilters();
</script>

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
/* Content Modal */
.content-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9998;align-items:center;justify-content:center}
.content-modal-overlay.active{display:flex}
.content-modal{background:var(--bg-card);border-radius:16px;width:min(680px,95vw);max-height:90vh;overflow-y:auto;position:relative}
.content-modal .cm-header{padding:20px 24px 12px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
.content-modal .cm-header .cm-title{font-size:16px;font-weight:700;margin:0}
.content-modal .cm-header .cm-platform{display:flex;gap:6px;margin-top:6px}
.content-modal .cm-close{background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-muted);padding:4px;line-height:1}
.content-modal .cm-close:hover{color:var(--text)}
.content-modal .cm-body{padding:20px 24px}
.content-modal .cm-section{margin-bottom:16px}
.content-modal .cm-section-label{font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text-muted);letter-spacing:.5px;margin-bottom:6px}
.content-modal .cm-section-text{font-size:14px;line-height:1.7;word-break:break-word;white-space:pre-wrap;color:#e2e8f0;background:#1e293b;padding:12px 14px;border-radius:8px;border:1px solid #334155}
.content-modal .cm-hashtags{font-size:13px;color:#67e8f9;word-break:break-word}
.content-modal .cm-product{font-size:13px;color:#94a3b8}

</style>

<!-- Lightbox Modal -->
<div class="lightbox-overlay" id="lightbox" onclick="if(event.target.id==='lightbox')closeLightbox()">
    <button class="lb-close" onclick="closeLightbox()">✕</button>
    <div id="lb-content"></div>
    <div class="lb-type" id="lb-type"></div>
</div>

<!-- Content Modal -->
<div class="content-modal-overlay" id="contentModal" onclick="if(event.target.id==='contentModal')closeContentModal()">
    <div class="content-modal">
        <div class="cm-header">
            <div>
                <div class="cm-title" id="cm-title"></div>
                <div class="cm-platform" id="cm-platform"></div>
            </div>
            <button class="cm-close" onclick="closeContentModal()">✕</button>
        </div>
        <div class="cm-body">
            <div class="cm-section">
                <div class="cm-section-label">Sản phẩm</div>
                <div class="cm-product" id="cm-product"></div>
            </div>
            <div class="cm-section">
                <div class="cm-section-label">Nội dung</div>
                <div class="cm-section-text" id="cm-body"></div>
            </div>
            <div class="cm-section">
                <div class="cm-section-label">Hashtags</div>
                <div class="cm-hashtags" id="cm-hashtags"></div>
            </div>
            <div class="cm-section" id="cm-media-section" style="display:none">
                <div class="cm-section-label">Media</div>
                <div id="cm-media"></div>
            </div>
        </div>
    </div>
</div>

<script>
function openContentModal(id, title, body, hashtags, platform, product, image, video) {
    const modal = document.getElementById('contentModal');
    document.getElementById('cm-title').textContent = title;
    document.getElementById('cm-body').textContent = body || '—';
    document.getElementById('cm-hashtags').textContent = hashtags || '—';
    document.getElementById('cm-product').textContent = product ? 'SP: ' + product : '';
    const platformEl = document.getElementById('cm-platform');
    const icons = {facebook:'📘',tiktok:'🎵',instagram:'📷',threads:'💬'};
    platformEl.innerHTML = icons[platform] ? '<span style="font-size:13px">'+icons[platform]+' '+platform.charAt(0).toUpperCase()+platform.slice(1)+'</span>' : '';
    // Media section
    const mediaEl = document.getElementById('cm-media');
    if (image || video) {
        let html = '';
        if (image) html += '<img src="'+image+'" alt="" style="max-width:100%;border-radius:8px;margin-top:8px;cursor:zoom-in" onclick="window.open(\''+image+'\',\'_blank\')">';
        if (video) html += '<video src="'+video+'" controls style="max-width:100%;border-radius:8px;margin-top:8px"></video>';
        mediaEl.innerHTML = html;
        mediaEl.style.display = 'block';
    } else {
        mediaEl.style.display = 'none';
    }
    modal.classList.add('active');
}

function closeContentModal() {
    document.getElementById('contentModal').classList.remove('active');
}

document.addEventListener('click', function(e) {
    const trigger = e.target.closest('.content-modal-trigger');
    if (trigger) {
        openContentModal(
            trigger.dataset.id,
            trigger.dataset.title,
            trigger.dataset.body,
            trigger.dataset.hashtags,
            trigger.dataset.platform,
            trigger.dataset.product,
            trigger.dataset.image,
            trigger.dataset.video
        );
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeContentModal();
});
</script>
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
    const content = document.getElementById('lb-content');
    content.innerHTML = '';
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