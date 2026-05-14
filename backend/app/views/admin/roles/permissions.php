
<?php

// Map new router variables to the expected variables
$focusRoleId = $focusRoleId ?? 0;
$role = null;
foreach ($roles as $r) {
    if ($r['id'] == $focusRoleId) {
        $role = $r;
        break;
    }
}
if (!$role && !empty($roles)) {
    $role = $roles[0];
    $focusRoleId = $role['id'];
}
if (!$role) die('Role not found');

$grouped_permissions = $groups ?? [];
$role_permissions = isset($matrix[$focusRoleId]) ? array_keys($matrix[$focusRoleId]) : [];
$isAdmin = ($role['id'] == 1);

/**
 * Oracle-style Permission Matrix UI
 * - Left sidebar: Module tree with accordion + search
 * - Right panel: Permission detail grid with sub-groups
 * - Summary stats per module (progress bars)
 * - Bulk grant/revoke per module
 * - Real-time search filter
 * - Change tracking with floating save indicator
 */

// Hàm dịch tên module sang tiếng Việt + icon (đồng bộ menu_structure)
function translateModuleName($key) {
    $map = [
        'Sales'      => ['Kinh doanh (Sales)', 'fas fa-chart-pie'],
        'Production' => ['Sản xuất (Production)', 'fas fa-industry'],
        'Inventory'  => ['Kho Vận (Logistics)', 'fas fa-warehouse'],
        'Quality'    => ['Chất lượng (QA/QC)', 'fas fa-check-circle'],
        'Purchasing' => ['Mua hàng (Purchase)', 'fas fa-shopping-cart'],
        'Finance'    => ['Tài chính - Kế toán', 'fas fa-balance-scale'],
        'Asset'      => ['Quản lý Tài sản', 'fas fa-cube'],
        'PM'         => ['Quản lý Dự án (PM)', 'fas fa-project-diagram'],
        'HR'         => ['Nhân sự (HRM)', 'fas fa-users'],
        'MasterData' => ['Dữ liệu nguồn', 'fas fa-database'],
        'Admin'      => ['Quản trị Hệ thống', 'fas fa-user-shield'],
    ];
    return $map[$key] ?? [ucfirst($key), 'fas fa-folder'];
}

// Hàm dịch tên sub-group sang tiếng Việt
function translateSubGroupName($key) {
    $map = [
        // --- Chung ---
        'general'       => 'Chức năng chung',
        'config'        => 'Cấu hình',
        'dashboard'     => 'Dashboard',
        'report'        => 'Báo cáo',

        // --- Sales sub-groups (prefix-based) ---
        'sales'              => 'Đơn hàng & Báo giá (Chung)',
        'pricelist'          => 'Bảng giá (Price List)',
        'customer_pricing'   => 'Giá khách hàng (Tiers)',
        'quote'              => 'Báo giá (SQ)',
        'order'              => 'Đơn hàng (SO)',

        // --- Production sub-groups (prefix-based) ---
        'planning'       => 'Kế hoạch SX (MPS)',
        'mrp'            => 'Hoạch định MRP',
        'workorder'      => 'Lệnh sản xuất (WO)',
        'bom'            => 'Định mức (BOM)',
        'production'     => 'Vận hành SX',
        'productionconfig' => 'Cấu hình SX',
        'shopfloor'      => 'Sàn sản xuất (Shop Floor)',
        'drawings'       => 'Bản vẽ kỹ thuật',
        'op_attribute_set' => 'Thuộc tính Công đoạn',

        // --- Inventory sub-groups (prefix-based) ---
        'stock'          => 'Giao dịch kho & Tồn kho',
        'stockcard'      => 'Thẻ kho',
        'receipt'        => 'Nhập kho (GRN)',
        'transfer'       => 'Điều chuyển kho (TRF)',
        'trip'           => 'Chuyến giao hàng (Trip)',
        'opening_stock'  => 'Số dư đầu kỳ',
        'adjustment'     => 'Điều chỉnh tồn kho (ADJ)',
        'gi_request'     => 'YC Xuất kho (GIR / Pick Release)',
        'gi_sales'       => 'Xác nhận Xuất kho (GI Sales)',
        'pick'           => 'Soạn hàng (Pick Confirm)',
        'material_req'   => 'Yêu cầu xuất NVL (MR)',
        'material_issue' => 'Phiếu xuất NVL (MI)',
        'material_return'=> 'Hoàn trả NVL (MR Return)',
        'wip_completion' => 'Nhập kho Thành phẩm (WC)',
        'pda'            => 'PDA / Mobile WMS',
        'warehouse'      => 'Kho bãi',
        'inventory'      => 'Cấu hình Kho',

        // --- Quality sub-groups ---
        'qa'             => 'Kiểm tra chất lượng (QA)',
        'qc_type'        => 'QC Types (Master)',

        // --- Purchasing sub-groups (prefix-based) ---
        'purchasing'         => 'Mua hàng (Chung)',
        'pr'                 => 'Yêu cầu mua (PR)',
        'po'                 => 'Đơn đặt hàng (PO)',
        'purchase_pricelist' => 'Bảng giá mua (Purchase PL)',
        'purchase_return'    => 'Trả hàng NCC (RTV)',

        // --- Finance sub-groups (parts[1]-based) ---
        'accounting'     => 'Kế toán (Chung)',
        'view_journal'   => 'Sổ Nhật ký',
        'create_journal' => 'Bút toán thủ công',
        'view_reports'   => 'Báo cáo Tài chính',
        'view_coa'       => 'Hệ thống Tài khoản',
        'manage_coa'     => 'Quản lý Tài khoản',
        'view_ap_invoice'  => 'Hoá đơn Phải trả (AP)',
        'create_ap_invoice' => 'Tạo AP Invoice',
        'view_ap_payment'  => 'Thanh toán NCC (AP Payment)',
        'create_ap_payment' => 'Tạo AP Payment',
        'tax'            => 'Thuế (Tax)',
        'cost_center'    => 'Trung tâm Chi phí',
        'rules'          => 'Quy tắc Định khoản',
        'project'        => 'Dự án Kế toán',
        'lock_period'    => 'Khóa sổ',
        'manage_gl_period' => 'Kỳ kế toán',
        'manage_exchange_rate' => 'Tỷ giá hối đoái',
        'manage_payment_term' => 'Điều khoản thanh toán',

        // --- Asset sub-groups (parts[1]-based) ---
        'asset'          => 'Tài sản (Chung)',
        'maintenance'    => 'Bảo trì',
        'revalue'        => 'Đánh giá lại',

        // --- PM sub-groups (parts[1]-based) ---
        'pm'             => 'Dự án (Chung)',
        'member'         => 'Thành viên',
        'task'           => 'Công việc',
        'payment'        => 'Thanh toán Milestone',
        'warranty'       => 'Bảo hành',
        'acceptance'     => 'Nghiệm thu',
        'complaint'      => 'Khiếu nại',

        // --- HR sub-groups ---
        'hr'             => 'Nhân sự (Chung)',
        'employee'       => 'Nhân viên',
        'contract'       => 'Hợp đồng',
        'payroll'        => 'Tiền lương',
        'workshift'      => 'Ca làm việc',
        'attendance'     => 'Chấm công',
        'symbol'         => 'Ký hiệu công',
        'holiday'        => 'Ngày lễ',
        'job_title'      => 'Chức danh',
        'leavetype'      => 'Loại phép',
        'leave'          => 'Nghỉ phép',
        'leavebalance'   => 'Quỹ phép',
        'overtime'       => 'Tăng ca',
        'performance'    => 'Đánh giá hiệu suất',
        'machine'        => 'Máy chấm công',
        'fingerprint'    => 'Vân tay',
        'backup'         => 'Đồng bộ Log Backup',
        'approval'       => 'Quy trình duyệt',

        // --- MasterData sub-groups (prefix-based) ---
        'product'        => 'Sản phẩm & Hàng hóa',
        'partner'        => 'Đối tác (KH & NCC)',
        'masterdata'     => 'Kho bãi & Vị trí',
        'tooling'        => 'Khuôn mẫu (Tooling)',
        'category'       => 'Nhóm sản phẩm',
        'attribute_set'  => 'Bộ thuộc tính',
        'uom'            => 'Đơn vị tính (UOM)',

        // --- Admin sub-groups (prefix-based) ---
        'system'         => 'Hệ thống (Chung)',
        'user'           => 'Người dùng',
        'role'           => 'Vai trò & Quyền',
        'department'     => 'Phòng ban',
        'settings'       => 'Cài đặt',
        'profile'        => 'Hồ sơ cá nhân',
        'print_label'    => 'In tem / QR',
        'site'           => 'Nhà máy (Site)',
        'migration'      => 'Migration & Schema',
        'cache'          => 'Cache hệ thống',
        'lookup'         => 'Sys Lookups',
        'module'         => 'Module & Tính năng',
        'integration'    => 'Tích hợp bên ngoài',
    ];
    return $map[strtolower($key)] ?? ucfirst(str_replace('_', ' ', $key));
}

function permissionIconClass(string $code): string
{
    $parts = explode('.', strtolower($code));
    $action = end($parts) ?: 'default';

    $map = [
        'view' => 'fas fa-eye',
        'list' => 'fas fa-list',
        'create' => 'fas fa-plus-circle',
        'add' => 'fas fa-plus-circle',
        'store' => 'fas fa-plus-circle',
        'edit' => 'fas fa-pen',
        'update' => 'fas fa-pen',
        'delete' => 'fas fa-trash-alt',
        'remove' => 'fas fa-trash-alt',
        'approve' => 'fas fa-check-circle',
        'reject' => 'fas fa-ban',
        'manage' => 'fas fa-sliders-h',
        'config' => 'fas fa-cog',
        'settings' => 'fas fa-cog',
        'sync' => 'fas fa-sync-alt',
        'run' => 'fas fa-play-circle',
        'generate' => 'fas fa-magic',
        'schedule' => 'fas fa-calendar-alt',
        'unlock' => 'fas fa-unlock-alt',
        'toggle' => 'fas fa-toggle-on',
    ];

    return $map[$action] ?? 'fas fa-key';
}

// Xây dựng grouped data với thống kê
$moduleStats = [];
$isAdmin = ($role['id'] == 1);

foreach ($grouped_permissions as $groupKey => $perms) {
    $granted = 0;
    foreach ($perms as $p) {
        if ($isAdmin || in_array($p['id'], $role_permissions)) {
            $granted++;
        }
    }
    $moduleStats[$groupKey] = [
        'total' => count($perms),
        'granted' => $granted,
        'percent' => count($perms) > 0 ? round(($granted / count($perms)) * 100) : 0
    ];
}

$totalPerms = 0;
$totalGranted = 0;
foreach ($moduleStats as $s) {
    $totalPerms += $s['total'];
    $totalGranted += $s['granted'];
}
$totalPct = $totalPerms > 0 ? round(($totalGranted / $totalPerms) * 100) : 0;
?>

<style>
:root {
    --sidebar-width: 320px;
    --toolbar-height: 56px;
    --perm-primary: var(--accent);
    --perm-primary-strong: #0891b2;
    --perm-primary-light: rgba(6,182,212,0.14);
    --perm-success: var(--success);
    --perm-warning: var(--warning);
    --perm-danger: var(--danger);
    --perm-surface: var(--bg-surface);
    --perm-surface-2: var(--bg-elevated);
    --perm-surface-3: var(--bg-hover);
    --perm-border: var(--border);
    --perm-border-strong: var(--border-hover);
    --perm-text: var(--text);
    --perm-text-soft: var(--text-sec);
    --perm-text-muted: var(--text-muted);
    --perm-shadow: 0 16px 40px rgba(0,0,0,0.22);
}
body {
    background:
        radial-gradient(circle at top left, rgba(34,211,238,0.08), transparent 24%),
        radial-gradient(circle at bottom right, rgba(139,92,246,0.08), transparent 20%),
        var(--bg-base);
}

/* ==================== TOOLBAR ==================== */
.perm-toolbar {
    background: linear-gradient(180deg, rgba(26,29,50,0.96) 0%, rgba(19,21,37,0.96) 100%);
    border: 1px solid var(--perm-border);
    border-radius: 18px 18px 0 0;
    padding: 10px 24px;
    position: relative;
    box-shadow: var(--perm-shadow);
    display: flex; justify-content: space-between; align-items: center;
    height: var(--toolbar-height);
}
.perm-toolbar .role-title { font-size: 1rem; font-weight: 700; color: var(--perm-text); margin: 0; }
.perm-toolbar .role-subtitle { font-size: 0.75rem; color: var(--perm-text-soft); }

/* ==================== LAYOUT ==================== */
.perm-layout {
    display: flex;
    min-height: 0;
    border: 1px solid var(--perm-border);
    border-top: none;
    border-radius: 0 0 18px 18px;
    overflow: visible;
    background: linear-gradient(180deg, rgba(19,21,37,0.98) 0%, rgba(11,13,23,0.98) 100%);
    box-shadow: var(--perm-shadow);
    align-items: flex-start;
}

/* ==================== SIDEBAR ==================== */
.perm-sidebar {
    width: var(--sidebar-width); min-width: var(--sidebar-width);
    background: linear-gradient(180deg, rgba(26,29,50,0.88) 0%, rgba(15,17,40,0.88) 100%);
    border-right: 1px solid var(--perm-border);
    overflow: hidden;
    position: sticky;
    top: 24px;
    align-self: flex-start;
    border-radius: 0 0 0 18px;
}
.sidebar-header { padding: 16px; border-bottom: 1px solid var(--perm-border); background: rgba(255,255,255,0.02); }
.sidebar-search { position: relative; }
.sidebar-search input {
    width: 100%; padding: 8px 12px 8px 36px;
    border: 1px solid var(--perm-border-strong); border-radius: 10px;
    font-size: 0.825rem; background: rgba(11,13,23,0.8);
    color: var(--perm-text);
    transition: border-color 0.15s, box-shadow 0.15s;
}
.sidebar-search input::placeholder { color: var(--perm-text-muted); }
.sidebar-search input:focus { outline: none; border-color: var(--perm-primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
.sidebar-search .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--perm-text-muted); font-size: 0.8rem; }
.sidebar-summary {
    padding: 10px 16px; border-bottom: 1px solid var(--perm-border);
    background: linear-gradient(135deg, rgba(6,182,212,0.15), rgba(139,92,246,0.12)); font-size: 0.75rem;
    color: #8ae8f7; font-weight: 700;
    display: flex; justify-content: space-between; align-items: center;
}

/* Module list */
.module-list { list-style: none; padding: 0; margin: 0; }
.module-item { border-bottom: 1px solid rgba(255,255,255,0.04); cursor: pointer; transition: background 0.1s; }
.module-item:hover { background: rgba(255,255,255,0.03); }
.module-item.active { background: linear-gradient(90deg, rgba(6,182,212,0.14), rgba(6,182,212,0.04)); border-left: 3px solid var(--perm-primary); }
.module-item.active .module-name { color: var(--perm-primary); font-weight: 700; }
.module-link { display: flex; align-items: center; padding: 10px 16px; text-decoration: none; color: inherit; gap: 12px; }
.module-icon {
    width: 32px; height: 32px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.8rem; background: rgba(255,255,255,0.05); color: var(--perm-text-soft); flex-shrink: 0;
}
.module-item.active .module-icon { background: var(--perm-primary); color: #fff; }
.module-info { flex: 1; min-width: 0; }
.module-name { font-size: 0.825rem; font-weight: 600; color: var(--perm-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.module-count { font-size: 0.7rem; color: var(--perm-text-soft); }
.module-progress { width: 44px; flex-shrink: 0; text-align: right; }
.module-progress .progress { height: 4px; border-radius: 2px; background: rgba(255,255,255,0.08); margin-bottom: 2px; }
.module-progress .progress-bar { border-radius: 2px; }
.module-pct { font-size: 0.65rem; color: var(--perm-text-soft); font-weight: 600; }

/* ==================== MAIN CONTENT ==================== */
.perm-main {
    flex: 1; padding: 24px; overflow: visible;
    background:
        radial-gradient(circle at top right, rgba(34,211,238,0.08), transparent 30%),
        radial-gradient(circle at bottom left, rgba(139,92,246,0.08), transparent 28%),
        rgba(11,13,23,0.78);
    border-radius: 0 0 18px 0;
}

.main {
    background:
        radial-gradient(circle at top left, rgba(34,211,238,0.07), transparent 22%),
        radial-gradient(circle at bottom right, rgba(139,92,246,0.07), transparent 18%),
        var(--bg-base);
}
.module-header-card {
    background: linear-gradient(180deg, rgba(26,29,50,0.96) 0%, rgba(19,21,37,0.96) 100%);
    border-radius: 14px; border: 1px solid var(--perm-border);
    padding: 20px 24px; margin-bottom: 20px;
    display: flex; align-items: center; justify-content: space-between;
}
.module-header-card h5 { margin: 0; font-weight: 700; color: var(--perm-text); font-size: 1rem; }
.module-header-card .module-meta { font-size: 0.8rem; color: var(--perm-text-soft); margin-top: 2px; }
.module-header-card .bulk-actions { display: flex; gap: 8px; }
.module-header-card .bulk-actions .btn { font-size: 0.75rem; font-weight: 600; padding: 4px 12px; border-radius: 6px; }

/* Sub-group cards */
.subgroup-card { background: linear-gradient(180deg, rgba(26,29,50,0.9) 0%, rgba(19,21,37,0.9) 100%); border-radius: 12px; border: 1px solid var(--perm-border); margin-bottom: 16px; overflow: hidden; transition: box-shadow 0.15s, border-color 0.15s; }
.subgroup-card:hover { box-shadow: 0 12px 26px rgba(0,0,0,0.18); border-color: var(--perm-border-strong); }
.subgroup-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 16px; background: rgba(255,255,255,0.03);
    border-bottom: 1px solid var(--perm-border);
    cursor: pointer; user-select: none;
}
.subgroup-header:hover { background: rgba(255,255,255,0.05); }
.subgroup-title { font-size: 0.8rem; font-weight: 700; color: var(--perm-text); text-transform: uppercase; letter-spacing: 0.3px; }
.subgroup-badge { font-size: 0.7rem; padding: 2px 8px; border-radius: 10px; font-weight: 600; }
.subgroup-toggle { font-size: 0.7rem; color: var(--perm-text-soft); transition: transform 0.2s; }
.subgroup-toggle.collapsed { transform: rotate(-90deg); }
.subgroup-body {
    padding: 14px;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

/* Permission row */
.perm-row {
    display: flex; align-items: center; padding: 12px 14px;
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 12px;
    transition: background 0.1s, border-color 0.1s, transform 0.1s; cursor: pointer; gap: 12px;
    min-height: 72px;
    background: rgba(7, 12, 24, 0.42);
}
.perm-row:hover { background: rgba(255,255,255,0.05); border-color: rgba(6,182,212,0.28); transform: translateY(-1px); }
.perm-row.is-checked { background: linear-gradient(135deg, rgba(6,182,212,0.16), rgba(6,182,212,0.05)); border-color: rgba(6,182,212,0.26); }
.perm-row .form-check-input {
    width: 18px; height: 18px; border-radius: 4px;
    border: 2px solid rgba(148,163,184,0.45); cursor: pointer; flex-shrink: 0; margin: 0;
    background-color: rgba(11,13,23,0.8);
}
.perm-row .form-check-input:checked { background-color: var(--perm-primary); border-color: var(--perm-primary); }
.perm-row .perm-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: #8ae8f7;
    background: rgba(6,182,212,0.12);
    border: 1px solid rgba(6,182,212,0.18);
}
.perm-row .perm-copy {
    min-width: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.perm-row .perm-code {
    font-family: 'SF Mono','Fira Code',monospace;
    font-size: 0.72rem; color: #8ae8f7;
    background: rgba(6,182,212,0.12); padding: 3px 8px;
    border-radius: 4px; white-space: nowrap; flex-shrink: 0; min-width: 160px;
    border: 1px solid rgba(6,182,212,0.16);
    display: inline-flex;
    width: fit-content;
    max-width: 100%;
}
.perm-row .perm-label { font-size: 0.84rem; color: var(--perm-text); flex: 1; line-height: 1.45; }
.perm-row.is-checked .perm-label { color: #d7f9ff; font-weight: 600; }

/* No results */
.perm-no-results { text-align: center; padding: 60px 20px; color: var(--perm-text-soft); }
.perm-no-results i { font-size: 3rem; margin-bottom: 16px; opacity: 0.3; }

/* Floating change counter */
.change-counter {
    position: fixed; bottom: 24px; right: 24px; z-index: 999;
    background: var(--perm-primary); color: #fff;
    border-radius: 16px; padding: 12px 24px;
    box-shadow: 0 8px 24px rgba(37,99,235,0.35);
    font-size: 0.85rem; font-weight: 600;
    display: none; align-items: center; gap: 12px;
    animation: permSlideUp 0.3s ease;
}
@keyframes permSlideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.change-counter .btn { font-size: 0.8rem; font-weight: 700; }

/* Responsive */
@media (max-width: 992px) {
    .perm-sidebar { width: 260px; min-width: 260px; }
    .subgroup-body { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .perm-layout { flex-direction: column; }
    .perm-sidebar { width: 100%; min-width: 100%; position: relative; top: 0; max-height: none; border-right: none; border-bottom: 1px solid var(--perm-border); border-radius: 0; }
    .perm-toolbar { height: auto; gap: 12px; align-items: flex-start; flex-direction: column; }
    .module-header-card { flex-direction: column; align-items: flex-start; gap: 12px; }
    .subgroup-body { padding: 10px; gap: 10px; }
    .perm-row { padding: 12px; min-height: 68px; }
    .perm-main { border-radius: 0 0 18px 18px; }
}
</style>

<form id="syncPermissionConfigForm" action="<?= url('/admin/roles/sync') ?>" method="POST" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= e((string)($_SESSION['csrf_token'] ?? '')) ?>">
    <input type="hidden" name="redirect_to" value="<?= e(url('/admin/roles/permissions/' . (int)$role['id'])) ?>">
</form>

<div class="container-fluid p-0">
<form id="permissionForm" action="<?= url('/admin/roles/permissions/' . (int)$role['id']) ?>" method="POST">
    <input type="hidden" name="csrf_token" value="<?= e((string)($_SESSION['csrf_token'] ?? '')) ?>">

    <!-- ==================== TOOLBAR ==================== -->
    <div class="perm-toolbar">
        <div class="d-flex align-items-center gap-3">
            <a href="<?= url('/admin/roles') ?>" class="btn btn-light btn-sm border-0 shadow-sm" title="Quay lại danh sách">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <div class="role-title">
                    <?= htmlspecialchars($role['name']); ?>
                    <?php if ($isAdmin): ?>
                        <span class="badge bg-danger ms-2" style="font-size:0.65rem;">SYSTEM ADMIN</span>
                    <?php endif; ?>
                </div>
                <div class="role-subtitle">
                    Permission Matrix &middot; Code: <?= htmlspecialchars($role['code'] ?? $role['name']); ?>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-light btn-sm border fw-bold text-secondary" onclick="confirmPermissionSync()">
                <i class="fas fa-sync-alt me-1"></i> Đồng bộ quyền
            </button>
            <?php if (!$isAdmin): ?>
                <span id="changeIndicator" class="badge bg-warning text-dark me-2" style="display:none; font-size:0.72rem;">
                    <i class="fas fa-pen me-1"></i> <span id="changeCount">0</span> thay đổi
                </span>
                <button type="button" class="btn btn-light btn-sm border fw-bold text-secondary" onclick="resetPermForm()">
                    <i class="fas fa-undo-alt me-1"></i> Hủy
                </button>
                <button type="button" class="btn btn-primary btn-sm fw-bold px-4 shadow-sm" onclick="confirmPermSave()">
                    <i class="fas fa-save me-1"></i> LƯU CẤU HÌNH
                </button>
            <?php else: ?>
                <span class="badge bg-danger p-2 shadow-sm">
                    <i class="fas fa-lock me-1"></i> FULL ACCESS — Không thể chỉnh sửa
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ==================== MAIN LAYOUT ==================== -->
    <div class="perm-layout">

        <!-- ==================== SIDEBAR ==================== -->
        <div class="perm-sidebar">
            <div class="sidebar-header">
                <div class="sidebar-search">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="permSearch" placeholder="Tìm quyền (tên hoặc code)..." autocomplete="off">
                </div>
            </div>

            <div class="sidebar-summary">
                <span><i class="fas fa-shield-alt me-1"></i> Tổng: <span id="globalGranted"><?= $totalGranted; ?></span> / <?= $totalPerms; ?> quyền</span>
                <span id="globalPct"><?= $totalPct; ?>%</span>
            </div>

            <ul class="module-list" id="moduleList">
                <?php
                $first = true;
                foreach ($grouped_permissions as $groupKey => $perms):
                    $moduleInfo = translateModuleName($groupKey);
                    $stats = $moduleStats[$groupKey];
                    $tabId = 'module-' . md5($groupKey);
                    $pctClass = $stats['percent'] == 100 ? 'bg-success' : ($stats['percent'] > 0 ? 'bg-primary' : 'bg-secondary');
                ?>
                    <li class="module-item <?= $first ? 'active' : ''; ?>" data-target="<?= $tabId; ?>" data-group="<?= strtolower($groupKey); ?>">
                        <a class="module-link" href="javascript:void(0)">
                            <div class="module-icon"><i class="<?= $moduleInfo[1]; ?>"></i></div>
                            <div class="module-info">
                                <div class="module-name"><?= $moduleInfo[0]; ?></div>
                                <div class="module-count">
                                    <span class="module-stat-granted" data-group="<?= $tabId; ?>"><?= $stats['granted']; ?></span> / <?= $stats['total']; ?> quyền
                                </div>
                            </div>
                            <div class="module-progress">
                                <div class="progress">
                                    <div class="progress-bar <?= $pctClass; ?> module-pbar" data-group="<?= $tabId; ?>"
                                         style="width: <?= $stats['percent']; ?>%"></div>
                                </div>
                                <div class="module-pct module-pct-text" data-group="<?= $tabId; ?>"><?= $stats['percent']; ?>%</div>
                            </div>
                        </a>
                    </li>
                <?php $first = false; endforeach; ?>
            </ul>
        </div>

        <!-- ==================== MAIN CONTENT ==================== -->
        <div class="perm-main" id="permMainContent">
            <?php if (function_exists('flash')) flash('role_msg'); ?>

            <?php
            $first = true;
            foreach ($grouped_permissions as $groupKey => $perms):
                $moduleInfo = translateModuleName($groupKey);
                $stats = $moduleStats[$groupKey];
                $tabId = 'module-' . md5($groupKey);

                // Build sub-groups từ permission code
                // Nhóm gom nhiều prefix → prefix làm sub-group
                // Nhóm đơn prefix (HR, Asset, PM) → parts[1] làm sub-group
                $singlePrefixGroups = ['Asset', 'PM'];
                $subGroups = [];
                foreach ($perms as $p) {
                    $parts = explode('.', $p['code']);
                    $prefix = $parts[0];

                    if (in_array($groupKey, $singlePrefixGroups)) {
                        // Nhóm đơn prefix: dùng parts[1] nếu có 3+ parts
                        $subName = (count($parts) >= 3) ? $parts[1] : 'general';
                    } elseif (count($parts) >= 3) {
                        // Nhóm multi-prefix nhưng permission có 3+ parts
                        // VD: sales.quote.view → quote, accounting.tax.manage → tax, product.category.view → category
                        $subName = $parts[1];
                    } else {
                        // 2-part code: dùng prefix làm sub-group
                        // VD: receipt.view → receipt, pr.create → pr
                        $subName = $prefix;
                    }
                    $subGroups[$subName][] = $p;
                }
                // Đưa General lên đầu
                if (isset($subGroups['general'])) {
                    $gen = $subGroups['general'];
                    unset($subGroups['general']);
                    $subGroups = ['general' => $gen] + $subGroups;
                }
            ?>
                <div class="module-panel" id="<?= $tabId; ?>" style="<?= $first ? '' : 'display:none;'; ?>">

                    <!-- Module Header Card -->
                    <div class="module-header-card">
                        <div>
                            <h5><i class="<?= $moduleInfo[1]; ?> me-2 text-primary"></i> <?= $moduleInfo[0]; ?></h5>
                            <div class="module-meta">
                                Module: <strong><?= htmlspecialchars($groupKey); ?></strong> &middot;
                                <span class="panel-stat-granted" data-group="<?= $tabId; ?>"><?= $stats['granted']; ?></span> / <?= $stats['total']; ?> quyền được cấp
                            </div>
                        </div>
                        <?php if (!$isAdmin): ?>
                        <div class="bulk-actions">
                            <button type="button" class="btn btn-outline-success btn-sm btn-grant-all" data-panel="<?= $tabId; ?>">
                                <i class="fas fa-check-double me-1"></i> Cấp tất cả
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm btn-revoke-all" data-panel="<?= $tabId; ?>">
                                <i class="fas fa-times me-1"></i> Thu hồi
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sub-group Cards -->
                    <?php foreach ($subGroups as $subKey => $subPerms):
                        $subId = $tabId . '-sub-' . md5($subKey);
                        $subGranted = 0;
                        foreach ($subPerms as $sp) {
                            if ($isAdmin || in_array($sp['id'], $role_permissions)) $subGranted++;
                        }
                        $subTotal = count($subPerms);
                    ?>
                        <div class="subgroup-card" data-subgroup="<?= $subId; ?>">
                            <div class="subgroup-header" onclick="toggleSubgroup('<?= $subId; ?>')">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-chevron-down subgroup-toggle" id="toggle-<?= $subId; ?>"></i>
                                    <span class="subgroup-title"><?= translateSubGroupName($subKey); ?></span>
                                    <span class="subgroup-badge <?= $subGranted == $subTotal ? 'bg-success text-white' : ($subGranted > 0 ? 'text-primary' : 'border text-muted'); ?>">
                                        <span class="sub-stat" data-sub="<?= $subId; ?>"><?= $subGranted; ?></span>/<?= $subTotal; ?>
                                    </span>
                                </div>
                                <?php if (!$isAdmin): ?>
                                <div>
                                    <input type="checkbox" class="form-check-input subgroup-check-all"
                                           data-subgroup="<?= $subId; ?>"
                                           <?= ($subGranted == $subTotal) ? 'checked' : ''; ?>
                                           title="Chọn/Bỏ chọn tất cả nhóm này"
                                           onclick="event.stopPropagation();">
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="subgroup-body" id="body-<?= $subId; ?>">
                                <?php foreach ($subPerms as $p):
                                    $isChecked = $isAdmin || in_array($p['id'], $role_permissions);
                                    $displayName = preg_replace('/^\[.*?\]\s*/', '', $p['name']);
                                    $permIcon = permissionIconClass($p['code']);
                                ?>
                                    <label class="perm-row <?= $isChecked ? 'is-checked' : ''; ?>"
                                           data-code="<?= strtolower($p['code']); ?>"
                                           data-name="<?= strtolower($displayName); ?>">
                                        <input type="checkbox" class="form-check-input item-checkbox"
                                               name="perm[]" value="<?= $p['id']; ?>"
                                               data-panel="<?= $tabId; ?>" data-sub="<?= $subId; ?>"
                                               <?= $isChecked ? 'checked' : ''; ?>
                                               <?= $isAdmin ? 'disabled checked' : ''; ?>>
                                        <span class="perm-icon"><i class="<?= $permIcon; ?>"></i></span>
                                        <span class="perm-copy">
                                            <span class="perm-code"><?= htmlspecialchars($p['code']); ?></span>
                                            <span class="perm-label"><?= htmlspecialchars($displayName); ?></span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php $first = false; endforeach; ?>

            <!-- No results placeholder -->
            <div class="perm-no-results" id="noResults" style="display:none;">
                <i class="fas fa-search d-block"></i>
                <h6>Không tìm thấy quyền nào</h6>
                <p class="text-muted small">Thử tìm kiếm với từ khóa khác</p>
            </div>
        </div>
    </div>

    <!-- Floating change counter -->
    <?php if (!$isAdmin): ?>
    <div class="change-counter" id="floatingChangeCounter">
        <i class="fas fa-pen-fancy"></i>
        <span><span id="floatChangeCount">0</span> thay đổi chưa lưu</span>
        <button type="button" class="btn btn-light btn-sm" onclick="confirmPermSave()">
            <i class="fas fa-save me-1"></i> Lưu ngay
        </button>
    </div>
    <?php endif; ?>

</form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // ========================================================
    // 1. SIDEBAR NAVIGATION - Click module to show panel
    // ========================================================
    const moduleItems = document.querySelectorAll('.module-item');
    const modulePanels = document.querySelectorAll('.module-panel');

    moduleItems.forEach(item => {
        item.addEventListener('click', function() {
            const target = this.dataset.target;

            moduleItems.forEach(m => m.classList.remove('active'));
            this.classList.add('active');

            modulePanels.forEach(p => p.style.display = 'none');
            const panel = document.getElementById(target);
            if (panel) panel.style.display = 'block';
        });
    });

    // ========================================================
    // 2. SEARCH - Filter permissions across all modules
    // ========================================================
    const searchInput = document.getElementById('permSearch');
    let searchTimeout;

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => performSearch(this.value.trim()), 200);
    });

    function performSearch(query) {
        const noResults = document.getElementById('noResults');

        if (query.length === 0) {
            // Reset: restore sidebar + show active module panel
            modulePanels.forEach(p => p.style.display = 'none');
            const activeItem = document.querySelector('.module-item.active');
            if (activeItem) {
                document.getElementById(activeItem.dataset.target).style.display = 'block';
            } else if (modulePanels.length > 0) {
                modulePanels[0].style.display = 'block';
                moduleItems[0]?.classList.add('active');
            }

            document.querySelectorAll('.perm-row').forEach(r => r.style.display = 'flex');
            document.querySelectorAll('.subgroup-card').forEach(c => c.style.display = 'block');
            document.querySelectorAll('.module-header-card').forEach(c => c.style.display = 'flex');
            noResults.style.display = 'none';
            moduleItems.forEach(m => { m.style.display = ''; m.style.opacity = '1'; });
            return;
        }

        const q = query.toLowerCase();
        let found = 0;
        let visibleModules = new Set();

        // Show all panels for cross-module search
        modulePanels.forEach(p => p.style.display = 'block');
        document.querySelectorAll('.module-header-card').forEach(c => c.style.display = 'none');

        // Filter permission rows
        document.querySelectorAll('.perm-row').forEach(row => {
            const code = row.dataset.code || '';
            const name = row.dataset.name || '';

            if (code.includes(q) || name.includes(q)) {
                row.style.display = 'flex';
                found++;
                const panel = row.closest('.module-panel');
                if (panel) visibleModules.add(panel.id);
            } else {
                row.style.display = 'none';
            }
        });

        // Hide empty subgroup cards
        document.querySelectorAll('.subgroup-card').forEach(card => {
            const visible = card.querySelectorAll('.perm-row[style*="flex"]');
            card.style.display = visible.length > 0 ? 'block' : 'none';
        });

        // Hide empty module panels
        modulePanels.forEach(panel => {
            if (!visibleModules.has(panel.id)) panel.style.display = 'none';
        });

        // Dim non-matching sidebar items
        moduleItems.forEach(item => {
            item.style.opacity = visibleModules.has(item.dataset.target) ? '1' : '0.35';
            item.style.display = '';
        });

        noResults.style.display = found === 0 ? 'block' : 'none';
    }

    // ========================================================
    // 3. CHANGE TRACKING - Track checkbox changes from original
    // ========================================================
    const allCheckboxes = document.querySelectorAll('.item-checkbox');
    const originalState = {};
    let changeCount = 0;

    allCheckboxes.forEach(cb => { originalState[cb.value] = cb.checked; });

    function updateChangeCount() {
        changeCount = 0;
        allCheckboxes.forEach(cb => {
            if (cb.checked !== originalState[cb.value]) changeCount++;
        });

        const indicator = document.getElementById('changeIndicator');
        const countEl = document.getElementById('changeCount');
        const floater = document.getElementById('floatingChangeCounter');
        const floatCount = document.getElementById('floatChangeCount');

        if (indicator) {
            indicator.style.display = changeCount > 0 ? 'inline-block' : 'none';
            if (countEl) countEl.textContent = changeCount;
        }
        if (floater) {
            floater.style.display = changeCount > 0 ? 'flex' : 'none';
            if (floatCount) floatCount.textContent = changeCount;
        }
    }

    allCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const row = this.closest('.perm-row');
            if (row) row.classList.toggle('is-checked', this.checked);

            updateSubgroupCheckAll(this.dataset.sub);
            updateModuleStats(this.dataset.panel);
            updateGlobalStats();
            updateChangeCount();
        });
    });

    // ========================================================
    // 4. SUBGROUP CHECK-ALL - Toggle all in a sub-group
    // ========================================================
    document.querySelectorAll('.subgroup-check-all').forEach(checkAll => {
        checkAll.addEventListener('change', function() {
            const subId = this.dataset.subgroup;
            const body = document.getElementById('body-' + subId);
            if (!body) return;

            const cbs = body.querySelectorAll('.item-checkbox:not(:disabled)');
            cbs.forEach(cb => {
                cb.checked = this.checked;
                const row = cb.closest('.perm-row');
                if (row) row.classList.toggle('is-checked', cb.checked);
            });

            if (cbs.length > 0) {
                updateModuleStats(cbs[0].dataset.panel);
                updateGlobalStats();
                updateChangeCount();
            }
        });
    });

    function updateSubgroupCheckAll(subId) {
        if (!subId) return;
        const body = document.getElementById('body-' + subId);
        const checkAll = document.querySelector('.subgroup-check-all[data-subgroup="' + subId + '"]');
        if (!body || !checkAll) return;

        const cbs = body.querySelectorAll('.item-checkbox');
        const checked = body.querySelectorAll('.item-checkbox:checked').length;

        checkAll.checked = (checked === cbs.length && cbs.length > 0);
        checkAll.indeterminate = (checked > 0 && checked < cbs.length);

        // Update sub-stat badge text
        const badge = document.querySelector('.sub-stat[data-sub="' + subId + '"]');
        if (badge) badge.textContent = checked;

        // Update badge color
        const card = document.querySelector('.subgroup-card[data-subgroup="' + subId + '"]');
        if (card) {
            const badgeEl = card.querySelector('.subgroup-badge');
            if (badgeEl) {
                badgeEl.className = 'subgroup-badge ' + (
                    checked === cbs.length ? 'bg-success text-white' :
                    checked > 0 ? 'text-primary' :
                    'border text-muted'
                );
            }
        }
    }

    // ========================================================
    // 5. BULK ACTIONS - Grant All / Revoke All per module
    // ========================================================
    document.querySelectorAll('.btn-grant-all').forEach(btn => {
        btn.addEventListener('click', function() {
            const panelId = this.dataset.panel;
            const panel = document.getElementById(panelId);
            if (!panel) return;

            panel.querySelectorAll('.item-checkbox:not(:disabled)').forEach(cb => {
                cb.checked = true;
                const row = cb.closest('.perm-row');
                if (row) row.classList.add('is-checked');
            });
            panel.querySelectorAll('.subgroup-check-all').forEach(sa => { sa.checked = true; sa.indeterminate = false; });

            updateModuleStats(panelId);
            updateGlobalStats();
            updateChangeCount();
        });
    });

    document.querySelectorAll('.btn-revoke-all').forEach(btn => {
        btn.addEventListener('click', function() {
            const panelId = this.dataset.panel;
            const panel = document.getElementById(panelId);
            if (!panel) return;

            panel.querySelectorAll('.item-checkbox:not(:disabled)').forEach(cb => {
                cb.checked = false;
                const row = cb.closest('.perm-row');
                if (row) row.classList.remove('is-checked');
            });
            panel.querySelectorAll('.subgroup-check-all').forEach(sa => { sa.checked = false; sa.indeterminate = false; });

            updateModuleStats(panelId);
            updateGlobalStats();
            updateChangeCount();
        });
    });

    // ========================================================
    // 6. STATS UPDATE - Recalculate sidebar & header stats
    // ========================================================
    function updateModuleStats(panelId) {
        if (!panelId) return;
        const panel = document.getElementById(panelId);
        if (!panel) return;

        const total = panel.querySelectorAll('.item-checkbox').length;
        const checked = panel.querySelectorAll('.item-checkbox:checked').length;
        const pct = total > 0 ? Math.round((checked / total) * 100) : 0;

        // Update sidebar stats text
        document.querySelectorAll('.module-stat-granted[data-group="' + panelId + '"]').forEach(el => el.textContent = checked);
        document.querySelectorAll('.module-pct-text[data-group="' + panelId + '"]').forEach(el => el.textContent = pct + '%');

        // Update progress bar
        const pbar = document.querySelector('.module-pbar[data-group="' + panelId + '"]');
        if (pbar) {
            pbar.style.width = pct + '%';
            pbar.className = 'progress-bar module-pbar ' + (pct === 100 ? 'bg-success' : pct > 0 ? 'bg-primary' : 'bg-secondary');
            pbar.dataset.group = panelId;
        }

        // Update panel header stats
        document.querySelectorAll('.panel-stat-granted[data-group="' + panelId + '"]').forEach(el => el.textContent = checked);

        // Update sub-group badges
        panel.querySelectorAll('.subgroup-card').forEach(card => {
            updateSubgroupCheckAll(card.dataset.subgroup);
        });
    }

    function updateGlobalStats() {
        let totalGranted = 0;
        const totalPerms = allCheckboxes.length;
        allCheckboxes.forEach(cb => { if (cb.checked) totalGranted++; });
        const pct = totalPerms > 0 ? Math.round((totalGranted / totalPerms) * 100) : 0;

        const globalGranted = document.getElementById('globalGranted');
        const globalPct = document.getElementById('globalPct');
        if (globalGranted) globalGranted.textContent = totalGranted;
        if (globalPct) globalPct.textContent = pct + '%';
    }

    // ========================================================
    // 7. KEYBOARD SHORTCUTS
    // ========================================================
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 's') { e.preventDefault(); if (changeCount > 0) confirmPermSave(); }
        if (e.ctrlKey && e.key === 'f') { e.preventDefault(); searchInput.focus(); }
        if (e.key === 'Escape' && document.activeElement === searchInput) { searchInput.value = ''; performSearch(''); }
    });

    // Init: set subgroup indeterminate states on page load
    document.querySelectorAll('.subgroup-check-all').forEach(sa => {
        updateSubgroupCheckAll(sa.dataset.subgroup);
    });
});

// ========================================================
// GLOBAL FUNCTIONS (accessible from onclick attributes)
// ========================================================
function safeConfirmDialog(options, onConfirm) {
    if (window.Swal && typeof window.Swal.fire === 'function') {
        window.Swal.fire(options).then(function(result) {
            if (result.isConfirmed) onConfirm();
        });
        return;
    }

    var message = (options.title || 'Xác nhận') + '\n\n' + (options.text || '');
    if (window.confirm(message)) {
        onConfirm();
    }
}

function showSavingDialog() {
    if (window.Swal && typeof window.Swal.fire === 'function') {
        window.Swal.fire({ title: 'Đang xử lý...', allowOutsideClick: false, didOpen: function() { window.Swal.showLoading(); } });
    }
}

function toggleSubgroup(subId) {
    const body = document.getElementById('body-' + subId);
    const toggle = document.getElementById('toggle-' + subId);
    if (!body) return;
    if (body.style.display === 'none') {
        body.style.display = 'block';
        if (toggle) toggle.classList.remove('collapsed');
    } else {
        body.style.display = 'none';
        if (toggle) toggle.classList.add('collapsed');
    }
}

function confirmPermSave() {
    safeConfirmDialog({
        title: 'Lưu cấu hình phân quyền?',
        text: 'Quyền hạn sẽ được cập nhật ngay lập tức cho tất cả người dùng thuộc vai trò này.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-save me-1"></i> Đồng ý lưu',
        cancelButtonText: 'Hủy'
    }, function() {
        showSavingDialog();
        document.getElementById('permissionForm').submit();
    });
}

function confirmPermissionSync() {
    safeConfirmDialog({
        title: 'Đồng bộ danh mục quyền?',
        text: 'Hệ thống sẽ đọc lại file cấu hình và cập nhật bảng permissions trong database.',
        icon: 'question',
        showCancelButton: true,
        background: 'var(--perm-surface, #111827)',
        color: 'var(--perm-gray-900)',
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fas fa-sync-alt me-1"></i> Đồng bộ',
        cancelButtonText: 'Hủy'
    }, function() {
        document.getElementById('syncPermissionConfigForm').submit();
    });
}

function resetPermForm() {
    safeConfirmDialog({
        title: 'Hủy thay đổi?',
        text: 'Các lựa chọn chưa lưu sẽ bị mất.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Đồng ý',
        cancelButtonText: 'Không'
    }, function() {
        window.location.reload();
    });
}
</script>
