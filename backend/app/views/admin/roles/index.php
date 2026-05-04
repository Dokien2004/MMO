<div class="page-header">
    <div>
        <h2><i class="fas fa-user-tag me-2"></i> Quản lý Vai trò (Roles)</h2>
        <p>Định nghĩa các nhóm người dùng và cập nhật danh sách quyền hạn hệ thống.</p>
    </div>
    
    <div class="btn-group">
        <?php // Check quyền nếu cần: if(hasPermission('role.manage')): ?>
            <form id="syncForm" action="<?= url('/admin') ?>/roles/sync" method="POST" style="display: inline;">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <button type="button" class="btn btn-ghost" onclick="confirmSync()">
                    <i class="fas fa-sync-alt me-1"></i> Đồng bộ Quyền
                </button>
            </form>

            <button type="button" class="btn btn-primary fw-600" onclick="openModal('addRoleModal')">
                <i class="fas fa-plus me-1"></i> Thêm Vai trò
            </button>
        <?php // endif; ?>
    </div>
</div>

<?php if(function_exists('flash')) flash('role_msg'); ?>

<div class="card">
    <div class="card-title">📋 Danh sách Vai trò</div>
    
    <?php if(empty($roles)): ?>
        <div class="empty-state">
            <i class="fas fa-user-tag fa-3x mb-16" style="opacity: 0.25;"></i><br>
            <h5 class="fw-normal">Chưa có vai trò nào được định nghĩa.</h5>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th width="60">ID</th>
                        <th width="250">Tên Vai trò</th>
                        <th>Mô tả</th>
                        <th width="150" class="text-center">Trạng thái</th>
                        <th width="180" style="text-align: right;">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($roles as $role): ?>
                    <tr class="<?php echo ($role['id'] == 1) ? 'current-site-row' : ''; ?>" title="Role Code: <?php echo htmlspecialchars($role['code'] ?? ''); ?>">
                        <td class="text-muted text-sm">#<?php echo $role['id']; ?></td>
                        
                        <td>
                            <div class="fw-600 text"><?php echo htmlspecialchars($role['name']); ?></div>
                            <?php if($role['id'] == 1): ?>
                                <span class="badge badge-error mt-8">SYSTEM ADMIN</span>
                            <?php endif; ?>
                            <div class="sub mt-8 font-monospace">CODE: <?php echo htmlspecialchars($role['code'] ?? ''); ?></div>
                        </td>
                        
                        <td class="text-muted text-sm">
                            <?php echo empty($role['description']) ? '<span class="fst-italic">- Chưa có mô tả -</span>' : htmlspecialchars($role['description']); ?>
                        </td>
                        
                        <td class="text-center">
                            <span class="badge badge-active">Active</span>
                        </td>
                        
                        <td style="text-align: right;">
                            <?php // if(hasPermission('role.manage')): ?>
                                <div class="btn-group" style="justify-content: flex-end;">
                                    <a href="<?= url('/admin') ?>/roles/permissions/<?php echo $role['id']; ?>" 
                                       class="btn btn-sm btn-ghost" title="Phân quyền chi tiết">
                                        <i class="fas fa-key" style="color: #0ea5e9;"></i>
                                    </a>

                                    <button type="button" class="btn btn-sm btn-ghost" 
                                            onclick="openEditRoleModal('<?php echo $role['id']; ?>', '<?php echo htmlspecialchars(addslashes($role['name'])); ?>', '<?php echo htmlspecialchars(addslashes($role['description'] ?? '')); ?>')"
                                            title="Sửa thông tin">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <?php if($role['id'] != 1): ?>
                                    <button type="button" class="btn btn-sm btn-danger-sm" 
                                            onclick="openDeleteRoleModal('<?php echo $role['id']; ?>', '<?php echo htmlspecialchars(addslashes($role['name'])); ?>')"
                                            title="Xóa vai trò">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            <?php // endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 16px;">
        <?php $pagination = $pagination ?? ($data['pagination'] ?? null); $entityLabel = 'vai trò'; require APP_VIEWS_PATH . '/layouts/_pagination.php'; ?>
    </div>
</div>

<!-- Modal Thêm Vai trò -->
<div class="modal-overlay" id="addRoleModal">
    <div class="modal-box">
        <h3 class="mb-24"><i class="fas fa-plus-circle me-2 text-primary"></i> Thêm Vai trò Mới</h3>
        
        <form action="<?= url('/admin') ?>/roles/add" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            
            <div class="form-group">
                <label>Tên Vai trò <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" 
                       value="<?php echo (isset($validation_error_for) && $validation_error_for == 'add') ? ($name ?? '') : ''; ?>" 
                       placeholder="VD: Kho Trưởng, Kế Toán..." required>
                <?php if(!empty($name_err) && isset($validation_error_for) && $validation_error_for == 'add'): ?>
                    <div class="text-danger mt-8 text-sm"><?php echo $name_err; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label>Mô tả</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Mô tả trách nhiệm của vai trò này..."><?php echo (isset($validation_error_for) && $validation_error_for == 'add') ? ($description ?? '') : ''; ?></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeModal('addRoleModal')">Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu dữ liệu</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Sửa Vai trò -->
<div class="modal-overlay" id="editRoleModal">
    <div class="modal-box">
        <h3 class="mb-24"><i class="fas fa-edit me-2" style="color: #0ea5e9;"></i> Cập nhật Vai trò</h3>
        
        <form id="editRoleForm" action="" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            
            <div class="form-group">
                <label>Tên Vai trò <span class="text-danger">*</span></label>
                <input type="text" name="name" id="editName" class="form-control" 
                       value="<?php echo (isset($validation_error_for) && $validation_error_for == 'edit') ? ($name ?? '') : ''; ?>" required>
                <?php if(!empty($name_err) && isset($validation_error_for) && $validation_error_for == 'edit'): ?>
                    <div class="text-danger mt-8 text-sm"><?php echo $name_err; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label>Mô tả</label>
                <textarea name="description" id="editDesc" class="form-control" rows="3"><?php echo (isset($validation_error_for) && $validation_error_for == 'edit') ? ($description ?? '') : ''; ?></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeModal('editRoleModal')">Hủy</button>
                <button type="submit" class="btn btn-primary" style="background: #0ea5e9; border-color: #0ea5e9;">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Xóa Vai trò -->
<div class="modal-overlay" id="deleteRoleModal">
    <div class="modal-box text-center">
        <div class="mb-24 text-danger" style="font-size: 40px; opacity: 0.75;"><i class="fas fa-trash-alt"></i></div>
        <h3>Xóa Vai trò?</h3>
        <p class="text-muted mb-8">Bạn có chắc chắn muốn xóa:</p>
        <h4 id="delRoleName" class="fw-bold text-dark mb-16">...</h4>
        <div class="alert alert-warning text-sm text-start mb-24" style="background: rgba(251, 191, 36, 0.1); border: 1px solid rgba(251, 191, 36, 0.2); padding: 12px; border-radius: 8px;">
            <i class="fas fa-exclamation-triangle me-1" style="color: #fbbf24;"></i> 
            <span style="color: #d97706;">Các nhân viên đang giữ vai trò này sẽ bị mất quyền hạn tương ứng.</span>
        </div>
        
        <form id="deleteRoleForm" action="" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            <div class="modal-actions" style="justify-content: center;">
                <button type="button" class="btn btn-cancel" onclick="closeModal('deleteRoleModal')">Hủy</button>
                <button type="submit" class="btn btn-danger">Đồng ý Xóa</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function openModal(id) {
        document.getElementById(id).classList.add('active');
    }
    
    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    function openEditRoleModal(id, name, desc) {
        document.getElementById('editName').value = name;
        document.getElementById('editDesc').value = desc;
        document.getElementById('editRoleForm').action = '<?= url('/admin') ?>/roles/edit/' + id;
        openModal('editRoleModal');
    }

    function openDeleteRoleModal(id, name) {
        document.getElementById('delRoleName').textContent = name;
        document.getElementById('deleteRoleForm').action = '<?= url('/admin') ?>/roles/delete/' + id;
        openModal('deleteRoleModal');
    }

    function confirmSync() {
        Swal.fire({
            title: 'Đồng bộ Quyền hạn?',
            text: "Hệ thống sẽ quét file cấu hình và cập nhật danh sách quyền vào Database. Hành động này không ảnh hưởng đến quyền đã gán.",
            icon: 'question',
            showCancelButton: true,
            background: 'var(--card-bg)',
            color: 'var(--text)',
            confirmButtonColor: 'var(--primary)',
            cancelButtonColor: 'var(--text-muted)',
            confirmButtonText: 'Đồng ý',
            cancelButtonText: 'Hủy'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('syncForm').submit();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Tự động mở Modal nếu có lỗi validation từ Server
        <?php if (isset($validation_error_for)): ?>
            <?php if ($validation_error_for === 'add'): ?>
                openModal('addRoleModal');
            <?php elseif ($validation_error_for === 'edit' && isset($error_id)): ?>
                var form = document.getElementById('editRoleForm');
                if (form) {
                    form.action = '<?= url('/admin') ?>/roles/edit/<?php echo $error_id; ?>';
                }
                openModal('editRoleModal');
            <?php endif; ?>
        <?php endif; ?>
    });
</script>
