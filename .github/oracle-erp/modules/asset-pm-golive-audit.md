# Asset & PM Modules — Go-Live Remaining Issues Audit

> **Ngày audit:** 2026-07-12
> **Module:** Asset (30% score) + PM (55% score)
> **Module tham chiếu:** Purchasing (100% — chuẩn Oracle)
> **Mục tiêu:** Liệt kê toàn bộ remaining issues cần fix trước go-live, sắp xếp theo severity
> **Phạm vi Asset:** 5 Controllers (2,199L), 3 Models (2,118L), 3 Services (413L), 41 Views, 10 JS — **0 DTOs, 0 Requests, 0 Helpers**
> **Phạm vi PM:** 8 Controllers (3,112L), 10 Models (1,308L), 12 Services (2,744L), 1 Helper (228L unused), 26 Views, 8 JS — **0 DTOs, 0 Requests**

---

## Tổng Kết Issues

| Module | CRITICAL | HIGH | MEDIUM | LOW | Total |
|--------|----------|------|--------|-----|-------|
| **Asset** | 5 | 9 | 8 | 4 | **26** |
| **PM** | 6 | 8 | 9 | 3 | **26** |
| **TOTAL** | **11** | **17** | **17** | **7** | **52** |

### Khu vực ĐẠT chuẩn (Clean)

**Asset:**
- SQL Injection: 0 — tất cả queries dùng parameterized binding `:param` ✅
- `$_SESSION['user_site_id']`: 0 instances — dùng `$this->getCurrentSiteId()` ✅
- Site scoping: BaseModel `$isSiteSpecific = true` trên AssetModel ✅
- Permission checks: Có ở tất cả action methods ✅
- CSRF validation: `$this->validateCSRF()` trên tất cả POST actions ✅
- View `asset_v()`: Consistent ✅
- No `Database::getInstance()` trong controllers — dùng `$this->db` ✅

**PM:**
- Auth check: Tất cả 8 controllers check `isLoggedIn()` trong constructor ✅
- Permissions: `requirePermission()` trên tất cả mutation methods ✅
- CSRF: `$this->validateCSRF()` trên tất cả POST handlers ✅
- SQL Safety: Tất cả queries dùng parameterized binds — 0 string concatenation ✅
- DocumentSequence: Dùng cho acceptance, complaints, warranty claims ✅
- WorkflowEngine: Integrated cho project + acceptance approvals ✅
- Show partials: project/show dùng 11 `_show_*.php` partials ✅
- `asset_v()`: Tất cả views dùng versioned assets ✅
- Transactions: Services dùng `beginTransaction/commit/rollBack` properly ✅
- Audit Logging: Models set `$useAuditLog = true` where appropriate ✅

---

# ═══════════════════════════════════════════
# ASSET MODULE — 26 Issues
# ═══════════════════════════════════════════

## A-P0 — CRITICAL (5 issues — PHẢI fix trước go-live)

### A-P0-1. Division-by-zero — 7 SQL expressions không có NULLIF

**Files:** `app/models/asset/AssetModel.php`, `app/controllers/asset/ReportController.php`
**Risk:** SQL error crash khi `useful_life_months = 0` hoặc NULL — production error 500

| # | File | Line | Expression | Fix |
|---|------|------|-----------|-----|
| 1 | AssetModel.php | L343 | `(2.0 / a.useful_life_months)` | `(2.0 / NULLIF(a.useful_life_months, 0))` |
| 2 | AssetModel.php | L347 | `(a.original_cost - a.salvage_value) / a.useful_life_months` | `/ NULLIF(a.useful_life_months, 0)` |
| 3 | AssetModel.php | L353 | `(2.0 / a.useful_life_months)` | `/ NULLIF(a.useful_life_months, 0)` |
| 4 | AssetModel.php | L357 | `(a.original_cost - a.salvage_value) / a.useful_life_months` | `/ NULLIF(a.useful_life_months, 0)` |
| 5 | AssetModel.php | L364 | `(2.0 / a.useful_life_months)` | `/ NULLIF(a.useful_life_months, 0)` |
| 6 | AssetModel.php | L368 | `(a.original_cost - a.salvage_value) / a.useful_life_months` | `/ NULLIF(a.useful_life_months, 0)` |
| 7 | ReportController.php | L69 | `(a.original_cost / a.useful_life_months) as monthly_depreciation` | `/ NULLIF(a.useful_life_months, 0)` |

**Status:** [ ] NOT FIXED

---

### A-P0-2. No DB transactions — 4 multi-step operations race condition

**File:** `app/models/asset/AssetModel.php`
**Risk:** Data corruption nếu 2 users cùng handover/dispose/revalue — partial writes

| # | Method | Line | Operations | Fix |
|---|--------|------|-----------|-----|
| 1 | `handover_asset()` | L495 | INSERT `asset_handovers` + UPDATE `assets` | Wrap `beginTransaction()/commit()/rollBack()` |
| 2 | `dispose_asset()` | L589 | INSERT `asset_disposals` + UPDATE `assets` | Wrap `beginTransaction()/commit()/rollBack()` |
| 3 | `revalue_asset()` | L1070 | INSERT `asset_revaluations` + UPDATE `assets` | Wrap `beginTransaction()/commit()/rollBack()` |
| 4 | `upgrade_asset()` | L1169 | Multiple INSERT/UPDATE | Wrap `beginTransaction()/commit()/rollBack()` |

**Ghi chú:** Hiện tại 0 instances `beginTransaction` trong toàn bộ AssetModel (1,346 lines).

**Status:** [ ] NOT FIXED

---

### A-P0-3. `$useAuditLog` not set on AssetModel

**File:** `app/models/asset/AssetModel.php` L14-20
**Risk:** Asset CRUD (create/update/delete) KHÔNG ghi sys_audit_logs — mất audit trail cho tài sản cố định

```php
// Hiện tại:
protected $table = 'assets';
protected $primaryKey = 'id';
protected $isSiteSpecific = true;
protected $useSoftDeletes = true;
// THIẾU: protected $useAuditLog = true;
```

**Fix:** Thêm `protected $useAuditLog = true;`

**Status:** [ ] NOT FIXED

---

### A-P0-4. Raw SQL trong DashboardController — 7 queries trực tiếp

**File:** `app/controllers/asset/DashboardController.php` (242 lines)
**Risk:** Vi phạm "No SQL in Controller" — SQL nằm trực tiếp trong controller, không qua Model/Service

| # | Line | Method | Query Description |
|---|------|--------|------------------|
| 1 | L83 | `getAssetStats()` | COUNT assets by status |
| 2 | L106 | `getCategoryBreakdown()` | GROUP BY category |
| 3 | L129 | `getLocationBreakdown()` | GROUP BY location |
| 4 | L149 | `getTopValueAssets()` | TOP 10 by value |
| 5 | L171 | `getMonthlyDepreciation()` | Monthly depreciation chart |
| 6 | L195 | `getRecentActivities()` | Recent audit logs |
| 7 | L225 | `ajax_depreciation_chart()` | AJAX chart data |

**Fix:**
1. Tạo `app/helpers/asset/AssetDashboardHelper.php` — move 7 queries vào static methods
2. Controller chỉ gọi `AssetDashboardHelper::getAssetStats($siteId)`
3. Tham chiếu: `app/helpers/inventory/InventoryDashboardHelper.php` (14 KPI methods)

**Status:** [ ] NOT FIXED

---

### A-P0-5. FILTER_SANITIZE_FULL_SPECIAL_CHARS nukes POST data

**File:** `app/controllers/asset/MaintenanceController.php` L104
**Risk:** `filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS)` double-encodes numeric fields (`estimated_cost`, `actual_cost`), deprecated PHP 8.1. Breaks data integrity.

```php
// Hiện tại L104:
$_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// Fix: Xóa dòng này, dùng specific sanitization per field:
$title = trim($_POST['title'] ?? '');
$estimated_cost = (float)($_POST['estimated_cost'] ?? 0);
// Hoặc dùng Request validation class
```

**Status:** [ ] NOT FIXED

---

## A-P1 — HIGH (9 issues — Cần fix sớm)

### A-P1-1. Mega Model — AssetModel.php 1,346 lines, 39 methods

**File:** `app/models/asset/AssetModel.php`
**Vi phạm:** Single Responsibility Principle — 1 model chứa: CRUD + depreciation + handover + disposal + revalue + upgrade + categories + locations + images + reports + statistics

**Fix — Split thành 5+ models:**

| Model mới | Methods di chuyển | Approx Lines |
|-----------|------------------|-------------|
| `AssetModel.php` (giữ lại) | `create_asset`, `update_asset`, `get_all_assets`, `count_assets`, `get_asset_detail`, `delete_asset`, `generate_asset_code`, `update_qr_code` | ~300 |
| `AssetDepreciationModel.php` | `calculate_depreciation_for_period`, `calculate_monthly_depreciation`, `get_month_depreciations`, `get_month_depreciation_total`, `get_depreciation_history`, `getDepreciationByMonth` | ~250 |
| `AssetTransactionModel.php` | `handover_asset`, `get_handover_history`, `dispose_asset`, `get_disposal_record`, `revalue_asset`, `get_revaluation_history`, `upgrade_asset` | ~350 |
| `AssetConfigModel.php` | `get_asset_categories`, `add_category`, `delete_category`, `get_asset_locations`, `add_location`, `get_chart_of_accounts`, `get_employees` | ~200 |
| `AssetImageModel.php` | `getAssetImages`, `addAssetImage`, `deleteAssetImage`, `setPrimaryImage` | ~100 |
| `AssetReportModel.php` | `getStats`, `getAssetsByDepartment`, `getAssetsByEmployee`, `get_asset_register`, `get_increase_decrease_report`, `get_warranty_expiring`, `get_insurance_expiring` | ~200 |

**Status:** [ ] NOT FIXED

---

### A-P1-2. No AssetConstants — ~20 hardcoded status strings

**Files:** AssetModel.php, ManagerController.php, DashboardController.php, ReportController.php, MaintenanceController.php
**Vi phạm:** Config-Driven Architecture — KHÔNG hardcode status/type/dropdown

**Hardcoded strings found:**
- `'active'`, `'inactive'`, `'in_use'`, `'disposed'`, `'draft'`, `'pending'`, `'maintenance'`
- Status labels: `'Sẵn sàng'`, `'Đang sử dụng'`, `'Đã thanh lý'` (ManagerController L651)

**Fix:**
1. Tạo `app/helpers/asset/AssetConstants.php`:
```php
class AssetConstants {
    // Asset Status
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_IN_USE = 'in_use';
    const STATUS_DISPOSED = 'disposed';
    const STATUS_MAINTENANCE = 'maintenance';

    const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Sẵn sàng',
        self::STATUS_INACTIVE => 'Ngừng sử dụng',
        self::STATUS_IN_USE => 'Đang sử dụng',
        self::STATUS_DISPOSED => 'Đã thanh lý',
        self::STATUS_MAINTENANCE => 'Đang bảo trì',
    ];

    // Maintenance Status
    const MAINT_DRAFT = 'draft';
    const MAINT_PENDING = 'pending';
    const MAINT_IN_PROGRESS = 'in_progress';
    const MAINT_COMPLETED = 'completed';
    const MAINT_CANCELLED = 'cancelled';

    // Depreciation Method
    const DEPRECIATION_STRAIGHT_LINE = 'straight_line';
    const DEPRECIATION_DECLINING = 'declining_balance';
    const DEPRECIATION_SUM_YEARS = 'sum_of_years';
}
```
2. Replace ~20 instances across all Asset files

**Status:** [ ] NOT FIXED

---

### A-P1-3. No checkLockedDate() cho financial transactions

**Files:** AssetModel.php `dispose_asset()`, `revalue_asset()`, `upgrade_asset()`
**Risk:** Depreciation/disposal/revalue có thể post vào locked GL period — sai số kế toán

**Fix:** Thêm `$this->checkLockedDate($transactionDate)` trước mỗi financial operation trong Controller.
Tham chiếu: `app/controllers/finance/JournalEntryController.php` pattern.

**Status:** [ ] NOT FIXED

---

### A-P1-4. Duplicate requirePermission() trong 4 controllers

**Files:**
- `DashboardController.php` L244
- `ReportController.php` L260
- `MaintenanceController.php` L404
- `InventoryController.php` L218

**Risk:** Custom implementation bypass base Controller error handling (redirect vs JSON response)

**Fix:** Xóa 4 custom `requirePermission()` methods — base Controller đã có sẵn.

**Status:** [ ] NOT FIXED

---

### A-P1-5. Raw SQL trong ReportController

**File:** `app/controllers/asset/ReportController.php` L79
**Risk:** SQL trực tiếp trong controller — vi phạm architecture rule

**Fix:** Di chuyển query vào `AssetReportModel.php` (A-P1-1 sẽ tạo model này)

**Status:** [ ] NOT FIXED

---

### A-P1-6. No Request validation classes

**Missing:** `app/requests/asset/`
**Risk:** Validation logic inline trong controllers — duplicated, inconsistent

**Fix:**
1. Tạo `app/requests/asset/StoreAssetRequest.php` — cho create
2. Tạo `app/requests/asset/UpdateAssetRequest.php` — cho edit
3. Tạo `app/requests/asset/StoreMaintenanceRequest.php` — cho maintenance

**Status:** [ ] NOT FIXED

---

### A-P1-7. No DTOs

**Missing:** `app/dtos/asset/`
**Risk:** Data transformation inline — complex data mapping trong controller

**Fix:** Tạo `app/dtos/asset/AssetDTO.php` cho import/export transformation

**Status:** [ ] NOT FIXED

---

### A-P1-8. No race-safe writes (missing FOR UPDATE)

**File:** `app/models/asset/AssetModel.php`
**Risk:** Concurrent handover/dispose — 2 users cùng dispose 1 asset

**Fix:** Thêm `SELECT ... FOR UPDATE` + WHERE guard pattern cho `handover_asset()`, `dispose_asset()`, `revalue_asset()`:
```php
$this->db->query("SELECT id, status FROM assets WHERE id = :id FOR UPDATE");
$this->db->bind(':id', $id);
$asset = $this->db->single();
if ($asset->status === 'disposed') {
    throw new BusinessException('Tài sản đã thanh lý');
}
```

**Status:** [ ] NOT FIXED

---

### A-P1-9. Hard DELETE trên asset_images

**File:** `app/models/asset/AssetModel.php` L930
**Vi phạm:** "UPDATE not DELETE+INSERT" rule

```php
// Hiện tại L930:
$this->db->query("DELETE FROM asset_images WHERE id = :id");

// Fix: Soft delete
$this->db->query("UPDATE asset_images SET deleted_at = NOW() WHERE id = :id");
```

**Status:** [ ] NOT FIXED

---

## A-P2 — MEDIUM (8 issues)

### A-P2-1. Mega Controller — ManagerController.php 1,106 lines, 28 methods

**Fix — Split:**
- `AssetConfigController.php` ← `categories()`, `locations()`, `store_location()`, `store_category()`, `delete_category()`
- `AssetImportController.php` ← `import()`, `processImportFile()`, `download_sample()`
- `AssetTransactionController.php` ← `handover()`, `dispose()`, `revalue()`, `upgrade()`, `warranty_alert()`
- `ManagerController.php` (giữ lại) ← CRUD, `show()`, `export()`, `scanner()`, `upload_image()`

**Status:** [ ] NOT FIXED

---

### A-P2-2. Custom log_audit() thay vì writeAuditLog()

**File:** `app/controllers/asset/ManagerController.php` L1151
**Fix:** Xóa `log_audit()` private method, dùng `$this->writeAuditLog()` từ base Controller

**Status:** [ ] NOT FIXED

---

### A-P2-3. Hardcoded status dropdowns trong views

**Files:** `app/views/asset/maintenance/_form.php`
**Vi phạm:** 8 statuses, 4 priorities, 4 types hardcoded trong HTML options

**Fix:** Load từ `sys_lookups` via Controller → `$data['statuses']`, `$data['priorities']`

**Status:** [ ] NOT FIXED

---

### A-P2-4. XSS risk trong maintenance form errors

**File:** `app/views/asset/maintenance/_form.php`
**Pattern:** `<?= $errors['asset_id'] ?>` — không dùng `e()`

**Fix:** `<?= e($errors['asset_id'] ?? '') ?>`

**Status:** [ ] NOT FIXED

---

### A-P2-5. No DashboardHelper (inline SQL)

**Fix:** Tạo `app/helpers/asset/AssetDashboardHelper.php` (ref: InventoryDashboardHelper.php)

**Status:** [ ] NOT FIXED (liên quan A-P0-4)

---

### A-P2-6. No ExportService

**File:** Export logic inline trong `ManagerController.php` L602-685
**Fix:** Tạo `app/services/asset/AssetExportService.php`

**Status:** [ ] NOT FIXED

---

### A-P2-7. Fragile count SQL via str_replace

**File:** `app/models/asset/AssetInventoryModel.php`
**Risk:** `str_replace()` to convert SELECT → COUNT có thể fail với complex queries

**Fix:** Viết count query riêng (pattern: `paginateRaw($sql, $countSql, $params)`)

**Status:** [ ] NOT FIXED

---

### A-P2-8. Inline `<script>` blocks trong 10+ views

**Fix:** Di chuyển inline JS vào `public/js/modules/assets/` files tương ứng

**Status:** [ ] NOT FIXED

---

## A-P3 — LOW (4 issues)

### A-P3-1. Duplicate jsonResponse() trong DashboardController

Mirror cùng issue A-P1-4 (duplicate methods). Sẽ tự resolve khi fix A-P1-4.

### A-P3-2. Hard DELETE trên asset_categories

**File:** AssetModel.php L759 — có check FK trước khi xóa, acceptable cho config entity.

### A-P3-3. Double-sanitization (htmlspecialchars trên input trước khi lưu DB)

Pattern không gây lỗi nhưng corrupts display. Low priority.

### A-P3-4. MaintenanceWorkflowService dùng Database::getInstance()

**File:** `app/services/asset/MaintenanceWorkflowService.php` L23
**Fix:** Dùng `$this->db` qua constructor injection pattern.

---

# ═══════════════════════════════════════════
# PM MODULE — 26 Issues
# ═══════════════════════════════════════════

## PM-P0 — CRITICAL (6 issues — PHẢI fix trước go-live)

### PM-P0-1. Site isolation breach — `$_SESSION['user_site_id'] ?? 1`

**File:** `app/controllers/pm/WarrantyController.php` L42, L72
**Risk:** **MULTI-TENANT DATA LEAK** — fallback `?? 1` trả về site 1 data cho user không có session → cross-site data exposure

```php
// Hiện tại L42:
$siteId = $_SESSION['user_site_id'] ?? 1;

// Fix:
$siteId = $this->getCurrentSiteId();  // Throws exception nếu missing
```

**Cần fix ở cả 2 lines: L42 và L72**

**Status:** [ ] NOT FIXED

---

### PM-P0-2. PmConstants — 228 lines defined, ZERO usage

**File:** `app/helpers/pm/PmConstants.php` (228 lines)
**Evidence:** 0 results khi grep `PmConstants::` trong toàn bộ `app/controllers/pm/`, `app/models/pm/`, `app/services/pm/`, `app/views/pm/`
**Risk:** 50+ hardcoded status strings rải khắp module

**Hardcoded strings tìm thấy:**
| String | Locations |
|--------|-----------|
| `'active'` | ProjectController, DashboardController, ReportController, TaskService |
| `'completed'` | ProjectController, DashboardController, ReportController |
| `'DONE'` | TaskController, DashboardController |
| `'TODO'` | TaskController, ProjectController, DashboardController |
| `'OPEN'` | DashboardController, ReportController |
| `'PAID'` | DashboardController, ProjectController, PaymentMilestoneModel |
| `'IN_PROGRESS'` | DashboardController, ReportController |
| `'REVIEW'` | TaskController |
| `'ASSIGNED'` | TaskService |
| `'RESOLVED'` | ReportController |
| `'planning'` | ProjectController, ReportController |
| `'on_hold'` | DashboardController |
| `'template'` | ProjectController |
| `'PENDING'` | ProjectController |
| `'ACTIVE'` | WarrantyModel |
| `'RELEASED'` | WarrantyModel |

**Fix:** Replace tất cả ~50 instances bằng `PmConstants::PROJECT_STATUS_ACTIVE`, `PmConstants::TASK_STATUS_DONE`, etc.

**Status:** [ ] NOT FIXED

---

### PM-P0-3. DashboardController — 200 lines raw SQL, no service layer

**File:** `app/controllers/pm/DashboardController.php` (237 lines)
**Risk:** 10+ raw `$this->db->query()` calls inline — vi phạm "No SQL in Controller"

**Queries inline:**
1. Total projects by status
2. Active vs completed count
3. On-hold count
4. Total tasks by status (TODO/IN_PROGRESS/DONE)
5. Overdue tasks count
6. Total budget vs actual
7. Payment summary (PAID/OPEN)
8. Recent activities
9. Top projects by value
10. Timeline chart data

**Fix:** Tạo `app/helpers/pm/PmDashboardHelper.php` hoặc `app/services/pm/DashboardService.php`

**Status:** [ ] NOT FIXED

---

### PM-P0-4. ReportController — 200 lines raw SQL, no service layer

**File:** `app/controllers/pm/ReportController.php` (221 lines)
**Risk:** 8+ raw queries inline — toàn bộ report logic trong controller

**Fix:** Tạo `app/services/pm/ReportService.php` hoặc `app/helpers/pm/PmReportHelper.php`

**Status:** [ ] NOT FIXED

---

### PM-P0-5. Fat ProjectController — 1,168 lines, 22 methods

**File:** `app/controllers/pm/ProjectController.php`
**Risk:** Vi phạm thin-controller, chứa template/milestone/product/lookup logic

**Leaked business logic:**
| Method | Lines | Problem |
|--------|-------|---------|
| `show()` | ~240-320 | 10+ inline lookup queries |
| `addMilestone()` | ~600-640 | Raw INSERT + sequence calculation |
| `saveAsTemplate()` | ~920-990 | Raw INSERT loop for project cloning |
| `createFromTemplate()` | ~990-1050 | Raw INSERT loop from template |
| `searchProducts()` | ~Variable | Product search directly from DB |

**Fix — Delegate to services:**
- Move template logic → `ProjectTemplateService.php`
- Move milestone logic → `ProjectService.php` (existing)
- Move lookup queries → load in `show()` via models
- Move product search → `ProjectProductModel.php`

**Status:** [ ] NOT FIXED

---

### PM-P0-6. 19 `Database::getInstance()` calls across controllers

**Files:** ProjectController (~13), TaskController, ComplaintController, WarrantyController, DashboardController, ReportController
**Risk:** Bypasses BaseModel site scoping — potential multi-tenant leaks

**Fix:** Replace all `Database::getInstance()` calls with proper model queries hoặc service calls.

**Status:** [ ] NOT FIXED

---

## PM-P1 — HIGH (8 issues)

### PM-P1-1. No checkLockedDate() for financial operations

**File:** `app/services/pm/AdvancePaymentService.php` L30
**Risk:** Payments có thể post vào locked GL period

**Fix:** Thêm GL period check trước `recordAdvancePayment()`

**Status:** [ ] NOT FIXED

---

### PM-P1-2. No Request validation classes

**Missing:** `app/requests/pm/`
**Fix:**
- `StoreProjectRequest.php`
- `UpdateProjectRequest.php`
- `StoreTaskRequest.php`
- `StoreComplaintRequest.php`

**Status:** [ ] NOT FIXED

---

### PM-P1-3. No DTOs

**Missing:** `app/dtos/pm/`
**Fix:** `ProjectDTO.php` cho complex project data transformation

**Status:** [ ] NOT FIXED

---

### PM-P1-4. PMNotificationService extends BaseModel

**File:** `app/services/pm/PMNotificationService.php` L1
**Risk:** Architectural smell — Service KHÔNG nên extend Model

**Fix:** Inject `$db` qua constructor thay vì inherit BaseModel. Hoặc dùng `Database::getInstance()` (lesser pattern).

**Status:** [ ] NOT FIXED

---

### PM-P1-5. confirmByToken() — unauthenticated GET without rate-limit

**File:** `app/controllers/pm/ComplaintController.php` ~L180
**Risk:** Email confirmation bypass login — nếu token weak → enumerable

**Fix:**
1. Add `rate_limit('complaint_confirm', 10, 300)` — 10 attempts per 5 min
2. Verify token length/entropy is sufficient (UUID v4 minimum)
3. Expire tokens after 72 hours

**Status:** [ ] NOT FIXED

---

### PM-P1-6. JS monolith — project.js 2,036 lines

**File:** `public/js/modules/pm/project.js`
**Fix:** Split thành:
- `project.js` (~500L) — core CRUD
- `project-kanban.js` (~400L) — kanban board
- `project-template.js` (~300L) — save/create from template
- `project-milestone.js` (~300L) — milestones management
- `project-product.js` → already exists as `project_products.js`

**Status:** [ ] NOT FIXED

---

### PM-P1-7. Fat report view — report/index.php 803 lines

**File:** `app/views/pm/report/index.php`
**Risk:** 200 lines inline `<script>` with Chart.js logic

**Fix:**
1. Extract inline JS → `public/js/modules/pm/report.js`
2. Split view into `_report_summary.php`, `_report_charts.php`, `_report_tables.php`

**Status:** [ ] NOT FIXED

---

### PM-P1-8. Hard DELETEs in 2 models

**Files:**
- `app/models/pm/TaskAssigneeModel.php` `unassign()` — hard DELETE, loses audit trail
- `app/models/pm/ProjectProductModel.php` `removeProduct()` ~L90 — hard DELETE

**Fix:** Soft delete (add `deleted_at` column) hoặc chuyển sang UPDATE pattern

**Status:** [ ] NOT FIXED

---

## PM-P2 — MEDIUM (9 issues)

### PM-P2-1. Inline HTML email templates

**Files:**
- `app/services/pm/ComplaintService.php` ~L300-360 — 60 lines inline HTML
- `app/services/pm/PMNotificationService.php` ~L100-200 — 40 lines inline HTML each

**Fix:** Move to `app/views/pm/email/` templates (pattern: `PMEmailService.php` already does this correctly)

**Status:** [ ] NOT FIXED

---

### PM-P2-2. Inconsistent XSS escaping in views

**Files:** `app/views/pm/project/index.php`, `complaint/index.php`
**Pattern:** Some `<?= $data[...] ?>` without `e()`

**Fix:** Audit all PM views, add `e()` to all user-controlled outputs

**Status:** [ ] NOT FIXED

---

### PM-P2-3. Business logic in TaskController quickUpdate()

**File:** `app/controllers/pm/TaskController.php` ~L260-330
**Method:** Status validation, DONE auto-completion, review notification — all inline

**Fix:** Move to `TaskService.php`

**Status:** [ ] NOT FIXED

---

### PM-P2-4. Hardcoded strings in PaymentMilestoneModel

**File:** `app/models/pm/PaymentMilestoneModel.php` L80-90
**Strings:** `'PAID'`, `'PENDING'`
**Fix:** `PmConstants::PAYMENT_PAID`, `PmConstants::PAYMENT_PENDING`

**Status:** [ ] NOT FIXED

---

### PM-P2-5. Hardcoded strings in WarrantyModel

**File:** `app/models/pm/WarrantyModel.php` L40, L90
**Strings:** `'ACTIVE'`, `'RELEASED'`
**Fix:** `PmConstants::WARRANTY_ACTIVE`, `PmConstants::WARRANTY_RELEASED`

**Status:** [ ] NOT FIXED

---

### PM-P2-6. Hardcoded strings in TaskService

**File:** `app/services/pm/TaskService.php` ~L80
**Strings:** `'TODO'` → `'ASSIGNED'` auto-transition
**Fix:** `PmConstants::TASK_TODO`, `PmConstants::TASK_ASSIGNED`

**Status:** [ ] NOT FIXED

---

### PM-P2-7. ComplaintController raw lookup queries

**File:** `app/controllers/pm/ComplaintController.php` ~L35-85
**Count:** 5 raw SQL queries for dropdowns
**Fix:** Move to model `findAll()` calls hoặc lookup service

**Status:** [ ] NOT FIXED

---

### PM-P2-8. AcceptanceController raw SQL + direct session access

**File:** `app/controllers/pm/AcceptanceController.php` ~L38-60
**Issues:** 2 raw SQL queries + `$_SESSION['user_id']` thay vì `$this->getCurrentUserId()`

**Status:** [ ] NOT FIXED

---

### PM-P2-9. Complaints reuse warranty_claims table

**File:** `app/services/pm/ComplaintService.php` ~L35
**Design:** `pm_warranty_claims` với `warranty_id=NULL` → confusing semantics
**Fix:** Tạo bảng `pm_complaints` riêng (low priority, schema change)

**Status:** [ ] NOT FIXED

---

## PM-P3 — LOW (3 issues)

### PM-P3-1. TaskController constructor — 4 try/catch blocks for model init

Defensive but messy. Low priority refactor.

### PM-P3-2. TaskController logTime() — raw SQL for employee lookup

Move to model. Low priority.

### PM-P3-3. Backup file in views

**File:** `app/views/pm/project/show.php.backup_20260324`
**Fix:** Delete backup file — should be tracked in git, not as file copy.

**Status:** [ ] NOT FIXED

---

# ═══════════════════════════════════════════
# FIX PRIORITY ROADMAP
# ═══════════════════════════════════════════

## Phase 1 — Security & Data Integrity (BLOCKING go-live)

> **Estimated effort:** 2-3 sessions
> **Impact:** Fix production crashes + multi-tenant leaks + data corruption

| # | Issue | Module | Severity |
|---|-------|--------|----------|
| 1 | PM-P0-1 | PM | Site isolation breach `?? 1` |
| 2 | A-P0-1 | Asset | Division-by-zero (7 SQL expressions) |
| 3 | A-P0-2 | Asset | No transactions (4 multi-step operations) |
| 4 | A-P0-5 | Asset | FILTER_SANITIZE corrupts POST data |
| 5 | A-P0-3 | Asset | Missing `$useAuditLog = true` |
| 6 | A-P1-3 | Asset | No checkLockedDate() financial ops |
| 7 | PM-P1-1 | PM | No checkLockedDate() payments |
| 8 | A-P1-8 | Asset | No race-safe writes |
| 9 | PM-P1-5 | PM | confirmByToken() unauthenticated without rate-limit |

## Phase 2 — Architecture Compliance (Best practice)

> **Estimated effort:** 3-4 sessions
> **Impact:** Code maintainability + module independence

| # | Issue | Module | Severity |
|---|-------|--------|----------|
| 1 | A-P1-2 | Asset | Create AssetConstants.php + replace ~20 strings |
| 2 | PM-P0-2 | PM | Adopt PmConstants:: ~50 replacements |
| 3 | A-P0-4 | Asset | Extract DashboardHelper from DashboardController |
| 4 | PM-P0-3 | PM | Extract DashboardService from DashboardController |
| 5 | PM-P0-4 | PM | Extract ReportService from ReportController |
| 6 | A-P1-4 | Asset | Remove 4 duplicate requirePermission() |
| 7 | PM-P0-6 | PM | Remove 19 Database::getInstance() calls |
| 8 | PM-P1-4 | PM | Fix PMNotificationService extends BaseModel |

## Phase 3 — Module Splitting & Structure (Improve score)

> **Estimated effort:** 4-5 sessions
> **Impact:** Module scores Asset 30%→65%, PM 55%→80%

| # | Issue | Module | Severity |
|---|-------|--------|----------|
| 1 | A-P1-1 | Asset | Split AssetModel 1,346L → 5+ models |
| 2 | A-P2-1 | Asset | Split ManagerController 1,106L → 4 controllers |
| 3 | PM-P0-5 | PM | Slim ProjectController 1,168L → delegate services |
| 4 | A-P1-6 | Asset | Create Request validation classes |
| 5 | PM-P1-2 | PM | Create Request validation classes |
| 6 | A-P2-6 | Asset | Create AssetExportService |
| 7 | PM-P1-7 | PM | Split report view 803L |
| 8 | PM-P1-6 | PM | Split project.js 2,036L |

## Phase 4 — Polish & Remaining Items

> **Estimated effort:** 2 sessions
> **Impact:** Module scores Asset 65%→80%, PM 80%→90%

| # | Issue | Module |
|---|-------|--------|
| 1 | A-P1-7 | Asset — Create DTOs |
| 2 | PM-P1-3 | PM — Create DTOs |
| 3 | A-P1-9 | Asset — Soft delete asset_images |
| 4 | PM-P1-8 | PM — Soft delete in 2 models |
| 5 | A-P2-3 | Asset — Remove hardcoded dropdowns in views |
| 6 | A-P2-4 | Asset — Fix XSS in error messages |
| 7 | PM-P2-1 | PM — Email templates to view files |
| 8 | PM-P2-2 | PM — XSS audit all views |
| 9 | A-P2-2 | Asset — Remove custom log_audit() |
| 10 | A-P2-8 | Asset — Move inline scripts to JS files |
| 11 | PM-P3-3 | PM — Delete backup file |

---

# ═══════════════════════════════════════════
# KẾT LUẬN
# ═══════════════════════════════════════════

## Module nào tệ nhất?

**Asset (30%)** là module tệ nhất trong hệ thống, đánh giá theo mọi tiêu chí:

| Tiêu chí | Asset (30%) | PM (55%) | Purchasing (100%) |
|-----------|-------------|----------|-------------------|
| Service layer | 3 files (413L) | 12 files (2,744L) | 13 files (~4,500L) |
| Helpers | **0** | 1 (unused) | 18 files |
| DTOs | **0** | 0 | 3 files |
| Requests | **0** | 0 | 3 files |
| Constants | **0** | 228L (unused) | Full PurchasingConstants |
| DB Transactions | **0 instances** | ✅ in services | ✅ throughout |
| Division-by-zero | **7 unguarded** | 0 | ✅ NULLIF |
| Race-safe writes | **None** | N/A | ✅ FOR UPDATE |
| checkLockedDate | **Missing** | Missing | ✅ Used |
| Mega files | Model 1,346L + Controller 1,106L | Controller 1,168L | Max ~600L |
| Raw SQL in controller | 8 queries (Dashboard+Report) | 19 instances | 0 |
| Hardcoded strings | ~20 instances | ~50 instances | 0 (uses Constants) |

**Kết luận:** Asset cần overhaul toàn diện, PM cần adopt constants + extract services. Cả 2 đều thiếu DTOs và Requests nhưng Asset thiếu cả service layer cơ bản.

## Target Scores After Remediation

| Phase | Asset | PM |
|-------|-------|----|
| Current | 30% | 55% |
| After Phase 1 | 40% | 60% |
| After Phase 2 | 55% | 70% |
| After Phase 3 | 70% | 85% |
| After Phase 4 | 80% | 90% |
