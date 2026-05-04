<?php 
// --- HELPER FUNCTIONS: XỬ LÝ CÂY PHÂN CẤP CHO BỘ LỌC ---
$deptTree = [];
if (!empty($data['departments'])) {
    foreach ($data['departments'] as $d) {
        $pid = $d['parent_id'] ? $d['parent_id'] : 0;
        $deptTree[$pid][] = $d;
    }
}

function renderDeptFilterOptions($tree, $parentId = 0, $level = 0, $selectedId = null) {
    if (!isset($tree[$parentId])) return;
    foreach ($tree[$parentId] as $d) {
        $prefix = str_repeat('— ', $level);
        $selected = ($selectedId == $d['id']) ? 'selected' : '';
        echo '<option value="'.$d['id'].'" '.$selected.'>'.$prefix.htmlspecialchars($d['name']).'</option>';
        renderDeptFilterOptions($tree, $d['id'], $level + 1, $selectedId);
    }
}
?>

<div class="page-header">
    <div>
        <h2><i class="fas fa-users-cog me-2"></i>Quản lý Users</h2>
        <p>Danh sách tài khoản và phân quyền hệ thống</p>
    </div>
    <div class="btn-group">
        <a href="<?= url('/admin') ?>/roles" class="btn btn-ghost">
            <i class="fas fa-shield-alt me-1"></i> Phân quyền
        </a>
        <a href="<?= url('/admin') ?>/users/add" class="btn btn-primary fw-600">
            <i class="fas fa-plus me-1"></i> Thêm mới
        </a>
    </div>
</div>

<?php if(function_exists('flash')) flash('user_message'); ?>

<div class="card mb-24">
    <form id="filterForm" action="<?= url('/admin') ?>/users" method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
        
        <div style="flex: 1; min-width: 200px;">
            <div class="form-group mb-0">
                <input type="text" name="search" class="form-control" 
                       placeholder="Tìm tên, user, email..." 
                       value="<?php echo htmlspecialchars(($data['filters']['search'] ?? '')); ?>">
            </div>
        </div>

        <div style="flex: 1; min-width: 150px;">
            <div class="form-group mb-0">
                <select name="site_id" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Tất cả Nhà máy --</option>
                    <?php foreach($data['sites'] as $site): ?>
                        <option value="<?php echo $site['id']; ?>" <?php echo (($data['filters']['site_id'] ?? '') == $site['id']) ? 'selected' : ''; ?>>
                            <?php echo $site['code'] . ' - ' . $site['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="flex: 1; min-width: 150px;">
            <div class="form-group mb-0">
                <select name="department_id" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Tất cả Phòng ban --</option>
                    <?php renderDeptFilterOptions($deptTree, 0, 0, ($data['filters']['department_id'] ?? '')); ?>
                </select>
            </div>
        </div>

        <div style="flex: 1; min-width: 120px;">
            <div class="form-group mb-0">
                <select name="role_id" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Vai trò --</option>
                    <?php foreach($data['roles'] as $role): ?>
                        <option value="<?php echo $role['id']; ?>" <?php echo (($data['filters']['role_id'] ?? '') == $role['id']) ? 'selected' : ''; ?>>
                            <?php echo $role['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div style="flex: 1; min-width: 150px;">
            <div class="form-group mb-0">
                <select name="status" class="form-control <?php echo (($data['filters']['status'] ?? '') == 'deleted') ? 'text-danger' : 'text-success'; ?>" onchange="this.form.submit()">
                    <option value="" <?php echo (($data['filters']['status'] ?? '') == '') ? 'selected' : ''; ?>>Đang hoạt động</option>
                    <option value="deleted" <?php echo (($data['filters']['status'] ?? '') == 'deleted') ? 'selected' : ''; ?>>Đã xóa (Thùng rác)</option>
                </select>
            </div>
        </div>

        <div class="btn-group mb-0">
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i></button>
            <a href="<?= url('/admin') ?>/users" class="btn btn-ghost" title="Xóa bộ lọc"><i class="fas fa-undo"></i></a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-title">📋 Danh sách người dùng</div>
    
    <?php if(empty($users)): ?>
        <div class="empty-state">
            <i class="fas fa-folder-open fa-3x mb-16" style="opacity: 0.25;"></i>
            <h5 class="fw-normal">Không tìm thấy dữ liệu</h5>
            <p class="text-sm">Thử thay đổi bộ lọc hoặc thêm user mới.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th width="280">Nhân viên</th>
                        <th width="200">Liên hệ & Vai trò</th>
                        <th width="150">Phòng ban</th>
                        <th width="250">Quyền truy cập (Sites)</th>
                        <th width="100" class="text-center">Trạng thái</th>
                        <th width="80" style="text-align: right;">#</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                        <tr>
                            <td>
                                <div class="flex items-center gap-8">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-600 text"><?php echo e($user['full_name']); ?></div>
                                        <div class="mono">@<?php echo e($user['username']); ?></div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="flex" style="flex-direction: column;">
                                    <span class="sub truncate" title="<?php echo e($user['email']); ?>">
                                        <i class="far fa-envelope me-1"></i> <?php echo e($user['email']); ?>
                                    </span>
                                    <div class="mt-8">
                                        <span class="role-badge role-operator">
                                            <i class="fas fa-user-shield me-1"></i> <?php echo e($user['role_name']); ?>
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <?php if(!empty($user['department_name'])): ?>
                                    <span class="text-sm"><i class="far fa-building text-muted me-1"></i> <?php echo e($user['department_name']); ?></span>
                                <?php else: ?>
                                    <span class="sub fst-italic">- N/A -</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="mb-8">
                                    <span class="badge badge-linked" title="Site Mặc định">
                                        <i class="fas fa-star me-1" style="color: #fbbf24;"></i> <?php echo $user['site_code']; ?>
                                    </span>
                                </div>
                                
                                <?php 
                                    $sites = !empty($user['all_site_codes']) ? explode(', ', $user['all_site_codes']) : [];
                                    $otherSites = array_filter($sites, function($s) use ($user) {
                                        return trim($s) !== $user['site_code'];
                                    });
                                    $count = 0; $limit = 4;
                                ?>
                                
                                <?php if(!empty($otherSites)): ?>
                                    <div class="flex flex-wrap gap-8">
                                        <?php foreach($otherSites as $siteCode): ?>
                                            <?php if($count < $limit): ?>
                                                <span class="site-pill"><?php echo $siteCode; ?></span>
                                            <?php endif; ?>
                                            <?php $count++; ?>
                                        <?php endforeach; ?>
                                        
                                        <?php if($count > $limit): ?>
                                            <span class="sub" style="border-bottom: 1px dotted var(--text-muted); cursor: help;" data-bs-toggle="tooltip" title="<?php echo implode(', ', array_slice($otherSites, $limit)); ?>">
                                                +<?php echo $count - $limit; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <?php if($user['is_active']): ?>
                                    <span class="badge badge-active">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-error">Locked</span>
                                <?php endif; ?>
                            </td>

                            <td style="text-align: right;">
                                <?php if(($data['filters']['status'] ?? '') == 'deleted'): ?>
                                    <form action="<?= url('/admin') ?>/users/restore/<?php echo $user['id']; ?>" method="POST" style="display:inline-block;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $data['csrf_token']; ?>">
                                        <button type="submit" class="btn btn-success-sm" title="Khôi phục lại">
                                            <i class="fas fa-trash-restore"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="btn-group" style="justify-content: flex-end;">
                                        <a href="<?= url('/admin') ?>/users/edit/<?php echo $user['id']; ?>" class="btn btn-sm btn-ghost" title="Sửa thông tin">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <?php if($user['id'] != $_SESSION['user_id']): ?>
                                            <button class="btn btn-sm btn-danger-sm" 
                                                    onclick="openDeleteUserModal('<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['full_name']); ?>')" title="Xóa tài khoản">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 16px;">
        <?php $pagination = $data['pagination'] ?? null; $entityLabel = 'người dùng'; require APP_VIEWS_PATH . '/layouts/_pagination.php'; ?>
    </div>
</div>

<div class="modal-overlay" id="deleteUserModal">
    <div class="modal-box text-center">
        <div class="mb-24 text-danger" style="font-size: 40px; opacity: 0.75;">
            <i class="fas fa-user-times"></i>
        </div>
        <h3>Xác nhận xóa</h3>
        <p class="text-muted">Bạn có chắc chắn muốn xóa:</p>
        <h4 class="fw-bold text text-uppercase mb-24" id="modalUserName">...</h4>
        <small class="text-muted d-block mb-24">Dữ liệu sẽ bị ẩn khỏi hệ thống.</small>
        
        <form id="deleteUserForm" action="" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $data['csrf_token'] ?? ($_SESSION['csrf_token'] ?? ''); ?>">
            <div class="modal-actions" style="justify-content: center;">
                <button type="button" class="btn btn-cancel" onclick="closeDeleteUserModal()">Hủy</button>
                <button type="submit" class="btn btn-danger">Đồng ý Xóa</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof bootstrap !== 'undefined') {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    });

    function openDeleteUserModal(id, name) {
        document.getElementById('modalUserName').textContent = name;
        document.getElementById('deleteUserForm').action = '<?= url('/admin') ?>/users/delete/' + id;
        document.getElementById('deleteUserModal').classList.add('active');
    }

    function closeDeleteUserModal() {
        document.getElementById('deleteUserModal').classList.remove('active');
    }
</script>
