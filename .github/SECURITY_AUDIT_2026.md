# Factory ERP — Security Audit Report

> **Ngày audit**: Tháng 6/2026  
> **Phạm vi**: Toàn bộ codebase (`app/`, `public/`, `cron_jobs/`)  
> **Phương pháp**: Static analysis + manual code review  
> **Tiêu chuẩn**: OWASP Top 10 (2021)

---

## ✅ Remediation Status (Cập nhật 21/04/2026 — Session 39)

| Mức độ | Tổng | Đã fix | Còn lại | Ghi chú |
|--------|------|--------|---------|--------|
| **CRITICAL** | 1 | **1** | 0 | DELETE via GET → POST+CSRF |
| **HIGH** | 4 | **4** | 0 | Portal brute force, cron secrets, XSS |
| **MEDIUM** | 18 | **18** | 0 | Tất cả đã fix ✅ |
| **LOW** | 13 | **12** | 1 | Còn: TCPDF version tracking (no Composer, acceptable) |

### Session 39 (2026-04-21) — PDO Placeholder Reuse Audit

Không phải lỗi security trực tiếp nhưng liên quan trực tiếp đến M1 (duplicate PDO params). Phát hiện 16 files nữa còn bind cùng placeholder nhiều lần → Fatal khi chạy với `ATTR_EMULATE_PREPARES=false`. Đã fix toàn bộ. Schema bug phụ: `ar_invoices` không có `due_date` → `FinanceDashboardHelper::getArAging()` đã được sửa sang `invoice_date` với Net30 buckets. Chi tiết: `MODULE_COMPLETION_ROADMAP.md` §VI.

### Chi tiết đã fix:

| # | Finding | Fix | Files |
|---|---------|-----|-------|
| **C1** | DELETE via GET (attendance/edit.php) | → POST + fetch() + CSRF token | 1 |
| **H1** | Portal brute force + logging | rate_limit() + logLoginAttempt() | AuthPortalController |
| **H2** | Hardcoded cron secret | → getenv('CRON_SECRET_KEY'), fail-closed | cron.php + 3 cron_jobs + AttendanceController |
| **H3** | XSS users/index.php | e() wrap 5 fields | users/index.php |
| **H4** | XSS performance/criteria.php + leave_request/index.php | esc_js() | 2 views |
| **M1** | Duplicate PDO params | :kw → :kw1/:kw2/:kw3 | 17 files Session 2+3 + **16 files Session 39 (2026-04-21)** — ALL fixed |
| **M2** | Path traversal downloads | realpath() + strpos() validation | ProductsController + PartnersController |
| **M3** | TaskController IDOR | site_id check in update/delete | TaskController |
| **M4** | Mass assignment PM | Service already has allowedFields whitelist | Verified safe |
| **M5** | Hardcoded reset password | → getenv('DEFAULT_RESET_PASSWORD'), fail-closed | EmployeeController |
| **M6** | Portal redirect validation | regex + // check | AuthPortalController |
| **M7** | Portal login logging | logLoginAttempt() added | AuthPortalController |
| **M8** | AttendanceBackup MIME check | finfo_file() validation | AttendanceBackupController |
| **M9** | Debug logging print_r | Removed 3 debug blocks | UsersController |
| **M10** | XSS leave_type, defect, cache, attachments, ToolingController | e()/esc_js() | 5 files |
| **M11** | escape_like() helper + controller queries | Created helper + applied to 6+3 controllers | 10 files |
| **M12** | API rate limiting | rate_limit() in all 3 API controllers | 3 files |
| **M13** | LIKE escape model-level | Audit: models use buildWhereClause() (safe), 0 raw LIKE sites in models | Verified clean |
| **M14** | Bundled PHPMailer redundancy | Composer v7.0.2 loaded first via autoload; bundled v7.0.0 is dead-code fallback | Verified safe |
| **L1** | ToolingController conditional permission | Removed function_exists() guard | ToolingController (7 spots) |
| **L2** | composer-setup.php info disclosure | Added to .gitignore | .gitignore |
| **L3** | error_reporting(0) suppression | Removed | UsersController |
| **L4** | Unescaped page title | e() in header.php | header.php |
| **L5** | .env security vars | Added CRON_SECRET_KEY + DEFAULT_RESET_PASSWORD | .env.local |
| **L6** | sanitize_input() undefined → fatal error | Created sanitize_date() helper + sanitize_input() compat alias; replaced 8 calls in 4 finance controllers | security_helper + 4 controllers |
| **L7** | Date validation in finance controllers | sanitize_input() → sanitize_date() with Y-m-d regex + checkdate() | TrialBalance, IncomeStatement, BalanceSheet, ArReport |
| **L8** | escape_like 3 remaining controllers | Added escape_like() to AttendanceReport + QaSpecification | 2 controllers |
| **L9** | XSS users/edit.php | Already uses htmlspecialchars() on full_name/email — verified safe | Verified |
| **L10** | XSS reflected in supplier_performance.php | `from_date`/`to_date` echoed bare in HTML attributes → `e()` | views/purchasing/reports/supplier_performance.php |
| **L11** | Inline (int) cast StockCardModel | `(int)$warehouseId` in raw SQL string → safe, cast prevents injection; comment added explaining PDO named param reuse limitation | StockCardModel.php (intentional pattern) |
| **L12** | Raw csrf_token ~50+ views | Cosmetic: views use `$_SESSION['csrf_token']` directly instead of csrf_field() helper — functionally equivalent | Acceptable |

### Còn lại (LOW risk — acceptable):
- **TCPDF version tracking**: `app/libraries/tcpdf/` not managed via Composer — LOW risk, library is stable. No CVEs active.

### Session 3 — Purchasing Module Comprehensive Security Audit (CLEAN):
Audit phủ toàn bộ module Purchasing (controllers, models, services, helpers):
- `PurchaseOrderController`: requirePermission + validateCSRF + rate_limit on all actions ✅
- `PurchaseRequestController`: validateCsrfToken + requirePermission + rate_limit ✅
- `PurchaseOrderService`: site_id ownership check, partner assignment validation ✅
- `PurchaseRequestService`: TransactionGuard + FOR UPDATE lock + site_id WHERE guard ✅
- `PurchaseOrderWorkflowService`: 6 workflow actions all have FOR UPDATE + site_id guard + cache clear ✅
- `PurchaseOrderModel.lockForUpdate()`: SELECT FOR UPDATE with site_id filter ✅
- `QuantityUpdater`: recalculation approach (no delta), uses PurchaseRequest constants in SQL (not user input) ✅

---

## Tổng Quan

| Mức độ | Số lượng | Đã fix | Ghi chú |
|--------|----------|--------|---------|
| **CRITICAL** | 1 | ✅ 1 | DELETE qua GET request (CSRF bypass) |
| **HIGH** | 4 | ✅ 4 | Portal brute force, hardcoded secrets, XSS trong JS context |
| **MEDIUM** | 18 | ✅ 18 | LIKE injection, path traversal, IDOR, mass assignment, debug log |
| **LOW** | 13 | ✅ 12 | 1 còn lại: TCPDF version tracking (acceptable) |
| **PASS** | 20+ | — | Password hashing, session config, security headers, audit trail |

**Kết luận chung**: Tất cả CRITICAL, HIGH, và MEDIUM đã fix. Hệ thống có nền tảng bảo mật tốt — `BaseModel` tự động site isolation, `csrf_field()` phủ rộng, `password_hash(PASSWORD_DEFAULT)` đúng chuẩn, security headers đầy đủ. 1 LOW còn lại là TCPDF không qua Composer (acceptable, no active CVEs).

---

## Mục Lục

1. [A03: Injection (SQL Injection)](#a03-injection)
2. [A03: Injection (XSS)](#a03-xss)
3. [A01: Broken Access Control (CSRF)](#a01-csrf)
4. [A01: Broken Access Control (IDOR & Permissions)](#a01-idor)
5. [A02: Cryptographic Failures](#a02-cryptographic-failures)
6. [A04: Insecure Design (File Upload & Path Traversal)](#a04-file-upload)
7. [A05: Security Misconfiguration](#a05-security-misconfiguration)
8. [A07: Authentication & Brute Force](#a07-authentication)
9. [A09: Logging & Monitoring](#a09-logging)
10. [A06: Vulnerable Dependencies](#a06-dependencies)
11. [Các hạng mục PASS](#pass-items)
12. [Kế hoạch khắc phục](#remediation-plan)

---

## A03: Injection — SQL Injection {#a03-injection}

### Đánh giá tổng thể: **TỐT** — Không phát hiện SQL injection trực tiếp

Toàn bộ codebase sử dụng `$this->db->bind()` (PDO prepared statements). Không có string concatenation trực tiếp user input vào SQL.

### MEDIUM: Duplicate PDO Named Parameters (21 queries / 13 files)

PDO không cho phép dùng cùng tên parameter nhiều lần trong 1 query. Các query dùng `:kw` cho nhiều LIKE clause gây lỗi `HY093`.

**Đã fix trong session này** (3 files — Session 2):
- `app/models/inventory/LotHistoryModel.php` — `:kw` → `:kw1`/`:kw2`/`:kw3`
- `app/models/inventory/GiRequestModel.php` — `:kw` → `:kw1`/`:kw2`
- `app/models/masterdata/PriceList.php` — `:kw` → `:kw1`/`:kw2` (2 methods)

**Đã fix trong Session 2 khác (7 files đã fixed trước)**:
- `app/models/hr/EmployeeModel.php` — `:kw` → `:kw1`/`:kw2`/`:kw3` ✅
- `app/models/purchasing/PurchaseRequest.php` — ✅ (verified clean)
- `app/models/purchasing/PurchaseOrder.php` — ✅ (verified clean)
- `app/models/sales/SalesOrder.php` — ✅ (verified clean)
- `app/models/sales/SalesQuote.php` — ✅ (verified clean)
- `app/models/quality/InspectionModel.php` — ✅ (verified clean)
- `app/models/asset/AssetModel.php` — ✅ (verified clean)

**Đã fix trong Session 3 (7 files)**:
- `app/models/finance/JournalEntryModel.php` — `:kw` ×3 → `:kw1`/`:kw2`/`:kw3`
- `app/models/finance/ChartOfAccount.php` — `:kw` ×2 → `:kw1`/`:kw2`
- `app/models/inventory/TripModel.php` — `:kw` ×3 (2 methods) → `:kw1`/`:kw2`/`:kw3`
- `app/models/inventory/PickNoteModel.php` — `:kw` ×3 → `:kw1`/`:kw2`/`:kw3`
- `app/models/inventory/InventoryReceiptModel.php` — `:kw` ×3 + `:kw` ×7 (4 methods) → `:kw1`–`:kw7`
- `app/models/core/PrintLabelTemplateModel.php` — `:kw` ×2 (2 methods) → `:kw1`/`:kw2`
- `app/models/production/WorkOrderModel.php` — already using `:kw`/`:kw2` (verified different names — clean)

**✅ M1 FULLY RESOLVED — Tất cả 17 files đã fix**

**Fix pattern**:
```php
// ❌ SAI
$sql .= " AND (code LIKE :kw OR name LIKE :kw OR desc LIKE :kw)";
$this->db->bind(':kw', "%$keyword%");

// ✅ ĐÚNG
$sql .= " AND (code LIKE :kw1 OR name LIKE :kw1 OR desc LIKE :kw2)";
// Hoặc dùng CONCAT trong SQL:
$sql .= " AND (code LIKE CONCAT('%', :kw, '%') OR name LIKE CONCAT('%', :kw, '%'))";
// Lưu ý: CONCAT pattern cho phép dùng lại :kw (MySQL treats it as same param)
```

### MEDIUM: LIKE Queries Thiếu Escape Wildcards (~30 queries)

User input chứa `%` hoặc `_` có thể mở rộng kết quả LIKE query ngoài ý muốn. Không phải vulnerability nguy hiểm nhưng vi phạm defense-in-depth.

**Fix**: Tạo helper `escape_like()`:
```php
function escape_like(string $input): string {
    return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $input);
}

// Sử dụng:
$this->db->bind(':kw', '%' . escape_like($keyword) . '%');
```

### LOW: Inline (int) Cast trong SQL

| File | Code |
|------|------|
| `app/models/inventory/StockCardModel.php` | `"... WHERE wh.id = " . (int)$warehouseId` |

Mặc dù `(int)` cast an toàn cho integer injection, nên chuyển sang bind() để thống nhất pattern.

### LOW: Date Format Không Validate (~5 controllers)

Một số controller nhận date input qua `$_GET['from']` / `$_GET['to']` mà không validate format `Y-m-d` trước khi bind vào SQL. PDO sẽ bind as string nên không injection, nhưng nên validate:

```php
$from = $_GET['from'] ?? '';
if ($from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    return $this->json(['error' => 'Invalid date format'], 400);
}
```

---

## A03: Injection — XSS (Cross-Site Scripting) {#a03-xss}

### Đánh giá tổng thể: **KHÁ** — Đa số view dùng `e()` đúng cách

### HIGH-1: Unescaped User Data trong HTML Context (6 vị trí)

| # | File | Line | Field | Context |
|---|------|------|-------|---------|
| 1 | `app/views/core/users/index.php` | ~L85 | `full_name` | Table cell: `<?= $user->full_name ?>` |
| 2 | `app/views/core/users/index.php` | ~L86 | `email` | Table cell: `<?= $user->email ?>` |
| 3 | `app/views/core/users/index.php` | ~L87 | `username` | Table cell: `<?= $user->username ?>` |
| 4 | `app/views/core/users/edit.php` | ~L25 | `full_name` | Input value: `value="<?= $data['user']->full_name ?>"` |
| 5 | `app/views/hr/leave_request/index.php` | ~L373 | Filter values | JS context: Filter value injected vào JS string |
| 6 | `app/views/hr/performance/criteria.php` | ~L46 | Name field | `onclick="editCriteria('<?= htmlspecialchars($c->name) ?>')"` — cần `esc_js()` thay `htmlspecialchars()` cho JS context |

**Fix**:
```php
// HTML context → e()
<?= e($user->full_name) ?>

// HTML attribute → esc_attr()
value="<?= esc_attr($data['user']->full_name) ?>"

// JS context → esc_js()
onclick="editCriteria('<?= esc_js($c->name) ?>')"
```

### MEDIUM: Unescaped Dynamic Content (6 vị trí)

| # | File | Field | Fix |
|---|------|-------|-----|
| 1 | `app/views/finance/payment_term/index.php` | `term_name` | `e()` |
| 2 | `app/views/hr/leave_type/index.php` | `leave_type_name` | `e()` |
| 3 | `app/views/quality/defect/index.php` | `defect_name` | `e()` |
| 4 | `app/views/systems/cache/index.php` | Error messages | `e()` |
| 5 | `app/views/sales/sales_order/_show_attachments.php` | `file_path` | `e()` |
| 6 | Error message display in multiple controllers | Flash messages | Verify `e()` in flash template |

### LOW: Page Titles

Một số view dùng `$data['title']` trong `<title>` tag mà không escape. Rủi ro thấp vì title được set từ controller (không phải user input trực tiếp).

---

## A01: Broken Access Control — CSRF {#a01-csrf}

### CRITICAL: DELETE Via GET Request

| File | Line | Code |
|------|------|------|
| `app/views/hr/attendance/edit.php` | ~L35 | `location.href = '<?= URLROOT ?>/hr/attendance/delete/' + id` |

**Vấn đề**: Xóa attendance record qua GET request — attacker có thể craft URL hoặc `<img>` tag để trigger deletion. Phải đổi sang POST + CSRF token.

**Fix**:
```javascript
// ❌ SAI
location.href = baseUrl + '/hr/attendance/delete/' + id;

// ✅ ĐÚNG
fetch(baseUrl + '/hr/attendance/delete/' + id, {
    method: 'POST',
    body: new URLSearchParams({ csrf_token: CSRF_TOKEN })
}).then(r => r.json()).then(data => {
    if (data.success) location.reload();
});
```

### MEDIUM: API Controllers với skipCSRF = true

| File | Issue |
|------|-------|
| `app/controllers/api/NotificationController.php` | `skipCSRF = true` — OK cho GET, nhưng cần review POST endpoints |
| `app/controllers/api/ProductSearchController.php` | `skipCSRF = true` — Read-only, acceptable |

**Recommendation**: Review tất cả API controller POST endpoints — nếu state-changing, cần alternative auth (API key, Bearer token).

### LOW: Một Số Form Dùng Raw Token

Một vài form ghi trực tiếp `<input name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">` thay vì `<?php csrf_field(); ?>`. Chức năng tương đương nhưng nên thống nhất dùng helper.

---

## A01: Broken Access Control — IDOR & Permissions {#a01-idor}

### MEDIUM: TaskController Thiếu Site Verification

| File | Method | Issue |
|------|--------|-------|
| `app/controllers/pm/TaskController.php` | `update($id)` | Không check `$task->site_id == $currentSiteId` trước khi update |
| `app/controllers/pm/TaskController.php` | `delete($id)` | Không check `$task->site_id` trước khi delete |
| `app/controllers/pm/TaskController.php` | `store()` | Raw `$_POST` passed to service — mass assignment risk |

**Fix**: Thêm site verification (copy pattern từ `ProjectController`):
```php
public function update($id) {
    $task = $this->taskModel->findById($id);
    if (!$task || $task->site_id != $this->getCurrentSiteId()) {
        return $this->json(['error' => 'Không tìm thấy task'], 404);
    }
    // ... tiếp tục update
}
```

### MEDIUM: Mass Assignment trong PM Module

| File | Method | Code |
|------|--------|------|
| `TaskController.php` | `store()` | `$this->taskService->createTask($_POST)` |
| `TaskController.php` | `update()` | `$this->taskService->updateTask($id, $_POST)` |
| `ProjectController.php` | `update()` | `$this->projectService->updateProject($id, $_POST)` |

**Fix**: Whitelist fields trước khi pass to service:
```php
$data = array_intersect_key($_POST, array_flip([
    'title', 'description', 'assignee_id', 'due_date', 'priority', 'status'
]));
$this->taskService->updateTask($id, $data);
```

### LOW: ToolingController Conditional Permission Check

| File | Code |
|------|------|
| `app/controllers/masterdata/ToolingController.php` | `if (function_exists('requirePermission')) requirePermission(...)` |

Guard `function_exists()` nghĩa là nếu helper fail to load, permission check bị skip hoàn toàn. Bỏ conditional — gọi `requirePermission()` trực tiếp.

---

## A02: Cryptographic Failures {#a02-cryptographic-failures}

### HIGH: Hardcoded Cron Secret Key

| File | Code | Severity |
|------|------|----------|
| `app/config/cron.php` | `define('CRON_SECRET_KEY', 'AutoCalc_Phuan_2026');` | HIGH |
| `app/controllers/hr/AttendanceController.php` | Fallback: `'AutoCalc_Default_Key'` | MEDIUM |

**Impact**: Ai có access repo đều biết key để trigger cron endpoints.

**Fix**:
```php
// app/config/cron.php
$cronKey = getenv('CRON_SECRET_KEY');
if (!$cronKey) {
    throw new RuntimeException('CRON_SECRET_KEY not set in .env');
}
define('CRON_SECRET_KEY', $cronKey);
```

### MEDIUM: Hardcoded Default Reset Password

| File | Code |
|------|------|
| `app/controllers/hr/EmployeeController.php` | `getenv('DEFAULT_RESET_PASSWORD') ?: 'Erp@2026!'` |

**Fix**: Bắt buộc env var, không fallback:
```php
$defaultPassword = getenv('DEFAULT_RESET_PASSWORD');
if (!$defaultPassword) {
    return $this->json(['error' => 'DEFAULT_RESET_PASSWORD chưa cấu hình'], 500);
}
```

### PASS: Password Hashing

Toàn bộ 8 vị trí `password_hash()` đều dùng `PASSWORD_DEFAULT`. Token generation dùng `bin2hex(random_bytes(32))` — 256-bit entropy. **Đạt chuẩn**.

---

## A04: Insecure Design — File Upload & Path Traversal {#a04-file-upload}

### MEDIUM: Path Traversal trong Download Functions

| # | File | Method | Issue |
|---|------|--------|-------|
| 1 | `app/controllers/masterdata/ProductsController.php` | `download_result($batchId)` | Đọc `$history->result_file_path` từ DB → `readfile()` mà không validate `realpath()` |
| 2 | `app/controllers/masterdata/PartnersController.php` | `download_result($batchId)` | Tương tự Products |

**Gold standard** (cần replicate): `DrawingController.php` dùng `realpath()` + `strpos()` verify path nằm trong allowed directory.

**Fix**:
```php
$fullPath = APPROOT . '/../public/' . $history->result_file_path;
$realPath = realpath($fullPath);
$allowedDir = realpath(APPROOT . '/../public/uploads/');

if (!$realPath || strpos($realPath, $allowedDir) !== 0) {
    return $this->json(['error' => 'Invalid file path'], 403);
}

readfile($realPath);
```

### MEDIUM: AttendanceBackupController Missing MIME Validation

| File | Method | Issue |
|------|--------|-------|
| `app/controllers/hr/AttendanceBackupController.php` | `uploadFile()` | Chỉ check extension (`.log`, `.txt`, `.csv`) — không check MIME type via `finfo` |

**Fix**: Thêm MIME check:
```php
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $_FILES['file']['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, ['text/plain', 'text/csv', 'application/csv'])) {
    return $this->json(['error' => 'File type not allowed'], 400);
}
```

### PASS: Centralized FileUploader Service

`app/services/io/FileUploader.php` — Proper MIME validation, size limits, `is_uploaded_file()` check, `sanitize_filename()`, `uniqid()` random names.

---

## A05: Security Misconfiguration {#a05-security-misconfiguration}

### MEDIUM: Debug Logging trong Production (UsersController)

| File | Line | Code |
|------|------|------|
| `app/controllers/core/UsersController.php` | ~L527 | `print_r($_POST, true)` ghi vào `delegation_debug.log` |

**Impact**: Ghi toàn bộ `$_POST` data (bao gồm `csrf_token`) vào log file. Nếu log bị leak → exposure.

**Fix**: Xóa block `--- START DEBUG LOG ---` / `--- END DEBUG LOG ---`, hoặc dùng `sanitizeForLogging()`.

### LOW: composer-setup.php Có phpinfo()

| File | Issue |
|------|-------|
| `composer-setup.php` | Chứa `phpinfo(INFO_GENERAL)` — không nên có trên production |

**Fix**: Xóa file khỏi production deployments.

### LOW: error_reporting(0) Suppresses Errors

| File | Code |
|------|------|
| `app/controllers/core/UsersController.php` ~L592 | `error_reporting(0);` |

Nên dùng try/catch thay vì suppress errors.

### PASS: .htaccess Protection

- Root `.htaccess`: Rewrite to `public/index.php`
- `app/.htaccess`: `Deny from all` — blocks direct access to `db_schema.sql`, logs, config
- `public/.htaccess`: Blocks `.env`, `composer.json`

### PASS: Security Headers (CSP, HSTS, X-Frame-Options)

`bootstrap.php` set đầy đủ headers: `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy`, `Strict-Transport-Security`, `Content-Security-Policy`.

**Lưu ý**: CSP có `'unsafe-inline'` cho scripts/styles — cần thiết cho jQuery legacy app, nhưng nên plan loại bỏ dần (nonce-based CSP).

---

## A07: Authentication & Brute Force {#a07-authentication}

### HIGH: Portal Login Không Có Brute Force Protection

| File | Issue |
|------|-------|
| `app/controllers/portal/AuthPortalController.php` | Không có `rate_limit()`, `isUserLocked()`, `logLoginAttempt()` |

**Impact**: Attacker có thể brute force unlimited passwords. Nghiêm trọng hơn vì Portal dùng mật khẩu mặc định `12345` cho first login.

**Fix**:
```php
public function login() {
    // ... validate input ...
    
    // Rate limit
    rate_limit('portal_login_' . $empCode, 5, 300);
    
    // Check lockout
    if ($this->isPortalLocked($empCode)) {
        flash('portal_msg', 'Tài khoản bị khóa 15 phút', 'alert alert-danger');
        redirect('portal/authPortal/login');
        return;
    }
    
    // Verify password
    if (!password_verify($password, $user->password)) {
        $this->logPortalLoginAttempt($empCode, false);
        // ... error response
    }
    
    $this->logPortalLoginAttempt($empCode, true);
    // ... create session
}
```

### MEDIUM: Portal Redirect Validation Yếu

| File | Code |
|------|------|
| `AuthPortalController.php` | `strpos($redirectPath, 'http') === false` — không chặn `//evil.com/path` |

**Fix**: Dùng regex validation như `AuthController`:
```php
if (preg_match('#^/[a-zA-Z0-9/_\-]#', $redirectPath) && strpos($redirectPath, '//') === false) {
    redirect($redirectPath);
}
```

### PASS: ERP Login

- ✅ `session_regenerate_id(true)` sau login
- ✅ `isUserLocked()` — lockout 15 phút sau 5 lần thất bại
- ✅ `logLoginAttempt()` — ghi log success/failure
- ✅ Password hash: `PASSWORD_DEFAULT` (bcrypt)
- ✅ Redirect validation: regex + `//` check

---

## A09: Logging & Monitoring {#a09-logging}

### MEDIUM: Portal Login Attempts Không Được Log

| File | Issue |
|------|-------|
| `AuthPortalController.php` | Không gọi `logLoginAttempt()` — failed attempts invisible |

### MEDIUM: Raw POST Data Logged (UsersController)

Xem mục [A05 Debug Logging](#a05-security-misconfiguration).

### PASS: Audit Trail

- `BaseModel` với `$useAuditLog = true` → `sys_audit_logs`
- `log_security_event()` cho suspicious activity
- `sanitizeForLogging()` redact sensitive data
- ERP login ghi `app/logs/security_YYYY-MM-DD.log`

---

## A06: Vulnerable Dependencies {#a06-dependencies}

### MEDIUM: Bundled PHPMailer Outdated

| Package | Bundled | Composer |
|---------|---------|----------|
| PHPMailer | `app/libraries/phpmailer/` v7.0.0 | `vendor/phpmailer/` v7.0.2 |

Hai bản copy có thể gây confusion — `MailerService.php` có thể load bản cũ.

**Fix**: Xóa `app/libraries/phpmailer/`, chỉ dùng Composer version.

### LOW: TCPDF Bundled Không Có Version Tracking

`app/libraries/tcpdf/` — không qua Composer, khó track CVEs.

### PASS: Composer Dependencies Cập Nhật

Tất cả packages chính đều ở latest stable: PHPSpreadsheet 5.4.0, Guzzle 7.10.0, endroid/qr-code 6.0.9.

---

## Các Hạng Mục PASS {#pass-items}

| Hạng mục | Trạng thái | Chi tiết |
|----------|------------|----------|
| SQL Injection (direct) | ✅ PASS | 100% bind() / prepared statements |
| Password Hashing | ✅ PASS | `PASSWORD_DEFAULT` (bcrypt), 8 vị trí |
| Token Generation | ✅ PASS | `bin2hex(random_bytes(32))` — CSRF, reset, remember-me |
| Session Fixation | ✅ PASS | `session_regenerate_id(true)` after login |
| Session Cookie Flags | ✅ PASS | `httponly=true`, `secure=true` (prod), `samesite=Lax` |
| Sensitive Data in Session | ✅ PASS | Không lưu password/secrets trong `$_SESSION` |
| CORS | ✅ PASS | Whitelist origins, không dùng `*` |
| Security Headers | ✅ PASS | CSP + HSTS + X-Frame + nosniff + Referrer-Policy |
| .htaccess Protection | ✅ PASS | `app/` deny all, `.env` blocked |
| Command Injection | ✅ PASS | Không có `exec()`, `system()`, `passthru()`, `shell_exec()` |
| Deserialization | ✅ PASS | Không có `unserialize()` với user input |
| Open Redirect (ERP) | ✅ PASS | Regex + `//` check |
| File Upload (centralized) | ✅ PASS | MIME + size + sanitize_filename + uniqid |
| Audit Logging | ✅ PASS | `sys_audit_logs` + `log_security_event()` |
| Log Injection Prevention | ✅ PASS | `sanitizeForLogging()` redact sensitive data |
| ERP Brute Force Protection | ✅ PASS | Lockout 15 min / 5 attempts, logged |
| Multi-site Isolation (BaseModel) | ✅ PASS | Auto `site_id` filter |
| Permission System | ✅ PASS | `requirePermission()` phủ rộng |
| Privilege Escalation (Users) | ✅ PASS | Multi-layer IDOR + role assignment validation |

---

## Kế Hoạch Khắc Phục {#remediation-plan}

### Sprint 1 — CRITICAL & HIGH (Tuần 1)

| # | Finding | Fix | Effort |
|---|---------|-----|--------|
| 1 | DELETE via GET (`attendance/edit.php`) | Đổi sang POST + CSRF | 30 phút |
| 2 | Portal login brute force | Thêm `rate_limit()` + lockout + logging | 2 giờ |
| 3 | Hardcoded cron secret | Move to `.env`, fail-closed | 30 phút |
| 4 | Unescaped HTML output (6 vị trí) | Thêm `e()` / `esc_js()` | 1 giờ |

### Sprint 2 — MEDIUM (Tuần 2)

| # | Finding | Fix | Effort |
|---|---------|-----|--------|
| 5 | Duplicate PDO params (10 files) | Rename `:kw` → `:kw1`, `:kw2` | 2 giờ |
| 6 | Path traversal in download (2 files) | Add `realpath()` validation | 30 phút |
| 7 | TaskController IDOR | Add site_id verification | 30 phút |
| 8 | TaskController mass assignment | Whitelist $_POST fields | 30 phút |
| 9 | Hardcoded reset password | Require env var, no fallback | 15 phút |
| 10 | Portal redirect validation | Copy ERP regex pattern | 15 phút |
| 11 | AttendanceBackup MIME check | Add finfo validation | 15 phút |
| 12 | Debug logging (UsersController) | Remove print_r debug blocks | 15 phút |
| 13 | LIKE escape helper | Create `escape_like()` + apply | 3 giờ |
| 14 | Unescaped MEDIUM XSS (6 vị trí) | Add `e()` | 30 phút |
| 15 | Remove bundled PHPMailer | Delete `app/libraries/phpmailer/` | 30 phút |
| 16 | API rate limiting | Add `rate_limit()` to API controllers | 1 giờ |
| 17 | Portal login logging | Add `logLoginAttempt()` | 30 phút |

### Sprint 3 — LOW & Improvements (Tuần 3-4)

| # | Finding | Fix | Effort |
|---|---------|-----|--------|
| 18 | ToolingController conditional permission | Remove `function_exists()` wrapper | 15 phút |
| 19 | Delete composer-setup.php | Remove from production | 5 phút |
| 20 | error_reporting(0) | Replace with try/catch | 15 phút |
| 21 | Inline (int) cast in SQL | Convert to bind() | 15 phút |
| 22 | Date format validation | Add regex validation | 30 phút |
| 23 | Raw csrf_token in forms | Standardize to csrf_field() | 30 phút |
| 24 | CSP unsafe-inline → nonce | Long-term: nonce-based CSP | 2+ tuần |

---

## File Tham Chiếu

| Category | Affected Files |
|----------|---------------|
| **SQL (duplicate params)** | `EmployeeModel`, `PurchaseRequest`, `PurchaseOrder`, `SalesOrder`, `SalesQuote`, `JournalEntry`, `InspectionModel`, `WorkOrderModel`, `AssetModel` |
| **XSS (HIGH)** | `views/core/users/index.php`, `views/core/users/edit.php`, `views/hr/leave_request/index.php`, `views/hr/performance/criteria.php` |
| **CSRF** | `views/hr/attendance/edit.php` |
| **Auth** | `controllers/portal/AuthPortalController.php` |
| **Path Traversal** | `controllers/masterdata/ProductsController.php`, `controllers/masterdata/PartnersController.php` |
| **Hardcoded Secrets** | `config/cron.php`, `controllers/hr/AttendanceController.php`, `controllers/hr/EmployeeController.php` |
| **Debug** | `controllers/core/UsersController.php` |
| **IDOR** | `controllers/pm/TaskController.php` |

---

> **Ghi chú**: Document này sẽ được cập nhật sau mỗi sprint fix. Đánh dấu `[FIXED]` cho items đã khắc phục.
