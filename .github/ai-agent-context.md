# Hướng dẫn AI Agent - Factory ERP

**Phiên bản**: v2.2 | **Cập nhật**: 21/04/2026  
**Mục đích**: Quick reference cho AI coding trên Factory ERP. Chi tiết kiến trúc xem **copilot-instructions.md**

---

## 🗄️ MYSQL CONNECTION (Local Development)

### Thông tin kết nối (từ `.env.local`)
```
DB_HOST=103.200.23.139
DB_PORT=3306
DB_USER=erpmesco_dev_test
DB_PASS=Phuan@123
DB_NAME=erpmesco_erp_test
```

### Phương thức kết nối trực tiếp từ Terminal (PowerShell)

**Command (One-liner)**:
```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -h 103.200.23.139 -u erpmesco_dev_test -p'Phuan@123' -D erpmesco_erp_test -e "SELECT COUNT(*) FROM products;"
```

**Giải thích**:
- `&` = Call operator (PowerShell - bắt buộc cho executable)
- `-h` = Host (IP database server)
- `-u` = Username
- `-p'...'` = Password (không có space sau -p)
- `-D` = Database name
- `-e` = Execute SQL command

**Ví dụ thực tế**:
```powershell
# Xem số lượng sản phẩm
& 'C:\xampp\mysql\bin\mysql.exe' -h 103.200.23.139 -u erpmesco_dev_test -p'Phuan@123' -D erpmesco_erp_test -e "SELECT COUNT(*) as total FROM products;"

# Xem danh sách bảng
& 'C:\xampp\mysql\bin\mysql.exe' -h 103.200.23.139 -u erpmesco_dev_test -p'Phuan@123' -D erpmesco_erp_test -e "SHOW TABLES;"

# Export dữ liệu (giữ nguyên format)
& 'C:\xampp\mysql\bin\mysql.exe' -h 103.200.23.139 -u erpmesco_dev_test -p'Phuan@123' -D erpmesco_erp_test -e "SELECT * FROM products LIMIT 5;"
```

**Tạo alias để dễ dùng** (thêm vào PowerShell Profile):
```powershell
# Thêm vào $PROFILE file
function mysql-dev {
    & 'C:\xampp\mysql\bin\mysql.exe' -h 103.200.23.139 -u erpmesco_dev_test -p'Phuan@123' -D erpmesco_erp_test @args
}

# Dùng:
mysql-dev -e "SELECT COUNT(*) FROM products;"
```

### Database Tables Overview (215 tables)

**Main Tables** (Thường dùng):
- `products` (111 rows) - Master data sản phẩm
- `partners` - Vendor, Customer, Supplier
- `warehouses` - Kho hàng
- `users`, `roles`, `permissions` - Security
- `sites` - Multi-tenant sites
- `purchase_orders`, `purchase_request_details` - Mua hàng
- `sales_orders`, `sales_order_details` - Bán hàng
- `inventory_transactions` - Giao dịch kho
- `journal_entries`, `journal_entry_details` - Kế toán
- `work_orders`, `boms` - Sản xuất
- `employees`, `attendance_logs` - Nhân sự
- `gl_periods`, `chart_of_accounts` - GL Master

**Test Connection**:
```powershell
# Kiểm tra kết nối
& 'C:\xampp\mysql\bin\mysql.exe' -h 103.200.23.139 -u erpmesco_dev_test -p'Phuan@123' -D erpmesco_erp_test -e "SELECT VERSION();"

# Kết quả: MariaDB version
```

---

## 🔧 RECENT FIXES & STATUS (27/01/2026)

| File | Issue | Fix | Commit |
|------|-------|-----|--------|
| PurchaseRequestController.php | Duplicate `flashAndRedirect()` method | Removed, fixed signature | f597b50 |
| InventoryReceiptController.php | Corrupted from merge conflict (Jan 26) | Refactored, 672 lines clean | f597b50 |
| Git History | Merge markers in 3+ commits | All files pass `php -l` ✓ | f597b50 |

**Lessons**: Always resolve `<<<<<<<`, `=======`, `>>>>>>>` BEFORE commit. Use `php -l` immediately after edits.

---

## 🎯 ORACLE ENGINEER STANDARD

**AI Coding Role**: Work as a **Senior Oracle Engineer** - Apply enterprise-grade standards:
- ✅ Code quality & maintainability first
- ✅ Comprehensive error handling (try-catch)
- ✅ Performance considerations (indexing, query optimization)
- ✅ Security best practices (parameterized queries, input validation)
- ✅ Scalability & modularity (single responsibility, reusable components)
- ✅ Documentation & code comments (self-explanatory code)
- ✅ Testing mindset (anticipate edge cases, validate data)
- ✅ Code review discipline (your code must pass peer review)

**This means**:
- No shortcuts, hacks, or temporary solutions
- All code follows Factory ERP patterns (BaseModel, Controller, Service layers)
- Anticipate breaking changes and future requirements
- Validate all inputs, handle all error scenarios
- Write clean, readable code that others can maintain

---

## 🚫 CRITICAL RULES (PHẢI TUÂN THỰ)

### 1. **Site Scoping - Multi-Tenant Isolation**
- ✅ Model mặc định auto-filter `site_id` (BaseModel)
- ❌ Bỏ qua site_id → BUG, user thấy dữ liệu site khác
- **Check**: Bảng global (Product, Partner) → set `$isSiteSpecific = false`

### 2. **SQL Parameterization - Anti-Injection**
- ✅ `$this->db->bind(':param', $value)`
- ❌ String concat: `"WHERE code = '$code'"` → VULNERABILITY

### 3. **CSRF Protection - All Forms**
- ✅ `<?php csrf_field(); ?>` trong mọi form POST
- ❌ Bỏ quên → CSRF attack vulnerability
- Exception: API → set `$this->skipCSRF = true`

### 4. **XSS Prevention - Output Escaping**
- ✅ `<?= e($user_input) ?>` hoặc `htmlspecialchars()`
- ❌ `<?= $user_input ?>` → XSS vulnerability

### 5. **Permission Check - Before Action**
- ✅ `requirePermission('module.action')` trước thay đổi
- ❌ Bỏ quên → User bypass features

### 6. **File Upload Validation**
- ✅ Validate MIME type + size + rename
- ❌ Chỉ check extension → Malware upload risk

### 7. **Module Independence - NO Mega Models/Controllers**
- ❌ **SAI**: Một ProductModel xử lý tất cả (tạo, sửa, tìm kiếm, import, export, validation)
- ✅ **ĐÚNG**: Tách riêng theo responsibility
  - `ProductModel.php` - ORM + basic queries
  - `ProductImportService.php` - Logic import file
  - `ProductExportService.php` - Logic export file
  - `ProductSearchService.php` - Advanced search + filters
  - `ProductPricingService.php` - Tính giá logic
  - `ProductCalculationHelper.php` - Helper functions
  - `ProductStoreRequest.php` - Validate input
  - `ProductDTO.php` - Data transformation

**Structure for each module**:
```
app/models/{module}/
├── {Entity}Model.php          # ORM only
├── {Detail}Model.php          # Related entities
└── ...

app/services/{module}/
├── {Entity}Service.php        # Core business logic
├── {Import}Service.php        # Import workflow
├── {Export}Service.php        # Export workflow
├── {Calculation}Service.php   # Complex calculations
└── ...

app/requests/{module}/
├── Store{Entity}Request.php   # Validate on create
├── Update{Entity}Request.php  # Validate on update
└── ...

app/dtos/{module}/
├── {Entity}DTO.php            # Data transfer object
└── {Search}DTO.php            # Search parameters

app/helpers/{module}/
├── {Entity}Helper.php         # Static helper functions
└── {Calculation}Helper.php    # Calculation utilities

app/libraries/{module}/
├── {Entity}Exporter.php       # PDF/Excel export
└── {Entity}Parser.php         # Parse/transform data
```

**Golden Rule**: 1 class = 1 responsibility. No mega files > 300 lines.

---

## 📁 ARCHITECTURE OVERVIEW

```
public/index.php (Entry)
  ↓
app/bootstrap.php (Config, Helpers, Security)
  ↓
app/core/App.php (URL Routing)
  ↓
Controller → Model (BaseModel) + Service → View
```

**Key Files**:
- `app/core/App.php` - Deep routing (URL → Controller)
- `app/core/Controller.php` - CSRF, permission, lazy-load
- `app/core/BaseModel.php` - ORM + site scope + soft delete
- `app/core/Database.php` - PDO singleton + transaction counter

**Important**: Tất cả folder viết **thường, không dấu gạch** (deploy Linux)

---

## 📋 MODULES & CONTROLLERS CHÍNH

| Module | Key Controllers | Key Models |
|--------|-----------------|-----------|
| **hr** | EmployeeController, AttendanceController, LeaveRequestController | Employee, Attendance, LeaveRequest |
| **inventory** | StockController, InventoryReceiptController, InventoryTransferController, MaterialIssueController, InventoryAuditController, GiSalesController, PdaController | WarehouseStock, InventoryReceipt, InventoryTransfer, MaterialIssue, InventoryAudit |
| **production** | WorkOrderController, BomController | WorkOrder, Bom |
| **purchasing** | PurchaseOrderController, PurchaseRequestController, PurchaseReturnController, PurchasePriceListController, PurchaseOrderApiController, PurchaseRequestApiController, PurchasingreportsController, MobileController, PodetaillistingController, PrdetaillistingController | PurchaseOrder, PurchaseRequest, PurchaseOrderShipment, PurchaseRequestShipment, PurchaseReturnModel, PoDetailListingModel, PrDetailListingModel |
| **sales** | SalesOrderController, SalesQuoteController | SalesOrder, SalesQuote |
| **finance** | JournalEntryController, CoaController | JournalEntry, Coa |
| **masterdata** | ProductController, PartnerController, WarehouseController | Product, Partner, Warehouse |

---

## 🔍 QUICK LOOKUP

| Cần | Tìm tại |
|-----|---------|
| Schema bảng | `app/db_schema.sql` |
| Quyền RBAC | `app/config/permissions_list.php` |
| Config DB | `app/config/config.php` (lines 15-22) |
| Helper functions | `app/helpers/*.php` |
| Security helpers | `app/helpers/security_helper.php` |
| Cấu trúc module | `app/models/{Module}/`, `app/services/{Module}/` |
| Detail kiến trúc | `.github/copilot-instructions.md` ⭐ |
| Recent fixes | Section này (RECENT FIXES & STATUS) |

---

## ⚠️ GIT WORKFLOW - PREVENT CORRUPTION

### Khi Pull/Merge:
```bash
git merge origin/main
# Check conflict markers
git diff --cached | grep -E "(<<<<<<<|=======|>>>>>>>)"
# Verify syntax
php -l app/controllers/**/*.php
```

### Khi có Conflicted File:
1. Mở file, xóa `<<<<<<<`, `=======`, `>>>>>>>`
2. Giữ ONLY version đúng
3. `php -l filename.php` → verify syntax OK
4. `git add file && git commit -m "Resolve conflicts"`

---

## 📝 CHECKLIST PRE-COMMIT

- [ ] Syntax: `php -l app/controllers/YOUR_FILE.php` → No errors
- [ ] Site scope: `$this->getCurrentSiteId()` used in queries
- [ ] SQL: No string concat, use `bind()`
- [ ] CSRF: `csrf_field()` in all forms
- [ ] Output: `e()` or `htmlspecialchars()` cho user input
- [ ] Permission: `requirePermission()` before changes
- [ ] No merge markers: `<<<<<<<`, `=======`, `>>>>>>>`
- [ ] Code review: `git diff --cached` looks good

---

## 🛠️ COMMON PATTERNS

### Load Model/Service
```php
$model = $this->model('Module/ModelName');
$service = $this->service('module/ServiceName');
```

### Query with Site Scoping
```php
$items = $model->all();  // Auto-filters by site_id
$item = $model->where('status', 'ACTIVE')->first();
```

### JSON Response
```php
return $this->json(['success' => true, 'data' => $data]);
```

### Permission & Redirect
```php
requirePermission('module.action');  // Throws 403 if denied
flash('msg', 'Success!', 'alert alert-success');
redirect('module/action');
```

---

## 🗄️ MYSQL ACCESS

### Direct MySQL Connection (XAMPP)

**Database Credentials** (from `.env.local`):
- Host: `103.200.23.139` (Remote server)
- User: `erpmesco_dev_test`
- Password: `Phuan@123`
- Database: `erpmesco_erp_test`
- Port: `3306`
- Version: MariaDB 10.11.15

**Connect via Command Line**:
```powershell
# Full path to XAMPP MySQL client
C:\xampp\mysql\bin\mysql.exe -h 103.200.23.139 -u erpmesco_dev_test -p'Phuan@123' erpmesco_erp_test

# Quick query (one-liner)
C:\xampp\mysql\bin\mysql.exe -h 103.200.23.139 -u erpmesco_dev_test -p'Phuan@123' erpmesco_erp_test -e "SELECT * FROM products LIMIT 5;"

# Check database info
C:\xampp\mysql\bin\mysql.exe -h 103.200.23.139 -u erpmesco_dev_test -p'Phuan@123' erpmesco_erp_test -e "SELECT DATABASE() as current_db, VERSION() as mysql_version;"

# Show all tables
C:\xampp\mysql\bin\mysql.exe -h 103.200.23.139 -u erpmesco_dev_test -p'Phuan@123' erpmesco_erp_test -e "SHOW TABLES;"

# Describe table structure
C:\xampp\mysql\bin\mysql.exe -h 103.200.23.139 -u erpmesco_dev_test -p'Phuan@123' erpmesco_erp_test -e "DESCRIBE products;"
```

**PHP Script Access** (if available):
```bash
php mysql-query.php "SELECT * FROM products LIMIT 5"
```

**Interactive Mode**:
```bash
php mysql-query.php
mysql> SHOW TABLES;
```

⚠️ **Important Notes**:
- Always check `site_id` column when querying business tables
- Remote server: expect ~100-200ms latency for queries
- Use parameterized queries in PHP code (never concat strings)
- **PDO `ATTR_EMULATE_PREPARES=false`**: Một placeholder (`:kw`) chỉ được xuất hiện **đúng 1 lần** trong câu SQL. Nếu cần dùng cùng giá trị ở nhiều chỗ, đặt tên khác nhau (`:kw1`, `:kw2`, ...) và bind riêng. Xem Session 39 audit trong MODULE_COMPLETION_ROADMAP §VI.
- Connection pooling handled by PDO in `Database` class

---

## 📚 FOR DETAILED GUIDANCE

→ See **`.github/copilot-instructions.md`** for:
- Complete architecture walkthrough (Module Independence Pattern)
- Request → DTO → Service → Model data flow
- View standardization (_form.php + _modals.php + {entity}.js pattern)
- Feature creation workflow & code organization
- All code examples & patterns

---

## 🔗 REFERENCE

**Status**: ✅ Stable — Session 39 (PDO placeholder audit 16 files + AR dashboard schema fix) applied  
**Last Updated**: 21/04/2026  
**Contributors**: AI Agent + Factory ERP Team
