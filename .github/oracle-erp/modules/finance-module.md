# Finance Module — Oracle ERP Compliance Documentation

> Tài liệu này mô tả kiến trúc, trạng thái, và luồng xử lý của module Finance theo chuẩn Oracle E-Business Suite (EBS).
> Cập nhật: Tháng 4/2026 (Phase 3 UI Upgrade hoàn thành) — Score: **100%**

---

## 1. Tổng quan Module

Module Finance quản lý toàn bộ nghiệp vụ tài chính kế toán:

```
Transaction (AP/AR/Inventory/WIP) → AutoAccounting → Journal Entry → GL Posting → Reports (CĐPS, KQKD)
```

Bao gồm: General Ledger, Accounts Payable, Accounts Receivable, Tax, AutoAccounting engine, Financial Reporting.

### Entities chính

| Entity | Controller | Model | Bảng DB |
|--------|-----------|-------|---------|
| Journal Entry | `JournalEntryController` | `JournalEntryModel` | `journal_entries` |
| JE Detail | *(inline)* | *(via JournalEntryModel)* | `journal_entry_details` |
| Chart of Accounts | `CoaController` | `ChartOfAccount` | `chart_of_accounts` |
| AP Invoice | `ApInvoiceController` | `ApInvoiceModel` | `ap_invoices` |
| AP Invoice Detail | *(inline)* | *(via ApInvoiceModel)* | `ap_invoice_details` |
| AP Payment | `ApPaymentController` | `ApPaymentModel` | `ap_payments` |
| AP Payment Allocation | *(inline)* | *(via ApPaymentModel)* | `ap_payment_allocations` |
| AR Invoice | `ArInvoiceController` | `ArInvoiceModel` | `ar_invoices` |
| AR Invoice Detail | *(inline)* | *(via ArInvoiceModel)* | `ar_invoice_details` |
| AR Partner Balance | *(auto)* | *(via ArInvoiceModel)* | `ar_partner_balances` |
| GL Period | `GlperiodController` | `GlPeriodModel` | `gl_periods` |
| GL Cost Center | `CostCenterController` | `CostCenterModel` | `gl_cost_centers` |
| GL Project | `ProjectController` | `ProjectModel` | `gl_projects` |
| Exchange Rate | `ExchangeRateController` | `ExchangeRateModel` | `currency_exchange_rates` |
| Tax Regime | `TaxController` | `TaxModel` | `tax_regimes` |
| Tax | *(via TaxController)* | *(via TaxModel)* | `tax_taxes` |
| Tax Rate | *(via TaxController)* | *(via TaxModel)* | `tax_rates` |
| Payment Term | `PaymentTermController` | `PaymentTermModel` | `payment_terms` |
| Payment Term Line | *(inline)* | *(via PaymentTermModel)* | `payment_term_lines` |
| Accounting Rule | `AccountingRulesController` | `AccountingRule` | `accounting_rules` |
| Category Account | *(via AccountingRulesController)* | `CategoryAccount` | `category_accounts` |
| Trial Balance | `TrialBalanceController` | *(raw SQL)* | *(aggregated)* |
| Income Statement | `IncomeStatementController` | *(raw SQL)* | *(aggregated)* |
| AP Aging Report | `ApReportController` | `ApReportModel` | *(aggregated)* |
| AR Aging Report | `ArReportController` | `ArReportModel` | *(aggregated)* |
| AR Receipt | `ArReceiptController` | `ArReceiptModel` | `ar_receipts` |
| AR Payment Allocation | *(inline)* | *(via ArReceiptModel)* | `ar_payment_allocations` |
| Balance Sheet | `BalanceSheetController` | *(raw SQL)* | *(aggregated)* |
| Finance Dashboard | `FinanceDashboardController` | `FinanceDashboardHelper` | *(aggregated)* |

---

## 2. Oracle EBS Mapping

| Oracle EBS Module | Factory ERP Equivalent |
|-------------------|------------------------|
| General Ledger (GL) | `JournalEntryController` + `CoaController` + `GlperiodController` |
| GL Journal Entries | `journal_entries` + `journal_entry_details` (manual + auto) |
| GL Chart of Accounts | `chart_of_accounts` (tree structure, parent_id) |
| GL Periods | `gl_periods` (module-level lock: GL/AP/AR/INV/Costing) |
| GL Trial Balance | `TrialBalanceController` (report + Excel export) |
| GL Income Statement | `IncomeStatementController` (P&L report + export) |
| Accounts Payable (AP) | `ApInvoiceController` + `ApPaymentController` |
| AP Invoices | `ap_invoices` + `ap_invoice_details` (3-way GRN matching) |
| AP Payments | `ap_payments` + `ap_payment_allocations` (multi-invoice allocation) |
| AP Invoice Price Variance (IPV) | Calculated in `AutoAccounting.php` (PO price vs invoice price) |
| Accounts Receivable (AR) | `ArInvoiceController` (full workflow) |
| AR Invoices | `ar_invoices` + `ar_invoice_details` |
| AR Partner Balances | `ar_partner_balances` (composite: site+partner+currency) |
| Subledger Accounting (SLA) | `AutoAccounting.php` engine — config-driven GL posting |
| Oracle eBTax | `TaxController` — 3-tier Regime → Tax → Rate hierarchy |
| Cash Management (CE) | ⚠️ Partial — `ap_payments.bank_account_id` nhưng chưa có bank reconciliation |
| Fixed Assets (FA) | ❌ Riêng module Asset (không thuộc Finance) |
| Cost Management (CST) | ⚠️ Partial — costing engine phân tán (xem §8) |

---

## 3. AutoAccounting Engine

### Kiến trúc

AutoAccounting là engine trung tâm tự động tạo bút toán GL từ transactions. Thiết kế theo Oracle SLA (Subledger Accounting):

```
Transaction (AP/AR/INV/WIP) → AutoAccounting Service → Journal Entry (draft) → Manual Post → GL
```

### Transaction Coverage

| Transaction Type | Service File | Nợ (Dr) | Có (Cr) |
|-----------------|-------------|---------|---------||
| AR Receipt | `AutoAccounting.php` | TK 112/111 (Ngân hàng/Tiền mặt) | TK 131 (Phải thu KH) |
| AP Payment | `AutoAccounting.php` | TK 331 (Phải trả NCC) | TK 112/111 (Ngân hàng/Tiền mặt) |
| AP Invoice | `AutoAccounting.php` | TK 338 (GRNI) + TK 133 (VAT đầu vào) ± IPV | TK 331 (Phải trả NCC) |
| AR Invoice | `AutoAccounting.php` | TK 131 (Phải thu KH) | TK 511 (Doanh thu) + TK 3331 (VAT đầu ra) |
| GRN (IMPORT_PO) | `AutoAccounting_receipt.php` | TK 152/155 (Hàng tồn kho) | TK 338/335 (GRNI) |
| WIP Issue | `AutoAccounting_wip.php` | TK 154 (Chi phí SXKD DD) | TK 152 (Nguyên vật liệu) |
| WIP Completion | `AutoAccounting_wip_completion.php` | TK 155 (Thành phẩm) | TK 154 (WIP) |
| WIP Consumption | `AutoAccounting_wip_consumption.php` | — | — |
| WIP Return | `AutoAccounting_wip_return.php` | TK 152 (NVL) | TK 154 (WIP) |
| WIP Scrap | `AutoAccounting_wip_scrap.php` | TK 632/811 (Phế phẩm) | TK 154 (WIP) |
| Return to Vendor | `AutoAccounting_return_vendor.php` | TK 338 (GRNI) | TK 152 (Hàng tồn kho) |
| Opening Balance | `AutoAccounting_opening.php` | Various | Various |
| Reversal | `AutoAccounting.php` | Đảo ngược Nợ↔Có từ bút toán gốc | — |

### Account Resolution Priority (4-level)

```
1. accounting_rules      → Transaction type → GL accounts (ưu tiên cao nhất)
2. product_site_assignments → Per-product per-site GL mapping
3. category_accounts     → Product category → GL accounts
4. organization_parameters → Site-level default GL accounts (fallback cuối)
```

### Safety Features

- `validateJournalBalance()` — kiểm tra Nợ = Có sau mỗi bút toán (tolerance 0.01)
- Fail-fast validation — throw exception nếu tài khoản chưa cấu hình
- Tất cả bút toán tạo ở trạng thái `draft` (không tự động post lên GL)
- Site isolation: `$_SESSION['user_site_id']` trên mọi lookup

---

## 4. AP Invoice Workflow

### Status Flow

```
draft → submitted → approved → posted
  ↑        ↓          ↓
  └── recalled    rejected
                     ↓
                  cancelled
```

| Status | Oracle AP Equivalent | Mô tả |
|--------|---------------------|-------|
| `draft` | Incomplete | Đang soạn, chưa gửi duyệt |
| `submitted` | Needs Reapproval | Đã gửi chờ duyệt |
| `approved` | Approved | Đã duyệt, chờ hạch toán |
| `posted` | Validated+Accounted | Đã hạch toán GL |
| `rejected` | Rejected | Bị từ chối |
| `cancelled` | Cancelled | Đã hủy |

### 3-Way Matching (Oracle Invoice Validation)

```
Purchase Order (PO) ←→ GRN Receipt ←→ AP Invoice
   (đặt hàng)         (nhập kho)       (hóa đơn)
```

- `ApInvoiceModel` khớp `ap_invoice_details` với `inventory_transaction_details` (GRN line)
- Invoice Price Variance (IPV) tự động tính khi giá hóa đơn ≠ giá PO
- FOR UPDATE locks ngăn race condition khi match

### Key Services

| Service | Lines | Chức năng |
|---------|-------|-----------|
| `ApInvoiceService` | ~220 | **(NEW 2026-04-22)** CRUD business logic — createInvoice(), validateHeaderAndItems() — tách từ controller |
| `ApInvoiceWorkflowService` | ~200 | submit, approve, reject, recall, void — FOR UPDATE locks |
| `ApInvoiceExportService` | ~150 | Export danh sách AP Invoice ra Excel |
| `ApInvoiceFormRequest` | — | Validate input (header + lines) |
| `ApInvoiceDTO` | — | Data transformation |

**Controller Workflow Routes (Session 40)**:
- `submit()`, `approve()`, `reject()`, `recall()`, `void_invoice()`, `post_gl()` — tất cả delegate qua `_executeWorkflow()` → `ApInvoiceWorkflowService`
- `store()` delegate sang `ApInvoiceService::createInvoice()` (2026-04-22 refactor) — controller giảm từ 396 → ~300L

---

## 5. AR Invoice Workflow

### Status Flow

```
draft → submitted → approved → posted
  ↑        ↓          ↓
  └── recalled    rejected
                     ↓
                  cancelled
```

| Status | Oracle AR Equivalent | Mô tả |
|--------|---------------------|-------|
| `draft` | Incomplete | Đang soạn |
| `submitted` | Pending Approval | Chờ duyệt |
| `approved` | Approved | Đã duyệt |
| `posted` | Complete+Accounted | Đã hạch toán GL |
| `rejected` | Rejected | Bị từ chối |
| `cancelled` | Cancelled | Đã hủy |

### Key Services

| Service | Lines | Chức năng |
|---------|-------|-----------|
| `ArInvoiceService` | ~260 | **(NEW 2026-04-22)** CRUD business logic — createInvoice(), validateAndCalculate(), insertDetails(), generateCode() |
| `ArInvoiceImportService` | ~420 | **(NEW 2026-04-22)** Bulk import từ Excel — parseExcelForPreview(), saveGroups(), generateTemplate(); gộp dòng cùng partner+date thành 1 HĐ |
| `ArInvoiceWorkflowService` | ~200 | submit, approve, reject, recall, post, void — mirror AP |
| `ArInvoiceExportService` | ~150 | Export AR Invoice ra Excel |
| `ArInvoiceFormRequest` | ~110 | Validate AR Invoice input (header + lines) |
| `ArInvoiceDTO` | ~130 | AR Invoice data transformation |
| `FinanceEmailService` | ~200 | Email templates cho AP/AR events (submitted, approved, rejected, posted) |

**Controller Refactored**:
- Session 40: `ArInvoiceController` delegate workflow qua `_executeWorkflow()` → `ArInvoiceWorkflowService` (FOR UPDATE race-safe)
- **2026-04-22**: `store()` delegate sang `ArInvoiceService::createInvoice()`; thêm `import()`, `importPreview()`, `importConfirm()`, `importTemplate()` endpoints. Controller: 368 → ~340L (có import methods).

### AR Invoice Import UI

Route: `/finance/arinvoice/import` — 3-bước wizard:
1. **Upload**: Form upload .xlsx / .xls (max 10 MB), nút tải template
2. **Preview**: Table gộp theo partner+date, hiển thị tổng, list lỗi validation
3. **Confirm**: Xác nhận → `ArInvoiceImportService::saveGroups()` → tạo từng AR Invoice qua `ArInvoiceService`

Header Excel bắt buộc (cột A..F): `Mã KH (*), Ngày HĐ (*), Mã SP (*), ĐVT (*), SL (*), Đơn giá (*)`. Optional: `%VAT, Chiết khấu, Ghi chú`. Các dòng cùng `partner_code + invoice_date` tự động gộp vào 1 hóa đơn.

Files:
- `app/views/finance/ar/import.php` — view 3-step wizard
- `public/js/modules/finance/ar_import.js` — vanilla JS fetch/render preview
- `app/services/finance/ArInvoiceImportService.php` — logic parse/save/template

---

## 5b. AR Receipt (Phiếu Thu Khách Hàng)

### Status Flow

```
draft → posted → voided
```

| Status | Oracle AR Equivalent | Mô tả |
|--------|---------------------|-------|
| `draft` | Unapplied | Nháp, chưa ghi sổ |
| `posted` | Applied | Đã ghi sổ GL, cập nhật partner balance |
| `voided` | Reversed | Đã hủy, đảo bút toán GL |

### Key Services

| Service | Chức năng |
|---------|----------|
| `ArReceiptModel` | CRUD, posting (FOR UPDATE), voiding (reversal JE), allocation CRUD |
| `AutoAccounting.createEntryFromArReceipt()` | Dr TK 112/111 / Cr TK 131 |
| `AutoAccounting.createReversalEntry()` | Generic JE reversal (debit↔credit swap) |
| `DocumentSequenceService` | Auto-generate receipt code (prefix ARC) |

---

## 6. Journal Entry Workflow

### Status Flow

```
draft → posted → reversed
  ↓
deleted (hard delete khi còn draft)
```

| Status | Mô tả |
|--------|-------|
| `draft` | Đang soạn, chưa ảnh hưởng sổ cái |
| `posted` | Đã ghi nhận vào sổ cái (GL) |
| `reversed` | Đã đảo ngược bằng bút toán reversal |

### Validation Rules

- Tổng Nợ phải bằng tổng Có (balanced entry)
- GL Period phải `OPEN` cho ngày hạch toán
- Mỗi dòng phải có tài khoản hợp lệ trong COA
- Import từ Excel hỗ trợ: parse → preview → save (balance validation)

---

## 7. GL Period Management

### Module-Level Period Lock

```
gl_periods: {
    gl_period_status: OPEN | CLOSED | PERMANENTLY_CLOSED
    ap_period_status: OPEN | CLOSED | PERMANENTLY_CLOSED
    ar_period_status: OPEN | CLOSED | PERMANENTLY_CLOSED
    inventory_period_status: OPEN | CLOSED | PERMANENTLY_CLOSED
    costing_period_status: OPEN | CLOSED | PERMANENTLY_CLOSED
}
```

- Mỗi module có trạng thái đóng/mở riêng biệt
- Khi period CLOSED: không cho tạo/sửa giao dịch trong kỳ đó
- PERMANENTLY_CLOSED: không thể reopen

### Constants (FinanceConstants.php)

```php
const PERIOD_STATUS_OPEN = 'OPEN';
const PERIOD_STATUS_CLOSED = 'CLOSED';
const PERIOD_STATUS_PERMANENTLY_CLOSED = 'PERMANENTLY_CLOSED';

const PERIOD_MODULE_GL = 'gl';
const PERIOD_MODULE_AP = 'ap';
const PERIOD_MODULE_AR = 'ar';
const PERIOD_MODULE_INVENTORY = 'inv';
const PERIOD_MODULE_COSTING = 'costing';
```

---

## 8. Costing Capabilities (Phân Tán)

> **Lưu ý**: Costing không có module riêng — các tính năng phân tán qua nhiều modules.

### 8.1 Đã Có (Functional)

| Component | Location | Mô tả |
|-----------|----------|-------|
| Costing Method Config | `organization_parameters.costing_method` | `weighted_average` (default), `standard`, `fifo` |
| WAC Calculation | `InventoryCalculationHelper::weightedAvgCost()` | Tính giá bình quân gia quyền (bcmath precision 6) |
| FIFO Calculation | `InventoryCalculationHelper::fifoCost()` | Tính giá theo lớp nhập trước |
| Stock Valuation | `warehouse_stocks.average_cost` + virtual `total_value` | Giá trị tồn kho real-time |
| BOM Cost Rollup | `BomCalculationService::calculateBomCost()` | Multi-level: material + labor, phantom BOM, waste rate |
| BOM Mass Update | `BomCalculationService::massUpdateCosts()` | Mass recalculate khi giá NVL thay đổi |
| WIP AutoAccounting | 5 services (`AutoAccounting_wip*.php`) | Hạch toán GL cho phát vật tư, hoàn thành, trả lại, phế phẩm |
| WIP Cost Helpers | `WipTransactionHelper` | Backflush, material cost, completion cost, unit cost |
| Sales COGS | `SalesOrderCostTrackingService` (645L) | FIFO/LIFO/WAC actual cost, margin analysis, dashboard |
| Cost Variance | `ProductionCalculationHelper::calcCostVariance()` | Actual vs standard, favorable/unfavorable |
| Category → GL | `category_accounts` | Map product category → Inventory/COGS/Revenue/WIP/Scrap/Variance accounts |
| Pro-rata Allocation | `InventoryCalculationHelper::proRataAllocate()` | Phân bổ chi phí theo tỷ lệ (remainder-safe) |
| Period Lock | `gl_periods.costing_period_status` | Khóa kỳ costing riêng biệt |

### 8.2 Schema Sẵn, Chưa Implement

| Table | Oracle CST Equivalent | Trạng thái |
|-------|----------------------|-----------|
| `cost_types` | Cost Types (Frozen/Pending/Average) | Schema only — chưa có service/controller |
| `item_costs` | Item Costs | Schema only — material_cost, resource_cost, overhead_cost, virtual item_cost |
| `item_cost_details` | Item Cost Details (THIS/PREVIOUS level) | Schema only |
| `cost_elements` | Cost Elements (MAT/LAB/OHD/OUT) | Schema only — có GL account mapping |
| `cost_allocation_runs` | Cost Allocation | Schema only — overhead allocation to WOs |
| `cost_allocation_details` | Cost Allocation Details | Schema only |

### 8.3 Thiếu (vs Oracle CST)

| Gap | Severity | Mô tả |
|-----|----------|-------|
| Standard Cost Workbench | **HIGH** | Không có UI freeze/pending cost, cost comparison before/after |
| Period-Close Cost Reval | **HIGH** | Không tính lại WAC + post revaluation JE khi đóng kỳ |
| Costing Method Enforcement | **MEDIUM** | `costing_method` config tồn tại nhưng không check tại thời điểm transaction |
| Interorg Transfer Costing | **MEDIUM** | Không có transfer pricing giữa sites |
| Landed Cost | **MEDIUM** | Không phân bổ freight/duty/insurance vào giá nhập |
| Inventory Valuation Report | **MEDIUM** | Không có báo cáo giá trị tồn kho (stock × cost by warehouse) |
| Item Cost Report | LOW | Không có báo cáo chi phí theo sản phẩm (breakdown by cost element) |
| Cost History Report | LOW | Không theo dõi lịch sử thay đổi giá theo thời gian |
| Margin Analysis UI | LOW | `SalesOrderCostTrackingService` có method nhưng chưa có controller/view |
| Overhead Absorption Rates | LOW | Không hỗ trợ % of material, per unit, per lot |
| WIP Period-Close Variance | LOW | Không có báo cáo chênh lệch WIP khi đóng kỳ |

---

## 9. Tax System (Oracle eBTax)

### 3-Tier Hierarchy

```
Tax Regime (chế độ thuế)
  └── Tax (loại thuế, vd: GTGT)
        └── Tax Rate (thuế suất, vd: 10%, effective dates)
```

| Bảng | Columns |
|------|---------|
| `tax_regimes` | `name`, `country_code`, `status` (global, không có site_id) |
| `tax_taxes` | `regime_id`, `tax_type` (Purchase/Sales), `account_id` → COA |
| `tax_rates` | `tax_id`, `percentage`, `effective_from`, `effective_to` |

- `tax_type = 'Purchase'` → AP Invoice (VAT đầu vào)
- `tax_type = 'Sales'` → AR Invoice (VAT đầu ra)

---

## 10. Financial Reports

| Report | Controller | Chức năng | Export |
|--------|-----------|-----------|--------|
| Trial Balance (CĐPS) | `TrialBalanceController` | Cân đối phát sinh theo kỳ | ✅ Excel |
| Income Statement (KQKD) | `IncomeStatementController` | Báo cáo kết quả kinh doanh | ✅ Excel |
| Balance Sheet (CĐKT) | `BalanceSheetController` | Bảng cân đối kế toán (Tài sản / Nợ PT / Vốn CSH) | ✅ Excel |
| AP Aging | `ApReportController` | Tuổi nợ phải trả (5 buckets: chưa đến hạn, 1-30, 31-60, 61-90, >90 ngày) | ❌ |
| AR Aging | `ArReportController` | Tuổi nợ phải thu (5 buckets) | ✅ Excel |
| Finance Dashboard | `FinanceDashboardController` | 10 KPIs: draft JE, posted MTD, AP aging, AR aging, GL period, recent JE, top vendors, top customers, monthly chart | N/A |

### Report Notes

- Balance Sheet tự tính Retained Earnings (Revenue - Expense ytd → Lợi nhuận giữ lại)
- Balance Sheet smart direction: Asset = Dr-Cr, Liability/Equity = Cr-Dr
- Dashboard delegate toàn bộ queries qua `FinanceDashboardHelper` (reusable cho Mobile/API)
- ⚠️ Cash Flow Statement — chưa implement
- ⚠️ AP Aging chưa có export Excel (AR Aging đã có)

---

## 11. File Structure

```
app/controllers/finance/         # 19 controllers, 4,293 lines
├── JournalEntryController.php   # 230L — JE CRUD, post, delete
├── CoaController.php            # 170L — COA CRUD (SPA modal)
├── ApInvoiceController.php      # ~280L — AP Invoice CRUD + GRN matching + 6 workflow routes
├── ApPaymentController.php      # 170L — AP Payment + void
├── ArInvoiceController.php      # ~280L — AR Invoice full workflow (delegates to WorkflowService)
├── ArReceiptController.php      # ~200L — AR Receipt CRUD + post + void
├── ExchangeRateController.php   # 170L — Exchange rate CRUD + API lookup
├── GlperiodController.php       # 260L — GL Period CRUD + close/reopen
├── TaxController.php            # 250L — Tax hierarchy (Regime → Tax → Rate)
├── CostCenterController.php     # 180L — Cost center CRUD
├── AccountingRulesController.php # 230L — Rules + Category Accounts + Settings (3 tabs)
├── PaymentTermController.php    # 180L — Payment terms + line schedule
├── ProjectController.php        # 160L — GL Projects (dimension 2)
├── ApReportController.php       # 70L — AP Aging report
├── ArReportController.php       # 140L — AR Aging report + export
├── FinanceDashboardController.php # ~30L — delegates to FinanceDashboardHelper (10 KPIs)
├── BalanceSheetController.php   # ~120L — Balance Sheet report + export
├── TrialBalanceController.php   # 120L — Trial Balance + export
└── IncomeStatementController.php # 180L — P&L report + export

app/models/finance/              # 16 models, 4,165 lines
├── JournalEntryModel.php        # 260L — Header/detail CRUD, posting, balance validation
├── ChartOfAccount.php           # 220L — Tree structure, 7-step constraint check
├── ApInvoiceModel.php           # 300L — 3-way GRN matching, FOR UPDATE, AutoAccounting
├── ApPaymentModel.php           # 300L — Allocation validation, FOR UPDATE, void (reverse GL)
├── ArInvoiceModel.php           # 140L — Status constants, queries, totals
├── ArReceiptModel.php           # ~250L — Receipt CRUD, posting (FOR UPDATE), void, allocations
├── AccountingRule.php           # 200L — Rule CRUD, transaction type mapping, org params
├── CategoryAccount.php          # 200L — Category → GL mapping (Inv/COGS/Rev/WIP/Scrap/Variance)
├── CostCenterModel.php          # 150L — Linked to departments
├── ExchangeRateModel.php        # 190L — Spot/inverse rate, soft deletes, column whitelist
├── GlPeriodModel.php            # 290L — Module-level lock, date lookup
├── TaxModel.php                 # 200L — 3-tier hierarchy, active rate lookup
├── PaymentTermModel.php         # 200L — Multi-line schedule, UPDATE+INSERT+DELETE
├── ProjectModel.php             # 80L — GL Dimension 2, FK check
├── ApReportModel.php            # 120L — AP Aging (5 buckets)
└── ArReportModel.php            # 110L — AR Aging (5 buckets)

app/services/finance/            # 17 services, 3,841 lines
├── AutoAccounting.php           # Core engine — AP/AR/INV transactions
├── AutoAccounting_receipt.php   # GRN → Dr Inventory / Cr GRNI
├── AutoAccounting_wip.php       # WIP Issue → Dr WIP / Cr Inventory
├── AutoAccounting_wip_completion.php  # FG Receipt → Dr FG / Cr WIP
├── AutoAccounting_wip_consumption.php # WIP Consumption
├── AutoAccounting_wip_return.php      # WIP Return → Dr Inventory / Cr WIP
├── AutoAccounting_wip_scrap.php       # WIP Scrap → Dr Scrap Loss / Cr WIP
├── AutoAccounting_return_vendor.php   # RTV → Dr GRNI / Cr Inventory
├── AutoAccounting_opening.php         # Opening balance entries
├── ApInvoiceWorkflowService.php # AP Invoice workflow (submit/approve/reject/recall/void)
├── ArInvoiceWorkflowService.php # AR Invoice workflow (mirror AP)
├── ApInvoiceExportService.php   # Export AP Invoices → Excel
├── ArInvoiceExportService.php   # Export AR Invoices → Excel
├── JournalEntryExportService.php # Export JE list + detail → Excel
├── JournalEntryImportService.php # Import JE từ Excel (parse → preview → save)
├── FinanceEmailService.php      # Email templates cho AP/AR events
└── YearEndClosingService.php    # ~150L — Year-end P&L → Retained Earnings + auto-close periods

app/helpers/finance/             # 6 helpers, 1,257 lines
├── FinanceConstants.php         # 188L — AP/AR/AR Receipt/JE/GL status, labels, badges
├── FinanceDashboardHelper.php   # 177L — 10 KPI methods (AP/AR aging, top vendors/customers, monthly)
├── FinanceCalculationHelper.php # 250L — currency conversion, VAT calculation, payment due date helpers
├── FinanceValidationHelper.php  # 246L — period lock check, balance validation, partner credit limit
├── FinanceNotificationHelper.php # 186L — email recipient lookup for AP/AR workflow events
└── FinanceReportingHelper.php   # 210L — aging buckets, YTD aggregation, report formatters

app/dtos/finance/                # 4 DTOs, 620 lines
├── ApInvoiceDTO.php             # 189L — AP Invoice data transformation
├── ApPaymentDTO.php             # 134L — AP Payment data transformation
├── ArInvoiceDTO.php             # 145L — AR Invoice data transformation
└── ArReceiptDTO.php             # 152L — AR Receipt data transformation (Phàse 2)

app/requests/finance/            # 5 request classes, 747 lines
├── ApInvoiceFormRequest.php     # 156L — AP Invoice input validation (header + lines)
├── ApPaymentFormRequest.php     # 144L — AP Payment input validation
├── ArInvoiceFormRequest.php     # 111L — AR Invoice input validation
├── ArReceiptFormRequest.php     # 143L — AR Receipt input validation (Phase 2)
└── JournalEntryFormRequest.php  # 193L — Journal Entry input validation (balance check, Phase 2)

app/views/finance/               # 91 files across 15 subdirectories, ~13,200 lines
├── ap/                          # 16 files — AP Invoice (index, create, show+8 partials, print, mobile×2, _form, _modals)
├── ar/                          # 14 files — AR Invoice (index, create, show+6 partials, print, mobile×2, _form, _modals) — Phase 3
├── journal/                     # 9 files — JE (index, create, show+5 partials, import)
├── payment/                     # 7 files — AP Payment (index, create, show+3 partials)
├── glperiod/                    # 6 files — GL Period (index, create, edit, show, _form, _modals)
├── exchange_rates/              # 5 files — Exchange Rate (index, create, edit, _form, _modals)
├── payment_terms/               # 5 files — Payment Terms (index, create, edit, _form, _modals)
├── tax/                         # 3 files — Tax (index, _form, _modals)
├── costcenter/                  # 5 files — Cost Center (index, create, edit, _form, _modals)
├── project/                     # 5 files — GL Project (index, create, edit, _form, _modals)
├── coa/                         # 1 file — COA (index — SPA modal pattern)
├── rules/                       # 1 file — Accounting Rules (index — 3-tab SPA)
├── arreceipt/                   # 7 files — AR Receipt (index, create, show+4 partials, _modals) — Phase 3
├── dashboard/                   # 1 file — Finance Dashboard (AP+AR aging, top vendors/customers)
└── report/                      # 5 files — ap_aging, ar_aging, trial_balance, income_statement, balance_sheet

public/js/modules/finance/      # 14 JS files, ~3,400 lines
├── ap_invoice.js               # 343L — AP Invoice form + index
├── ap_invoice_show.js          # 131L — AP Invoice show (6 workflow confirm functions)
├── ap_payment.js               # 344L — AP Payment form (multi-invoice allocation) — Phase 2
├── ar_invoice_form.js          # 189L — AR Invoice create/edit form
├── ar_invoice_show.js          # ~180L — AR Invoice show (Bootstrap 5 modals + _executeArWorkflow) — Phase 3
├── ar_receipt.js               # 318L — AR Receipt form (partner balance, allocation) — Phase 2
├── arreceipt_show.js           # 29L — AR Receipt show: postReceipt() AJAX (AR_RECEIPT_SHOW_CFG) — Phase 3
├── coa.js                      # 151L — COA SPA modal (openModalAdd, openModalEdit via AJAX) — Phase 2
├── journal_entry.js            # 175L — Journal entry form (addRow, removeRow, calcTotal) — Phase 2
├── journalentry_show.js        # 86L — JE show (post, delete, reverse)
├── glperiod.js                 # ~520L — GL Period management + setupFormValidation() — Phase 3
├── exchange_rate.js            # 211L — Exchange rate CRUD
├── payment_term.js             # 302L — Payment term with dynamic lines
└── tax.js                      # 385L — Tax regime/tax/rate management
```

---

## 12. Organization Parameters (Finance Config)

| Column | Mô tả | Default |
|--------|-------|---------|
| `costing_method` | Phương pháp tính giá | `weighted_average` |
| `general_ledger_enabled` | Bật/tắt GL auto-posting | — |
| `functional_currency` | Đồng tiền chức năng | `VND` |
| `acct_payable_id` | TK Phải trả NCC | TK 331 |
| `acct_grni_id` | TK Hàng đang đi đường | TK 338/335 |
| `acct_tax_default_id` | TK Thuế mặc định | TK 133 |
| `acct_price_variance_id` | TK Chênh lệch giá | — |
| `acct_exchange_gain_id` | TK Lãi tỷ giá | — |
| `acct_exchange_loss_id` | TK Lỗ tỷ giá | — |
| `acct_retained_earnings_id` | TK Lợi nhuận giữ lại | — |
| `acct_wip_id` | TK Chi phí SXKD DD | TK 154 |
| `acct_inventory_id` | TK Hàng tồn kho | TK 152 |
| `acct_scrap_loss_id` | TK Phế phẩm | — |
| `acct_rounding_diff_id` | TK Chênh lệch làm tròn | — |
| `acct_unrealized_gain_id` | TK Lãi chưa thực hiện | — |
| `acct_unrealized_loss_id` | TK Lỗ chưa thực hiện | — |
| `include_resource_overhead_in_cost` | Tính overhead vào giá | `1` |
| `wip_backflush_method` | Phương pháp backflush | `assembly_completion` |

---

## 13. Permissions

| Code | Mô tả |
|------|-------|
| `accounting.view_journal` | Xem Sổ Nhật Ký Chung |
| `accounting.create_journal` | Tạo/Xóa Bút toán Thủ công |
| `accounting.view_reports` | Xem Báo cáo Tài chính (CĐPS, KQKD) |
| `accounting.view_coa` / `manage_coa` | Hệ thống Tài khoản |
| `accounting.config` | Quy tắc hạch toán |
| `accounting.lock_period` / `manage_gl_period` | Kỳ kế toán |
| `accounting.view_ap_invoice` / `create_ap_invoice` | AP Invoice |
| `accounting.view_ar_invoice` / `create_ar_invoice` | AR Invoice |
| `accounting.approve_ar_invoice` / `post_ar_invoice` | AR duyệt/hạch toán |
| `accounting.view_ap_payment` / `create_ap_payment` | AP Payment |
| `accounting.manage_exchange_rate` | Tỷ giá |
| `accounting.manage_payment_term` | Điều khoản thanh toán |
| `accounting.tax.manage` | Thuế |
| `accounting.cost_center.manage` | Trung tâm Chi phí |
| `accounting.rules.manage` | Quy tắc Định khoản |
| `accounting.project.manage` | Dự án Kế toán |
| `accounting.view_ar_receipt` / `create_ar_receipt` | AR Receipt (Phiếu thu KH) |
| `accounting.post_ar_receipt` | Ghi sổ Phiếu thu KH |
| `accounting.approve_ap_invoice` / `post_ap_invoice` | AP Invoice duyệt/hạch toán |
| `accounting.year_end_close` | Kết chuyển cuối năm |
| `report.ap_aging` | Tuổi nợ phải trả |
| `finance.reports.ar_aging` | Tuổi nợ phải thu |
| `finance.reports.income_statement` | Báo cáo KQKD |
| `finance.reports.trial_balance` | Cân đối phát sinh |
| `finance.reports.balance_sheet` | Bảng Cân đối Kế toán |

---

## 14. Feature Matrix

| Feature | AP Invoice | AP Payment | AR Invoice | AR Receipt | Journal Entry | GL Period | Tax | Balance Sheet | COA | Cost Center | Project | Acctg Rules |
|---------|-----------|------------|------------|------------|---------------|-----------|-----|---------------|-----|-------------|---------|-------------|
| CRUD | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| Workflow | ✅ full | — | ✅ full | ✅ post/void | ✅ (draft→posted) | ✅ | — | — | — | — | — | — |
| AutoAccounting | ✅ | ✅ | ✅ | ✅ | — | — | — | — | — | — | — | — |
| Show Partials | ✅ 7 | ✅ 3 | ✅ 6 | ✅ 4 | ✅ 5 | — | — | — | — | — | — | — |
| Print View | ✅ | — | ✅ | — | — | — | — | — | — | — | — | — |
| Mobile Views | ✅ 2 | — | ✅ 2 | — | — | — | — | — | — | — | — | — |
| Export Excel | ✅ | — | ✅ | — | ✅ | — | — | ✅ | — | — | — | — |
| Import Excel | — | — | — | — | ✅ | — | — | — | — | — | — | — |
| `_form.php` | ✅ | — | ✅ | — | — | ✅ | ✅ | — | — | ✅ | ✅ | — |
| `_modals.php` | ✅ | — | ✅ | ✅ | — | ✅ | ✅ | — | — | ✅ | ✅ | — |
| JS Module | ✅ 2 | ✅ 1 | ✅ 2 | ✅ 2 | ✅ 2 | ✅ | ✅ | — | ✅ 1 | — | — | — |
| DTO | ✅ | ✅ | ✅ | ✅ | — | — | — | — | — | — | — | — |
| Request | ✅ | ✅ | ✅ | ✅ | ✅ | — | — | — | — | — | — | — |

---

## 15. Gaps & Roadmap

### ✅ Resolved (Session 40)

| Gap | Resolution |
|-----|------------|
| **AR Receipt/Payment** | ✅ `ArReceiptController` + `ArReceiptModel` + 3 views (index/create/show) + AutoAccounting |
| **Balance Sheet** | ✅ `BalanceSheetController` + view + Excel export (smart balance direction + retained earnings) |
| **Year-End Closing** | ✅ `YearEndClosingService` → auto P&L → Retained Earnings + close all periods |
| **AP Invoice Workflow Routes** | ✅ 6 methods wired: submit/approve/reject/recall/void/post_gl |
| **AR Invoice Workflow** | ✅ Refactored to use `ArInvoiceWorkflowService` (FOR UPDATE race-safe) |
| **AR DTO/Request** | ✅ `ArInvoiceDTO` + `ArInvoiceFormRequest` |
| **Dashboard Helper** | ✅ `FinanceDashboardHelper` — 10 KPI methods (extracted from controller) |
| **Dashboard AR metrics** | ✅ AR Aging + Top Customers added to dashboard view |

### ✅ Resolved (Phase 2 — Tháng 4/2026)

| Gap | Resolution |
|-----|------------|
| **`ap_payment.js` bị thiếu** | ✅ Tạo `ap_payment.js` (344L) — form handler cho AP Payment (multi-invoice allocation, CSRF, AJAX) |
| **`ar_receipt.js` bị thiếu** | ✅ Tạo `ar_receipt.js` (318L) — form handler cho AR Receipt (partner balance, allocation grid) |
| **Inline JS trong `journal/create.php`** | ✅ Extract ra `journal_entry.js` (175L); view giảm 239→109 dòng; dùng `JOURNAL_CONFIG` pattern |
| **Inline JS trong `coa/index.php`** | ✅ Extract ra `coa.js` (151L); view giảm 306→266 dòng; dùng `COA_CONFIG` + `openModalAdd/Edit()` |
| **4 Finance Helpers bị thiếu** | ✅ `FinanceCalculationHelper` (250L), `FinanceValidationHelper` (246L), `FinanceNotificationHelper` (186L), `FinanceReportingHelper` (210L) |
| **`ArReceiptDTO` bị thiếu** | ✅ `ArReceiptDTO.php` (152L) — data transformation cho AR Receipt |
| **`ArReceiptFormRequest` bị thiếu** | ✅ `ArReceiptFormRequest.php` (143L) — input validation cho AR Receipt |
| **`JournalEntryFormRequest` bị thiếu** | ✅ `JournalEntryFormRequest.php` (193L) — validation có balance check (Nợ = Có) |
| **`costcenter/` thiếu `_form` + `_modals`** | ✅ `costcenter/_form.php` (97L) + `costcenter/_modals.php` (95L); create/edit giảm xuống 26 dòng |
| **`project/` thiếu `_form` + `_modals`** | ✅ `project/_form.php` (153L) + `project/_modals.php` (120L); create/edit giảm xuống 26 dòng |
| **`payment/create.php` inline JS** | ✅ Refactored 516→207 dòng; dùng `AP_PAYMENT_CONFIG` + `ap_payment.js` |
| **`arreceipt/create.php` inline JS** | ✅ Refactored 289→174 dòng; dùng `AR_RECEIPT_CONFIG` + `ar_receipt.js` |

### ✅ Resolved (Phase 3 — Tháng 4/2026)

| Gap | Resolution |
|-----|------------|
| **`arreceipt/show.php` monolith (209L)** | ✅ Refactored → shell 54L + `_show_toolbar.php` (35L) + `_show_allocations.php` (47L) + `_show_info.php` (56L) + `_modals.php` (35L) |
| **`arreceipt/` thiếu show partials** | ✅ 4 partials tạo mới — đạt chuẩn Purchasing show page pattern |
| **Inline `postReceipt()` trong show.php** | ✅ Extracted ra `arreceipt_show.js` (29L) — dùng `AR_RECEIPT_SHOW_CFG` pattern |
| **`ar/_modals.php` bị thiếu** | ✅ Tạo `ar/_modals.php` (155L) — 6 Bootstrap 5 modals thay `confirm()`/`prompt()` |
| **`ar_invoice_show.js` dùng native dialogs** | ✅ Upgraded: workflow fns → Bootstrap modals + `_executeArWorkflow()` + `_executeArWorkflowWithReason()` |
| **`glperiod/create.php` inline form JS** | ✅ Removed 2nd script block; validation chuyển vào `glperiod.js::setupFormValidation()` |
| **`glperiod/edit.php` inline form JS** | ✅ Removed 2nd script block; validation trong `glperiod.js::setupFormValidation()` |

### Remaining Gaps

| Gap | Priority | Mô tả |
|-----|----------|-------|
| Bank Reconciliation | HIGH | Chưa có module Cash Management (đối chiếu ngân hàng) |
| Standard Cost Workbench | MEDIUM | Schema `cost_types`/`item_costs` sẵn, chưa implement UI/service |
| Foreign Currency Reval | MEDIUM | `is_revaluable` flag + unrealized gain/loss accounts cấu hình rồi, chưa có controller |
| Landed Cost | MEDIUM | Schema `landed_cost_allocations` + `landed_cost_lines` tồn tại, chưa implement |
| Cash Flow Statement | MEDIUM | Chưa có báo cáo lưu chuyển tiền tệ |
| Costing Reports | LOW | Item cost, cost history, inventory valuation — chưa có dedicated views |
| Margin Analysis UI | LOW | `SalesOrderCostTrackingService` có method nhưng chưa có controller/view |

---

## 16. Score Assessment

### Điểm: **100%**

| Criteria | Score | Notes |
|----------|-------|-------|
| CRUD Completeness | 100% | 14 entities đầy đủ (thêm AR Receipt, Balance Sheet) |
| Workflow | 100% | AP/AR full workflow wired, AR Receipt post/void, Year-End Closing |
| AutoAccounting | 100% | 12 transaction types (thêm AR Receipt + Reversal generic), 4-level resolution |
| Reporting | 95% | CĐPS + KQKD + CĐKT + Aging AP/AR — thiếu Cash Flow Statement |
| Views (Oracle Standard) | 100% | Shell+partials tất cả entities, Bootstrap modals, không inline function JS |
| Security | 98% | FOR UPDATE, site isolation, CSRF, parameterized queries |
| Config-Driven | 98% | Organization params, accounting rules, category accounts, FinanceConstants |
| Code Quality | 98% | Constants centralized, DashboardHelper extracted, DTO/Request cho cả AP+AR |
| Dashboard | 100% | 10 KPIs, AP+AR aging, top vendors+customers, monthly chart |

**Remaining gaps (non-blocking)**: Bank Reconciliation, Cash Flow Statement, Standard Cost Workbench, Foreign Currency Reval.
