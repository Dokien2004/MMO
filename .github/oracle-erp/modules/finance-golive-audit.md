# Finance Module — Go-Live Audit

> **Cập nhật**: 2026-04-21 · Session: Finance full scan + UI sync + Landed Cost
> **Phạm vi**: 20 controllers, 17 models, ~27 services, 7 helpers, 16 view folders
> **Reference baseline**: Module **Purchasing** (100% chuẩn Oracle EBS standard)

---

## I. Tổng quan Module

Finance là module kế toán tài chính theo chuẩn **Oracle Fusion Cloud Financials / Oracle EBS R12**, bao gồm:

- **General Ledger (GL)**: Journal Entry, Chart of Accounts, GL Periods, Cost Centers, Projects, Exchange Rates
- **Accounts Payable (AP)**: Invoice (3-way match PO↔GRN), Payment, Aging, Reports
- **Accounts Receivable (AR)**: Invoice (from SO shipment), Receipt + allocation, Aging, Reports
- **Cost Management**: **Landed Cost Allocation** (NEW 2026-04), Auto-Accounting Engine (7 variants)
- **Period-End**: Trial Balance, Balance Sheet, Income Statement, Year-End Closing, Exchange Rate Revaluation
- **Tax Management**: VAT setup, tax codes, tax on AP/AR
- **Accounting Rules Engine**: Cấu hình bút toán tự động cho mọi giao dịch Inventory/Purchasing/Sales/Manufacturing

---

## II. Inventory — Cấu trúc File

### Controllers (`app/controllers/finance/` — 20 files)

| File | Chức năng |
|------|-----------|
| `AccountingRulesController` | Cấu hình rule auto-GL cho các module |
| `ApInvoiceController` | AP Invoice CRUD + 3-way match + workflow |
| `ApPaymentController` | AP Payment + allocation + FX gain/loss |
| `ApReportController` | AP Aging, outstanding, trend reports |
| `ArInvoiceController` | AR Invoice from SO shipment |
| `ArReceiptController` | Customer receipt + partial allocation |
| `ArReportController` | AR Aging, DSO, customer statement |
| `BalanceSheetController` | Bảng cân đối kế toán at-date |
| `CoaController` | Chart of Accounts tree CRUD |
| `CostCenterController` | Cost center master data |
| `ExchangeRateController` | FX rate import/maintain |
| `FinanceDashboardController` | KPI dashboard (aging, period, trends) |
| `GlperiodController` | GL Period open/close/lock |
| `IncomeStatementController` | P&L report |
| `JournalEntryController` | Manual JE + reversal + import Excel |
| `LandedCostController` ✨ | Landed Cost allocation (NEW) |
| `PaymentTermController` | Payment term master data |
| `ProjectController` | Project segment (cross-module) |
| `TaxController` | Tax code management |
| `TrialBalanceController` | Trial balance at-period |

### Models (`app/models/finance/` — 17 files)

Tất cả models extend `BaseModel`, sử dụng `site_id` isolation, soft delete, audit log. Include **LandedCostAllocationModel** (NEW).

### Services (`app/services/finance/` — ~27 files)

| Service | Scope |
|---------|-------|
| `ApInvoiceService`, `ApInvoiceImportService`, `ApInvoiceExportService`, `ApInvoiceEmailService` | AP Invoice workflow |
| `ApPaymentService` | AP Payment + FX calc |
| `ApReportService` | AP reports |
| `ArInvoiceService`, `ArInvoiceImportService`, `ArInvoiceExportService`, `ArInvoiceEmailService` | AR Invoice workflow |
| `ArReceiptService` | AR Receipt + allocation |
| `ArReportExportService` | AR report export |
| `JournalEntryService`, `JournalEntryImportService`, `JournalEntryExportService` | GL manual |
| `GlPeriodService` | Period open/close lifecycle |
| `CoaService` | Chart of accounts operations |
| `FinanceEmailService` | Email notifications |
| `TrialBalanceExportService` | TB export |
| `YearEndClosingService` | Year-end P&L → Retained Earnings close |
| **`LandedCostService`** ✨ | Landed Cost allocation + post |
| **Auto-Accounting Engine** (7 variants) | Tự động sinh JE từ Inventory/WIP/Return: `AutoAccountingService`, `AutoAccounting_opening`, `AutoAccounting_receipt`, `AutoAccounting_return_vendor`, `AutoAccounting_wip`, `AutoAccounting_wip_completion`, `AutoAccounting_wip_consumption`, `AutoAccounting_wip_return`, `AutoAccounting_wip_scrap` |

### Helpers (`app/helpers/finance/` — 7 files)

- `FinanceConstants.php` — Status constants (AP/AR/JE/GL statuses)
- `FinanceCalculationHelper.php` — FX, tax, aging bucket calc
- `FinanceDashboardHelper.php` — Dashboard KPI queries
- `FinanceNotificationHelper.php` — Email recipient lookup
- `FinanceReportingHelper.php` — Report utility queries
- `FinanceValidationHelper.php` — Lifecycle guards
- **`LandedCostConstants.php`** ✨ — Landed Cost status/cost_type/method labels

### Views (`app/views/finance/` — 16 folders)

```
ap/              ar/              arreceipt/       coa/
costcenter/      dashboard/       exchange_rates/  glperiod/
journal/         landedcost/ ✨    payment/         payment_terms/
project/         report/          rules/           tax/
```

---

## III. UI Standardization Audit (Session 2026-04-21)

### ✅ Đã đồng bộ Purchasing UI Standard (13 index pages)

Tất cả đều có comment `[SYNC] Purchasing UI Standard` và dùng:
- `.table-container { height: calc(100vh - 220px/260px) }`
- `.table-sticky thead th { background-color: #343a40; color: #fff; sticky; z-index: 10 }`
- `.table-compact td { padding: 0.5rem 0.4rem; font-size: 0.85rem }`
- Header pattern: `<h5 class="mb-0 fw-bold"> <span class="bg-XXX bg-opacity-10 text-XXX rounded p-2 me-2"><i/></span> Title`
- Badge colors: draft #6c757d · submitted #ffc107 · approved #198754 · posted #0d6efd · cancelled #dc3545

| File | Status |
|------|--------|
| `finance/ap/index.php` | ✅ |
| `finance/ar/index.php` | ✅ |
| `finance/arreceipt/index.php` | ✅ |
| `finance/journal/index.php` | ✅ |
| `finance/coa/index.php` | ✅ |
| `finance/payment/index.php` | ✅ |
| `finance/glperiod/index.php` | ✅ |
| `finance/tax/index.php` | ✅ |
| `finance/exchange_rates/index.php` | ✅ |
| `finance/costcenter/index.php` | ✅ |
| `finance/project/index.php` | ✅ |
| `finance/payment_terms/index.php` | ✅ |
| `finance/rules/index.php` | ✅ |

### ✅ Đã sync trong session này

| File | Action |
|------|--------|
| `finance/landedcost/index.php` | Rework toàn bộ từ plain card → Purchasing standard (table-container, table-sticky, badge-process, code-lca monospace, icon header badge, site label, responsive breakpoints) |
| `finance/landedcost/create.php` | Header chuẩn Purchasing, table-sticky trong GRN picker, button styling nhất quán |
| `finance/landedcost/show.php` | Header with code-lca + badge-process, action buttons chuẩn `fw-bold shadow-sm`, permission checks |

### 🎨 Views riêng style (CHẤP NHẬN — không sync)

| File | Lý do |
|------|-------|
| `finance/dashboard/index.php` | KPI dashboard cần style riêng (kpi-card, aging-bar, period-badge) |
| `finance/report/trial_balance.php` | Report page — summary card style phù hợp hơn |
| `finance/report/balance_sheet.php` | Report page — `section-header.asset/liability/equity` style đặc thù |
| `finance/report/income_statement.php` | Report P&L |
| `finance/report/ap_aging.php` / `ar_aging.php` | Aging reports |

**Nguyên tắc**: Index/list pages → Purchasing standard. Dashboard/Report pages → có thể dùng style riêng phù hợp bản chất trình bày số liệu.

---

## IV. Feature Matrix

| Entity | CRUD | Workflow | Import | Export | Print | Dashboard | Show page | Status |
|--------|------|----------|--------|--------|-------|-----------|-----------|--------|
| AP Invoice | ✅ | ✅ (draft→submitted→approved→paid) | ✅ Excel | ✅ | ✅ PDF | ✅ | ✅ 7 partials | 100% |
| AP Payment | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ | 100% |
| AR Invoice | ✅ | ✅ (from SO shipment) | ✅ | ✅ | ✅ PDF | ✅ | ✅ 7 partials | 100% |
| AR Receipt | ✅ | ✅ + allocation | — | ✅ | — | ✅ | ✅ | 100% |
| Journal Entry | ✅ | ✅ (draft→posted→reversed) | ✅ Excel | ✅ | ✅ | ✅ | ✅ 7 partials | 100% |
| Chart of Accounts | ✅ tree | — | ✅ | — | — | — | — | 100% |
| Cost Center | ✅ | — | — | — | — | — | — | 100% |
| GL Period | ✅ open/close/lock | — | — | — | — | — | — | 100% |
| Exchange Rate | ✅ | — | ✅ | — | — | — | — | 100% |
| Payment Term | ✅ | — | — | — | — | — | — | 100% |
| Project | ✅ | — | — | — | — | — | — | 100% |
| Tax | ✅ | — | — | — | — | — | — | 100% |
| Accounting Rules | ✅ config | — | — | — | — | — | — | 100% |
| **Landed Cost** ✨ | ✅ | ✅ draft→allocated→posted→cancelled | — | — | — | — | ✅ | **85%** (thiếu import Excel, export, print) |
| Trial Balance | 🔍 view | — | — | ✅ | ✅ | — | — | 100% |
| Balance Sheet | 🔍 view | — | — | ✅ | ✅ | — | — | 100% |
| Income Statement | 🔍 view | — | — | ✅ | ✅ | — | — | 100% |
| AP Aging Report | 🔍 view | — | — | ✅ | — | — | — | 100% |
| AR Aging Report | 🔍 view | — | — | ✅ | — | — | — | 100% |

---

## V. Prerequisites (trước Go-Live)

### 1. Master Data Setup
- [ ] **Chart of Accounts**: Import đủ cây tài khoản chuẩn TT200/TT133 VN
- [ ] **Accounting Rules**: Map đủ 200+ account cho các transaction types (Inventory, Purchasing, Sales, Manufacturing, Return, Scrap)
- [ ] **Organization Parameters** (`organization_parameters` table): Config các account mặc định per site
  - `acct_inventory`, `acct_inventory_ap`, `acct_inventory_adj`
  - `acct_cogs`, `acct_sales`
  - `acct_wip`, `acct_labor`, `acct_overhead`, `acct_scrap_loss`
  - `acct_variance`, `acct_expense_default`
- [ ] **Cost Centers**: Tạo cost center cho các phòng ban
- [ ] **Payment Terms**: NET30, NET60, COD, tùy doanh nghiệp
- [ ] **Tax Codes**: VAT 0%, 5%, 8%, 10%
- [ ] **Currencies + Exchange Rates**: Setup currency pairs + daily rate import

### 2. GL Period Setup
- [ ] Tạo 12 period cho năm đầu (Jan–Dec)
- [ ] Opening period đầu năm → import opening balance qua **JE manual** hoặc **AutoAccounting_opening service**
- [ ] Tùy chỉnh lịch đóng sổ: daily cut-off, monthly close by 5th

### 3. Permissions (Role Matrix)
- `finance.view` · `finance.journal.create` · `finance.journal.post`
- `finance.ap.create` · `finance.ap.approve` · `finance.ap.payment`
- `finance.ar.create` · `finance.ar.approve` · `finance.ar.receipt`
- `finance.period.close` · `finance.period.lock`
- `finance.landed_cost.view` · `finance.landed_cost.create` · `finance.landed_cost.post` ✨

### 4. Document Sequences
| Code | Pattern | Dùng cho |
|------|---------|----------|
| `APINV` | `APINV-{YYYY}/{MM}/{####}` | AP Invoice |
| `APPAY` | `APPAY-{YYYY}/{MM}/{####}` | AP Payment |
| `ARINV` | `ARINV-{YYYY}/{MM}/{####}` | AR Invoice |
| `ARREC` | `ARREC-{YYYY}/{MM}/{####}` | AR Receipt |
| `JE` | `JE-{YYYY}/{MM}/{####}` | Manual Journal |
| `LCA` ✨ | `LCA-{YYYY}/{MM}/{####}` | Landed Cost Allocation |

---

## VI. Core Workflows

### 1. AP Invoice (3-way match)
```
PO (Purchasing) → GRN (Inventory Receipt) → AP Invoice (Finance)
                                               ↓
                             Auto-match PO qty + GRN qty + Invoice qty
                                               ↓
                       Variance analysis (qty + price variance → G/L)
                                               ↓
                       Draft → Submitted → Approved → Paid (via AP Payment)
                                               ↓
                              AutoAccounting_receipt generates JE
```

### 2. AR Invoice (from SO shipment)
```
Sales Quote → SO → Shipment (Delivery Note) → AR Invoice (auto-generate)
                                                       ↓
                                       Customer Receipt (partial allowed)
                                                       ↓
                                     Allocation to invoices (FIFO / manual)
                                                       ↓
                                                   Reconciliation
```

### 3. Journal Entry (Manual)
```
JE Draft → Validate (balanced debit/credit) → Post to GL → (optional) Reverse
                                                   ↓
                                  Update gl_periods, account balances
```

### 4. Landed Cost Allocation ✨
```
Import invoice arrives (shipping, duty, insurance, ...) 
             ↓
Create LCA draft → Pick AP invoice (optional) → Set total_amount + currency
             ↓
Pick GRN lines (filter by supplier + date) → Select lines to allocate
             ↓
Method: VALUE (pro-rata by receipt value) | QUANTITY (pro-rata by qty)
             ↓
LandedCostService.allocate() → Writes to `landed_cost_distributions` table
             ↓
Post → Updates `inventory_receipt_details.unit_cost_with_landed_cost`
     → Generates JE: Dr Inventory / Cr AP Clearing (or direct GL)
             ↓
Cancel: Reverses JE, reverts inventory unit cost
```

### 5. Period-End Close
```
Daily:    AutoAccounting runs (inventory txns → JE)
Monthly:
  1. Run AP Aging, AR Aging, Trial Balance
  2. Reconcile bank accounts (manual)
  3. FX revaluation (MANUAL currently — see §VII gap)
  4. Close period (`glperiod/close`) — locks JE posting
  5. Run Balance Sheet + Income Statement
Year-end:
  1. Run YearEndClosingService → Transfer P&L accounts → Retained Earnings
  2. Open next year periods
```

---

## VII. Known Gaps & TODO (Post Go-Live)

### 🔴 HIGH — Prepared in DB but no UI

| Feature | DB Table | Status |
|---------|----------|--------|
| **FX Revaluation** | `gl_revaluation_history` | ❌ Table exists, KHÔNG có controller/model/service — cần implement: run revaluation per period, compute diff, post JE |
| **Bank Reconciliation** | — | ❌ Chưa có table cho bank_statements + recon lines |
| **Recurring Journals** | — | ❌ Chưa có schema |
| **Budget vs Actual** | — | ❌ Chưa có budget module |

### 🟡 MEDIUM — Landed Cost enhancements

- [ ] Import LCA từ Excel (bulk upload invoice + GRN mapping)
- [ ] Export danh sách LCA ra Excel
- [ ] Print LCA allocation slip (PDF)
- [ ] Dashboard riêng cho LCA: % chi phí landed cost / tổng giá vốn nhập
- [ ] Multi-invoice allocation: 1 LCA gom nhiều hóa đơn CP

### 🟢 LOW — Nice-to-have

- [ ] Email notification cho AP payment due
- [ ] Statement of Account email gửi KH (AR)
- [ ] Mobile view cho AP approver
- [ ] Batch export TB/BS/IS theo range period
- [ ] Drill-down từ GL balance → JE → source document

---

## VIII. Integration Points

| Source Module | Trigger | Target in Finance | Handler |
|---------------|---------|-------------------|---------|
| Inventory Receipt (GRN) | Post receipt | JE Dr Inventory / Cr GR-IR Clearing | `AutoAccounting_receipt` |
| Inventory Transfer | Post transfer | — (no JE if internal) | — |
| Inventory Return to Vendor | Post return | JE Dr GR-IR / Cr Inventory | `AutoAccounting_return_vendor` |
| Opening Stock | Import opening | JE Dr Inventory / Cr Opening Equity | `AutoAccounting_opening` |
| WIP Material Issue | Post issue | JE Dr WIP / Cr Inventory | `AutoAccounting_wip_consumption` |
| WIP Completion | Complete WO | JE Dr FG Inventory / Cr WIP | `AutoAccounting_wip_completion` |
| WIP Scrap | Scrap posting | JE Dr Scrap Loss / Cr WIP | `AutoAccounting_wip_scrap` |
| WIP Return (FG→WIP) | Return component | JE Dr WIP / Cr FG | `AutoAccounting_wip_return` |
| Purchasing AP Match | Invoice 3-way | Updates GR-IR clearing | `ApInvoiceService` |
| Sales Shipment | Deliver goods | JE Dr COGS / Cr Inventory + create AR Invoice | (pending audit — check if hook wired) |
| **Landed Cost Post** ✨ | LCA post | JE Dr Inventory / Cr AP Clearing + update unit_cost | `LandedCostService` |

---

## IX. Go-Live Checklist

### Tuần -4 (Data Prep)
- [ ] Verify Chart of Accounts đúng TT200
- [ ] Mapping 200+ accounting rules — test 3 transaction types end-to-end
- [ ] Setup document sequences (APINV, ARINV, JE, LCA...)
- [ ] Import exchange rates 12 tháng gần nhất
- [ ] Setup permissions matrix theo 5 role: Finance Manager, AP Clerk, AR Clerk, GL Accountant, Auditor

### Tuần -2 (Opening Balance)
- [ ] Import opening balance qua JE manual (cross-check TB balanced)
- [ ] Import AP outstanding invoices
- [ ] Import AR outstanding invoices
- [ ] Run Trial Balance → verify = 0 variance

### Tuần -1 (User Training)
- [ ] Train AP Clerk: create invoice, 3-way match, submit for approval
- [ ] Train AR Clerk: create invoice from SO, apply receipt
- [ ] Train GL Accountant: JE, period close, reports
- [ ] Train Finance Manager: approval workflow, Landed Cost allocation, FX

### Go-Live Week
- [ ] Day 1: Freeze old system, cut-over announcement
- [ ] Day 1-3: Hypercare — monitor AutoAccounting JE generation, check for any AP/AR creation failure
- [ ] Day 5: First week close trial — verify reports match expectation
- [ ] Day 30: First month close — full period-end workflow

### Post Go-Live (tuần +2)
- [ ] Review Landed Cost allocation accuracy (spot check 5 LCA)
- [ ] Audit JE with no source document (orphan JEs)
- [ ] Verify GL Period lock prevents post to closed period
- [ ] Roll-forward FY planning

---

## X. Support & Escalation

- **Module Owner**: Finance team lead
- **Tech Lead**: Backend team — xem `app/services/finance/AutoAccountingService.php` làm ref
- **Reference Docs**:
  - `.github/oracle-erp/modules/finance-module.md` — Architecture overview
  - `.github/oracle-erp/modules/finance-phase2-upgrade.md` — Phase 2 upgrade history
  - `.github/oracle-erp/modules/finance-phase3-upgrade.md` — Phase 3 upgrade history
  - `.github/oracle-erp/modules/purchasing-golive-audit.md` — Reference gold standard
  - `docs/` — Technical upgrade docs (WIP, Production compliance)

---

**Last updated**: 2026-04-21 by AI Agent (Finance full scan + Landed Cost UI sync)
