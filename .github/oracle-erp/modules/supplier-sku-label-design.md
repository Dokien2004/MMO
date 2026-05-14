# Supplier SKU on GRN & Print Label — Design Document

> **Status:** Pending Decision  
> **Created:** 2026-04-16  
> **Module:** Inventory × MasterData × PrintLabel  
> **Pattern:** Oracle ASL Snapshot — lưu mã NCC tại thời điểm giao dịch

---

## 1. Bối cảnh

Một SKU nội bộ (`products.sku`) có thể được mua từ **nhiều NCC khác nhau**, mỗi NCC có **mã hàng riêng** (`partner_product_code`) và **tên hàng riêng** (`partner_product_name`).

Dữ liệu này đã tồn tại trong:
- `product_partners` — Master data (ASL)
- `purchase_order_details` — Snapshot lúc tạo PO

Nhưng **bị đứt mạch** tại GRN và Lot → Print Label không resolve được.

---

## 2. Data Flow hiện tại

```
product_partners              purchase_order_details        inventory_transaction_details     product_lots
┌────────────────────┐        ┌────────────────────────┐    ┌────────────────────────────┐   ┌──────────────┐
│ partner_product     │  copy  │ partner_product_code ✅│    │ po_detail_id (FK) ─────────│──→│ partner_id ✅│
│   _code ✅         │──────→ │ partner_product_name ✅│    │ ❌ KHÔNG có cột riêng      │   │ ❌ KHÔNG có  │
│ partner_product     │        └────────────────────────┘    │    cho mã/tên NCC          │   │    mã/tên NCC│
│   _name ✅         │                                      └────────────────────────────┘   └──────────────┘
└────────────────────┘
```

---

## 3. Gap trong PrintLabelService

| Template category | `supplier_name` | `partner_product_code` | `partner_product_name` |
|---|---|---|---|
| **product** | ❌ | ❌ | ❌ |
| **lot** | ✅ có | ❌ thiếu | ❌ thiếu |
| **grn** | ✅ (`partner_name`) | ❌ thiếu | ❌ thiếu |

---

## 4. Phương án

### A. Resolve at query time (không thay đổi DB)

PrintLabelService JOIN ngược qua `po_detail_id` → `purchase_order_details` để lấy `partner_product_code/name`.

| Ưu điểm | Nhược điểm |
|---|---|
| Không cần migration | Chỉ hoạt động khi GRN từ PO (có `po_detail_id`) |
| Không thay đổi schema | Nhập kho thủ công → không có data |
| | Query phức tạp (JOIN sâu) |
| | Category **product** vẫn không có NCC info |

### B. Lưu trực tiếp vào GRN detail + lot ⭐ ĐỀ XUẤT

Thêm 2 cột vào mỗi bảng:

```sql
ALTER TABLE inventory_transaction_details
  ADD COLUMN partner_product_code VARCHAR(50) DEFAULT NULL COMMENT 'Mã hàng phía NCC (snapshot từ PO)',
  ADD COLUMN partner_product_name VARCHAR(150) DEFAULT NULL COMMENT 'Tên hàng phía NCC (snapshot từ PO)';

ALTER TABLE product_lots
  ADD COLUMN partner_product_code VARCHAR(50) DEFAULT NULL COMMENT 'Mã hàng phía NCC (snapshot từ GRN)',
  ADD COLUMN partner_product_name VARCHAR(150) DEFAULT NULL COMMENT 'Tên hàng phía NCC (snapshot từ GRN)';
```

Khi tạo GRN từ PO → copy `partner_product_code/name` từ `purchase_order_details`.

| Ưu điểm | Nhược điểm |
|---|---|
| Oracle-style snapshot — bảo toàn data tại thời điểm nhập kho | Cần migration |
| Query đơn giản, nhanh | Sửa logic tạo GRN |
| Lot mang theo mã NCC suốt vòng đời | |
| Nhập kho thủ công có thể nhập tay mã NCC | |

### C. Hybrid (B + fallback A)

Giống B nhưng khi cột mới rỗng → fallback JOIN qua `po_detail_id`, rồi fallback `product_partners`.

| Ưu điểm | Nhược điểm |
|---|---|
| Bao phủ 100% case kể cả data cũ | Logic phức tạp nhất |

---

## 5. Phạm vi thay đổi (Phương án B)

### 5.1 Database Migration

```sql
-- File: app/migrations/add_partner_product_to_grn_lot.sql

ALTER TABLE inventory_transaction_details
  ADD COLUMN partner_product_code VARCHAR(50) DEFAULT NULL
    COMMENT 'Mã hàng phía NCC (snapshot từ PO)' AFTER product_id,
  ADD COLUMN partner_product_name VARCHAR(150) DEFAULT NULL
    COMMENT 'Tên hàng phía NCC (snapshot từ PO)' AFTER partner_product_code;

ALTER TABLE product_lots
  ADD COLUMN partner_product_code VARCHAR(50) DEFAULT NULL
    COMMENT 'Mã hàng phía NCC (snapshot từ GRN)' AFTER partner_id,
  ADD COLUMN partner_product_name VARCHAR(150) DEFAULT NULL
    COMMENT 'Tên hàng phía NCC (snapshot từ GRN)' AFTER partner_product_code;
```

### 5.2 Model Changes

| File | Method | Thay đổi |
|---|---|---|
| `app/models/inventory/InventoryReceiptModel.php` | `createGRN()` | Copy `partner_product_code/name` từ PO detail vào GRN detail INSERT + lot INSERT |
| `app/models/inventory/InventoryReceiptModel.php` | `getReceiptDetails()` | SELECT thêm `itd.partner_product_code`, `itd.partner_product_name` |

### 5.3 Service Changes

| File | Method | Thay đổi |
|---|---|---|
| `app/services/systems/PrintLabelService.php` | `_resolveLot()` | SELECT thêm `pl.partner_product_code`, `pl.partner_product_name` |
| `app/services/systems/PrintLabelService.php` | `_resolveGrn()` | JOIN `inventory_transaction_details` để lấy `partner_product_code/name` (first line hoặc aggregated) |
| `app/services/systems/PrintLabelService.php` | `getVariableHints()` | Thêm `partner_product_code`, `partner_product_name` vào hints cho `lot` và `grn` |

### 5.4 Schema Update

| File | Thay đổi |
|---|---|
| `app/db_schema.sql` | Thêm 2 cột vào `inventory_transaction_details` + `product_lots` |

### 5.5 Template Variables mới

Sau khi implement, người dùng có thể dùng trong template HTML:

```html
<!-- Tem lô hàng (lot) -->
<div>Mã NCC: {{partner_product_code}}</div>
<div>Tên NCC: {{partner_product_name}}</div>
<div>NCC: {{supplier_name}} ({{supplier_code}})</div>

<!-- Tem phiếu nhập (grn) -->
<div>Mã hàng NCC: {{partner_product_code}}</div>
<div>Tên hàng NCC: {{partner_product_name}}</div>
```

---

## 6. Không nằm trong scope

- In tem category **product** không hiển thị mã NCC (vì 1 SKU có nhiều NCC → cần chọn NCC nào?)
- Nếu muốn in tem product kèm mã NCC → cần mở rộng generate API cho phép truyền thêm `partner_id` → resolve `product_partners`

---

## 7. Checklist triển khai

- [ ] Tạo migration `add_partner_product_to_grn_lot.sql`
- [ ] Cập nhật `db_schema.sql` — thêm 2 cột vào 2 bảng
- [ ] Sửa `InventoryReceiptModel.createGRN()` — copy mã NCC từ PO detail
- [ ] Sửa `InventoryReceiptModel.getReceiptDetails()` — SELECT thêm 2 cột
- [ ] Sửa `PrintLabelService._resolveLot()` — thêm 2 biến
- [ ] Sửa `PrintLabelService._resolveGrn()` — thêm 2 biến
- [ ] Sửa `PrintLabelService.getVariableHints()` — thêm hints
- [ ] Test: Tạo PO có mã NCC → nhập kho → verify GRN detail + lot có `partner_product_code`
- [ ] Test: In tem lot → verify `{{partner_product_code}}` render đúng
- [ ] Test: Nhập kho thủ công (không từ PO) → verify cột rỗng, tem không lỗi
