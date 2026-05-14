<div class="page-header">
    <div>
        <h2><i class="fas fa-building me-2"></i> Danh sách Nhà máy & Chi nhánh</h2>
        <p>Quản lý cấu trúc đa điểm của doanh nghiệp</p>
    </div>
    <a href="<?= url('/admin') ?>/sites/add" class="btn btn-primary fw-600 px-3">
        <i class="fas fa-plus me-1"></i> Thêm mới
    </a>
</div>

<?php if(function_exists('flash')) flash('site_msg'); ?>

<div class="card">
    <div class="card-title">Quản lý Sites</div>
    <?php if(empty($sites)): ?>
        <div class="empty-state">
            <i class="fas fa-city fa-3x mb-16" style="opacity: 0.25;"></i><br>
            <p>Chưa có dữ liệu Site nào.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th width="50">ID</th>
                        <th width="150">Mã Site</th>
                        <th width="250">Tên hiển thị</th>
                        <th>Địa chỉ</th>
                        <th width="120" class="text-center">Trạng thái</th>
                        <th width="150" style="text-align: right;">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($sites as $site): ?>
                        <tr class="<?php echo (isset($_SESSION['site_id']) && $_SESSION['site_id'] == $site['id']) ? 'current-site-row' : ''; ?>">
                            
                            <td class="text-muted text-sm">#<?php echo $site['id']; ?></td>
                            
                            <td>
                                <div class="admin-code fw-600"><?php echo $site['code']; ?></div>
                                <?php if(isset($site['is_master']) && $site['is_master']): ?>
                                    <span class="badge badge-warning mt-8">MASTER</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="fw-600 text flex items-center gap-8">
                                    <?php echo $site['name']; ?>
                                    <?php if(isset($_SESSION['site_id']) && $_SESSION['site_id'] == $site['id']): ?>
                                        <span class="site-pill">CURRENT</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if(!empty($site['parent_name'])): ?>
                                    <div class="sub mt-8">
                                        <i class="fas fa-level-up-alt fa-rotate-90 me-1"></i> 
                                        Trực thuộc: <strong class="text-sec"><?php echo $site['parent_name']; ?></strong>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="text-muted text-sm">
                                <?php echo !empty($site['address']) ? '<i class="fas fa-map-marker-alt text-danger me-1"></i>' . $site['address'] : '<span class="fst-italic">- Chưa cập nhật -</span>'; ?>
                            </td>
                            
                            <td class="text-center">
                                <?php if($site['is_active']): ?>
                                    <span class="badge badge-active">Hoạt động</span>
                                <?php else: ?>
                                    <span class="badge badge-none">Tạm khóa</span>
                                <?php endif; ?>
                            </td>

                            <td style="text-align: right;">
                                <div class="btn-group" style="justify-content: flex-end;">
                                    <?php if($site['is_active'] && isset($_SESSION['site_id']) && $_SESSION['site_id'] != $site['id']): ?>
                                    <a href="<?= url('/admin') ?>/sites/change/<?php echo $site['id']; ?>" 
                                       class="btn btn-sm btn-ghost" 
                                       data-bs-toggle="tooltip" title="Chuyển làm việc tại Site này">
                                       <i class="fas fa-exchange-alt"></i>
                                    </a>
                                    <?php endif; ?>

                                    <a href="<?= url('/admin') ?>/sites/edit/<?php echo $site['id']; ?>" 
                                       class="btn btn-sm btn-ghost mx-1" title="Chỉnh sửa">
                                       <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <button class="btn btn-sm btn-danger-sm" 
                                            onclick="openDeleteModal('<?php echo $site['id']; ?>', '<?php echo $site['code']; ?>')" title="Xóa">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="padding-top: 16px; border-top: 1px solid var(--border); margin-top: 16px;">
            <small class="text-muted">Tổng cộng: <strong><?php echo count($sites); ?></strong> chi nhánh/nhà máy.</small>
        </div>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="deleteSiteModal">
    <div class="modal-box" style="text-align: center;">
        <div class="mb-24 text-warning" style="font-size: 40px; opacity: 0.75;"><i class="fas fa-exclamation-triangle"></i></div>
        <h3>Xác nhận xóa Site?</h3>
        <p class="text-muted mb-24">Bạn có chắc chắn muốn xóa site <strong id="delSiteCode" class="text"></strong> không? Dữ liệu liên quan có thể bị ảnh hưởng.</p>
        
        <form id="deleteSiteForm" action="" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $data['csrf_token'] ?? ($_SESSION['csrf_token'] ?? ''); ?>">
            
            <div class="modal-actions" style="justify-content: center;">
                <button type="button" class="btn btn-cancel" onclick="closeDeleteModal()">Hủy bỏ</button>
                <button type="submit" class="btn btn-danger">Xóa dữ liệu</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Tooltip activation
    if (typeof bootstrap !== 'undefined') {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    function openDeleteModal(id, code) {
        document.getElementById('delSiteCode').textContent = code;
        document.getElementById('deleteSiteForm').action = '<?= url('/admin') ?>/sites/delete/' + id;
        document.getElementById('deleteSiteModal').classList.add('active');
    }

    function closeDeleteModal() {
        document.getElementById('deleteSiteModal').classList.remove('active');
    }
</script>
