<?php require APP_VIEWS_PATH . '/layouts/header.php'; ?>

<style>
    .notification-section { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); border-radius: .5rem; overflow: hidden; }
    .notification-section .section-header { cursor: pointer; transition: background .2s; }
    .notification-section .section-header:hover { filter: brightness(0.97); }
    .notification-section .section-header .toggle-icon { transition: transform .3s; }
    .notification-section .section-header.collapsed .toggle-icon { transform: rotate(-90deg); }
    .section-status { font-size: .75rem; padding: .25em .6em; border-radius: 50px; }
    .tag-input-wrap { min-height: 44px; border: 1px solid #dee2e6; border-radius: .375rem; padding: .35rem .5rem; cursor: text; display: flex; flex-wrap: wrap; gap: .35rem; align-items: center; background: #fff; }
    .tag-input-wrap:focus-within { border-color: #86b7fe; box-shadow: 0 0 0 .25rem rgba(13,110,253,.25); }
    .tag-input-wrap input { border: none; outline: none; flex: 1 1 200px; min-width: 200px; padding: .15rem 0; background: transparent; }
    .tag-input-wrap .badge { font-size: .82rem; padding: .4em .6em; }
    .sub-section-title { font-size: .9rem; font-weight: 600; color: #495057; border-bottom: 1px solid #e9ecef; padding-bottom: .5rem; margin-bottom: .75rem; }
</style>

<?php
/**
 * Helper: Render reusable recipient selection block
 * @param string $prefix    - ID prefix, e.g. 'daily', 'mccUnknown', 'mccMissing'
 * @param string $dbPrefix  - DB field prefix, e.g. '', 'mcc_unknown_', 'mcc_missing_'
 * @param object $config    - Config object
 * @param array  $roles     - Roles list
 * @param array  $users     - Users list
 * @param string $badgeColor - Badge class for emails e.g. 'bg-success', 'bg-danger'
 */
function renderRecipientBlock($prefix, $dbPrefix, $config, $roles, $users, $badgeColor = 'bg-success') {
    // Resolve field names
    $recipientType = $dbPrefix ? $config->{$dbPrefix . 'recipient_type'} : $config->recipient_type;
    $selectedRoles = $dbPrefix ? ($config->{$dbPrefix . 'selected_roles'} ?? []) : ($config->selected_roles ?? []);
    $selectedUserIds = $dbPrefix ? ($config->{$dbPrefix . 'selected_users'} ?? []) : ($config->selected_users ?? []);
    $additionalEmails = $dbPrefix ? ($config->{$dbPrefix . 'additional_emails'} ?? []) : ($config->additional_emails ?? []);
    $ccEmails = $dbPrefix ? ($config->{$dbPrefix . 'cc'} ?? []) : ($config->cc_emails ?? []);
    $postRoleName = $dbPrefix ? "{$dbPrefix}selected_roles" : "selected_roles";
    $postTypeName = $dbPrefix ? "{$dbPrefix}recipient_type" : "recipient_type";
?>
    <!-- Kiểu người nhận -->
    <div class="mb-3">
        <div class="sub-section-title">📋 Kiểu người nhận</div>
        <div class="d-flex gap-4">
            <div class="form-check">
                <input class="form-check-input recipient-type-radio" type="radio"
                       name="<?= $postTypeName ?>" id="<?= $prefix ?>RecipientRoles"
                       value="roles" <?= $recipientType === 'roles' ? 'checked' : '' ?>
                       data-prefix="<?= $prefix ?>">
                <label class="form-check-label" for="<?= $prefix ?>RecipientRoles">
                    <strong>Theo vai trò</strong>
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input recipient-type-radio" type="radio"
                       name="<?= $postTypeName ?>" id="<?= $prefix ?>RecipientUsers"
                       value="users" <?= $recipientType === 'users' ? 'checked' : '' ?>
                       data-prefix="<?= $prefix ?>">
                <label class="form-check-label" for="<?= $prefix ?>RecipientUsers">
                    <strong>Theo người dùng</strong>
                </label>
            </div>
        </div>
    </div>

    <!-- Chọn vai trò -->
    <div class="mb-3" id="<?= $prefix ?>RolesSection">
        <div class="sub-section-title">👥 Chọn vai trò</div>
        <div class="row">
            <?php foreach ($roles as $role): ?>
                <div class="col-md-6 col-lg-4 mb-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="<?= $postRoleName ?>[]"
                               value="<?= $role->id ?>" id="<?= $prefix ?>_role_<?= $role->id ?>"
                               <?= in_array($role->id, $selectedRoles) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="<?= $prefix ?>_role_<?= $role->id ?>">
                            <?= e($role->name) ?>
                            <small class="text-muted">(<?= e($role->code) ?>)</small>
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Chọn người dùng -->
    <div class="mb-3" id="<?= $prefix ?>UsersSection" style="display: none;">
        <div class="sub-section-title">👤 Chọn người dùng</div>
        <div class="mb-2">
            <input type="text" id="<?= $prefix ?>UserSearchBox" class="form-control form-control-sm mb-2"
                   placeholder="Tìm theo tên, email hoặc phòng ban...">
            <select id="<?= $prefix ?>UsersSelectDropdown" class="form-select form-select-sm" size="4">
                <?php foreach ($users as $user): ?>
                    <?php if (!in_array($user->id, $selectedUserIds)): ?>
                    <option value="<?= $user->id ?>"
                            data-name="<?= e($user->full_name) ?>"
                            data-email="<?= e($user->email) ?>"
                            data-department="<?= e(strtolower($user->department_name ?? '')) ?>">
                        <?= e($user->full_name) ?> (<?= e($user->email) ?>)
                        <?php if (!empty($user->department_name)): ?> - <?= e($user->department_name) ?><?php endif; ?>
                    </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">💡 Click vào người dùng để thêm</small>
        </div>
        <div class="mb-2">
            <label class="form-label fw-bold small">
                Đã chọn: <span id="<?= $prefix ?>SelectedUsersCount" class="badge bg-primary"><?= count($selectedUserIds) ?></span>
            </label>
            <div id="<?= $prefix ?>SelectedUsersList" class="d-flex flex-wrap gap-2 p-2 border rounded"
                 style="min-height: 38px; max-height: 160px; overflow-y: auto; background: #f8f9fa;">
                <?php foreach ($users as $user):
                    if (in_array($user->id, $selectedUserIds)): ?>
                    <span class="badge bg-primary <?= $prefix ?>-selected-user-badge" data-user-id="<?= $user->id ?>"
                          data-name="<?= e($user->full_name) ?>" data-email="<?= e($user->email) ?>"
                          style="font-size:.82rem; padding:.4em .6em;">
                        <?= e($user->full_name) ?>
                        <small class="opacity-75">(<?= e($user->email) ?>)</small>
                        <button type="button" class="btn-close btn-close-white ms-1 <?= $prefix ?>-remove-user"
                                data-user-id="<?= $user->id ?>" style="font-size:.55rem;" title="Xóa"></button>
                    </span>
                <?php endif; endforeach; ?>
            </div>
            <div id="<?= $prefix ?>NoSelectedUsersMsg" class="text-muted fst-italic small mt-1 <?= !empty($selectedUserIds) ? 'd-none' : '' ?>">
                Chưa chọn người dùng nào
            </div>
        </div>
        <?php $hiddenName = $dbPrefix ? "{$dbPrefix}selected_users_json" : "selected_users_json"; ?>
        <input type="hidden" name="<?= $hiddenName ?>" id="<?= $prefix ?>SelectedUsersHidden" value="<?= implode(',', $selectedUserIds) ?>">
    </div>

    <!-- Email bổ sung + CC -->
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="sub-section-title">📧 Email bổ sung</div>
            <?php
                $emailWrapId = "{$prefix}EmailTagWrap";
                $emailInputId = "{$prefix}NewEmailInput";
                $emailHiddenName = $dbPrefix ? "{$dbPrefix}additional_emails" : "additional_emails";
                $emailHiddenId = "{$prefix}AdditionalEmailsHidden";
                $emailEmptyId = "{$prefix}NoEmailsMsg";
                $emailBadgeClass = "{$prefix}-email-badge";
                $emailRemoveClass = "{$prefix}-remove-email";
            ?>
            <div class="tag-input-wrap" id="<?= $emailWrapId ?>">
                <?php if (!empty($additionalEmails)): ?>
                    <?php foreach ($additionalEmails as $email): ?>
                        <span class="badge <?= $badgeColor ?> <?= $emailBadgeClass ?>" data-email="<?= e($email) ?>">
                            <?= e($email) ?>
                            <button type="button" class="btn-close btn-close-white ms-1 <?= $emailRemoveClass ?>"
                                    data-email="<?= e($email) ?>" style="font-size: 0.55rem;" title="Xóa"></button>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
                <input type="email" id="<?= $emailInputId ?>" placeholder="Nhập email, nhấn Enter">
            </div>
            <?php if (empty($additionalEmails)): ?>
                <span class="text-muted fst-italic small" id="<?= $emailEmptyId ?>">Chưa có email nào</span>
            <?php endif; ?>
            <input type="hidden" name="<?= $emailHiddenName ?>" id="<?= $emailHiddenId ?>" value="">
        </div>
        <div class="col-lg-6">
            <div class="sub-section-title">📋 Email CC</div>
            <?php
                $ccWrapId = "{$prefix}CcTagWrap";
                $ccInputId = "{$prefix}NewCcInput";
                $ccHiddenName = $dbPrefix ? "{$dbPrefix}cc" : "cc_emails";
                $ccHiddenId = "{$prefix}CcEmailsHidden";
                $ccEmptyId = "{$prefix}NoCcMsg";
                $ccBadgeClass = "{$prefix}-cc-badge";
                $ccRemoveClass = "{$prefix}-remove-cc";
            ?>
            <div class="tag-input-wrap" id="<?= $ccWrapId ?>">
                <?php if (!empty($ccEmails)): ?>
                    <?php foreach ($ccEmails as $email): ?>
                        <span class="badge bg-info text-dark <?= $ccBadgeClass ?>" data-email="<?= e($email) ?>">
                            <?= e($email) ?>
                            <button type="button" class="btn-close btn-close-white ms-1 <?= $ccRemoveClass ?>"
                                    data-email="<?= e($email) ?>" style="font-size: 0.55rem;" title="Xóa"></button>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
                <input type="email" id="<?= $ccInputId ?>" placeholder="Nhập email CC, nhấn Enter">
            </div>
            <?php if (empty($ccEmails)): ?>
                <span class="text-muted fst-italic small" id="<?= $ccEmptyId ?>">Chưa có CC nào</span>
            <?php endif; ?>
            <input type="hidden" name="<?= $ccHiddenName ?>" id="<?= $ccHiddenId ?>" value="">
        </div>
    </div>
<?php } ?>

<div class="container-fluid mt-4 mb-5">
    <div class="row mb-4">
        <div class="col">
            <h3><i class="fas fa-bell"></i> <?= $data['title'] ?></h3>
            <p class="text-muted mb-0">Cấu hình người nhận cho từng loại thông báo. Mỗi loại có thể bật/tắt và cấu hình độc lập.</p>
        </div>
    </div>

    <?php flash('msg'); ?>

    <form id="configForm" method="POST" action="<?= url('/admin') ?>/settings/save-daily-report-config">
        <?php csrf_field(); ?>
        <input type="hidden" name="is_active_fallback" value="0">

        <!-- ═══════════════ SECTION 1: BÁO CÁO CHẤM CÔNG HÀNG NGÀY ═══════════════ -->
        <div class="card notification-section mb-4">
            <div class="section-header d-flex align-items-center justify-content-between p-3 bg-white"
                 data-bs-toggle="collapse" data-bs-target="#section-daily">
                <div class="d-flex align-items-center gap-3">
                    <i class="fas fa-chevron-down toggle-icon text-muted"></i>
                    <div>
                        <h5 class="mb-0"><i class="fas fa-clipboard-list text-primary me-2"></i>Báo cáo chấm công hàng ngày</h5>
                        <small class="text-muted">Gửi email tổng hợp chấm công cho quản lý vào cuối ngày</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3" onclick="event.stopPropagation()">
                    <span class="section-status <?= $data['config']->is_active ? 'bg-success text-white' : 'bg-secondary text-white' ?>" id="dailyStatus">
                        <?= $data['config']->is_active ? '● Đang hoạt động' : '○ Tắt' ?>
                    </span>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                               value="1" <?= $data['config']->is_active ? 'checked' : '' ?>
                               onchange="updateStatusBadge(this, 'dailyStatus')">
                    </div>
                </div>
            </div>
            <div class="collapse show" id="section-daily">
                <div class="card-body border-top">
                    <?php renderRecipientBlock('daily', '', $data['config'], $data['roles'], $data['users'], 'bg-success'); ?>

                    <!-- Preview + Test -->
                    <div class="row g-3 mt-2">
                        <div class="col-lg-7">
                            <div class="sub-section-title"><i class="fas fa-eye text-primary me-1"></i> Xem trước danh sách</div>
                            <button type="button" class="btn btn-sm btn-outline-primary mb-2" id="btnPreview">
                                <i class="fas fa-sync-alt"></i> Tải danh sách
                            </button>
                            <span class="ms-2">Tổng: <span id="totalRecipients" class="badge bg-primary">0</span></span>
                            <div id="emailsList" class="border rounded p-2 mt-2"
                                 style="max-height: 200px; overflow-y: auto; min-height: 40px; background: #f8f9fa;">
                                <em class="text-muted">Nhấn "Tải danh sách" để xem</em>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="sub-section-title"><i class="fas fa-paper-plane text-warning me-1"></i> Gửi email test</div>
                            <div class="input-group">
                                <input type="email" class="form-control form-control-sm" id="testEmail" placeholder="your@email.com">
                                <button type="button" class="btn btn-sm btn-warning" id="btnSendTest">
                                    <i class="fas fa-paper-plane"></i> Gửi test
                                </button>
                            </div>
                            <small class="text-muted">Phụ thuộc cron job</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════ SECTION 2: CẢNH BÁO MCC LẠ ═══════════════ -->
        <div class="card notification-section mb-4">
            <div class="section-header d-flex align-items-center justify-content-between p-3 bg-white"
                 data-bs-toggle="collapse" data-bs-target="#section-mcc-unknown">
                <div class="d-flex align-items-center gap-3">
                    <i class="fas fa-chevron-down toggle-icon text-muted"></i>
                    <div>
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Cảnh báo MCC lạ</h5>
                        <small class="text-muted">Mã chấm công xuất hiện trên máy nhưng không khớp với nhân viên nào trong hệ thống</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3" onclick="event.stopPropagation()">
                    <span class="section-status <?= $data['config']->mcc_alerts_active ? 'bg-success text-white' : 'bg-secondary text-white' ?>" id="mccUnknownStatus">
                        <?= $data['config']->mcc_alerts_active ? '● Đang hoạt động' : '○ Tắt' ?>
                    </span>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="mcc_alerts_active" id="mccAlertsActive"
                               value="1" <?= $data['config']->mcc_alerts_active ? 'checked' : '' ?>
                               onchange="updateStatusBadge(this, 'mccUnknownStatus'); updateStatusBadge(this, 'mccMissingStatus')">
                    </div>
                </div>
            </div>
            <div class="collapse" id="section-mcc-unknown">
                <div class="card-body border-top">
                    <?php renderRecipientBlock('mccUnknown', 'mcc_unknown_', $data['config'], $data['roles'], $data['users'], 'bg-danger'); ?>
                </div>
            </div>
        </div>

        <!-- ═══════════════ SECTION 3: CẢNH BÁO THIẾU MCC ═══════════════ -->
        <div class="card notification-section mb-4">
            <div class="section-header d-flex align-items-center justify-content-between p-3 bg-white"
                 data-bs-toggle="collapse" data-bs-target="#section-mcc-missing">
                <div class="d-flex align-items-center gap-3">
                    <i class="fas fa-chevron-down toggle-icon text-muted"></i>
                    <div>
                        <h5 class="mb-0"><i class="fas fa-user-slash text-warning me-2"></i>Cảnh báo Thiếu MCC</h5>
                        <small class="text-muted">Nhân viên đang hoạt động nhưng chưa được gán mã chấm công (timekeeper_id)</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3" onclick="event.stopPropagation()">
                    <span class="section-status <?= $data['config']->mcc_alerts_active ? 'bg-success text-white' : 'bg-secondary text-white' ?>" id="mccMissingStatus">
                        <?= $data['config']->mcc_alerts_active ? '● Đang hoạt động' : '○ Tắt' ?>
                    </span>
                </div>
            </div>
            <div class="collapse" id="section-mcc-missing">
                <div class="card-body border-top">
                    <div class="alert alert-warning py-2 mb-3">
                        <i class="fas fa-link me-1"></i>
                        <strong>Lưu ý:</strong> MCC lạ và Thiếu MCC dùng chung toggle kích hoạt ở phần "Cảnh báo MCC lạ" phía trên.
                    </div>
                    <?php renderRecipientBlock('mccMissing', 'mcc_missing_', $data['config'], $data['roles'], $data['users'], 'bg-warning'); ?>
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="card notification-section p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div class="text-muted small">
                    <i class="fas fa-info-circle me-1"></i> Tất cả thay đổi sẽ được lưu cùng lúc khi nhấn "Lưu cấu hình"
                </div>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-1"></i> Lưu cấu hình
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Result Modal -->
<div class="modal fade" id="resultModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" id="resultModalHeader">
                <h5 class="modal-title" id="resultModalTitle">Kết quả</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div id="resultIcon" class="mb-3" style="font-size: 4rem;"></div>
                <h5 id="resultMessage" class="mb-2"></h5>
                <p id="resultDetail" class="text-muted mb-0"></p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="btnResultClose">Đóng</button>
                <button type="button" class="btn btn-primary d-none" id="btnResultReload">
                    <i class="fas fa-sync-alt"></i> Tải lại trang
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const URLROOT = "<?= url() ?>";
const CSRF_TOKEN = "<?= csrf_token() ?>";

function updateStatusBadge(checkbox, badgeId) {
    var badge = document.getElementById(badgeId);
    if (!badge) return;
    badge.className = 'section-status ' + (checkbox.checked ? 'bg-success text-white' : 'bg-secondary text-white');
    badge.textContent = checkbox.checked ? '● Đang hoạt động' : '○ Tắt';
}

// Collapse chevron rotation
document.querySelectorAll('.section-header[data-bs-toggle="collapse"]').forEach(function(header) {
    var collapseEl = document.querySelector(header.getAttribute('data-bs-target'));
    if (collapseEl) {
        collapseEl.addEventListener('hide.bs.collapse', function() { header.classList.add('collapsed'); });
        collapseEl.addEventListener('show.bs.collapse', function() { header.classList.remove('collapsed'); });
        if (!collapseEl.classList.contains('show')) header.classList.add('collapsed');
    }
});
</script>
<script src="<?= asset_v('js/modules/core/daily_report_recipients.js') ?>"></script>

<?php require APP_VIEWS_PATH . '/layouts/footer.php'; ?>
