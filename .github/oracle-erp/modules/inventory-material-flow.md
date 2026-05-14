# Luồng Nguyên Vật Liệu & Hàng Hóa — Material Flow Reference

> **Oracle EBS Reference:** Oracle Inventory User's Guide + Oracle WIP User's Guide + Oracle OM User's Guide  
> **Phạm vi:** Toàn bộ vòng đời vật tư — từ khi mua vào đến khi xuất bán hoặc tiêu hao trong sản xuất  
> **Cập nhật:** 2026-03-26

---

## 1. Tổng Quan Luồng (Big Picture)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         FULL MATERIAL JOURNEY                               │
│                                                                             │
│  SUPPLIER ──► [GRN/Receipt] ──► KHO NVL ──────────────────► KHO TP          │
│                    │              │                              │          │
│                    │         [Transfer]                   [WIP Completion]  │
│                    │              │                              │          │
│                    │         KHO WIP ◄── [Mat. Issue] ──────────┘           │
│                    │              │                                         │
│                    │         [WIP Issue] → Tiêu hao tại Work Center         │
│                    │              │                                         │
│                    │         [Mat. Return] → NVL thừa trả về kho WIP        │
│                    │                                                        │
│  CUSTOMER ◄── [Ship/SO] ◄── KHO TP                                          │
│       │                       │                                             │
│  [RMA Return] ──────────────►─┘                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Phân loại vật tư theo vòng đời:

| Loại | Từ | Đến | Transaction Type |
|------|-----|------|-----------------|
| NVL (Raw Material) | Supplier | Kho NVL → WIP | IMPORT_PO → EXPORT_PRODUCTION |
| Bán thành phẩm (WIP) | Kho NVL | Kho TP | EXPORT_PRODUCTION → IMPORT_PRODUCTION |
| Thành phẩm (FG) | WIP | Kho TP | IMPORT_PRODUCTION |
| Hàng bán (SO) | Kho TP | Customer | EXPORT_SO / EXPORT_SALE |
| Hàng trả về (Return) | Customer | Kho TP | IMPORT_RETURN / RETURN_SALE |
| Hàng trả NCC (Vendor Return) | Kho | Supplier | EXPORT_RETURN |

---

## 2. Luồng 1 — Mua Hàng → Nhập Kho NVL (Procurement to Receipt)

### 2.1 Flow Diagram

```
PR Tạo
  │
  ▼
PR Approved (phê duyệt nội bộ)
  │
  ▼
Convert → PO (Purchase Order)
  │
  ▼
PO Approved (gửi NCC)
  │
  ▼
GRN — Nhận hàng từ NCC (Goods Receipt Note)
  │
  ├── Nhận đủ   → PO Line status = RECEIVED → PO Closed
  └── Nhận thiếu → PO Line status = PARTIAL  → Còn mở chờ nhận tiếp
        │
        ▼
  inventory_transactions (type = IMPORT_PO)
        │
        ▼
  warehouse_stocks +qty (Kho NVL)
        │
        ▼
  AP Invoice (Matching PO ↔ Receipt ↔ Invoice)
        │
        ▼
  AP Payment
```

### 2.2 Bảng DB tham chiếu

| Bước | Bảng chính | Cột quan trọng |
|------|-----------|----------------|
| PR | `purchase_requests` + `purchase_request_lines` | `status`, `site_id` |
| PO | `purchase_orders` + `purchase_order_lines` | `status`, `supplier_id`, `total_amount` |
| GRN (Receipt) | `inventory_receipts` + `inventory_receipt_lines` | `qty_received`, `po_line_id` |
| Stock update | `warehouse_stocks` | `quantity`, `average_cost` |
| Transaction log | `inventory_transactions` + `inventory_transaction_details` | `type_id`, `source_type='PO'`, `source_id=po.id` |
| AP Invoice | `ap_invoices` + `ap_invoice_lines` | `po_id`, `status` |

### 2.3 Tác động tồn kho

```
warehouse_stocks:
  + quantity      += qty_received
  + average_cost   = (old_qty × old_cost + recv_qty × unit_cost) ÷ (old_qty + recv_qty)
```

### 2.4 GL Entries (Oracle 3-way match)

```
Tại thời điểm GRN:
  Nợ  TK 152/153 (Inventory — NVL/Hàng hóa)    +recv_value
  Có  TK 331     (Phải trả NCC / AP Accrual)    +recv_value

Tại thời điểm Invoice Match:
  Nợ  TK 331     (AP Accrual)                   +inv_value
  Có  TK 331.1   (Phải trả NCC — Invoice)        +inv_value
  [± Price Variance nếu đơn giá PO ≠ Invoice]

Tại thời điểm Payment:
  Nợ  TK 331.1   (Phải trả NCC)
  Có  TK 112     (Tiền gửi ngân hàng)
```

---

## 3. Luồng 2 — Kho NVL → Sản Xuất (Material to WIP)

### 3.1 Overview

Ba transaction types liên quan:

| Name | Oracle Type | Tác động WO | Tác động Kho | Code |
|------|-------------|-------------|--------------|------|
| Material Issue | Sub-Inventory Transfer | `work_order_materials.qty_issued` += | WIP +qty, NVL -qty | `TRANSFER` (sub-inv) |
| WIP Issue | WIP Component Issue (Type 35) | `work_order_materials.qty_consumed` += | WIP -qty (tiêu hao thực) | `EXPORT_PRODUCTION` |
| WIP Component Return | WIP Component Return (Type 43) | `work_order_materials.qty_issued` -= | WIP +qty | `RETURN_PRODUCTION` |

> **Phân biệt quan trọng:**  
> - **Material Issue** = đẩy NVL vật lý từ kho NVL sang kho WIP (staging). Chưa tiêu hao.  
> - **WIP Issue** = ghi nhận tiêu hao thực tế tại Work Center. Giảm tồn WIP.  
> - **WIP Return** = trả NVL thừa từ kho WIP về kho NVL hoặc giữ lại WIP.

---

### 3.2 Luồng 2A — Material Issue (Sub-Inventory Transfer)

```
Work Order Released/In-Progress
  │
  ▼
Kho thủ kho chuẩn bị NVL theo BOM
  │
  ▼
inventory_transactions (type = TRANSFER, source_type = 'WO', source_id = wo.id)
  │
  ├── Kho NVL: warehouse_stocks  -qty
  └── Kho WIP: warehouse_stocks  +qty
        │
        ▼
  work_order_materials.qty_issued  +=qty  (tracking đã xuất kho staging)
```

**Bảng DB:**

| Bảng | Cột thay đổi | Chiều |
|------|-------------|-------|
| `warehouse_stocks` (kho nguồn = NVL) | `quantity` | − |
| `warehouse_stocks` (kho đích = WIP) | `quantity` | + |
| `work_order_materials` | `qty_issued` | + |
| `inventory_transactions` | header record | INSERT |
| `inventory_transaction_details` | `work_order_id` | INSERT |

**GL:**
```
Nợ  TK 154 (WIP — Nguyên vật liệu tại WIP)    +transfer_value
Có  TK 152 (Kho NVL)                           +transfer_value
[Tại thời điểm chuyển kho nội bộ]
```

---

### 3.3 Luồng 2B — WIP Issue (Oracle Component Issue)

> Ghi nhận tiêu hao NVL THỰC TẾ tại Work Center  
> Oracle Transaction Type 35

```
Thủ kho/Shop Floor khai báo đã dùng NVL tại Work Center
  │
  ▼
inventory_transactions (type = EXPORT_PRODUCTION, source_type = 'WO')
  │
  └── Kho WIP: warehouse_stocks  -qty (tiêu hao thực)
        │
        ▼
  work_order_materials.qty_consumed +=qty
```

**Bảng DB:**

| Bảng | Cột thay đổi | Chiều |
|------|-------------|-------|
| `warehouse_stocks` (kho WIP) | `quantity` | − |
| `work_order_materials` | `qty_consumed` | + |
| `inventory_transactions` | `type=EXPORT_PRODUCTION` | INSERT |

**GL:**
```
Nợ  TK 154.1 (WIP — Chi phí NVL tiêu hao)   +issue_value
Có  TK 154   (WIP — Tài sản tại WIP)          +issue_value
[Chuyển từ WIP Asset → WIP Cost]
```

---

### 3.4 Luồng 2C — WIP Component Return (Oracle Type 43)

> Trả NVL **thừa** sau sản xuất về kho WIP (hoặc kho NVL nếu Transfer tiếp)  
> KHÔNG phải đảo phiếu sai — chỉ trả vật tư thực sự dư thừa

```
Khi WO hoàn thành/gần hoàn thành có NVL thừa
  │
  ▼
Chọn WO → Xem work_order_materials.qty_issued (đã kéo ra)
  │
  ▼
Nhập SL hoàn trả (≤ qty_issued - qty_đã_return_trước)
  │
  ▼
inventory_transactions (type = RETURN_PRODUCTION, source_type = 'WO')
  │
  └── Kho WIP: warehouse_stocks  +qty
        │
        ▼
  work_order_materials.qty_issued  -=qty (NVL kéo ra → giảm phản ánh đã trả)
```

**Return Window:**
```
returnable_qty = qty_issued - SUM(đã return trước đó)
```

**Bảng DB:**

| Bảng | Cột thay đổi | Chiều |
|------|-------------|-------|
| `warehouse_stocks` (kho WIP) | `quantity` | + |
| `work_order_materials` | `qty_issued` | − |
| `inventory_transactions` | `type=RETURN_PRODUCTION`, `source_type='WO'` | INSERT |
| `inventory_transaction_details` | `work_order_id` | INSERT |

**GL:**
```
Nợ  TK 152/153 (Inventory — NVL)    +return_value   [+kho nhận lại]
Có  TK 154     (WIP)                 +return_value   [−WIP cost]
[Ngược chiều WIP Issue]
```

---

## 4. Luồng 3 — Sản Xuất → Nhập Thành Phẩm (WIP Completion)

### 4.1 Flow Diagram

```
Shop Floor báo cáo hoàn thành WO (qty_produced)
  │
  ▼
WIP Completion transaction
  │
  ├── inventory_transactions (type = IMPORT_PRODUCTION)
  │     └── Kho TP: warehouse_stocks +qty (thành phẩm)
  │
  ├── work_orders.qty_completed += qty
  │
  └── Nếu qty_completed >= qty_planned → WO status = COMPLETED
        │
        ▼
  Scrap (nếu có):
    inventory_transactions (type = EXPORT_DAMAGE)
    └── Kho WIP: warehouse_stocks -qty_scrap
```

### 4.2 Cost Roll-up (Oracle WIP Completion Cost)

```
Cost của 1 thành phẩm hoàn thành:
  = (Tổng NVL tiêu hao × unit_cost) + Chi phí nhân công + Overhead
  = SUM(qty_consumed × unit_cost per material)   ← NVL (BOM-based)
    + SUM(hours × labor_rate per operation)       ← Routing
    + SUM(machine_hours × overhead_rate)          ← Overhead
```

**Bảng DB:**

| Bảng | Cột thay đổi | Chiều |
|------|-------------|-------|
| `warehouse_stocks` (kho TP) | `quantity`, `average_cost` | + |
| `work_orders` | `qty_completed` | + |
| `inventory_transactions` | `type=IMPORT_PRODUCTION` | INSERT |

**GL:**
```
Nợ  TK 155 (Kho thành phẩm)         +completion_value
Có  TK 154 (WIP — Chi phí sản xuất) +completion_value

[Scrap GL:]
Nợ  TK 632 (Chi phí hỏng ngoài định mức)  +scrap_value
Có  TK 154 (WIP)                            +scrap_value
```

---

## 5. Luồng 4 — Bán Hàng → Xuất Kho (Order Fulfillment)

### 5.1 Flow Diagram

```
Customer → Sales Quote (SQ)
              │
              ▼
         Convert to Sales Order (SO)
              │
              ▼
         SO Approved (Credit Check OK + ATP Check)
              │
              ▼
         Pick Release → Warehouse Pick List
              │
              ▼
         Pick Confirm (chọn lot, bin, actual qty)
              │
              ▼
         Ship Confirm (giao hàng, carrier, tracking)
              │
              ├── inventory_transactions (type = EXPORT_SO)
              │     └── Kho TP: warehouse_stocks -qty
              │
              └── Delivery Note / Packing Slip (in phiếu giao hàng)
                    │
                    ▼
              AR Invoice (tạo hóa đơn bán hàng → Phải thu)
                    │
                    ▼
              AR Receipt (khách thanh toán)
```

### 5.2 Bảng DB

| Bước | Bảng chính |
|------|-----------|
| SO Header | `sales_orders` |
| SO Lines | `sales_order_lines` |
| Delivery | `inventory_transactions` (`type=EXPORT_SO`) |
| Stock update | `warehouse_stocks` (−qty) |
| AR Invoice | `ar_invoices` |

### 5.3 GL Entries

```
Tại thời điểm Ship/Xuất kho:
  Nợ  TK 632 (Giá vốn hàng bán — COGS)   +cogs_value    [cost_based]
  Có  TK 155 (Kho thành phẩm)             +cogs_value

Tại thời điểm Invoice (doanh thu):
  Nợ  TK 131 (Phải thu khách hàng)        +invoice_value
  Có  TK 511 (Doanh thu bán hàng)         +revenue_value
  Có  TK 3331 (Thuế GTGT đầu ra - 10%)   +vat_value

Tại thời điểm khách thanh toán:
  Nợ  TK 112 (Tiền gửi ngân hàng)         +payment
  Có  TK 131 (Phải thu KH)                +payment
```

---

## 6. Luồng 5 — Hàng Trả Về (Returns)

### 6.1 Khách Trả Hàng (Customer Return / RMA)

```
Khách yêu cầu trả hàng → Return Merchandise Authorization (RMA)
  │
  ▼
Nhận hàng trả về (kiểm tra chất lượng)
  │
  ├── Hàng đạt → Nhập lại Kho TP
  │     inventory_transactions (type = RETURN_SALE / IMPORT_RETURN)
  │     └── warehouse_stocks (Kho TP) +qty
  │
  └── Hàng hỏng → Nhập Kho Scrap / Hủy
        inventory_transactions (type = EXPORT_DAMAGE)
```

**GL (Khách trả hàng):**
```
Nợ  TK 155 (Kho TP — hàng nhận lại)    +return_cost
Có  TK 632 (Giá vốn — hoàn nhập)        +return_cost

[Credit Note cho khách:]
Nợ  TK 511 (Doanh thu — giảm)           +return_revenue
Có  TK 131 (Phải thu — giảm)             +return_revenue
```

### 6.2 Trả Hàng Nhà Cung Cấp (Vendor Return)

```
Phát hiện hàng NCC giao không đạt (QC fail, sai spec, ...)
  │
  ▼
inventory_transactions (type = EXPORT_RETURN)
  └── warehouse_stocks (Kho NVL) -qty
        │
        ▼
Hủy hoặc giảm AP Invoice (Credit Memo từ NCC)
```

**GL:**
```
Nợ  TK 331 (Phải trả NCC — giảm)        +return_value
Có  TK 152 (Kho NVL — xuất trả)          +return_value
```

---

## 7. Luồng 6 — Chuyển Kho Nội Bộ (Transfer)

### 7.1 Sub-Inventory Transfer (cùng organization)

```
Kho A (NVL, WIP, TP, Scrap...) → Kho B
  │
  ▼
inventory_transactions (type = TRANSFER)
  ├── source_warehouse_id: Kho A → warehouse_stocks -qty
  └── dest_warehouse_id:   Kho B → warehouse_stocks +qty
[Không thay đổi average_cost — chỉ dịch chuyển vị trí]
```

**GL (Transfer nội bộ — Oracle không tạo GL):**
```
Không phát sinh GL entry khi chuyển kho cùng tổ chức
[Chỉ tổng qty toàn hệ thống không đổi]
```

### 7.2 Organization Transfer (khác site — Oracle Interorg)

> Chưa implement, ghi nhận cho roadmap

```
Site A Shipping Org → Transit Inventory → Site B Receiving Org
[Phát sinh Interorg GL: TK Liên đơn vị]
```

---

## 8. Ma Trận Transaction Types vs Tác Động

| Transaction Type | Kho Nguồn | Kho Đích | qty_issued | qty_consumed | qty_completed | GL Entries |
|----------------|-----------|----------|-----------|-------------|--------------|-----------|
| IMPORT_PO | — | Kho NVL | — | — | — | 152 Dr, 331 Cr |
| TRANSFER (Mat.Issue) | Kho NVL | Kho WIP | + | — | — | 154 Dr, 152 Cr |
| EXPORT_PRODUCTION (WIP Issue) | Kho WIP | — | — | + | — | 154.1 Dr, 154 Cr |
| RETURN_PRODUCTION (WIP Return) | — | Kho WIP | − | — | — | 152 Dr, 154 Cr |
| IMPORT_PRODUCTION (WIP Completion) | — | Kho TP | — | — | + | 155 Dr, 154 Cr |
| EXPORT_SO / EXPORT_SALE | Kho TP | — | — | — | — | 632 Dr, 155 Cr |
| RETURN_SALE / IMPORT_RETURN | — | Kho TP | — | — | — | 155 Dr, 632 Cr |
| EXPORT_RETURN | Kho NVL | — | — | — | — | 331 Dr, 152 Cr |
| EXPORT_DAMAGE | Kho WIP/TP | — | — | — | — | 632 Dr, 154/155 Cr |
| TRANSFER (internal) | Kho A | Kho B | — | — | — | (Không phát sinh GL) |
| OPENING_BALANCE | — | Kho | — | — | — | 152/155 Dr, 411/331 Cr |

---

## 9. Tác Động Đến work_order_materials

> Bảng tracking tình trạng NVL theo từng Work Order

```
work_order_materials
├── qty_planned      — Kế hoạch theo BOM × qty WO (không đổi sau khi WO released)
├── qty_issued       — Đã kéo ra kho WIP staging (Material Issue)
│                      ▲ tăng khi TRANSFER/Material Issue
│                      ▼ giảm khi WIP Component Return
├── qty_consumed     — Tiêu hao thực tế tại Work Center (WIP Issue)
│                      ▲ tăng khi EXPORT_PRODUCTION/WIP Issue
│                      [không bao giờ giảm bởi Return — chỉ tăng]
└── variance         — qty_consumed − qty_planned  [đo lệch thực tế vs kế hoạch]
```

**Cột nào giảm khi nào:**
```
qty_issued  ---[giảm]--→ WIP Component Return (RETURN_PRODUCTION)
qty_consumed ---[KHÔNG giảm]--- (chỉ tăng, không reversible)
```

---

## 10. Tác Động Đến warehouse_stocks (Tổng Hợp)

```sql
-- Xem tất cả movements theo product:
SELECT
    t.code,
    tt.name AS transaction_type,
    t.date_action,
    CASE WHEN d.warehouse_id = t.dest_warehouse_id THEN +d.quantity
         ELSE -d.quantity
    END AS qty_change,
    d.unit_cost,
    t.source_type, t.source_id
FROM inventory_transactions t
JOIN inventory_transaction_details d ON d.transaction_id = t.id
JOIN transaction_types tt ON tt.id = t.type_id
WHERE d.product_id = :product_id
  AND t.site_id = :site_id
  AND t.status = 'approved'
ORDER BY t.date_action, t.id;
```

---

## 11. Trạng Thái Kho Theo Giai Đoạn

```
Kho NVL (Raw Material):
  Nhập:   IMPORT_PO                       [GRN từ NCC]
  Xuất:   TRANSFER → WIP                  [Material Issue]
          EXPORT_RETURN → Supplier        [Vendor Return]
          EXPORT_DAMAGE                   [Hư hỏng, mất mát]

Kho WIP (Work-In-Process):
  Nhập:   TRANSFER từ NVL                 [Material Issue]
          RETURN_PRODUCTION               [WIP Return từ WO]
  Xuất:   EXPORT_PRODUCTION               [WIP Issue tiêu hao]
          IMPORT_PRODUCTION (→ Kho TP)    [Hoàn thành WO]
          EXPORT_DAMAGE                   [Scrap]

Kho TP (Finished Goods):
  Nhập:   IMPORT_PRODUCTION               [WIP Completion]
          RETURN_SALE / IMPORT_RETURN     [Khách trả về]
          TRANSFER từ kho khác
  Xuất:   EXPORT_SO / EXPORT_SALE         [Giao khách]
          TRANSFER → kho khác
          EXPORT_DAMAGE                   [Hư hỏng]
```

---

## 12. Config Quan Trọng (organization_parameters)

| Tham số | Mô tả | Ảnh hưởng |
|---------|-------|-----------|
| `default_wip_warehouse_id` | Kho WIP mặc định | Material Issue, WIP Return |
| `default_fg_warehouse_id` | Kho TP mặc định | WIP Completion |
| `wip_return_direct_post` | 1 = post ngay, 0 = pending | WIP Component Return |
| `wip_issue_require_wo` | 1 = bắt buộc chọn WO khi WIP Issue | WIP Issue form |
| `material_issue_direct_post` | 1 = post ngay | Material Issue |
| `costing_method` | `weighted_average` / `standard` / `fifo` | Tính giá trị tồn kho |

---

## 13. Kiểm Soát Số Lượng — Quantity Check Points

```
Điểm kiểm soát quan trọng theo Oracle:

[Nhập kho NVL]
  Kiểm tra: SO/PO qty ordered vs qty received (tolerance)
  Block:     Nếu qty_received > qty_ordered × (1 + over_receipt_tolerance)

[Xuất NVL cho SX (Material Issue)]
  Kiểm tra: qty_issued so với BOM qty × WO qty (overpick check)
  Cảnh báo: qty_issued > qty_planned (push mode — có thể vượt)

[WIP Issue]
  Kiểm tra: qty_consumed vs tồn kho WIP hiện tại
  Block:     qty_consumed > warehouse_stocks.quantity (thiếu tồn)

[WIP Return]
  Kiểm tra: qty_return ≤ (qty_issued − qty_đã_return)
  Block:     Vượt quá return window

[WIP Completion]
  Kiểm tra: qty_completed ≤ qty_planned (soft warning nếu vượt)
  Block:     WO phải ở trạng thái released / in_progress

[Xuất kho TP — SO Fulfillment]
  Kiểm tra: ATP (Available to Promise) = on_hand − reserved − open_so
  Block:     qty_ship > available_qty (hard block hoặc backorder tùy config)
```

---

## 14. Tích Hợp Module Cross-Reference

| Event | Module nguồn | Module đích | Trigger |
|-------|-------------|------------|---------|
| GRN hoàn thành | Purchasing (PO) | Inventory (INV) | `InventoryReceiptService::createReceipt()` |
| WO Released | Production (WIP) | Inventory (INV) | `WorkOrderService::release()` → tạo WOM |
| Material Issue | Inventory (INV) | Production (WIP) | `MaterialIssueService::issue()` → cập nhật qty_issued |
| WIP Issue | Production (WIP) | Inventory (INV) | `WipIssueService::postIssue()` → cập nhật qty_consumed |
| WIP Return | Inventory (INV) | Production (WIP) | `MaterialReturnService::createReturn()` → giảm qty_issued |
| WIP Completion | Production (WIP) | Inventory (INV) | `WipCompletionService::complete()` → nhập TP |
| SO Shipped | Sales (OM) | Inventory (INV) | `DeliveryNoteService::confirm()` → giảm TP |
| GL Auto Post | Inventory (INV) | Finance (GL) | `AutoAccounting_*.php` (post-commit) |

---

## 15. Tài Liệu Liên Quan

| Tài liệu | File |
|---------|------|
| Inventory module spec | `inventory-inv.md` |
| WIP + BOM spec | `production-wip.md` |
| Purchasing spec | `purchasing-po.md` |
| Sales OM spec | `sales-om.md` |
| GL + AP spec | `finance-gl-ap.md` |
| WIP Issue reference | `inventory-wipissue-reference.md` |
| Oracle compliance docs | `../../docs/WIP_Oracle_Compliance_Upgrade.md` |
| Enterprise patterns | `../architecture/enterprise-patterns.md` |
