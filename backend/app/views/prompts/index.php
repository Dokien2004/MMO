<?php
$platforms = [
    'facebook'  => ['icon' => '📘', 'label' => 'Facebook'],
    'tiktok'    => ['icon' => '🎵', 'label' => 'TikTok'],
    'instagram' => ['icon' => '📷', 'label' => 'Instagram'],
    'threads'   => ['icon' => '💬', 'label' => 'Threads'],
    'general'   => ['icon' => '🌐', 'label' => 'Chung (Fallback)'],
];

$typeLabels = [
    'content' => ['icon' => '📝', 'label' => 'Content', 'desc' => 'Sinh bài viết text'],
    'image'   => ['icon' => '🖼️', 'label' => 'Image', 'desc' => 'Tạo ảnh AI'],
    'video'   => ['icon' => '🎬', 'label' => 'Video', 'desc' => 'Tạo video slideshow'],
];

// Group templates
$byPlatform = [];
foreach ($templates as $t) {
    $p = $t['platform'] ?? 'general';
    $key = $t['template_key'] ?? '';
    // Determine type
    if (strpos($key, 'content_text') === 0) $type = 'content';
    elseif (strpos($key, 'image') === 0) $type = 'image';
    elseif (strpos($key, 'video') === 0) $type = 'video';
    else continue;
    
    if (!isset($byPlatform[$p])) $byPlatform[$p] = [];
    if (!isset($byPlatform[$p][$type])) $byPlatform[$p][$type] = null;
    $byPlatform[$p][$type] = $t;
}
?>

<style>
.prompts-tabs{display:flex;gap:4px;margin-bottom:20px;flex-wrap:wrap}
.prompts-tab{padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;background:var(--bg-hover);color:var(--text-muted);transition:all .2s}
.prompts-tab:hover{background:var(--border)}
.prompts-tab.active{background:var(--accent);color:#fff}
.platform-section{display:none}
.platform-section.active{display:block}
.platform-section h2{font-size:16px;margin:0 0 16px;color:var(--text)}
.type-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:12px;margin-bottom:16px}
.type-card{border:1px solid var(--border);border-radius:12px;padding:16px;background:var(--bg-card)}
.type-card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px}
.type-card h3{margin:0;font-size:14px;font-weight:700;display:flex;align-items:center;gap:8px}
.type-card h3 .sub{font-size:12px;font-weight:400;color:var(--text-muted)}
.prompt-field{margin-bottom:12px}
.prompt-field label{display:block;font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px}
.prompt-field textarea{width:100%;border:1px solid var(--border);border-radius:8px;padding:10px;font-size:12px;line-height:1.5;background:var(--bg);color:var(--text);font-family:'JetBrains Mono',monospace;resize:vertical;box-sizing:border-box;min-height:80px}
.prompt-field textarea:focus{outline:none;border-color:var(--accent)}
.btn-save{background:var(--success);color:#fff;border:none;padding:8px 20px;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px}
.btn-save:hover{opacity:.9}
.saved-msg{font-size:12px;color:var(--success);margin-top:6px;display:none}
.badge-active{background:var(--success);color:#fff;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600}
.badge-muted{background:var(--bg-hover);color:var(--text-muted);padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600}
</style>

<div class="page-header">
    <div>
        <div class="page-kicker">Prompt Engineering</div>
        <h2>Prompt Templates</h2>
        <p>Sửa prompt cho từng mạng xã hội: content, ảnh, video.</p>
    </div>
</div>

<!-- Tabs -->
<div class="prompts-tabs">
    <?php foreach ($platforms as $key => $info): ?>
        <button class="prompts-tab <?= $key === 'facebook' ? 'active' : '' ?>" 
                data-platform="<?= e($key) ?>"
                onclick="switchTab('<?= e($key) ?>')">
            <?= $info['icon'] ?> <?= $info['label'] ?>
        </button>
    <?php endforeach; ?>
</div>

<!-- Sections -->
<?php foreach ($platforms as $pkey => $pinfo): ?>
    <div class="platform-section <?= $pkey === 'facebook' ? 'active' : '' ?>" id="section-<?= e($pkey) ?>">
        <h2><?= $pinfo['icon'] ?> <?= $pinfo['label'] ?></h2>
        
        <div class="type-grid">
            <?php foreach ($typeLabels as $tkey => $tinfo): ?>
                <?php $tpl = $byPlatform[$pkey][$tkey] ?? null; ?>
                <div class="type-card">
                    <div class="type-card-header">
                        <h3><?= $tinfo['icon'] ?> <?= $tinfo['label'] ?> <span class="sub">— <?= $tinfo['desc'] ?></span></h3>
                        <?php if ($tpl): ?>
                            <span class="badge-active">✓ Active</span>
                        <?php else: ?>
                            <span class="badge-muted">Fallback</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($tpl): ?>
                        <form data-ajax method="POST" action="<?= url('/prompts/save') ?>" 
                              onsubmit="savePrompt(this, '<?= e($pkey) ?>_<?= e($tkey) ?>'); return false;">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$tpl['id'] ?>">
                            <input type="hidden" name="template_key" value="<?= e($tpl['template_key']) ?>">
                            <input type="hidden" name="platform" value="<?= e($pkey) ?>">
                            <input type="hidden" name="template_name" value="<?= e($tpl['template_name']) ?>">
                            <input type="hidden" name="is_active" value="1">
                            <input type="hidden" name="sort_order" value="10">

                            <div class="prompt-field">
                                <label>System Prompt</label>
                                <textarea name="system_prompt" rows="4"><?= e($tpl['system_prompt'] ?? '') ?></textarea>
                            </div>

                            <div class="prompt-field">
                                <label>User Prompt</label>
                                <textarea name="user_prompt" rows="4"><?= e($tpl['user_prompt'] ?? '') ?></textarea>
                            </div>

                            <button type="submit" class="btn-save">💾 Lưu</button>
                            <span class="saved-msg" id="saved-<?= e($pkey) ?>_<?= e($tkey) ?>">✓ Đã lưu</span>
                            <span class="sub" style="margin-left:12px;font-size:11px">#<?= (int)$tpl['id'] ?></span>
                        </form>
                    <?php else: ?>
                        <p class="sub" style="font-size:12px">Đang dùng prompt chung (general). Hệ thống sẽ tự fallback.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<!-- Placeholder reference -->
<div class="card mb-16" style="margin-top:8px">
    <div class="card-title" style="cursor:pointer" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='none'?'block':'none'">
        📋 Biến khả dụng trong prompt <span class="sub">(bấm để mở/đóng)</span>
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
        <div class="hint-box">
            Khi sinh content/ảnh/video, hệ thống tự thay <code>{{product_name}}</code> bằng tên sản phẩm, <code>{{price}}</code> bằng giá format, v.v.
        </div>
    </div>
</div>

<script>
function switchTab(platform) {
    document.querySelectorAll('.prompts-tab').forEach(t => {
        t.classList.toggle('active', t.dataset.platform === platform);
    });
    document.querySelectorAll('.platform-section').forEach(s => {
        s.classList.toggle('active', s.id === 'section-' + platform);
    });
}

function savePrompt(form, key) {
    const fd = new FormData(form);
    fetch(form.action, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const saved = document.getElementById('saved-' + key);
                if (saved) {
                    saved.style.display = 'inline';
                    setTimeout(() => saved.style.display = 'none', 2000);
                }
                toastr.success('Đã lưu!');
            } else {
                toastr.error(res.message || 'Lỗi khi lưu');
            }
        });
}

// Ctrl+S to save active section
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        const form = document.querySelector('.platform-section.active form');
        if (form) savePrompt(form, document.querySelector('.prompts-tab.active').dataset.platform + '_content');
    }
});
</script>