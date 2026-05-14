# Finance Module — Phase 3 UI Upgrade

> **Ngày thực hiện**: Tháng 4/2026  
> **Phạm vi**: show.php partials, Bootstrap 5 modals, JS inline cleanup  
> **Mục tiêu**: Đạt chuẩn Purchasing — không có inline function JS, tất cả show pages là shell+partials  
> **Trạng thái**: ✅ Hoàn thành 100%

---

## 1. Mục tiêu Phase 3

Phase 3 hoàn thiện các gap còn lại so với chuẩn Purchasing module:

| Gap | Trước | Sau |
|-----|-------|-----|
| `arreceipt/show.php` monolith | 209L, inline `<style>` + inline `postReceipt()` JS | 54L shell + 4 partials + external JS |
| `ar/_modals.php` missing | Dùng `confirm()` / `prompt()` native dialogs | Bootstrap 5 modals với UX chuẩn |
| `ar_invoice_show.js` | Workflow fns gọi `confirm()` / `prompt()` thô | Mở Bootstrap modal → user confirm → execute |
| `glperiod/create.php` + `edit.php` | 2 script blocks (config + form validation inline) | 1 script block (config) + validation trong `glperiod.js` |

---

## 2. Deliverables — Chi tiết

### 2.1 `arreceipt/show.php` → Shell + Partials

**Trước:** 209 dòng monolith (inline CSS `<style>`, inline HTML toolbar/table/info, inline void modal, inline `postReceipt()` JS)  
**Sau:** 54 dòng shell + 4 partials + 1 external JS file

```
app/views/finance/arreceipt/
├── show.php                    ✅ 54L — shell: header, style block, include partials, footer, config, external JS
├── _show_toolbar.php           ✅ 35L — top-panel: back button, code, status badge, Post/Void buttons
├── _show_allocations.php       ✅ 47L — left-col: allocation table (invoice_code, dates, amounts, total tfoot)
├── _show_info.php              ✅ 56L — right-col: 5 info cards:
│                                          ▸ Thông tin phiếu thu (code, date, method, currency)
│                                          ▸ Khách hàng (partner_code, partner_name)
│                                          ▸ Tổng số tiền thu (big blue amount)
│                                          ▸ Bút toán GL (je_id, je_status) — conditional
│                                          ▸ Lịch sử (creator, created_at, updater, note)
└── _modals.php                 ✅ 35L — Void modal: form POST to /arreceipt/void/{id}, CSRF, void_reason textarea

public/js/modules/finance/
└── arreceipt_show.js           ✅ 29L — postReceipt(id): confirm + fetch POST + reload
                                          Config: AR_RECEIPT_SHOW_CFG = { urls.postGl, csrfToken }
```

**Shell pattern:**
```php
<?php
require APPROOT . '/views/layouts/header.php';
$receipt  = $data['receipt'];
$allocs   = $data['allocations'];
$isDraft  = ($receipt->status === 'draft');
$isPosted = ($receipt->status === 'posted');
$isVoided = ($receipt->status === 'voided');
?>
<style>/* Layout CSS: .main-wrapper, .top-panel, .content-panel, .left-col, .right-col, etc. */</style>
<div class="container-fluid main-wrapper">
    <?php require APPROOT . '/views/finance/arreceipt/_show_toolbar.php'; ?>
    <?php flash('ar_receipt_msg'); ?>
    <div class="content-panel">
        <?php require APPROOT . '/views/finance/arreceipt/_show_allocations.php'; ?>
        <?php require APPROOT . '/views/finance/arreceipt/_show_info.php'; ?>
    </div>
</div>
<?php require APPROOT . '/views/finance/arreceipt/_modals.php'; ?>
<?php require APPROOT . '/views/layouts/footer.php'; ?>
<script>
const AR_RECEIPT_SHOW_CFG = {
    urls: { postGl: '<?= URLROOT ?>/finance/arreceipt/post_gl' },
    csrfToken: '<?= csrf_token() ?>'
};
</script>
<script src="<?= asset_v('js/modules/finance/arreceipt_show.js') ?>"></script>
```

---

### 2.2 AR Invoice: Bootstrap 5 Workflow Modals

**Trước:** `ar_invoice_show.js` dùng native `confirm()` / `prompt()` — UX kém, thiếu styling  
**Sau:** Bootstrap 5 modals với proper UX, `ar/_modals.php` được include trong show.php

**File mới: `app/views/finance/ar/_modals.php`** (155L) — 6 modals:

| Modal ID | Action | Đặc điểm |
|----------|--------|-----------|
| `#arModalSubmit` | Submit | Simple confirm, bg-primary |
| `#arModalRecall` | Recall | Simple confirm, bg-warning |
| `#arModalApprove` | Approve | Simple confirm, bg-success |
| `#arModalReject` | Reject | Textarea `#arRejectReason` bắt buộc, bg-danger |
| `#arModalPost` | Post | Warning alert (irreversible), bg-info |
| `#arModalVoid` | Void | Textarea `#arVoidReason` bắt buộc, bg-danger |

**ar_invoice_show.js — Workflow functions:**

```javascript
// Cũ (native dialog — UX kém)
function confirmSubmitArInvoice(id) {
    if (!confirm('Gửi hóa đơn này?')) return;
    _arWorkflowPost(AR_INVOICE_CFG.urls.submit, 'Gửi duyệt thành công.');
}
function confirmRejectArInvoice(id) {
    const reason = prompt('Lý do từ chối:');
    if (reason === null) return;
    _arWorkflowPost(AR_INVOICE_CFG.urls.reject, '...', { reason: reason.trim() });
}

// Mới (Bootstrap 5 modal — UX chuẩn)
function confirmSubmitArInvoice(id) {
    new bootstrap.Modal(document.getElementById('arModalSubmit')).show();
}
function confirmRejectArInvoice(id) {
    document.getElementById('arRejectReason').value = '';
    new bootstrap.Modal(document.getElementById('arModalReject')).show();
}

// Execute helpers
function _executeArWorkflow(action) {
    // Ẩn modal → _arWorkflowPost(url, successMsg)
}
function _executeArWorkflowWithReason(action, textareaId) {
    // Validate reason → ẩn modal → _arWorkflowPost(url, successMsg, { reason })
}
```

**`ar/show.php` updated:**
```php
<!-- Action bar (sticky bottom) -->
<?php require APPROOT . '/views/finance/ar/_show_action_bar.php'; ?>

<!-- Workflow Modals -->
<?php require APPROOT . '/views/finance/ar/_modals.php'; ?>

<?php require APPROOT . '/views/layouts/footer.php'; ?>
```

---

### 2.3 GL Period: Form Validation → `glperiod.js`

**Trước:** `glperiod/create.php` và `edit.php` mỗi file có 2 script blocks:
1. Config block (OK)
2. Form validation `DOMContentLoaded` handler (inline — không cần)

**Sau:** Form validation được chuyển vào `glperiod.js` `setupFormValidation()`:

```javascript
// glperiod.js — DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    setupEventListeners();
    setupSelectAllCheckbox();
    if (GLPERIOD_CONFIG.isCreateMode || GLPERIOD_CONFIG.isEditMode) {
        setupFormValidation();  // NEW
    }
});

// Validate: period_name (create only), start_date, end_date, date order
function setupFormValidation() {
    const formId = GLPERIOD_CONFIG.isCreateMode ? 'form-glperiod-create' : 'form-glperiod-edit';
    const form = document.getElementById(formId);
    if (!form) return;
    form.addEventListener('submit', function(e) {
        // ... validation với Swal.fire() fallback to alert()
    });
}
```

**Views updated:**
- `glperiod/create.php`: Removed inline form validation script block (2nd script)
- `glperiod/edit.php`: Removed inline form validation script block (2nd script)
- Both now have: 1 config block + `glperiod.js` — clean Oracle Engineer Standard V2

---

## 3. Finance View Structure After Phase 3

### `arreceipt/` directory

```
app/views/finance/arreceipt/
├── create.php              ✅ 174L — AR Receipt create form (AR_RECEIPT_CONFIG + ar_receipt.js)
├── index.php               ✅ 155L — AR Receipt list (filter, status badges, action buttons)
├── show.php                ✅ 54L — Shell (↓ từ 209L)
├── _show_toolbar.php       ✅ 35L — NEW: Top-panel toolbar
├── _show_allocations.php   ✅ 47L — NEW: Left-col allocation table
├── _show_info.php          ✅ 56L — NEW: Right-col info cards
└── _modals.php             ✅ 35L — NEW: Void modal

public/js/modules/finance/
└── arreceipt_show.js       ✅ 29L — NEW: postReceipt() AJAX
```

### `ar/` directory

```
app/views/finance/ar/
├── create.php              ✅ AR Invoice create (shell)
├── index.php               ✅ AR Invoice list
├── show.php                ✅ 96L — Shell + _modals.php include added
├── _form.php               ✅ 220L — Shared create form
├── _modals.php             ✅ 155L — NEW: 6 Bootstrap 5 workflow modals
├── _show_action_bar.php    ✅ Workflow buttons (sticky bottom)
├── _show_attachments.php   ✅ File attachments
├── _show_history.php       ✅ Approval history timeline
├── _show_lines_table.php   ✅ Invoice lines + totals
├── _show_right_panel.php   ✅ Customer + payment info
└── _show_workflow.php      ✅ 4-step stepper
```

---

## 4. Finance JS Modules — Inventory After Phase 3

| File | Lines | Purpose |
|------|-------|---------|
| `ap_payment.js` | 344L | AP Payment form (AP_PAYMENT_CONFIG) |
| `ar_receipt.js` | 318L | AR Receipt form (AR_RECEIPT_CONFIG) |
| `ar_invoice_show.js` | ~180L | AR Invoice workflow actions (Bootstrap modals) |
| `ar_invoice_form.js` | ~200L | AR Invoice create/edit form |
| `journal_entry.js` | 175L | JE form: addRow, removeRow, calcTotal |
| `journalentry_show.js` | ~120L | JE show: confirmPost, confirmDelete (SweetAlert2) |
| `coa.js` | 151L | COA SPA modal (COA_CONFIG) |
| `glperiod.js` | ~480L | GL Period list + modals + **setupFormValidation()** (Phase 3) |
| `ap_invoice_show.js` | ~300L | AP Invoice workflow actions |
| `arreceipt_show.js` | 29L | **NEW** AR Receipt post_gl AJAX |

---

## 5. Kết quả

- **Finance module đạt chuẩn Purchasing đầy đủ**: mọi show page là shell+partials, không có inline function JS
- **UX nâng cấp**: workflow actions dùng Bootstrap 5 modals thay native `confirm()`/`prompt()` (AR Invoice)
- **Code quality**: 0 inline function JS trong Finance views (chỉ còn config injection blocks — theo chuẩn)
- **Maintainability**: Form validation tập trung trong `glperiod.js` `setupFormValidation()` thay vì mỗi view một copy
