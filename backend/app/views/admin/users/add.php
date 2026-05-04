<div class="page-header">
    <div>
        <h2><i class="fas fa-user-plus me-2"></i> Thêm người dùng</h2>
        <p>Tạo tài khoản đăng nhập mới và gán vai trò truy cập ban đầu.</p>
    </div>
    <div class="btn-group">
        <a href="<?= url('/admin/users'); ?>" class="btn btn-ghost">
            <i class="fas fa-arrow-left me-1"></i> Quay lại
        </a>
    </div>
</div>

<?php if (function_exists('flash')) flash('user_message'); ?>

<form id="addUserForm" action="<?= url('/admin/users/store'); ?>" method="POST">
    <input type="hidden" name="csrf_token" value="<?= e((string)($csrf_token ?? ($_SESSION['csrf_token'] ?? ''))); ?>">

    <div class="grid-2" style="align-items: start;">
        <div class="card">
            <div class="card-title">👤 Thông tin tài khoản</div>

            <div class="form-group">
                <label class="form-label">Họ và tên</label>
                <input
                    type="text"
                    name="full_name"
                    class="form-control"
                    value="<?= e((string)($full_name ?? '')); ?>"
                    placeholder="Nguyễn Văn A"
                    required
                >
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        value="<?= e((string)($email ?? '')); ?>"
                        placeholder="name@company.com"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">Tên đăng nhập</label>
                    <input
                        type="text"
                        name="username"
                        class="form-control"
                        value="<?= e((string)($username ?? '')); ?>"
                        placeholder="username"
                        required
                    >
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Mật khẩu</label>
                    <input
                        type="password"
                        name="password"
                        class="form-control"
                        placeholder="Nhập mật khẩu"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">Xác nhận mật khẩu</label>
                    <input
                        type="password"
                        name="confirm_password"
                        class="form-control"
                        placeholder="Nhập lại mật khẩu"
                        required
                    >
                </div>
            </div>

            <div class="hint-box mt-16">
                Tài khoản mới sẽ được tạo ở trạng thái hoạt động. Nếu cần khóa hoặc đổi mật khẩu sau đó, bạn có thể chỉnh trong màn hình danh sách người dùng.
            </div>
        </div>

        <div class="card">
            <div class="card-title">🛡️ Phân quyền ban đầu</div>

            <div class="form-group">
                <label class="form-label">Site mặc định</label>
                <select name="site_id" id="site_id" class="form-control" required>
                    <?php foreach (($sites ?? []) as $site): ?>
                        <option
                            value="<?= (int)$site['id']; ?>"
                            <?= ((int)($default_site_id ?? 0) === (int)$site['id']) ? 'selected' : ''; ?>
                        >
                            <?= e((string)$site['code']); ?> - <?= e((string)$site['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Vai trò hệ thống</label>
                <select name="role_id" class="form-control" required>
                    <?php foreach (($roles ?? []) as $role): ?>
                        <option
                            value="<?= (int)$role['id']; ?>"
                            <?= ((int)($role_id ?? 0) === (int)$role['id']) ? 'selected' : ''; ?>
                        >
                            <?= e((string)$role['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Sites đang có trong hệ thống</label>
                <div class="status-stack">
                    <?php foreach (($sites ?? []) as $site): ?>
                        <div class="status-line">
                            <strong><?= e((string)$site['code']); ?> - <?= e((string)$site['name']); ?></strong>
                            <span class="badge <?= ((int)($default_site_id ?? 0) === (int)$site['id']) ? 'badge-linked' : 'badge-none'; ?>">
                                <?= ((int)($default_site_id ?? 0) === (int)$site['id']) ? 'Mặc định' : 'Khả dụng'; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="modal-actions" style="justify-content: flex-end; margin-top: 24px;">
                <a href="<?= url('/admin/users'); ?>" class="btn btn-ghost">Hủy</a>
                <button type="submit" class="btn btn-primary" id="submitAddUserBtn">
                    <i class="fas fa-save me-1"></i> Tạo người dùng
                </button>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('addUserForm');
    const submitBtn = document.getElementById('submitAddUserBtn');

    if (!form || !submitBtn) {
        return;
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        const formData = new FormData(form);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const originalHtml = submitBtn.innerHTML;

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner"></span> Đang tạo...';

        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            },
            body: formData
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (!data.success) {
                window.AffMVP?.showToast(data.message || 'Không thể tạo người dùng.', 'error');
                return;
            }

            window.AffMVP?.showToast(data.message || 'Đã tạo người dùng mới.', 'success');
            setTimeout(function () {
                window.location.href = '<?= url('/admin/users'); ?>';
            }, 500);
        })
        .catch(function () {
            window.AffMVP?.showToast('Có lỗi xảy ra. Vui lòng thử lại.', 'error');
        })
        .finally(function () {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
        });
    });
});
</script>
