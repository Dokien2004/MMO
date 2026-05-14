<?php
/**
 * My Products — User-curated product selection.
 * Users pick products from AI radar, add affiliate links, then trigger content generation.
 *
 * Variables: $products (list data), $summary, $filters
 */
$items      = $products['data'] ?? [];
$total      = (int)($products['total'] ?? 0);
$summary    = $summary ?? [];
$filters    = $filters ?? [];

$statusLabels = [
    'pending'           => ['label' => 'Chờ xử lý', 'class' => 'badge-draft'],
    'active'            => ['label' => 'Đang hoạt động', 'class' => 'badge-active'],
    'content_generated' => ['label' => 'Có nội dung', 'class' => 'badge-content-ready'],
    'published'         => ['label' => 'Đã đăng', 'class' => 'badge-posted'],
    'paused'            => ['label' => 'Tạm dừng', 'class' => 'badge-archived'],
    'archived'          => ['label' => 'Lưu trữ', 'class' => 'badge-archived'],
];
?>

<div class="hero-card">
    <div class="hero-row">
        <div>
            <div class="page-kicker">🎯 Sản phẩm đã chọn</div>
            <h2 class="hero-title">My Products</h2>
            <p class="hero-subtitle">Sản phẩm bạn đã chọn từ AI Radar hoặc thêm thủ công. Thêm link affiliate, sau đó sinh content tự động.</p>
        </div>
        <div class="hero-actions">
            <button class="btn btn-primary" onclick="openAddModal()">
                <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Thêm sản phẩm
            </button>
            <a class="btn btn-accent" href="<?= url('/analytics') ?>">📊 AI Radar</a>
            <a class="btn btn-purple" href="<?= url('/scraper') ?>">🔍 Product Radar</a>
        </div>
    </div>
</div>

<!-- Summary Stats -->
<div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card accent">
        <div class="label">Tổng SP đã chọn</div>
        <div class="value"><?= (int)($summary['total'] ?? 0) ?></div>
    </div>
    <div class="stat-card warning">
        <div class="label">Chờ xử lý</div>
        <div class="value"><?= (int)($summary['pending'] ?? 0) ?></div>
    </div>
    <div class="stat-card success">
        <div class="label">Đang hoạt động</div>
        <div class="value"><?= (int)($summary['active'] ?? 0) ?></div>
    </div>
    <div class="stat-card purple">
        <div class="label">Có link Aff</div>
        <div class="value"><?= (int)($summary['has_affiliate'] ?? 0) ?></div>
    </div>
</div>

<!-- Filter Bar -->
<div class="card" style="padding:14px 18px;margin-bottom:16px;">
    <form method="GET" action="<?= url('/my-products') ?>" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <input type="text" class="form-control" name="search" placeholder="🔍 Tìm tên sản phẩm..."
               value="<?= e($filters['search'] ?? '') ?>" style="flex:1;min-width:180px;max-width:360px;">
        <select class="form-control" name="status" style="width:auto;min-width:140px;">
            <option value="">Tất cả trạng thái</option>
            <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Chờ xử lý</option>
            <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Đang hoạt động</option>
            <option value="content_generated" <?= ($filters['status'] ?? '') === 'content_generated' ? 'selected' : '' ?>>Có nội dung</option>
            <option value="published" <?= ($filters['status'] ?? '') === 'published' ? 'selected' : '' ?>>Đã đăng</option>
        </select>
        <select class="form-control" name="platform" style="width:auto;min-width:130px;">
            <option value="">Tất cả nguồn</option>
            <option value="shopee" <?= ($filters['platform'] ?? '') === 'shopee' ? 'selected' : '' ?>>Shopee</option>
            <option value="lazada" <?= ($filters['platform'] ?? '') === 'lazada' ? 'selected' : '' ?>>Lazada</option>
            <option value="tiktok_shop" <?= ($filters['platform'] ?? '') === 'tiktok_shop' ? 'selected' : '' ?>>TikTok Shop</option>
            <option value="manual" <?= ($filters['platform'] ?? '') === 'manual' ? 'selected' : '' ?>>Nhập tay</option>
        </select>
        <button type="submit" class="btn btn-accent">Lọc</button>
        <?php if (!empty($filters['search']) || !empty($filters['status']) || !empty($filters['platform'])): ?>
            <a href="<?= url('/my-products') ?>" class="btn btn-ghost" style="font-size:12px;">✕ Xóa lọc</a>
        <?php endif; ?>
    </form>
</div>

<!-- Products Table -->
<div class="card">
    <div class="section-heading" style="margin-bottom:12px;">
        <div class="card-title">
            <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:var(--accent);fill:none;stroke-width:2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            Danh sách sản phẩm (<?= $total ?>)
        </div>
    </div>

    <?php if (empty($items)): ?>
        <div class="empty-state" style="padding:40px 20px;text-align:center;">
            <p style="font-size:40px;margin-bottom:12px;">🎯</p>
            <p style="font-weight:600;margin-bottom:8px;">Chưa có sản phẩm nào được chọn</p>
            <p class="text-muted" style="margin-bottom:16px;">Vào <strong>AI Radar</strong> để xem gợi ý sản phẩm tiềm năng, hoặc thêm thủ công bên dưới.</p>
            <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                <a class="btn btn-primary" href="<?= url('/analytics') ?>">📊 AI Radar</a>
                <button class="btn btn-accent" onclick="openAddModal()">+ Thêm thủ công</button>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table style="min-width:900px;">
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th style="width:80px;">Nguồn</th>
                        <th style="width:90px;">Giá</th>
                        <th style="width:70px;">AI Score</th>
                        <th style="width:100px;">Trạng thái</th>
                        <th style="width:90px;">Link Aff</th>
                        <th style="width:120px;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <?php
                    $id = (int)$item['id'];
                    $status = $statusLabels[$item['status'] ?? 'pending'] ?? $statusLabels['pending'];
                    $hasAff = !empty($item['affiliate_url']);
                    $score = (float)($item['overall_score'] ?? $item['ai_score'] ?? 0);
                    $scoreColor = $score >= 80 ? '#10b981' : ($score >= 60 ? '#4f46e5' : ($score >= 35 ? '#f59e0b' : '#94a3b8'));
                    $recBadge = match($item['recommendation'] ?? '') {
                        'strong_buy' => '<span class="badge" style="background:#10b981;color:#fff;font-size:10px;">Strong Buy</span>',
                        'buy' => '<span class="badge" style="background:#4f46e5;color:#fff;font-size:10px;">Buy</span>',
                        'hold' => '<span class="badge" style="background:#f59e0b;color:#000;font-size:10px;">Hold</span>',
                        'avoid' => '<span class="badge" style="background:#ef4444;color:#fff;font-size:10px;">Avoid</span>',
                        default => '',
                    };
                    ?>
                    <tr>
                        <td>
                            <strong style="font-size:13px;"><?= e((string)$item['product_name']) ?></strong>
                            <?php if (!empty($item['notes'])): ?>
                                <div class="sub" style="font-size:11px;color:var(--text-muted);margin-top:2px;">📝 <?= e(mb_substr((string)$item['notes'], 0, 60)) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($item['product_url'])): ?>
                                <a href="<?= e((string)$item['product_url']) ?>" target="_blank" rel="noreferrer" class="text-xs" style="color:var(--accent);display:inline-block;margin-top:2px;">Xem SP ↗</a>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?= e((string)($item['source_platform'] ?? 'shopee')) ?>" style="font-size:11px;"><?= e((string)($item['source_platform'] ?? 'shopee')) ?></span></td>
                        <td class="text-sm"><?= (float)($item['price'] ?? 0) > 0 ? number_format((float)$item['price'], 0, ',', '.') . ' ₫' : '—' ?></td>
                        <td>
                            <?php if ($score > 0): ?>
                                <span style="font-weight:700;color:<?= $scoreColor ?>;font-size:15px;"><?= round($score, 1) ?></span>
                                <?= $recBadge ?>
                            <?php else: ?>
                                <span class="text-muted" style="font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= $status['class'] ?>"><?= $status['label'] ?></span></td>
                        <td>
                            <?php if ($hasAff): ?>
                                <span class="badge badge-active" style="font-size:11px;">✓ Có link</span>
                            <?php else: ?>
                                <button class="btn btn-sm btn-accent" onclick="openEditModal(<?= $id ?>)" style="font-size:11px;padding:4px 10px;">+ Thêm link</button>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <button class="btn btn-sm btn-ghost" onclick="openEditModal(<?= $id ?>)" title="Sửa" style="padding:4px 8px;">✏️</button>
                                <?php if ($hasAff && in_array($item['status'], ['active', 'pending'])): ?>
                                    <button class="btn btn-sm btn-success" onclick="generateContent(<?= $id ?>)" title="Sinh content" style="padding:4px 8px;">✨</button>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-ghost" onclick="archiveProduct(<?= $id ?>, '<?= e(addslashes($item['product_name'])) ?>')" title="Lưu trữ" style="padding:4px 8px;color:var(--danger);">🗑</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Add Product Modal -->
<div class="modal-overlay" id="addModal" style="display:none;">
    <div class="modal-box" style="max-width:560px;">
        <h3>Thêm sản phẩm thủ công</h3>
        <p class="sub" style="margin-bottom:16px;">Nhập thông tin sản phẩm bạn muốn quảng bá.</p>
        <form id="addForm" onsubmit="submitAddForm(event)">
            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label">Tên sản phẩm *</label>
                <input class="form-control" name="product_name" required placeholder="VD: Áo thun nam cotton...">
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label">Link sản phẩm gốc</label>
                <input class="form-control" name="product_url" placeholder="https://shopee.vn/...">
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label">Link Affiliate</label>
                <input class="form-control" name="affiliate_url" placeholder="https://s.shopee.vn/...">
            </div>
            <div class="grid-3" style="margin-bottom:12px;">
                <div class="form-group">
                    <label class="form-label">Nguồn</label>
                    <select class="form-control" name="source_platform">
                        <option value="shopee">Shopee</option>
                        <option value="lazada">Lazada</option>
                        <option value="tiktok_shop">TikTok Shop</option>
                        <option value="manual">Nhập tay</option>
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label">Ghi chú</label>
                <textarea class="form-control" name="notes" rows="2" placeholder="Thông tin thêm..."></textarea>
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary" style="flex:1;">Thêm sản phẩm</button>
                <button type="button" class="btn btn-cancel" onclick="closeModal('addModal')">Hủy</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal-overlay" id="editModal" style="display:none;">
    <div class="modal-box" style="max-width:560px;">
        <h3>Cập nhật sản phẩm</h3>
        <p class="sub" style="margin-bottom:16px;">Thêm link affiliate và cập nhật thông tin.</p>
        <form id="editForm" onsubmit="submitEditForm(event)">
            <input type="hidden" name="edit_id" id="edit_id">
            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label">Tên sản phẩm *</label>
                <input class="form-control" name="product_name" id="edit_product_name" required>
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label">Link Affiliate ✨</label>
                <input class="form-control" name="affiliate_url" id="edit_affiliate_url" placeholder="Dán link affiliate tại đây..." style="border-color:var(--accent);">
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label">Link gốc</label>
                <input class="form-control" name="product_url" id="edit_product_url">
            </div>
            <div class="grid-3" style="margin-bottom:12px;">
                <div class="form-group">
                    <label class="form-label">Trạng thái</label>
                    <select class="form-control" name="status" id="edit_status">
                        <option value="pending">Chờ xử lý</option>
                        <option value="active">Đang hoạt động</option>
                        <option value="paused">Tạm dừng</option>
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label">Ghi chú</label>
                <textarea class="form-control" name="notes" id="edit_notes" rows="2"></textarea>
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary" style="flex:1;">Cập nhật</button>
                <button type="button" class="btn btn-cancel" onclick="closeModal('editModal')">Hủy</button>
            </div>
        </form>
    </div>
</div>

<!-- Confirm Archive Modal -->
<div class="modal-overlay" id="archiveModal" style="display:none;">
    <div class="modal-box" style="max-width:420px;">
        <h3>Lưu trữ sản phẩm?</h3>
        <p style="margin:12px 0;">Sản phẩm <strong id="archiveProductName"></strong> sẽ được chuyển vào lưu trữ.</p>
        <div class="modal-actions">
            <button class="btn btn-primary" style="background:var(--danger);flex:1;" onclick="confirmArchive()">Lưu trữ</button>
            <button class="btn btn-cancel" onclick="closeModal('archiveModal')">Hủy</button>
        </div>
    </div>
</div>

<script>
const BASE = '<?= url('') ?>';
const CSRF = '<?= e(csrf_token()) ?>';
let archiveTargetId = null;

function openAddModal() {
    document.getElementById('addForm').reset();
    document.getElementById('addModal').style.display = 'flex';
}

function openEditModal(id) {
    // Fetch product data
    fetch(`${BASE}/api/my-products/${id}`, { headers: { 'X-CSRF-Token': CSRF } })
        .then(r => r.json())
        .then(res => {
            if (!res.success) { alert(res.message); return; }
            const p = res.data;
            document.getElementById('edit_id').value = p.id;
            document.getElementById('edit_product_name').value = p.product_name || '';
            document.getElementById('edit_product_url').value = p.product_url || '';
            document.getElementById('edit_affiliate_url').value = p.affiliate_url || '';
            document.getElementById('edit_status').value = p.status || 'pending';
            document.getElementById('edit_notes').value = p.notes || '';
            document.getElementById('editModal').style.display = 'flex';
        })
        .catch(err => alert('Lỗi: ' + err.message));
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
});

function submitAddForm(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append('csrf_token', CSRF);
    fetch(`${BASE}/my-products/add`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (typeof toastr !== 'undefined') toastr.success(res.message);
                else alert(res.message);
                setTimeout(() => location.reload(), 800);
            } else {
                if (typeof toastr !== 'undefined') toastr.error(res.message);
                else alert(res.message);
            }
        })
        .catch(err => alert('Lỗi: ' + err.message));
}

function submitEditForm(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append('csrf_token', CSRF);
    const id = fd.get('edit_id');
    fd.delete('edit_id');
    fetch(`${BASE}/my-products/update/${id}`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (typeof toastr !== 'undefined') toastr.success(res.message);
                else alert(res.message);
                setTimeout(() => location.reload(), 800);
            } else {
                if (typeof toastr !== 'undefined') toastr.error(res.message);
                else alert(res.message);
            }
        })
        .catch(err => alert('Lỗi: ' + err.message));
}

function archiveProduct(id, name) {
    archiveTargetId = id;
    document.getElementById('archiveProductName').textContent = name;
    document.getElementById('archiveModal').style.display = 'flex';
}

function confirmArchive() {
    if (!archiveTargetId) return;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fetch(`${BASE}/my-products/archive/${archiveTargetId}`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            closeModal('archiveModal');
            if (res.success) {
                if (typeof toastr !== 'undefined') toastr.success(res.message);
                else alert(res.message);
                setTimeout(() => location.reload(), 800);
            } else {
                if (typeof toastr !== 'undefined') toastr.error(res.message);
                else alert(res.message);
            }
        })
        .catch(err => alert('Lỗi: ' + err.message));
}

function generateContent(id) {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('product_id', id);
    fetch(`${BASE}/my-products/generate-content`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (typeof toastr !== 'undefined') toastr.success(res.message);
                else alert(res.message);
                setTimeout(() => location.reload(), 1200);
            } else {
                if (typeof toastr !== 'undefined') toastr.error(res.message);
                else alert(res.message);
            }
        })
        .catch(err => alert('Lỗi: ' + err.message));
}
</script>
