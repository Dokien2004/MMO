
<style>
    /* =====================================================================
     * MODULE CONFIG UI - Oracle-style per-site toggle management
     * ===================================================================== */
    
    /* Page Header */
    .config-header {
        background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
        color: #fff;
        border-radius: 12px;
        padding: 24px 28px;
        margin-bottom: 24px;
    }
    .config-header h4 { margin: 0; font-weight: 700; font-size: 1.3rem; }
    .config-header .subtitle { opacity: 0.8; font-size: 0.85rem; margin-top: 4px; }
    
    /* Site Selector */
    .site-selector {
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: 8px;
        padding: 6px 14px;
        color: #fff;
        font-weight: 600;
        min-width: 200px;
    }
    .site-selector option { color: #333; background: #fff; }
    .site-selector:focus { outline: none; box-shadow: 0 0 0 2px rgba(255,255,255,0.5); }

    /* Stats Badge */
    .stat-badge {
        background: rgba(255,255,255,0.2);
        border-radius: 20px; padding: 4px 14px;
        font-size: 0.8rem; font-weight: 600;
    }

    /* Module Cards */
    .module-card {
        background: #fff;
        border-radius: 10px;
        border: 1px solid #e8ecf1;
        transition: all 0.2s ease;
        overflow: hidden;
    }
    .module-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
    .module-card.disabled { opacity: 0.6; }
    .module-card.disabled .module-body { background: #fafafa; }
    
    .module-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 16px 20px;
        border-bottom: 1px solid #f0f2f5;
        cursor: pointer;
        user-select: none;
    }
    .module-header:hover { background: #f8fafc; }

    .module-icon {
        width: 44px; height: 44px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    .module-icon.core { background: #e3f2fd; color: #1565c0; }
    .module-icon.addon { background: #f3e5f5; color: #7b1fa2; }
    
    .module-info { flex: 1; margin-left: 14px; }
    .module-info .mod-name { font-weight: 700; font-size: 0.95rem; color: #1e293b; }
    .module-info .mod-meta {
        display: flex; align-items: center; gap: 8px;
        margin-top: 3px; font-size: 0.78rem; color: #94a3b8;
    }
    .module-info .mod-code {
        font-family: 'Consolas', monospace;
        background: #f1f5f9; color: #475569;
        padding: 1px 6px; border-radius: 4px;
        font-size: 0.72rem; font-weight: 600;
    }
    .module-info .type-badge {
        padding: 1px 8px; border-radius: 10px;
        font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
    }
    .type-badge.core { background: #dbeafe; color: #1e40af; }
    .type-badge.addon { background: #f3e8ff; color: #6b21a8; }

    .feature-count-badge {
        background: #f1f5f9; color: #64748b;
        padding: 4px 10px; border-radius: 6px;
        font-size: 0.78rem; font-weight: 600;
        margin-right: 12px; white-space: nowrap;
    }
    .feature-count-badge .enabled-count { color: #059669; font-weight: 700; }

    /* Toggle Switch - Oracle style */
    .module-toggle .form-check-input {
        width: 3em; height: 1.5em; cursor: pointer;
        border: 2px solid #cbd5e1;
    }
    .module-toggle .form-check-input:checked {
        background-color: #059669; border-color: #059669;
    }
    .module-toggle .form-check-input:focus {
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.2);
    }

    /* Feature expand area */
    .module-body {
        max-height: 0; overflow: hidden;
        transition: max-height 0.35s ease;
        background: #fafbfc;
    }
    .module-body.expanded {
        max-height: 2000px; /* Đủ lớn để hiển thị tất cả features */
    }
    .module-body-inner { padding: 16px 20px; }

    /* Expand Arrow */
    .expand-arrow {
        font-size: 0.75rem; color: #94a3b8;
        transition: transform 0.3s ease;
        margin-left: 8px;
    }
    .expand-arrow.rotated { transform: rotate(180deg); }

    /* Feature Row */
    .feature-row {
        display: flex; align-items: center; justify-content: space-between;
        padding: 10px 14px;
        border-radius: 8px;
        border: 1px solid #e8ecf1;
        background: #fff;
        margin-bottom: 8px;
        transition: all 0.15s ease;
    }
    .feature-row:hover { border-color: #c7d2fe; background: #f8faff; }
    .feature-row.disabled-feature { opacity: 0.5; background: #f9fafb; }
    
    .feature-info { flex: 1; }
    .feature-info .feat-name { font-weight: 600; font-size: 0.88rem; color: #334155; }
    .feature-info .feat-code {
        font-family: 'Consolas', monospace;
        font-size: 0.72rem; color: #94a3b8;
    }
    .feature-info .feat-depends {
        font-size: 0.72rem; color: #f59e0b;
        margin-top: 2px;
    }
    .feature-type-badge {
        font-size: 0.68rem; padding: 2px 8px; border-radius: 10px;
        font-weight: 600; margin-left: 8px;
    }
    .feature-type-badge.sub_module { background: #ecfdf5; color: #065f46; }
    .feature-type-badge.report { background: #fef3c7; color: #92400e; }
    .feature-type-badge.config { background: #f0f9ff; color: #0369a1; }
    .feature-type-badge.workflow { background: #fce7f3; color: #9d174d; }

    .feature-toggle .form-check-input {
        width: 2.5em; height: 1.25em; cursor: pointer;
        border: 2px solid #cbd5e1;
    }
    .feature-toggle .form-check-input:checked {
        background-color: #0ea5e9; border-color: #0ea5e9;
    }

    /* Loading spinner */
    .toggle-loading {
        display: none;
        width: 20px; height: 20px;
        border: 2px solid #e2e8f0; border-top: 2px solid #3b82f6;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Empty features */
    .no-features {
        text-align: center; padding: 20px;
        color: #94a3b8; font-size: 0.85rem;
    }

    /* Legend */
    .legend-bar {
        display: flex; gap: 16px; flex-wrap: wrap;
        font-size: 0.78rem; color: #64748b;
    }
    .legend-item { display: flex; align-items: center; gap: 4px; }
</style>

<div class="container-fluid px-4 mt-4 mb-5">

    <!-- ====================== PAGE HEADER ====================== -->
    <div class="config-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4><i class="fas fa-puzzle-piece me-2"></i>Cấu hình Module & Tính năng</h4>
                <div class="subtitle">Bật/tắt module và tính năng cho từng nhà máy (Oracle Opt-In Model)</div>
            </div>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <?php
                    $enabledModCount = 0;
                    $totalModCount = count($data['modules']);
                    foreach ($data['modules'] as $m) { if ($m->is_enabled) $enabledModCount++; }
                ?>
                <span class="stat-badge">
                    <i class="fas fa-cubes me-1"></i>
                    <span id="statModules"><?= $enabledModCount ?></span> / <?= $totalModCount ?> modules
                </span>

                <?php if ($data['isSuperAdmin'] && !empty($data['sites'])): ?>
                    <select class="site-selector" id="siteSelector" onchange="changeSite(this.value)">
                        <?php foreach ($data['sites'] as $site): ?>
                            <option value="<?= $site->id ?>" <?= $site->id == $data['siteId'] ? 'selected' : '' ?>>
                                <?= e($site->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Nút Đồng bộ Module & Features (giống sync Permissions) -->
                    <form method="POST" action="<?= url('/admin') ?>/systemmodules/syncModules" 
                          id="formSyncModules" style="margin:0; display:inline-block;">
                        <?php csrf_field(); ?>
                        <button type="button" class="btn btn-sm btn-outline-light" 
                                onclick="confirmSyncModules()" title="Đồng bộ Module & Features từ config file">
                            <i class="fas fa-sync-alt me-1"></i>Sync Modules
                        </button>
                    </form>
                <?php else: ?>
                    <span class="stat-badge">
                        <i class="fas fa-building me-1"></i><?= e($data['currentSite']->name) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Alert box -->
    <div id="alertBox"><?php if(function_exists('flash')) flash('sysmod_msg'); ?></div>

    <!-- Legend -->
    <div class="legend-bar mb-3">
        <div class="legend-item"><span class="type-badge core">CORE</span> Module lõi hệ thống</div>
        <div class="legend-item"><span class="type-badge addon">ADDON</span> Module mở rộng</div>
        <div class="legend-item"><i class="fas fa-link text-warning"></i> Có dependency</div>
    </div>

    <!-- ====================== MODULE LIST ====================== -->
    <div class="row g-3" id="moduleList">
        <?php foreach ($data['modules'] as $mod): ?>
            <?php
                $isEnabled = (bool) $mod->is_enabled;
                $typeClass = strtolower($mod->module_type ?? 'core');
                $features = $data['featuresGrouped'][$mod->id] ?? [];
                $enabledFeats = 0;
                foreach ($features as $f) { if ($f->is_enabled) $enabledFeats++; }
            ?>
            <div class="col-12" id="module-card-<?= $mod->id ?>">
                <div class="module-card <?= !$isEnabled ? 'disabled' : '' ?>" data-module-id="<?= $mod->id ?>">
                    
                    <!-- Module Header (Click to expand features) -->
                    <div class="module-header" onclick="toggleExpand(<?= $mod->id ?>)">
                        <div class="d-flex align-items-center flex-grow-1">
                            <div class="module-icon <?= $typeClass ?>">
                                <i class="<?= e($mod->icon) ?>"></i>
                            </div>
                            <div class="module-info">
                                <div class="mod-name">
                                    <?= e($mod->name) ?>
                                </div>
                                <div class="mod-meta">
                                    <span class="mod-code"><?= e($mod->code) ?></span>
                                    <span class="type-badge <?= $typeClass ?>"><?= e($mod->module_type ?? 'CORE') ?></span>
                                    <?php if (!empty($mod->description)): ?>
                                        <span class="d-none d-md-inline">— <?= e($mod->description) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-center">
                            <!-- Feature count badge -->
                            <?php if ($mod->feature_count > 0): ?>
                                <span class="feature-count-badge" id="feat-badge-<?= $mod->id ?>">
                                    <i class="fas fa-puzzle-piece me-1"></i>
                                    <span class="enabled-count"><?= $enabledFeats ?></span> / <?= $mod->feature_count ?>
                                </span>
                            <?php endif; ?>

                            <!-- Module Toggle -->
                            <div class="module-toggle" onclick="event.stopPropagation()">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" 
                                           id="mod-toggle-<?= $mod->id ?>"
                                           <?= $isEnabled ? 'checked' : '' ?>
                                           onchange="handleModuleToggle(<?= $mod->id ?>, this)">
                                </div>
                            </div>

                            <!-- Loading indicator -->
                            <div class="toggle-loading ms-2" id="mod-loading-<?= $mod->id ?>"></div>

                            <!-- Expand arrow -->
                            <i class="fas fa-chevron-down expand-arrow" id="arrow-<?= $mod->id ?>"></i>
                        </div>
                    </div>

                    <!-- Features Panel (Collapsed by default) -->
                    <div class="module-body" id="features-<?= $mod->id ?>">
                        <div class="module-body-inner">
                            <?php if (empty($features)): ?>
                                <div class="no-features">
                                    <i class="fas fa-info-circle me-1"></i>Module này chưa có tính năng con nào được định nghĩa.
                                </div>
                            <?php else: ?>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted" style="font-size: 0.82rem; font-weight: 600;">
                                        <i class="fas fa-list-check me-1"></i>Danh sách Tính năng
                                    </span>
                                </div>
                                <?php foreach ($features as $feat): ?>
                                    <?php
                                        $featEnabled = (bool) $feat->is_enabled;
                                        $featTypeClass = strtolower($feat->feature_type ?? 'sub_module');
                                        $moduleDisabled = !$isEnabled;
                                    ?>
                                    <div class="feature-row <?= (!$featEnabled || $moduleDisabled) ? 'disabled-feature' : '' ?>"
                                         id="feat-row-<?= $feat->id ?>">
                                        <div class="feature-info">
                                            <div class="d-flex align-items-center">
                                                <span class="feat-name"><?= e($feat->name) ?></span>
                                                <span class="feature-type-badge <?= $featTypeClass ?>"><?= e($feat->feature_type) ?></span>
                                            </div>
                                            <div class="feat-code"><?= e($feat->code) ?></div>
                                            <?php if (!empty($feat->depends_on)): ?>
                                                <div class="feat-depends">
                                                    <i class="fas fa-link me-1"></i>Yêu cầu: <?= e($feat->depends_on) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <div class="toggle-loading me-2" id="feat-loading-<?= $feat->id ?>"></div>
                                            <div class="feature-toggle" onclick="event.stopPropagation()">
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input" type="checkbox"
                                                           id="feat-toggle-<?= $feat->id ?>"
                                                           <?= $featEnabled ? 'checked' : '' ?>
                                                           <?= $moduleDisabled ? 'disabled' : '' ?>
                                                           data-module-id="<?= $mod->id ?>"
                                                           onchange="handleFeatureToggle(<?= $feat->id ?>, this)">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Info note -->
    <div class="mt-4 p-3 rounded" style="background: #fffbeb; border: 1px solid #fde68a; font-size: 0.82rem; color: #92400e;">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Lưu ý:</strong> Tắt module sẽ tự động tắt tất cả tính năng con. Thay đổi có hiệu lực ngay lập tức cho site đang chọn.
        Nếu bạn đang thao tác trên site hiện tại, menu sẽ tự động cập nhật sau khi reload trang.
    </div>
</div>


<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Module Config JS -->
<script>
    // Config từ PHP
    const MODULE_CONFIG = {
        siteId: <?= $data['siteId'] ?>,
        csrfToken: '<?= $_SESSION['csrf_token'] ?? '' ?>',
        urls: {
            config: '<?= url('/admin') ?>/systemmodules/config/',
            toggleModule: '<?= url('/admin') ?>/systemmodules/ajax_toggle_module',
            toggleFeature: '<?= url('/admin') ?>/systemmodules/ajax_toggle_feature',
            features: '<?= url('/admin') ?>/systemmodules/ajax_features/'
        }
    };
</script>
<script src="<?= asset_v('js/modules/core/module-config.js') ?>"></script>

<script>
// Xác nhận trước khi sync modules (tránh bấm nhầm)
function confirmSyncModules() {
    Swal.fire({
        title: 'Đồng bộ Module & Features?',
        html: 'Hệ thống sẽ đồng bộ từ file <code>config/modules_list.php</code> vào database.<br>' +
              '<small class="text-muted">• Thêm module/feature mới<br>• Cập nhật thông tin<br>• Vô hiệu mục thừa<br>• Auto-enable cho các sites</small>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#1a237e',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-sync-alt"></i> Đồng bộ',
        cancelButtonText: 'Hủy'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('formSyncModules').submit();
        }
    });
}
</script>
