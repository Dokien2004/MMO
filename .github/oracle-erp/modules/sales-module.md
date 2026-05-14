# Sales Module — Oracle ERP Compliance Documentation

> Tài liệu này mô tả kiến trúc, trạng thái, và luồng xử lý của module Sales theo chuẩn Oracle E-Business Suite (EBS).
> Cập nhật: Tháng 1/2026

---

## 1. Tổng quan Module

Module Sales quản lý toàn bộ vòng đời bán hàng của công ty:

```
Prospect → Sales Quote (SQ) → Sales Order (SO) → GI Request → AR Invoice
```

### Entities chính

| Entity | Controller | Model | Bảng DB |
|--------|-----------|-------|---------|
| Sales Quote | `SalesQuoteController` | `SalesQuoteModel` | `sales_quotes` |
| Quote Lines | *(inline)* | `SalesQuoteLineModel` | `sales_quote_lines` |
| Sales Order | `SalesOrderController` | `SalesOrderModel` | `sales_orders` |
| SO Lines | *(inline)* | `SalesOrderLineModel` | `sales_order_lines` |
| Price List | `PriceListController` | `PriceListModel` | `price_lists`, `price_list_items` |
| Shipment Schedule | *(inline)* | `SalesShipmentScheduleModel` | `sales_shipment_schedules` |

---

## 2. Oracle EBS Mapping

| Oracle EBS Module | Factory ERP Equivalent |
|-------------------|------------------------|
| Order Management (OM) | `SalesOrderController` + `SalesOrderService` |
| Sales Orders (OM.SO) | `sales_orders` + `sales_order_lines` |
| Quoting (ASO) | `sales_quotes` + `sales_quote_lines` |
| Pricing (ONT) | `price_lists` + `PriceEngineService` |
| Shipment Schedules | `sales_shipment_schedules` |
| AR Invoice | `ar_invoices` (Finance module) |

---

## 3. Sales Quote Workflow

### Status Flow

```
draft → pending_approval → approved → converted
                       ↘ rejected → recalled → draft
```

| Status | Mô tả | Tiếng Việt |
|--------|-------|-----------|
| `draft` | Đang soạn | Nháp |
| `pending_approval` | Chờ phê duyệt | Chờ duyệt |
| `approved` | Đã duyệt (chưa tạo SO) | Đã duyệt |
| `rejected` | Bị từ chối | Từ chối |
| `converted` | Đã tạo SO | Đã chuyển SO |

### Actions / Permissions

| Action | Permission | Điều kiện |
|--------|-----------|-----------|
| Create | `sales.create_quote` | Bất kỳ |
| Edit | `sales.quote.edit` | Status = draft/rejected/recall |
| Submit | `sales.create_quote` | Chủ phiếu + status = draft |
| Approve | `sales.approve_quote` | Cấp duyệt + status = pending |
| Reject | `sales.approve_quote` | Cấp duyệt + status = pending |
| Recall | *(owner only)* | Status = pending_approval |
| Convert to SO | `sales.create_order` | Status = approved |
| Delete | `sales.delete` | Status = draft |

### Service Classes

```
app/services/sales/
├── SalesQuoteWorkflowService.php     # submit/approve/reject/recall/convert
├── SalesQuoteCalculationService.php  # Line totals, tax, margins
├── SalesQuoteAttachmentService.php   # File upload/download
└── PriceEngineService.php            # Price lookup + discount calc
```

---

## 4. Sales Order Workflow

### Status Flow (Oracle OM Lifecycle)

```
draft → pending_approval → confirmed → planning → manufacturing → shipping → completed
                       ↘ rejected
         (recall available from pending_approval → draft)
```

| Status | Oracle OM Equivalent | Mô tả |
|--------|---------------------|-------|
| `draft` | Not yet entered | Nháp |
| `pending_approval` | Awaiting approval | Chờ duyệt |
| `confirmed` | Booked | Đã xác nhận |
| `planning` | Demand staged | Lên kế hoạch |
| `manufacturing` | WIP issued | Đang sản xuất |
| `shipping` | Shipped | Đang giao |
| `completed` | Closed | Hoàn tất |
| `rejected` | Cancelled | Từ chối/Hủy |

### Actions / Permissions

| Action | Permission | Điều kiện |
|--------|-----------|-----------|
| Create | `sales.create_order` | Bất kỳ |
| Edit | `sales.edit` | draft/rejected |
| Submit | `sales.create_order` | draft, chủ phiếu |
| Approve | `sales.approve_order` | pending_approval |
| Reject | `sales.approve_order` | pending_approval |
| Recall | *(owner)* | pending_approval |
| Ship (GI) | `inventory.create_gi` | confirmed/planning |
| Copy | `sales.create_order` | Bất kỳ status |
| Print | `sales.view` | Bất kỳ |
| Delete | `sales.delete` | draft only |

### Service Classes

```
app/services/sales/
├── SalesOrderService.php              # Core CRUD + status transition
├── SalesOrderWorkflowService.php      # Approval routing + multi-level
├── SalesOrderCalculationService.php   # Totals, tax, backorder calc
├── SalesOrderAttachmentService.php    # File management
├── SalesDocumentFlowService.php       # Cross-doc traceability
├── SOEmailService.php                 # Trigger emails on status change
├── PriceEngineService.php             # Price + discount resolution
└── ShipmentScheduleService.php        # Delivery schedule management
```

---

## 5. View Architecture (Oracle Standard V2)

### Sales Order Views

```
app/views/sales/salesorder/
├── index.php                  # List with filter/search/pagination
├── create.php                 # Form tạo mới (thin, includes _form.php)
├── edit.php                   # Form chỉnh sửa (thin, includes _form.php)
├── _form.php                  # Shared create/edit form
├── show.php                   # ✅ Shell - 181 lines, all partials
├── _show_header.php           # Title bar + status badge + buttons
├── _show_workflow.php         # 4-step status stepper
├── _show_info_card.php        # Customer / dates / payment info
├── _show_items_table.php      # Line items table + shipment schedule
├── _show_history.php          # Approval timeline + delivery card
├── _show_attachments.php      # Document-level attachments
├── _show_flow.php             # Document flow tab (SO→GI→AR)
├── _show_action_bar.php       # Sticky footer action buttons
├── _modals.php                # Shared modals
└── print.php                  # Print layout

public/js/modules/sales/
├── salesorder_show.js         # ✅ All show page interactions
└── sales_order.js             # Create/edit form + AJAX
```

### Sales Quote Views

```
app/views/sales/salesquote/
├── index.php                  # List with filter/search/pagination
├── create.php                 # Form tạo mới
├── edit.php                   # Form chỉnh sửa
├── _form.php                  # Shared form
├── show.php                   # ✅ Shell - 193 lines, all partials
├── _show_header.php           # Title + status + Copy/Edit/Print
├── _show_workflow.php         # 3-step stepper (Draft→Approve→Convert)
├── _show_info_card.php        # Customer/salesman/probability/dates
├── _show_items_table.php      # Items + shipments + margin column
├── _show_history.php          # Approval timeline + validity card
├── _show_attachments.php      # File attachments
├── _show_flow.php             # Quote→SO→GI→AR flow tab
├── _show_action_bar.php       # submit/approve/convert actions
└── _modals.php                # Shared modals

public/js/modules/sales/
├── salesquote_show.js         # ✅ All show page interactions
└── sales_quote.js             # Create/edit form + AJAX
```

---

## 6. Data Models

### `sales_orders`

Key columns:
- `id`, `code` (e.g. SO250101-0001), `site_id`
- `customer_id` → partners, `salesman_id` → employees
- `order_date`, `delivery_date`, `valid_until`
- `status` (enum: draft/pending_approval/confirmed/planning/manufacturing/shipping/completed/rejected)
- `currency`, `exchange_rate`, `subtotal`, `tax_amount`, `discount_amount`, `shipping_fee`, `total_amount`
- `payment_term_id`, `price_list_id`, `incoterm`
- `ship_to_address`, `ship_to_district`, `ship_to_city`
- `customer_po` (customer's PO reference for traceability)
- `note`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`

### `sales_order_lines`

Key columns:
- `id`, `order_id`, `line_number`, `product_id`, `sku`
- `warehouse_id`, `uom_id`, `quantity`, `unit_price`
- `discount_percent`, `tax_rate`, `line_total`
- `attributes` (JSON — product variant attributes)
- `note`

### `sales_quotes`

Similar to `sales_orders` plus:
- `expiry_date` (quote validity)
- `probability` (win probability %)
- `profit_margin`, `margin_percent` (for `sales.view_revenue` permission)
- `converted_so_id` → `sales_orders`

---

## 7. Pending Work

### High Priority

- [ ] `SalesOrderExportService.php` — Excel drill-down export (SO lines + shipments + totals)
- [ ] `app/views/sales/dashboard/index.php` — Sales KPI dashboard
  - Revenue vs Target chart
  - Top customers, top products
  - Backorder alerts
  - Pending approvals
- [ ] AR Invoice auto-creation from SO approval (finance integration)

### Medium Priority

- [ ] Price List management UI (`app/views/sales/pricelist/`)
- [ ] Bulk SO approval (index page checkbox + batch approve)
- [ ] SO duplicate detection (same customer + product within date range)
- [ ] Quote to SO conversion validates stock availability (ATP check)

### Low Priority

- [ ] SO amendment after approval (with re-approval flow)
- [ ] Sales forecast based on historical orders
- [ ] Mobile-optimized SO view for field sales staff

---

## 8. API Endpoints Reference

| URL Pattern | Controller Method | Purpose |
|------------|------------------|---------|
| `GET /sales/salesorder` | `index()` | List SOs |
| `GET /sales/salesorder/show/{id}` | `show($id)` | SO detail |
| `POST /sales/salesorder/store` | `store()` | Create SO |
| `POST /sales/salesorder/update/{id}` | `update($id)` | Update SO |
| `POST /sales/salesorder/submit/{id}` | `submit($id)` | Submit for approval |
| `POST /sales/salesorder/approve/{id}` | `approve($id)` | Approve SO |
| `POST /sales/salesorder/reject/{id}` | `reject($id)` | Reject SO |
| `POST /sales/salesorder/recall/{id}` | `recall($id)` | Recall to draft |
| `POST /sales/salesorder/delete/{id}` | `delete($id)` | Soft-delete |
| `GET /sales/salesorder/print/{id}` | `print($id)` | Print layout |
| `POST /sales/salesorder/copy/{id}` | `copy($id)` | Duplicate SO |
| `GET /sales/salesorder/convert/{quoteId}` | `convert($id)` | Create from Quote |

Same pattern for `/sales/salesquote/*`:
- Additional: `POST /sales/salesquote/saveAgreement/{id}` — Save quote price to contract

---

## 9. Security Notes

1. **Site Isolation**: All queries filtered by `site_id` via BaseModel
2. **Permission Gates**: `requirePermission('sales.X')` on every action
3. **CSRF**: All POST forms include `csrf_field()` and JS uses `SQ_CONFIG.csrfToken`
4. **SQL**: All parameterized via `$db->bind()` — no string concatenation
5. **Revenue/Margin**: Gated behind `sales.view_revenue` permission — not shown to all users
6. **Print Layout**: Available without approval (view permission only)
