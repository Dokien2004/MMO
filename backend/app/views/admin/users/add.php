
<div class="container-fluid px-4 mt-4">
    
    <form action="<?= url('/admin') ?>/users/add" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $data['csrf_token'] ?? ''; ?>">

        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
            <div class="d-flex align-items-center">
                <a href="<?= url('/admin') ?>/users" class="btn btn-light btn-sm me-3 shadow-sm rounded-circle border" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;" title="Quay lại danh sách">
                    <i class="fas fa-arrow-left text-secondary"></i>
                </a>
                <div>
                    <h4 class="mb-0 text-primary fw-bold">Thêm Nhân sự mới</h4>
                    <small class="text-muted">Tạo tài khoản và thiết lập quyền truy cập ban đầu</small>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                <i class="fas fa-save me-2"></i> Lưu dữ liệu
            </button>
        </div>

        <?php if(function_exists('flash')) flash('user_message'); ?>

        <div class="row g-4">
            
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="m-0 fw-bold text-dark"><i class="far fa-id-card me-2 text-primary"></i>Thông tin chung</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <!-- [ORACLE UPGRADE] Chọn nhân viên có sẵn -->
                            <div class="col-12">
                                <label class="form-label fw-bold text-primary">Liên kết Nhân viên (Tùy chọn)</label>
                                <select name="employee_id" id="select_employee" class="form-select border-primary shadow-sm">
                                    <option value="">-- Tạo nhân viên mới (Mặc định) --</option>
                                    <!-- Options sẽ được load qua AJAX -->
                                </select>
                                <div class="form-text small">Chọn nhân viên có sẵn để tạo tài khoản đăng nhập cho họ.</div>
                            </div>
                            <div class="col-12"><hr class="my-2 opacity-25"></div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Họ và tên <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" id="input_fullname" class="form-control <?php echo (!empty($data['full_name_err'])) ? 'is-invalid' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($data['full_name']); ?>" placeholder="Nhập họ tên đầy đủ...">
                                <div class="invalid-feedback"><?php echo $data['full_name_err']; ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" id="input_email" class="form-control <?php echo (!empty($data['email_err'])) ? 'is-invalid' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($data['email']); ?>" placeholder="example@company.com">
                                <div class="invalid-feedback"><?php echo $data['email_err']; ?></div>
                            </div>

                            <div class="col-12"><hr class="my-3 opacity-25"></div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">Tên đăng nhập <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text bg-light text-muted"><i class="fas fa-user"></i></span>
                                    <input type="text" name="username" class="form-control <?php echo (!empty($data['username_err'])) ? 'is-invalid' : ''; ?>" 
                                           value="<?php echo htmlspecialchars($data['username']); ?>" placeholder="username">
                                    <div class="invalid-feedback"><?php echo $data['username_err']; ?></div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">Mật khẩu <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text bg-light text-muted"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="password" class="form-control <?php echo (!empty($data['password_err'])) ? 'is-invalid' : ''; ?>" placeholder="******">
                                    <div class="invalid-feedback"><?php echo $data['password_err']; ?></div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">Xác nhận MK <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text bg-light text-muted"><i class="fas fa-check-double"></i></span>
                                    <input type="password" name="confirm_password" class="form-control <?php echo (!empty($data['confirm_password_err'])) ? 'is-invalid' : ''; ?>" placeholder="******">
                                    <div class="invalid-feedback"><?php echo $data['confirm_password_err']; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-primary text-white py-3">
                        <h6 class="m-0 fw-bold"><i class="fas fa-sitemap me-2"></i>Tổ chức & Phân quyền</h6>
                    </div>
                    <div class="card-body p-4 bg-light">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nhà máy / Chi nhánh <span class="text-danger">*</span></label>
                            <select name="default_site_id" id="select_site_id" class="form-select border-primary shadow-sm">
                                <?php foreach($data['sites'] as $site): ?>
                                    <option value="<?php echo $site->id; ?>" <?php echo ($data['default_site_id'] == $site->id) ? 'selected' : ''; ?>>
                                        <?php echo $site->code . ' - ' . $site->name; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text text-muted small mt-1">Dữ liệu phòng ban sẽ load theo Site này.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Phòng ban</label>
                            <select name="department_id" id="select_department_id" class="form-select">
                                <option value="">-- Chọn Phòng ban --</option>
                                <?php foreach($data['departments'] as $dept): ?>
                                    <option value="<?php echo $dept->id; ?>" <?php echo ($data['department_id'] == $dept->id) ? 'selected' : ''; ?>>
                                        <?php echo $dept->name; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Vai trò hệ thống <span class="text-danger">*</span></label>
                            <select name="role_id" class="form-select fw-bold text-primary">
                                <?php foreach($data['roles'] as $role): ?>
                                    <option value="<?php echo $role->id; ?>" <?php echo ($data['role_id'] == $role->id) ? 'selected' : ''; ?>>
                                        <?php echo $role->name; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold border-bottom w-100 pb-1 mb-2">Quyền truy cập dữ liệu Site khác</label>
                            <div class="bg-white border rounded p-2 custom-scrollbar" style="max-height: 200px; overflow-y: auto;">
                                <?php if(empty($data['sites'])): ?>
                                    <p class="text-muted small text-center mb-0 p-2">Không có dữ liệu Site.</p>
                                <?php else: ?>
                                    <?php foreach($data['sites'] as $site): ?>
                                        <div class="form-check py-1">
                                            <input class="form-check-input" type="checkbox" name="access_sites[]" 
                                                   value="<?php echo $site->id; ?>" id="access_site_<?php echo $site->id; ?>"
                                                   <?php echo (in_array($site->id, $data['access_sites'] ?? [])) ? 'checked' : ''; ?>>
                                            <label class="form-check-label user-select-none cursor-pointer" for="access_site_<?php echo $site->id; ?>">
                                                <strong><?php echo $site->code; ?></strong> - <?php echo $site->name; ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
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
        const empSelect = document.getElementById('select_employee');
        
        // Base URL lấy từ config PHP
        const BASE_URL = "<?= url() ?>";

        siteSelect.addEventListener('change', function() {
            const siteId = this.value;
            
            // 1. Tự động check vào ô Access Site tương ứng
            const accessCheckbox = document.getElementById('access_site_' + siteId);
            if(accessCheckbox) accessCheckbox.checked = true;

            // 2. Reset dropdown phòng ban
            deptSelect.innerHTML = '<option value="">Đang tải dữ liệu...</option>';
            deptSelect.disabled = true;

            // 3. Gọi API (Route: ajax_get_departments)
            fetch(`${BASE_URL}/core/users/ajax_get_departments/${siteId}`)
                .then(response => {
                    // Xử lý lỗi HTTP (401, 403, 500)
                    if (!response.ok) {
                        if(response.status === 401) alert("Phiên làm việc hết hạn. Vui lòng đăng nhập lại.");
                        if(response.status === 403) alert("Bạn không có quyền truy cập dữ liệu của Site này.");
                        throw new Error('Server Error');
                    }
                    return response.json();
                })
                .then(data => {
                    // Clear old options
                    deptSelect.innerHTML = '<option value="">-- Chọn Phòng ban --</option>';
                    
                    if (Array.isArray(data) && data.length > 0) {
                        data.forEach(dept => {
                            const option = document.createElement('option');
                            option.value = dept.id;
                            option.textContent = dept.name; // Hiển thị tên phòng ban
                            deptSelect.appendChild(option);
                        });
                    } else {
                        const option = document.createElement('option');
                        option.value = "";
                        option.textContent = "-- Chưa có phòng ban --";
                        deptSelect.appendChild(option);
                    }
                    deptSelect.disabled = false;
                })
                .catch(error => {
                    console.error('Error:', error);
                    deptSelect.innerHTML = '<option value="">Lỗi tải dữ liệu</option>';
                    deptSelect.disabled = false; 
                });
        });

        // [ORACLE UPGRADE] Auto-fill khi chọn nhân viên
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
                placeholder: '-- Tìm kiếm nhân viên --',
                allowClear: true,
                minimumInputLength: 0
            }).on('select2:select', function (e) {
                // Tự động điền thông tin khi chọn
                var data = e.params.data;
                if(data.full_name) document.getElementById('input_fullname').value = data.full_name;
                if(data.email) document.getElementById('input_email').value = data.email;
                if(data.department_id) {
                    deptSelect.value = data.department_id;
                }
            });
        }
    });
</script>

