# Factory ERP — Module Completion Roadmap
> Ngày tạo: 01/04/2026 | Cập nhật: Session 39 (2026-04-21) — PDO placeholder reuse audit (16 files) + AR dashboard schema fix (ar_invoices.due_date→invoice_date); Session 38 Site isolation audit (95 queries / 45 files) | Chuẩn tham chiếu: Inventory & Purchasing
> Mục tiêu: Đưa tất cả modules lên cùng chuẩn chất lượng

---

## I. ĐỊNH NGHĨA CHUẨN MODULE (Oracle Engineer Standard)

Lấy từ phân tích thực tế của **Purchasing** và **Inventory** — 2 modules hoàn thiện nhất hệ thống.

### Điểm số chuẩn (Purchasing = 100%)
| Hạng mục | Purchasing | Inventory | Benchmark |
|----------|-----------|-----------|-----------|
| Mobile views | ✅ 4 | ✅ 4 (+PDA 17) | ≥ 1 |
| Import wizard | ✅ 2 | ✅ 1 (Opening) | ≥ 1 |
| Print views | ✅ 2 | ✅ 7 | ≥ 1 |
| `_show_*.php` partials | ✅ 21 | ✅ 83 (14 entities) | ≥ 8 |
| WorkflowService | ✅ 1 | ❌ 0 | ≥ 1 |
| EmailService | ✅ 2 | ❌ 0 | ≥ 1 |
| ExportService (Excel) | ✅ 2 | ✅ 8 | ≥ 1 |
| Dashboard views | ✅ 3 | ✅ 1 | ≥ 1 |
| JS show file | ✅ 1 | ✅ 4 | ≥ 1 |

---

## II. CHECKLIST ĐẦY ĐỦ CHO MỖI MODULE

### A. Controller
- [ ] `private const PERM_VIEW`, `PERM_CREATE`, `PERM_EDIT`, `PERM_DELETE`, `PERM_APPROVE`
- [ ] `index()` — DAC 3 lớp: `view_all` → `dept filter` → `own-only`
- [ ] `index()` — Statuses từ `SysLookup` (KHÔNG hardcode)
- [ ] `index()` → responsive table, filter bar, pagination
- [ ] `show()` — mobile routing via `$this->isMobileDevice()`
- [ ] `show()` — load: approval_logs, attachments, document flow (batch, tránh N+1)
- [ ] `create()` — pre-validate config trước khi render form
- [ ] `store()` / `update()` → CSRF + validate → Service → JSON response
- [ ] Workflow actions: `submit()`, `approve()`, `reject()`, `recall()`, `close()`/`cancel()`
- [ ] AJAX helpers: `searchProduct()`, `getSuppliers()`, `getChildren()`
- [ ] AJAX đều: CSRF validate → `Content-Type: application/json` → `exit`
- [ ] `printView($id)` — print-friendly layout
- [ ] `import()` / `importPreview()` / `importConfirm()` — Excel wizard
- [ ] `export()` — Excel export via Service

### B. Model
- [ ] Status constants: `const STATUS_DRAFT`, `STATUS_PENDING`, `STATUS_APPROVED`, `STATUS_CLOSED`, `STATUS_CANCELLED`
- [ ] `getStatusLabels(): array` — static, feeds badge rendering
- [ ] `getEditableStatuses(): array`
- [ ] `getDeletableStatuses(): array`
- [ ] `lockForUpdate($id, $siteId)` — race-condition guard
- [ ] Atomic UPDATE with WHERE guard — `UPDATE ... SET qty = qty + :delta WHERE qty = :old_qty` (optimistic lock)
- [ ] **KHÔNG DELETE rồi INSERT** — upsert phải check FK trước khi xóa (bảo toàn audit trail + FK)
- [ ] `getHistory($id)` — approval log
- [ ] `canUserApprove($docId, $userId)` — delegates to WorkflowEngine
- [ ] `getAllAttachments($docId, $siteId)` — header + line level

### C. Services
- [ ] `{Entity}Service.php` — createFromDTO, updateFromDTO, cache invalidation
- [ ] `{Entity}WorkflowService.php` — submit/approve/reject/recall + race-condition lock
- [ ] `{Entity}ImportService.php` — PHPSpreadsheet Excel import
- [ ] `{Entity}ExportService.php` — PHPSpreadsheet Excel export
- [ ] `{Entity}EmailService.php` — notify on workflow transitions
- [ ] `DocumentFlowService` integration (cross-doc traceability)
- [ ] `AttachmentService` — upload/delete shared

### D. Views — Short list
- [ ] **Browser tab title**: Mọi `$this->view()` phải truyền `'title' => 'Tiêu đề trang'` (BẮT BUỘC — key phải là `title`, KHÔNG dùng `page_title`)
- [ ] `index.php` — Bootstrap table, filter bar, badges từ DB lookup
- [ ] `index_mobile.php` — card-based mobile list
- [ ] `_form.php` — create + edit unified (detect `$isEdit`)
- [ ] `create.php` / `edit.php` — shell (~70 dòng), include `_form.php`
- [ ] `show.php` — shell (~80 dòng), include `_show_*.php` partials
- [ ] `show_mobile.php` — mobile-optimized detail
- [ ] `_show_header.php` — code + status badge + action buttons
- [ ] `_show_workflow.php` — step stepper + mobile timeline
- [ ] `_show_info_card.php` — metadata card
- [ ] `_show_items_table.php` — read-only line items
- [ ] `_show_history.php` — approval timeline
- [ ] `_show_attachments.php` — file list
- [ ] `_show_action_bar.php` — sticky footer, context-sensitive buttons
- [ ] `_show_flow.php` — document chain traceability (tabs + count badges)
- [ ] `print.php` — print-safe layout (no nav)
- [ ] `import.php` — 3-step wizard (select → preview → confirm)
- [ ] `{entity}_dashboard.php` — KPI summary
- [ ] `_modals.php` — tất cả modals tập trung

### E. JavaScript
- [ ] `{entity}.js` — CONFIG object + row management + AJAX + SweetAlert2
- [ ] `{entity}_show.js` — workflow AJAX + confirm dialogs (không inline `<script>`)

### F. Oracle Process Flow
- [ ] Document numbering: auto-sequence từ `document_sequences` table
- [ ] Approval chain: multi-level configurable qua `WorkflowEngine`
- [ ] Audit trail: `sys_audit_logs` tự động (BaseModel `$useAuditLog = true`)
- [ ] GL auto-entries: `AutoAccounting` service khi approve/post
- [ ] Status history: bảng `*_approval_logs` ghi mỗi transition
- [ ] Period-lock check: `$this->checkLockedDate($docDate)` trước khi post
- [ ] Cross-module document flow visible trên mỗi document

---

## III. ĐÁNH GIÁ TỪNG MODULE

### 📊 Bảng điểm tổng quan

| Module | Mobile | Import | Print | Show Partials | Workflow Svc | Email Svc | Export Svc | Dashboard | JS Show | **Score** |
|--------|--------|--------|-------|--------------|-------------|----------|-----------|-----------|---------|-----------|
| **[CHUẨN] Purchasing** | ✅ 4+7 | ✅ 2 | ✅ 2 | ✅ 23 files (PO 14 + PR 9) | ✅ 2 (PO+PR) | ✅ 2 | ✅ 4 (PO+PR+Detail) | ✅ 3 | ✅ 2 | **100%** |
| **[CHUẨN] Inventory** | ✅ 4+PDA | ✅ 1 | ✅ 7 | ✅ 83 files (14 entities) | ⚠️ N/A¹ | ❌ | ✅ 8 | ✅ | ✅ 4 | **95%** |
| **Sales ✅** | ✅ 4 | ✅ 3 (SO+SQ+FC) | ✅ 2 | ✅ 16 files | ✅ SO+SQ | ✅ 2 | ✅ 4 (SO+SQ+FC+SoDetail) | ✅ | ✅ 4 | **100% + Forecast/Credit/RMA/Commission** |
| **Production ✅** | ✅ 4 | ✅ 1 | ✅ WO+BOM | ✅ WO+BOM+Plan 15 | ✅ | ✅ 2 | ✅ 4 | ✅ | ✅ 2 | **100%** |
| **Finance ✅** | ✅ 4 | ✅ JE | ✅ AP+AR | ✅ 21 files | ✅ AP+AR | ✅ | ✅ AP+AR+JE | ✅ | ✅ 4 | **100%** |
| **HR ✅** | ✅ 7 | ✅ | ✅ 7 | ✅ Emp+Leave+Contract+Perf+Attend+Payroll | ✅ 2 | ✅ | ✅ 6 | ✅ | ✅ 2 | **100%** |
| Quality 🔄 | ✅ 4 | ✅ 1 | ✅ 2 | ✅ Insp 6 + Spec 3 | ✅ | ✅ | ✅ 2 | ✅ | ✅ 2 | **100%** |
| Asset | ❌ | ⚠️ | ❌ | ✅ 9(5-tab+ops) | ✅ Maint | ✅ 2 | ❌ | ✅ | ✅ | **75%** |
| PM | ❌ | ❌ | ❌ | ✅ 14 | ✅ 2 | ✅ 2 | ❌ | ✅ | ❌ | **55%** |
| **Master Data** | N/A | ✅ 2 | ✅ 2 | ✅ 18 (Products 11 + Partners 7) | N/A | N/A | ✅ 2 | N/A | ✅ 3 | **95%** |

> **¹ Ghi chú Inventory**: Module giao dịch kho (transactional), mỗi Service tự xử lý workflow (approve/cancel/reverse) — không cần formal WorkflowService như document-approval modules. EmailService là gap thực tế duy nhất còn lại. Code quality đạt chuẩn cao nhất hệ thống (163 findings audited, 142 fixed, zero hardcodes).
> **Ghi chú Master Data**: Module tham chiếu (CRUD reference data), không áp dụng Mobile/Workflow/Email/Dashboard.
> Scoring chỉ tính: Import, Print, Show Partials, Export, JS Show (5 hạng mục applicable).
> Session 18: Products đạt 100% (print, 12 granular permissions, controller cleanup → service).

---

## IV. ROADMAP CHI TIẾT THEO MODULE

---

### ✅ MODULE: SALES (Score: 88% → 100% — Session 9 final)

**Hoàn thành (Session 8):**
- ✅ `app/views/sales/salesorder/index_mobile.php` (card-based mobile SO list, blue gradient, client-side filter)
- ✅ `app/views/sales/salesquote/index_mobile.php` (card-based mobile SQ list, purple gradient, client-side filter)
- ✅ `SalesOrderController.php` updated: mobile routing via `isMobileDevice()` in index()
- ✅ `SalesQuoteController.php` updated: mobile routing via `isMobileDevice()` in index()

**Hoàn thành (01/04/2026):**
- ✅ SO + SQ show partials: 8 files mỗi module (16 tổng)
- ✅ `salesorder_show.js` + `salesquote_show.js` (workflow AJAX, modals, amountInWords)
- ✅ `SalesOrderExportService.php` (exportList + exportDetail, PhpSpreadsheet)
- ✅ `SalesDashboardController.php` + `dashboard/index.php` (KPI: revenue, SO, backorders, top customers)
- ✅ `AttributeDisplayHelper.php` v1.1.0 (sort order fix, matrix rendering, unit display)
- ✅ `ProductAttributeSet.php` LEFT JOIN uom_units (unit_symbol)
- ✅ Bug fix: SQ `_show_items_table.php` margin footer class

**Còn lại (đợt 2):**
- ✅ AR Invoice module hoàn chỉnh (session 5 — controller + model + views + JS + permissions + menu)
    - ✅ `SalesQuoteExportService.php` (session 6 — exportList + exportDetail, PhpSpreadsheet, 14 cols)

**Oracle Process Gaps:**
- [x] AR Invoice flow: SO → Shipment → AR Invoice → AR Payment ✅
- [x] **Customer credit limit check khi tạo SO** ✅ (Phase 3 — 2026-04-23)
- [ ] Revenue recognition (khi nào ghi nhận doanh thu GL)
- [x] **Return Merchandise Authorization (RMA) workflow** ✅ (Phase 4 — 2026-04-23)
- [ ] Sales backorder report cần hoàn thiện

---

#### ✅ SALES PHASE 2 — Forecast Module (Session 39 — 2026-04-23)
**Mục tiêu:** Dự báo doanh số theo kỳ + tracking consumption từ SO actual.

**Files (~22):**
- `app/migrations/2026_04_23_sales_forecast.sql` — `sales_forecasts` + `sales_forecast_details` (FK products/users/uoms)
- `app/models/sales/SalesForecastModel.php` — paginate + auto-calc actual_qty từ SO completed
- `app/services/sales/SalesForecastService.php` — CRUD + workflow (draft→confirmed→archived)
- `app/services/sales/SalesForecastImportService.php` — Excel batch import (matrix layout: rows=product, cols=period)
- `app/services/sales/SalesForecastExportService.php` — Excel export
- `app/controllers/sales/SalesForecastController.php` — index/create/edit/show/store/update/import/export/confirm/archive
- `app/views/sales/salesforecast/{index,create,edit,show,import,_form}.php`
- `public/js/modules/sales/sales_forecast.js` + `sales_forecast_show.js`
- Config: 8 perms (`sales.forecast.{view/create/edit/delete/confirm/archive/import/export}`), lookups (FORECAST_STATUS, FORECAST_PERIOD_TYPE, FORECAST_METHOD), sequence FC, menu entry, feature flag SALES_FORECAST

**Workflow:** `draft → confirmed → archived` (transitionStatus race-safe).

#### ✅ SALES PHASE 3 — Customer Credit Mgmt (Session 39 — 2026-04-23)
**Mục tiêu:** Hạn mức công nợ + Credit Hold; check tự động khi submit SO.

**Files (4 + 3 edits):**
- `app/services/sales/CustomerCreditService.php` — `getPartnerCredit()` (formula: limit − open AR − pending SO × ER), `assertCanCreateOrder()` (throw BusinessException), `getCreditDashboard()`, `classifyStatus()` (OK/WARNING/CRITICAL/EXCEEDED/HOLD/NO_LIMIT)
- `app/controllers/sales/CustomerCreditController.php` — index/show/update/toggleHold
- `app/views/sales/customercredit/index.php` — KPI dashboard + filter + edit modal
- `public/js/modules/sales/customer_credit.js`
- **Tích hợp:** `SalesOrderWorkflowService::submitForApproval()` injected `checkCustomerCredit($soId)` — fail-open trên system error
- **Schema:** Tận dụng `partner_site_assignments.credit_limit_override` + `credit_hold` (đã có sẵn + index `idx_perf_psa_check`)
- Config: 2 perms (`sales.credit.{view/manage}`), menu entry

#### ✅ SALES PHASE 4 — Sales Returns / RMA (Session 39 — 2026-04-23)
**Mục tiêu:** Phiếu trả hàng từ KH; quy trình duyệt → nhận hàng → phát hành Credit Note.

**Files (8):**
- `app/migrations/2026_04_23_sales_phase_3_5.sql` — `sales_returns` (FK partners/sales_orders/warehouses/ar_invoices) + `sales_return_details` (CASCADE delete)
- `app/models/sales/SalesReturnModel.php` — `paginateList`, `findWithRelations`, `getDetails` (JOIN products/uoms/sales_order_details)
- `app/services/sales/SalesReturnService.php` — `createReturn` (DocumentSequenceService 'SR'), `transitionStatus` race-safe, workflow methods
- `app/controllers/sales/SalesReturnController.php` — CRUD + `submit/approve/reject/receive/credit/cancel`
- `app/views/sales/salesreturn/{index,create,show}.php`
- `public/js/modules/sales/sales_return.js`
- Config: 7 perms (`sales.return.{view/create/edit/delete/approve/receive/credit}`), lookups (SALES_RETURN_STATUS/REASON/DISPOSITION), sequence SR, 2-level menu

**Workflow:** `draft → pending → approved → received → credited` (or cancelled/rejected anytime).

#### ✅ SALES PHASE 5 MVP — Commission (Session 39 — 2026-04-23)
**Mục tiêu:** Quy tắc tính hoa hồng theo NV/KH/nhóm SP × kỳ; tự động tính từ SO completed.

**Files (6):**
- `sales_commission_rules` + `sales_commissions` (FK users/product_categories/partners/sales_orders)
- `SalesCommissionRuleModel.php` + `SalesCommissionModel.php`
- `SalesCommissionService.php` — `calculateForPeriod($from, $to, $userId)` matches first rule by priority, supports REVENUE/MARGIN/QUANTITY basis; `lockRecord()`, `markPaid()`
- `SalesCommissionController.php` — index/rules/storeRule/deleteRule/calculate/lock/markPaid
- `app/views/sales/salescommission/{index,rules}.php`
- Config: 3 perms (`sales.commission.{view/manage/calc}`), lookups (BASIS/STATUS), sequence SCM, 2-level menu

**Workflow:** `draft → locked → paid` (cancelled anytime).

**🔵 Deferred (Phase 5 Full — Future):** Sales Mobile PWA, Promotion Engine, Commission UI tích hợp dashboard chính.

**🚀 Migration đã apply (2026-04-23):** 4 tables (`sales_returns`, `sales_return_details`, `sales_commission_rules`, `sales_commissions`). 20 permissions sync DB và đã grant cho roles Administrator (1) / Sale Manager (5) / Sale (4). **User cần logout & login lại** để session refresh permissions.

**Việc cần làm - theo thứ tự ưu tiên:**

#### ~~SALES P1: Refactor show.php → partials~~ ✅ DONE
```
app/views/sales/salesorder/
├── show.php                    → Refactor: shell 80 dòng
├── _show_header.php            → TẠO MỚI: SO number + status badge + actions
├── _show_workflow.php          → TẠO MỚI: Draft→Confirmed→Shipped→Invoiced→Closed
├── _show_info_card.php         → TẠO MỚI: Customer, delivery address, terms
├── _show_items_table.php       → TẠO MỚI: Line items with qty ordered/shipped/invoiced
├── _show_history.php           → TẠO MỚI: Approval + status change log
├── _show_attachments.php       → TẠO MỚI: File list
├── _show_action_bar.php        → TẠO MỚI: Confirm/Cancel/Invoice/Close buttons
└── _show_flow.php              → TẠO MỚI: SO → Shipments → AR Invoice chain

app/views/sales/salesquote/
├── (same pattern ↑)
```

#### ~~SALES P2: Tạo ExportService~~ ✅ DONE (SalesOrderExportService)
```php
// app/services/sales/SalesOrderExportService.php
// app/services/sales/SalesQuoteExportService.php
// Export: SO detail listing (Excel), SO summary by customer/period
```

#### ~~SALES P3: Sales Dashboard~~ ✅ DONE
```
app/views/sales/dashboard/
└── index.php                   → KPIs: Revenue MTD/YTD, Open SO value, Backorder %, Top customers
```

#### ~~SALES P4: AR Invoice Module~~ ✅ DONE (session 5)
```php
// app/controllers/finance/ArInvoiceController.php  ✅ CREATED — 10 methods (index/show/create/store/submit/recall/approve/reject/post/void)
// app/models/finance/ArInvoiceModel.php             ✅ CREATED — 6 methods, status constants
// GL entries: Debit AR / Credit Revenue + Tax Payable (post() calls checkLockedDate)
```

#### ~~SALES P5: JS Show file~~ ✅ DONE
```
public/js/modules/sales/salesorder_show.js
public/js/modules/sales/salesquote_show.js
```

**Hoàn thành (Session 37 — GoLive Phase 3: Soft-Delete + Race-Condition Hardening):**
- ✅ **15 `deleted_at IS NULL` fixes** — thêm vào JOINs thiếu filter soft-deleted records:
  - `SalesOrderModel.php` (6 fixes): getById, getDetails, getShipmentsByOrder, getStatusHistory, getDocumentFlow, createDirectOrder
  - `SalesQuoteModel.php` (4 fixes): getById, getDetails, getStatusHistory, getDocumentFlow
  - `SalesOrderService.php` (2 fixes): processLine availability check, getAvailableStock
  - `SalesQuoteService.php` (1 fix): calculateQuoteTotal
  - `SalesDashboardHelper.php` (1 fix): getPendingSalesOrders
  - `SalesQuantityHelper.php` (1 fix): getShippedQuantity
- ✅ **12 FOR UPDATE + re-check locks** trên workflow methods (approve/reject/recall/cancel/submit/close):
  - `SalesOrderModel.php`: `lockForUpdate()` helper + 6 workflow methods
  - `SalesQuoteWorkflowService.php`: 4 workflow methods (submit/approve/reject/recall)
  - `SalesQuoteService.php`: 2 workflow methods (cancel/convertToSalesOrder)
- ✅ Chi tiết: `.github/oracle-erp/modules/sales-golive-audit.md` § Phase 3
- ✅ All 8 files PHP lint validated — zero errors

---

---

### ✅ MODULE: FINANCE (Score: 83% → 100% — Session 9 final, Extended Session 10)

**Hoàn thành (Session 10 — Print Views + Dashboard KPIs):**
- ✅ `app/views/finance/payment/print.php` (Phiếu Chi / Ủy Nhiệm Chi print view — standalone HTML, signature blocks, số tiền bằng chữ via JS, @media print)
- ✅ `app/controllers/finance/ApPaymentController.php` — thêm `print($id)` method
- ✅ `app/views/finance/payment/_show_toolbar.php` — "In Phiếu" button → dedicated print page (không còn dùng window.print())
- ✅ `app/views/finance/journal/print.php` (Phiếu Kế Toán / Bút Toán print view — balance check, debit/credit table, signature blocks)
- ✅ `app/controllers/finance/JournalEntryController.php` — thêm `print($id)` method
- ✅ `app/views/finance/payment/_modals.php` (Extract void modal → shared _modals.php, Oracle standard pattern)
- ✅ `app/views/finance/payment/show.php` — refactor to `require _modals.php` thay inline modal
- ✅ `app/helpers/finance/FinanceDashboardHelper.php` — thêm 4 KPIs: `getApSummary()`, `getArSummary()`, `getPaymentMonthSummary()`, `getRecentGlPeriods()` (tổng: 11 KPI methods, 236 dòng)

**Hoàn thành (Session 9):**
- ✅ `ArInvoiceWorkflowService.php` (submit/approve/reject/recall/void with lock + transaction + audit)
- ✅ `ArInvoiceExportService.php` (exportList + exportDetail, PhpSpreadsheet, 2-sheet detail)
- ✅ `app/views/finance/ap/index_mobile.php` (card-based mobile AP list, red gradient)
- ✅ `app/views/finance/ar/index_mobile.php` (card-based mobile AR list, green gradient)
- ✅ `app/views/finance/ar/show_mobile.php` (mobile AR detail with workflow actions)
- ✅ `ApInvoiceController.php` updated: mobile routing via `isMobileDevice()` in index() + show()
- ✅ `ArInvoiceController.php` updated: mobile routing via `isMobileDevice()` in index() + show()

**Hoàn thành (Session 8):**
- ✅ `ApInvoiceWorkflowService.php` (submit/approve/reject/recall/void with lock + transaction + audit)
- ✅ `FinanceEmailService.php` (11 email render methods for AP/AR/Payment workflows)
- ✅ `ApInvoiceExportService.php` (exportList + exportDetail, PhpSpreadsheet)
- ✅ `JournalEntryExportService.php` (exportList + exportDetail, multi-sheet with debit/credit totals)
- ✅ `JournalEntryImportService.php` (parseExcelForPreview + saveImport + generateTemplate)
- ✅ `app/views/finance/journal/import.php` (3-step import wizard: template → upload/preview → save)
- ✅ `app/views/finance/ap/show_mobile.php` (mobile AP invoice detail with workflow actions)
- ✅ `app/views/finance/ap/_show_history.php` (approval timeline with color-coded actions)
- ✅ `app/views/finance/ap/_show_attachments.php` (file attachment list with download/preview)
- ✅ `app/views/finance/ar/_show_history.php` (approval timeline for AR invoices)
- ✅ `app/views/finance/ar/_show_attachments.php` (file attachment list for AR invoices)

**Hoàn thành (session 5 — AR Invoice module):**
- ✅ `ArInvoiceModel.php` (status constants, getInvoices/getById/getDetails/getTotals/getPaidAmount)
- ✅ `ArInvoiceController.php` (10 methods: index/show/create/store/submit/recall/approve/reject/post/void)
- ✅ `app/views/finance/ar/index.php` (sticky table, filter bar, status badges, paid/remaining cols)
- ✅ `app/views/finance/ar/show.php` (shell ~80 dòng, toolbar + workflow + content panel)
- ✅ `app/views/finance/ar/_show_workflow.php` (4-step stepper: Tạo HĐ → Chờ duyệt → Đã duyệt → Ghi sổ)
- ✅ `app/views/finance/ar/_show_action_bar.php` (modes: submit/approve/post/collect)
- ✅ `app/views/finance/ar/_show_lines_table.php` (line items + tfoot subtotal/tax/discount/grand)
- ✅ `app/views/finance/ar/_show_right_panel.php` (customer + payment status + SO/GL links)
- ✅ `app/views/finance/ar/_form.php` + `create.php` (dynamic line rows + recalc)
- ✅ `public/js/modules/finance/ar_invoice_show.js` (workflow AJAX: submit/recall/approve/reject/post/void)
- ✅ `public/js/modules/finance/ar_invoice_form.js` (add/remove lines, onProductChange, recalcAllArLines)
- ✅ `app/config/permissions_list.php` (4 AR perms: view/create/approve/post)
- ✅ `app/config/menu_structure.php` (AR Invoice menu uncommented, correct permission)

**Hoàn thành (session 4):**
- ✅ AP Invoice `_show_workflow.php` (4-step stepper: Tạo HĐ → Chờ duyệt → Đã duyệt → Ghi sổ)
- ✅ AP Invoice `_show_action_bar.php` (modes: submit/approve/post/pay)
- ✅ `ap_invoice_show.js` (confirmSubmit/Recall/Reject/Approve/Post/Void với fetch API)
- ✅ AP Invoice `show.php` updated: includes workflow + action bar + `AP_INVOICE_CFG` config
- ✅ `TrialBalanceController.php` (index + export + `_fetchTrialBalance()` private SQL)
- ✅ `trial_balance.php` (filter bar + 3 KPI cards + full table + tfoot + print CSS)

**Hoàn thành (sessions 1-3):**
- ✅ JE show.php refactored → shell + 5 partials
- ✅ `_show_header.php`, `_show_info_card.php`, `_show_lines_table.php`, `_show_source_doc.php`, `_show_action_bar.php`
- ✅ `journalentry_show.js` (confirmPostJE, confirmDeleteJE với SweetAlert2)
- ✅ `FinanceDashboardController.php` (7 queries: draftCount, AP aging 5-bucket, top vendors, 6M chart)
- ✅ `finance/dashboard/index.php` (KPI cards, AP aging bars, Chart.js, recent JE, GL period status)
- ✅ AP Invoice `show.php` refactored (27KB → 8KB): 3 partials (`_show_toolbar.php`, `_show_lines_table.php`, `_show_right_panel.php`)
- ✅ AP Payment `show.php` refactored (14KB → 6KB): 3 partials (`_show_toolbar.php`, `_show_allocations_table.php`, `_show_right_panel.php`)
- ✅ Inline DOCSO (amount-in-words VN) + tab-scroll JS retained in shells

**Việc cần làm:**

#### ~~FIN P1: Refactor JE show.php → partials~~ ✅ DONE
```
app/views/finance/journal/
├── show.php                    ✅ Shell ~90 dòng
├── _show_header.php            ✅ JE number + period + status (Draft/Posted) + buttons
├── _show_info_card.php         ✅ Date, type, description, ref_doc, created_by
├── _show_lines_table.php       ✅ Debit/Credit lines with account + partner + CC/Project
├── _show_source_doc.php        ✅ Embedded source: INVENTORY_TRX / AP_INVOICE / AP_PAYMENT
└── _show_action_bar.php        ✅ Post/Delete/Print sticky bar
```

#### ~~FIN P2: Finance Dashboard~~ ✅ DONE
```
app/controllers/finance/FinanceDashboardController.php  ✅
app/views/finance/dashboard/index.php                   ✅
→ KPIs: AP aging 5-bucket, draft JE count, posted MTD, GL period status
→ Top 5 vendors, recent 10 JE, 6-month Chart.js bar
```

#### ~~FIN P3: JE Show JS~~ ✅ DONE
```
public/js/modules/finance/journalentry_show.js  ✅
→ confirmPostJE(), confirmDeleteJE() với SweetAlert2 + fetch API
```

#### ~~FIN P4 (partial): AP Invoice show partials~~ ✅ DONE
```
app/views/finance/ap/
├── show.php                    ✅ Shell ~90 dòng (27KB → 8KB)
├── _show_toolbar.php           ✅ Back + invoice# + status badge + print/pay buttons
├── _show_lines_table.php       ✅ Left-col: invoice lines + tfoot net/tax/grand totals
└── _show_right_panel.php       ✅ Right-col: vendor + AP aging (#amountInWords) + GL entries
```

#### ~~FIN P5 (partial): AP Payment show partials~~ ✅ DONE
```
app/views/finance/payment/
├── show.php                    ✅ Shell ~80 dòng (14KB → 6KB)
├── _show_toolbar.php           ✅ Back + phiếu chi code + voided/posted badge + print/void buttons
├── _show_allocations_table.php ✅ Left-col: allocations table + total tfoot
└── _show_right_panel.php       ✅ Right-col: partner + payment info (#amountInWords) + notes
```

#### ~~FIN P6: Trial Balance~~ ✅ DONE (session 4)
```
app/controllers/finance/TrialBalanceController.php  ✅ index() + export() + _fetchTrialBalance()
app/views/finance/report/trial_balance.php          ✅ filter bar + 3 KPI cards + full table + tfoot + print CSS
```

#### ~~FIN P7: AP Invoice Workflow~~ ✅ DONE (session 4)
```
app/views/finance/ap/
├── _show_workflow.php     ✅ DONE session 4 — 4-step stepper
├── _show_action_bar.php   ✅ DONE session 4 — submit/approve/post/pay modes
public/js/modules/finance/ap_invoice_show.js  ✅ DONE session 4
```

#### ~~FIN P9: AR Invoice Module~~ ✅ DONE (session 5)
```
app/models/finance/ArInvoiceModel.php               ✅ 6 methods + status constants
app/controllers/finance/ArInvoiceController.php     ✅ 10 methods (full CRUD + workflow)
app/views/finance/ar/
├── index.php               ✅ List + filter + paid/remaining cols
├── show.php                ✅ Shell ~80 dòng
├── create.php              ✅ Shell + _form.php include
├── _form.php               ✅ Dynamic line rows (create/edit unified)
├── _show_workflow.php      ✅ 4-step stepper
├── _show_action_bar.php    ✅ submit/approve/post/collect modes
├── _show_lines_table.php   ✅ Line items + tfoot
└── _show_right_panel.php   ✅ Customer + payment + SO/GL links
public/js/modules/finance/ar_invoice_show.js    ✅ Workflow AJAX
public/js/modules/finance/ar_invoice_form.js    ✅ Add/remove lines + recalc
app/config/permissions_list.php  ✅ 4 AR permissions added
app/config/menu_structure.php    ✅ AR menu uncommented
```

#### ~~FIN P8: Income Statement + AR Aging Reports~~ ✅ DONE (session 6)
```
app/models/finance/ArReportModel.php                ✅ getArAgingReport() + getPartnerArDetails()
app/controllers/finance/ArReportController.php      ✅ aging() + get_partner_details_ajax() + export()
app/controllers/finance/IncomeStatementController.php ✅ index() + export() + _fetchIncomeData()
app/views/finance/report/ar_aging.php               ✅ green theme, AJAX partner detail modal
app/views/finance/report/income_statement.php        ✅ KPI cards, Revenue+Expense sections, net profit
app/config/permissions_list.php  ✅ finance.reports.ar_aging + finance.reports.income_statement
app/config/menu_structure.php    ✅ AR Aging + P&L menu items added to Finance section
```

#### ✅ FIN Phase 2: JS Externalization + Helpers + DTOs + Requests (Tháng 4/2026)

```
Mục tiêu: Chuẩn hoá code quality theo Oracle Engineer Standard V2

JS modules mới (4 files):
├── public/js/modules/finance/ap_payment.js     ✅ 344L — AP Payment form (AP_PAYMENT_CONFIG)
├── public/js/modules/finance/ar_receipt.js     ✅ 318L — AR Receipt form (AR_RECEIPT_CONFIG)
├── public/js/modules/finance/journal_entry.js  ✅ 175L — JE form: addRow/removeRow/calcTotal (JOURNAL_CONFIG)
└── public/js/modules/finance/coa.js            ✅ 151L — COA SPA modal (COA_CONFIG)

Finance Helpers mới (4 files):
├── FinanceCalculationHelper.php  ✅ 250L — currency conversion, VAT calc, payment due dates
├── FinanceValidationHelper.php   ✅ 246L — period lock, balance validation, credit limit
├── FinanceNotificationHelper.php ✅ 186L — email recipient lookup AP/AR
└── FinanceReportingHelper.php    ✅ 210L — aging buckets, YTD aggregation, formatters

DTOs + Requests mới (3 files):
├── app/dtos/finance/ArReceiptDTO.php              ✅ 152L
├── app/requests/finance/ArReceiptFormRequest.php  ✅ 143L
└── app/requests/finance/JournalEntryFormRequest.php ✅ 193L

View Partials mới (4 files):
├── app/views/finance/costcenter/_form.php    ✅ 97L — shared form create/edit
├── app/views/finance/costcenter/_modals.php  ✅ 95L
├── app/views/finance/project/_form.php       ✅ 153L — shared form create/edit
└── app/views/finance/project/_modals.php     ✅ 120L

Views refactored (inline JS → external):
├── payment/create.php       ✅ 516→207 lines (-60%)
├── arreceipt/create.php     ✅ 289→174 lines (-40%)
├── journal/create.php       ✅ 239→109 lines (-54%)
└── coa/index.php            ✅ 306→266 lines (-13%)

Detail: .github/oracle-erp/modules/finance-phase2-upgrade.md
```

#### ✅ FIN Phase 3: View Partials + Bootstrap Modals + JS Cleanup (Tháng 4/2026)

```
Mục tiêu: Đạt chuẩn Purchasing — shell+partials cho tất cả show pages, Bootstrap modals thay confirm()/prompt()

arreceipt/show.php refactored (209→54L shell):
├── app/views/finance/arreceipt/show.php              ✅ Shell 54L (209→54L, -74%)
├── app/views/finance/arreceipt/_show_toolbar.php     ✅ 35L — Top toolbar: code, status badge, Post/Void buttons
├── app/views/finance/arreceipt/_show_allocations.php ✅ 47L — Left col: allocation table + total tfoot
├── app/views/finance/arreceipt/_show_info.php        ✅ 56L — Right col: 5 info cards (receipt, customer, amount, GL, history)
└── app/views/finance/arreceipt/_modals.php           ✅ 35L — Void modal (form POST + CSRF)

JS extracted:
└── public/js/modules/finance/arreceipt_show.js       ✅ 29L — postReceipt() AJAX (AR_RECEIPT_SHOW_CFG)

AR Invoice: Bootstrap 5 workflow modals (thay confirm()/prompt()):
├── app/views/finance/ar/_modals.php                  ✅ 155L — 6 modals: Submit/Recall/Approve/Reject(reason)/Post/Void(reason)
├── app/views/finance/ar/show.php                     ✅ Updated — include _modals.php trước footer
└── public/js/modules/finance/ar_invoice_show.js      ✅ Updated — workflow fns → Bootstrap modal + _executeArWorkflow()

GL Period: inline JS → glperiod.js:
├── public/js/modules/finance/glperiod.js             ✅ Updated — setupFormValidation() thêm vào DOMContentLoaded
├── app/views/finance/glperiod/create.php             ✅ Updated — 2nd script block removed
└── app/views/finance/glperiod/edit.php               ✅ Updated — 2nd script block removed
```

Detail: .github/oracle-erp/modules/finance-phase3-upgrade.md


---

### 🔵 MODULE: PRODUCTION (Score: 92% → 95% → 100% — Session 13 final | Go-Live Audit: 96% ✅ 2026-04-11)

**Hoàn thành (session 13 — Mobile+Email+Export+Print+JS → 100%):**
- ✅ `app/views/production/bom/index_mobile.php` — Blue gradient BOM card list (status/version/product/SKU)
- ✅ `app/views/production/shopfloor/dashboard_mobile.php` — Green gradient WC cards (waiting/running stats)
- ✅ `app/controllers/production/BomController.php` — `isMobileDevice()` + mobile routing + `printView($id)`
- ✅ `app/controllers/production/ShopFloorController.php` — `isMobileDevice()` + mobile routing
- ✅ `app/services/production/ProductionEmailService.php` — `notifyWorkOrderStatus()` + `notifyPlanStatus()`
- ✅ `app/services/production/ProductionPlanExportService.php` — 9-col Excel (MPS list, purple header)
- ✅ `app/controllers/production/ProductionPlanController.php` — `export()` endpoint added
- ✅ `public/js/modules/production/bom_show.js` — Extracted from inline (jsTree + lifecycle modals)
- ✅ `app/views/production/bom/show.php` — Inline JS removed, loads external `bom_show.js` + `BOM_SHOW_CFG`
- ✅ `app/views/production/bom/print.php` — A4 landscape BOM print (material list + co-products + signatures)

**Hoàn thành (session 12 — BOM export endpoint):**
- ✅ `BomController.php` updated: Added `export($id)` method calling `BomExportService`
- ✅ `BomExportService.php` already existed — now wired to controller endpoint
- ✅ `ProductionReportController.php` already existed (index + wo_report + export)

**Hoàn thành (session 11 — Mobile + Import + Export + Plan partials):**
- ✅ Plan `show.php` refactored: 643 → 164 lines (shell + 3 partials)
- ✅ Plan `_show_header.php` — Top header bar with action buttons (back, edit, delete, approve, create WO dropdown)
- ✅ Plan `_show_info_card.php` — Left info panel (plan info, 5-step workflow stepper, summary stats)
- ✅ Plan `_show_items_table.php` — Right product table (12 columns, checkboxes, WO badges, urgency flags)
- ✅ WO `index_mobile.php` — Orange gradient, card-based WO list with search/filter/status dropdown
- ✅ Plan `index_mobile.php` — Purple gradient, card-based Plan list with search/filter
- ✅ `WorkOrderController.php` updated: `isMobileDevice()` + mobile routing in `index()`
- ✅ `ProductionPlanController.php` updated: `isMobileDevice()` + mobile routing in `index()`
- ✅ `WorkOrderExportService.php` — 14-col Excel export (overdue highlighting, summary row, freezePane)
- ✅ `WorkOrderImportService.php` — `parseExcelForPreview()` + `generateTemplate()` with instruction sheet
- ✅ `WorkOrderController.php` endpoints: `export()`, `download_template()`, `import_process()`
- ✅ Production menu restructured (6 sub-groups: MPS, WO, BOM, Engineering, Reports, Config)
- ✅ Finance menu restructured (5 sub-groups: GL, AP, AR, Reports, Config)

**Hoàn thành (session 5 — BOM show.php integration):**
- ✅ BOM `show.php` integrated: 3 partials linked (`_show_info_card.php`, `_show_items_table.php`, `_show_history.php`)
- ✅ BOM `show.php` refactored: 1269 → 797 lines, PHP syntax clean
- ✅ Fixes: missing `</div>` for tab-content + container-fluid, unclosed `if(false):` block, routing tab indent

**Hoàn thành (session 4 — BOM partials):**
- ✅ `_show_header.php` — BOM title + status badges + action buttons
- ✅ `_show_info_card.php` — general tab: 3-column info/tech-specs/mini-timeline
- ✅ `_show_items_table.php` — components tab + outputs table (dynamic columns via `$industry_config`)
- ✅ `_show_history.php` — audit trail with diff viewer + workflow event badges

**Hiện trạng (sessions 1-3):**
- ✅ Work Order, BOM, Routing, Shop Floor, Production Plan đều có CRUD
- ✅ BOM có workflow service (BomWorkflowService, BomLifecycleService)
- ✅ WIP services đầy đủ (WipIssueService, WipCompletionService)
- ✅ BOM `_show_action_bar.php` có nhưng thiếu toàn bộ partials khác
- ✅ Work Order show.php refactored → shell + 4 partials + `workorder_show.js` (session 2)
- ✅ Work Order show.php upgraded to 5 tabs: `_show_wip_transactions.php`, `_show_cost_card.php`, `_show_history.php` (session 3)
- ✅ Work Order `print_traveler.php` (A4 WO Traveller with routing + BOM + sign-off) (session 3)

**Oracle Process Gaps (remaining):**
- [ ] WIP Accounting: Material consumption → WIP GL entry → FG completion
- [ ] Variance analysis: Standard cost vs actual cost per WO
- [ ] Capacity planning (Work Center load vs available)
- [ ] Scrap/Reject tracking và GL write-off
- [ ] Shop floor data collection (labor time, machine time)

**Việc cần làm:**

#### ~~PROD P1: Work Order show partials~~ ✅ DONE (sessions 2–3)
```
app/views/production/workorder/
├── show.php                    ✅ Shell ~160 dòng (refactored)
├── _show_header.php            ✅ WO number + status badge + action buttons + _modals.php
├── _show_info_card.php         ✅ Product, qty, dates, work center, BOM, progress
├── _show_materials_tab.php     ✅ Materials tab: BOM lines vs issued qty + add/edit/remove AJAX
└── _show_operations_tab.php    ✅ Operations tab: routing stages with planned/actual time

public/js/modules/production/
└── workorder_show.js           ✅ Delete/Release/Materials CRUD/Drawing change/Search autocomplete
```

#### ~~PROD P2: Work Order print (traveller)~~ ✅ DONE
```
app/views/production/workorder/print_traveler.php  ✅ WO Traveller: header + BOM + routing + sign-off
```

#### ~~PROD P5: BOM show partials~~ ✅ DONE (session 4)
```
app/views/production/bom/
├── _show_header.php            ✅ NEW — BOM title + status badges + action buttons
├── _show_info_card.php         ✅ NEW — general tab: 3-col info/tech-specs/mini-timeline (bomTlClass guard)
├── _show_items_table.php       ✅ NEW — components + outputs tables (dynamic via industry_config)
└── _show_history.php           ✅ NEW — audit trail + diff viewer + workflow event badges
```

Note: BOM `show.php` integration COMPLETE (session 5) — 797 lines, 4 partials linked, syntax-clean.

#### ~~PROD P6: Production Plan show partials~~ ✅ DONE (session 11)
```
app/views/production/planning/
├── show.php                    ✅ Shell ~164 dòng (643 → 164, refactored)
├── _show_header.php            ✅ NEW — Top header + action buttons (approve, create WO)
├── _show_info_card.php         ✅ NEW — Plan info + 5-step workflow stepper + summary stats
└── _show_items_table.php       ✅ NEW — 12-col product table + WO badges + urgency flags
```

#### ~~PROD P7: Mobile views~~ ✅ DONE (session 11)
```
app/views/production/workorder/index_mobile.php   ✅ Orange gradient, card-based, client-side filter
app/views/production/planning/index_mobile.php    ✅ Purple gradient, card-based, client-side filter
app/controllers/production/WorkOrderController.php     ✅ isMobileDevice() + mobile routing
app/controllers/production/ProductionPlanController.php ✅ isMobileDevice() + mobile routing
```

#### ~~PROD P8: WO Export Service~~ ✅ DONE (session 11)
```
app/services/production/WorkOrderExportService.php  ✅ 14-col Excel, overdue highlighting, summary row
app/controllers/production/WorkOrderController.php  ✅ export() endpoint
```

#### ~~PROD P9: WO Import Service~~ ✅ DONE (session 11)
```
app/services/production/WorkOrderImportService.php  ✅ parseExcelForPreview + generateTemplate
app/controllers/production/WorkOrderController.php  ✅ download_template() + import_process()
```

#### ~~PROD P3: Production Report Controller~~ ✅ ALREADY EXISTS
```php
// app/controllers/production/ProductionReportController.php — đã có index + wo_report + export
```

#### ~~PROD P4: BOM Export Service~~ ✅ DONE (session 12)
```php
// app/services/production/BomExportService.php — đã có (multi-level indented BOM export)
// app/controllers/production/BomController.php — Added export($id) endpoint (session 12)
```

#### ~~PROD P10: BOM+ShopFloor Mobile, Email, Plan Export, BOM Print, JS Show~~ ✅ DONE (session 13)
```
app/views/production/bom/index_mobile.php             ✅ Blue gradient, BOM card list + status filter
app/views/production/shopfloor/dashboard_mobile.php    ✅ Green gradient, WC cards + search
app/services/production/ProductionEmailService.php     ✅ WO + Plan email notifications
app/services/production/ProductionPlanExportService.php ✅ 9-col MPS Excel export
app/controllers/production/ProductionPlanController.php ✅ export() endpoint
app/views/production/bom/print.php                     ✅ A4 landscape print (materials + co-products + signatures)
app/controllers/production/BomController.php           ✅ printView($id) + isMobileDevice()
app/controllers/production/ShopFloorController.php     ✅ isMobileDevice() + mobile routing
public/js/modules/production/bom_show.js               ✅ Extracted inline JS (jsTree, lifecycle modals)
app/views/production/bom/show.php                      ✅ Cleaned inline JS → external bom_show.js
```

---

### ✅ MODULE: HR (Score: 60% → 75% → 85% → 92% → 95% → 100% — Session 16 final)

**Hoàn thành (session 16 — Mobile + Print + Show Partials + JS Show → 100%):**
- ✅ `app/views/hr/employee/index_mobile.php` (green gradient, status filter, card-based)
- ✅ `app/views/hr/attendance/index_mobile.php` (orange gradient, dept filter, work days count)
- ✅ `app/views/hr/payroll/index_mobile.php` (purple gradient, search, net pay cards)
- ✅ `app/views/hr/contract/index_mobile.php` (blue gradient, status filter, contract cards)
- ✅ `app/views/hr/performance/index_mobile.php` (pink gradient, status filter, score/grade cards)
- ✅ 5 controllers updated: `isMobileDevice()` + mobile routing (Employee, Attendance, Payroll, Contract, Performance)
- ✅ `app/views/hr/employee/print.php` (A4 portrait: personal info, work info, contract history, signatures)
- ✅ `app/views/hr/attendance/print.php` (A4 landscape: daily timesheet grid, symbols, totals, signatures)
- ✅ `EmployeeController.php` + `AttendanceController.php`: `printView()` methods added
- ✅ `public/js/modules/hr/contract/contract_show.js` (extracted from contract/show.php inline JS)
- ✅ `contract/show.php` refactored: inline JS → external contract_show.js
- ✅ `attendance/detail.php` refactored: shell ~50 dòng + `_detail_filter.php` + `_detail_report.php` + `_detail_adjust_modal.php`
- ✅ `payroll/view.php` refactored: shell ~45 dòng + `_show_sidebar.php` (info + email cards)

**Hoàn thành (session 15 — Extract Export Services):**
- ✅ `app/services/hr/EmployeeExportService.php` (PhpSpreadsheet Excel, 47 cols, green theme, zebra stripes)
- ✅ `app/services/hr/AttendanceExportService.php` (3 sheets summary + detail format, OT/night shift logic, raw logs)
- ✅ `EmployeeController.php` refactored: export() → EmployeeExportService (~230 lines extracted)
- ✅ `AttendanceController.php` refactored: export() → AttendanceExportService (~553 lines extracted, including exportDetailFormat)

**Hoàn thành (session 14 — Export Services + Print Views):**
- ✅ `app/services/hr/ContractExportService.php` (PhpSpreadsheet Excel, 10 cols, blue theme)
- ✅ `app/services/hr/PerformanceExportService.php` (PhpSpreadsheet Excel, 11 cols, purple theme)
- ✅ `app/views/hr/payroll/print.php` (A4 payslip: work summary, OT, earnings/deductions, net, signatures, confidential)
- ✅ `app/views/hr/performance/print.php` (A4 review: grade badge, scores by category, comments, signatures)
- ✅ `ContractController.php` updated: `export()` method wired to ContractExportService
- ✅ `PerformanceController.php` updated: `export()` + `printView()` methods
- ✅ `PayrollController.php` updated: `printView()` method
- ✅ FileUploader bug fix sửa toàn bộ controllers (Employee, Contract, Products, SalesQuote, Portal, Asset)
- ✅ Avatar hiển thị: AuthController set session, header, profile page, portal

**Hoàn thành (session 12 — Mobile + Print + Export):**
- ✅ `app/views/hr/leave_request/index_mobile.php` (card-based mobile, teal gradient, client-side filter)
- ✅ `app/views/hr/overtime_request/index_mobile.php` (card-based mobile, purple gradient, client-side filter)
- ✅ `app/views/hr/leave_request/print.php` (A4 print: company header, leave details, 3 signature blocks)
- ✅ `app/views/hr/overtime_request/print.php` (A4 print: company header, OT details, 3 signature blocks)
- ✅ `app/services/hr/LeaveRequestExportService.php` (PhpSpreadsheet Excel, 9 cols, teal theme)
- ✅ `app/services/hr/OvertimeExportService.php` (PhpSpreadsheet Excel, 10 cols, purple theme)
- ✅ `LeaveRequestController.php` updated: `isMobileDevice()` + mobile routing + `export()` + `printView()`
- ✅ `OvertimeRequestController.php` updated: `isMobileDevice()` + mobile routing + `export()` + `printView()`

**Hoàn thành (session 4):**
- ✅ Contract `_show_action_bar.php` (modes: terminate/renew/edit, terminate modal + JS inline)
- ✅ Contract `_show_workflow.php` (3-step stepper: Tạo HĐ → Đang hiệu lực → Kết thúc, near-expiry badge)
- ✅ Contract `show.php` updated: includes workflow + action bar + `CONTRACT_CFG` JS config + `submitTerminateContract()`

**Hoàn thành (sessions 1-3):**
- ✅ Employee CRUD hoàn chỉnh (39 columns, import wizard)
- ✅ Attendance tracking, Leave Request, Overtime, Payroll, Contract
- ✅ Email services (Leave, Overtime, Timekeeper, Adjustment)
- ✅ HR Dashboard cơ bản
- ✅ Employee show.php refactored → shell + 5 partials (session 2)
- ✅ Leave Request view.php refactored → shell + 3 partials + `leave_request_show.js` (session 2)
- ✅ Contract show.php refactored → shell + 2 partials + `show()` controller method (session 3)
- ✅ Performance showreview.php refactored → shell + 3 partials + external JS (session 13)

**Hoàn thành (session 13 — Workflow + Validation + Print):**
- ✅ `app/services/hr/LeaveRequestWorkflowService.php` (submit/approve/reject/recall/cancel + transaction + lockForUpdate)
- ✅ `app/services/hr/OvertimeRequestWorkflowService.php` (same pattern + attendance recalc in service)
- ✅ `app/requests/hr/LeaveRequestStoreRequest.php` (input validation class)
- ✅ `app/requests/hr/OvertimeRequestStoreRequest.php` (input validation class)
- ✅ `app/views/hr/contract/print.php` (A4 print: company header, contract details, salary, 3 signature blocks)
- ✅ `app/views/hr/performance/_show_header.php` + `_show_info_card.php` + `_show_scores_detail.php` (show partials)
- ✅ `public/js/modules/hr/performance/performance.js` updated: submitReview + closeReview functions
- ✅ `LeaveRequestController.php` updated: recall() + cancel() wired to WorkflowService
- ✅ `OvertimeRequestController.php` updated: recall() + cancel() wired to WorkflowService
- ✅ `ContractController.php` updated: printView() method added

**Oracle HCM Process Gaps:**
- [ ] Employee lifecycle: Hire → Probation → Confirm → Transfer → Separation
- [ ] Organizational hierarchy chart (org chart view)
- [ ] Payroll: tax calculation engine theo biếu lũy tiến VN
- [ ] Time & Labor: liên kết timekeeper → attendance → payroll
- [ ] Benefits enrollment tracking
- [ ] Training records và certification tracking

**Việc cần làm:**

#### ~~HR P1: Employee show partials~~ ✅ DONE
```
app/views/hr/employee/
├── show.php                    ✅ Shell ~107 dòng (647 → 107)
├── _show_sidebar.php           ✅ Avatar + name + status badge + join_date + timekeeper_id + action buttons
├── _show_tab_info.php          ✅ 9 sections: job/personal/ID/education/contact/emergency/bank/insurance + terminated
├── _show_tab_contracts.php     ✅ Contracts table + Thêm HĐ button
├── _show_tab_leaves.php        ✅ Leave history table
├── _show_tab_assets.php        ✅ Assigned assets table
└── _job_history.php            ✅ Pre-existing partial, reused
```

#### ~~HR P2: Leave Request show partials~~ ✅ DONE
```
app/views/hr/leave_request/
├── view.php                    ✅ Shell ~107 dòng (383 → 107)
├── _show_header.php            ✅ Back link + title + status badge + 3-step stepper
├── _show_info_card.php         ✅ Employee info + leave details + attachments (previewAttachment)
└── _show_timeline.php          ✅ Right col: approval history + pending state

public/js/modules/hr/
└── leave_request_show.js       ✅ confirmApproveShow + confirmRejectShow + submitLeaveAction + previewAttachment
```

#### ~~HR P3: Contract show~~ ✅ DONE (sessions 3–4)
```
app/controllers/hr/ContractController.php   ✅ Added show() method (session 3)
app/views/hr/contract/
├── show.php                    ✅ Shell updated (session 4 — workflow + action bar + CONTRACT_CFG)
├── _show_header.php            ✅ Back + contract# + status badge + employee name/code + edit/file buttons
├── _show_info_card.php         ✅ Contract details + salary cards + audit trail
├── _show_workflow.php          ✅ NEW session 4 — 3-step stepper + near-expiry badge
└── _show_action_bar.php        ✅ NEW session 4 — terminate/renew/edit modes + terminate modal
```

#### ~~HR P4: Payroll Export~~ ✅ ALREADY EXISTS
```php
// app/services/hr/PayrollExportService.php — đã có (2-sheet export: department summary + detail payslips)
```

#### ~~HR P5: Mobile views cho Portal entry points~~ ✅ DONE (session 12)
```
app/views/hr/leave_request/index_mobile.php   ✅ Teal gradient, card-based, client-side filter
app/views/hr/overtime_request/index_mobile.php ✅ Purple gradient, card-based, client-side filter
```

#### ~~HR P6: Print views~~ ✅ DONE (session 12)
```
app/views/hr/leave_request/print.php   ✅ A4 printable leave request + signatures
app/views/hr/overtime_request/print.php ✅ A4 printable OT request + signatures
```

#### ~~HR P7: Export services~~ ✅ DONE (session 12)
```
app/services/hr/LeaveRequestExportService.php  ✅ 9-col Excel, teal theme
app/services/hr/OvertimeExportService.php      ✅ 10-col Excel, purple theme
```

---

### ✅ MODULE: QUALITY (Score: 48% → 65% → 100% — Session 16 final)

**Hoàn thành (session 16 — Quality → 100%):**
- ✅ `app/views/quality/specification/index_mobile.php` (purple gradient, status+QC type filters, cards with product/revision/status)
- ✅ `app/views/quality/defect/index_mobile.php` (red gradient, severity filter chips, defect cards with code/description/group)
- ✅ `app/views/quality/characteristic/index_mobile.php` (teal gradient, active/inactive filter chips, characteristic cards)
- ✅ `QaSpecificationController.php` + `QaDefectController.php` + `QaCharacteristicController.php`: added `isMobileDevice()` + mobile routing
- ✅ `app/views/quality/specification/show.php` refactored: 338 → 32 lines (shell with 3 partials)
- ✅ `app/views/quality/specification/_show_header.php` (breadcrumb + title + all workflow buttons + print button)
- ✅ `app/views/quality/specification/_show_product_card.php` (product info + status badge + dates + approval + notes)
- ✅ `app/views/quality/specification/_show_spec_lines.php` (grouped category tables with spec parameters)
- ✅ `public/js/modules/quality/specification_show.js` (SPEC_CFG + showWorkflowModal + submitWorkflowForm)
- ✅ `app/views/quality/specification/print.php` (A4 landscape, grouped spec tables + signatures)
- ✅ `QaSpecificationController::printView()` + `export()` + `exportDetail()` methods added
- ✅ `app/services/quality/QaEmailService.php` (notifyInspectionDecision + notifySpecificationWorkflow)
- ✅ `app/services/quality/SpecificationExportService.php` (exportList + exportDetail — PhpSpreadsheet Excel)

**Hoàn thành (session 12 — Mobile + Import + Export wiring):**
- ✅ `app/views/quality/inspection/index_mobile.php` (card-based mobile, blue gradient, decision filter)
- ✅ `app/services/quality/QaInspectionImportService.php` (Excel import: preview + import, product/lot/qcType cache, VN number format)
- ✅ `QaInspectionController.php` updated: `isMobileDevice()` + mobile routing + `export()` + `import()` endpoints
- ✅ `InspectionExportService.php` already existed — now wired to controller via `export()` endpoint

**Hoàn thành (session 4):**
- ✅ `QaInspectionWorkflowService.php` (accept/reject/hold transitions + `_transition()` internal)
- ✅ Inspection `_show_workflow.php` (3-step stepper: Tạo phiếu → Đang kiểm → Quyết định)
- ✅ Inspection `_show_action_bar.php` (hold→[Giữ lại/Từ chối/Chấp thuận], accept/reject→[Xem xét lại])
- ✅ `inspection_show.js` (confirmAccept/Reject/Hold functions với fetch API + INSP_CFG)
- ✅ `QaInspectionController.php` updated: added `accept()`, `reject()`, `hold()` action methods
- ✅ Inspection `show.php` updated: includes workflow + action bar + `INSP_CFG` config + both JS files

**Hoàn thành (sessions 1-3):**
- ✅ QA Inspection có create/edit/show/index
- ✅ QA Specification với header + lines
- ✅ Defect codes, QC Types, QC Status — master data hoàn chỉnh
- ✅ Service layer cơ bản (6 services)
- ✅ Inspection show.php refactored → shell + 4 partials (session 2)
- ✅ Inspection print.php (A4 printable report + signatures) (session 3)
- ✅ QC Dashboard (KPI cards, pass rate trend, top defects, by-type table) (session 3)
- ❌ Pareto analysis controller chưa có

**Oracle Quality Process Gaps:**
- [ ] Inspection flow: Incoming → In-Process → Final → Ship
- [ ] NCR (Non-Conformance Report) workflow: Open → Review → Disposition → Close
- [ ] CAPA (Corrective Action / Preventive Action) tracking
- [ ] Sampling plan (AQL) integration với inspection
- [ ] Quality holds: block inventory release khi fail inspection
- [ ] Pareto chart tự động từ defect codes

**Việc cần làm:**

#### ~~QA P1: Inspection show partials~~ ✅ DONE (sessions 2 + 4)
```
app/views/quality/inspection/
├── show.php                    ✅ Shell ~64 dòng (350 → 64), updated session 4 (workflow + action bar + JS)
├── _show_header.php            ✅ Title + decision badge (ACCEPT/REJECT/HOLD) + Edit/Delete/Back
├── _show_product_card.php      ✅ Product image + SKU + lot badge + KPI row (pass/reject/rework rates)
├── _show_measurements.php      ✅ Measurements table (characteristic/min/target/max/actual/UOM/result)
├── _show_qc_meta.php           ✅ QC Info + vendor + loss/scrap + audit trail
├── _show_workflow.php          ✅ NEW session 4 — 3-step stepper (Tạo → Kiểm → Quyết định)
└── _show_action_bar.php        ✅ NEW session 4 — hold/accept/reject decision buttons

app/services/quality/
└── QaInspectionWorkflowService.php  ✅ NEW session 4 — accept/reject/hold transitions

app/controllers/quality/QaInspectionController.php
└── accept(), reject(), hold()  ✅ NEW session 4 — workflow action endpoints

public/js/modules/quality/
└── inspection_show.js          ✅ NEW session 4 — confirm functions + INSP_CFG
```

#### ~~QA P2: Inspection print.php~~ ✅ DONE (session 3)
```
app/views/quality/inspection/print.php      ✅ A4 printable inspection report
app/controllers/quality/QaInspectionController.php  ✅ Added print() method
app/views/quality/inspection/_show_header.php       ✅ Added "In phiếu" button
```

#### ~~QA P3: Quality Dashboard~~ ✅ DONE (session 3)
```
app/views/quality/dashboard/index.php       ✅ KPI cards + monthly trend Chart.js + top defects + by-type
app/services/quality/QaInspectionService.php ✅ getDashboardStats() added
app/controllers/quality/QaInspectionController.php ✅ Added dashboard() action
```

#### QA P4: NCR Module (Non-Conformance Report)
```
app/controllers/quality/NcrController.php
app/models/quality/NcrModel.php
app/services/quality/NcrService.php
app/views/quality/ncr/
└── (full set: index, show, create, _form, _modals, _show_*.php)
```

#### QA P5: Pareto Controller
```php
// app/controllers/quality/QaiqcController.php  (bỏ comment trong menu)
// Pareto chart: top defect codes by frequency and impact
```

---

### 🔵 MODULE: INVENTORY (Score: 45% → 55% → 70% → 75% → 85% → 90% → 92% → 95% — Session 36)

**Đặc thù**: Module giao dịch kho hàng (transactional). Mỗi Service tự xử lý workflow (approve/cancel/reverse). Không cần formal WorkflowService như Purchasing/Sales. Code quality đạt chuẩn cao nhất hệ thống sau full hardcode audit.

**Hoàn thành (Session 20 — Dashboard + Mobile views: 4 entities):**
- ✅ `app/helpers/inventory/InventoryDashboardHelper.php` (14 KPI methods: stock overview, movement summary, low stock, expiring lots, pending transactions, audit summary, picking, MR, top products, recent transactions)
- ✅ `app/controllers/inventory/InventoryDashboardController.php` (requirePermission + filter validation + 14 data arrays)
- ✅ `app/views/inventory/dashboard/index.php` (~530L: 6 KPI cards + warehouse table + alerts + charts + Chart.js)
- ✅ `app/config/permissions_list.php` added `inventory.dashboard`
- ✅ `app/config/menu_structure.php` added "Dashboard Kho" (first child of Inventory group)
- ✅ `app/views/inventory/receipt/show_mobile.php` (blue gradient, partner/warehouse info, cost permission-gated, attachments, approve/cancel)
- ✅ `app/views/inventory/transfer/show_mobile.php` (teal gradient, warehouse flow visualization src→dst, approve/cancel)
- ✅ `app/views/inventory/materialissue/show_mobile.php` (orange gradient, source type badges WIP_MR/WO/ADHOC, WO/MR links)
- ✅ `app/views/inventory/audit/show_mobile.php` (purple gradient, KPI grid progress/accuracy%, blind/open mode, recount hierarchy, variance highlighting)
- ✅ 4 controllers wired: `isMobileDevice()` + mobile routing in show() (InventoryReceiptController, InventoryTransferController, MaterialIssueController, InventoryAuditController)
- ✅ All files PHP lint validated — zero errors

**Hoàn thành (Session 19 — Show Partials refactoring: 14 entities, 83 partial files):**
- ✅ Receipt show.php (583L → shell ~3.5KB) → 7 partials: header, info_card, items_table, flow_tab, modals, action_bar, scripts
- ✅ Transfer show.php (334L → shell ~3.2KB) → 6 partials: header, info_card, items_table, modals, action_bar, scripts
- ✅ Material Issue show.php (401L → shell ~2.8KB) → 5 new partials + 1 existing `_modals.php` reused
- ✅ GI Sales show.php (480L → shell ~3.0KB) → 5 partials: header, info_card, items_table, modals, scripts
- ✅ GI Request show.php (709L → shell ~3.5KB) → 6 partials: header, alerts, info_card, items_table, action_bar, modals
- ✅ Audit show.php (634L → shell ~5.0KB) → 8 partials: header, summary, analysis, info_card, items_table, action_bar, modals, scripts
- ✅ Pick Confirm show.php (553L → shell ~6.4KB) → 6 new partials + 1 existing `_modals.php` reused
- ✅ Opening show.php (386L → shell ~3.0KB) → 6 partials: header, info_card, items_table, action_bar, modals, scripts
- ✅ Material Requisition show.php (359L → shell ~5.0KB) → 5 new partials + 1 existing `_modals.php` reused
- ✅ Adjustment show.php (356L → shell ~3.5KB) → 6 partials: header, info_card, items_table, action_bar, modals, scripts
- ✅ WIP Issue show.php (338L → shell ~4.3KB) → 5 new partials + 1 existing `_modals.php` reused
- ✅ Material Return show.php (304L → shell ~3.2KB) → 5 partials: header, info_card, items_table, modals, scripts
- ✅ Trip show.php (249L → shell ~4.1KB) → 5 partials: header, info_card, items_table, modals, scripts
- ✅ Lot History show.php (230L → shell ~1.8KB) → 3 partials: header, info_card, items_table
- ✅ Tất cả partials PHP lint validated — zero errors

**Hiện trạng:**
```
Receipt:             ✅ 7 partials + shell
Transfer:            ✅ 6 partials + shell
Material Issue:      ✅ 5 partials + _modals.php (existing)
GI Sales:            ✅ 5 partials + shell
GI Request:          ✅ 6 partials + shell
Audit:               ✅ 8 partials + shell
Pick Confirm:        ✅ 6 partials + _modals.php (existing)
Opening:             ✅ 6 partials + shell
Material Requisition:✅ 5 partials + _modals.php (existing)
Adjustment:          ✅ 6 partials + shell
WIP Issue:           ✅ 5 partials + _modals.php (existing)
Material Return:     ✅ 5 partials + shell
Trip:                ✅ 5 partials + shell
Lot History:         ✅ 3 partials + shell
```

**Hoàn thành (Session 21 — Mobile index views + Export + Print + Helpers):**
- ✅ `app/views/inventory/receipt/index_mobile.php` (blue gradient, supplier/warehouse cards, status filter, client search)
- ✅ `app/views/inventory/transfer/index_mobile.php` (teal gradient, src→dst warehouse flow badges)
- ✅ `app/views/inventory/materialissue/index_mobile.php` (orange gradient, WO badge, priority dots, total_amount)
- ✅ `app/views/inventory/audit/index_mobile.php` (purple gradient, stat boxes total_lines/variance, variance highlight)
- ✅ 4 controllers wired: `isMobileDevice()` + mobile routing in index() (Receipt, Transfer, MaterialIssue, Audit)

**Hoàn thành (Session 22 — Export Excel + Helpers + JS show files):**
- ✅ `app/services/inventory/AuditExportService.php` (Excel export phiếu kiểm kê: variance highlight, mode/status labels)
- ✅ Export methods wired: `export()` in InventoryReceiptController, InventoryTransferController, MaterialIssueController, InventoryAuditController
- ✅ `app/helpers/inventory/InventoryCalculationHelper.php` (weighted avg cost, FIFO cost layers, variance %, UOM conversion, pro-rata allocation, audit summary)
- ✅ `app/helpers/inventory/InventoryValidationHelper.php` (available qty check, batch availability, lot expiry, lot duplicate, bin validation, transaction date, receive tolerance)
- ✅ `app/helpers/inventory/InventoryReportingHelper.php` (ABC classification, stock aging analysis, turnover ratio, dead/slow-moving detection, status badge CSS)
- ✅ `public/js/modules/inventory/inventory_transfer_show.js` (reverse modal validation, tooltip init)
- ✅ `public/js/modules/inventory/material_issue_show.js` (reverse validation, tab persistence, tooltip init)
- ✅ `public/js/modules/inventory/inventory_audit_show.js` (data-confirm handler, variance highlighting, print button)

**Remaining gaps (future sessions):**
- [x] Mobile views — show_mobile.php cho Receipt, Transfer, Material Issue, Audit (Session 20)
- [x] Dashboard view — InventoryDashboardHelper + Controller + View + Permission + Menu (Session 20)
- [x] Mobile index views — index_mobile.php cho Receipt, Transfer, Material Issue, Audit (Session 21)
- [x] Export Excel — Receipt, Transfer, MaterialIssue, Audit (Session 22)
- [x] Audit print view + controller method (Session 21)
- [x] Helpers expansion — InventoryCalculationHelper, InventoryValidationHelper, InventoryReportingHelper (Session 22)
- [x] JS show files — transfer_show.js, material_issue_show.js, audit_show.js (Session 22)
- [x] Full module audit — security, SQL, XSS, status maps, column names (Session 23)
- [ ] StockCard show partials (nếu cần)

**Hoàn thành (Session 23 — Full Module Audit: 10 bugs fixed, 12 files modified):**
- ✅ **CRITICAL FIX**: `InventoryValidationHelper::checkAvailableQty()` — cột `quantity_available` không tồn tại → sửa thành `SUM(quantity - quantity_reserved)`
- ✅ **CRITICAL FIX**: `InventoryCalculationHelper::auditSummary()` — sai tên cột (`system_qty` → `system_quantity`) + null detection bug `(float)null = 0.0`
- ✅ **CRITICAL FIX**: Bảng `inventory_count_records` thiếu trong schema → tạo migration + cập nhật db_schema.sql
- ✅ **HIGH FIX**: 4 Export Services hardcode status maps → dùng `InventoryConstants` labels (Receipt, Transfer, Audit, MaterialIssue)
- ✅ **HIGH FIX**: `InventoryDashboardHelper::getAuditSummary()` — status `'counting'` không tồn tại → chỉ dùng `'in_progress'`
- ✅ **Constants**: Thêm `IT_STATUS_LABELS`, `AUDIT_STATUS_LABELS`, `AUDIT_MODE_LABELS` vào InventoryConstants.php
- ✅ **XSS FIX**: `receipt/show_mobile.php` dùng `esc_url()` cho file path, `transfer/_form.php` dùng `esc_attr()`/`e()`
- ✅ Audit kết quả: Controllers (26) clean, Models (20) clean, Services (25) fixed, Helpers (7) fixed, Views (211) 97% score

**Hoàn thành (Session 24-26 — UI Standardization + Partner Info):**
- ✅ Transfer `_form.php` UI sync: Enterprise layout (icon circle header, summary stats, fixed action-bar, 9-column table khớp Receipt)
- ✅ Transfer `_modals.php` + `inventory_transfer.js` stock picker modal: `<colgroup>` + `table-layout:fixed` = aligned columns
- ✅ Transfer stock picker: badge-lot (indigo), badge-bin (green), row-selected (blue), input-transfer-qty (yellow border) CSS
- ✅ Opening Stock `create.php`: synced to standard (fixed action-bar, icon circle header, padding-bottom 70px)
- ✅ Partner/NCC info added to all 5 inventory lot pickers:
  - `InventoryReceiptModel.php` — JOIN partners in `getPendingPoItems()`
  - `LotHistoryModel.php` — JOIN partners in `searchLotsWithStock()`
  - `WipIssueModel.php` — JOIN partners in `searchLots()`
  - `inventory_receipt.js` — PO group header shows partner_name
  - `materialissue.js`, `wipissue-create.js`, `stock_adjustment.js` — lot dropdown shows NCC
  - `inventory_transfer.js` — already had partner info (modal)
- ✅ Created `.github/inventory/INVENTORY_UI_STANDARD.md` — 11-section UI standard document

**Hoàn thành (Session 27 — Cache Busting Fix):**
- ✅ **CRITICAL FIX**: `asset_v()` dùng `filemtime()` cho cả dev lẫn production — file thay đổi → URL mới → browser tự tải lại
  - Trước: Production dùng `APP_VERSION` hardcode → deploy mới nhưng browser vẫn cache cũ
  - Sau: `filemtime()` cho tất cả environment, fallback `APP_VERSION` chỉ khi file không tồn tại
- ✅ Fixed 2 views dùng sai `asset_path()` + manual `?v=APP_VERSION`:
  - `purchasing/pricelist/show.php` → `asset_v()`
  - `sales/pricelist/show.php` → `asset_v()`

**Hoàn thành (Session 32 — Allow Negative Stock Consistency + PDO Fix):**
- ✅ **CRITICAL FIX**: `allow_negative_stock` giờ áp dụng nhất quán toàn bộ module (trước chỉ có Transfer approve/reverse)
  - `InventoryTransferModel.php` — `createTransfer()`, `updateTransfer()`: `checkAllowNegative()` gọi TRƯỚC foreach loop (tránh PDO $stmt singleton conflict)
  - `WipIssueModel.php` — `createPending()`, `allocateFIFO()`, `reserveStock()`: cache per-warehouse, truyền `$allowNegative` param
  - `MaterialIssueService.php` — `approveByFifo()`: cache per-warehouse (mỗi line có thể khác warehouse), wrap cả lot/bin mode + FIFO mode
  - `StockAdjustmentService.php` — `approve()`, `reverse()`, `autoDeductStock()`: check trước loop, truyền param, thêm bin/lot specific check
- ✅ **Pattern**: `checkAllowNegative($warehouseId, $siteId)` — `COALESCE(warehouse.allow_negative_stock, org_params.allow_negative_stock, 0)` — gọi TRƯỚC FOR UPDATE loops
- ✅ **PDO FIX**: `SessionManager::recordSession()` — duplicate named params trong UPSERT (`EMULATE_PREPARES=false` không cho reuse `:sessid`, `:ip`, `:agent`)
- ✅ Removed debug `error_log()` từ `InventoryTransferModel.php`
- ✅ All 5 files PHP lint validated — zero errors

**Hoàn thành (Session 33-34 — Hardcode Elimination Phase 2: Views):**
- ✅ **50+ view files** updated — tất cả hardcode status/type/label strings chuyển sang `InventoryConstants` labels
- ✅ **180+ replacements** across: receipt, transfer, materialissue, wipissue, girequest, gisales, materialrequisition, pickconfirm, adjustment, audit, opening, trip, lothistory, materialreturn, stock, dashboard, PDA
- ✅ Pattern: `'APPROVED'` → `InventoryConstants::IR_STATUS_APPROVED`, `'Phiếu nhập'` → `InventoryConstants::IR_STATUS_LABELS[...]`
- ✅ Badges, filters, status displays, mobile views — tất cả dùng constants thay vì hardcode

**Hoàn thành (Session 35 — Hardcode Elimination Phase 3: Models + Dead Code Cleanup + Asset_v Audit):**
- ✅ **Magic numbers eliminated** trong 6 model files (~22 replacements):
  - `TransactionType.php`: Added `OPERATION_IN=1`, `OPERATION_OUT=-1`, `OPERATION_TRANSFER=0`
  - `InventoryConstants.php`: Added 7 constants (`QC_TYPE_IQC_CODE`, `SPEC_STATUS_ACTIVE`, `SOURCE_TYPE_DN`, `DOCUMENT_TYPE_DN`, `LOT_STATUS_AVAILABLE`, `LOOKUP_ADJUSTMENT_REASON`, `ACTIVE_FLAG`)
  - Fixed: `InventoryReceiptModel`, `WarehouseStockModel`, `StockCardModel`, `GiRequestModel`, `StockAdjustmentModel`, `PointInTimeStockModel`
- ✅ **Dead code cleanup** — 16 items removed:
  - 14× `show_old.php` deleted (receipt, transfer, materialissue, wipissue, materialrequisition, pickconfirm, girequest, adjustment, audit, opening, trip, lothistory, materialreturn, gisales)
  - `InventoryConfigController.php.new` deleted (stale duplicate)
  - Empty `app/views/inventory/deliverynote/` directory removed
  - Audit confirmed: all 20 models, 25 services, 7 helpers, 9 DTOs, 12 requests, 20 JS files are ACTIVE
- ✅ **Asset_v audit**: 100% compliant — 249 `asset_v()` calls across all views (245 JS + 4 CSS), zero violations
  - No bare `URLROOT` for local JS/CSS, no manual `?v=` patterns
  - Only exceptions: PWA manifest/service-worker files (legitimate)

**Hoàn thành (Session 36 — Hardcode Audit Phase 2/3 FINAL: 144 findings, 124 fixed, 32 files):**
- ✅ **P3 writeAuditLog** — 16 hardcoded status strings → `InventoryConstants::*` trong 6 controllers (Receipt, Transfer, MI, MR, WipIssue, PickConfirm)
- ✅ **P3 Controller hardcodes** — 8 controllers: `'IN'/'OUT'` → `ADJ_TYPE_IN/OUT`, `'in'/'out'` → `FLOW_IN/OUT`, `'supplier'` → `MasterdataConstants::PARTNER_TYPE_SUPPLIER`, `role_id !== 1` → `$_SESSION['is_admin']`
- ✅ **P3 Model hardcodes** — 3 files: `'ADJUSTMENT_IN'/'ADJUSTMENT_OUT'` → `TransactionType::*`, `'ADJUSTMENT'` → `SOURCE_TYPE_ADJUSTMENT`, `'ADHOC'` → `SOURCE_TYPE_ADHOC`
- ✅ **P3 Service hardcodes** — 6 files: `TransactionType::*`, `LOT_STATUS_AVAILABLE`, `SOURCE_TYPE_DN/AUDIT/ADHOC`, ghost user `?? 0` → `?? null` + throw
- ✅ **P3 View hardcodes** — 12 files: status maps + filter options → `InventoryConstants::*` (materialreturn, pickconfirm×6, pda×3, transfer_mobile, materialrequisition)
- ✅ **New constant**: `SOURCE_TYPE_ADJUSTMENT` added to `InventoryConstants.php`
- ✅ **Security fix**: `OpeningStockImportService` ghost user fallback `?? 0` → throw Exception
- ✅ **Loose == audit**: 70+ `REQUEST_METHOD == 'POST'` instances reviewed — all string-to-string, documented as acceptable
- ✅ All 32 modified files PHP syntax validated — zero errors
- ✅ Audit documentation updated: `.github/oracle-erp/modules/inventory-golive-audit.md` Phase 2 section added

**Thống kê hiện tại (Session 36):**
```
Controllers:  26 files,  12,647 lines
Models:       20 files,  ~12,030 lines
Services:     25 files,  ~8,000+ lines
Views:       197 files across 23 subdirectories
JS Modules:   20 files,  ~7,055 lines
Helpers:       7 files,   ~2,800 lines
DTOs:          9 files,   ~861 lines
Requests:     12 files,   ~852 lines
Print views:   7 (Receipt, Transfer, MI, WipIssue, Audit, PickConfirm×2, GiSales)
Export Svcs:   8 (Receipt, Transfer, MI, Audit, GRN Register, Issue Register, PO Outstanding, PIT Stock)
Import Svcs:   1 (OpeningStockImportService)
Mobile:        4 entities × (index_mobile + show_mobile) + PDA 17 files
Config:       18+ permissions, 18 menu items, 12 lookups, 11 transaction types, 15 document sequences
Constants:    InventoryConstants.php ~545 lines (13 new constants added in Phase 2/3)
TOTAL:        ~37,600+ lines
```

**Code Quality (highest in system):**
```
Security Audit:     163 findings total (Phase 1 + Phase 2), 142 fixed, 21 documented
Hardcode Strings:   ZERO remaining — all status/type/label strings use InventoryConstants
SQL Injection:      100% parameterized (all bind(), zero string concatenation)
XSS:                100% views escaped (e(), esc_attr(), esc_url())
CSRF:               100% forms + AJAX protected
Site Scoping:       100% queries filter site_id (via BaseModel or explicit)
asset_v():          100% compliant (249 calls, zero violations)
```

**Remaining gaps (feature-level only):**
- [ ] EmailService (notify on approve/reject events) — **genuine gap, 5% remaining**
- [ ] StockCard show partials (nếu cần)
- [ ] WipCompletion views (controller exists, 179L — no views)
- [ ] materialissue.js split (2,739L — candidate for refactoring)

---

### 🟡 MODULE: MASTER DATA (Score: 50% → 75% → 90% — Session 18)

**Entities**: Products (**100%**), Partners (70%), UOM (60%), AttributeSets (55%), Categories (50%), Warehouses (35%), Tooling (25%)

**Đã hoàn thành (Session 17):**
- ✅ Products show.php → 11 `_show_*.php` partials
- ✅ Tài liệu `.github/masterdata/MASTERDATA_MODULE.md`
- ✅ show.php giảm từ 619L → ~100L shell + 11 partials

**Đã hoàn thành (Session 18) — Products → 100%:**
- ✅ Granular permissions: +5 mới → tổng 12 permissions (`product.import`, `product.export`, `product.print`, `product.view_cost`, `product.view_price`)
- ✅ Permission-gated UI: Giá Vốn/Giá Bán ẩn theo `view_cost`/`view_price`, action bar theo `can_edit`/`can_delete`/`can_print`
- ✅ Controller: `import()` → `product.import`, `export()` → `product.export`, `download_template()` thêm security
- ✅ Fat methods → ProductService: `getAttributeSetDefinitionsForJs()`, `getEnumValuesById()`, `getProductPrintData()`
- ✅ Print view `print.php` (A4 ISO format, permission-gated pricing)

**Hiện trạng:**
```
Products:   ✅ 100% — Service, DTO, Request, _form, _modals, Import/Export, JS 3, Show 11, Print, 12 Perms
Partners:   ✅ Service, DTO, Request, _form, _modals, Import/Export, JS 2 files | ❌ Show Partials
UOM:        ✅ Service 3, Request 5, SPA via AJAX | N/A show partials
AttrSets:   ✅ _form, _modals, JS 1 file | ❌ Service, DTO, Request
Categories: ✅ _form, Request 1 | ❌ Service, _modals, JS
Warehouses: ❌ Service, _form, _modals, JS
Tooling:    🔴 Model MISSING | ❌ Full architecture
```

**Remaining gaps (future sessions):**
- [ ] Partners show.php → partials (466L monolithic)
- [ ] Tooling model fix (CRITICAL — broken)
- [ ] Warehouses service + form standardization
- [ ] Products _form.php split (916L — functional, low priority)
- [ ] Products Product.php Model split (1106L → tách Import + PDA)

---

### � MODULE: ASSET (Score: 15% → 30% → 75% — R4 Security Audit + R5 View Restructure)

**Hiện trạng (verified R4/R5 — post Session 37):**
- ✅ 5 Controllers: DashboardController, AssetController, MaintenanceController, OperationsController, ReportController
- ✅ 3 Models: AssetModel (1,400L — `useAuditLog=true`, 4 `beginTransaction`, 6 `NULLIF`), AssetInventoryModel, AssetMaintenanceModel
- ✅ **6 Services**: AssetService, DepreciationService, MaintenanceService, **MaintenanceWorkflowService**, AssetEmailService, MaintenanceEmailService
- ✅ **4 Helpers**: AssetConstants, AssetCalculationHelper, AssetDashboardHelper, AssetValidationHelper
- ✅ **1 DTO**: AssetDTO
- ✅ **2 Requests**: AssetStoreRequest, AssetUpdateRequest
- ✅ **42 Views** (8 subdirs: asset/, maintenance/, operations/, stocktake/, dashboard/, reports/, scanner/, config/):
  - `asset/`: show.php (198L shell) + 9 show partials + create/edit/import/index/_form/_modals
  - `operations/`: dispose.php, handover.php, revalue.php, upgrade.php
  - `reports/`: 6 views (asset_register, by_department, by_employee, depreciation, increase_decrease, warranty_alert)
  - `stocktake/`: index.php, create.php, show.php
  - `maintenance/`: full CRUD + show.php + calendar.php + _form + _modals
- ✅ **10 JS files**: dashboard, edit, form, import, list, maintenance, report-department, report-depreciation, **show**, transactions
- ✅ Dashboard: DashboardController + dashboard.js + dashboard/index.php
- ✅ Email services: 2 (AssetEmailService, MaintenanceEmailService)
- ✅ Show partials: 9 files (`_show_tab_overview`, `_show_tab_finance`, `_show_tab_maintenance`, `_show_tab_handover`, `_show_tab_history`, `_show_action_bar`, `_show_header`, `_show_lifecycle`, `_show_scripts`)
- ✅ Security fixes (R4): AS1-AS4 all FIXED — CSRF on all DELETE endpoints, cron auth via CRON_SECRET_KEY
- ✅ Site isolation: `getAssetImages()` + `get_revaluation_history()` both add `site_id` filter
- ✅ SQL fixes: `get_month_depreciations()` + `get_month_depreciation_total()` — fixed `a.site_id` via JOIN (was `ad.site_id` non-existent)
- ✅ Service fixes: `MaintenanceService` — correct table name `asset_maintenances`, correct column `completed_date`
- ✅ Helper fix: `AssetValidationHelper` uses real statuses from `AssetConstants`
- ✅ DB schema: `asset_categories.code` UNIQUE key site-scoped; `asset_maintenances.status` ENUM expanded
- ✅ View structure: R5 renamed folders (`list_detail/`+`forms/` → `asset/`, `inventory/` → `stocktake/`, `transactions/` → `operations/`)
- ⚠️ `import.php` view exists (7,768 bytes) but AssetImportService not yet created / wired
- ❌ Thiếu mobile views (index_mobile, show_mobile)
- ❌ Thiếu print view
- ❌ Thiếu ExportService
- ❌ Thiếu Asset lifecycle WorkflowService (acquisition→disposal state machine)
- ❌ Không có GL integration (depreciation posting, disposal GL entry)

**Oracle Asset Process Gaps:**
- [ ] Asset lifecycle Oracle: `In Service` → `Under Repair` → `Disposed` với GL entries
- [ ] Depreciation auto-posting monthly (cron job → Journal Entry)
- [ ] Asset revaluation với GL entry
- [ ] Capital expenditure approval (acquisition PO → Asset creation)
- [ ] Property/Plant/Equipment register per GL account
- [ ] Asset insurance tracking

**Việc cần làm:**

#### ASSET P1: WorkflowService
```php
// app/services/asset/AssetWorkflowService.php
// submit_acquisition(), approve_acquisition(), retire(), dispose()
// Mỗi transition → AutoAccounting GL entry
```

#### ASSET P2: Import wizard
```
app/views/assets/manager/import.php
app/services/asset/AssetImportService.php
```

#### ASSET P3: ExportService
```php
// app/services/asset/AssetExportService.php
// Export: Asset Register (Excel) — all assets with book value, accumulated depreciation
```

#### ASSET P4: GL Integration
```php
// app/services/finance/AutoAccounting_asset.php
// Acquisition: Dr Asset Account / Cr AP or Cash
// Depreciation: Dr Depreciation Expense / Cr Accumulated Depreciation
// Disposal: Dr Accumulated Depreciation + Dr Loss on Disposal / Cr Asset Account
```

---

## V. FULL CODEBASE AUDIT — Session 27 (08/04/2026)

> Audit toàn bộ mã nguồn theo chuẩn Oracle R12 + OWASP Top 10 + project conventions.
> Kết quả ghi nhận theo module. User sẽ quyết định thứ tự sửa.
>
> **Phase 1 (CRITICAL):** 12/12 COMPLETE — Session 27-28 (08/04/2026)
> **Phase 2 (HIGH):** 7/~15 DONE — Session 28-29 (09/04/2026)
> **Phase 3 (Inventory Hardcode):** 3/3 Phases COMPLETE — Session 33-36 (09/04/2026)
> Includes: FinanceConstants, PmConstants, isMobileDevice→Base, checkLockedDate AP, JE bug fix, AS4 cron auth

### 📊 Tổng quan Audit

| Module | CRITICAL | HIGH | MEDIUM | LOW | Security Score | Architecture Score | Oracle Score |
|--------|---------|------|--------|-----|---------------|-------------------|-------------|
| **Core Framework** | 1 | 0 | 1 | 2 | 85% | 70% | N/A |
| **Purchasing** | 0 | 3 | 5 | 4 | 90% | 75% | 95% |
| **Sales** | 1 | 5 | 4 | 0 | ~~85%~~ 95% | 65% | 95% |
| **Production** | 0 | 4 | 3 | 0 | 95% | 85% | 90% |
| **Finance** | 3 | 5 | 4 | 0 | 70% | 60% | 75% |
| **HR** | 3 | 7 | 8 | 2 | 65% | 55% | 70% |
| **Quality** | 0 | 1 | 3 | 2 | 90% | 85% | 75% |
| **Inventory** | 2 | 6 | 5 | 4 | ~~80%~~ 95% | ~~70%~~ 95% | 85% |
| **Master Data** | 3 | 3 | 5 | 2 | 60% | 65% | 80% |
| **PM** | 1 | 3 | 4 | 1 | 75% | 40% | 25% |
| **Asset** | 0 | 2 | 2 | 0 | 90% | 65% | 50% |

---

### 🔴 CORE FRAMEWORK

| # | Sev | Vấn đề | File | Ghi chú |
|---|-----|--------|------|---------|
| C1 | **CRITICAL** | `EMULATE_PREPARES = true` — PDO xử lý bind client-side, yếu hơn server-side prepared statements | `app/core/Database.php` ~L38 | Đổi thành `false` |
| C2 | **MEDIUM** | `Controller.php` 751 dòng — CSRF, auth, pagination, audit, view rendering gộp chung | `app/core/Controller.php` | Chấp nhận được cho base class |
| C3 | **LOW** | `extract($data, EXTR_SKIP)` trong view rendering — khó trace variables | `app/core/Controller.php` ~L390 | Maintainability, không phải security |
| C4 | **LOW** | `EnvSecurityManager` logging tách rời `Controller::logError()` | `app/core/EnvSecurityManager.php` | 2 logging paths |

---

### 🟢 PURCHASING (100% score)

> **Phase 8 Encoding & Cleanup (2026-05-11):** 17 issues fixed — die()→json(), echo json_encode→json(), hardcoded statuses→constants, JSON_UNESCAPED_UNICODE system-wide.
> **Phase 7 Shipment Security Audit (2026-04-14):** 22 issues fixed across 7 files (2 CRITICAL, 4 HIGH, 16 MEDIUM).
> **⚠️ QUY TẮC:** Mọi thao tác DB phải dùng UPDATE, tuyệt đối KHÔNG DELETE rồi INSERT — bảo toàn FK integrity.
> Chi tiết: [`purchasing-golive-audit.md`](../oracle-erp/modules/purchasing-golive-audit.md) — Phase 1-8 hoàn thành, 69+ issues fixed.
>
> **Actual size (2026-04-20):** 10 Controllers (5,235L), 7 Models (4,963L), 15 Services (8,153L), 18 Helpers (4,596L), 70 Views (17,872L), 7 JS (6,148L), 3 DTOs (779L), 3 Requests (716L) = **133 files, ~48,462L**

| # | Sev | Vấn đề | File | Ghi chú |
|---|-----|--------|------|---------|
| PU1 | **HIGH** | `PurchaseOrder.php` 2,024→**1,493** dòng | `app/models/purchasing/PurchaseOrder.php` | ✅ Phase 4c split → PurchaseOrderQueryHelper (583L) |
| PU2 | **HIGH** | `PurchaseRequest.php` 1,277→**1,203** dòng | `app/models/purchasing/PurchaseRequest.php` | ✅ Phase 4a delegation → PurchaseRequestService |
| PU3 | **HIGH** | `PurchaseOrderController.php` 1,439→**1,346** dòng | `app/controllers/purchasing/PurchaseOrderController.php` | ✅ Phase 4b template extraction |
| PU4 | **MEDIUM** | `$this->skipCSRF = true` in API controllers | 2 API controllers | ✅ Verified: Base Controller auto-validates, mutations có CSRF riêng |
| PU5 | **MEDIUM** | `new Service()` → `$this->service()` | PO Controller, PR API | ✅ Phase 5a fixed |
| PU6 | **MEDIUM** | Fat dashboard views (1,275L, 1,031L) | Views | ⚠️ Acceptable for dashboard complexity |
| PU7 | **MEDIUM** | Fat mobile views (PO 734L, PR 903L) | Views | ⚠️ Acceptable — partials used within |
| PU8 | **MEDIUM** | `function_exists('requirePermission')` wrapping | API controllers | ✅ Phase 5e — defensive, kept intentionally for API backward-compat |

---

### 🟢 SALES (100% score)

| # | Sev | Vấn đề | File | Ghi chú |
|---|-----|--------|------|---------|
| SA1 | **CRITICAL** | Case-sensitive path bug: `js/Modules/sales/sales_order.js` (viết hoa M) — FAIL trên Linux/production | `app/views/sales/salesorder/convert_confirm.php` L206 | Fix ngay |
| SA2 | **HIGH** | `SalesOrderController.php` 2,035 dòng — fattest controller | Controller | Tách AJAX → SalesOrderApiController |
| SA3 | **HIGH** | `SalesOrderModel.php` 1,594 dòng — chứa `createDirectOrder()`, `convertFromQuote()`, `cancelOrder()` | Model | Business logic → SalesOrderService |
| SA4 | **HIGH** | `SalesQuoteModel.php` `saveToAgreement()` 150 dòng business logic trong model | Model L683-830 | → PriceListService |
| SA5 | **HIGH** | `SalesOrderService.php` 1,563 dòng — cần split | Service | → Create/Update/Workflow services |
| SA6 | **HIGH** | `SalesOrderModel.createDirectOrder()` gọi `$this->atpService->processReservation()` — model orchestrate service | Model ~L485 | Vi phạm kiến trúc |
| SA7 | **MEDIUM** | `create.php` + `edit.php` SO chứa inline JS (238-241L) | Views | JS → sales_order.js |
| SA8 | **MEDIUM** | `sales_order.js` + `sales_quote.js` không quản lý CSRF token cho standalone AJAX | JS | Chỉ form.serialize() có CSRF |
| SA9 | **MEDIUM** | Print views 600-630L — fat | Views | Chấp nhận được cho print |
| SA10 | **MEDIUM** | Import views 488-548L | Views | Có thể tách partials |

---

### 🟢 PRODUCTION (100% score)

| # | Sev | Vấn đề | File | Ghi chú |
|---|-----|--------|------|---------|
| PR1 | **HIGH** | `BomController.php` 1,222 dòng | Controller | Có service delegation tốt nhưng file quá lớn |
| PR2 | **HIGH** | `WorkOrderModel.php` 1,229 dòng | Model | Tách report/filter queries → service |
| PR3 | **HIGH** | `BomModel.php` 1,161 dòng — cost rollup, explosion | Model | Một phần logic → BomExplosionService |
| PR4 | **HIGH** | `ProductionPlanModel.php` 867 dòng | Model | Borderline |
| PR5 | **MEDIUM** | Direct SQL trong controller cho AJAX (products/bom search) | `WorkOrderController.php` L1059-1094 | → Model methods |
| PR6 | **MEDIUM** | Stub services: WorkCenter/Routing/RoutingStage (23L mỗi file) | Services | Minimal facade, chưa có logic |
| PR7 | **MEDIUM** | `wipmove` views không có `_form.php`, `_modals.php`, `_show_*.php` | Views | Inline tất cả |
| **Oracle** | **MEDIUM** | Chưa có capacity planning service (CRP/resource loading) | Gap | |
| **Oracle** | **MEDIUM** | Chưa có variance analysis service (standard vs actual cost) | Gap | |

---

### 🟡 FINANCE (100% score nhưng architecture gaps)

| # | Sev | Vấn đề | File | Ghi chú |
|---|-----|--------|------|---------|
| FI1 | **CRITICAL** | XSS: `print.php` AP — `echo $inv->partner_name`, `$inv->invoice_number`, `$inv->code` không `e()` | `app/views/finance/ap/print.php` L6,50,57 | Fix ngay |
| FI2 | **CRITICAL** | AR Invoice `post()` KHÔNG tạo GL journal entry — chỉ đổi status | `ArInvoiceController.php` ~L238 | Phải tạo JE: Dr Receivables / Cr Revenue |
| FI3 | **CRITICAL** | AR Invoice `_generateCode()` dùng `COUNT(*)` — race condition, bypass DocumentSequenceService | `ArInvoiceController.php` L329-337 | → `DocumentSequenceService::generate($siteId, 'AR')` |
| FI4 | **HIGH** | Không có `FinanceConstants.php` — hardcode status strings `'draft'`, `'posted'`, `'approved'` rải rác 6+ files | Toàn module | ✅ Created 188L |
| FI5 | **HIGH** | `FinanceDashboardController` chỉ có SQL trực tiếp (126L, 7 raw queries) | Controller | → DashboardService hoặc Model |
| FI6 | **HIGH** | AP Invoice controller thiếu workflow actions (submit/approve/reject/post) | `ApInvoiceController.php` | `_show_action_bar.php` có buttons nhưng backend thiếu handlers |
| FI7 | **HIGH** | Missing DTOs cho AR Invoice, JournalEntry, Tax, CostCenter, Project, ExchangeRate, PaymentTerm | `app/dtos/finance/` | Chỉ có 2: ApInvoiceDTO, ApPaymentDTO |
| FI8 | **HIGH** | AR Invoice `store()` business logic trong controller — line total, bulk insert | `ArInvoiceController.php` L140-190 | ✅ FIXED 2026-04-22: → `ArInvoiceService` (createInvoice/validateAndCalculate/insertDetails/generateCode) |
| FI9 | **MEDIUM** | `checkLockedDate()` THIẾU trong AP Invoice store và AP Payment store | Controllers | ✅ Added to both store() |
| FI10 | **MEDIUM** | CostCenter + Project views không có `_form.php`, `_modals.php`, `asset_v()` | Views | Old-style views |
| FI11 | **HIGH** | Thiếu Import Excel cho AR Invoice (AP+JE đã có, AR chưa có) | Service + view | ✅ FIXED 2026-04-22: `ArInvoiceImportService` (420L) + `ar/import.php` 3-step wizard + `ar_import.js` |
| FI12 | **MEDIUM** | AP Invoice `store()` bloat 130L validation + item processing trong controller | `ApInvoiceController.php` | ✅ FIXED 2026-04-22: → `ApInvoiceService::createInvoice()` + `validateHeaderAndItems()`, controller giảm 396 → ~300L |
| **Oracle** | **MEDIUM** | Chưa có budget vs actual reporting | Gap | |
| **Oracle** | **MEDIUM** | Chưa có bank reconciliation module | Gap | |
| **Oracle** | **MEDIUM** | Multi-currency partial — ExchangeRate table có, AR Invoice chưa xử lý currency | Gap | |

---

### ✅ HR (100% features — security audit 99.8%, GO-LIVE APPROVED)

> **Go-live audit:** `.github/oracle-erp/modules/hr-golive-audit.md` — Score 99.8/100
> **Audit completed:** 2026-04-11 (Phase 1-5), 2026-07 (Phase 6-12) — P0 ALL ✅, P1 13/13 ✅, P2-15/P2-17 ✅, getById site_id 12 models ✅
> **Deep audit (Oracle Sr. Engineer):** 2026-07 Session 38 — 248 files (42,248+ lines) audited. 5 CRITICAL + 10 HIGH + 15 MEDIUM findings. All P0/P1 fixed in-session.

| # | Sev | Vấn đề | Status | Ghi chú |
|---|-----|--------|--------|---------|
| HR1 | **CRITICAL** | SQL injection HrDashboardController — string interpolation | ✅ FIXED | P0-1: parameterized binds |
| HR2 | **CRITICAL** | Hardcoded password '12345' + exposed in flash | ✅ FIXED | P0-2: .env config |
| HR3 | **CRITICAL** | `AttendanceController` manual CSRF — 8 redundant blocks | ✅ FIXED | P2-6: removed, base Controller handles |
| HR4 | **HIGH** | 45 `Database::getInstance()` in controllers | ✅ FIXED | P1-2: all replaced with `$this->db` (1 intentional skip) |
| HR5 | **HIGH** | `$_SESSION` direct access — 48+ instances | ✅ FIXED | P1-3+P1-6: `getCurrentUserId()` / `currentUser()` |
| HR6 | **HIGH** | 4 AJAX endpoints missing permission checks | ✅ FIXED | P1-5: `requirePermission()` added |
| HR7 | **HIGH** | `buildFilteredDepartmentTree()` duplication in 8 controllers | ✅ FIXED | P1-7: extracted to DepartmentHelper (~424 lines removed) |
| HR8 | **HIGH** | HrConstants — 429 lines defined, only 12 references | ✅ FIXED | P1-1: adopted across 19 files, 85+ strings replaced |
| HR9 | **HIGH** | XSS — 20 unescaped outputs in views | ✅ FIXED | P1-10: `e()` + `esc_js()` applied |
| HR10 | **MEDIUM** | Hardcoded status dropdowns in 21 views | ✅ FIXED | P2-8: HrConstants label/badge methods |
| HR11 | **MEDIUM** | `implode(',', $ids)` — 9 instances | ✅ FIXED | P2-5: named placeholders |
| HR12 | **MEDIUM** | HrDashboardController ~500L raw SQL inline | ✅ FIXED | Extracted 4 methods to HrDashboardHelper + deleted dead code (1164→770L) |
| HR13 | **LOW** | 5 files >1,000 lines (AttendanceCalculator, large JS) | ⏳ Post-launch | P2-1: split recommended |
| HR14 | **LOW** | CHỈ 1 DTO, 2 Request classes — 14+ entities | ⏳ Post-launch | P2-2+P2-3: add as needed |
| **Oracle** | **HIGH** | Payroll → GL: không tạo journal entry khi finalize payroll | Gap | Oracle `Pay_Payroll → GL_INTERFACE` |
| **Oracle** | **HIGH** | Employee lifecycle: không có state machine (probation→active→terminated) | Gap | Direct update, no validation |
| **Oracle** | **MEDIUM** | Attendance document numbering: dùng auto-increment, không formatted sequence | Gap | |

#### Deep Audit — Session 38 (Oracle Sr. Engineer Level)

**Scope:** 23 controllers (12,060L), 24 models (9,183L), 24 services (8,929L), 7 helpers (2,138L), 5 DTOs (686L), 6 requests (275L), 137 views, 22 JS files (8,977L)

| # | Sev | Vấn đề | Status | Files affected |
|---|-----|--------|--------|----------------|
| HRD1 | **CRITICAL** | HrConstants LEAVE/OT constants lowercase ('draft','pending') vs DB UPPERCASE ('DRAFT','PENDING') — all view comparisons silently failing | ✅ FIXED | HrConstants.php, lookups_list.php |
| HRD2 | **CRITICAL** | LeaveRequestController::store() multi-employee bulk loop without transaction — partial failure corrupts leave balances | ✅ FIXED | LeaveRequestController.php |
| HRD3 | **CRITICAL** | AttendanceController::submit_adjustment() auto-approve path (~210 lines) without transaction — adjustment + timesheet writes not atomic | ✅ FIXED | AttendanceController.php |
| HRD4 | **CRITICAL** | AttendanceModel 2 queries use lowercase `'approved'` for leave_requests.status — case mismatch bug (works only on CI collation) | ✅ FIXED | AttendanceModel.php L1270, L1539 |
| HRD5 | **CRITICAL** | AttendanceCalculator `'approved'` lowercase in getDailyAttendanceStats — same case mismatch | ✅ FIXED | AttendanceCalculator.php L2299 |
| HRD6 | **HIGH** | ~60+ hardcoded status strings across 5 controllers (Leave/OT/Attendance/Performance/HrDashboard) | ✅ FIXED | 5 controllers — all replaced with HrConstants |
| HRD7 | **HIGH** | ~15 hardcoded status strings in AttendanceModel SQL CASE expressions | ✅ FIXED | AttendanceModel.php — used `$adjApproved` variable |
| HRD8 | **HIGH** | 12 hardcoded status strings in 4 services (OT/Leave/Calculator/AdjWorkflow) | ✅ FIXED | 4 service files — bind params + HrConstants |
| HRD9 | **HIGH** | LeaveRequestModel, OvertimeRequestModel hardcoded 'PENDING' in ORDER BY | ✅ FIXED | 2 model files |
| HRD10 | **HIGH** | 3 WorkflowServices define duplicate local STATUS_* constants | ✅ FIXED | All 3 services now delegate to HrConstants::LEAVE_*/OT_*/ADJ_* |
| HRD11 | **HIGH** | 0/23 controllers use PERM_ constants (all inline permission strings) | ✅ FIXED | Created HrPermissions.php (76 constants), 168+ call sites replaced across 20 controllers |
| HRD12 | **HIGH** | 0/23 controllers use Request validation classes (6 exist but unused) | ⏳ Post-launch | Dead code or adoption needed |
| HRD13 | **MEDIUM** | Bulk approve/reject in OT/Leave controllers lack transaction wrapping | ✅ ASSESSED | Acceptable — each operation independently transactional via WorkflowService |
| HRD14 | **MEDIUM** | Missing timesheet status constants in HrConstants (present/absent/standard) | ✅ FIXED | Added 12 TS_* constants + replaced 73 hardcoded strings in Calculator/Model |
| HRD15 | **MEDIUM** | Duplicate lookups: LEAVE_REQUEST_STATUS & HR_LEAVE_STATUS in lookups_list.php | ✅ FIXED | Removed legacy duplicates, switched controllers to HR_LEAVE/OT_STATUS |
| HRD16 | **MEDIUM** | EmployeeController 2,247L — bloated | ✅ FIXED | Extracted EmployeeImportController (1,213L) — main controller 1,239L |
| HRD17 | **LOW** | Export services (4 files) use hardcoded label maps instead of HrConstants methods | ✅ FIXED | All 4 exports use HrConstants::get*StatusLabels() + added getPerformanceStatusLabels() |
| HRD18 | **LOW** | 7 models without audit logging (AttendanceModel 1,586L highest priority) | ✅ FIXED | Enabled audit on LeaveBalanceModel + PayrollComponentModel (financial impact) |

**✅ Positive findings:**
- No SQL injection risks in any model (all parameterized)
- 22/23 controllers have permission checks (only 1 missing — Portal)
- BaseModel auto-scopes site_id correctly for all HR models
- All 3 workflow services have proper `lockAndLoad()` with `SELECT ... FOR UPDATE` + transactions
- View standardization strong: 14/21 entities have `_form.php` + `_modals.php`
- `asset_v()` 100% compliant (252 calls, 0 violations)
- SweetAlert2 migration complete

---

### 🟢 QUALITY (100% score)

| # | Sev | Vấn đề | File | Ghi chú |
|---|-----|--------|------|---------|
| QA1 | **HIGH** | `QaInspectionController::index()` thiếu `requirePermission('qa.view')` | Controller L54 | Ai login cũng xem được |
| QA2 | **MEDIUM** | `QaInspectionService.php` 807 dòng | Service | Tách dashboard stats |
| QA3 | **MEDIUM** | `QaInspectionWorkflowService.php` chỉ 66 dòng (stub) — logic nằm trong InspectionService | Service | Merge hoặc expand |
| **Oracle** | **HIGH** | Thiếu CAR/CAPA (Corrective Action / Preventive Action) tracking | Gap | Oracle `QA_RESULTS → QA_ACTIONS` |
| **Oracle** | **HIGH** | Thiếu Supplier Quality Scoring — không tự tính pass/fail rate per supplier | Gap | Oracle `QA_SUPPLIER_SCORECARD` |
| **Oracle** | **MEDIUM** | Không auto-create inspection lot khi GRN posting | Gap | Manual navigation |
| **Oracle** | **MEDIUM** | Thiếu skip lot / reduced inspection dựa trên supplier history | Gap | AQL sampling có, skip lot chưa |

---

### 🔵 INVENTORY (95% score — code quality highest in system)

| # | Sev | Vấn đề | File | Ghi chú |
|---|-----|--------|------|---------|
| IN1 | **CRITICAL** | `StockAdjustmentController` product search thiếu `site_id` filter — expose ALL products cross-site | Controller L387-401 | Thêm `AND p.site_id = :site` |
| IN2 | **CRITICAL** | Direct raw SQL trong controller cho product search (bypass model/service) | Controller L387 | → Product model search |
| IN3 | **HIGH** | `GiRequestModel.php` 1,685 dòng — CRUD + search + status + pick + shipping | Model | Tách GiRequestQueryModel |
| IN4 | **HIGH** | `InventoryReceiptModel.php` 1,649 dòng | Model | Tách receipt filter/pagination → service |
| IN5 | **HIGH** | `MaterialIssueModel.php` 1,092 dòng | Model | |
| IN6 | **HIGH** | `PdaController.php` 1,293 dòng — receiving + transfer + picking + inquiry + audit + MI | Controller | Tách PdaReceivingController, PdaPickingController... |
| IN7 | **HIGH** | `PickConfirmController.php` 1,222 dòng | Controller | |
| IN8 | **HIGH** | `MaterialIssueService.php` 1,318 dòng | Service | → MaterialIssueWorkflowService + CostingService |
| IN9 | **MEDIUM** | `WarehouseStockModel` thiếu BaseModel properties ($table, $isSiteSpecific, $useSoftDeletes) | Model | Raw queries only |
| IN10 | **MEDIUM** | ~~Hardcode status strings trong `writeAuditLog()` calls — không dùng InventoryConstants~~ | ~~Controllers 3+ files~~ | ✅ Fixed (Session 33-35) |
| IN11 | **MEDIUM** | `PickNoteModel` string concat constant vào SQL — fragile pattern | Model L237 | Dùng bind() |
| IN12 | **MEDIUM** | ~~Stale file `InventoryConfigController.php.new`~~ | ~~Controller~~ | ✅ Deleted (Session 35) |
| IN13 | **MEDIUM** | `GiRequest` show page thiếu `_show_scripts.php` — JS inline | View | |
| IN14 | **LOW** | ~~14 `show_old.php` files chưa cleanup~~ | ~~Views~~ | ✅ Deleted (Session 35) |
| **Oracle** | **MEDIUM** | Chưa có min-max planning / reorder point automation | Gap | |

---

### 🟡 MASTER DATA (95% score)

| # | Sev | Vấn đề | File | Ghi chú |
|---|-----|--------|------|---------|
| MD1 | **CRITICAL** | Tooling `create.php` + `edit.php` thiếu `csrf_field()` — POST form không có CSRF | Views | Fix ngay |
| MD2 | **CRITICAL** | Tooling `show.php` 3 modal forms thiếu `csrf_field()` | View L308-371 | Fix ngay |
| MD3 | **CRITICAL** | Tooling `show.php` 10+ XSS: `echo $data['tool']->name/code/dimensions/location_bin` không `e()` | View L65-291 | Fix ngay |
| MD4 | **HIGH** | Tooling `index.php` XSS: `$tool->code` trong `onclick` không escape | View L145 | Injection via crafted code |
| MD5 | **HIGH** | `ToolingController` reflected XSS: flash message chứa `$_POST['code']` không escape | Controller L92 | `e($_POST['code'])` |
| MD6 | **HIGH** | `Product.php` model 1,213 dòng | Model | Tách search/import |
| MD7 | **MEDIUM** | Tooling module 25% — không có `_form.php`, `_modals.php`, external JS, DTO, Request, Service | Module | Full refactor needed |
| MD8 | **MEDIUM** | `ProductCategoriesController` + `AttributeSetsController` hardcode `'active'`/`'inactive'` | Controllers | → MasterdataConstants |
| MD9 | **MEDIUM** | `ToolingController` permission wrapping `function_exists('requirePermission')` | Controller | Sẽ skip nếu helper chưa load |
| MD10 | **MEDIUM** | Tooling views dùng `<?php echo` thay `<?=`, không `asset_v()` | Views | Old style |

---

### 🟠 PM (45% score)

| # | Sev | Vấn đề | File | Ghi chú |
|---|-----|--------|------|---------|
| PM1 | **CRITICAL** | `ComplaintController::acknowledge()` thiếu permission check | Controller ~L170 | Ai login cũng acknowledge được |
| PM2 | **HIGH** | `ProjectController.php` 1,219 dòng + 18+ direct DB queries | Controller | → ProjectService |
| PM3 | **HIGH** | `DashboardController` (PM) 12+ raw SQL queries, 238L toàn SQL | Controller | → DashboardService |
| PM4 | **HIGH** | Zero DTOs, zero Request classes cho cả module | Module | |
| PM5 | **MEDIUM** | Hardcode `$site_id = $_SESSION['user_site_id'] ?? 1` — fallback site 1 | `WarrantyController.php` L43 | → `$this->getCurrentSiteId()` |
| PM6 | **MEDIUM** | Không có `_form.php`, `_modals.php` cho Project, Task | Views | Form duplication |
| PM7 | **MEDIUM** | Không có `PmConstants.php` — status hardcode rải rác | Module | ✅ Created 260L |
| PM8 | **MEDIUM** | Không có mobile views, print views, import/export | Module | |
| **Oracle** | **HIGH** | Không có project cost tracking integration với Finance GL | Gap | |
| **Oracle** | **HIGH** | Không có budget vs actual, resource billing rates | Gap | |
| **Oracle** | **MEDIUM** | Không có project close/capitalize → GL posting | Gap | |

---

### 🔴 ASSET (15% score)

| # | Sev | Vấn đề | File | Ghi chú |
|---|-----|--------|------|---------|
| AS1 | **CRITICAL** | `MaintenanceController::delete()` thiếu CSRF **VÀ** POST check — GET request trigger delete | Controller ~L285 | Fix ngay: check POST + validateCSRF |
| AS2 | **CRITICAL** | `ManagerController` 4 methods thiếu `validateCSRF()`: `delete()`, `delete_image()`, `delete_category()` | Controller L402,540,1103 | |
| AS3 | **CRITICAL** | `InventoryController::checkin()` thiếu CSRF | Controller ~L137 | |
| AS4 | **CRITICAL** | `calculate_depreciation()` dùng IP check (`$_SERVER['REMOTE_ADDR']`) — spoofable | Controller ~L433 | ✅ → CRON_SECRET_KEY + hash_equals() |
| AS5 | **HIGH** | `AssetModel.php` 1,404 dòng — mega model chứa tất cả | Model | Tách 5 models |
| AS6 | **HIGH** | `ManagerController.php` 1,141 dòng | Controller | Tách 4 controllers |
| AS7 | **HIGH** | Zero business logic services (chỉ 2 email services) | Module | Tạo AssetService, DepreciationService |
| AS8 | **HIGH** | Zero DTO/Request classes | Module | |
| AS9 | **MEDIUM** | `FILTER_SANITIZE_FULL_SPECIAL_CHARS` trước khi lưu DB — double-encode khi render | `MaintenanceController.php` ~L104 | Store raw, escape output |
| AS10 | **MEDIUM** | Không có external JS files (`public/js/modules/asset/`) | Module | All inline JS |
| AS11 | **MEDIUM** | Không có `_form.php`, `_modals.php`, `_show_*.php` partials | Views | Full refactor needed |
| **Oracle** | **HIGH** | Depreciation: chỉ straight-line, thiếu declining balance, units-of-production | Gap | |
| **Oracle** | **HIGH** | Asset disposal/retirement không tạo GL entries | Gap | |
| **Oracle** | **MEDIUM** | Không có CIP (Construction-in-Progress) tracking | Gap | |
| **Oracle** | **MEDIUM** | Không có mass additions from AP Invoice | Gap | |
| **Oracle** | **MEDIUM** | Asset transfer chỉ person-to-person, không org-to-org | Gap | |

---

### 📋 CROSS-MODULE ISSUES

| # | Sev | Vấn đề | Scope | Ghi chú |
|---|-----|--------|-------|---------|
| X1 | **CRITICAL** | `Database.php EMULATE_PREPARES = true` ảnh hưởng TOÀN BỘ hệ thống | Core | Đổi `false` |
| X2 | **HIGH** | `isMobileDevice()` duplicated trong 5+ controllers | PO, PR, SO, SQ, Production | ✅ Added to Base Controller |
| X3 | **HIGH** | Inconsistent service loading: `new Service()` vs `$this->service()` | PO, PR, SO, SQ controllers | ⏸️ Deferred (low ROI) |
| X4 | **HIGH** | Model orchestrate service (vi phạm kiến trúc) | SO Model gọi ATPService, PO Model gọi ShipmentService | Model chỉ nên làm ORM |
| X5 | **MEDIUM** | Backup files (`.backup`, `show_old.php`, `_old.php`) rải rác toàn hệ thống | All modules | ✅ Inventory cleaned (Session 35), other modules pending |
| X6 | **MEDIUM** | `function_exists('requirePermission')` wrapping (defensive, redundant) | PO, Tooling | Helpers luôn loaded |

---

### 🎯 ƯU TIÊN SỬA — Top 20

| Priority | ID | Module | Severity | Vấn đề | Effort | Status |
|---------|-----|--------|----------|--------|--------|--------|
| 1 | X1 | Core | CRITICAL | `EMULATE_PREPARES = false` | 1 dòng | ✅ P1 |
| 2 | SA1 | Sales | CRITICAL | Case-sensitive path `js/Modules/` → `js/modules/` | 1 dòng | ✅ P1 |
| 3 | FI1 | Finance | CRITICAL | XSS trong AP `print.php` — thêm `e()` | 5 dòng | ✅ P3 |
| 4 | AS1 | Asset | CRITICAL | Maintenance `delete()` qua GET — thêm POST check + CSRF | 10 dòng | ✅ P5 |
| 5 | MD1-2 | Master Data | CRITICAL | Tooling CSRF — thêm `csrf_field()` vào 5 forms | 5 dòng | ✅ P9-10 |
| 6 | MD3-5 | Master Data | CRITICAL | Tooling XSS — wrap `e()` toàn bộ | 20 dòng | ✅ P11-12 |
| 7 | HR1 | HR | CRITICAL | Open Redirect AttendanceController — validate URL | 5 dòng | ✅ P2 |
| 8 | FI2 | Finance | CRITICAL | AR Invoice `post()` phải tạo GL journal entry | ~50 dòng | ✅ P6 |
| 9 | FI3 | Finance | CRITICAL | AR Invoice code gen → DocumentSequenceService | 10 dòng | ✅ P7 |
| 10 | IN1 | Inventory | CRITICAL | StockAdjustment product search thiếu site_id | 2 dòng | ✅ P4 |
| 11 | AS2-3 | Asset | CRITICAL | Asset controllers thiếu CSRF (5 methods) | 10 dòng | ✅ P5 |
| 12 | PM1 | PM | CRITICAL | ComplaintController missing permission | 1 dòng | ✅ P8 |
| 13 | QA1 | Quality | HIGH | InspectionController::index() missing permission | 1 dòng | ✅ P8 |
| 14 | HR4 | HR | HIGH | HolidayController import/generate thiếu CSRF | 2 dòng | ✅ (framework) |
| 15 | FI4 | Finance | HIGH | Tạo `FinanceConstants.php` | ~100 dòng | ✅ P2-FI4 |
| 16 | FI9 | Finance | MEDIUM | AP Invoice/Payment thiếu `checkLockedDate()` | 10 dòng | ✅ P2-FI9 |
| 17 | SA6 | Sales | HIGH | Model orchestrate service — refactor pattern | ~200 dòng | ⏳ |
| 18 | X2 | Cross | HIGH | `isMobileDevice()` → Base Controller | 20 dòng | ✅ P2-X2 |
| 19 | X3 | Cross | HIGH | `new Service()` → `$this->service()` | 20 dòng | ⏸️ Deferred |
| 20 | MD7 | Master Data | MEDIUM | Tooling full refactor (CSRF+XSS+form+modals+JS) | ~500 dòng | ✅ P9-12 (security) |

> **Ghi chú**: Priority 1-12 là security fixes (CRITICAL) — **ALL DONE** (Phase 1, Session 27-28).
> Priority 13-16, 18 đã hoàn thành (Phase 2). #17 & #19 deferred post-GoLive.
> Phase 1: 12 CRITICAL fixes (08/04/2026) | Phase 2: 7/15 HIGH fixes (09/04/2026)

---

### 🟢 MODULE: PM (Score: 45% → 55% — Session 37 audit)

**Hiện trạng (verified Session 37):**
- ✅ 8 Controllers: ProjectController, TaskController, MemberController, AcceptanceController, ComplaintController, WarrantyController, DashboardController, ReportController
- ✅ 10 Models: ProjectModel, ProjectMemberModel, ProjectProductModel, ProjectTaskModel, TaskAssigneeModel, TaskCommentModel, TimeLogModel, PaymentMilestoneModel, WarrantyModel, WarrantyClaimModel
- ✅ **12 Services**: ProjectService, **ProjectWorkflowService**, ProjectActivityService, ProjectAttachmentService, TaskService, AcceptanceService, **AcceptanceWorkflowService**, AdvancePaymentService, ComplaintService, WarrantyService, **PMEmailService**, **PMNotificationService**
- ✅ 1 Helper: PmConstants.php (228 lines)
- ✅ 26 Views (acceptance, complaint, dashboard, project incl. 14 show partials, report, warranty)
- ✅ 8 JS files: acceptance, complaint, pm-notify, project, project_form, project_products, warranty-claims, warranty
- ✅ **WorkflowService**: ProjectWorkflowService + AcceptanceWorkflowService (2 services)
- ✅ **Email**: PMEmailService + PMNotificationService (2 services)
- ✅ Dashboard: DashboardController + ReportController
- ❌ 0 DTOs, 0 Request classes
- ❌ Không có print view (Project contract, acceptance report)
- ❌ Không có import/export
- ❌ Không có mobile view
- ⚠️ Backup file: `views/pm/project/show.php.backup_20260324` → cleanup

**Oracle PM Process Gaps:**
- [ ] Project budget tracking vs actual spend
- [ ] Earned Value Management (EVM) metrics
- [ ] Resource allocation và utilization report
- [ ] Change Request workflow (scope change → approval → budget revision)
- [ ] Milestone-based invoicing (milestone approved → AR invoice)

**Việc cần làm:**

#### ~~PM P1: WorkflowService~~ ✅ EXISTS
```php
// app/services/pm/ProjectWorkflowService.php — Project lifecycle transitions
// app/services/pm/AcceptanceWorkflowService.php — Acceptance approval workflow
```

#### PM P2: Export
```php
// app/services/pm/ProjectExportService.php
// Export: Project list Excel, Gantt chart data, resource usage
```

#### PM P3: Print views
```
app/views/pm/project/print.php   → Project summary + milestone table
app/views/pm/acceptance/print.php → Acceptance certificate
```

---

## V. ORACLE PROCESS GAPS — CROSS-MODULE

### 1. Document Numbering (toàn hệ thống)
**Hiện tại:** Employee code sinh thủ công (SITE+YYMMDD+SEQ), không dùng `document_sequences`  
**Chuẩn Oracle:** Tất cả documents dùng `DocumentSequenceService` từ `document_sequences` table

| Module | Document | Hiện tại | Cần làm |
|--------|----------|---------|---------|
| HR | Employee Code | Manual PHP loop | `DocumentSequenceService::next('EMPLOYEE', $siteId)` |
| HR | Leave Request | ✅ Đã dùng sequence | OK |
| Finance | Journal Entry | Manual | `DocumentSequenceService::next('JE', $siteId)` |
| Quality | Inspection | Manual | `DocumentSequenceService::next('QC_INSP', $siteId)` |
| Asset | Asset Tag | Manual | `DocumentSequenceService::next('ASSET', $siteId)` |

### 2. Approval Workflow Engine
**Hiện tại:** Purchasing và HR dùng `WorkflowEngine` — các module còn lại dùng ad-hoc SQL  
**Cần làm:** Tất cả documents có approval flow phải dùng `WorkflowEngine` + `WorkflowResolver`

### 3. GL Period Enforcement
**Hiện tại:** Finance → JE có period check, nhưng Inventory, Purchasing, Production không enforce  
**Cần làm:** `$this->checkLockedDate($docDate)` trong mọi controller POST có financial impact

### 4. Multi-Currency
**Hiện tại:** ExchangeRateModel có, Purchasing dùng, Finance có  
**Cần làm:** Sales module cần currency + exchange rate cho SO/AR

### 5. Attachment System
**Hiện tại:** Purchasing có `AttachmentService` hoàn chỉnh, cùng pattern  
**Cần làm:** Finance, Quality, HR Leave/Contract, Production WO cần `_show_attachments.php`

---

## VI. THỨ TỰ TRIỂN KHAI (Priority Queue)

### Giai đoạn 1 — Quick wins (1-2 tuần/module)
Refactor show.php → partials cho các modules đã có data nhưng UI chưa chuẩn:

```
Priority 1 (tuần 1-2): Sales module show partials + JS show files
Priority 2 (tuần 3-4): Finance JE workflow + show partials
Priority 3 (tuần 5-6): HR Employee show partials + Payroll export
Priority 4 (tuần 7-8): Production WO show partials + print traveller
```

### Giai đoạn 2 — Feature gaps (1 tháng/module)
Các tính năng còn thiếu hoàn toàn:

```
Priority 5: Quality NCR module + dashboard
Priority 6: Asset workflow + GL integration
Priority 7: Finance financial reports (Trial Balance, P&L, BS)
Priority 8: AR Invoice controller + AR module
```

### Giai đoạn 3 — Oracle compliance (ongoing)
Cross-module Oracle process alignment:

```
- Universal DocumentSequenceService adoption
- GL Period enforcement everywhere  
- WorkflowEngine adoption for all document approvals
- Multi-currency in Sales
- Attachment system standardization
```

---

## VII. TEMPLATE CODE — MODULE MỚI

### Khởi tạo nhanh cho module mới (copy từ Purchasing pattern):

```bash
# 1. Controller
cp app/controllers/purchasing/PurchaseOrderController.php app/controllers/{module}/{Entity}Controller.php

# 2. Model (với status constants)
cp app/models/purchasing/PurchaseOrder.php app/models/{module}/{Entity}.php

# 3. Services
cp app/services/purchasing/PurchaseOrderService.php app/services/{module}/{Entity}Service.php
cp app/services/purchasing/PurchaseOrderWorkflowService.php app/services/{module}/{Entity}WorkflowService.php

# 4. Views directory
mkdir app/views/{module}/{entity}

# 5. JS files
cp public/js/modules/purchasing/purchase_order.js public/js/modules/{module}/{entity}.js
cp public/js/modules/purchasing/purchase_order_show.js public/js/modules/{module}/{entity}_show.js
```

### Oracle Status Flow — Chuẩn cho mọi document:
```
DRAFT → PENDING_APPROVAL → APPROVED → [PARTIAL_FULFILLED] → CLOSED
                         ↘ REJECTED → DRAFT (recall for edit)
DRAFT/PENDING → CANCELLED (nếu chưa có transaction)
```

---

## VIII. THEO DÕI TIẾN ĐỘ

### Cập nhật file này sau mỗi sprint:

| Date | Module | Task | Status | Dev |
|------|--------|------|--------|-----|
| 01/04/2026 | ALL | Initial audit | ✅ Done | - |
| Session 2 | Production | WO show partials (4 partials + workorder_show.js) | ✅ Done | AI |
| Session 3 | Production | WO show 5 tabs + print_traveler.php | ✅ Done | AI |
| Session 4 | Production | BOM show partials (4 files) | ✅ Done | AI |
| Session 5 | Production | BOM show.php integration + refactor | ✅ Done | AI |
| Session 8 | Sales | Show partials + mobile + export | ✅ Done | AI |
| Session 9 | Sales | SQ export + final 100% | ✅ Done | AI |
| Session 8 | Finance | AP/AR workflow + email + export + import | ✅ Done | AI |
| Session 9 | Finance | AR workflow + mobile + final 100% | ✅ Done | AI |
| 2026-04-22 | Finance | Parity w/ Purchasing: `ApInvoiceService`, `ArInvoiceService`, `ArInvoiceImportService` + import wizard + refactor 2 controllers (396→300L, 368→340L) | ✅ Done | AI |
| Session 11 | Production | Plan show partials (3 files, 643→164 lines) | ✅ Done | AI |
| Session 11 | Production | Mobile views (WO + Plan index_mobile.php) | ✅ Done | AI |
| Session 11 | Production | WorkOrderExportService (14-col Excel) | ✅ Done | AI |
| Session 11 | Production | WorkOrderImportService (preview + template) | ✅ Done | AI |
| Session 11 | Production | Menu restructure (Production 6 groups + Finance 5 groups) | ✅ Done | AI |
| - | Production | Report Controller + BOM Export | 🔲 Todo | - |
| - | Quality | NCR module | 🔲 Todo | - |
| - | HR | Payroll export + mobile views | 🔲 Todo | - |
| - | Asset | WorkflowService + GL | 🔲 Todo | - |
| Session 19 | Inventory | Show Partials refactoring (14 entities, 83 files) | ✅ Done | AI |
| Session 20 | Inventory | Dashboard (Helper 14 KPIs + Controller + View + Permission + Menu) | ✅ Done | AI |
| Session 20 | Inventory | Mobile views (4 show_mobile.php + 4 controllers wired) | ✅ Done | AI |
| Session 33-34 | Inventory | Hardcode Elimination Phase 2: Views (50+ files, 180+ replacements → InventoryConstants) | ✅ Done | AI |
| Session 35 | Inventory | Hardcode Elimination Phase 3: Models magic numbers (6 files, 22 replacements) | ✅ Done | AI |
| Session 35 | Inventory | Dead code cleanup (14 show_old.php + 1 .php.new + 1 empty dir) | ✅ Done | AI |
| Session 35 | ALL | Asset_v audit — 100% compliant (249 calls, 0 violations) | ✅ Done | AI |
| Session 37 | Sales | GoLive Phase 3: 15 deleted_at fixes + 12 FOR UPDATE locks (8 files) | ✅ Done | AI |
| Session 37 | ALL | Full project audit + documentation update (ROADMAP, PLAYBOOK, AGENTS.md) | ✅ Done | AI |
| Session 38 | ALL | **Site Isolation Audit** — ~95 raw SQL queries fixed across 45 files (see §V below) | ✅ Done | AI |
| Session 39 | ALL | **PDO Placeholder Reuse Audit** — 16 files fixed (duplicate `:kw`/`:uid`/`:date` re-binds) + AR dashboard schema fix (`due_date`→`invoice_date`) | ✅ Done | AI |

---

## V. SITE ISOLATION SECURITY AUDIT (Session 38)

> **Ngày**: Session 38 | **Phạm vi**: Toàn bộ hệ thống | **Loại**: Security — Multi-tenant data leak prevention

### Tóm tắt

Phát hiện ~95 raw SQL queries trong controllers/services **thiếu `site_id` WHERE clause**, cho phép cross-site data leakage trong môi trường multi-tenant. Tất cả đã được fix và syntax-verified.

### Nguyên nhân gốc

- `BaseModel` tự động filter `site_id` khi `$isSiteSpecific = true`, nhưng **raw SQL queries** trong controllers/services bypass cơ chế này
- Products dùng `product_site_assignments` JOIN (không có `site_id` trực tiếp trên bảng)
- Partners dùng `partner_site_assignments` JOIN

### Files Fixed (45 files)

#### Controllers (16 files)
| File | Module | Queries Fixed | Pattern |
|------|--------|--------------|---------|
| `hr/EmployeeController.php` | HR | 3 | `e.site_id = :site_id` in $whereSQL |
| `hr/EmployeeImportController.php` | HR | 2 | Auto-code + department lookup |
| `hr/LeaveRequestController.php` | HR | 4 | Employee lookups |
| `hr/PayrollController.php` | HR | 3 | Payroll periods + slips |
| `hr/AttendanceController.php` | HR | 7 | Adjustments + timesheets + leave_ledger |
| `hr/EmailApprovalController.php` | HR | 1 | Dynamic table lookup |
| `core/ApprovalController.php` | Core | 1 | workflow_instances |
| `core/UsersController.php` | Core | 1 | employees lookup |
| `portal/ProfileController.php` | Portal | 1 | employees UPDATE |
| `pm/TaskController.php` | PM | 1 | employees lookup |
| `api/ProductSearchController.php` | API | 1 | product_site_assignments JOIN |
| `masterdata/PartnerMappingController.php` | Master Data | 1 | product_site_assignments JOIN |
| `masterdata/ProductCategoriesController.php` | Master Data | 1 | product_site_assignments JOIN |
| `quality/QaSpecificationController.php` | Quality | 2 | partner_site_assignments JOIN |
| `systems/MisaSyncController.php` | Systems | 1 | product_site_assignments JOIN |

#### Services (29 files)
| File | Module | Queries Fixed | Pattern |
|------|--------|--------------|---------|
| `hr/PayrollCalculator.php` | HR | 5 | employees + contracts + timesheets + shifts |
| `hr/AttendanceCalculator.php` | HR | 3 | timesheets + work_shifts |
| `hr/AttendanceTypeResolver.php` | HR | 1 | attendance_configurations |
| `hr/LeaveRequestWorkflowService.php` | HR | 1 | lockAndLoad + site_id |
| `hr/OvertimeRequestWorkflowService.php` | HR | 1 | lockAndLoad + site_id |
| `hr/AttendanceAdjustmentWorkflowService.php` | HR | 2 | lockAndLoad + timesheets |
| `hr/AdjustmentEmailService.php` | HR | 1 | getEmployeeUserId |
| `hr/LeaveEmailService.php` | HR | 1 | getEmployeeUserId |
| `hr/OvertimeEmailService.php` | HR | 1 | getEmployeeUserId |
| `inventory/InventoryShippingService.php` | Inventory | 8 | gi_requests + transactions + warehouses + delivery_shipments |
| `inventory/LotGenerationService.php` | Inventory | 4 | product_lots + product_serials |
| `inventory/TripService.php` | Inventory | 3 | gi_requests + wsh_trips |
| `inventory/MaterialIssueService.php` | Inventory | 1 | wip_material_requisitions |
| `inventory/OpeningStockTemplateService.php` | Inventory | 1 | partner_site_assignments JOIN |
| `production/BomExplosionService.php` | Production | 3 | boms + cache DELETE + cache SELECT |
| `production/BomWorkflowService.php` | Production | 1 | getBom with siteId |
| `production/BomService.php` | Production | 1 | work_orders count |
| `production/WipMoveService.php` | Production | 1 | work_orders |
| `production/WipCompletionService.php` | Production | 2 | work_order_materials JOIN |
| `production/WorkOrderCloseService.php` | Production | 1 | work_order_materials JOIN |
| `quality/QcTypeService.php` | Quality | 2 | specifications + inspections |
| `quality/QcStatusService.php` | Quality | 1 | inspections |
| `quality/DefectService.php` | Quality | 1 | inspection_defects |
| `quality/QaInspectionImportService.php` | Quality | 3 | Products + QC types + inspectors cache |
| `quality/QaInspectionWorkflowService.php` | Quality | 2 | inspection_results SELECT + UPDATE |
| `finance/AutoAccounting.php` | Finance | 2 | partners + inventory_transactions |
| `finance/JournalEntryImportService.php` | Finance | 1 | partner_site_assignments JOIN |
| `asset/AssetEmailService.php` | Asset | 1 | employees email |
| `sales/SalesQuoteService.php` | Sales | 1 | employees department |
| `purchasing/DocumentFlowService.php` | Purchasing | 5 | PO + GRN + AP Invoice + RTV queries |

### Fix Patterns Used

| Pattern | When Used | Example |
|---------|-----------|---------|
| `AND site_id = :sid` | Table has direct `site_id` column | `employees`, `work_orders`, `boms` |
| `INNER JOIN product_site_assignments psa ON p.id = psa.product_id AND psa.site_id = :sid` | Products (no direct site_id) | ProductSearchController, MisaSyncController |
| `INNER JOIN partner_site_assignments psa ON p.id = psa.partner_id AND psa.site_id = :sid` | Partners (site via assignment table) | QaSpecificationController, OpeningStockTemplateService |
| `INNER JOIN parent_table ON child.fk = parent.id AND parent.site_id = :sid` | Child table without site_id | `work_order_materials` → JOIN `work_orders` |
| `(site_id IS NULL OR site_id = :sid)` | Nullable site_id (global + site-specific) | `qc_types` |

### Verification

- All 45 files passed `php -l` syntax check
- Bind patterns: `$this->getCurrentSiteId()` (controllers), `(int)($_SESSION['user_site_id'] ?? 0)` (services)

---

---

## VI. PDO PLACEHOLDER REUSE AUDIT (Session 39 — 2026-04-21)

> **Ngày**: 2026-04-21 | **Trigger**: Finance Dashboard 3 Fatal errors | **Loại**: Stability — Prepared statement compatibility

### Root Cause

Hệ thống chạy với `PDO::ATTR_EMULATE_PREPARES => false` (server-side prepares). Trong chế độ này MariaDB/MySQL **KHÔNG cho phép reuse cùng 1 named placeholder** nhiều lần trong câu SQL — khác với emulated mode đã từng cho phép. Phải đặt tên unique cho mỗi lần bind (`:kw1`, `:kw2`, ...).

### Schema Discovery

- Bảng `ar_invoices` **không có** cột `due_date` — chỉ có `invoice_date`. `FinanceDashboardHelper::getArAging()` tham chiếu sai cột → Fatal error. Đã đổi toàn bộ sang `invoice_date` với Net30 buckets.

### Files Fixed (16)

| # | File | Module | Placeholder | Notes |
|---|------|--------|-------------|-------|
| 1 | `helpers/finance/FinanceDashboardHelper.php` | Finance | `due_date` → `invoice_date` | + cleanup duplicate SELECT block |
| 2 | `models/finance/ApReportModel.php` | Finance | `:rpt_date ×8+×3` → `:rpt1…:rpt11` | Aging + ledger reports |
| 3 | `models/finance/ArReportModel.php` | Finance | `:rpt_date ×8+×4` → `:rpt1…:rpt12` | Aging + aging by customer |
| 4 | `models/production/WipMoveModel.php` | Production | `:kw ×3 queries` → `:kw1`/`:kw2`/`:kw3` | WO search filter |
| 5 | `services/production/WipMoveService.php` | Production | `:wo_id` dual-use | Split into `:wo_id1`/`:wo_id2` |
| 6 | `services/inventory/InventoryShippingService.php` | Inventory | `:uid ×2` | Operator + approver lookup |
| 7 | `models/hr/AttendanceModel.php` | HR | `:approver_id ×2`, `:work_date` dual | Adjustment + timesheet reports |
| 8 | `services/masterdata/UomUnitService.php` | Master Data | `:id` dual-use in reference check | Split to `:id1`/`:id2` |
| 9 | `models/costing/CostTypeModel.php` | Costing | `:uid` dual-use | Created_by + updated_by |
| 10 | `helpers/sales/SalesValidationHelper.php` | Sales | `:uom_id ×2` | UOM compatibility check |
| 11 | `services/sales/SalesATPService.php` | Sales | `:qty` dual-use | Reservation + availability calc |
| 12 | `models/finance/TaxModel.php` | Finance | `:tdate` dual-use | Tax rate effective date |
| 13 | `models/masterdata/UomConversionModel.php` | Master Data | `:id` dual-use | Conversion reference |
| 14 | `models/quality/QaSamplingPlanModel.php` | Quality | `:lot_size` dual-use | AQL sampling lookup |
| 15 | `models/quality/QaSpecificationHeaderModel.php` | Quality | `:date` dual-use | Effective date range |
| 16 | `models/sales/SalesOrderModel.php` | Sales | `:amount` (dead code path) | Defensive fix |

### Pattern Applied

```php
// ❌ TRƯỚC: Cùng placeholder dùng 2 lần — Fatal SQLSTATE[HY093]
$sql = "WHERE (code LIKE :kw OR name LIKE :kw OR ref LIKE :kw)";
$this->db->bind(':kw', $kw);

// ✅ SAU: Mỗi lần xuất hiện có tên riêng
$sql = "WHERE (code LIKE :kw1 OR name LIKE :kw2 OR ref LIKE :kw3)";
$this->db->bind(':kw1', $kw);
$this->db->bind(':kw2', $kw);
$this->db->bind(':kw3', $kw);
```

### Verification

- Tất cả 16 files pass `php -l`
- Live DB check (`103.200.23.139/erpmesco_erp_test`): 9 tables/columns referenced all exist → **NO migration cần thiết**
- Schema audit: `ar_invoices` column list verified via `information_schema.columns` — xác nhận không có `due_date`

---

*Tài liệu này được tạo tự động bởi AI scan ngày 01/04/2026. Cập nhật lần cuối: Session 39 (2026-04-21) — PDO placeholder reuse audit + AR dashboard schema fix.*
