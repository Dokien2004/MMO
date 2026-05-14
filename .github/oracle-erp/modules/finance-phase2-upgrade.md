# Finance Module — Phase 2 UI Upgrade

> **Ngày thực hiện**: Tháng 4/2026  
> **Phạm vi**: JS externalization, View standardization (Oracle Engineer Standard V2), Helpers + DTO + FormRequest layer  
> **Trạng thái**: ✅ Hoàn thành 100%

---

## 1. Mục tiêu Phase 2

Phase 2 tập trung vào **chuẩn hoá code quality** cho Finance module theo Oracle Engineer Standard V2:

| Mục tiêu | Lý do |
|----------|-------|
| Tách inline JS ra file riêng | Tránh browser cache miss, dễ debug, comply với CSP headers |
| Tạo đủ 13 JS modules | Mỗi entity có file JS riêng (convention: `{entity}.js` + `{entity}_show.js`) |
| Tạo `_form.php` + `_modals.php` | Loại bỏ form duplication giữa create/edit views |
| Tạo đủ DTOs + FormRequests | Data layer chuẩn hoá cho 4 transactional entities |
| Tạo 4 Finance Helpers còn thiếu | Business logic tính toán, validate, notify, report ra helper riêng |

---

## 2. Deliverables — Tạo mới

### 2.1 JavaScript Modules (4 files mới)

| File | Lines | Chức năng | Config Object |
|------|-------|-----------|---------------|
| `public/js/modules/finance/ap_payment.js` | 344L | AP Payment form: multi-invoice allocation grid, CSRF, AJAX submit | `AP_PAYMENT_CONFIG` |
| `public/js/modules/finance/ar_receipt.js` | 318L | AR Receipt form: partner lookup, allocation grid, CSRF | `AR_RECEIPT_CONFIG` |
| `public/js/modules/finance/journal_entry.js` | 175L | Journal Entry form: `addRow()`, `removeRow()`, `calcTotal()` (balance check) | `JOURNAL_CONFIG` |
| `public/js/modules/finance/coa.js` | 151L | COA SPA modal: `openModalAdd()`, `openModalEdit(id)` via AJAX | `COA_CONFIG` |

**Pattern Config Injection** (chuẩn toàn module):

```php
<!-- PHP truyền data/URL sang JS -->
<script>
const JOURNAL_CONFIG = {
    accounts:    <?= json_encode(array_values($data['accounts']), JSON_UNESCAPED_UNICODE) ?>,
    partners:    <?= json_encode(array_values($data['partners']), JSON_UNESCAPED_UNICODE) ?>,
    costCenters: <?= json_encode(array_values($data['cost_centers']), JSON_UNESCAPED_UNICODE) ?>,
    projects:    <?= json_encode(array_values($data['projects']), JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="<?= asset_v('js/modules/finance/journal_entry.js') ?>"></script>
```

### 2.2 Finance Helpers (4 files mới)

| File | Lines | Chức năng |
|------|-------|-----------|
| `app/helpers/finance/FinanceCalculationHelper.php` | 250L | Currency conversion (multi-rate), VAT calculation, payment due date, rounding |
| `app/helpers/finance/FinanceValidationHelper.php` | 246L | Period lock check, journal balance validation, partner credit limit check |
| `app/helpers/finance/FinanceNotificationHelper.php` | 186L | Email recipient lookup cho AP/AR workflow events (submitted, approved, rejected, posted) |
| `app/helpers/finance/FinanceReportingHelper.php` | 210L | Aging bucket calculation (5 tiers), YTD aggregation, report formatters |

### 2.3 DTOs (1 file mới)

| File | Lines | Chức năng |
|------|-------|-----------|
| `app/dtos/finance/ArReceiptDTO.php` | 152L | AR Receipt data transformation: normalize receipt header + allocation lines |

### 2.4 FormRequests (2 files mới)

| File | Lines | Rules key |
|------|-------|-----------|
| `app/requests/finance/ArReceiptFormRequest.php` | 143L | Validate receipt date, partner_id, amount > 0, currency, allocation sum ≤ amount |
| `app/requests/finance/JournalEntryFormRequest.php` | 193L | Validate date, period open, lines balance (Nợ = Có, tolerance 0.01), account_id required |

### 2.5 View Partials (4 files mới)

| File | Lines | Dùng bởi |
|------|-------|----------|
| `app/views/finance/costcenter/_form.php` | 97L | `costcenter/create.php` + `costcenter/edit.php` |
| `app/views/finance/costcenter/_modals.php` | 95L | `costcenter/index.php` (delete confirm modal) |
| `app/views/finance/project/_form.php` | 153L | `project/create.php` + `project/edit.php` |
| `app/views/finance/project/_modals.php` | 120L | `project/index.php` (delete confirm modal) |

---

## 3. Deliverables — Refactor views

### 3.1 Views được thu gọn (inline JS → external file)

| View | Before | After | Reduction | JS File |
|------|--------|-------|-----------|---------|
| `payment/create.php` | 516L | 207L | **60% ↓** | `ap_payment.js` |
| `arreceipt/create.php` | 289L | 174L | **40% ↓** | `ar_receipt.js` |
| `journal/create.php` | 239L | 109L | **54% ↓** | `journal_entry.js` |
| `coa/index.php` | 306L | 266L | **13% ↓** | `coa.js` |

### 3.2 Views được thu gọn (shared _form.php)

| Create View | Before | After | Edit View | Before | After |
|-------------|--------|-------|-----------|--------|-------|
| `costcenter/create.php` | ~120L | 26L | `costcenter/edit.php` | ~120L | 26L |
| `project/create.php` | ~160L | 26L | `project/edit.php` | ~160L | 26L |

---

## 4. Trạng thái JS Coverage (sau Phase 2)

| Entity | Form JS | Show JS | Ghi chú |
|--------|---------|---------|---------|
| AP Invoice | `ap_invoice.js` (343L) | `ap_invoice_show.js` (131L) | ✅ Đầy đủ |
| AP Payment | `ap_payment.js` (344L) ✅ NEW | — | ✅ Form có inline allocation |
| AR Invoice | `ar_invoice_form.js` (189L) | `ar_invoice_show.js` (101L) | ✅ Đầy đủ |
| AR Receipt | `ar_receipt.js` (318L) ✅ NEW | — | ✅ Form + allocation |
| Journal Entry | `journal_entry.js` (175L) ✅ NEW | `journalentry_show.js` (86L) | ✅ Đầy đủ |
| GL Period | `glperiod.js` (481L) | — | ✅ SPA pattern |
| Exchange Rate | `exchange_rate.js` (211L) | — | ✅ CRUD |
| Payment Term | `payment_term.js` (302L) | — | ✅ Dynamic lines |
| Tax | `tax.js` (385L) | — | ✅ 3-tier hierarchy |
| COA | `coa.js` (151L) ✅ NEW | — | ✅ SPA modal |
| Cost Center | — | — | ⬜ Không cần (simple CRUD, modal trong index) |
| Project | — | — | ⬜ Không cần (simple CRUD, modal trong index) |
| Accounting Rules | — | — | ⬜ SPA 3-tab, JS embedded OK |

**Tổng: 13 JS files, 3,317 lines** — tăng từ 9 files lên 13 files.

---

## 5. DTO + FormRequest Coverage (sau Phase 2)

| Entity | DTO | FormRequest | Ghi chú |
|--------|-----|-------------|---------|
| AP Invoice | `ApInvoiceDTO` (189L) | `ApInvoiceFormRequest` (156L) | ✅ |
| AP Payment | `ApPaymentDTO` (134L) | `ApPaymentFormRequest` (144L) | ✅ |
| AR Invoice | `ArInvoiceDTO` (145L) | `ArInvoiceFormRequest` (111L) | ✅ |
| AR Receipt | `ArReceiptDTO` (152L) ✅ NEW | `ArReceiptFormRequest` (143L) ✅ NEW | ✅ |
| Journal Entry | — | `JournalEntryFormRequest` (193L) ✅ NEW | ✅ |

---

## 6. Helper Coverage (sau Phase 2)

| Helper | Lines | Key Methods |
|--------|-------|-------------|
| `FinanceConstants` | 188L | `AP_STATUS_*`, `AR_STATUS_*`, `JE_STATUS_*`, `GL_PERIOD_STATUS_*` — labels + badges |
| `FinanceDashboardHelper` | 177L | `getDraftJournalCount()`, `getApAgingSummary()`, `getArAgingSummary()`, `getTopVendors()`, `getTopCustomers()`, `getMonthlyChart()` — 10 KPI methods |
| `FinanceCalculationHelper` | 250L | `convertCurrency()`, `calcVatAmount()`, `calcPaymentDueDate()`, `roundFinancial()` |
| `FinanceValidationHelper` | 246L | `checkPeriodOpen()`, `validateJournalBalance()`, `checkPartnerCreditLimit()`, `validateMatchingTolerance()` |
| `FinanceNotificationHelper` | 186L | `getApprovalRecipients()`, `getApNotifyList()`, `getArNotifyList()` |
| `FinanceReportingHelper` | 210L | `calcAgingBuckets()`, `aggregateYtd()`, `formatReportRow()`, `buildTrialBalanceRow()` |

---

## 7. View Structure Completeness (sau Phase 2)

| Submodule | `_form.php` | `_modals.php` | JS File | Ghi chú |
|-----------|------------|--------------|---------|---------|
| `ap/` | ✅ | ✅ | ✅ ap_invoice.js + show | Full Oracle standard |
| `ar/` | ✅ | ❌ | ✅ ar_invoice_form.js + show | Workflow fn ở ar_invoice_show.js |
| `journal/` | ❌ | ❌ | ✅ journal_entry.js + show | Form inline (acceptable cho complex) |
| `payment/` | ❌ | ❌ | ✅ ap_payment.js | Form inline (acceptable cho complex) |
| `arreceipt/` | ❌ | ❌ | ✅ ar_receipt.js | Form inline (acceptable cho complex) |
| `glperiod/` | ✅ | ✅ | ✅ glperiod.js | ✅ Full |
| `exchange_rates/` | ✅ | ✅ | ✅ exchange_rate.js | ✅ Full |
| `payment_terms/` | ✅ | ✅ | ✅ payment_term.js | ✅ Full |
| `tax/` | ✅ | ✅ | ✅ tax.js | ✅ Full |
| `costcenter/` | ✅ NEW | ✅ NEW | ❌ | Simple CRUD, OK |
| `project/` | ✅ NEW | ✅ NEW | ❌ | Simple CRUD, OK |
| `coa/` | N/A | N/A | ✅ coa.js | SPA modal pattern |
| `rules/` | N/A | N/A | ❌ | 3-tab SPA, acceptable |
| `dashboard/` | N/A | N/A | N/A | Read-only |
| `report/` | N/A | N/A | N/A | Read-only |

---

## 8. Gaps Còn Lại (Non-blocking)

Những gaps này không được giải quyết trong Phase 2 và là non-blocking cho production:

| Gap | Priority | Lý do chưa làm |
|-----|----------|----------------|
| `ar/_modals.php` | LOW | Workflow fns đã được xử lý trong `ar_invoice_show.js` — đủ rồi |
| `arreceipt/_form.php` + `_modals.php` | LOW | `arreceipt/create.php` 174L, acceptable |
| `payment/_form.php` | LOW | `payment/create.php` 207L với complex allocation UI, khó share |
| Bank Reconciliation | HIGH | Cần module Cash Management riêng biệt |
| Cash Flow Statement | MEDIUM | Cần kế toán review để xác định indirect/direct method |
| Standard Cost Workbench | MEDIUM | Schema `cost_types`/`item_costs` sẵn, cần UX design |
| Foreign Currency Reval | MEDIUM | `is_revaluable` flag + GL accounts có rồi, cần period-close workflow |

---

## 9. Coding Patterns Áp Dụng

### 9.1 XSS-safe HTML generation trong JS

`journal_entry.js` dùng `escHtml()` khi build HTML động để tránh XSS từ tên tài khoản/đối tác:

```javascript
function escHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
```

### 9.2 AJAX với CSRF token

Tất cả AJAX requests trong finance JS modules đều include CSRF token:

```javascript
function getFormDataWithCSRF() {
    const fd = new FormData();
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');
    return fd;
}

fetch(ENTITY_CONFIG.urls.someAction, {
    method: 'POST',
    body: getFormDataWithCSRF()
}).then(r => r.json()).then(res => { ... });
```

### 9.3 Bootstrap 5 Modal pattern

```javascript
function openModal(modalId) {
    const el = document.getElementById(modalId);
    if (!el) return;
    if (typeof bootstrap !== 'undefined') {
        new bootstrap.Modal(el).show();
    } else if (typeof jQuery !== 'undefined' && $.fn.modal) {
        $(el).modal('show');
    }
}
```

### 9.4 asset_v() cho cache busting

```php
<!-- ✅ ĐÚNG -->
<script src="<?= asset_v('js/modules/finance/ap_payment.js') ?>"></script>

<!-- ❌ SAI -->
<script src="<?= URLROOT ?>/js/modules/finance/ap_payment.js?v=<?= time() ?>"></script>
```

---

## 10. Checklist Kiểm tra (Post-Phase 2)

```
PHP Syntax:
  ✓ journal/create.php — No syntax errors (109 lines)
  ✓ coa/index.php — No syntax errors (266 lines)
  ✓ costcenter/create.php, edit.php — No syntax errors (26 lines each)
  ✓ project/create.php, edit.php — No syntax errors (26 lines each)

File Integrity:
  ✓ Empty PHP files: 0 (trừ 2 known placeholders)
  ✓ No create_file used on existing files

JS Modules:
  ✓ journal_entry.js — addRow/removeRow/calcTotal + escHtml
  ✓ coa.js — openModalAdd/openModalEdit + Select2 init
  ✓ ap_payment.js — multi-invoice allocation
  ✓ ar_receipt.js — partner balance + allocation

Asset Cache Busting:
  ✓ Tất cả <script src> dùng asset_v()
  ✓ Không có ?v=time() hardcoded

View Size:
  ✓ payment/create.php: 207 lines (was 516)
  ✓ journal/create.php: 109 lines (was 239)
  ✓ coa/index.php: 266 lines (was 306)
  ✓ costcenter/create.php: 26 lines (shared _form)
  ✓ project/create.php: 26 lines (shared _form)
```
