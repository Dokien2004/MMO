# Factory ERP - AI Coding Instructions (Cập nhật 21/04/2026)

## Architecture Overview

**Factory ERP** là một hệ thống ERP sản xuất Việt Nam được xây dựng trên **custom PHP MVC framework** với hỗ trợ multi-site/multi-tenant. Các pattern chính:

-   **MVC Core**: Routing tùy chỉnh via `App.php` → Controllers → Models → Views
-   **Database**: PDO with MySQL, soft deletes, audit logging, site-scoped queries
-   **Multi-tenancy**: Tất cả bảng có `site_id`; queries tự động filter theo `user_site_id`
-   **Services Layer**: Business logic ở `app/services/` (inventory, accounting, planning, etc.)
-   **Base Classes**: `BaseModel` (ORM + audit), `Controller` (routing + permissions), `Database` (singleton, transaction-aware)

## Critical Bootstrap Flow

1. `public/index.php` - Entry point (CORS, maintenance check, HTTPS redirect)
2. `app/bootstrap.php` - Load config (database, helpers, security headers, timezone)
3. `app/core/App.php` - URL routing với deep folder support (`/master-data/products` → `masterdata/ProductsController.php`)
4. `app/core/Controller` - Base controller với lazy-loaded models/services & CSRF validation
5. Tất cả queries tự động scoped theo `current_site_id` via `BaseModel` trừ khi `isSiteSpecific = false`

## Essential Patterns

### Models & Queries

-   Extends `BaseModel` (KHÔNG query trực tiếp qua Database)
-   Set `protected $table = 'table_name'`
-   Dùng `buildWhereClause()` cho WHERE conditions (tự động handle site_id + soft_delete)
-   Luôn dùng **parameterized queries**: `$this->db->bind(':param', $value)`
-   **KHÔNG** nối chuỗi input vào SQL
-   **SQL phải đúng 100%** tên bảng, tên cột → **BẮT BUỘC tra `app/db_schema.sql`** trước khi viết query

Ví dụ:

```php
class ProductModel extends BaseModel {
    protected $table = 'products';
    protected $isSiteSpecific = false;  // Global (shared across sites)
    protected $useSoftDeletes = true;
    protected $useAuditLog = true;      // Auto-log created_by, updated_by

    public function getActive() {
        $this->db->query("SELECT * FROM {$this->table} WHERE status = :status");
        $this->db->bind(':status', 'ACTIVE');
        return $this->db->resultSet();
    }
}
```

### Controller Patterns

-   Load models via `$this->model('Subfolder/ModelName')` (có cache, lazy-load)
-   Load services via `$this->service('subfolder/ServiceName')`
-   POST tự động CSRF validation (set `$this->skipCSRF = true` chỉ cho API/webhook)
-   Get current user/site: `$this->getCurrentUserId()`, `$this->getCurrentSiteId()`
-   **Luôn check permissions** trước các hành động nhạy cảm

Ví dụ:

```php
class ProductController extends Controller {
    public function index() {
        $model = $this->model('Product');  // Cached instance
        $products = $model->all();         // Auto filter site_id
        return $this->json($products);
    }

    public function store() {
        requirePermission('product.add');  // Check permission or throw 403
        
        $data = [
            'code' => $_POST['code'] ?? '',
            'name' => $_POST['name'] ?? '',
        ];
        
        $model = $this->model('Product');
        $id = $model->insert($data);       // Auto set site_id, created_by
        
        return $this->json(['success' => true, 'id' => $id]);
    }
}
```

### Services Layer

-   Stateless business logic (pricing, inventory, accounting calculations)
-   Access database via `$this->db` hoặc lazy-load model trong constructor
-   Transaction support: `$db->beginTransaction()`, `$db->commit()`, `$db->rollBack()`
-   Ví dụ: `InventoryService` check stock availability + cost calculations

### Routing & URLs

-   URL `/master-data/products/create` → `app/controllers/masterdata/ProductsController.php::create()`
-   Folder names: `master-data` → `masterdata` (strip hyphens, lowercase)
-   Controller class names: PascalCase + "Controller" suffix
-   **Deep routing**: Mỗi URL segment cố gắng match folder trước, sau đó controller file

Ví dụ routing:
```
/hr/employee/update/123
  → Folder: hr/
  → Controller: EmployeeController.php
  → Method: update($id = 123)
```

## Site Scoping & Security

**Mọi query phải tôn trọng site boundaries:**

```php
// ✅ CORRECT: BaseModel tự động thêm site_id filter
$model = $this->model('Product');
$products = $model->all();  // Chỉ return products của current_site_id

// ❌ WRONG: Raw query mà không check site
$this->db->query("SELECT * FROM products WHERE status = 'ACTIVE'");
```

**Permission checks:**

```php
requirePermission('product.view');  // Global helper, throw 403 nếu deny
if (!hasPermission('product.edit')) {
    return $this->json(['error' => 'Denied'], 403);
}
```

## JSON Columns & Auto-Parsing

Models tự động decode các columns từ JSON:

-   `attributes`, `specs`, `payload`, `old_values`, `new_values`, `bom_data_json`

Cách sử dụng:

```php
$product = $model->find(1);
echo $product->attributes['color'];  // Auto-decoded by BaseModel
```

## File Locations

| Purpose       | Path                                |
| ------------- | ----------------------------------- |
| Controllers   | `app/controllers/{Module}/*.php`    |
| Models        | `app/models/{Module}/*.php`         |
| Services      | `app/services/{Module}/*.php`       |
| Views         | `app/views/{module}/{entity}/*.php` |
| Configs       | `app/config/*.php`                  |
| DB Schema     | `app/db_schema.sql`                 |
| Public Assets | `public/js/`, `public/uploads/`     |
| Helpers       | `app/helpers/*.php`                 |

## Module Independence & Code Organization Pattern

**Critical Rule**: Tính năng phải độc lập - **KHÔNG dùng chung mega Model hoặc Service**

### Cấu Trúc Module (Best Practice)

```
app/models/{Module}/
  ├── {Entity}Model.php           # Mỗi entity riêng lẻ
  ├── {Detail}Model.php
  └── ...

app/services/{Module}/
  ├── {Entity}Service.php         # Mỗi nghiệp vụ riêng
  ├── {Calculation}Service.php
  ├── {Workflow}Service.php
  └── {Helper}Service.php

app/dtos/{Module}/                # Data Transfer Objects (nếu cần)
  ├── {Entity}DTO.php
  └── {Request}DTO.php

app/requests/{Module}/            # Request validation
  ├── Store{Entity}Request.php
  └── Update{Entity}Request.php

app/helpers/{Module}/             # Helper riêng module
  ├── {Entity}Helper.php
  └── {Calculation}Helper.php
```

### Ví Dụ: Inventory Module (Cấu Trúc Độc Lập)

**❌ SAI**: Mega model
```php
// InventoryModel.php (500+ dòng, xử lý tất cả)
class InventoryModel extends BaseModel {
    // Receipt logic + Transfer logic + Delivery logic + Stock logic
}
```

**✅ ĐÚNG**: Chia ra độc lập
```
app/models/inventory/
├── InventoryReceiptModel.php      # Chỉ xử lý receipt
├── InventoryTransferModel.php     # Chỉ xử lý transfer
├── DeliveryNoteModel.php          # Chỉ xử lý delivery
├── WarehouseStockModel.php        # Chỉ xử lý stock
└── LotHistoryModel.php            # Chỉ xử lý lot tracking

app/services/inventory/
├── InventoryReceiptService.php    # Receipt workflow (validate, auto-create transaction)
├── InventoryTransferService.php   # Transfer workflow
├── InventoryShippingService.php   # Delivery workflow
├── LotGenerationService.php       # Auto lot creation + tracking
├── InventoryService.php           # Core (check available qty, update stock)
└── AutoAccounting_receipt.php     # Auto GL entries khi nhập

app/requests/inventory/
├── ReceiptStoreRequest.php        # Validate receipt input
├── TransferStoreRequest.php       # Validate transfer input
└── DeliveryStoreRequest.php       # Validate delivery input

app/helpers/inventory/
├── InventoryConstants.php         # Single source of truth: status codes + labels
├── InventoryCalculationHelper.php # Tính cost, weighted average, variance
├── InventoryValidationHelper.php  # Check stock availability, lot/bin validation
├── InventoryReportingHelper.php   # ABC classification, stock aging, turnover
├── InventoryDashboardHelper.php   # 14 KPI methods cho dashboard
├── InventoryReceiptItemProcessor.php # Receipt line item processing logic
└── OpeningStockHelper.php         # Opening stock import/calculation helper
```

### Ví Dụ: Purchasing Module (Cấu Trúc Độc Lập — Module Chuẩn 100%)

```
app/controllers/purchasing/               # 10 files, 5,235L
├── PurchaseOrderController.php           # 1,346L — PO CRUD + workflow + import/export + print + show + shipment + attachment
├── PurchaseRequestController.php         # 1,185L — PR CRUD + workflow + import + amendment + mobile
├── PurchasePriceListController.php       # 663L — Price list CRUD + import/export + history
├── PurchaseReturnController.php          # 483L — RTV CRUD + workflow + GL posting
├── MobileController.php                  # 412L — PWA mobile dashboard + push + pending approvals
├── PurchasingreportsController.php       # 408L — Reports: supplier perf, pending PO/PR + export
├── PurchaseOrderApiController.php        # 317L — JSON API: pending PR items, Select2 data, currency
├── PurchaseRequestApiController.php      # 248L — JSON API: product search, PR data, batch convert
├── PodetaillistingController.php         # 88L — PO detail lines listing + export
└── PrdetaillistingController.php         # 85L — PR detail lines listing + export

app/models/purchasing/                    # 7 files, 4,963L
├── PurchaseOrder.php                     # 1,493L — PO ORM + status + workflow + validation + financial
├── PurchaseRequest.php                   # 1,203L — PR ORM + workflow + sync merge + amendment
├── PurchaseOrderShipment.php             # 687L — Shipment tracking + batch load + tolerance
├── PurchaseReturnModel.php               # 629L — RTV + GL posting + workflow
├── PurchaseRequestShipment.php           # 415L — PR shipment scheduling + batch load
├── PrDetailListingModel.php              # 278L — PR detail lines aggregation
└── PoDetailListingModel.php              # 258L — PO detail lines aggregation

app/services/purchasing/                  # 15 files, 8,153L
├── PurchaseRequestImportService.php      # 941L — Bulk import PR from Excel
├── PurchaseOrderService.php              # 894L — Core PO business logic + CRUD + workflow
├── PurchaseRequestService.php            # 713L — Core PR business logic + CRUD + workflow
├── PurchaseReturnService.php             # 678L — RTV workflow + GL posting
├── PurchaseOrderWorkflowService.php      # 640L — Multi-level approval routing
├── ShipmentService.php                   # 630L — Shipment CRUD + ETA tracking
├── DocumentFlowService.php               # 629L — Document flow traceability
├── PurchaseRequestAmendmentService.php   # 532L — PR amendment lifecycle
├── PurchaseOrderImportService.php        # 524L — Bulk import PO from Excel
├── PurchaseOrderAmendmentService.php     # 467L — PO amendment lifecycle
├── POEmailService.php                    # 385L — PO workflow email notifications
├── PREmailService.php                    # 378L — PR workflow email notifications
├── AttachmentService.php                 # 376L — File upload/download/delete
├── PrDetailListingExportService.php      # 188L — Export PR detail listing to Excel
└── PoDetailListingExportService.php      # 178L — Export PO detail listing to Excel

app/requests/purchasing/                  # 3 files, 716L
├── PurchaseOrderFormRequest.php          # 342L — PO header + lines + shipment validation
├── PurchaseRequestFormRequest.php        # 313L — PR header + lines validation
└── PurchaseReturnStoreRequest.php        # 61L — RTV input validation

app/dtos/purchasing/                      # 3 files, 779L
├── PurchaseOrderDTO.php                  # 448L — PO data transformer
├── PurchaseRequestDTO.php                # 249L — PR data transformer
└── PurchaseReturnDTO.php                 # 82L — RTV data transformer

app/helpers/purchasing/                   # 18 files, 4,596L
├── PurchasingDashboardHelper.php         # 842L — Dashboard KPI methods (15+)
├── PurchaseOrderQueryHelper.php          # 583L — Extracted PO query methods
├── PurchaseRequestPrinter.php            # 392L — TCPDF print layout
├── DetailValidator.php                   # 375L — Line item validation
├── PurchaseOrderAttachmentHelper.php     # 321L — PO attachment path + validation
├── QuantityUpdater.php                   # 301L — Qty tracking (PR→PO→GRN sync)
├── PurchasingConstants.php               # 247L — Status constants + labels + sqlIn()
├── PurchaseOrderWarehouseHelper.php      # 245L — Warehouse validation + control
├── PurchaseRequestAttachmentHelper.php   # 240L — PR attachment path + validation
├── FinancialCalculator.php               # 195L — Header totals (VAT, discount, grand total)
├── ToPoBatchHelper.php                   # 194L — Batch convert PR→PO
├── DetailCalculator.php                  # 160L — Line-level calculations
├── PoReportHelper.php                    # 157L — PO report queries
├── PurchaseReturnHelper.php              # 126L — RTV helper + GL calc
├── PurchasingNotificationHelper.php      # 96L — Email recipients lookup
├── ReportHelper.php                      # 85L — PR report utilities
├── SequenceGenerator.php                 # 19L — PR code auto-gen (delegates to DocumentSequenceService)
└── PoSequenceGenerator.php               # 18L — PO code auto-gen (delegates to DocumentSequenceService)

app/views/purchasing/                     # 70 files, 17,872L
├── orders/          # 27 files — PO views (create, edit, index, show, print, import, dashboard, 14 _show_* partials, _form, _modals)
├── requests/        # 19 files — PR views (create, edit, index, show, print, import, dashboards, 9 _show_* partials, _form, _modals)
├── pricelist/       # 7 files — Price list views (CRUD, show, _form, _lineform, _modals)
├── return/          # 6 files — RTV views (CRUD, show, _form, _modals)
├── mobile/          # 7 files — PWA mobile views (index, pending, notifications, settings, 403, _header, _footer)
├── po_detail_listing/  # 1 file — PO detail report view
├── pr_detail_listing/  # 1 file — PR detail report view
└── reports/         # 1 file — Supplier performance report

public/js/modules/purchasing/             # 7 files, 6,148L
├── purchase_order.js                     # 2,430L — PO form handler + Select2 + UOM conversion
├── purchase_request.js                   # 1,331L — PR form handler (hybrid catalog + manual)
├── purchase_return.js                    # 772L — RTV form handler + GRN mapping
├── shipment_widget.js                    # 613L — Shipment tracking widget
├── po_shipment_widget.js                 # 456L — Shipment grid widget
├── purchase_request_show.js               # 413L — PR detail page interactions
└── README.md                             # 133L — JS module documentation
```

### Control Flow: Request → DTO → Service → Model

```php
class PurchaseOrderController extends Controller {
    public function store() {
        // [1] Validate input qua Request class
        $request = new PurchaseOrderStoreRequest($_POST);
        if (!$request->validate()) {
            return $this->json(['errors' => $request->errors()], 422);
        }
        
        // [2] Convert to DTO (optional, cho complex data transform)
        $dto = new PurchaseOrderDTO($request->validated());
        
        // [3] Pass to Service (business logic)
        $service = $this->service('purchasing/PurchaseOrderService');
        $result = $service->createAndValidate($dto);  // Service handles workflow
        
        // [4] Service uses Model (ORM + audit)
        $model = $this->model('purchasing/PurchaseOrder');
        $id = $model->insert($result->toArray());  // Auto set site_id, created_by
        
        return $this->json(['success' => true, 'id' => $id]);
    }
    
    public function approve($id) {
        $model = $this->model('purchasing/PurchaseOrder');
        $po = $model->find($id);
        
        // Use workflow service for approval
        $workflowService = $this->service('purchasing/PurchaseOrderWorkflowService');
        $workflowService->submitForApproval($id, $_POST['approver_id']);
        
        return $this->json(['success' => true]);
    }
}
```

### Lợi Ích Của Module Independence

| Benefit | Explanation |
|---------|------------|
| **Single Responsibility** | Mỗi class làm 1 việc (Model = ORM, Service = logic, Request = validate) |
| **Reusability** | Service có thể gọi từ nhiều controllers/jobs |
| **Testability** | Mock từng service, DTO riêng lẻ (unit test dễ) |
| **Maintainability** | File nhỏ (150-250 dòng), dễ sửa, dễ hiểu |
| **Scalability** | Thêm feature mới không ảnh hưởng cũ |
| **Clear Ownership** | Biết data flow: Request → DTO → Service → Model |

### Checklist Khi Tạo Feature Mới

- [ ] Tạo Model riêng (không merge vào model khác)
- [ ] Tạo Service riêng cho nghiệp vụ (có thể reuse qua controller khác)
- [ ] Tạo Request class để validate (dùng qua `$this->service('FileUploader')` nếu có file)
- [ ] Tạo DTO nếu cần transform data (optional cho simple, bắt buộc cho complex)
- [ ] Tạo Helper nếu có tính toán phức tạp (dùng static method hoặc inject vào Service)
- [ ] Mỗi class = 1 clear responsibility
- [ ] Service chứa business logic, không nên chứa validation (Request làm việc đó)
- [ ] Model chứa ORM, không nên chứa business logic

---

## Modules Hiện Có

### Controllers

| Module | Controllers |
|--------|------------|
| auth | AuthController, AuthPortalController |
| dashboard | DashboardController |
| hr | EmployeeController, AttendanceController, LeaveRequestController, PayrollController, ContractController, HolidayController, JobTitleController, WorkshiftsController, LeaveTypeController, OvertimeRequestController, LeaveBalanceController, AttendanceSymbolController, ConfigController |
| inventory | StockController, InventoryReceiptController, InventoryTransferController, DeliveryNoteController, InventoryConfigController, LotHistoryController, StockCardController |
| production | WorkOrderController, BomController, ProductionPlanController, ShopFloorController, DrawingController |
| purchasing | PurchaseOrderController, PurchaseRequestController, PurchaseReturnController, PurchasePriceListController, PurchaseOrderApiController, PurchaseRequestApiController, PurchasingreportsController, MobileController, PodetaillistingController, PrdetaillistingController |
| finance | JournalEntryController, CoaController, ExchangeRateController, ApInvoiceController, ApPaymentController, TaxController, CostCenterController, GlperiodController, AccountingRulesController, PaymentTermController, ApReportController, ProjectController |
| sales | (Controllers in sales/) - SO, Quote workflows |
| production | (Controllers in production/) - Work orders, BOM, Planning |
| systems | System management, user, role, configuration |

### Models

| Module | Key Models |
|--------|-----------|
| masterdata | Product, Partner, Warehouse, UomUnit |
| hr | Employee, Attendance, LeaveRequest, Contract, JobTitle, LeaveType, WorkShift |
| inventory | WarehouseStock, InventoryReceipt, InventoryTransfer, StockCard, LotHistory |
| production | WorkOrder, Bom, Routing, RoutingStage, WorkCenter |
| purchasing | PurchaseOrder, PurchaseRequest, PurchaseOrderShipment, PurchaseRequestShipment, PurchaseReturnModel, PoDetailListingModel, PrDetailListingModel |
| finance | JournalEntry, Coa, ExchangeRate, GlPeriod |
| sales | SalesOrder, SalesQuote |

## Common Workflows

### Creating a New Feature

1. Tạo model extends `BaseModel` với table name & scope settings
2. Tạo controller extends `Controller` với permission checks
3. Load model: `$model = $this->model('Subfolder/Name')`
4. Return JSON: `$this->json($data, 200)` hoặc render view: `$this->view('module.action', $data)`
5. Route auto-discovered via folder/file naming

### Handling Transactions

```php
try {
    $this->db->beginTransaction();
    $result1 = $model->insert($data1);
    $result2 = $model->insert($data2);
    $this->db->commit();
} catch (Exception $e) {
    $this->db->rollBack();
    error_log("[ERROR] " . $e->getMessage());
}
```

**Note**: Database hỗ trợ nested transaction via counter (không double-commit)

### Testing Queries

Enable query logging: inspect `$this->db->lastQuery` trong debug mode (APP_ENV=development show detailed errors)

## Dependency Management

-   **Composer packages**: QR code, barcodes, PHPMailer, PHPSpreadsheet (xem `composer.json`)
-   Load via: `require_once APPROOT . '/vendor/autoload.php'` (auto-loaded trong bootstrap)
-   PSR-4 namespaces: `App\Libraries\`, `Zkteco\` (for face/time clock integration)

## Key Gotchas

1. **Luôn dùng parameterized queries** - raw string concatenation break site security
2. **Check `file_exists()` trước `require`** - prevent fatal errors on missing files
3. **Session-based auth** - set `$_SESSION['user_id']` & `$_SESSION['user_site_id']` trong AuthController
4. **Transaction counter**: Database hỗ trợ nested transactions via counter (đừng double-commit)
5. **Soft deletes**: Models với `useSoftDeletes = true` tự động filter `deleted_at IS NULL`
6. **SQL phải đúng 100%**: Trước khi viết query, BẮT BUỘC tra `app/db_schema.sql` để verify tên bảng, tên cột. Không được đoán — sai tên cột = runtime error
7. **UPDATE not DELETE+INSERT**: Mọi thao tác sửa dữ liệu phải dùng **UPDATE**, tuyệt đối **KHÔNG** DELETE rồi INSERT. Pattern upsert phải check FK/linked data trước khi xóa row. Lý do: bảo toàn FK integrity, audit trail (`created_at`/`created_by`), tránh race condition
8. **Race-safe writes**: Dùng `SELECT ... FOR UPDATE` + atomic `UPDATE ... WHERE old_value = :expected` (WHERE guard) cho concurrent operations. Dùng `NULLIF(divisor, 0)` trong SQL để tránh division-by-zero
9. **Site isolation qua JOIN**: Không dùng `$_SESSION['user_site_id']` trực tiếp trong model queries — JOIN qua parent table có `site_id` column
10. **⛔ KHÔNG dùng `create_file` cho file đã tồn tại**: AI agent tuyệt đối KHÔNG được dùng tool `create_file` trên file có sẵn — sẽ ghi đè toàn bộ nội dung và có thể gây corruption hàng loạt file khác (incident 2026-04-20: 343 file bị empty 0 bytes). **BẮT BUỘC** dùng `replace_string_in_file` hoặc `multi_replace_string_in_file` để sửa file. Luôn `read_file` trước khi quyết định tool nào dùng.
11. **Giới hạn file operations**: Không thực hiện quá **50 file edits** trong một phiên. Nếu cần sửa nhiều hơn, chia thành nhiều phiên nhỏ. Mỗi phiên phải verify kết quả trước khi tiếp tục.
12. **Verify sau mỗi batch edit**: Sau mỗi đợt sửa file (>10 files), chạy `Get-ChildItem -Recurse app/ -Filter *.php | Where-Object { $_.Length -eq 0 } | Measure-Object` để kiểm tra không có file nào bị empty.
13. **PDO placeholder không được reuse**: Hệ thống chạy `ATTR_EMULATE_PREPARES=false` (server-side prepares). Mỗi named placeholder (`:kw`, `:uid`, `:date`...) chỉ được xuất hiện **ĐÚNG 1 LẦN** trong câu SQL. Nếu cần dùng cùng giá trị nhiều nơi, đặt tên khác nhau (`:kw1`, `:kw2`, `:kw3`) và bind riêng từng cái. Vi phạm = Fatal `SQLSTATE[HY093]`. Session 39 audit đã fix 16 files — xem `MODULE_COMPLETION_ROADMAP.md` §VI.

## Language & Conventions

-   Code comments: **Tiếng Việt** (chuẩn của codebase)
-   Database columns: snake_case (`created_at`, `updated_by`)
-   PHP classes: PascalCase (`ProductModel`, `InventoryService`)
-   Config constants: UPPERCASE (`DB_HOST`, `APP_VERSION`)
-   Timestamps: MySQL format `YYYY-MM-DD HH:MM:SS` (UTC/Asia/Ho_Chi_Minh timezone)

## Views & Frontend Standardization (ORACLE ENGINEER STANDARD - V2)

**Mục tiêu:** Loại bỏ form duplication, centralize modals/JS, improve maintainability

### View File Structure Pattern

Mỗi module nên follow cấu trúc này:

```
app/views/{module}/{entity}/
├── index.php                 # List/dashboard view
├── create.php               # Form tạo mới (70-80 dòng, include _form.php)
├── edit.php                 # Form chỉnh sửa (75-85 dòng, include _form.php)
├── show.php                 # Chi tiết view (optional)
├── _form.php               # Shared form cho create/edit (200-300 dòng) ✅
├── _modals.php             # Reusable modals (150-200 dòng) ✅
└── (JS chuyển sang public/js/modules/)

public/js/modules/
├── {entity}.js             # Complete form/AJAX/modal handling (300-400 dòng) ✅
└── (Một file duy nhất per entity, handle tất cả interactions)
```

### Implementation Guidelines

#### 1. Create `_form.php` (Shared Form)

**Mục đích:** Single form template dùng cho cả create.php và edit.php

```php
<?php
// Detect mode từ $data['employee'] (hoặc similar)
$isEdit = isset($data['employee']) && !empty($data['employee']->id);
$entity = $isEdit ? $data['employee'] : (object)[...defaults...];

// Determine form action URL
$formAction = $isEdit
    ? URLROOT . '/hr/employee/update/' . $entity->id
    : URLROOT . '/hr/employee/store';
?>

<form id="form-<?= $isEdit ? 'edit' : 'create' ?>" method="POST" action="<?= $formAction ?>" enctype="multipart/form-data">
    <?php csrf_field(); ?>

    <!-- Form fields ở đây - same cho cả 2 modes -->
    <!-- Dùng $entity->field để data binding -->
    <!-- Dùng $isEdit ? ... : ... để show/hide fields -->
    
    <div class="form-group">
        <label>Mã nhân viên</label>
        <input type="text" name="code" value="<?= e($entity->code ?? '') ?>" required>
    </div>

    <?php if ($isEdit): ?>
        <div class="form-group">
            <label>Trạng thái</label>
            <select name="status" required>
                <option value="ACTIVE" <?= $entity->status === 'ACTIVE' ? 'selected' : '' ?>>Hoạt động</option>
            </select>
        </div>
    <?php endif; ?>

    <button type="submit"><?= $isEdit ? 'Cập nhật' : 'Tạo mới' ?></button>
</form>
```

**Key Points:**

-   Single form handle cả create và edit modes
-   Detect mode từ `$data['entity']` existence
-   Routes POST đúng controller action (store vs update/{id})
-   Form ID khác nhau cho JS targeting: `form-employee-create` vs `form-employee-edit`
-   Show/hide fields dựa trên `$isEdit` flag

#### 2. Create `_modals.php` (Shared Modals)

**Mục đích:** Reusable Bootstrap 5 modal templates cho common actions

```php
<!-- Reset Password Modal -->
<div class="modal fade" id="modalResetPassword" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Đặt lại mật khẩu</h5>
                <button type="button" class="btn-close" data-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Nhân viên: <strong id="empNameDisplay"></strong>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="submitResetPassword()">Xác nhận</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="modalDeleteEmployee" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Xóa nhân viên</h5>
            </div>
            <div class="modal-body">
                Bạn chắc chắn muốn xóa <strong id="empNameDelete"></strong>?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-danger" onclick="submitDeleteEmployee()">Xóa</button>
            </div>
        </div>
    </div>
</div>
```

**Key Points:**

-   3-4 modals per entity (reset/delete/status/import)
-   Dùng data-binding elements với IDs để JS populate
-   Modal buttons gọi JS functions: `onclick="submitResetPassword()"`
-   Bootstrap 5 structure: `.modal-dialog`, `.modal-content`, `.modal-header`, `.modal-body`, `.modal-footer`
-   Mỗi modal có unique `id=""` cho bootstrap.Modal('#{id}').show()

#### 3. Create `{entity}.js` (Centralized JavaScript)

**Mục đích:** Tất cả form/AJAX/modal handling ở một file

```javascript
// [1] CONFIG OBJECT - Nhận từ PHP via <script> tag
const EMPLOYEE_CONFIG = {
    isEditMode: false,
    currentEmployeeId: null,
    urls: {
        store: "/hr/employee/store",
        update: "/hr/employee/update/",
        resetPassword: "/hr/employee/reset-password/",
        delete: "/hr/employee/delete/",
    },
};

// [2] DOMContentLoaded - Initialize
document.addEventListener("DOMContentLoaded", function () {
    setupEventListeners();
});

// [3] EVENT LISTENERS
function setupEventListeners() {
    const form = document.getElementById("form-employee");
    if (form) {
        form.addEventListener("submit", confirmFormSubmit);
    }
}

// [4] Form Submit
function confirmFormSubmit(e) {
    e.preventDefault();
    if (confirm("Bạn chắc chắn muốn lưu?")) {
        this.submit();
    }
}

// [5] AJAX METHODS
function loadChildren() {
    const parentId = document.getElementById("parent_id").value;
    const formData = new FormData();
    formData.append("parent_id", parentId);
    formData.append("csrf_token", getCsrfToken());

    fetch(EMPLOYEE_CONFIG.urls.load_children, { 
        method: "POST", 
        body: formData 
    })
        .then((r) => r.json())
        .then((data) => {
            // Populate select from JSON response
        });
}

// [6] MODAL HANDLERS
function confirmResetPassword(id, name) {
    EMPLOYEE_CONFIG.currentEmployeeId = id;
    document.getElementById("empNameDisplay").textContent = name;
    new bootstrap.Modal(document.getElementById("modalResetPassword")).show();
}

function submitResetPassword() {
    const id = EMPLOYEE_CONFIG.currentEmployeeId;
    fetch(EMPLOYEE_CONFIG.urls.resetPassword + id, {
        method: "POST",
        body: getFormDataWithCSRF(),
    })
        .then((r) => r.json())
        .then((data) => {
            if (data.success) {
                showAlert("Thành công", "success");
                location.reload();
            } else {
                showAlert(data.message || "Lỗi", "error");
            }
        })
        .catch(err => {
            showAlert("Lỗi kết nối", "error");
            console.error(err);
        });
}

// [7] UTILITIES
function getCsrfToken() {
    return document.querySelector('input[name="csrf_token"]')?.value || "";
}

function getFormDataWithCSRF() {
    const fd = new FormData();
    fd.append("csrf_token", getCsrfToken());
    return fd;
}

function showAlert(msg, type) {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const alert = document.createElement("div");
    alert.className = `alert ${alertClass} alert-dismissible fade show`;
    alert.innerHTML = msg + '<button type="button" class="btn-close" data-dismiss="alert"></button>';
    document.body.prepend(alert);
    setTimeout(() => alert.remove(), 5000);
}
```

**Key Points:**

-   Single file handle TẤT CẢ JS interactions cho entity
-   Sections: CONFIG, DOMContentLoaded, setupEventListeners, AJAX, modals, utilities
-   ENTITY_CONFIG passed từ PHP via `<script>` tag trong view
-   Fetch API cho AJAX (modern, native)
-   Modal functions: `confirm{Action}(id, name)` để show + `submit{Action}()` để send
-   Error handling with try-catch & user alerts
-   CSRF token automatically included trong requests

#### 4. Refactor `create.php` & `edit.php`

**Trước:** 300-400 dòng với full form HTML

**Sau:** 70-80 dòng

```php
<?php require APPROOT . '/views/layouts/header.php'; ?>

<div class="container-fluid mt-4 mb-5">
    <!-- Title bar -->
    <h4><?= $isEdit ? 'Chỉnh sửa' : 'Tạo mới' ?> Nhân viên</h4>
    <?php flash('msg'); ?>

    <!-- Include shared form -->
    <div class="card">
        <div class="card-body">
            <?php require APPROOT . '/views/hr/employee/_form.php'; ?>
        </div>
    </div>
</div>

<!-- Include shared modals -->
<?php require APPROOT . '/views/hr/employee/_modals.php'; ?>

<?php require APPROOT . '/views/layouts/footer.php'; ?>

<!-- External JS libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Pass config to JS -->
<script>
    const URLROOT = "<?= URLROOT ?>";
    const EMPLOYEE_CONFIG = {
        isEditMode: <?= $isEdit ? 'true' : 'false' ?>,
        currentEmployeeId: <?= $isEdit ? $entity->id : 'null' ?>,
        urls: { 
            store: "<?= URLROOT ?>/hr/employee/store",
            update: "<?= URLROOT ?>/hr/employee/update/",
            resetPassword: "<?= URLROOT ?>/hr/employee/reset-password/"
        }
    };
</script>

<!-- Entity JS -->
<script src="<?= URLROOT ?>/js/modules/employee.js"></script>
```

**Key Points:**

-   Header/title/flash messages
-   Include \_form.php (single component cho cả 2 modes)
-   Include \_modals.php (tất cả modal templates)
-   Include footer
-   Pass ENTITY_CONFIG từ PHP đến JS
-   Reference entity.js file

#### 5. Update `index.php`

Add to footer section:

```php
<!-- Include modals for table actions -->
<?php require APPROOT . '/views/hr/employee/_modals.php'; ?>

<!-- Entity JS handles modal actions -->
<script src="<?= URLROOT ?>/js/modules/employee.js"></script>

<script>
    // Config for index page
    const URLROOT = "<?= URLROOT ?>";
    const EMPLOYEE_CONFIG = { 
        urls: {
            resetPassword: "<?= URLROOT ?>/hr/employee/reset-password/",
            delete: "<?= URLROOT ?>/hr/employee/delete/"
        }
    };
</script>
```

**Key Points:**

-   Table action buttons gọi JS functions: `onclick="confirmResetPassword(id, name)"`
-   JS functions được define trong entity.js
-   Modals được share (\_modals.php)
-   Cùng EMPLOYEE_CONFIG object manage state

### Code Reduction Results

| Component    | Before    | After     | Reduction            |
| ------------ | --------- | --------- | -------------------- |
| create.php   | 352 lines | 70 lines  | 80% ↓                |
| edit.php     | 412 lines | 75 lines  | 82% ↓                |
| \_form.php   | Scattered | 274 lines | Centralized ✅       |
| \_modals.php | Scattered | 170 lines | Centralized ✅       |
| {entity}.js  | None      | 420 lines | New single source ✅ |

### Applied Modules

-   ✅ **HR Employee** - Complete (create.php, edit.php, index.php refactored)
-   ⏳ **HR Contract** - To be standardized
-   ⏳ **HR LeaveRequest** - To be standardized
-   ✅ **Purchasing** - Complete (module chuẩn 100%, 133 files, 48K+ lines, Go-Live Audit Phase 1-8 hoàn thành)
-   (Tất cả modules khác follow same pattern)

### Testing Checklist per View

```
Create Mode:
  ✓ Page loads with empty form
  ✓ Tất cả required fields marked
  ✓ Dynamic selects populate via AJAX
  ✓ File upload validates (size, type)
  ✓ Form submit validates before POST
  ✓ Confirmation dialog shown
  ✓ POST to /module/entity/store succeeds

Edit Mode:
  ✓ Page loads with data pre-filled
  ✓ isEditMode = true trong JS config
  ✓ Edit-only fields visible (status selector, etc.)
  ✓ Form routes to /module/entity/update/{id}
  ✓ Modal buttons show/hide correctly

Modal Actions:
  ✓ Password reset modal shows employee name
  ✓ Delete modal requires confirmation
  ✓ Status modal shows termination reason khi cần
  ✓ Import modal validates file type
  ✓ AJAX responses trigger alerts/redirects
```

### Common Pitfalls to Avoid

1. **Đừng repeat form HTML** - Dùng \_form.php cho cả create/edit
2. **Đừng embed JS trong views** - Move to {entity}.js module
3. **Đừng hardcode URLs** - Dùng URLROOT + ENTITY_CONFIG từ PHP
4. **Đừng forget CSRF tokens** - Auto-include trong forms + AJAX requests
5. **Đừng ignore file validations** - Server + client side (2 layers)
6. **Đừng make modals per-action** - Dùng \_modals.php cho tất cả common actions

---

## Key Security Rules

### 1. Environment Variables

**❌ SAI**: Hardcode credentials
```php
define('DB_HOST', 'localhost');
define('DB_PASS', 'secret123');
```

**✅ ĐÚNG**: Dùng .env
```
// .env
DB_HOST=localhost
DB_PASS=secret
MAILER_PASSWORD=emailpass
```

```php
$host = getenv('DB_HOST');
$pass = getenv('DB_PASS');
```

### 2. XSS Prevention

**❌ SAI**: Echo trực tiếp
```php
<?= $user_input ?>
```

**✅ ĐÚNG**: Dùng escape helpers
```php
<?= e($user_input) ?>
<?= htmlspecialchars($var, ENT_QUOTES, 'UTF-8') ?>
<?= esc_attr($attribute) ?>
<?= esc_url($url) ?>
```

### 3. CSRF Protection

**❌ SAI**: Form POST mà không có CSRF
```php
<form method="POST">
    <input name="name">
</form>
```

**✅ ĐÚNG**: Thêm CSRF field
```php
<form method="POST">
    <?php csrf_field(); ?>
    <input name="name">
</form>
```

### 4. File Upload Security

**❌ SAI**: Chỉ check extension
```php
if (strpos($file, '.pdf')) { ... }
```

**✅ ĐÚNG**: Validate MIME + Extension + Rename
```php
$uploader = $this->service('FileUploader');
$result = $uploader->upload($_FILES['doc'], ['pdf', 'doc'], ['application/pdf']);
```

### 5. Rate Limiting & Security Logging

```php
// Rate limit login attempts
rate_limit('login_attempt', 5, 300);  // 5 attempts per 5 minutes

// Log suspicious activity
log_security_event('suspicious_input_detected', [
    'user_id' => $userId,
    'input' => $suspicious_input,
    'ip' => $_SERVER['REMOTE_ADDR']
]);
```

## Helpers Bắt Buộc Biết

| File | Hàm quan trọng |
|------|--------------|
| session_helper.php | `isLoggedIn()`, `currentUser()`, `hasPermission()`, `requirePermission()`, `flash()`, `csrf_field()`, `csrf_token()` |
| url_helper.php | `URLROOT`, `APPROOT`, `redirect()`, `base_url()`, `asset_path()`, `asset_v()` |
| format_helper.php | `format_date()`, `format_currency()`, `format_number()`, `format_datetime()` |
| security_helper.php | `e()`, `esc_attr()`, `esc_url()`, `esc_js()`, `sanitize_int()`, `sanitize_email()`, `sanitize_filename()`, `rate_limit()`, `log_security_event()` |
| AccessControlHelper.php | `getAccessibleDepartments()`, `getAccessibleWarehouses()` |

## Checklist Trước Commit

- [ ] Model extends BaseModel (nếu có model)
- [ ] SQL dùng bind(), không nối string
- [ ] Form POST có `<?php csrf_field(); ?>`
- [ ] Output trong view dùng `e()` hoặc `htmlspecialchars()`
- [ ] Check quyền trước hành động nhạy cảm
- [ ] Verify site_id scope (query có tôn trọng site)
- [ ] File upload validate MIME type (nếu có)
- [ ] Transaction dùng beginTransaction/commit/rollBack
- [ ] Không hardcode sensitive data (dùng .env)
- [ ] **KHÔNG hardcode** status/type/dropdown → dùng `lookups_list.php` sync DB
- [ ] **KHÔNG hardcode** permissions → dùng `permissions_list.php` sync DB
- [ ] **KHÔNG hardcode** menu items → dùng `menu_structure.php` sync DB
- [ ] **SQL query phải đúng 100%** tên bảng, tên cột → tra `app/db_schema.sql` trước khi viết query
- [ ] View theo chuẩn (create/edit ngắn, _form.php + _modals.php shared)
- [ ] JS ở file riêng (public/js/modules/{entity}.js)
- [ ] JS/CSS local dùng `asset_v()` — **KHÔNG** dùng bare `URLROOT` hay `?v=<?= time() ?>`
- [ ] **Browser tab title**: Mọi `$this->view()` phải truyền `'title' => 'Tiêu đề trang'` trong `$data` (header.php dùng `$data['title']`)
- [ ] CSRF token included trong AJAX requests
- [ ] **KHÔNG có file 0 bytes**: Chạy `Get-ChildItem -Recurse app/ -Filter *.php | Where-Object { $_.Length -eq 0 }` — phải trả về 0 kết quả (trừ 2 placeholder: `views/core/approval/dashboard.php`, `views/purchasing/orders/_show_flow.php`)
- [ ] **AI agent KHÔNG dùng `create_file` trên file đã tồn tại** — chỉ dùng `replace_string_in_file`

## Config-Driven Architecture (KHÔNG Hardcode)

**Nguyên tắc**: Mọi giá trị cấu hình phải qua config file hoặc DB — KHÔNG hardcode trong code.

| Cần thêm | Config File | Sync DB Table |
|----------|-------------|--------------|
| Status/Type/Dropdown values | `app/config/lookups_list.php` | `sys_lookups` |
| Menu items / Feature flags | `app/config/menu_structure.php` | `sys_menus` + `sys_features` |
| Permissions | `app/config/permissions_list.php` | `sys_permissions` |
| Module overrides | `app/config/modules_list.php` | `sys_modules` |
| Module constants | `app/config/{module}.php` | Static (`define()`) |

**Workflow**: Edit config file → Admin sync → DB mirrors config → Code queries DB at runtime.

Chi tiết pattern: xem `.github/oracle-erp/architecture/enterprise-patterns.md` §7

## Nơi Tra Cứu

- **Schema**: app/db_schema.sql - Kiểm tra tên bảng, cột, data types
- **Quyền**: app/config/permissions_list.php - Danh sách tất cả permissions
- **Lookups**: app/config/lookups_list.php - Status/type/dropdown values (KHÔNG hardcode)
- **Menu**: app/config/menu_structure.php - Menu items + feature flags
- **Cấu hình**: app/config/config.php - APP_ENV, timezone, CORS, etc.
- **Bootstrap**: app/bootstrap.php - Load sequence, helpers, config
- **Router**: app/core/App.php - URL routing logic, folder depth
- **Base Model**: app/core/BaseModel.php - ORM methods, site scoping, soft deletes
- **Base Controller**: app/core/Controller.php - CSRF, permission, lazy load
- **Database**: app/core/Database.php - PDO singleton, transaction counter
- **Oracle Knowledge**: .github/oracle-erp/ - Oracle EBS patterns, module specs, templates
- **AI Context**: .github/ai-agent-context.md - Quick reference

---

**Ghi chú cuối**: Khi nghi ngờ, hãy xem các file controller/model hiện có để làm reference. Kiến trúc này đã được kiểm chứng qua hàng trăm hàm trong hơn 20 modules.
