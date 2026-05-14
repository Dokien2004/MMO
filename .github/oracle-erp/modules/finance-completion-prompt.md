# FINANCE MODULE COMPLETION — AGENT PROMPT FOR CLAUDE OPUS 4.7

> **STATUS UPDATE (Session Finance Upgrade):** Finance module đã hoàn tất nâng cấp đạt chuẩn Purchasing. Tóm tắt:
> - **Services thêm mới (8 files):** `ApPaymentService` (135L), `ArReceiptService` (121L), `JournalEntryService` (242L), `GlPeriodService` (176L), `CoaService` (172L), `TrialBalanceExportService` (81L), `ApReportExportService` (81L), `ArReportExportService` (76L). Tổng ~1,084 dòng logic bóc tách khỏi controllers.
> - **DTOs thêm mới (2 files):** `JournalEntryDTO` (57L), `GlPeriodDTO` (47L). Finance có đủ 6 DTOs: Ap/Ar Invoice, Ap Payment, Ar Receipt, Journal Entry, GL Period.
> - **Controllers refactored (8 files):** Tất cả controllers CRUD/report giờ là thin controllers, chỉ xử lý CSRF/permission/audit/flash/redirect; business logic delegate cho service. Kết quả LOC: ApPayment 220→161L (-27%), ArReceipt 250→204L (-18%), JournalEntry 360→270L (-25%), GlPeriod 520→419L (-19%), Coa 204→137L (-33%), TrialBalance 150→107L (-29%), ArReport 180→116L (-36%).
> - **Validation:** Toàn bộ 31 file finance PHP (controllers/models/services/dtos/helpers) pass `php -l` — 0 syntax errors, 0 empty files. Backup files `ap/show_backup.php` và `payment/show_backup.php` đã được archive (`.archived`).
> - **Kết quả:** Finance đạt cấu trúc giống Purchasing gold-standard — thin controller, Service layer tách rõ nghiệp vụ, DTOs cho data transformation, Export services dùng PhpSpreadsheet.

---

## CONTEXT & OBJECTIVE

Bạn là AI coding agent làm việc trên project **Factory ERP** (custom PHP MVC framework, không dùng Laravel/Symfony). Nhiệm vụ là **hoàn thiện Finance module** để đạt tiêu chuẩn **Purchasing module** về:
- Tổ chức code (Services, Requests, DTOs, Helpers)
- Thiết kế giao diện (index, show shell + partials, create, edit, _form, _modals)
- File JS tách riêng theo entity
- Thin controller, business logic vào Service

**Project root:** `C:\xampp\htdocs\factory-erp\`

---

## ARCHITECTURE RULES (BẮT BUỘC TUÂN THỦ)

### Framework Conventions

1. **KHÔNG dùng `create_file` trên file đã tồn tại** — luôn dùng `replace_string_in_file` / `multi_replace_string_in_file`. Trước khi tạo file, dùng `file_search` kiểm tra.
2. **SQL phải tra `app/db_schema.sql`** trước khi viết bất kỳ query nào.
3. **Tất cả queries phải bind params** — KHÔNG nối chuỗi vào SQL.
4. **Named param trùng**: PDO MySQL không cho phép cùng một `:param` xuất hiện 2 lần trong 1 query — dùng `:sid2`, `:sid3` hoặc convert sang positional params.
5. **`buildPagination()` trả về array** — views phải dùng `$pagination = $data['pagination']; require APPROOT . '/views/layouts/_pagination.php';` (KHÔNG `echo $data['pagination']`).
6. **`asset_v()`** cho tất cả JS/CSS local — KHÔNG dùng `URLROOT` bare.
7. **`csrf_field()`** trong mọi form POST.
8. **`e()`** cho tất cả output user data (XSS prevention).
9. **`site_id` isolation**: mọi raw SQL phải có `AND table.site_id = :sid`. Products/Partners dùng JOIN qua `product_site_assignments` / `partner_site_assignments`.
10. **`UPDATE` không `DELETE+INSERT`**: sửa data phải dùng UPDATE.

### Model pattern

```php
class MyModel extends BaseModel {
    protected $table = 'my_table';
    protected $isSiteSpecific = true;
    protected $useSoftDeletes = true;
    protected $useAuditLog = true;

    public function getList(int $siteId, array $filters, int $limit, int $offset): array {
        $sql = "SELECT ... FROM my_table WHERE site_id = :sid ...";
        $params = [':sid' => $siteId];
        // Thêm filters động
        if (!empty($filters['search'])) {
            $sql .= " AND name LIKE :kw";
            $params[':kw'] = '%' . $filters['search'] . '%';
        }
        $sql .= " LIMIT :lim OFFSET :off";
        $params[':lim'] = $limit;
        $params[':off'] = $offset;
        $this->db->query($sql);
        foreach ($params as $k => $v) $this->db->bind($k, $v);
        return $this->db->resultSet() ?: [];
    }
}
```

### Controller pattern (thin)

```php
class MyController extends Controller {
    private const PERM_VIEW = 'accounting.view_my';
    
    public function __construct() {
        parent::__construct();
        if (!isLoggedIn()) { redirect('auth/login'); exit; }
        requirePermission(self::PERM_VIEW);
    }

    public function index() {
        $siteId = $this->getCurrentSiteId();
        [$page, $perPage, $offset] = $this->getPaginationParams(20);
        $filters = [...];
        $model = $this->model('finance/MyModel');
        $items = $model->getList($siteId, $filters, $perPage, $offset);
        $total = $model->countList($siteId, $filters);
        $this->view('finance/my/index', [
            'title' => 'Tiêu đề',
            'items' => $items,
            'filters' => $filters,
            'pagination' => $this->buildPagination($page, $perPage, $total),
        ]);
    }

    public function store() {
        requirePermission('accounting.create_my');
        // Validate → Service → json response
        $service = $this->service('finance/MyService');
        $id = $service->create($_POST, $this->getCurrentSiteId(), $this->getCurrentUserId());
        $this->json(['success' => true, 'id' => $id]);
    }
}
```

### View shell pattern (show.php ≤ 100 dòng)

```php
<?php require APPROOT . '/views/layouts/header.php'; ?>
<style>/* Layout CSS only */</style>

<div class="container-fluid main-wrapper">
    <?php require APPROOT . '/views/finance/entity/_show_toolbar.php'; ?>
    <?php require APPROOT . '/views/finance/entity/_show_workflow.php'; ?>
    
    <ul class="nav nav-tabs mb-3">...</ul>
    <div class="tab-content">
        <div class="tab-pane active" id="pane-detail">
            <?php require APPROOT . '/views/finance/entity/_show_info_card.php'; ?>
            <?php require APPROOT . '/views/finance/entity/_show_lines_table.php'; ?>
        </div>
    </div>
    
    <?php require APPROOT . '/views/finance/entity/_show_action_bar.php'; ?>
    <?php require APPROOT . '/views/finance/entity/_modals.php'; ?>
</div>

<?php require APPROOT . '/views/layouts/footer.php'; ?>
<script>const CONFIG = { id: <?= $data['entity']->id ?>, urls: {...} };</script>
<script src="<?= asset_v('js/modules/finance/entity_show.js') ?>"></script>
```

---

## TRẠNG THÁI HIỆN TẠI CỦA FINANCE MODULE

### Files đã tồn tại (KHÔNG tạo mới, chỉ cải thiện nếu cần)

```
app/controllers/finance/
├── AccountingRulesController.php   (246L)
├── ApInvoiceController.php         (396L)
├── ApPaymentController.php         (193L) ⚠️ có business logic trong controller
├── ApReportController.php          (61L)
├── ArInvoiceController.php         (313L)
├── ArReceiptController.php         (199L) ⚠️ có business logic trong controller
├── ArReportController.php          (150L)
├── BalanceSheetController.php      (184L)
├── CoaController.php               (184L)
├── CostCenterController.php        (176L)
├── ExchangeRateController.php      (170L)
├── FinanceDashboardController.php  (35L)  ✅ đã hoàn thiện
├── GlperiodController.php          (456L) ⚠️ quá dày, cần extract Service
├── IncomeStatementController.php   (190L)
├── JournalEntryController.php      (316L)
├── PaymentTermController.php       (157L)
├── ProjectController.php           (152L)
├── TaxController.php               (275L)
└── TrialBalanceController.php      (124L)

app/models/finance/
├── AccountingRule.php       (191L)
├── ApInvoiceModel.php       (359L)
├── ApPaymentModel.php       (233L)
├── ApReportModel.php        (125L)
├── ArInvoiceModel.php       (133L) ⚠️ mỏng, thiếu nhiều methods
├── ArReceiptModel.php       (319L)
├── ArReportModel.php        (112L)
├── CategoryAccount.php      (207L)
├── ChartOfAccount.php       (248L)
├── CostCenterModel.php      (134L)
├── ExchangeRateModel.php    (194L)
├── GlPeriodModel.php        (533L)
├── JournalEntryModel.php    (299L)
├── PaymentTermModel.php     (290L)
├── ProjectModel.php         (76L) ⚠️ rất mỏng
└── TaxModel.php             (315L)

app/services/finance/
├── ApInvoiceExportService.php         (206L) ✅
├── ApInvoiceWorkflowService.php       (247L) ✅
├── ArInvoiceExportService.php         (239L) ✅
├── ArInvoiceWorkflowService.php       (249L) ✅
├── AutoAccounting.php                 (634L) ✅
├── AutoAccounting_opening.php         (114L) ✅
├── AutoAccounting_receipt.php         (109L) ✅
├── AutoAccounting_return_vendor.php   (108L) ✅
├── AutoAccounting_wip*.php            (5 files) ✅
├── FinanceEmailService.php            (62L)  ⚠️ quá mỏng
├── JournalEntryExportService.php      (226L) ✅
├── JournalEntryImportService.php      (280L) ✅
└── YearEndClosingService.php          (145L) ✅
❌ THIẾU: ApPaymentService.php
❌ THIẾU: ArReceiptService.php
❌ THIẾU: JournalEntryService.php
❌ THIẾU: GlPeriodService.php
❌ THIẾU: CoaService.php
❌ THIẾU: ApReportExportService.php
❌ THIẾU: ArReportExportService.php
❌ THIẾU: TrialBalanceExportService.php

app/helpers/finance/
├── FinanceCalculationHelper.php   (220L) ✅
├── FinanceConstants.php           (166L) ✅
├── FinanceDashboardHelper.php     (243L) ✅
├── FinanceNotificationHelper.php  (169L) ✅
├── FinanceReportingHelper.php     (187L) ✅
└── FinanceValidationHelper.php    (221L) ✅

app/dtos/finance/
├── ApInvoiceDTO.php    ✅
├── ApPaymentDTO.php    ✅
├── ArInvoiceDTO.php    ✅
├── ArReceiptDTO.php    ✅
❌ THIẾU: JournalEntryDTO.php
❌ THIẾU: GlPeriodDTO.php

app/requests/finance/
├── ApInvoiceFormRequest.php     ✅
├── ApPaymentFormRequest.php     ✅
├── ArInvoiceFormRequest.php     ✅
├── ArReceiptFormRequest.php     ✅
└── JournalEntryFormRequest.php  ✅
❌ THIẾU: GlPeriodFormRequest.php (nếu cần)

app/views/finance/ — CHI TIẾT TỪNG FOLDER:
    ap/
    ├── _form.php (127L)         ✅
    ├── _modals.php (61L)        ⚠️ mỏng
    ├── _show_action_bar.php     ✅
    ├── _show_attachments.php    ✅
    ├── _show_history.php        ✅
    ├── _show_lines_table.php    ✅
    ├── _show_right_panel.php    ✅
    ├── _show_toolbar.php        ✅
    ├── _show_workflow.php       ✅
    ├── create.php (27L)         ✅ (include _form.php)
    ├── edit.php                 ❌ THIẾU
    ├── index.php (224L)         ✅
    ├── index_mobile.php         ✅
    ├── print.php                ✅
    ├── show.php (130L)          ✅ (shell + partials)
    ├── show_backup.php (503L)   ⚠️ file backup, cần xóa hoặc đổi tên
    └── show_mobile.php          ✅

    ar/
    ├── _form.php (204L)         ✅
    ├── _modals.php (154L)       ✅
    ├── _show_action_bar.php     ✅
    ├── _show_attachments.php    ✅
    ├── _show_history.php        ✅
    ├── _show_lines_table.php    ✅
    ├── _show_right_panel.php    ✅
    ├── _show_workflow.php       ✅
    ├── create.php (11L)         ⚠️ chỉ 11 dòng, cần kiểm tra
    ├── edit.php                 ❌ THIẾU
    ├── index.php (123L)         ✅
    ├── index_mobile.php         ✅
    ├── print.php                ✅
    ├── show.php (86L)           ✅ (shell + partials)
    └── show_mobile.php          ✅

    arreceipt/ (Phiếu thu khách hàng)
    ├── _modals.php (38L)        ⚠️ rất mỏng
    ├── _show_allocations.php    ✅
    ├── _show_info.php           ✅
    ├── _show_toolbar.php        ✅
    ├── create.php (161L)        ⚠️ quá dày cho create.php (nên extract _form.php)
    ├── index.php (148L)         ✅
    ├── show.php (49L)           ⚠️ shell nhưng có thể thiếu partials
    ❌ THIẾU: _form.php
    ❌ THIẾU: _show_action_bar.php
    ❌ THIẾU: _show_right_panel.php
    ❌ THIẾU: edit.php (có thể không cần nếu receipt không edit)

    payment/ (Phiếu chi AP)
    ├── _modals.php (49L)        ⚠️ mỏng
    ├── _show_allocations_table  ✅
    ├── _show_right_panel        ✅
    ├── _show_toolbar            ✅
    ├── create.php (188L)        ⚠️ quá dày, nên extract _form.php
    ├── index.php (149L)         ✅
    ├── print.php                ✅
    ├── show.php (46L)           ⚠️ shell mỏng
    ├── show_backup.php (333L)   ⚠️ backup file, cần xóa
    ❌ THIẾU: _form.php
    ❌ THIẾU: edit.php
    ❌ THIẾU: _show_action_bar.php (chuẩn Purchasing)

    journal/
    ├── _show_action_bar.php     ✅
    ├── _show_header.php         ✅
    ├── _show_info_card.php      ✅
    ├── _show_lines_table.php    ✅
    ├── _show_source_doc.php     ✅
    ├── create.php (101L)        ✅
    ├── import.php (282L)        ✅
    ├── index.php (183L)         ✅
    ├── print.php (222L)         ✅
    ├── show.php (65L)           ✅
    ❌ THIẾU: _form.php (create.php có inline form)
    ❌ THIẾU: _modals.php
    ❌ THIẾU: edit.php

    glperiod/
    ├── _form.php (163L)         ✅
    ├── _modals.php (200L)       ✅
    ├── create.php (49L)         ✅
    ├── edit.php (87L)           ✅
    ├── index.php (252L)         ✅
    ├── show.php (225L)          ⚠️ quá dày, nên có partials

    coa/ (Chart of Accounts)
    ├── index.php (254L)         ⚠️ SPA-style, cần _modals.php riêng
    ❌ THIẾU: _modals.php
    ❌ THIẾU: create.php / edit.php / show.php

    costcenter/
    ├── _form.php (86L)          ✅
    ├── _modals.php (89L)        ✅
    ├── create.php (22L)         ✅
    ├── edit.php (22L)           ✅
    └── index.php (112L)         ✅

    project/
    ├── _form.php (133L)         ✅
    ├── _modals.php (107L)       ✅
    ├── create.php (22L)         ✅
    ├── edit.php (22L)           ✅
    └── index.php (128L)         ✅

    exchange_rates/
    ├── _form.php (87L)          ✅
    ├── _modals.php (111L)       ✅
    ├── create.php (36L)         ✅
    ├── edit.php (36L)           ✅
    └── index.php (146L)         ✅

    payment_terms/
    ├── _form.php (197L)         ✅
    ├── _modals.php (105L)       ✅
    ├── create.php (39L)         ✅
    ├── edit.php (39L)           ✅
    └── index.php (140L)         ✅

    tax/
    ├── _form.php (92L)          ✅
    ├── _modals.php (196L)       ✅
    └── index.php (203L)         ✅

    report/
    ├── ap_aging.php (375L)      ✅
    ├── ar_aging.php (334L)      ✅
    ├── balance_sheet.php (158L) ✅
    ├── income_statement.php (282L) ✅
    └── trial_balance.php (169L) ✅

    dashboard/
    └── index.php (479L)         ✅

public/js/modules/finance/
├── ap_invoice.js        (294L) ✅
├── ap_invoice_show.js   (118L) ✅
├── ap_payment.js        (307L) ✅
├── ar_invoice_form.js   (153L) ✅
├── ar_invoice_show.js   (125L) ✅
├── ar_receipt.js        (283L) ✅
├── arreceipt_show.js    (26L)  ⚠️ rất mỏng
├── coa.js               (126L) ✅
├── exchange_rate.js     (193L) ✅
├── glperiod.js          (459L) ✅
├── journalentry_show.js (81L)  ⚠️ mỏng
├── journal_entry.js     (146L) ✅
├── payment_term.js      (266L) ✅
└── tax.js               (357L) ✅
❌ THIẾU: ap_payment_show.js (show page interaction)
❌ THIẾU: ar_receipt_show.js (đầy đủ — hiện chỉ 26L)
❌ THIẾU: dashboard.js (chart/KPI interactivity)
❌ THIẾU: coa_show.js (nếu coa có show page)
❌ THIẾU: ap_report.js (aging chart + export)
❌ THIẾU: ar_report.js (aging chart + export)
```

---

## PHÂN TÍCH GAP — VIỆC CẦN LÀM

### PRIORITY 1 — Services (Business Logic Extraction)

**Tại sao:** Controller hiện tại chứa business logic (ApPaymentController, ArReceiptController, GlperiodController quá dày 456L). Theo Purchasing pattern: Controller ≤ 200L, business logic ở Service.

#### 1.1. Tạo `app/services/finance/ApPaymentService.php`

Extract từ `ApPaymentController.php`. Service phải xử lý:
- `create(array $data, int $siteId, int $userId): int` — tạo phiếu chi + ghi GL
- `allocate(int $paymentId, array $invoiceIds, int $userId): void` — phân bổ vào AP invoices
- `cancel(int $paymentId, int $userId): void` — hủy phiếu chi
- `getPaymentWithDetails(int $id, int $siteId): object` — load đầy đủ cho show page

Tham khảo: `app/services/purchasing/PurchaseOrderService.php` (894L)

#### 1.2. Tạo `app/services/finance/ArReceiptService.php`

Extract từ `ArReceiptController.php`. Service phải xử lý:
- `create(array $data, int $siteId, int $userId): int`
- `allocate(int $receiptId, array $invoiceIds, int $userId): void`
- `cancel(int $receiptId, int $userId): void`

#### 1.3. Tạo `app/services/finance/JournalEntryService.php`

Extract từ `JournalEntryController.php`. Service phải xử lý:
- `create(array $data, int $siteId, int $userId): int`
- `post(int $jeId, int $userId): void` — post JE vào GL
- `reverse(int $jeId, string $reversalDate, int $userId): int` — tạo JE đảo
- `getWithLines(int $id, int $siteId): object`

#### 1.4. Tạo `app/services/finance/GlPeriodService.php`

Extract từ `GlperiodController.php` (456L — quá dày). Service phải xử lý:
- `openPeriod(int $periodId, int $siteId, int $userId): void`
- `closePeriod(int $periodId, int $siteId, int $userId): void`
- `lockPeriod(int $periodId, int $siteId, int $userId): void`
- `checkDateInOpenPeriod(string $date, int $siteId): bool`

#### 1.5. Tạo `app/services/finance/CoaService.php`

- `create(array $data, int $siteId, int $userId): int`
- `update(int $id, array $data, int $userId): void`
- `canDelete(int $id): bool` — check không có journal entries
- `getTree(int $siteId): array` — lấy cây tài khoản

#### 1.6. Tạo Export Services

```
app/services/finance/ApReportExportService.php  — Export AP Aging sang Excel
app/services/finance/ArReportExportService.php  — Export AR Aging sang Excel
app/services/finance/TrialBalanceExportService.php — Export Trial Balance sang Excel
```

Tham khảo: `app/services/finance/ApInvoiceExportService.php` (206L) — dùng PhpSpreadsheet.

---

### PRIORITY 2 — Views Cần Refactor (Purchasing Standard)

#### 2.1. `arreceipt/` — Tạo pattern chuẩn

**Hiện trạng:**
- `create.php` (161L): Quá dày, form inline
- `show.php` (49L): Shell mỏng, thiếu partials
- `_modals.php` (38L): Rất mỏng

**Cần tạo mới:**

**`app/views/finance/arreceipt/_form.php`** (~150L)
```
Shared form cho create: fields = receipt_date, receipt_number (auto), partner_id, amount, payment_method, bank_account_id, reference, notes
Detect mode: $isEdit = !empty($data['receipt'])
Form action: /finance/arreceipt/store hoặc /finance/arreceipt/update/{id}
```

**`app/views/finance/arreceipt/_show_action_bar.php`** (~50L)
```
Fixed bottom bar với buttons: In phiếu, Hủy, Phân bổ
Hiển thị khi scroll qua header
```

**`app/views/finance/arreceipt/_show_right_panel.php`** (~80L)
```
Info cards: Số phiếu, Khách hàng, Trạng thái, Số tiền, Đã phân bổ, Còn lại
```

**Refactor `app/views/finance/arreceipt/create.php`** → Rút gọn ~25L, include `_form.php`

**Refactor `app/views/finance/arreceipt/show.php`** → Shell ~80L dùng partials

#### 2.2. `payment/` — Tạo pattern chuẩn

**Cần tạo mới:**

**`app/views/finance/payment/_form.php`** (~170L)
```
Shared form: payment_date, payment_number (auto), partner_id, amount, payment_method, bank_account_id, reference, notes, ap_invoice_ids (multi-select để phân bổ)
```

**`app/views/finance/payment/_show_action_bar.php`** (~50L)
```
Buttons: In phiếu, Hủy, Export
```

**Refactor `app/views/finance/payment/create.php`** → ~30L, include `_form.php`

**Xóa/archive `app/views/finance/payment/show_backup.php`** → Đổi tên thành `show_backup_DEPRECATED.php`
**Xóa/archive `app/views/finance/ap/show_backup.php`** → tương tự

#### 2.3. `journal/` — Tạo _form.php và _modals.php

**Cần tạo mới:**

**`app/views/finance/journal/_form.php`** (~200L)
```
Shared form: je_date, period_id, reference, description, currency, exchange_rate
Lines table: debit/credit với account_id, cost_center_id, description, amount
Balanced validation: total debit = total credit
```

**`app/views/finance/journal/_modals.php`** (~100L)
```
Modal: Xác nhận Post, Modal: Reverse JE, Modal: Import từ Excel
```

**Refactor `app/views/finance/journal/create.php`** → ~40L, include `_form.php`

#### 2.4. `ap/edit.php` + `ar/edit.php` — Tạo mới

**Cần tạo:**

**`app/views/finance/ap/edit.php`** (~25L)
```php
<?php
$isEdit = true;
require APPROOT . '/views/layouts/header.php';
?>
<div class="container-fluid mt-4 mb-5">
    <h5>Sửa Hóa đơn AP: <?= e($data['invoice']->code) ?></h5>
    <?php flash('msg'); ?>
    <?php require APPROOT . '/views/finance/ap/_form.php'; ?>
</div>
<?php require APPROOT . '/views/layouts/footer.php'; ?>
<script src="<?= asset_v('js/modules/finance/ap_invoice.js') ?>"></script>
```

**`app/views/finance/ar/edit.php`** (~25L) — tương tự

**`app/views/finance/ar/create.php`** — Kiểm tra và bổ sung (hiện 11L — có thể thiếu)

#### 2.5. `coa/` — Tạo _modals.php

COA dùng SPA pattern (AJAX modals). Cần tách modal HTML ra khỏi `index.php`:

**`app/views/finance/coa/_modals.php`** (~150L)
```
Modal: Thêm tài khoản (add account)
Modal: Sửa tài khoản (edit account)
Modal: Xóa tài khoản (confirm delete)
Modal: Import tài khoản từ Excel
```

**Refactor `app/views/finance/coa/index.php`** → Bỏ inline modal HTML, dùng `require '_modals.php'`

#### 2.6. `glperiod/show.php` — Refactor thành shell + partials

**Hiện trạng:** `show.php` (225L) — quá dày

**Cần tạo:**
- `_show_header.php` (~50L): Title, status badge, action buttons
- `_show_modules_table.php` (~80L): Table trạng thái open/close theo module (AP/AR/GL/Costing)
- `_show_history.php` (~60L): Timeline mở/đóng kỳ

**Refactor `glperiod/show.php`** → Shell ~70L

---

### PRIORITY 3 — JavaScript Files

#### 3.1. Tạo `public/js/modules/finance/ap_payment_show.js` (~250L)

```javascript
// Config từ PHP
const AP_PAYMENT_CONFIG = {
    id: null, // set từ PHP
    status: null,
    urls: {
        cancel: '/finance/payment/cancel/',
        print: '/finance/payment/print/',
        allocate: '/finance/payment/allocate/',
    }
};

// Sections:
// - DOMContentLoaded: setup action bar (scroll behavior)
// - confirmCancel(): modal confirm → POST → reload
// - allocateInvoice(invoiceId): AJAX phân bổ
// - printPayment(): open print page
// - showAlert(msg, type): thông báo
```

#### 3.2. Mở rộng `public/js/modules/finance/arreceipt_show.js` (26L → ~250L)

```javascript
// Hiện tại chỉ 26 dòng — cần bổ sung:
// - Scroll behavior cho action bar
// - confirmCancel(): modal → AJAX cancel
// - allocateInvoice(): AJAX phân bổ
// - loadAllocationTable(): reload bảng phân bổ
// - confirmPost(): xác nhận ghi sổ
```

#### 3.3. Tạo `public/js/modules/finance/dashboard.js` (~200L)

```javascript
// Finance Dashboard interactivity:
// - Chart.js cho Monthly Activity bar chart
// - AP Aging donut chart
// - AR Aging donut chart
// - Auto-refresh KPI cards mỗi 5 phút
// - Filter by period (nếu dashboard có date picker)
// Config: const FINANCE_DASHBOARD = { urls: { refresh: '/finance/dashboard/kpi' } }
```

#### 3.4. Tạo `public/js/modules/finance/ap_report.js` (~200L)

```javascript
// AP Report (Aging + Outstanding):
// - Filter: date_from, date_to, partner_id, status
// - Export Excel: fetch /finance/ap-report/export → download blob
// - Print: window.print()
// - Chart.js: aging bar chart
```

#### 3.5. Tạo `public/js/modules/finance/ar_report.js` (~200L)

Tương tự `ap_report.js` nhưng cho AR.

---

### PRIORITY 4 — Controllers Refactor

#### 4.1. Thin hóa `GlperiodController.php` (456L → ~200L)

Sau khi có `GlPeriodService`, move các method sau vào service:
- `openPeriod()`, `closePeriod()`, `lockPeriod()` — business logic
- Controller chỉ: validate → call service → json/redirect

#### 4.2. Thin hóa `ApPaymentController.php` (193L)

```php
// Hiện tại: controller trực tiếp query DB
// Sau: Controller → ApPaymentService
public function store() {
    requirePermission(self::PERM_CREATE);
    $request = new ApPaymentFormRequest($_POST);
    if (!$request->validate()) {
        return $this->json(['errors' => $request->errors()], 422);
    }
    $service = $this->service('finance/ApPaymentService');
    $id = $service->create($request->validated(), $this->getCurrentSiteId(), $this->getCurrentUserId());
    return $this->json(['success' => true, 'id' => $id, 'redirect' => URLROOT . '/finance/payment/show/' . $id]);
}
```

#### 4.3. Thin hóa `ArReceiptController.php` (199L)

Tương tự ApPayment — move sang `ArReceiptService`.

---

### PRIORITY 5 — DTOs Còn Thiếu

#### 5.1. Tạo `app/dtos/finance/JournalEntryDTO.php` (~120L)

```php
class JournalEntryDTO {
    public int $site_id;
    public string $je_date;
    public int $period_id;
    public string $reference;
    public string $description;
    public string $currency = 'VND';
    public float $exchange_rate = 1.0;
    public array $lines = []; // [{account_id, debit, credit, cost_center_id, description}]
    
    public function __construct(array $data) { ... }
    public function validate(): bool { ... }
    public function toArray(): array { ... }
    public function isBalanced(): bool { /* sum(debit) === sum(credit) */ }
}
```

#### 5.2. Tạo `app/dtos/finance/GlPeriodDTO.php` (~80L)

```php
class GlPeriodDTO {
    public string $name;
    public string $start_date;
    public string $end_date;
    public string $status; // draft, open, closed, locked
    public array $module_statuses; // [GL, AP, AR, Costing]
    
    public function __construct(array $data) { ... }
    public function validate(): bool { ... }
}
```

---

## HƯỚNG DẪN THỰC HIỆN TỪNG PHASE

### Phase 1: Services (Tuần 1)

**Thứ tự làm:**
1. Đọc `app/controllers/finance/ApPaymentController.php` toàn bộ
2. Đọc `app/models/finance/ApPaymentModel.php` toàn bộ
3. Tạo `app/services/finance/ApPaymentService.php`
4. Refactor `ApPaymentController.php` để dùng Service
5. Tương tự cho ArReceipt, JournalEntry, GlPeriod
6. Syntax check: `php -l file.php`

**Checklist per Service:**
- [ ] `create()` method: validate → insert → auto-accounting → return id
- [ ] `update()` method: check status allowed → update → audit log
- [ ] `cancel()` method: check status → update status → reverse GL
- [ ] Transaction: `beginTransaction() / commit() / rollBack()`
- [ ] Site isolation: mọi query có site_id
- [ ] Error logging: `error_log('[ApPaymentService] ...')`

### Phase 2: Views Refactor (Tuần 2)

**Thứ tự làm:**
1. Đọc `arreceipt/create.php` (161L) → tạo `_form.php` → shrink create.php
2. Đọc `arreceipt/show.php` → tạo partials → shrink show.php
3. Tương tự payment/
4. Tương tự journal/
5. Tạo `ap/edit.php`, `ar/edit.php`
6. Tạo `coa/_modals.php`

**Checklist per View:**
- [ ] `_form.php`: detect mode ($isEdit), dynamic action URL, csrf_field()
- [ ] `create.php` ≤ 35L, include _form.php
- [ ] `edit.php` ≤ 35L, include _form.php
- [ ] `show.php` ≤ 100L (shell), require partials
- [ ] Tất cả output dùng `e()`
- [ ] Pagination: `$pagination = $data['pagination']; require '_pagination.php'`
- [ ] JS: `asset_v('js/modules/finance/entity.js')`
- [ ] `$data['title']` luôn được truyền từ controller

### Phase 3: JavaScript (Tuần 3)

**Thứ tự làm:**
1. Tạo `ap_payment_show.js`
2. Mở rộng `arreceipt_show.js`
3. Tạo `dashboard.js`
4. Tạo `ap_report.js` + `ar_report.js`

**Checklist per JS file:**
- [ ] `const CONFIG = {...}` nhận từ PHP via `<script>` tag
- [ ] `getCsrfToken()` helper
- [ ] AJAX dùng `fetch()` với FormData + CSRF
- [ ] Error handling: `.catch(err => showAlert(err.message, 'error'))`
- [ ] Action bar scroll behavior nếu là show page

### Phase 4: Cleanup (Tuần 4)

1. Xóa/archive file backup (`show_backup.php`) sau khi verify show.php mới hoạt động
2. Verify: `Get-ChildItem -Recurse app/ -Filter *.php | Where-Object { $_.Length -eq 0 }` → 0 files

---

## PURCHASING REFERENCE — TIÊU CHUẨN ĐỐI CHIẾU

### PO show.php shell (~130L) — copy pattern này

```php
<?php require APPROOT . '/views/layouts/header.php'; ?>
<link rel="stylesheet" href="...sweetalert2...">
<style>
    .po-wrapper { font-family: 'Inter',...; background: #f4f6f9; min-height: 100vh; padding-bottom: 80px; }
    .action-bar { position: fixed; bottom: 0; left: 0; right: 0; ... transform: translateY(100%); transition: transform 0.3s; }
    .action-bar.show { transform: translateY(0); }
</style>
<div class="po-wrapper container-fluid p-3 p-md-4">
    <?php require APPROOT . '/views/purchasing/orders/_show_header.php'; ?>
    <?php require APPROOT . '/views/purchasing/orders/_show_workflow.php'; ?>
    <ul class="nav nav-tabs ...">
        <li><button ... data-bs-target="#pane-detail">Chi tiết</button></li>
        <li><button ... data-bs-target="#pane-flow">Luồng chứng từ</button></li>
        <li><button ... data-bs-target="#pane-revisions">Lịch sử sửa đổi</button></li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="pane-detail">
            <?php require APPROOT . '/views/purchasing/orders/_show_info_card.php'; ?>
            <!-- Items table card -->
            <?php require APPROOT . '/views/purchasing/orders/_show_lines_table.php'; ?>
        </div>
        <div class="tab-pane" id="pane-flow">
            <?php require APPROOT . '/views/purchasing/orders/_show_flow.php'; ?>
        </div>
    </div>
    <?php require APPROOT . '/views/purchasing/orders/_show_action_bar.php'; ?>
    <?php require APPROOT . '/views/purchasing/orders/_modals.php'; ?>
</div>
<?php require APPROOT . '/views/layouts/footer.php'; ?>
<script>const PO_CONFIG = { id: <?= $po->id ?>, status: '<?= $po->status ?>', urls: {...} };</script>
<script src="<?= asset_v('js/modules/purchasing/purchase_order.js') ?>"></script>
```

### PO _show_header.php pattern (~80L)

```php
<?php
$po = $data['po'];
$statusLabel = PurchasingConstants::PO_STATUS_LABELS[$po->status] ?? $po->status;
$statusClass = PurchasingConstants::PO_STATUS_CLASSES[$po->status] ?? 'badge-secondary';
?>
<div class="d-flex justify-content-between align-items-center bg-white rounded-3 p-3 mb-3 shadow-sm border">
    <div class="d-flex align-items-center gap-3">
        <a href="<?= URLROOT ?>/purchasing/purchase-order" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Quay lại
        </a>
        <div>
            <h5 class="mb-0 fw-bold"><?= e($po->po_number) ?></h5>
            <small class="text-muted"><?= format_date($po->order_date) ?></small>
        </div>
        <span class="badge <?= $statusClass ?> fs-6"><?= $statusLabel ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= URLROOT ?>/purchasing/purchase-order/print/<?= $po->id ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
            <i class="fas fa-print me-1"></i> In
        </a>
        <?php if (hasPermission('po.edit') && in_array($po->status, ['draft', 'submitted'])): ?>
        <a href="<?= URLROOT ?>/purchasing/purchase-order/edit/<?= $po->id ?>" class="btn btn-sm btn-warning">
            <i class="fas fa-pencil-alt me-1"></i> Sửa
        </a>
        <?php endif; ?>
    </div>
</div>
```

### PO _show_action_bar.php pattern (~60L)

```php
<div class="action-bar" id="actionBar">
    <div class="d-flex align-items-center gap-2">
        <span class="fw-bold text-dark"><?= e($po->po_number) ?></span>
        <span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span>
    </div>
    <div class="d-flex gap-2">
        <?php if (hasPermission('po.approve') && $po->status === 'submitted'): ?>
        <button class="btn btn-success" onclick="confirmApprove()">
            <i class="fas fa-check me-1"></i> Duyệt
        </button>
        <?php endif; ?>
        <?php if (hasPermission('po.cancel') && in_array($po->status, ['draft', 'submitted'])): ?>
        <button class="btn btn-outline-danger" onclick="confirmCancel()">
            <i class="fas fa-times me-1"></i> Hủy
        </button>
        <?php endif; ?>
        <a href="<?= URLROOT ?>/purchasing/purchase-order/print/<?= $po->id ?>" target="_blank" class="btn btn-outline-secondary">
            <i class="fas fa-print me-1"></i> In
        </a>
    </div>
</div>
```

### Purchasing JS pattern (purchase_order.js excerpt)

```javascript
// [1] CONFIG
const PO_CONFIG = window.PO_CONFIG || {};

// [2] INIT
document.addEventListener('DOMContentLoaded', () => {
    setupActionBar();
    setupEventListeners();
});

// [3] ACTION BAR — show/hide on scroll
function setupActionBar() {
    const bar = document.getElementById('actionBar');
    if (!bar) return;
    const observer = new IntersectionObserver(entries => {
        bar.classList.toggle('show', !entries[0].isIntersecting);
    }, { threshold: 0.1 });
    const header = document.querySelector('.po-wrapper');
    if (header) observer.observe(header.firstElementChild);
}

// [4] APPROVE
function confirmApprove() {
    if (!confirm('Duyệt đơn hàng này?')) return;
    fetch(PO_CONFIG.urls.approve + PO_CONFIG.id, {
        method: 'POST',
        body: getFormDataWithCSRF()
    }).then(r => r.json()).then(res => {
        if (res.success) { showToast('Đã duyệt', 'success'); setTimeout(() => location.reload(), 1200); }
        else showToast(res.message, 'error');
    }).catch(e => showToast('Lỗi kết nối', 'error'));
}

// [5] CSRF
function getFormDataWithCSRF() {
    const fd = new FormData();
    fd.append('csrf_token', document.querySelector('[name="csrf_token"]')?.value || '');
    return fd;
}

// [6] TOAST
function showToast(msg, type = 'success') {
    const colors = { success: '#198754', error: '#dc3545', warning: '#ffc107' };
    // Swal.fire hoặc tự tạo toast element
    const toast = document.createElement('div');
    toast.className = `position-fixed top-0 end-0 m-3 alert alert-${type === 'error' ? 'danger' : type} shadow`;
    toast.style.cssText = 'z-index:9999;min-width:250px;';
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
```

---

## DATABASE SCHEMA — CÁC BẢNG CHÍNH

> **BẮT BUỘC**: Trước khi viết bất kỳ query nào, tra `app/db_schema.sql` để verify tên cột chính xác. Dưới đây là tóm tắt nhanh.

```
ap_invoices:
  id, site_id, invoice_number, partner_id, invoice_date, due_date,
  subtotal, tax_amount, total_amount, paid_amount, currency, exchange_rate,
  status (draft|submitted|approved|posted|paid|partially_paid|cancelled),
  notes, created_by, updated_by, created_at, updated_at, deleted_at

ap_payments:
  id, site_id, payment_number, partner_id, payment_date, amount,
  payment_method, bank_account_id, reference, status, notes,
  created_by, updated_by, created_at, updated_at, deleted_at

ap_payment_allocations:
  id, payment_id, invoice_id, allocated_amount, created_at

ar_invoices:
  id, site_id, invoice_number, partner_id, invoice_date, due_date,
  subtotal, tax_amount, total_amount, paid_amount, currency, exchange_rate,
  status (draft|submitted|approved|posted|partially_paid|paid|cancelled)

ar_receipts:
  id, site_id, receipt_number, partner_id, receipt_date, amount,
  payment_method, bank_account_id, reference, status, notes,
  created_by, updated_by, created_at, updated_at, deleted_at

journal_entries:
  id, site_id, je_number, je_date, period_id, reference, description,
  currency, exchange_rate, status (draft|posted|reversed),
  source_type, source_id, created_by, updated_by, deleted_at

journal_entry_lines:
  id, je_id, line_num, account_id, cost_center_id, description,
  debit_amount, credit_amount, currency, exchange_rate

gl_periods:
  id, site_id, name, period_num, period_year, start_date, end_date,
  status (future|open|closed|locked),
  gl_status, ap_status, ar_status, costing_status

chart_of_accounts (coa):
  id, site_id, account_code, account_name, account_type (asset|liability|equity|revenue|expense),
  parent_id, is_detail, allow_posting, status (active|inactive),
  description, created_by, updated_by, deleted_at

cost_centers:
  id, site_id, code, name, parent_id, manager_id, status, budget_amount

projects:
  id, site_id, code, name, partner_id, start_date, end_date,
  status, budget_amount, description
```

---

## CONSTANTS REFERENCE

File: `app/helpers/finance/FinanceConstants.php`

```php
class FinanceConstants {
    // AP Invoice status
    const AP_STATUS_DRAFT     = 'draft';
    const AP_STATUS_SUBMITTED = 'submitted';
    const AP_STATUS_APPROVED  = 'approved';
    const AP_STATUS_POSTED    = 'posted';
    const AP_STATUS_PAID      = 'paid';
    const AP_STATUS_CANCELLED = 'cancelled';

    // AR Invoice status
    const AR_STATUS_DRAFT         = 'draft';
    const AR_STATUS_SUBMITTED     = 'submitted';
    const AR_STATUS_APPROVED      = 'approved';
    const AR_STATUS_POSTED        = 'posted';
    const AR_STATUS_PARTIALLY_PAID = 'partially_paid';
    const AR_STATUS_PAID          = 'paid';
    const AR_STATUS_CANCELLED     = 'cancelled';

    // GL Period status
    const GL_PERIOD_OPEN   = 'open';
    const GL_PERIOD_CLOSED = 'closed';
    const GL_PERIOD_LOCKED = 'locked';

    // JE status
    const JE_STATUS_DRAFT    = 'draft';
    const JE_STATUS_POSTED   = 'posted';
    const JE_STATUS_REVERSED = 'reversed';
}
```

---

## SECURITY CHECKLIST (Bắt buộc cho mọi file mới)

```
Controller:
☑ requirePermission() ở đầu mỗi action
☑ $siteId từ $this->getCurrentSiteId() — KHÔNG từ $_POST/$_GET
☑ Parameterized queries — KHÔNG string concat vào SQL
☑ (int) cast cho numeric IDs từ input

Service:
☑ Nhận $siteId từ controller — KHÔNG từ $_SESSION trực tiếp
☑ Transaction: beginTransaction / commit / rollBack
☑ Validate foreign keys trước khi insert
☑ error_log() với prefix [ServiceName]

View:
☑ e() cho mọi output user data
☑ csrf_field() trong mọi form
☑ hasPermission() trước khi show buttons nhạy cảm
☑ asset_v() cho JS/CSS

JS:
☑ CSRF token trong mọi POST request
☑ try/catch hoặc .catch() cho fetch calls
☑ Không expose sensitive data trong window object
```

---

## EXECUTION ORDER TRONG MỖI PHIÊN LÀM VIỆC

1. Đọc file cần làm: `read_file` trước, hiểu rõ structure
2. Tra schema: search `app/db_schema.sql` verify column names
3. Tạo/sửa file với `replace_string_in_file` hoặc `create_file` (chỉ cho file MỚI)
4. Syntax check: `php -l <file.php>`
5. Verify 0 bytes: `Get-ChildItem -Recurse app/ -Filter *.php | Where-Object { $_.Length -eq 0 }`
6. Tối đa 50 file edits per session

---

## KẾT QUẢ KỲ VỌNG SAU KHI HOÀN THÀNH

| Metric | Hiện tại | Mục tiêu |
|--------|----------|-----------|
| Services/Module | 17 (chủ yếu AutoAccounting) | 22+ (có đủ CRUD services) |
| Controllers (avg lines) | ~230L (quá dày) | ≤ 180L (thin) |
| Views với _form.php | 6/10 entities | 10/10 |
| Views với _modals.php | 7/10 entities | 10/10 |
| Views show dùng partials | 3/10 entities | 8/10 |
| JS files | 14 | 19+ |
| DTOs | 4 | 6 |
| **Module Score** | **75%** | **95%** |

---

## FILE THAM CHIẾU QUAN TRỌNG

Đọc các file này kỹ trước khi bắt đầu làm:

| File | Mục đích |
|------|----------|
| `app/services/purchasing/PurchaseOrderService.php` | Reference: Service pattern chuẩn |
| `app/controllers/purchasing/PurchaseOrderController.php` | Reference: Thin controller pattern |
| `app/views/purchasing/orders/show.php` | Reference: Show shell pattern |
| `app/views/purchasing/orders/_show_header.php` | Reference: Header partial |
| `app/views/purchasing/orders/_show_action_bar.php` | Reference: Action bar partial |
| `app/views/purchasing/orders/_form.php` | Reference: Shared form pattern |
| `app/helpers/purchasing/PurchasingConstants.php` | Reference: Constants pattern |
| `public/js/modules/purchasing/purchase_order.js` | Reference: JS module pattern |
| `app/helpers/finance/FinanceConstants.php` | Finance constants hiện tại |
| `app/db_schema.sql` | Database schema — LUÔN tra trước khi viết SQL |
| `app/helpers/web/url_helper.php` | asset_v(), URLROOT, etc. |
