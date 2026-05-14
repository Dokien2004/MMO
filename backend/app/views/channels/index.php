<?php
/**
 * Social Channels — Multi-channel publishing management.
 * Manage Facebook Pages, Facebook Groups, TikTok accounts.
 *
 * Variables: $channels, $summary
 */
$channels = $channels ?? [];
$summary = $summary ?? [];

$typeLabels = [
    'facebook_page'  => ['label' => 'Facebook Page', 'icon' => '📘', 'badge' => 'badge-active'],
    'facebook_group' => ['label' => 'Facebook Group', 'icon' => '👥', 'badge' => 'badge-draft'],
    'tiktok'         => ['label' => 'TikTok', 'icon' => '🎵', 'badge' => 'badge-content-ready'],
    'instagram'      => ['label' => 'Instagram', 'icon' => '📸', 'badge' => 'badge-posted'],
];

$statusLabels = [
    'active' => ['label' => 'Hoạt động', 'class' => 'badge-active'],
    'paused' => ['label' => 'Tạm dừng', 'class' => 'badge-archived'],
    'error'  => ['label' => 'Lỗi', 'class' => 'badge-draft'],
];
?>

<div class="hero-card">
    <div class="hero-row">
        <div>
            <div class="page-kicker">📡 Kênh đăng bài</div>
            <h2 class="hero-title">Social Channels</h2>
            <p class="hero-subtitle">Quản lý các kênh Facebook Page, Facebook Group, TikTok để đăng bài tự động.</p>
        </div>
        <div class="hero-actions">
            <button class="btn btn-primary" onclick="openAddChannel()">
                <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Thêm kênh
            </button>
        </div>
    </div>
</div>

<!-- Summary Stats -->
<div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card accent">
        <div class="label">Tổng kênh</div>
        <div class="value"><?= (int)($summary['total'] ?? 0) ?></div>
    </div>
    <div class="stat-card success">
        <div class="label">Đang hoạt động</div>
        <div class="value"><?= (int)($summary['active'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">📘 FB Page</div>
        <div class="value"><?= (int)($summary['facebook_page'] ?? 0) ?></div>
    </div>
    <div class="stat-card purple">
        <div class="label">🎵 TikTok</div>
        <div class="value"><?= (int)($summary['tiktok'] ?? 0) ?></div>
    </div>
</div>

<!-- Info Card -->
<div class="card" style="padding:16px;margin-bottom:16px;border-left:3px solid var(--accent);">
    <div style="display:flex;gap:12px;align-items:flex-start;">
        <span style="font-size:20px;">💡</span>
        <div>
            <strong>Cách hoạt động</strong>
            <ul style="margin:8px 0 0;padding-left:20px;font-size:13px;color:var(--text-sec);line-height:1.8;">
                <li><strong>Facebook Page:</strong> Sử dụng Graph API — cần Page Access Token (lấy từ Facebook Developer)</li>
                <li><strong>Facebook Group:</strong> Sử dụng browser automation — cần cookie session (đăng nhập FB rồi export cookie)</li>
                <li><strong>TikTok:</strong> Sử dụng browser automation — cần cookie session hoặc TikTok Content Posting API</li>
                <li>Giới hạn: mỗi kênh có <strong>giới hạn bài/ngày</strong> để tránh bị ban</li>
            </ul>
        </div>
    </div>
</div>

<!-- Channels List -->
<div class="card">
    <div class="section-heading" style="margin-bottom:12px;">
        <div class="card-title">
            <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:var(--accent);fill:none;stroke-width:2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            Danh sách kênh (<?= count($channels) ?>)
        </div>
    </div>

    <?php if (empty($channels)): ?>
        <div class="empty-state" style="padding:40px 20px;text-align:center;">
            <p style="font-size:40px;margin-bottom:12px;">📡</p>
            <p style="font-weight:600;margin-bottom:8px;">Chưa có kênh nào</p>
            <p class="text-muted" style="margin-bottom:16px;">Thêm kênh Facebook hoặc TikTok để bắt đầu đăng bài tự động.</p>
            <button class="btn btn-primary" onclick="openAddChannel()">+ Thêm kênh đầu tiên</button>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table style="min-width:800px;">
                <thead>
                    <tr>
                        <th>Kênh</th>
                        <th style="width:120px;">Loại</th>
                        <th style="width:100px;">Trạng thái</th>
                        <th style="width:120px;">Đã đăng hôm nay</th>
                        <th style="width:120px;">Lần đăng cuối</th>
                        <th style="width:100px;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($channels as $ch): ?>
                    <?php
                    $type = $typeLabels[$ch['channel_type'] ?? 'facebook_page'] ?? $typeLabels['facebook_page'];
                    $status = $statusLabels[$ch['status'] ?? 'active'] ?? $statusLabels['active'];
                    $postsToday = (int)($ch['posts_today'] ?? 0);
                    $limit = (int)($ch['daily_post_limit'] ?? 5);
                    $pct = $limit > 0 ? min(100, round($postsToday / $limit * 100)) : 0;
                    ?>
                    <tr>
                        <td>
                            <strong style="font-size:13px;"><?= $type['icon'] ?> <?= e((string)$ch['channel_name']) ?></strong>
                            <?php if (!empty($ch['channel_id'])): ?>
                                <div class="sub" style="font-size:11px;">ID: <?= e((string)$ch['channel_id']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= $type['badge'] ?>" style="font-size:11px;"><?= $type['label'] ?></span></td>
                        <td><span class="badge <?= $status['class'] ?>"><?= $status['label'] ?></span></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="flex:1;height:6px;background:var(--bg-elevated);border-radius:3px;overflow:hidden;">
                                    <div style="width:<?= $pct ?>%;height:100%;background:<?= $pct >= 80 ? 'var(--danger,#ef4444)' : 'var(--accent)' ?>;border-radius:3px;transition:width .3s;"></div>
                                </div>
                                <span class="text-sm"><?= $postsToday ?>/<?= $limit ?></span>
                            </div>
                        </td>
                        <td class="text-sm"><?= !empty($ch['last_post_at']) ? date('d/m H:i', strtotime((string)$ch['last_post_at'])) : '—' ?></td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <button class="btn btn-sm btn-ghost" onclick="editChannel(<?= (int)$ch['id'] ?>)" title="Sửa">✏️</button>
                                <button class="btn btn-sm btn-ghost" onclick="deleteChannel(<?= (int)$ch['id'] ?>, '<?= e(addslashes($ch['channel_name'])) ?>')" title="Xóa" style="color:var(--danger);">🗑</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Channel Modal -->
<div class="modal-overlay" id="channelModal" style="display:none;">
    <div class="modal-box" style="max-width:560px;">
        <h3 id="channelModalTitle">Thêm kênh mới</h3>
        <form id="channelForm" onsubmit="submitChannel(event)">
            <input type="hidden" name="channel_id_edit" id="channel_id_edit" value="">
            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label">Loại kênh *</label>
                <select class="form-control" name="channel_type" id="ch_type" onchange="toggleFields()" required>
                    <option value="facebook_page">📘 Facebook Page (API)</option>
                    <option value="facebook_group">👥 Facebook Group (Browser)</option>
                    <option value="tiktok">🎵 TikTok (Browser Upload)</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label">Tên kênh *</label>
                <input class="form-control" name="channel_name" id="ch_name" required placeholder="VD: Page Ép Phê, Group Săn Deal...">
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label">Channel ID</label>
                <input class="form-control" name="channel_id" id="ch_id" placeholder="Page ID / Group ID / TikTok @username">
            </div>
            <div id="tokenField" class="form-group" style="margin-bottom:12px;">
                <label class="form-label">Access Token</label>
                <textarea class="form-control" name="access_token" id="ch_token" rows="2" placeholder="Facebook Page Access Token..."></textarea>
            </div>
            <div id="cookieField" class="form-group" style="margin-bottom:12px;display:none;">
                <label class="form-label">Cookie Session</label>
                <textarea class="form-control" name="cookie_data" id="ch_cookie" rows="3" placeholder="Paste cookies JSON từ browser extension..."></textarea>
                <div class="sub" style="margin-top:4px;">Dùng extension "EditThisCookie" hoặc "Cookie-Editor" để export cookie JSON</div>
            </div>
            <div class="grid-2" style="margin-bottom:12px;">
                <div class="form-group">
                    <label class="form-label">Giới hạn bài/ngày</label>
                    <input class="form-control" name="daily_post_limit" id="ch_limit" type="number" min="1" max="50" value="5">
                </div>
                <div class="form-group">
                    <label class="form-label">Trạng thái</label>
                    <select class="form-control" name="status" id="ch_status">
                        <option value="active">Hoạt động</option>
                        <option value="paused">Tạm dừng</option>
                    </select>
                </div>
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary" style="flex:1;">Lưu kênh</button>
                <button type="button" class="btn btn-cancel" onclick="closeModal('channelModal')">Hủy</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div class="modal-overlay" id="deleteModal" style="display:none;">
    <div class="modal-box" style="max-width:420px;">
        <h3>Xóa kênh?</h3>
        <p style="margin:12px 0;">Kênh <strong id="deleteChannelName"></strong> sẽ bị xóa vĩnh viễn.</p>
        <div class="modal-actions">
            <button class="btn btn-primary" style="background:var(--danger);flex:1;" onclick="confirmDelete()">Xóa</button>
            <button class="btn btn-cancel" onclick="closeModal('deleteModal')">Hủy</button>
        </div>
    </div>
</div>

<script>
const BASE = '<?= url('') ?>';
const CSRF = '<?= e(csrf_token()) ?>';
let deleteTargetId = null;

function toggleFields() {
    const type = document.getElementById('ch_type').value;
    const tokenField = document.getElementById('tokenField');
    const cookieField = document.getElementById('cookieField');
    if (type === 'facebook_page') {
        tokenField.style.display = '';
        cookieField.style.display = 'none';
    } else {
        tokenField.style.display = 'none';
        cookieField.style.display = '';
    }
}

function openAddChannel() {
    document.getElementById('channelModalTitle').textContent = 'Thêm kênh mới';
    document.getElementById('channelForm').reset();
    document.getElementById('channel_id_edit').value = '';
    toggleFields();
    document.getElementById('channelModal').style.display = 'flex';
}

function editChannel(id) {
    fetch(`${BASE}/api/channels/${id}`, { headers: { 'X-CSRF-Token': CSRF } })
        .then(r => r.json())
        .then(res => {
            if (!res.success) { alert(res.message); return; }
            const ch = res.data;
            document.getElementById('channelModalTitle').textContent = 'Sửa kênh';
            document.getElementById('channel_id_edit').value = ch.id;
            document.getElementById('ch_type').value = ch.channel_type || 'facebook_page';
            document.getElementById('ch_name').value = ch.channel_name || '';
            document.getElementById('ch_id').value = ch.channel_id || '';
            document.getElementById('ch_token').value = ch.access_token || '';
            document.getElementById('ch_cookie').value = ch.cookie_data || '';
            document.getElementById('ch_limit').value = ch.daily_post_limit || 5;
            document.getElementById('ch_status').value = ch.status || 'active';
            toggleFields();
            document.getElementById('channelModal').style.display = 'flex';
        })
        .catch(err => alert('Lỗi: ' + err.message));
}

function submitChannel(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append('csrf_token', CSRF);
    const editId = fd.get('channel_id_edit');
    fd.delete('channel_id_edit');
    const url = editId ? `${BASE}/channels/update/${editId}` : `${BASE}/channels/create`;
    fetch(url, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (typeof toastr !== 'undefined') toastr.success(res.message);
                setTimeout(() => location.reload(), 800);
            } else {
                if (typeof toastr !== 'undefined') toastr.error(res.message);
                else alert(res.message);
            }
        })
        .catch(err => alert('Lỗi: ' + err.message));
}

function deleteChannel(id, name) {
    deleteTargetId = id;
    document.getElementById('deleteChannelName').textContent = name;
    document.getElementById('deleteModal').style.display = 'flex';
}

function confirmDelete() {
    if (!deleteTargetId) return;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fetch(`${BASE}/channels/delete/${deleteTargetId}`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            closeModal('deleteModal');
            if (res.success) {
                if (typeof toastr !== 'undefined') toastr.success(res.message);
                setTimeout(() => location.reload(), 800);
            } else {
                if (typeof toastr !== 'undefined') toastr.error(res.message);
                else alert(res.message);
            }
        })
        .catch(err => alert('Lỗi: ' + err.message));
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', function(e) { if (e.target === this) this.style.display = 'none'; });
});
</script>
