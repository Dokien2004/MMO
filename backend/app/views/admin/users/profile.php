
<div class="container-fluid px-4 mt-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 text-primary fw-bold">Hồ sơ cá nhân</h4>
            <small class="text-muted">Quản lý thông tin tài khoản và bảo mật</small>
        </div>
        <div>
            <span class="badge bg-light text-dark border px-3 py-2">
                <i class="far fa-clock me-1"></i> Đăng nhập lần cuối: 
                <span class="fw-bold"><?php echo isset($data['user']->last_login_at) ? date('H:i d/m/Y', strtotime($data['user']->last_login_at)) : 'Chưa ghi nhận'; ?></span>
            </span>
        </div>
    </div>

    <?php if(function_exists('flash')) flash('profile_msg'); ?>

    <div class="row g-4">
        
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center p-5">
                    <div class="mb-4 position-relative d-inline-block">
                        <?php if (!empty($data['user']->avatar_url)): ?>
                            <img src="<?= url() ?>/uploads/employees/<?= e($data['user']->avatar_url) ?>" 
                                 alt="Avatar" class="rounded-circle shadow" 
                                 style="width: 120px; height: 120px; object-fit: cover;"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <div class="rounded-circle bg-primary text-white align-items-center justify-content-center shadow" 
                                 style="width: 120px; height: 120px; font-size: 3rem; font-weight: bold; margin: 0 auto; display: none;">
                                <?= strtoupper(substr($data['user']->full_name ?? 'U', 0, 1)) ?>
                            </div>
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center shadow" 
                                 style="width: 120px; height: 120px; font-size: 3rem; font-weight: bold; margin: 0 auto;">
                                <?= strtoupper(substr($data['user']->full_name ?? 'U', 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div class="position-absolute bottom-0 end-0 bg-success border border-white rounded-circle p-2" title="Active" style="width: 20px; height: 20px;"></div>
                    </div>

                    <h5 class="fw-bold text-dark mb-1"><?php echo $data['user']->full_name; ?></h5>
                    <p class="text-muted mb-3">@<?php echo $data['user']->username; ?></p>

                    <div class="d-flex justify-content-center gap-2 mb-4">
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 px-3 py-2 rounded-pill">
                            <i class="fas fa-user-tag me-1"></i> <?php echo $data['user']->role_name; ?>
                        </span>
                    </div>

                    <div class="border-top pt-4 text-start">
                        <div class="mb-3">
                            <label class="small text-muted fw-bold text-uppercase">Email</label>
                            <div class="d-flex align-items-center">
                                <i class="far fa-envelope text-secondary me-2"></i>
                                <span><?php echo $data['user']->email; ?></span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="small text-muted fw-bold text-uppercase">Phòng ban</label>
                            <div class="d-flex align-items-center">
                                <i class="far fa-building text-secondary me-2"></i>
                                <span><?php echo $data['user']->department_name ?? 'Chưa cập nhật'; ?></span>
                            </div>
                        </div>
                        <div>
                            <label class="small text-muted fw-bold text-uppercase">Đơn vị công tác (Site)</label>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-industry text-secondary me-2"></i>
                                <span><?php echo $data['user']->site_name ?? 'N/A'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom-0 p-0">
                    <ul class="nav nav-tabs nav-fill" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-3 fw-bold" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                                <i class="fas fa-shield-alt me-2"></i>Bảo mật & Mật khẩu
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-3 fw-bold" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab">
                                <i class="fas fa-info-circle me-2"></i>Thông tin Chi tiết
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-3 fw-bold" id="delegation-tab" data-bs-toggle="tab" data-bs-target="#delegation" type="button" role="tab">
                                <i class="fas fa-user-friends me-2"></i>Ủy quyền
                            </button>
                        </li>
                    </ul>
                </div>
                
                <div class="card-body p-4">
                    <div class="tab-content" id="profileTabsContent">
                        
                        <div class="tab-pane fade show active" id="security" role="tabpanel">
                            <h6 class="text-dark fw-bold mb-3">Cập nhật thông tin</h6>
                            
                            <form action="<?= url('/admin') ?>/users/profile" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $data['csrf_token'] ?? $_SESSION['csrf_token'] ?? ''; ?>">

                                <div class="row mb-3">
                                    <label class="col-md-4 col-form-label text-md-end">Họ và tên</label>
                                    <div class="col-md-7">
                                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($data['user']->full_name); ?>">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <label class="col-md-4 col-form-label text-md-end">Email</label>
                                    <div class="col-md-7">
                                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($data['user']->email); ?>">
                                    </div>
                                </div>

                                <hr class="my-4">
                                <h6 class="text-dark fw-bold mb-3">Đổi mật khẩu (Tùy chọn)</h6>

                                <div class="row mb-3">
                                    <label class="col-md-4 col-form-label text-md-end">Mật khẩu hiện tại</label>
                                    <div class="col-md-7">
                                        <div class="input-group">
                                            <input type="password" name="old_password" id="old_password" 
                                                   class="form-control <?php echo (!empty($data['old_password_err'])) ? 'is-invalid' : ''; ?>" 
                                                   value="<?php echo $data['old_password']; ?>">
                                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="old_password"><i class="far fa-eye"></i></button>
                                            <div class="invalid-feedback"><?php echo $data['old_password_err']; ?></div>
                                        </div>
                                        <div class="form-text small">Bắt buộc nhập nếu bạn muốn đổi mật khẩu mới.</div>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <label class="col-md-4 col-form-label text-md-end">Mật khẩu mới</label>
                                    <div class="col-md-7">
                                        <div class="input-group">
                                            <input type="password" name="new_password" id="new_password" 
                                                   class="form-control <?php echo (!empty($data['new_password_err'])) ? 'is-invalid' : ''; ?>"
                                                   value="<?php echo $data['new_password']; ?>">
                                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password"><i class="far fa-eye"></i></button>
                                            <div class="invalid-feedback"><?php echo $data['new_password_err']; ?></div>
                                        </div>
                                        <div class="form-text small">Tối thiểu 6 ký tự. Nên có chữ hoa và số.</div>
                                    </div>
                                </div>

                                <div class="row mb-4">
                                    <label class="col-md-4 col-form-label text-md-end">Xác nhận mật khẩu</label>
                                    <div class="col-md-7">
                                        <div class="input-group">
                                            <input type="password" name="confirm_password" id="confirm_password" 
                                                   class="form-control <?php echo (!empty($data['confirm_password_err'])) ? 'is-invalid' : ''; ?>"
                                                   value="<?php echo $data['confirm_password']; ?>">
                                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password"><i class="far fa-eye"></i></button>
                                            <div class="invalid-feedback"><?php echo $data['confirm_password_err']; ?></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-7 offset-md-4">
                                        <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm">
                                            <i class="fas fa-save me-2"></i> Lưu thay đổi
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <hr class="my-4">
                            <h6 class="text-dark fw-bold mb-3">Quản lý phiên đăng nhập</h6>
                            
                            <?php $otherSessions = $data['other_session_count'] ?? 0; ?>
                            
                            <div class="alert <?= $otherSessions > 0 ? 'alert-warning' : 'alert-info' ?> border-0 d-flex align-items-center mb-3">
                                <i class="fas fa-desktop fa-2x me-3 opacity-50"></i>
                                <div>
                                    <?php if ($otherSessions > 0): ?>
                                        Tài khoản của bạn đang được đăng nhập trên <strong><?= $otherSessions ?></strong> thiết bị khác.
                                        <br><small class="text-muted">Nếu bạn không nhận ra, hãy đăng xuất tất cả để bảo vệ tài khoản.</small>
                                    <?php else: ?>
                                        Không phát hiện phiên đăng nhập nào khác ngoài thiết bị hiện tại.
                                    <?php endif; ?>
                                </div>
                            </div>

                            <form action="<?= url('/admin') ?>/users/logoutAllDevices" method="POST" 
                                  onsubmit="return confirm('Bạn chắc chắn muốn đăng xuất khỏi tất cả các thiết bị khác? Các phiên đăng nhập khác sẽ bị hủy ngay lập tức.');">
                                <?php csrf_field(); ?>
                                <button type="submit" class="btn btn-outline-danger fw-bold px-4">
                                    <i class="fas fa-sign-out-alt me-2"></i> Đăng xuất khỏi tất cả thiết bị khác
                                </button>
                            </form>
                        </div>

                        <div class="tab-pane fade" id="info" role="tabpanel">
                            <h6 class="text-dark fw-bold mb-3">Thông tin hệ thống</h6>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm mb-0">
                                    <tbody>
                                        <tr>
                                            <th class="bg-light w-25">ID Nhân viên</th>
                                            <td>#<?php echo $data['user']->id; ?></td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light">Họ và tên</th>
                                            <td><?php echo htmlspecialchars($data['user']->full_name); ?></td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light">Mã nhân viên</th>
                                            <td class="font-monospace text-primary"><?php echo htmlspecialchars($data['user']->employee_code ?? 'N/A'); ?></td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light">Tài khoản</th>
                                            <td><?php echo $data['user']->username; ?></td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light">Ngày tham gia</th>
                                            <td><?php echo isset($data['user']->created_at) ? date('d/m/Y', strtotime($data['user']->created_at)) : 'N/A'; ?></td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light">Trạng thái</th>
                                            <td>
                                                <span class="badge bg-success">Hoạt động (Active)</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-4">
                                <h6 class="text-dark fw-bold mb-2">Quyền truy cập Kho & Site</h6>
                                <div class="alert alert-info border-0 d-flex align-items-center">
                                    <i class="fas fa-info-circle fa-2x me-3 opacity-50"></i>
                                    <div>
                                        Bạn đang truy cập hệ thống với vai trò <strong><?php echo $data['user']->role_name; ?></strong>.<br>
                                        Nếu cần thay đổi quyền hạn hoặc thêm quyền truy cập kho, vui lòng liên hệ Admin.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="delegation" role="tabpanel">
                            
                            <?php if(!empty($data['user']->delegated_to_user_id) && !empty($data['user']->delegation_end_date)): ?>
                                <h6 class="text-dark fw-bold mb-3">Thông tin Ủy quyền hiện tại</h6>
                                <div class="alert alert-success border-0 mb-4">
                                    <p class="mb-1">
                                        <i class="fas fa-user-check me-2"></i>
                                        <strong>Người được ủy quyền:</strong>
                                        <span class="fw-bold text-primary"><?php echo htmlspecialchars($data['user']->deputy_name ?? 'N/A'); ?></span>
                                    </p>
                                    <p class="mb-0">
                                        <i class="far fa-calendar-check me-2"></i>
                                        <strong>Hiệu lực đến ngày:</strong>
                                        <?php echo date('d/m/Y', strtotime($data['user']->delegation_end_date)); ?>
                                    </p>
                                </div>
                                <hr class="my-4">
                            <?php endif; ?>

                            <h6 class="text-dark fw-bold mb-3">Thiết lập Người được ủy quyền</h6>
                            <div class="alert alert-warning border-0 mb-4 d-flex align-items-center">
                                <i class="fas fa-exclamation-triangle fa-2x me-3 opacity-50"></i>
                                <div>
                                    Người được ủy quyền sẽ có thể thực hiện các công việc thay bạn (ví dụ: duyệt yêu cầu) trong thời gian bạn vắng mặt.
                                    <br>Để hủy ủy quyền, hãy chọn "-- Bỏ chọn --" và bấm lưu.
                                </div>
                            </div>

                            <form action="<?= url('/admin') ?>/users/delegate" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $data['csrf_token'] ?? $_SESSION['csrf_token'] ?? ''; ?>">
                                
                                <div class="row mb-3">
                                    <label for="deputy_id" class="col-md-4 col-form-label text-md-end">Người được ủy quyền</label>
                                    <div class="col-md-7">
                                        <select name="deputy_id" id="deputy_id" class="form-select">
                                            <option value="">-- Bỏ chọn --</option>
                                            <?php if(isset($data['all_users']) && is_array($data['all_users'])): ?>
                                                <?php foreach($data['all_users'] as $u): ?>
                                                    <?php if($u->id != $data['user']->id): // Can't delegate to self ?>
                                                        <option value="<?php echo $u->id; ?>" <?php echo ($data['user']->delegated_to_user_id ?? '') == $u->id ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($u->full_name); ?> (@<?php echo htmlspecialchars($u->username); ?>)
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mb-4">
                                    <label for="deputy_valid_until" class="col-md-4 col-form-label text-md-end">Ủy quyền có hiệu lực đến</label>
                                    <div class="col-md-7">
                                        <input type="date" name="deputy_valid_until" id="deputy_valid_until" 
                                               class="form-control" 
                                               value="<?php echo !empty($data['user']->delegation_end_date) ? date('Y-m-d', strtotime($data['user']->delegation_end_date)) : ''; ?>">
                                        <div class="form-text">Để trống nếu muốn ủy quyền vô thời hạn.</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-7 offset-md-4">
                                        <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm">
                                            <i class="fas fa-save me-2"></i> Lưu thiết lập Ủy quyền
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleButtons = document.querySelectorAll('.toggle-password');

        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const inputField = document.getElementById(targetId);
                const icon = this.querySelector('i');

                if (inputField.type === 'password') {
                    inputField.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    inputField.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
    });
</script>

