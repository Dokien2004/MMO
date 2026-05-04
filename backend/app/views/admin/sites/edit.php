
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container-fluid px-4 mt-4">
    
    <form id="editSiteForm" action="<?= url('/admin') ?>/sites/edit/<?php echo $data['id']; ?>" method="POST">
        
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token ?? ($_SESSION['csrf_token'] ?? ''); ?>">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center">
                <a href="<?= url('/admin') ?>/sites" class="btn btn-outline-secondary btn-sm me-3 shadow-sm rounded-circle" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;" title="Quay lại danh sách">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h4 class="mb-0 text-primary fw-bold">Cập nhật Site</h4>
                    <small class="text-muted">Đang chỉnh sửa: <strong><?php echo $data['code']; ?></strong> - <?php echo $data['name']; ?></small>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                <i class="fas fa-save me-2"></i> Lưu thay đổi
            </button>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="m-0 fw-bold text-dark"><i class="fas fa-edit me-2 text-warning"></i>Thông tin cấu hình</h6>
                    </div>
                    
                    <div class="card-body p-4">
                        <div class="row g-3">
                            
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Mã Site (Code) <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control text-uppercase font-monospace <?php echo (!empty($data['code_err'])) ? 'is-invalid' : ''; ?>" 
                                       value="<?php echo $data['code']; ?>" placeholder="VD: HN-SITE-01">
                                <div class="invalid-feedback"><?php echo $data['code_err'] ?? ''; ?></div>
                                <div class="form-text small">Lưu ý: Thay đổi mã Site có thể ảnh hưởng đến các chứng từ cũ.</div>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label fw-bold">Tên hiển thị <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control <?php echo (!empty($data['name_err'])) ? 'is-invalid' : ''; ?>" 
                                       value="<?php echo $data['name']; ?>" placeholder="VD: Nhà máy Bắc Ninh">
                                <div class="invalid-feedback"><?php echo $data['name_err'] ?? ''; ?></div>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-bold">Địa chỉ</label>
                                <textarea name="address" class="form-control" rows="2" placeholder="Địa chỉ chi tiết..."><?php echo $data['address']; ?></textarea>
                            </div>

                            <div class="col-12"><hr class="text-muted my-2"></div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Trực thuộc (Site Cha)</label>
                                <select name="parent_site_id" class="form-select">
                                    <option value="">-- Là cấp cao nhất (Không có cha) --</option>
                                    <?php if(isset($data['all_sites']) && !empty($data['all_sites'])): ?>
                                        <?php foreach($data['all_sites'] as $site): ?>
                                            <?php 
                                            // [LOGIC] Không cho phép chọn chính mình làm cha
                                            if ($site->id == $data['id']) continue; 
                                            ?>
                                            <option value="<?php echo $site->id; ?>" <?php echo ($data['parent_site_id'] == $site->id) ? 'selected' : ''; ?>>
                                                <?php echo $site->code . ' - ' . $site->name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <div class="form-text text-muted small">Site cha quản lý site này (Cấu trúc hình cây).</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold mb-2 d-block">Tùy chọn nâng cao</label>
                                
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="is_master" id="isMaster" value="1" <?php echo ($data['is_master'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold text-dark" for="isMaster">Là Master Site</label>
                                </div>

                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" <?php echo ($data['is_active'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="isActive">Đang hoạt động (Active)</label>
                                </div>
                            </div>

                        </div>
                    </div>
                    
                    <div class="card-footer bg-light text-end py-3">
                        <a href="<?= url('/admin') ?>/sites" class="btn btn-light border text-secondary me-2">Hủy bỏ</a>
                        <button type="button" class="btn btn-primary fw-bold px-4" onclick="confirmSave()">
                            <i class="fas fa-check me-1"></i> Cập nhật
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    function confirmSave() {
        Swal.fire({
            title: 'Xác nhận cập nhật?',
            text: "Bạn có chắc chắn muốn lưu các thay đổi cho Site này?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Đồng ý',
            cancelButtonText: 'Hủy'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('editSiteForm').submit();
            }
        });
    }
</script>

