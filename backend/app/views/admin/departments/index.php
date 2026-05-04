<?php 

// --- HELPER FUNCTIONS & DATA PREPARATION ---

// 1. Lấy dữ liệu từ Controller
$employeeCounts = $data['employee_counts'] ?? [];

// 2. Xây dựng cấu trúc cây cho Phòng ban
$deptTree = [];
if (!empty($data['departments'])) {
    foreach ($data['departments'] as $d) {
        $pid = $d->parent_id ? $d->parent_id : 0;
        $deptTree[$pid][] = $d;
    }
}

// 3. [NEW] Hàm đệ quy tính tổng số nhân sự (bao gồm các phòng ban con)
function calculateCumulativeCounts(&$tree, $employeeCounts, $parentId = 0) {
    if (!isset($tree[$parentId])) {
        return 0;
    }
    
    $totalForThisLevel = 0;
    foreach ($tree[$parentId] as $dept) {
        $childCount = calculateCumulativeCounts($tree, $employeeCounts, $dept->id);
        $directCount = $employeeCounts[$dept->id] ?? 0;
        $dept->cumulative_user_count = $directCount + $childCount;
        $totalForThisLevel += $dept->cumulative_user_count;
    }
    return $totalForThisLevel;
}

// 4. Chạy hàm tính toán để thêm thuộc tính 'cumulative_user_count' vào mỗi object phòng ban
calculateCumulativeCounts($deptTree, $employeeCounts, 0);

// 5. Tính tổng nhân sự toàn công ty (Tổng các node gốc)
$grandTotalEmployees = 0;
if (isset($deptTree[0])) {
    foreach ($deptTree[0] as $rootDept) {
        $grandTotalEmployees += $rootDept->cumulative_user_count ?? 0;
    }
}

// 2. Chuẩn bị dữ liệu cây cho Dropdown chọn cha
$parentTree = [];
if (!empty($data['parents'])) {
    foreach ($data['parents'] as $p) {
        $pid = $p->parent_id ? $p->parent_id : 0;
        $parentTree[$pid][] = $p;
    }
}

// 5. Hàm đệ quy render Dropdown Options
function renderParentOptions($tree, $parentId = 0, $level = 0, $selectedId = null) {
    if (!isset($tree[$parentId])) return;
    foreach ($tree[$parentId] as $p) {
        // Tạo thụt đầu dòng visual
        $prefix = str_repeat('│&nbsp;&nbsp;&nbsp;', $level);
        $symbol = ($level > 0) ? '└─ ' : '';
        
        $selected = ($selectedId == $p->id) ? 'selected' : '';
        echo '<option value="'.$p->id.'" '.$selected.'>'.$prefix.$symbol.$p->name.' ('.$p->code.')</option>';
        
        // Đệ quy con
        renderParentOptions($tree, $p->id, $level + 1, $selectedId);
    }
}

// 6. Hàm đệ quy render Table Rows
function renderDeptRows($tree, $parentId = 0, $level = 0)
{
    if (!isset($tree[$parentId])) return;

    foreach ($tree[$parentId] as $dept) {
        $hasChildren = isset($tree[$dept->id]);
        // Tính toán padding cho tên phòng ban
        $paddingLeft = 10 + ($level * 25); // Mỗi cấp thụt vào 25px

        // Class và attribute cho JS
        // [FIX] Chỉ ẩn level >= 2, level 0 và 1 hiện sẵn
        $rowClass = ($level >= 2) ? 'd-none' : '';
        $parentAttr = ($level > 0) ? 'data-parent-id="' . $parentId . '"' : '';

        // Render HTML Row (Sử dụng biến $dept)
        // Lưu ý: Phải đóng PHP tag để viết HTML cho sạch, sau đó mở lại để gọi đệ quy
        ?>
        <tr class="<?php echo $rowClass; ?>" data-id="<?php echo $dept->id; ?>" <?php echo $parentAttr; ?>>
            <td class="ps-4 text-muted small">#<?php echo $dept->id; ?></td>

            <td>
                <div class="d-flex align-items-center" style="padding-left: <?php echo $paddingLeft; ?>px;">
                    <?php if ($hasChildren): ?>
                        <a href="#" class="btn-toggle-children me-2 text-decoration-none" data-id="<?php echo $dept->id; ?>" title="Mở rộng/Thu gọn">
                            <?php 
                            // Level 0 và 1: Hiện minus (đã expand), Level >= 2: Hiện plus (collapsed)
                            $iconClass = ($level < 2) ? 'fa-minus-square text-primary' : 'fa-plus-square text-secondary';
                            ?>
                            <i class="fas <?php echo $iconClass; ?>"></i>
                        </a>
                    <?php else: ?>
                        <span class="text-muted me-2" style="width: 1.2em; display: inline-block; text-align: center;">
                            <?php echo ($level > 0) ? '└' : '•'; ?>
                        </span>
                    <?php endif; ?>

                    <div class="d-flex flex-column">
                        <span class="fw-bold text-dark text-primary-hover">
                            <?php echo $dept->name; ?>
                        </span>
                        <small class="text-muted font-monospace">
                            <i class="fas fa-tag me-1 text-secondary opacity-50"></i><?php echo $dept->code; ?>
                        </small>
                        <?php if (!empty($dept->description)): ?>
                            <small class="text-muted fst-italic mt-1 text-truncate" style="max-width: 200px;" title="<?php echo $dept->description; ?>">
                                <?php echo $dept->description; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </td>

            <td>
                <div class="d-flex flex-column small">
                    <div class="mb-1">
                        <span class="text-secondary">Trực thuộc:</span>
                        <?php if ($dept->parent_name): ?>
                            <span class="fw-bold text-dark"><?php echo $dept->parent_name; ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border">Cấp cao nhất</span>
                        <?php endif; ?>
                    </div>

                    <div>
                        <span class="text-secondary">Quản lý:</span>
                        <?php if ($dept->manager_name): ?>
                            <span class="text-primary fw-bold"><i class="fas fa-user-tie me-1"></i><?php echo $dept->manager_name; ?></span>
                        <?php else: ?>
                            <span class="text-muted fst-italic">- Chưa bổ nhiệm -</span>
                        <?php endif; ?>
                    </div>

                    <div class="mt-1">
                        <span class="text-secondary">Mua hàng:</span>
                        <?php if (!empty($dept->buyer_name)): ?>
                            <span class="text-success fw-bold"><i class="fas fa-user-tag me-1"></i><?php echo $dept->buyer_name; ?></span>
                        <?php else: ?>
                            <span class="text-muted fst-italic">- Chưa phân công -</span>
                        <?php endif; ?>
                    </div>
                </div>
            </td>

            <td class="text-center">
                <?php if ($dept->cumulative_user_count > 0): ?>
                    <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3 border border-info border-opacity-25">
                        <?php echo $dept->cumulative_user_count; ?> NV
                    </span>
                <?php else: ?>
                    <span class="text-muted small">-</span>
                <?php endif; ?>
            </td>

            <td class="text-center">
                <?php if ($dept->status == 'active'): ?>
                    <span class="badge bg-success bg-opacity-75"><i class="fas fa-check me-1"></i> Active</span>
                <?php else: ?>
                    <span class="badge bg-danger bg-opacity-75"><i class="fas fa-times me-1"></i> Inactive</span>
                <?php endif; ?>
            </td>

            <td class="text-end pe-4">
                <div class="btn-group">
                    <?php if ($level == 0 && hasPermission('department.create')): ?>
                        <button type="button" class="btn btn-sm btn-light text-success border me-1 btn-add-child"
                                data-parent-id="<?php echo $dept->id; ?>"
                                data-parent-name="<?php echo htmlspecialchars($dept->name); ?>"
                                title="Thêm phòng ban con">
                            <i class="fas fa-plus"></i>
                        </button>
                    <?php endif; ?>
                    <?php if (hasPermission('department.edit')): ?>
                    <button type="button" class="btn btn-sm btn-light text-primary border me-1"
                            data-bs-toggle="modal"
                            data-bs-target="#editDeptModal"
                            data-id="<?php echo $dept->id; ?>"
                            data-code="<?php echo $dept->code; ?>"
                            data-name="<?php echo $dept->name; ?>"
                            data-parent-id="<?php echo $dept->parent_id; ?>"
                            data-manager-id="<?php echo $dept->manager_id; ?>"
                            data-manager-name="<?php echo htmlspecialchars($dept->manager_name ?? ''); ?>"
                            data-buyer-id="<?php echo $dept->assigned_buyer_id; ?>"
                            data-buyer-name="<?php echo htmlspecialchars($dept->buyer_name ?? ''); ?>"
                            data-description="<?php echo $dept->description; ?>"
                            data-status="<?php echo $dept->status; ?>"
                            title="Sửa">
                        <i class="fas fa-edit"></i>
                    </button>
                    <?php endif; ?>

                    <?php if (hasPermission('department.delete')): ?>
                    <button type="button" class="btn btn-sm btn-light text-danger border"
                            data-bs-toggle="modal"
                            data-bs-target="#deleteDeptModal"
                            data-id="<?php echo $dept->id; ?>"
                            data-code="<?php echo $dept->code; ?>"
                            data-name="<?php echo htmlspecialchars($dept->name); ?>"
                            data-count="<?php echo $dept->cumulative_user_count; ?>"
                            title="Xóa">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php
// Gọi đệ quy cho con
        renderDeptRows($tree, $dept->id, $level + 1);
    }
}
?>

<div class="container-fluid px-4 mt-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-primary fw-bold mb-1"><i class="fas fa-sitemap me-2"></i> Cơ cấu Tổ chức</h4>
            <small class="text-muted">
                Danh sách phòng ban tại: 
                <span class="fw-bold text-dark">
                    <?php echo isset($_SESSION['site_name']) ? $_SESSION['site_name'] : 'N/A'; ?>
                </span>
            </small>
        </div>
        
        <div>
            <a href="<?= url('/admin') ?>/departments/orgchart" class="btn btn-outline-secondary shadow-sm fw-bold me-2">
                <i class="fas fa-project-diagram me-1"></i> Xem Sơ đồ
            </a>
            <?php if(hasPermission('department.create')): ?>
            <button type="button" class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addDeptModal">
                <i class="fas fa-plus me-1"></i> Thêm Phòng ban
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php flash('dept_msg'); ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="tblItems" class="table table-hover align-middle mb-0">
                    <thead class="bg-light small text-uppercase text-secondary">
                        <tr>
                            <th class="ps-4" style="width: 50px;">ID</th>
                            <th style="width: 250px;">Phòng ban</th>
                            <th style="width: 200px;">Cấu trúc</th>
                            <th class="text-center" style="width: 120px;">Nhân sự</th>
                            <th class="text-center" style="width: 100px;">Trạng thái</th>
                            <th class="text-end pe-4" style="width: 100px;">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($data['departments'])): ?>
                            <?php 
                                // Gọi hàm render đệ quy bắt đầu từ root (0)
                                renderDeptRows($deptTree, 0); 
                            ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center p-5 text-muted"><i class="fas fa-folder-open fa-2x mb-2 opacity-50"></i><br>Chưa có dữ liệu phòng ban.</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if(!empty($data['departments'])): ?>
                    <tfoot class="bg-light border-top fw-bold">
                        <tr>
                            <td colspan="3" class="text-end pe-4 text-uppercase text-secondary">Tổng nhân sự:</td>
                            <td class="text-center text-primary"><?php echo number_format($grandTotalEmployees); ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addDeptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2"></i> Thêm Phòng ban</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= url('/admin') ?>/departments/add" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $data['csrf_token']; ?>">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Mã PB <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control <?php echo (!empty($data['code_err'])) ? 'is-invalid' : ''; ?>" value="<?php echo $data['code'] ?? ''; ?>" placeholder="SALE" style="text-transform: uppercase;" required autofocus>
                            <div class="invalid-feedback"><?php echo $data['code_err'] ?? ''; ?></div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Tên Phòng ban <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control <?php echo (!empty($data['name_err'])) ? 'is-invalid' : ''; ?>" value="<?php echo $data['name'] ?? ''; ?>" placeholder="Phòng Kinh Doanh" required>
                            <div class="invalid-feedback"><?php echo $data['name_err'] ?? ''; ?></div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Trực thuộc (Phòng ban cha)</label>
                            <select name="parent_id" class="form-select">
                                <option value="">-- 🏢 Cấp cao nhất (Không có cha) --</option>
                                <?php 
                                    $selectedParent = isset($data['parent_id']) ? $data['parent_id'] : null;
                                    renderParentOptions($parentTree, 0, 0, $selectedParent); 
                                ?>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Trưởng phòng / Quản lý</label>
                            <!-- Smart Select Component -->
                            <div class="position-relative smart-select" id="addManagerSelect">
                                <input type="text" class="form-control search-input" placeholder="Tìm kiếm theo tên hoặc mã..." autocomplete="off">
                                <input type="hidden" name="manager_id" class="value-input" value="<?php echo $data['manager_id'] ?? ''; ?>">
                                <div class="search-select-dropdown d-none"></div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Nhân viên mua hàng phụ trách</label>
                            <!-- Smart Select Component -->
                            <div class="position-relative smart-select" id="addBuyerSelect">
                                <input type="text" class="form-control search-input" placeholder="Tìm kiếm theo tên hoặc email..." autocomplete="off">
                                <input type="hidden" name="assigned_buyer_id" class="value-input" value="<?php echo $data['assigned_buyer_id'] ?? ''; ?>">
                                <div class="search-select-dropdown d-none"></div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Mô tả thêm</label>
                            <textarea name="description" class="form-control" rows="2"><?php echo $data['description'] ?? ''; ?></textarea>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Trạng thái</label>
                            <select name="status" class="form-select">
                                <option value="active" <?php echo (isset($data['status']) && $data['status'] == 'active') ? 'selected' : ''; ?>>Hoạt động</option>
                                <option value="inactive">Tạm ngưng</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-light btn-sm border" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold px-4">Lưu dữ liệu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editDeptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white py-2">
                <h6 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i> Cập nhật Phòng ban</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editDeptForm" action="" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $data['csrf_token']; ?>">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Mã PB <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="editCode" class="form-control <?php echo (isset($data['validation_error_for']) && $data['validation_error_for'] === 'edit' && !empty($data['code_err'])) ? 'is-invalid' : ''; ?>" value="<?php echo (isset($data['validation_error_for']) && $data['validation_error_for'] === 'edit') ? ($data['code'] ?? '') : ''; ?>" style="text-transform: uppercase;" required>
                            <div class="invalid-feedback"><?php echo (isset($data['validation_error_for']) && $data['validation_error_for'] === 'edit') ? ($data['code_err'] ?? '') : ''; ?></div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Tên Phòng ban <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="editName" class="form-control <?php echo (isset($data['validation_error_for']) && $data['validation_error_for'] === 'edit' && !empty($data['name_err'])) ? 'is-invalid' : ''; ?>" value="<?php echo (isset($data['validation_error_for']) && $data['validation_error_for'] === 'edit') ? ($data['name'] ?? '') : ''; ?>" required>
                            <div class="invalid-feedback"><?php echo (isset($data['validation_error_for']) && $data['validation_error_for'] === 'edit') ? ($data['name_err'] ?? '') : ''; ?></div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Trực thuộc</label>
                            <select name="parent_id" id="editParentId" class="form-select">
                                <option value="">-- 🏢 Cấp cao nhất --</option>
                                <?php 
                                    // Render options, JS sẽ set selected value sau
                                    renderParentOptions($parentTree, 0, 0); 
                                ?>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Trưởng phòng</label>
                            <!-- Smart Select Component -->
                            <div class="position-relative smart-select" id="editManagerSelect">
                                <input type="text" class="form-control search-input" placeholder="Tìm kiếm theo tên hoặc mã..." autocomplete="off">
                                <input type="hidden" name="manager_id" id="editManagerId" class="value-input">
                                <div class="search-select-dropdown d-none"></div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Nhân viên mua hàng phụ trách</label>
                            <!-- Smart Select Component -->
                            <div class="position-relative smart-select" id="editBuyerSelect">
                                <input type="text" class="form-control search-input" placeholder="Tìm kiếm theo tên hoặc email..." autocomplete="off">
                                <input type="hidden" name="assigned_buyer_id" id="editBuyerId" class="value-input">
                                <div class="search-select-dropdown d-none"></div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Mô tả</label>
                            <textarea name="description" id="editDescription" class="form-control" rows="2"><?php echo (isset($data['validation_error_for']) && $data['validation_error_for'] === 'edit') ? ($data['description'] ?? '') : ''; ?></textarea>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Trạng thái</label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="active" <?php echo (isset($data['validation_error_for']) && $data['validation_error_for'] === 'edit' && isset($data['status']) && $data['status'] == 'active') ? 'selected' : ''; ?>>Hoạt động</option>
                                <option value="inactive">Tạm ngưng</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-light btn-sm border" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-info text-white btn-sm fw-bold px-4">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteDeptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white py-2">
                <h6 class="modal-title fw-bold"><i class="fas fa-trash-alt me-2"></i> Xóa Phòng ban?</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-4 text-center">
                <p class="mb-1 text-muted">Bạn có chắc chắn muốn xóa:</p>
                <h5 id="delDeptName" class="fw-bold text-dark mb-0">...</h5>
                <small class="text-muted d-block mb-3">(Mã: <span id="delDeptCode">...</span>)</small>
                
                <div id="userCountWarning" class="alert alert-warning py-2 small mb-0 text-start border-0 bg-warning bg-opacity-10 text-dark d-none">
                    <i class="fas fa-exclamation-triangle me-1 text-warning"></i> 
                    Đang có <b id="delUserCount">0</b> nhân sự thuộc phòng ban này. 
                    <div class="mt-1 text-danger fw-bold">Không thể xóa!</div>
                </div>
            </div>
            
            <div class="modal-footer bg-light border-0 justify-content-center py-2">
                <button type="button" class="btn btn-light btn-sm px-3 border" data-bs-dismiss="modal">Hủy</button>
                
                <form id="deleteDeptForm" action="" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $data['csrf_token']; ?>">
                    <button type="submit" id="btnConfirmDelete" class="btn btn-danger btn-sm px-3 fw-bold">Đồng ý Xóa</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>

    document.addEventListener('DOMContentLoaded', function () {
        
        // 1. Xử lý Modal EDIT
        var editModal = document.getElementById('editDeptModal');
        // Chỉ chạy JS này nếu không có lỗi validation từ server, để tránh ghi đè dữ liệu lỗi
        <?php if (!isset($data['validation_error_for']) || $data['validation_error_for'] !== 'edit'): ?>
        if(editModal) {
            editModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                
                // Lấy data từ nút bấm
                var id = button.getAttribute('data-id');
                var code = button.getAttribute('data-code');
                var name = button.getAttribute('data-name');
                
                // Lấy thêm các field mới
                var parentId = button.getAttribute('data-parent-id');
                var managerId = button.getAttribute('data-manager-id');
                var managerName = button.getAttribute('data-manager-name');
                var buyerId = button.getAttribute('data-buyer-id');
                var buyerName = button.getAttribute('data-buyer-name');
                var description = button.getAttribute('data-description');
                var status = button.getAttribute('data-status');
                
                // Fill vào form
                editModal.querySelector('#editCode').value = code;
                editModal.querySelector('#editName').value = name;
                editModal.querySelector('#editDescription').value = description;
                
                // Set Selected cho Dropdown
                editModal.querySelector('#editParentId').value = parentId ? parentId : '';
                
                // Set Smart Select Value
                setSmartSelectValue(document.getElementById('editManagerSelect'), managerId, managerName);
                setSmartSelectValue(document.getElementById('editBuyerSelect'), buyerId, buyerName);

                editModal.querySelector('#editStatus').value = status ? status : 'active';
                
                // Update Action Form
                editModal.querySelector('#editDeptForm').action = '<?= url('/admin') ?>/departments/edit/' + id;
            });
        }
        <?php endif; ?>

        // 2. Xử lý Modal DELETE
        var deleteModal = document.getElementById('deleteDeptModal');
        if(deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var id = button.getAttribute('data-id');
                var code = button.getAttribute('data-code');
                var name = button.getAttribute('data-name');
                var count = parseInt(button.getAttribute('data-count'));
                
                deleteModal.querySelector('#delDeptName').textContent = name; // Name is already htmlspecialchared in the button
                deleteModal.querySelector('#delDeptCode').textContent = code;
                deleteModal.querySelector('#deleteDeptForm').action = '<?= url('/admin') ?>/departments/delete/' + id;

                var warningEl = deleteModal.querySelector('#userCountWarning');
                var btnConfirm = deleteModal.querySelector('#btnConfirmDelete');
                var countSpan = deleteModal.querySelector('#delUserCount');

                if (count > 0) {
                    warningEl.classList.remove('d-none');
                    countSpan.textContent = count;
                    btnConfirm.style.display = 'none'; // Ẩn nút xóa nếu có nhân viên
                } else {
                    warningEl.classList.add('d-none');
                    btnConfirm.style.display = 'inline-block';
                }
            });
        }

        // 3. [NEW] Tree view toggle (Vanilla JS)
        var table = document.getElementById('tblItems');
        if (table) {
            table.addEventListener('click', function(e) {
                // Xử lý nút Toggle Tree
                var toggleBtn = e.target.closest('.btn-toggle-children');
                if (toggleBtn) {
                    e.preventDefault();
                    var parentId = toggleBtn.getAttribute('data-id');
                    var icon = toggleBtn.querySelector('i');
                    
                    if (icon.classList.contains('fa-plus-square')) {
                        // --- EXPAND ---
                        icon.classList.remove('fa-plus-square', 'text-secondary');
                        icon.classList.add('fa-minus-square', 'text-primary');
                        
                        var children = document.querySelectorAll('tr[data-parent-id="' + parentId + '"]');
                        children.forEach(function(child) {
                            child.classList.remove('d-none');
                        });
                    } else {
                        // --- COLLAPSE ---
                        icon.classList.remove('fa-minus-square', 'text-primary');
                        icon.classList.add('fa-plus-square', 'text-secondary');
                        
                        // Hide all descendants recursively
                        var queue = [parentId];
                        while(queue.length > 0) {
                            var currentId = queue.shift();
                            var children = document.querySelectorAll('tr[data-parent-id="' + currentId + '"]');
                            
                            children.forEach(function(child) {
                                child.classList.add('d-none');
                                // Reset icon
                                var childIcon = child.querySelector('.btn-toggle-children i');
                                if (childIcon) {
                                    childIcon.classList.remove('fa-minus-square', 'text-primary');
                                    childIcon.classList.add('fa-plus-square', 'text-secondary');
                                }
                                // Add to queue
                                var childId = child.getAttribute('data-id');
                                if (childId) queue.push(childId);
                            });
                        }
                    }
                }

                // 4. [NEW] Handle Add Child button (Vanilla JS)
                var addBtn = e.target.closest('.btn-add-child');
                if (addBtn) {
                    var pId = addBtn.getAttribute('data-parent-id');
                    var addModalEl = document.getElementById('addDeptModal');
                    var form = addModalEl.querySelector('form');
                    var select = addModalEl.querySelector('select[name="parent_id"]');
                    
                    if(form) form.reset();
                    // Reset Smart Select
                    setSmartSelectValue(document.getElementById('addManagerSelect'), '');
                    setSmartSelectValue(document.getElementById('addBuyerSelect'), '');
                    if(select) select.value = pId;
                    
                    var modalInstance = new bootstrap.Modal(addModalEl);
                    modalInstance.show();
                }
            });
        }

        // 5. [NEW] Smart Select Logic
        function initSmartSelect(container, url, emptyLabel) {
            if (!container) return;
            const input = container.querySelector('.search-input');
            const hidden = container.querySelector('.value-input');
            const dropdown = container.querySelector('.search-select-dropdown');
            let debounceTimer;

            function fetchOptions(keyword) {
                dropdown.innerHTML = '';
                dropdown.classList.remove('d-none');
                dropdown.innerHTML = '<div class="p-2 text-muted small fst-italic">Đang tìm kiếm...</div>';

                fetch(url + '?q=' + encodeURIComponent(keyword))
                    .then(response => response.json())
                    .then(data => {
                        dropdown.innerHTML = '';
                        
                        // Add "No selection" option
                        const defaultOption = document.createElement('div');
                        defaultOption.className = 'search-select-item text-muted fst-italic';
                        defaultOption.textContent = emptyLabel;
                        defaultOption.onclick = () => selectItem('', '');
                        dropdown.appendChild(defaultOption);

                        if (data.length === 0) {
                            dropdown.innerHTML += '<div class="p-2 text-muted small text-center">Không tìm thấy kết quả</div>';
                            return;
                        }

                        data.forEach(m => {
                            const item = document.createElement('div');
                            item.className = 'search-select-item';
                            item.textContent = m.text;
                            item.onclick = () => selectItem(m.id, m.text);
                            dropdown.appendChild(item);
                        });
                    })
                    .catch(err => {
                        dropdown.innerHTML = '<div class="p-2 text-danger small">Lỗi tải dữ liệu</div>';
                    });
            }

            function selectItem(id, text) {
                hidden.value = id;
                input.value = text;
                dropdown.classList.add('d-none');
            }

            input.addEventListener('focus', () => {
                if(input.value.length === 0) fetchOptions(''); // Load default/recent if needed or just wait for type
            });

            input.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    fetchOptions(input.value);
                }, 300); // Debounce 300ms
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!container.contains(e.target)) {
                    dropdown.classList.add('d-none');
                }
            });
        }

        // Init for both modals
        initSmartSelect(document.getElementById('addManagerSelect'), '<?= url('/admin') ?>/departments/ajax_search_managers', '-- Chưa bổ nhiệm --');
        initSmartSelect(document.getElementById('editManagerSelect'), '<?= url('/admin') ?>/departments/ajax_search_managers', '-- Chưa bổ nhiệm --');
        initSmartSelect(document.getElementById('addBuyerSelect'), '<?= url('/admin') ?>/departments/ajax_search_buyers', '-- Chưa phân công --');
        initSmartSelect(document.getElementById('editBuyerSelect'), '<?= url('/admin') ?>/departments/ajax_search_buyers', '-- Chưa phân công --');
    });

    // Helper to set value programmatically (for Edit Modal)
    function setSmartSelectValue(container, id, name = '') {
        if (!container) return;
        const input = container.querySelector('.search-input');
        const hidden = container.querySelector('.value-input');
        hidden.value = id;
        
        if (!id) {
            input.value = '';
            return;
        }
        
        // Use provided name directly to avoid lookup
        input.value = name ? name : 'ID: ' + id;
    }

    // 3. [NEW] Tự động mở Modal nếu có lỗi validation từ Server
    <?php if (isset($data['validation_error_for'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($data['validation_error_for'] === 'add'): ?>
            var addModal = new bootstrap.Modal(document.getElementById('addDeptModal'));
            addModal.show();
        <?php elseif ($data['validation_error_for'] === 'edit' && isset($data['error_id'])): ?>
            var editModal = new bootstrap.Modal(document.getElementById('editDeptModal'));
            // Cập nhật lại action của form cho đúng ID bị lỗi
            var form = document.getElementById('editDeptForm');
            if (form) {
                form.action = '<?= url('/admin') ?>/departments/edit/<?php echo $data['error_id']; ?>';
            }
            editModal.show();
        <?php endif; ?>
    });
    <?php endif; ?>
</script>

