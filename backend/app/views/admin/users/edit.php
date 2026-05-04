
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container-fluid px-4 mt-4">
    
    <form id="editUserForm" action="<?= url('/admin') ?>/users/edit/<?php echo $data['id']; ?>" method="POST">
        
        <input type="hidden" name="csrf_token" value="<?php echo $data['csrf_token'] ?? ''; ?>">

        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
            <div class="d-flex align-items-center">
                <a href="<?= url('/admin') ?>/users" class="btn btn-light btn-sm me-3 shadow-sm rounded-circle border" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;" title="Quay lại danh sách">
                    <i class="fas fa-arrow-left text-secondary"></i>
                </a>
                <div>
                    <h4 class="mb-0 text-primary fw-bold">Cập nhật user</h4>
                    <small class="text-muted font-monospace">ID: #<?php echo $data['id']; ?> &bull; @<?php echo htmlspecialchars($data['username']); ?></small>
                </div>
            </div>
            
            <div>
                <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                    <i class="fas fa-save me-2"></i> Lưu thay đổi
                </button>
            </div>
        </div>

        <?php if(function_exists('flash')) flash('user_message'); ?>

        <?php if(!empty($data['email_err']) || !empty($data['password_err']) || !empty($data['full_name_err'])): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm border-start border-danger border-4" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle me-2 fa-lg text-danger"></i> 
                    <div><strong>Có lỗi xảy ra!</strong> Vui lòng kiểm tra lại thông tin nhập liệu bên dưới.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            
            <div class="col-lg-7">
                <div class="card shadow-sm border-0 mb-4 h-100">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="m-0 fw-bold text-dark"><i class="far fa-id-card me-2 text-primary"></i>Thông tin chung</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <!-- [ORACLE UPGRADE] Chọn nhân viên có sẵn -->
                            <div class="col-12">
                                <label class="form-label fw-bold text-primary">Liên kết Nhân viên</label>
                                <select name="employee_id" id="select_employee" class="form-select border-primary shadow-sm">
                                    <?php if(!empty($data['employee_id'])): ?>
                                        <option value="<?php echo $data['employee_id']; ?>" selected>
                                            <?php echo htmlspecialchars($data['employee_name']); ?>
                                        </option>
                                    <?php endif; ?>
                                    <!-- Options khác sẽ được load qua AJAX -->
                                </select>
                                <div class="form-text small">Thay đổi nhân viên liên kết với tài khoản này.</div>
                            </div>
                            <div class="col-12"><hr class="my-2 opacity-25"></div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Họ và tên <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control <?php echo (!empty($data['full_name_err'])) ? 'is-invalid' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($data['full_name']); ?>">
                                <div class="invalid-feedback"><?php echo $data['full_name_err']; ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" id="input_email" class="form-control <?php echo (!empty($data['email_err'])) ? 'is-invalid' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($data['email']); ?>">
                                <div class="invalid-feedback"><?php echo $data['email_err']; ?></div>
                            </div>
                            
                            <div class="col-12">
                                <div class="p-3 bg-light rounded border d-flex align-items-center mt-2">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?php echo ($data['is_active'] == 1) ? 'checked' : ''; ?> style="width: 3em; height: 1.5em; margin-top: 0;">
                                    </div>
                                    <div class="ms-3">
                                        <label class="form-check-label fw-bold text-dark cursor-pointer d-block" for="is_active">Trạng thái Hoạt động (Active)</label>
                                        <div class="small text-muted" style="line-height: 1.2;">Nếu tắt, user này sẽ bị khóa truy cập ngay lập tức.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <hr class="text-muted my-3 opacity-25">
                            </div>

                            <div class="col-12">
                                <h6 class="fw-bold text-warning mb-3"><i class="fas fa-key me-2"></i>Đổi mật khẩu (Tùy chọn)</h6>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small text-muted">Mật khẩu mới</label>
                                <input type="password" name="password" class="form-control form-control-sm <?php echo (!empty($data['password_err'])) ? 'is-invalid' : ''; ?>" placeholder="Để trống nếu không đổi...">
                                <div class="invalid-feedback"><?php echo $data['password_err']; ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted">Xác nhận mật khẩu</label>
                                <input type="password" name="confirm_password" class="form-control form-control-sm <?php echo (!empty($data['confirm_password_err'])) ? 'is-invalid' : ''; ?>" placeholder="Nhập lại mật khẩu...">
                                <div class="invalid-feedback"><?php echo $data['confirm_password_err']; ?></div>
                            </div>
                            <div class="col-12">
                                <small class="text-muted fst-italic"><i class="fas fa-info-circle me-1"></i> Chỉ nhập vào ô trên nếu bạn muốn reset mật khẩu cho nhân viên này.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                
                <div class="card shadow-sm border-0 mb-4 h-100">
                    <div class="card-header bg-primary text-white py-3">
                        <h6 class="m-0 fw-bold"><i class="fas fa-sitemap me-2"></i>Tổ chức & Site</h6>
                    </div>
                    <div class="card-body p-4 bg-light">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Chi nhánh / Nhà máy Chính <span class="text-danger">*</span></label>
                            <select name="default_site_id" id="select_site_id" class="form-select border-primary shadow-sm">
                                <?php foreach($data['sites'] as $site): ?>
                                    <option value="<?php echo $site->id; ?>" <?php echo ($data['default_site_id'] == $site->id) ? 'selected' : ''; ?>>
                                        <?php echo $site->code . ' - ' . $site->name; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small">Đây là Site mặc định khi User đăng nhập.</div>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Phòng ban</label>
                                <select name="department_id" id="select_department_id" class="form-select form-select-sm">
                                    <option value="">-- Chọn --</option>
                                    <?php foreach($data['departments'] as $dept): ?>
                                        <option value="<?php echo $dept->id; ?>" <?php echo ($data['department_id'] == $dept->id) ? 'selected' : ''; ?>>
                                            <?php echo $dept->name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Vai trò</label>
                                <select name="role_id" class="form-select form-select-sm fw-bold text-primary">
                                    <?php foreach($data['roles'] as $role): ?>
                                        <option value="<?php echo $role->id; ?>" <?php echo ($data['role_id'] == $role->id) ? 'selected' : ''; ?>>
                                            <?php echo $role->name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="form-label fw-bold border-bottom w-100 pb-1 mb-2 d-flex justify-content-between">
                                <span>Quyền truy cập Site khác</span>
                                <span class="badge bg-secondary rounded-pill">Multi-site</span>
                            </label>
                            <div class="bg-white border rounded p-2 custom-scrollbar" style="max-height: 150px; overflow-y: auto;">
                                <?php foreach($data['sites'] as $site): ?>
                                    <div class="form-check mb-1">
                                        <input class="form-check-input site-access-checkbox" type="checkbox" name="access_sites[]" 
                                               value="<?php echo $site->id; ?>" id="access_site_<?php echo $site->id; ?>"
                                               <?php echo (in_array($site->id, $data['access_sites'] ?? [])) ? 'checked' : ''; ?>>
                                        <label class="form-check-label small user-select-none w-100 cursor-pointer" for="access_site_<?php echo $site->id; ?>">
                                            <strong><?php echo $site->code; ?></strong> - <?php echo $site->name; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="form-label fw-bold border-bottom w-100 pb-1 mb-2 d-flex justify-content-between">
                                <span>Quyền truy cập Kho</span>
                                <span class="badge bg-secondary rounded-pill">Inventory</span>
                            </label>
                            <div class="bg-white border rounded p-0 custom-scrollbar" style="max-height: 250px; overflow-y: auto;">
                                <?php if(empty($data['all_warehouses'])): ?>
                                    <div class="text-center p-4 text-muted">
                                        <small>Chưa có kho nào.</small>
                                    </div>
                                <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php 
                                        $currentSiteName = '';
                                        foreach($data['all_warehouses'] as $wh): 
                                            if($currentSiteName != $wh->site_name):
                                                $currentSiteName = $wh->site_name;
                                        ?>
                                                <li class="list-group-item bg-light fw-bold text-uppercase small text-primary ps-3 sticky-top border-top shadow-sm" style="z-index: 5;">
                                                    <i class="fas fa-industry me-1"></i> <?php echo $wh->site_name; ?>
                                                </li>
                                        <?php endif; ?>
                                        
                                        <li class="list-group-item ps-4 py-2 list-group-item-action border-0">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="warehouses[]" 
                                                       value="<?php echo $wh->id; ?>" id="wh_<?php echo $wh->id; ?>"
                                                       <?php echo (in_array($wh->id, $data['user_warehouses'] ?? [])) ? 'checked' : ''; ?>>
                                                <label class="form-check-label w-100 cursor-pointer small" for="wh_<?php echo $wh->id; ?>">
                                                    <?php echo $wh->name; ?> 
                                                    <span class="badge bg-light text-secondary border ms-1"><?php echo $wh->code; ?></span>
                                                </label>
                                            </div>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const siteSelect = document.getElementById('select_site_id');
        const deptSelect = document.getElementById('select_department_id');
        const form = document.getElementById('editUserForm');
        const BASE_URL = "<?= url() ?>";

        // 1. XỬ LÝ SUBMIT FORM VỚI SWEETALERT2
        form.addEventListener('submit', function(e) {
            e.preventDefault(); 

            Swal.fire({
                title: 'Xác nhận cập nhật?',
                text: "Bạn có chắc chắn muốn lưu các thay đổi này?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-check me-1"></i> Đồng ý lưu',
                cancelButtonText: 'Hủy bỏ'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit(); 
                }
            });
        });

        // 2. XỬ LÝ KHI ĐỔI SITE
        siteSelect.addEventListener('change', function() {
            const siteId = this.value;
            
            const accessCheckbox = document.getElementById('access_site_' + siteId);
            if(accessCheckbox) accessCheckbox.checked = true;

            deptSelect.innerHTML = '<option value="">Đang tải dữ liệu...</option>';
            deptSelect.disabled = true;

            fetch(`${BASE_URL}/core/users/ajax_get_departments/${siteId}`)
                .then(response => {
                    if (!response.ok) throw new Error('Lỗi kết nối Server');
                    return response.json();
                })
                .then(data => {
                    deptSelect.innerHTML = '<option value="">-- Chọn Phòng ban --</option>';
                    if (Array.isArray(data) && data.length > 0) {
                        data.forEach(dept => {
                            const option = document.createElement('option');
                            option.value = dept.id;
                            option.textContent = `${dept.name}`;
                            deptSelect.appendChild(option);
                        });
                    } else {
                        const option = document.createElement('option');
                        option.value = "";
                        option.textContent = "-- Không có phòng ban --";
                        deptSelect.appendChild(option);
                    }
                    deptSelect.disabled = false;
                })
                .catch(error => {
                    console.error(error);
                    deptSelect.innerHTML = '<option value="">Lỗi tải dữ liệu</option>';
                    deptSelect.disabled = false; 
                });
        });

        // [ORACLE UPGRADE] Auto-fill khi chọn nhân viên (Select2 AJAX)
        if($('#select_employee').length) {
            $('#select_employee').select2({
                theme: 'bootstrap-5',
                ajax: {
                    url: BASE_URL + '/core/users/ajax_search_unlinked_employees',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term, // Từ khóa tìm kiếm
                            site_id: $('#select_site_id').val() // Lấy site hiện tại
                        };
                    },
                    processResults: function (data) {
                        return { results: data.results };
                    },
                    cache: true
                },
                placeholder: '-- Tìm kiếm nhân viên (để thay đổi) --',
                allowClear: true,
                minimumInputLength: 0
            }).on('select2:select', function (e) {
                // Tự động điền thông tin khi chọn
                var data = e.params.data;
                if(data.full_name) document.getElementById('input_fullname').value = data.full_name;
                if(data.email) document.getElementById('input_email').value = data.email;
                if(data.department_id) deptSelect.value = data.department_id;
            });
        }
    });
</script>

