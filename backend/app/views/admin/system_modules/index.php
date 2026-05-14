<div class="page-header">
    <div>
        <h2><i class="fas fa-cubes me-2"></i> Quản lý Modules</h2>
        <p>Bật hoặc tắt các khu vực chức năng đang hiển thị trong hệ thống.</p>
    </div>
</div>

<?php if (function_exists('flash')) flash('sysmod_msg'); ?>

<?php
$modules = $modules ?? ($data['modules'] ?? []);
$coreModules = ['DASHBOARD', 'ADMIN'];
?>

<div class="card mb-24">
    <div class="card-title">Tổng quan hệ thống</div>
    <div class="stats-grid" style="margin-bottom: 0;">
        <div class="stat-card">
            <div class="label">Tổng modules</div>
            <div class="value"><?= count($modules); ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Đang hoạt động</div>
            <div class="value">
                <?= count(array_filter($modules, static fn(array $module): bool => !empty($module['is_enabled']))); ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="label">Module lõi</div>
            <div class="value"><?= count($coreModules); ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-title">Danh sách modules</div>

    <?php if (empty($modules)): ?>
        <div class="empty-state">
            <i class="fas fa-cubes fa-3x mb-16" style="opacity: 0.25;"></i>
            <h5 class="fw-normal">Chưa có module nào</h5>
            <p class="text-sm">Danh sách module sẽ hiển thị tại đây khi dữ liệu được seed vào hệ thống.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th width="320">Module</th>
                        <th>Mô tả</th>
                        <th width="120" class="text-center">Thứ tự</th>
                        <th width="140" class="text-center">Trạng thái</th>
                        <th width="140" class="text-center">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modules as $module): ?>
                        <?php
                        $code = (string)($module['code'] ?? '');
                        $name = (string)($module['name'] ?? $code);
                        $description = trim((string)($module['description'] ?? ''));
                        $icon = trim((string)($module['icon'] ?? 'fas fa-cube'));
                        $isEnabled = !empty($module['is_enabled']);
                        $isCore = in_array($code, $coreModules, true);
                        ?>
                        <tr>
                            <td>
                                <div class="flex items-center gap-8">
                                    <div class="user-avatar" style="background: rgba(6,182,212,0.12); color: #22d3ee;">
                                        <i class="<?= e($icon); ?>"></i>
                                    </div>
                                    <div>
                                        <div class="fw-600 text"><?= e($name); ?></div>
                                        <div class="mono"><?= e($code); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($description !== ''): ?>
                                    <span class="text-sm text-sec"><?= e($description); ?></span>
                                <?php else: ?>
                                    <span class="sub fst-italic">- Chưa có mô tả -</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="metric-pill"><?= (int)($module['sort_order'] ?? 0); ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($isEnabled): ?>
                                    <span class="badge badge-active">Đang bật</span>
                                <?php else: ?>
                                    <span class="badge badge-error">Đang tắt</span>
                                <?php endif; ?>
                                <?php if ($isCore): ?>
                                    <div class="mt-8">
                                        <span class="badge badge-linked">Core</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <label class="switch-inline" title="<?= $isCore ? 'Module lõi không thể tắt' : 'Bật hoặc tắt module'; ?>">
                                    <input
                                        type="checkbox"
                                        class="module-toggle"
                                        data-id="<?= (int)($module['id'] ?? 0); ?>"
                                        <?= $isEnabled ? 'checked' : ''; ?>
                                        <?= $isCore ? 'disabled' : ''; ?>
                                    >
                                    <span class="switch-slider"></span>
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
.switch-inline {
    position: relative;
    display: inline-flex;
    width: 48px;
    height: 28px;
}

.switch-inline input {
    opacity: 0;
    width: 0;
    height: 0;
}

.switch-slider {
    position: absolute;
    inset: 0;
    border-radius: 999px;
    background: rgba(100,116,139,0.28);
    border: 1px solid rgba(255,255,255,0.08);
    transition: var(--transition);
    cursor: pointer;
}

.switch-slider::before {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    left: 3px;
    top: 3px;
    border-radius: 50%;
    background: #fff;
    transition: var(--transition);
    box-shadow: 0 2px 8px rgba(0,0,0,0.24);
}

.switch-inline input:checked + .switch-slider {
    background: rgba(16,185,129,0.32);
    border-color: rgba(16,185,129,0.48);
}

.switch-inline input:checked + .switch-slider::before {
    transform: translateX(20px);
}

.switch-inline input:disabled + .switch-slider {
    cursor: not-allowed;
    opacity: 0.65;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggles = document.querySelectorAll('.module-toggle');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    toggles.forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            const checkbox = this;
            const formData = new FormData();
            formData.append('module_id', checkbox.dataset.id || '');
            formData.append('enabled', checkbox.checked ? '1' : '0');
            formData.append('csrf_token', csrfToken);

            fetch('<?= url('/admin/modules/toggle'); ?>', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': csrfToken
                },
                body: formData
            })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data.success) {
                    checkbox.checked = !checkbox.checked;
                    alert(data.message || 'Không thể cập nhật trạng thái module.');
                    return;
                }
                window.location.reload();
            })
            .catch(function () {
                checkbox.checked = !checkbox.checked;
                alert('Không thể cập nhật trạng thái module.');
            });
        });
    });
});
</script>
