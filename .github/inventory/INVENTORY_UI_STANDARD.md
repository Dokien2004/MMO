# Inventory Module — UI Style Standard (Chuẩn Giao diện)

> **Tài liệu chuẩn hóa giao diện** cho toàn bộ module Inventory.  
> Gold Standard Reference: `app/views/inventory/receipt/_form.php`  
> Cập nhật: 21/04/2026

---

## 1. Tổng quan Kiến trúc Giao diện

Mọi entity trong module Inventory tuân theo kiến trúc **WMS Full-Screen Layout**:

```
┌─────────────────────────────────────────────┐
│  app-header (fixed top, shadow)             │
│  ┌─ Back ─ Icon ─ Title/Subtitle ─ Stats ─┐│
│  └────────────────────────────────────────┘ │
├─────────────────────────────────────────────┤
│  app-body (scrollable, flex: 1 1 auto)      │
│  ┌── Sidebar ──┬── Main Content ──────────┐ │
│  │  Card Info   │  Card Hàng hóa          │ │
│  │  (col-lg-3)  │  (col-lg-9)             │ │
│  │              │  ┌── Table ───────────┐  │ │
│  │  • Kho       │  │ # │SP│Lô│...│Xóa  │  │ │
│  │  • Ngày      │  │───│──│──│───│───   │  │ │
│  │  • Ref       │  │ 1 │..│..│   │ 🗑  │  │ │
│  │  • Ghi chú   │  └──────────────────┘  │ │
│  └──────────────┴────────────────────────┘ │
├─────────────────────────────────────────────┤
│  action-bar (fixed bottom, z-index: 1040)   │
│  ┌─ Summary (Dòng + Tổng SL) ── Buttons ──┐│
│  └─────────────────────────────────────────┘│
└─────────────────────────────────────────────┘
```

---

## 2. CSS Layout Standard (Bắt buộc)

Mỗi `_form.php` phải định nghĩa **LOCAL** `<style>` block với các class sau:

```css
/* ═══ LAYOUT (bắt buộc — copy nguyên) ═══ */
body { overflow: hidden; }
.app-container {
    display: flex; flex-direction: column;
    height: calc(100vh - 60px);
    background-color: #f4f6f9;
    margin: 0; padding: 0;
}
.app-header {
    flex: 0 0 auto;
    background: #fff;
    border-bottom: 1px solid #dee2e6;
    padding: 10px 20px;
    display: flex; justify-content: space-between; align-items: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
.app-body {
    flex: 1 1 auto;
    overflow-y: auto;
    padding: 15px;
    padding-bottom: 70px;   /* Chừa chỗ cho action-bar */
    scrollbar-width: thin;
}
.action-bar {
    position: fixed; bottom: 0; left: 0; right: 0;
    background: #fff;
    padding: 10px 20px;
    box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
    z-index: 1040;
    display: flex; justify-content: space-between; align-items: center;
    border-top: 1px solid #dee2e6;
}

/* ═══ COMPONENTS (bắt buộc) ═══ */
.form-label { font-weight: 600; font-size: 0.85rem; color: #555; }
.form-control, .form-select { font-size: 0.9rem; }
.table-sm td, .table-sm th { vertical-align: middle; }
.fw-500 { font-weight: 500; }
.select2-container--bootstrap-5 .select2-selection { min-height: 38px; }
.table-responsive-modal { max-height: 60vh; overflow-y: auto; }
.table-sticky thead th {
    position: sticky; top: 0; z-index: 2;
    background-color: #f8f9fa;
}
```

### CSS Entity-Specific (tùy chỉnh)

Mỗi entity có thể thêm styles riêng **SAU** block layout chuẩn:

```css
/* Entity-specific: dùng class prefix riêng */
.tbl-receipt { /* Receipt table */ }
.tbl-issue   { /* Material Issue table */ }
.tbl-pick    { /* GI Request / Pick table */ }
.tbl-adj     { /* Stock Adjustment table */ }
.tbl-return  { /* Material Return table */ }
.tbl-gi      { /* GI Sales table */ }
```

---

## 3. HTML Structure Standard

### 3.1 Container Wrapper

```html
<div class="container-fluid px-0">
    <form action="..." method="POST" class="app-container" id="{entity}Form">
        <?php csrf_field(); ?>
        <!-- header → body → action-bar -->
    </form>
</div>
```

### 3.2 App Header

```html
<div class="app-header">
    <div class="d-flex align-items-center gap-3">
        <!-- Back button -->
        <a href="<?= $backUrl ?>" class="btn btn-outline-secondary btn-sm fw-bold">
            <i class="fas fa-arrow-left"></i>
        </a>

        <!-- Icon circle (theme-colored) -->
        <div class="bg-<?= $themeColor ?> bg-opacity-10 text-<?= $themeColor ?>
                        rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:36px;height:36px;">
            <i class="fas fa-<?= $isEdit ? 'pen' : '{entity-icon}' ?>"></i>
        </div>

        <!-- Title + Subtitle -->
        <div>
            <h5 class="mb-0 fw-bold text-dark" style="font-size:1rem;">
                <?= $isEdit ? 'Cập nhật' : 'Tạo mới' ?> {Entity Name}
                <?php if ($isEdit): ?>
                    <span class="font-monospace text-primary ms-2" style="font-size:.88rem">
                        <?= e($trx->code) ?>
                    </span>
                <?php endif; ?>
            </h5>
            <p class="text-muted small mb-0">
                <?= $isEdit ? '<i class="fas fa-edit me-1"></i>Chỉnh sửa...' : 'Mô tả...' ?>
            </p>
        </div>
    </div>

    <!-- Summary Stats (phải) -->
    <div class="d-flex gap-3 text-center align-items-center">
        <div class="small"><span class="text-muted">Dòng:</span> <strong class="text-primary" id="summaryLines">0</strong></div>
        <div class="small"><span class="text-muted">Tổng SL:</span> <strong class="text-dark" id="summaryQty">0</strong></div>
    </div>
</div>
```

**Theme Color quy ước:**
| Mode | $themeColor | Icon |
|------|-------------|------|
| Create | `success` | Entity-specific (truck-loading, exchange-alt, random, ...) |
| Edit | `warning` | `pen` |

### 3.3 App Body — Sidebar + Main Content

```html
<div class="app-body">
    <?php flash('stock_msg'); ?>
    <div class="row g-3">

        <!-- Sidebar: Thông tin chung (col-lg-3) -->
        <div class="col-lg-3">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white fw-bold text-primary border-bottom py-2">
                    <i class="fas fa-info-circle me-1"></i> Thông tin chung
                </div>
                <div class="card-body bg-light">
                    <!-- Form fields: Kho, NCC, Ngày, Ref, Ghi chú -->
                </div>
            </div>
        </div>

        <!-- Main: Chi tiết Hàng hóa (col-lg-9) -->
        <div class="col-lg-9">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-primary" style="font-size:.9rem">
                            <i class="fas fa-boxes me-1"></i> Chi tiết Hàng hóa
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="openPicker()">
                            <i class="fas fa-plus-circle me-1"></i> Thêm hàng
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0" id="mainTable">
                            <thead class="table-light">...</thead>
                            <tbody id="mainTableBody">...</tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
```

**Card quy ước:**
- `card shadow-sm border-0` — không viền, shadow nhẹ
- `card-header bg-white` — nền trắng, viền dưới
- `card-body bg-light` — nền xám nhạt (chỉ sidebar)
- Main card body: `p-0` (table chiếm hết)

### 3.4 Action Bar

```html
<div class="action-bar">
    <!-- Tóm tắt (trái) -->
    <div class="d-flex gap-3 align-items-center small">
        <span class="text-muted">Dòng: <strong class="text-primary" id="summaryLines2">0</strong></span>
        <span class="text-muted">Tổng SL: <strong class="text-dark" id="summaryQty2">0</strong></span>
    </div>

    <!-- Buttons (phải) -->
    <div class="d-flex gap-2">
        <a href="<?= $backUrl ?>" class="btn btn-light btn-sm border fw-bold px-3">
            <i class="fas fa-times me-1"></i> Hủy
        </a>
        <button type="button" class="btn btn-sm btn-<?= $themeColor ?> fw-bold px-4" id="btnSubmit">
            <i class="fas fa-save me-1"></i>
            <?= $isEdit ? 'Lưu thay đổi' : 'Hoàn tất {Entity}' ?>
        </button>
    </div>
</div>
```

**Button quy ước:**
| Button | Class | Icon | Label |
|--------|-------|------|-------|
| Cancel | `btn-light border` | `fa-times` | "Hủy" |
| Submit Create | `btn-{themeColor}` | `fa-save` | "Hoàn tất {Entity}" |
| Submit Edit | `btn-{themeColor}` | `fa-save` | "Lưu thay đổi" |

---

## 4. Table Columns Standard

### 4.1 Main Table (Hàng hóa)

Cột bắt buộc cho mọi entity có lot tracking:

| # | Cột | Width | Align | Nội dung |
|---|-----|-------|-------|----------|
| 1 | # | 30px | center | Row index |
| 2 | Sản phẩm | auto | left | SKU (bold primary) + tên SP (dark) |
| 3 | Lô | 100-120px | left | lot_number (plain text) |
| 4 | HSD | 90-100px | center | expiry_date hoặc '—' |
| 5 | Bin | 100-130px | left | bin_code (plain text) |
| 6 | SL | 90-110px | right | `input[type=number]` border-warning, text-end |
| 7 | Diễn giải | auto | left | `input[type=text]` (optional) |
| 8 | Xóa | 35px | center | `fa-trash-alt` text-danger |

**Cột entity-specific** (thêm nếu cần):
- Receipt: Đơn giá, Thành tiền, Mã PO
- Transfer: Bin nguồn, Bin đích (2 cột riêng, 130px mỗi cột)
- Material Issue: Kho, Bin (picker style)
- GI Request: Bin gợi ý, SL Pick

### 4.2 Product Cell Rendering

```html
<!-- Chuẩn: SKU + Tên SP cùng ô -->
<td>
    <span class="fw-bold text-primary" style="font-size:.82rem">{SKU}</span>
    <span class="text-dark ms-1" style="font-size:.8rem">{Product Name}</span>
</td>
```

### 4.3 Lot/Bin Display

```html
<!-- Plain text (KHÔNG dùng badge) -->
<td class="small font-monospace">{lot_number}</td>
<td class="small">{bin_code}</td>
```

### 4.4 Quantity Input

```html
<input type="number" class="form-control form-control-sm text-end border-warning fw-bold main-qty-input"
       min="0" step="0.01" value="0.00">
```

### 4.5 Empty State

```html
<tr id="emptyRow">
    <td colspan="{total_cols}" class="text-center text-muted py-4">
        <i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>
        Chưa có hàng hóa. Bấm "Thêm hàng" để bắt đầu.
    </td>
</tr>
```

---

## 5. Modal Standard

### 5.1 Stock/Lot Picker Modal

```html
<div class="modal fade" id="modalStockPicker" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xxl modal-dialog-scrollable" style="max-width:1380px">
        <div class="modal-content border-0 shadow-lg">
            <!-- Header: dark background -->
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold text-uppercase">
                    <i class="fas fa-search-plus me-2 text-warning"></i>{Title}
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <!-- Body -->
            <div class="modal-body bg-light p-0">
                <!-- Filter bar (sticky) -->
                <div class="card card-body py-2 mb-0 border-0 border-bottom shadow-sm"
                     style="position:sticky;top:0;z-index:20;background:#fff;border-radius:0;">
                    <!-- Filter inputs -->
                </div>

                <!-- Content -->
                <div id="pickerContainer" class="p-3">...</div>
            </div>

            <!-- Footer -->
            <div class="modal-footer bg-white py-2">
                <div class="me-auto text-primary fw-bold small" id="countSelect">Đã chọn: 0 dòng</div>
                <button type="button" class="btn btn-light btn-sm border" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary btn-sm fw-bold px-4" onclick="addSelected()">
                    <i class="fas fa-check me-1"></i> Thêm vào phiếu
                </button>
            </div>
        </div>
    </div>
</div>
```

### 5.2 Confirm Modal

```html
<div class="modal fade" id="modalConfirmSubmit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-{themeColor} text-{dark/white} py-2">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-check-circle me-2"></i>Xác nhận Lưu
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <p class="mb-1 fs-5">Bạn có chắc chắn muốn lưu phiếu này?</p>
                <small class="text-muted">Vui lòng kiểm tra kỹ...</small>
            </div>
            <div class="modal-footer justify-content-center py-2">
                <button class="btn btn-secondary px-4" data-bs-dismiss="modal">Hủy bỏ</button>
                <button class="btn btn-{themeColor} px-4 fw-bold" onclick="submitForm()">
                    <i class="fas fa-save me-2"></i> Đồng ý Lưu
                </button>
            </div>
        </div>
    </div>
</div>
```

---

## 6. Partner/Lot Info Standard (BẮT BUỘC)

### 6.1 Quy tắc: Mọi nơi hiển thị Lot phải kèm Partner (NCC)

| Nơi hiển thị | Cách hiển thị Partner |
|---------------|----------------------|
| **Stock Picker Modal** | Cột riêng "Nguồn (PO / NCC)": PO code bold + supplier name muted |
| **PO Picker Modal** | PO group header: icon building + partner_name sau PO code |
| **Lot Dropdown** (inline picker) | Append "— NCC: {name}" vào label dropdown |
| **Show page** `_show_items_table.php` | Cột riêng hoặc tooltip trên lot cell |

### 6.2 Backend: SQL Join Pattern

Khi query lot data, **LUÔN** join `partners` table:

```sql
-- Qua product_lots.partner_id
SELECT pl.lot_number, pl.expiry_date, ptr.name AS partner_name
FROM product_lots pl
LEFT JOIN partners ptr ON pl.partner_id = ptr.id
WHERE ...

-- Qua PO → partner (cho receipt/PO context)
SELECT po.code AS po_code, ptr.name AS partner_name
FROM purchase_orders po
LEFT JOIN partners ptr ON po.partner_id = ptr.id
WHERE ...
```

### 6.3 Frontend: JS Rendering Pattern

**Stock Picker Modal:**
```javascript
const poInfo = (s.po_code || s.supplier_name) 
    ? `<div class="small fw-bold text-dark">${_escHtml(s.po_code || '')}</div>
       <div class="small text-muted text-truncate" style="max-width:120px;"
            title="${_escHtml(s.supplier_name || '')}">${_escHtml(s.supplier_name || '')}</div>`
    : '<span class="text-muted small">-</span>';
```

**Inline Lot Dropdown:**
```javascript
// Label format: "LOT-001 (Tồn: 500) - HSD: 2024-12-31 — NCC: ABC Corp"
let label = lot.lot_number + ' (Tồn: ' + qty + ')';
if (lot.expiry_date) label += ' - HSD: ' + lot.expiry_date;
if (lot.partner_name) label += ' — NCC: ' + lot.partner_name;
```

**PO Group Header:**
```javascript
// Hiển thị partner name ngay sau PO code
${group.info.partner_name
    ? `<span class="small text-dark ms-3">
         <i class="fas fa-building me-1 text-muted"></i>${escapeHtml(group.info.partner_name)}
       </span>`
    : ''}
```

---

## 7. Show Page Standard

### 7.1 File Structure

```
app/views/inventory/{entity}/
├── show.php                  # Shell: load header + include partials
├── _show_header.php          # Breadcrumb + title + status badge
├── _show_info_card.php       # Thông tin chung (2-3 columns)
├── _show_items_table.php     # Bảng chi tiết hàng hóa
├── _show_action_bar.php      # Action buttons (approve, cancel, print...)
├── _show_modals.php          # Modals cho actions (approve, cancel, reverse)
└── _show_scripts.php         # JS cho show page
```

### 7.2 Status Badge Pattern

```php
<?php
$statusMap = [
    'draft'     => ['bg' => 'secondary', 'icon' => 'fa-edit',       'label' => 'Nháp'],
    'submitted' => ['bg' => 'info',      'icon' => 'fa-paper-plane', 'label' => 'Chờ duyệt'],
    'approved'  => ['bg' => 'success',   'icon' => 'fa-check',      'label' => 'Đã duyệt'],
    'cancelled' => ['bg' => 'danger',    'icon' => 'fa-ban',        'label' => 'Đã hủy'],
];
$s = $statusMap[$trx->status] ?? $statusMap['draft'];
?>
<span class="badge bg-<?= $s['bg'] ?>">
    <i class="fas <?= $s['icon'] ?> me-1"></i><?= $s['label'] ?>
</span>
```

---

## 8. JavaScript Standard

### 8.1 File Location

```
public/js/modules/inventory/
├── {entity}.js               # Main JS (create/edit)
├── {entity}_show.js          # Show page JS (optional)
├── inventory-common.js       # Shared utilities
```

### 8.2 Config Object Pattern

```html
<!-- Trong create.php / edit.php -->
<script>
const {ENTITY}_CONFIG = {
    isEditMode: <?= $isEdit ? 'true' : 'false' ?>,
    urls: {
        store:     '<?= URLROOT ?>/inventory/{entity}/store',
        update:    '<?= URLROOT ?>/inventory/{entity}/update/',
        getStock:  '<?= URLROOT ?>/inventory/{entity}/getStock',
        searchLots:'<?= URLROOT ?>/inventory/{entity}/searchLots',
    },
    warehouses: <?= json_encode($data['warehouses'] ?? []) ?>,
};
</script>
```

### 8.3 Summary Update Pattern

```javascript
function updateSummary() {
    const rows = document.querySelectorAll('#mainTableBody tr:not(#emptyRow)');
    let totalQty = 0;
    rows.forEach(tr => {
        const input = tr.querySelector('.main-qty-input');
        if (input) totalQty += parseFloat(input.value) || 0;
    });

    // Update cả header và action-bar
    const lineCount = rows.length;
    const qtyStr = totalQty.toLocaleString('vi-VN', { maximumFractionDigits: 2 });
    ['summaryLines', 'summaryLines2'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = lineCount;
    });
    ['summaryQty', 'summaryQty2'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = qtyStr;
    });
}
```

### 8.4 CSRF Token Pattern

```javascript
function getCsrfToken() {
    return document.querySelector('input[name="csrf_token"]')?.value || '';
}

function postWithCsrf(url, data = {}) {
    const fd = new FormData();
    fd.append('csrf_token', getCsrfToken());
    Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    return fetch(url, { method: 'POST', body: fd }).then(r => r.json());
}
```

---

## 9. Checklist Compliance — Entity Matrix

| Entity | Layout CSS | Header | Action Bar | Summary Stats | Partner/Lot | Show Partials | Score |
|--------|-----------|--------|------------|---------------|-------------|---------------|-------|
| **Receipt** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ 6 files | 100% |
| **Transfer** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ 6 files | 100% |
| **Material Issue** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ 5 files | 100% |
| **WIP Issue** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ 5 files | 100% |
| **Adjustment** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ 6 files | 100% |
| **GI Request** | ✅ | ✅ | ✅ | ✅ | N/A | ✅ 7 files | 100% |
| **GI Sales** | ✅ | ✅ | ✅ | ✅ | N/A | ✅ 5 files | 100% |
| **Mat. Requisition** | ✅ | ✅ | ✅ | ✅ | N/A | ✅ 5 files | 100% |
| **Pick Confirm** | ✅ | ✅ | ✅ | ✅ | N/A | ✅ 7 files | 100% |
| **Mat. Return** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ 5 files | 100% |
| **Trip** | ✅ | ✅ | ✅ | ⚠️ | N/A | ✅ | 95% |
| **Opening Stock** | ✅ | ✅ | ❌ | ❌ | N/A | ✅ 5 files | 80% |

---

## 10. Quy tắc khi tạo Entity mới

1. **Copy CSS block** từ Receipt `_form.php` (lines 33-80)
2. **Dùng `container-fluid px-0`** wrapper
3. **Header**: Icon circle + title + subtitle + summary stats
4. **Sidebar**: `col-lg-3`, card bg-light
5. **Main**: `col-lg-9`, table-responsive, table-sm, table-hover
6. **Action bar**: Fixed bottom, summary trái, buttons phải
7. **Modal**: `modal-xxl`, dark header, filter bar sticky
8. **Partner/Lot**: LUÔN join partners khi query lot data
9. **JS**: Config object, updateSummary(), CSRF helpers
10. **Show page**: Tách thành 6+ partials

---

## 11. Files đã Đồng bộ (Tháng 4/2026)

### Backend — Partner Info cho Lot:
- `app/models/inventory/InventoryReceiptModel.php` → join partners trong `getPendingPoItems()`
- `app/models/inventory/LotHistoryModel.php` → join partners trong `searchLotsWithStock()`
- `app/models/inventory/WipIssueModel.php` → join partners trong `searchLots()`

### Frontend — Hiển thị Partner:
- `public/js/modules/inventory/inventory_receipt.js` → PO group header hiển thị partner_name
- `public/js/modules/inventory/inventory_transfer.js` → Stock picker "Nguồn (PO / NCC)" column
- `public/js/modules/inventory/materialissue.js` → Lot dropdown "— NCC: {name}"
- `public/js/modules/inventory/wipissue-create.js` → Lot dropdown "(partner_name)"
- `public/js/modules/inventory/stock_adjustment.js` → Lot dropdown "— NCC: {name}"
