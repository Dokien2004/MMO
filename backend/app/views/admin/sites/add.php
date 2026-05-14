<?php
$allSites = $all_sites ?? [];
?>

<div class="page-header">
    <div>
        <div class="page-kicker">Admin / Sites</div>
        <h2>Thêm Site mới</h2>
        <p>Tạo nhà máy, chi nhánh hoặc site làm việc mới cho hệ thống.</p>
    </div>
    <div class="hero-actions">
        <a href="<?= url('/admin/sites') ?>" class="btn btn-ghost">← Quay lại danh sách</a>
    </div>
</div>

<div class="publish-mode-grid">
    <div class="card publish-mode-card ready">
        <div class="section-heading">
            <div>
                <div class="card-title">Thông tin cơ bản</div>
                <div class="section-note">Nhập mã site và tên hiển thị. Mã site nên ngắn, viết liền, không dấu.</div>
            </div>
        </div>

        <form data-ajax method="POST" action="<?= url('/admin/sites/store') ?>">
            <input type="hidden" name="csrf_token" value="<?= e((string)($csrf_token ?? ($_SESSION['csrf_token'] ?? ''))) ?>">

            <div class="grid-2 compact-grid">
                <div class="form-group">
                    <label class="form-label">Mã Site <span class="text-danger">*</span></label>
                    <input class="form-control" name="code" required autofocus placeholder="VD: HN-SITE-01" value="<?= e((string)($code ?? '')) ?>" style="text-transform:uppercase">
                    <div class="sub">Chỉ dùng chữ, số, gạch ngang hoặc gạch dưới. Hệ thống sẽ tự chuẩn hóa viết hoa.</div>
                    <?php if (!empty($code_err ?? '')): ?><div class="text-danger text-sm mt-8"><?= e((string)$code_err) ?></div><?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">Tên hiển thị <span class="text-danger">*</span></label>
                    <input class="form-control" name="name" required placeholder="VD: Chi nhánh Hà Nội" value="<?= e((string)($name ?? '')) ?>">
                    <div class="sub">Tên này sẽ hiển thị ở sidebar/topbar và danh sách quản trị.</div>
                    <?php if (!empty($name_err ?? '')): ?><div class="text-danger text-sm mt-8"><?= e((string)$name_err) ?></div><?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Địa chỉ</label>
                <textarea class="form-control" name="address" rows="3" placeholder="Địa chỉ chi tiết..."> <?= e((string)($address ?? '')) ?></textarea>
            </div>

            <div class="grid-2 compact-grid">
                <div class="form-group">
                    <label class="form-label">Trực thuộc Site cha</label>
                    <select class="form-control" name="parent_site_id">
                        <option value="">Cấp cao nhất / không có site cha</option>
                        <?php foreach ($allSites as $site): ?>
                            <?php
                            $siteId = (int)($site['id'] ?? $site->id ?? 0);
                            $siteCode = (string)($site['code'] ?? $site->code ?? '');
                            $siteName = (string)($site['name'] ?? $site->name ?? '');
                            ?>
                            <option value="<?= $siteId ?>" <?= ((string)($parent_site_id ?? '') === (string)$siteId) ? 'selected' : '' ?>>
                                <?= e($siteCode . ' — ' . $siteName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="sub">Chọn site quản lý cấp trên nếu đây là chi nhánh con.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Trạng thái & vai trò</label>
                    <div class="option-stack">
                        <label class="option-row">
                            <input type="checkbox" name="is_active" value="1" <?= ((int)($is_active ?? 1) === 1) ? 'checked' : '' ?>>
                            <span>
                                <strong>Kích hoạt site</strong>
                                <small>Cho phép người dùng chuyển sang site này.</small>
                            </span>
                        </label>
                        <label class="option-row">
                            <input type="checkbox" name="is_master" value="1" <?= !empty($is_master ?? 0) ? 'checked' : '' ?>>
                            <span>
                                <strong>Master Site</strong>
                                <small>Dùng làm site quản lý/cấu hình chung nếu cần.</small>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="action-strip mt-16">
                <div>
                    <strong>Sẵn sàng tạo site?</strong>
                    <div class="sub">Sau khi lưu, Boss có thể quay lại danh sách để chuyển site hoặc chỉnh sửa.</div>
                </div>
                <div class="btn-group">
                    <a href="<?= url('/admin/sites') ?>" class="btn btn-ghost">Hủy</a>
                    <button type="submit" class="btn btn-accent">Lưu site mới</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card publish-mode-card">
        <div class="section-heading">
            <div>
                <div class="card-title">Gợi ý cấu hình</div>
                <div class="section-note">Một vài quy ước để dữ liệu site dễ quản lý hơn.</div>
            </div>
        </div>
        <div class="quick-guide">
            <div class="guide-item">
                <strong>Mã site</strong>
                <span>Dùng dạng <code>HN-SITE-01</code>, <code>HCM-CN-01</code> hoặc <code>MAIN</code>.</span>
            </div>
            <div class="guide-item">
                <strong>Site cha</strong>
                <span>Để trống nếu là cấp cao nhất; chọn site cha nếu là chi nhánh con.</span>
            </div>
            <div class="guide-item">
                <strong>Master Site</strong>
                <span>Chỉ bật cho site trung tâm/quản lý chung, không nên bật tràn lan.</span>
            </div>
        </div>
    </div>
</div>
