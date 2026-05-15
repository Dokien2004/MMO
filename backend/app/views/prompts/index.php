<?php
$keyLabels = [
    'content_text'   => ['🖊️ Bài viết', 'Prompt gửi LLM để sinh tiêu đề + body + hashtag + CTA'],
    'image'          => ['🖼️ Ảnh AI', 'Prompt chính khi có đủ thông tin sản phẩm'],
    'image_fallback' => ['🖼️ Ảnh dự phòng', 'Prompt khi thiếu thông tin sản phẩm'],
    'video'          => ['🎬 Video AI', 'Prompt cho Kling / MeiGen / FFmpeg'],
];
$templatesByKey = [];
foreach ($templates as $t) {
    $templatesByKey[$t['template_key']][] = $t;
}
?>

<div class="page-header">
    <div>
        <div class="page-kicker">Prompt Engineering</div>
        <h2>Prompt Templates</h2>
        <p>Quản lý kịch bản AI. Thay đổi cách viết bài, thiết kế ảnh, dựng video — không cần sửa code.</p>
    </div>
    <div class="btn-group">
        <a class="btn btn-outline" href="<?= url('/settings') ?>">⚙️ Settings</a>
        <button class="btn btn-accent" onclick="openCreateModal()">+ Tạo template mới</button>
    </div>
</div>

<div class="action-strip">
    <div>
        <strong><?= count($templates) ?> templates</strong>
        <div class="sub">
            <?php foreach ($templateKeys as $key => $desc): ?>
                <?php $active = array_filter($templatesByKey[$key] ?? [], fn($t) => !empty($t['is_active'])); ?>
                <?= $keyLabels[$key][0] ?? $key ?>: <?= count($active) ? '✅' : '⚠️' ?>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="btn-group">
        <a class="btn btn-outline" href="<?= url('/contents') ?>">Content</a>
    </div>
</div>

<!-- Placeholder reference -->
<div class="card mb-16" id="placeholderRef">
    <div class="card-title" style="cursor:pointer" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='none'?'block':'none'">
        📋 Biến khả dụng <span class="sub">(bấm để mở/đóng)</span>
    </div>
    <div style="display:none">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Placeholder</th><th>Mô tả</th></tr></thead>
                <tbody>
                    <?php foreach ($placeholders as $ph => $desc): ?>
                        <tr>
                            <td><code><?= e($ph) ?></code></td>
                            <td><?= e($desc) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="hint-box mt-8">
            Khi sinh content/ảnh/video, hệ thống sẽ tự thay <code>{{product_name}}</code> thành tên sản phẩm thật, <code>{{price}}</code> thành giá đã format, v.v.
        </div>
    </div>
</div>

<!-- Template cards by key -->
<?php foreach ($templateKeys as $key => $desc): ?>
    <div class="card mb-16">
        <div class="card-title"><?= $keyLabels[$key][0] ?? $key ?> — <?= e($desc) ?></div>
        <div class="sub mb-8"><?= e($keyLabels[$key][1] ?? '') ?></div>

        <?php if (empty($templatesByKey[$key])): ?>
            <div class="empty-state">
                <p>Chưa có template nào cho loại này. Hệ thống sẽ dùng prompt mặc định hardcode.</p>
                <button class="btn btn-accent" onclick="openCreateModal('<?= e($key) ?>')">+ Tạo template</button>
            </div>
        <?php else: ?>
            <?php foreach ($templatesByKey[$key] as $t): ?>
                <div class="prompt-card <?= !empty($t['is_active']) ? 'prompt-active' : 'prompt-inactive' ?>" id="tmpl-<?= (int)$t['id'] ?>">
                    <div class="prompt-card-header">
                        <div>
                            <strong><?= e($t['template_name']) ?></strong>
                            <?php if (!empty($t['is_active'])): ?>
                                <span class="badge badge-success">Đang dùng</span>
                            <?php else: ?>
                                <span class="badge badge-muted">Tắt</span>
                            <?php endif; ?>
                            <span class="sub">#<?= (int)$t['id'] ?> · Cập nhật <?= e($t['updated_at']) ?></span>
                        </div>
                        <div class="btn-group">
                            <?php if (empty($t['is_active'])): ?>
                                <button class="btn btn-success btn-sm" onclick="activateTemplate(<?= (int)$t['id'] ?>)">✅ Kích hoạt</button>
                            <?php endif; ?>
                            <button class="btn btn-outline btn-sm" onclick="editTemplate(<?= (int)$t['id'] ?>)">✏️ Sửa</button>
                            <button class="btn btn-outline btn-sm" onclick="previewTemplate(<?= (int)$t['id'] ?>)">👁️ Preview</button>
                            <button class="btn btn-outline btn-sm" onclick="deleteTemplate(<?= (int)$t['id'] ?>, '<?= e(addslashes($t['template_name'])) ?>')">🗑️</button>
                        </div>
                    </div>
                    <?php if (trim($t['system_prompt'] ?? '') !== ''): ?>
                        <div class="prompt-section">
                            <div class="prompt-label">System Prompt:</div>
                            <pre class="prompt-pre"><?= e($t['system_prompt']) ?></pre>
                        </div>
                    <?php endif; ?>
                    <div class="prompt-section">
                        <div class="prompt-label">User Prompt:</div>
                        <pre class="prompt-pre"><?= e($t['user_prompt']) ?></pre>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<!-- Modal: Create / Edit template -->
<div class="modal-overlay" id="promptModal" style="display:none;">
    <div class="modal-box" style="max-width:720px;">
        <h3 id="promptModalTitle">Tạo Prompt Template</h3>
        <form data-ajax method="POST" action="<?= url('/prompts/save') ?>" id="promptForm">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" id="pf_id">

            <div class="grid-2 compact-grid mb-12">
                <div class="form-group">
                    <label class="form-label">Loại prompt *</label>
                    <select class="form-control" name="template_key" id="pf_key" required>
                        <?php foreach ($templateKeys as $key => $desc): ?>
                            <option value="<?= e($key) ?>"><?= $keyLabels[$key][0] ?? $key ?> — <?= e($desc) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Tên hiển thị *</label>
                    <input class="form-control" name="template_name" id="pf_name" required placeholder="VD: Phong cách Review Chuyên Gia">
                </div>
            </div>

            <div class="form-group mb-12">
                <label class="form-label">System Prompt <span class="sub">(role=system, chỉ dùng cho sinh bài viết text)</span></label>
                <textarea class="form-control" name="system_prompt" id="pf_system" rows="3" placeholder="VD: Ban la copywriter affiliate..."></textarea>
            </div>

            <div class="form-group mb-12">
                <label class="form-label">User Prompt * <span class="sub">(nội dung chính, hỗ trợ {{placeholder}})</span></label>
                <textarea class="form-control" name="user_prompt" id="pf_user" rows="12" required placeholder="Dùng {{product_name}}, {{price}}, {{platform}}, {{affiliate_url}}..."></textarea>
            </div>

            <div class="grid-2 compact-grid mb-12">
                <div class="form-group">
                    <label class="form-label">Trạng thái</label>
                    <select class="form-control" name="is_active" id="pf_active">
                        <option value="1">Kích hoạt (dùng ngay)</option>
                        <option value="0">Tắt (lưu nháp)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Thứ tự ưu tiên</label>
                    <input class="form-control" type="number" name="sort_order" id="pf_sort" value="0" min="0">
                </div>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary">💾 Lưu template</button>
                <button type="button" class="btn btn-outline" onclick="previewCurrentForm()">👁️ Preview</button>
                <button type="button" class="btn btn-outline" onclick="closePromptModal()">Đóng</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Preview -->
<div class="modal-overlay" id="previewModal" style="display:none;">
    <div class="modal-box" style="max-width:700px;">
        <h3>👁️ Preview Prompt</h3>
        <p class="sub mb-8">Kết quả sau khi thay thế placeholder bằng dữ liệu sản phẩm mẫu.</p>
        <pre class="prompt-pre" id="previewResult" style="max-height:450px;overflow-y:auto;white-space:pre-wrap;"></pre>
        <button type="button" class="btn btn-outline mt-16" onclick="document.getElementById('previewModal').style.display='none'">Đóng</button>
    </div>
</div>

<style>
.prompt-card{border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:12px;background:var(--bg-card);transition:border-color .2s}
.prompt-active{border-left:3px solid var(--accent)}
.prompt-inactive{opacity:.7}
.prompt-card-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:8px}
.prompt-section{margin-top:8px}
.prompt-label{font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px}
.prompt-pre{background:var(--bg-hover);border:1px solid var(--border);border-radius:8px;padding:12px 14px;font-size:12.5px;line-height:1.5;white-space:pre-wrap;word-break:break-word;max-height:200px;overflow-y:auto;color:var(--text-secondary);font-family:'JetBrains Mono',monospace}
.badge-success{background:var(--success);color:#fff;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600}
.badge-muted{background:var(--bg-hover);color:var(--text-muted);padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600}
.mb-12{margin-bottom:12px}
.mb-16{margin-bottom:16px}
.mb-8{margin-bottom:8px}
#promptForm textarea{font-family:'JetBrains Mono',monospace;font-size:12.5px;line-height:1.5}
</style>

<script>
const TEMPLATES_DATA = <?= json_encode($templates, JSON_UNESCAPED_UNICODE) ?>;

function openCreateModal(key) {
    document.getElementById('promptModalTitle').textContent = 'Tạo Prompt Template';
    document.getElementById('pf_id').value = '';
    document.getElementById('pf_key').value = key || 'content_text';
    document.getElementById('pf_name').value = '';
    document.getElementById('pf_system').value = '';
    document.getElementById('pf_user').value = '';
    document.getElementById('pf_active').value = '1';
    document.getElementById('pf_sort').value = '0';
    document.getElementById('promptModal').style.display = 'flex';
}

function editTemplate(id) {
    const t = TEMPLATES_DATA.find(x => +x.id === id);
    if (!t) return;
    document.getElementById('promptModalTitle').textContent = 'Sửa Template #' + id;
    document.getElementById('pf_id').value = id;
    document.getElementById('pf_key').value = t.template_key;
    document.getElementById('pf_name').value = t.template_name;
    document.getElementById('pf_system').value = t.system_prompt || '';
    document.getElementById('pf_user').value = t.user_prompt || '';
    document.getElementById('pf_active').value = t.is_active ? '1' : '0';
    document.getElementById('pf_sort').value = t.sort_order || 0;
    document.getElementById('promptModal').style.display = 'flex';
}

function closePromptModal() {
    document.getElementById('promptModal').style.display = 'none';
}

function activateTemplate(id) {
    if (!confirm('Kích hoạt template #' + id + '? Các template cùng loại sẽ bị tắt.')) return;
    const fd = new FormData();
    fd.append('id', id);
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');
    fetch(BASE_URL + '/prompts/activate', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            if (res.success) { toastr.success(res.message); location.reload(); }
            else toastr.error(res.message);
        });
}

function deleteTemplate(id, name) {
    if (!confirm('Xóa template "' + name + '"? Không thể hoàn tác.')) return;
    const fd = new FormData();
    fd.append('id', id);
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');
    fetch(BASE_URL + '/prompts/delete', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            if (res.success) { toastr.success(res.message); location.reload(); }
            else toastr.error(res.message);
        });
}

function previewTemplate(id) {
    const t = TEMPLATES_DATA.find(x => +x.id === id);
    if (!t) return;
    doPreview(t.user_prompt || '');
}

function previewCurrentForm() {
    const text = document.getElementById('pf_user').value;
    if (!text.trim()) { toastr.warning('Nhập nội dung User Prompt trước.'); return; }
    doPreview(text);
}

function doPreview(promptText) {
    const fd = new FormData();
    fd.append('user_prompt', promptText);
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');
    fetch(BASE_URL + '/prompts/preview', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                document.getElementById('previewResult').textContent = res.data?.rendered || '(trống)';
                document.getElementById('previewModal').style.display = 'flex';
            } else {
                toastr.error(res.message);
            }
        });
}

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
});
</script>
