# Module Tính Giá Thành (Cost Management)

> Cập nhật: Tháng 4/2026 — Tài liệu thiết kế. Chưa implement. Score: **0%**
>
> Oracle Analogy: **Oracle Cost Management (CMF)** — Standard Costing + WIP Actual Cost + Overhead Allocation + Variance Analysis

---

## 1. Tổng quan Module

Module **Tính Giá Thành** quản lý toàn bộ vòng đời chi phí sản xuất từ định nghĩa giá thành tiêu chuẩn (standard cost) đến tính toán giá thành thực tế (actual cost) và phân tích chênh lệch (variance analysis).

### Phạm vi chức năng

| Sub-module | Mô tả | Oracle Pattern |
|------------|-------|----------------|
| **Standard Cost** | Định mức giá thành SP theo BOM | Cost Update / Frozen Cost |
| **BOM Cost Rollup** | Tính toán hàng loạt từ BOM hierarchy | Cost Roll-up |
| **WIP Valuation** | Theo dõi chi phí thực tế theo Lệnh sản xuất | WIP Accounting |
| **Overhead Allocation** | Phân bổ chi phí chung SXC vào WO | Resource / Overhead |
| **Variance Analysis** | Chênh lệch giá vật tư, nhân công, SXC | Standard vs Actual |
| **Landed Cost** | Chi phí mua hàng bổ sung (vận chuyển, thuế) | Landed Cost |
| **Period Close** | Đóng kỳ giá thành, chốt số liệu | Period Close |
| **Reports** | Báo cáo giá thành sản phẩm, biến động | Cost Reports |

### Phương pháp tính giá hỗ trợ

- **WEIGHTED_AVG** — Giá bình quân gia quyền (mặc định xuất kho)
- **FIFO** — Nhập trước xuất trước
- **STANDARD** — Giá tiêu chuẩn (dùng cho sản xuất, variance tracking)

---

## 2. Database — Bảng Hiện Có (Live DB)

> Tất cả bảng dưới đây **đã tồn tại** trong DB. Không cần tạo mới.

### 2.1 Standard Cost

#### `cost_types` — Loại giá thành
| Cột | Kiểu | Mô tả |
|-----|------|-------|
| id | int PK | |
| site_id | int FK | |
| cost_type | varchar(20) | FROZEN / AVERAGE / PENDING |
| created_by, updated_by, created_at, updated_at | | Audit |
| deleted_at, deleted_by | | Soft delete |

> **Lưu ý**: `FROZEN` = giá tiêu chuẩn đang sử dụng; `PENDING` = giá thử nghiệm/cập nhật; `AVERAGE` = trung bình thực tế.

#### `cost_elements` — Yếu tố chi phí
| Cột | Kiểu | Mô tả |
|-----|------|-------|
| id | int PK | |
| site_id | int FK | |
| code | varchar(20) | MATERIAL / LABOR / OVERHEAD / OUTSOURCE |
| name | varchar(100) | Tên hiển thị (VN) |
| cost_allocation_account_id | int FK coa | TK phân bổ GL |
| deleted_at, deleted_by | | Soft delete |

> **Dữ liệu mặc định**:
> - `MATERIAL` — Chi phí Nguyên vật liệu
> - `LABOR` — Chi phí Nhân công trực tiếp
> - `OVERHEAD` — Chi phí Sản xuất chung (SXC)
> - `OUTSOURCE` — Chi phí Thuê ngoài (Gia công)

#### `item_costs` — Giá thành tiêu chuẩn theo sản phẩm
| Cột | Kiểu | Mô tả |
|-----|------|-------|
| id | int PK | |
| product_id | int FK products | |
| site_id | int FK | |
| cost_type_id | int FK cost_types | |
| material_cost | decimal(20,6) | Chi phí NVL/đơn vị |
| resource_cost | decimal(20,6) | Chi phí nhân công + máy/đơn vị |
| overhead_cost | decimal(20,6) | Chi phí SXC phân bổ/đơn vị |
| item_cost | decimal(20,6) **VIRTUAL** | material + resource + overhead |
| deleted_at, deleted_by | | Soft delete |

> **UNIQUE**: `(product_id, site_id, cost_type_id)` — mỗi SP chỉ có 1 giá mỗi loại cost_type.

#### `item_cost_details` — Chi tiết giá thành theo cost element
| Cột | Kiểu | Mô tả |
|-----|------|-------|
| id | bigint PK | |
| item_cost_id | int FK item_costs | |
| cost_element_id | int FK cost_elements | |
| level_type | varchar(10) | `THIS` (cấp hiện tại) / `PREVIOUS` (sub-assembly) |
| unit_cost | decimal(20,6) | Đơn giá yếu tố chi phí này |
| created_at | timestamp | |
| deleted_at, deleted_by | | Soft delete |

---

### 2.2 WIP Actual Cost Tracking

#### `wip_period_balances` — Số dư WIP cuối kỳ
| Cột | Kiểu | Mô tả |
|-----|------|-------|
| id | bigint PK | |
| site_id | int FK | |
| period_id | int FK gl_periods | |
| work_order_id | int FK work_orders | |
| gl_account_id | int FK chart_of_accounts | TK 154/WIP |
| wip_material_value | decimal(15,2) | Giá trị NVL đã tích lũy vào WIP |
| wip_resource_value | decimal(15,2) | Giá trị nhân công/máy đã tích lũy |
| wip_overhead_value | decimal(15,2) | Giá trị SXC đã phân bổ |
| total_wip_value | decimal(15,2) **VIRTUAL** | Tổng giá trị WIP |
| snapshot_date | datetime | Thời điểm chụp số dư |

#### `wip_resource_transactions` — Giao dịch chi phí nhân công/máy
| Cột | Kiểu | Mô tả |
|-----|------|-------|
| id | int PK | |
| site_id | int FK | |
| wip_move_trx_id | int FK wip_move_transactions | |
| work_order_id | int FK work_orders | |
| operation_id | int FK work_order_operations | |
| resource_id | int | Mã máy/nhân lực |
| transaction_date | datetime | Ngày phát sinh |
| usage_amount | decimal(15,4) | Số lượng sử dụng (giờ/ca) |
| uom_id | int FK uom_units | |
| unit_cost | decimal(15,2) | Đơn giá nguồn lực |
| total_cost | decimal(15,2) **VIRTUAL** | = usage_amount × unit_cost |
| gl_account_id | int FK | TK ghi Nợ |

#### `work_order_materials` (Production) — Xuất NVL thực tế
> Đã có trong Production module. Cột quan trọng cho Costing:
> - `qty_consumed` — Số lượng đã tiêu thụ thực tế
> - `total_planned_qty` — Số lượng định mức
> - `actual_quantity` — Số lượng thực tế xuất

#### `wip_completions` + `wip_completion_lines` — Nhập kho thành phẩm
> - `unit_cost` — Đơn giá thực tế khi nhập kho TP
> - `total_cost` — = qty_completed × unit_cost

---

### 2.3 Overhead Allocation (Phân bổ SXC)

#### `cost_allocation_runs` — Lần phân bổ SXC theo kỳ
| Cột | Kiểu | Mô tả |
|-----|------|-------|
| id | int PK | |
| site_id | int FK | |
| period_id | int FK gl_periods | |
| allocation_type | varchar(20) | LABOR_HOUR / MACHINE_HOUR / MATERIAL_VALUE |
| total_amount | decimal(15,2) | Tổng chi phí cần phân bổ |
| allocation_base | varchar(20) | Cơ sở phân bổ |
| total_base_units | decimal(15,2) | Tổng đơn vị cơ sở |
| cost_per_unit | decimal(15,4) | = total_amount / total_base_units |
| created_at, created_by, updated_at, updated_by | | Audit |
| deleted_at, deleted_by | | Soft delete |

#### `cost_allocation_details` — Phân bổ chi tiết vào từng WO
| Cột | Kiểu | Mô tả |
|-----|------|-------|
| id | int PK | |
| run_id | int FK cost_allocation_runs | |
| work_order_id | int FK work_orders | |
| base_units | decimal(15,4) | Số đơn vị cơ sở của WO này |
| allocated_amount | decimal(15,2) | Số tiền phân bổ |
| deleted_at, deleted_by | | Soft delete |

---

### 2.4 Landed Cost (Chi phí mua hàng bổ sung)

#### `landed_cost_allocations` — Header phân bổ landed cost
| Cột | Kiểu | Mô tả |
|-----|------|-------|
| id | int PK | |
| site_id | int FK | |
| cost_invoice_id | int FK ap_invoices | Hoá đơn chi phí (vận chuyển, bảo hiểm…) |
| allocation_method | varchar(20) | VALUE / QUANTITY / WEIGHT |
| total_amount | decimal(15,2) | Tổng phân bổ |
| status | varchar(20) | DRAFT / APPROVED / POSTED |
| created_at | timestamp | |
| deleted_at, deleted_by | | Soft delete |

#### `landed_cost_distributions` — Phân bổ vào từng GRN line
| Cột | Kiểu | Mô tả |
|-----|------|-------|
| id | int PK | |
| allocation_id | int FK landed_cost_allocations | |
| receipt_detail_id | bigint FK inventory_transaction_details | |
| allocated_amount | decimal(15,2) | Chi phí phân bổ vào dòng này |
| deleted_at, deleted_by | | Soft delete |

---

## 3. Bảng Cần Tạo Mới

> Phần này liệt kê bảng **CHƯA TỒN TẠI** trong DB, cần tạo mới để module hoạt động hoàn chỉnh.

### 3.1 `cost_variances` — Bảng phân tích chênh lệch

```sql
CREATE TABLE `cost_variances` (
  `id`                  bigint(20) NOT NULL AUTO_INCREMENT,
  `site_id`             int(11) NOT NULL,
  `period_id`           int(11) NOT NULL COMMENT 'FK: gl_periods',
  `work_order_id`       int(11) NOT NULL COMMENT 'FK: work_orders',
  `product_id`          int(11) NOT NULL COMMENT 'FK: products',
  
  -- Standard Cost (từ item_costs)
  `std_material_cost`   decimal(15,4) DEFAULT 0.0000,
  `std_resource_cost`   decimal(15,4) DEFAULT 0.0000,
  `std_overhead_cost`   decimal(15,4) DEFAULT 0.0000,
  `std_qty`             decimal(15,4) DEFAULT 0.0000 COMMENT 'Số lượng sản phẩm định mức',
  
  -- Actual Cost (từ wip_period_balances, wip_resource_transactions)
  `act_material_cost`   decimal(15,4) DEFAULT 0.0000,
  `act_resource_cost`   decimal(15,4) DEFAULT 0.0000,
  `act_overhead_cost`   decimal(15,4) DEFAULT 0.0000,
  `act_qty`             decimal(15,4) DEFAULT 0.0000 COMMENT 'Số lượng sản phẩm thực tế nhập kho',
  
  -- Variance (Thực tế - Tiêu chuẩn, giá trị âm = tiết kiệm)
  `material_price_variance`    decimal(15,4) GENERATED ALWAYS AS (act_material_cost - std_material_cost) VIRTUAL,
  `resource_efficiency_variance` decimal(15,4) GENERATED ALWAYS AS (act_resource_cost - std_resource_cost) VIRTUAL,
  `overhead_variance`          decimal(15,4) GENERATED ALWAYS AS (act_overhead_cost - std_overhead_cost) VIRTUAL,
  `total_variance`             decimal(15,4) GENERATED ALWAYS AS (
                                 (act_material_cost + act_resource_cost + act_overhead_cost) -
                                 (std_material_cost + std_resource_cost + std_overhead_cost)
                               ) VIRTUAL,
  
  `computed_at`         datetime DEFAULT NULL COMMENT 'Thời điểm tính toán',
  `computed_by`         int(11) DEFAULT NULL,
  `is_closed`           tinyint(1) DEFAULT 0 COMMENT '1 = đã khoá (period close)',
  `note`                varchar(500) DEFAULT NULL,
  
  `created_at`          timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`          timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at`          timestamp NULL DEFAULT NULL,
  `deleted_by`          int(11) DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_variance_wo_period` (`site_id`, `period_id`, `work_order_id`),
  KEY `idx_variance_product` (`product_id`, `period_id`),
  KEY `idx_variance_period` (`site_id`, `period_id`),
  
  CONSTRAINT `fk_variance_wo` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`),
  CONSTRAINT `fk_variance_period` FOREIGN KEY (`period_id`) REFERENCES `gl_periods` (`id`),
  CONSTRAINT `fk_variance_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Phân tích chênh lệch giá thành: tiêu chuẩn vs thực tế theo WO + kỳ';
```

### 3.2 `cost_period_summary` — Tổng hợp giá thành theo kỳ + sản phẩm

```sql
CREATE TABLE `cost_period_summary` (
  `id`                  int(11) NOT NULL AUTO_INCREMENT,
  `site_id`             int(11) NOT NULL,
  `period_id`           int(11) NOT NULL COMMENT 'FK: gl_periods',
  `product_id`          int(11) NOT NULL COMMENT 'FK: products',
  
  `qty_produced`        decimal(15,4) DEFAULT 0.0000 COMMENT 'Tổng SP nhập kho trong kỳ',
  `qty_scrapped`        decimal(15,4) DEFAULT 0.0000 COMMENT 'Tổng SP phế phẩm',
  
  `total_material_cost` decimal(15,2) DEFAULT 0.00 COMMENT 'Tổng chi phí NVL thực tế',
  `total_resource_cost` decimal(15,2) DEFAULT 0.00 COMMENT 'Tổng chi phí nhân công thực tế',
  `total_overhead_cost` decimal(15,2) DEFAULT 0.00 COMMENT 'Tổng SXC phân bổ',
  `total_actual_cost`   decimal(15,2) GENERATED ALWAYS AS (total_material_cost + total_resource_cost + total_overhead_cost) VIRTUAL,
  `unit_actual_cost`    decimal(15,4) DEFAULT NULL COMMENT 'Đơn giá thực tế = total / qty_produced',
  
  `total_std_cost`      decimal(15,2) DEFAULT 0.00 COMMENT 'Giá thành tiêu chuẩn (qty × std unit cost)',
  `total_variance`      decimal(15,2) DEFAULT 0.00 COMMENT 'Chênh lệch tổng',
  
  `status`              enum('draft','computed','closed') DEFAULT 'draft',
  `computed_at`         datetime DEFAULT NULL,
  `closed_at`           datetime DEFAULT NULL,
  `closed_by`           int(11) DEFAULT NULL,
  
  `created_at`          timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`          timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at`          timestamp NULL DEFAULT NULL,
  `deleted_by`          int(11) DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cost_period_product` (`site_id`, `period_id`, `product_id`),
  KEY `idx_cost_summary_period` (`period_id`, `site_id`),
  
  CONSTRAINT `fk_cps_period` FOREIGN KEY (`period_id`) REFERENCES `gl_periods` (`id`),
  CONSTRAINT `fk_cps_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tổng hợp giá thành theo kỳ kế toán: thực tế vs tiêu chuẩn theo từng sản phẩm';
```

---

## 4. Cấu Trúc Module (File Organization)

```
app/controllers/costing/
├── ItemCostController.php          # Giá tiêu chuẩn SP — CRUD + BOM rollup
├── CostAllocationController.php    # Phân bổ SXC theo kỳ
├── LandedCostController.php        # Landed cost (mua hàng + GRN)
├── VarianceController.php          # Phân tích chênh lệch
├── PeriodCloseController.php       # Đóng kỳ giá thành
└── CostReportController.php        # Báo cáo

app/models/costing/
├── ItemCostModel.php               # item_costs + item_cost_details CRUD
├── CostElementModel.php            # cost_elements CRUD
├── CostTypeModel.php               # cost_types CRUD
├── CostAllocationRunModel.php      # cost_allocation_runs + details
├── LandedCostModel.php             # landed_cost_allocations + distributions
├── CostVarianceModel.php           # cost_variances CRUD
├── CostPeriodSummaryModel.php      # cost_period_summary CRUD
└── WipPeriodBalanceModel.php       # wip_period_balances (read-mostly)

app/services/costing/
├── StandardCostService.php         # Tạo/cập nhật item_costs từ BOM
├── CostRollupService.php           # BOM cost roll-up (recursive explosion)
├── ActualCostService.php           # Tính actual cost từ WO materials + resources
├── OverheadAllocationService.php   # Tính cost_per_unit và phân bổ vào WOs
├── LandedCostService.php           # Phân bổ LC vào GRN lines, update stock cost
├── VarianceComputeService.php      # Tính cost_variances từ std vs actual
├── PeriodCloseService.php          # Đóng kỳ: snapshot + lock + GL posting
└── AutoAccounting_costing.php      # GL entries khi post variance (Nợ 632/Có 154)

app/helpers/costing/
├── CostingConstants.php            # COST_TYPE_*, COST_ELEMENT_*, ALLOC_BASE_*
├── CostingCalculationHelper.php    # unit cost, weighted avg, variance %
├── CostingValidationHelper.php     # Check period open, std cost exists
└── CostingReportHelper.php         # Queries cho báo cáo giá thành

app/views/costing/
├── item_cost/
│   ├── index.php                   # Danh sách giá tiêu chuẩn (filter by product/type)
│   ├── edit.php                    # Chỉnh sửa giá (shell ~80L)
│   ├── _form.php                   # Shared form: cost elements breakdown
│   ├── _modals.php                 # Modal: rollup confirm, delete confirm
│   └── rollup.php                  # BOM Cost Rollup page (mass rollup)
├── allocation/
│   ├── index.php                   # Danh sách allocation runs theo kỳ
│   ├── create.php                  # Tạo allocation run (shell ~80L)
│   ├── show.php                    # Chi tiết: run + WO distribution (shell)
│   ├── _show_header.php            # Run summary
│   ├── _show_details.php           # WO allocation table
│   ├── _form.php                   # Shared form
│   └── _modals.php                 # Confirm approve modal
├── landed_cost/
│   ├── index.php                   # Danh sách LC allocations
│   ├── create.php                  # Tạo (link AP invoice → GRN lines)
│   ├── show.php                    # Chi tiết (shell)
│   ├── _show_header.php
│   ├── _show_distributions.php
│   └── _modals.php
├── variance/
│   ├── index.php                   # Báo cáo variance theo kỳ/SP/WO
│   └── show.php                    # Chi tiết 1 WO: std vs actual breakdown
├── period_close/
│   ├── index.php                   # Danh sách kỳ + trạng thái costing_period_status
│   └── close.php                   # Quy trình đóng kỳ (checklist)
└── reports/
    ├── cost_sheet.php              # Phiếu tính giá thành (per WO)
    ├── product_cost.php            # Giá thành theo sản phẩm (kỳ)
    └── variance_summary.php        # Tóm tắt chênh lệch

public/js/modules/costing/
├── item_cost.js                    # Standard cost form + BOM rollup AJAX
├── cost_allocation.js              # Allocation run form + WO distribution grid
├── landed_cost.js                  # LC form + GRN line allocation grid
└── variance.js                     # Variance filter + drill-down
```

---

## 5. Business Flows

### 5.1 Standard Cost Setup (Giá Tiêu Chuẩn)

```
[Admin] Nhập / update giá tiêu chuẩn
         │
         ▼
ItemCostController::store/update()
         │
         ├─ StandardCostService::createOrUpdate($productId, $siteId, $costTypeId, $elements)
         │    ├─ Validate: cost_type phải PENDING (không sửa FROZEN trực tiếp)
         │    ├─ Upsert item_costs (product_id, site_id, cost_type_id)
         │    └─ Upsert item_cost_details (per cost_element)
         │
         └─ Hoặc: BOM Cost Rollup (mass calculation)
              │
              CostRollupService::rollup($siteId, $costTypeId)
              ├─ Load all active BOMs (product hierarchy)
              ├─ Bottom-up explosion: raw materials → sub-assembly → FG
              ├─ Tính material_cost = Σ (component_qty × component_unit_cost)
              ├─ Tính resource_cost = Σ (routing operations × resource_rate)
              ├─ Overhead_cost = resource_cost × overhead_rate (from config)
              └─ UPDATE item_costs + item_cost_details + boms.costing_updated_at
```

### 5.2 Actual Cost Accumulation (WIP)

```
[Production] Xuất NVL vào WIP
    → wip_material_requisitions (issued) + work_order_materials.qty_consumed++
    → AutoAccounting_wip_consumption: Nợ 621 / Có 152

[Shop Floor] Chấm công, ghi máy
    → wip_resource_transactions (thêm record)
    → AutoAccounting: Nợ 622/627 / Có 334/214

[Period end] OverheadAllocationService::allocate($periodId)
    → Tính cost_per_unit từ cost_allocation_runs
    → Phân bổ vào work_orders → INSERT cost_allocation_details
    → UPDATE wip_period_balances.wip_overhead_value

[WO close] Nhập kho thành phẩm
    → wip_completions + wip_completion_lines (unit_cost = actual)
    → AutoAccounting: Nợ 155 / Có 154
```

### 5.3 Variance Computation (Phân Tích Chênh Lệch)

```
VarianceComputeService::compute($periodId, $siteId)
    │
    ├─ For each WO in period:
    │   ├─ Actual: SUM(wip_period_balances) + cost_allocation_details
    │   ├─ Standard: item_costs.item_cost × qty_produced
    │   └─ UPSERT cost_variances (std_* vs act_* columns)
    │
    └─ UPSERT cost_period_summary (per product)
         material_price_variance = act_material - std_material
         resource_efficiency_variance = act_resource - std_resource
         overhead_variance = act_overhead - std_overhead
```

### 5.4 Period Close (Đóng Kỳ)

```
PeriodCloseController::close($periodId)
    │
    ├─ [1] Validate: costing_period_status = OPEN
    ├─ [2] Check: All WOs in period are COMPLETED or CLOSED
    ├─ [3] VarianceComputeService::compute() — final computation
    ├─ [4] AutoAccounting_costing::postVariance() — GL entries cho chênh lệch
    │       Nợ 632 / Có 154 (nếu actual > standard)
    │       Hoặc Có 632 / Nợ 154 (tiết kiệm)
    ├─ [5] UPDATE cost_period_summary.status = 'closed'
    ├─ [6] UPDATE cost_variances.is_closed = 1
    └─ [7] UPDATE gl_periods.costing_period_status = 'CLOSED'
```

### 5.5 Landed Cost (Chi phí mua hàng bổ sung)

```
[AP] Hoá đơn vận chuyển/bảo hiểm nhận được
    → ap_invoices (cost invoice)
    │
    ▼
LandedCostController::create()
    ├─ Link ap_invoice → landed_cost_allocations (header)
    ├─ Select GRN lines (inventory_transaction_details) cần phân bổ
    ├─ Chọn method: VALUE / QUANTITY / WEIGHT
    └─ LandedCostService::allocate()
         ├─ Tính allocated_amount per GRN line
         ├─ INSERT landed_cost_distributions
         ├─ UPDATE inventory_transaction_details.unit_cost (cộng thêm LC)
         └─ UPDATE warehouse_stock (điều chỉnh giá trị tồn kho)
```

---

## 6. Controller Design

### `ItemCostController` — Standard Cost Management

```php
class ItemCostController extends Controller {
    public function index()         // Danh sách giá tiêu chuẩn — filter product, cost_type, site
    public function edit($id)       // Sửa giá (PENDING type only)
    public function update($id)     // POST — lưu thay đổi
    public function rollup()        // GET — confirm page
    public function runRollup()     // POST — thực hiện BOM Cost Roll-up
    public function freeze($id)     // POST — đổi PENDING → FROZEN (period update)
    public function history($id)    // Lịch sử giá thành (audit trail)
}
```

### `CostAllocationController` — Overhead Allocation

```php
class CostAllocationController extends Controller {
    public function index()         // Danh sách runs theo kỳ
    public function create()        // Form tạo run
    public function store()         // POST — tạo run + tính cost_per_unit
    public function show($id)       // Chi tiết run + WO distribution
    public function distribute($id) // POST — phân bổ vào WOs
    public function approve($id)    // POST — duyệt và ghi GL
    public function cancel($id)     // POST — huỷ run
}
```

### `VarianceController` — Variance Analysis

```php
class VarianceController extends Controller {
    public function index()         // Report: filter period, product, WO
    public function compute()       // POST — chạy VarianceComputeService
    public function show($id)       // Chi tiết 1 variance record (drill-down)
    public function export()        // Export Excel
}
```

### `PeriodCloseController`

```php
class PeriodCloseController extends Controller {
    public function index()         // Danh sách kỳ với costing_period_status
    public function checklist($id)  // Checklist trước khi close
    public function close($id)      // POST — thực hiện đóng kỳ
    public function reopen($id)     // POST — mở lại (admin only)
}
```

---

## 7. Integration Points (Liên kết module khác)

| Module | Liên kết | Hướng |
|--------|----------|-------|
| **Finance / GL Period** | `gl_periods.costing_period_status` — kiểm soát mở/khoá | Read/Write |
| **Finance / COA** | `cost_elements.cost_allocation_account_id` — TK GL | Read |
| **Finance / AutoAccounting** | Post GL entries khi close kỳ (variance) | Write |
| **Finance / AP Invoice** | `landed_cost_allocations.cost_invoice_id` — LC invoice | Read |
| **Production / BOM** | `boms` — BOM cost rollup; `boms.costing_updated_at` | Read/Write |
| **Production / Work Orders** | `work_orders.actual_labor_cost`, `actual_overhead_cost` | Read/Write |
| **Production / WIP** | `wip_resource_transactions`, `wip_material_requisitions` | Read |
| **Inventory** | `inventory_transaction_details.unit_cost` — landed cost update | Write |
| **Inventory** | `warehouse_stock` — điều chỉnh giá trị tồn kho | Write |
| **Master Data / Products** | `products.cost_price` — standard cost reference | Read/Write |
| **Master Data / Products** | `item_costs` — đây là giá chính thức, cost_price là tham chiếu | Write |

---

## 8. GL Accounting Entries

| Sự kiện | Nợ | Có | Service |
|---------|----|----|---------|
| Xuất NVL vào WIP | TK 621 (Chi phí NVL) | TK 152 (NVL tồn kho) | AutoAccounting_wip_consumption |
| Chấm công sản xuất | TK 622 (Nhân công) | TK 334 (Lương) | AutoAccounting_wip_consumption |
| Phân bổ SXC | TK 627 (SXC) | TK 214/334 (Khấu hao/Lương GT) | AutoAccounting_costing |
| Nhập kho TP | TK 155 (Thành phẩm) | TK 154 (WIP/CP SXKD dở dang) | AutoAccounting_wip_consumption |
| Hạch toán chênh lệch (variance > 0) | TK 632 (Giá vốn) | TK 154 (WIP) | AutoAccounting_costing |
| Hạch toán chênh lệch (variance < 0) | TK 154 (WIP) | TK 632 (Giá vốn) | AutoAccounting_costing |
| Landed Cost update | TK 15x (Hàng tồn kho) | TK 331 (AP) | LandedCostService |

---

## 9. Permissions

Thêm vào `app/config/permissions_list.php`:

```php
// ===== COSTING MODULE =====
'costing.view'                  => '[Giá Thành] Xem module tính giá thành',
'costing.item_cost.view'        => '[Giá Thành] Xem giá tiêu chuẩn sản phẩm',
'costing.item_cost.edit'        => '[Giá Thành] Sửa giá tiêu chuẩn (PENDING)',
'costing.item_cost.freeze'      => '[Giá Thành] Đóng băng giá (PENDING → FROZEN)',
'costing.rollup.run'            => '[Giá Thành] Chạy BOM Cost Roll-up',
'costing.allocation.view'       => '[Giá Thành] Xem phân bổ SXC',
'costing.allocation.create'     => '[Giá Thành] Tạo phân bổ SXC',
'costing.allocation.approve'    => '[Giá Thành] Duyệt phân bổ SXC',
'costing.landed_cost.view'      => '[Giá Thành] Xem landed cost',
'costing.landed_cost.create'    => '[Giá Thành] Tạo landed cost',
'costing.landed_cost.approve'   => '[Giá Thành] Duyệt landed cost',
'costing.variance.view'         => '[Giá Thành] Xem phân tích chênh lệch',
'costing.variance.compute'      => '[Giá Thành] Chạy tính toán chênh lệch',
'costing.period.close'          => '[Giá Thành] Đóng kỳ giá thành',
'costing.period.reopen'         => '[Giá Thành] Mở lại kỳ giá thành (Admin)',
'costing.report.view'           => '[Giá Thành] Xem báo cáo giá thành',
'costing.report.export'         => '[Giá Thành] Xuất báo cáo giá thành Excel',
```

---

## 10. Menu & Module Config

### `app/config/modules_list.php` — thêm:

```php
[
    'code'              => 'COSTING',
    'name'              => 'Tính Giá Thành',
    'icon'              => 'fas fa-coins',
    'description'       => 'Standard Cost, WIP valuation, overhead allocation, variance analysis',
    'module_type'       => 'CORE',
    'permission_prefix' => '["costing"]',
    'menu_group'        => 'Vận hành',
    'sort_order'        => 8,
],
```

### `app/config/menu_structure.php` — thêm nhóm mới:

```php
[
    'label'      => 'Tính Giá Thành',
    'icon'       => 'fas fa-coins',
    'permission' => 'costing.view',
    'children'   => [
        ['label' => 'Giá Tiêu Chuẩn',    'url' => '/costing/item-cost',       'permission' => 'costing.item_cost.view'],
        ['label' => 'BOM Cost Roll-up',   'url' => '/costing/item-cost/rollup','permission' => 'costing.rollup.run'],
        ['label' => 'Phân bổ SXC',        'url' => '/costing/allocation',      'permission' => 'costing.allocation.view'],
        ['label' => 'Landed Cost',        'url' => '/costing/landed-cost',     'permission' => 'costing.landed_cost.view'],
        ['label' => 'Phân tích Chênh lệch','url' => '/costing/variance',       'permission' => 'costing.variance.view'],
        ['label' => 'Đóng Kỳ Giá Thành', 'url' => '/costing/period-close',   'permission' => 'costing.period.close'],
        ['label' => 'Báo cáo Giá Thành',  'url' => '/costing/reports',        'permission' => 'costing.report.view'],
    ],
],
```

---

## 11. Feature Matrix (Kế Hoạch)

| Feature | Item Cost | Allocation | Landed Cost | Variance | Period Close | Reports |
|---------|-----------|------------|-------------|----------|--------------|---------|
| Index/List | ⏳ | ⏳ | ⏳ | ⏳ | ⏳ | ⏳ |
| Create/Edit | ⏳ | ⏳ | ⏳ | N/A | N/A | N/A |
| Show partials | ⏳ | ⏳ | ⏳ | ⏳ | ⏳ | ⏳ |
| `_form.php` | ⏳ | ⏳ | ⏳ | N/A | N/A | N/A |
| `_modals.php` | ⏳ | ⏳ | ⏳ | ⏳ | ⏳ | — |
| JS Module | ⏳ | ⏳ | ⏳ | ⏳ | — | — |
| Workflow/Approve | N/A | ⏳ | ⏳ | N/A | ⏳ | N/A |
| GL Posting | N/A | ⏳ | ⏳ | ⏳ | ⏳ | N/A |
| Export Excel | — | ⏳ | — | ⏳ | — | ⏳ |
| **Score** | **0%** | **0%** | **0%** | **0%** | **0%** | **0%** |

---

## 12. Build Sequence (Thứ Tự Xây Dựng)

Khuyến nghị xây dựng theo thứ tự:

### Phase 1 — Foundation (Nền tảng)
1. `CostingConstants.php` — constants, status maps
2. `CostElementModel.php` + `CostTypeModel.php` — config entities
3. `ItemCostModel.php` — core model
4. `StandardCostService.php` — create/update item costs
5. `ItemCostController.php` — index + edit (CRUD giá tiêu chuẩn)
6. Views: `item_cost/index.php`, `edit.php`, `_form.php`, `_modals.php`
7. Thêm permissions + menu

### Phase 2 — BOM Rollup
8. `CostRollupService.php` — recursive BOM explosion + cost calculation
9. `ItemCostController::rollup()` + `runRollup()`
10. Views: `item_cost/rollup.php` + `item_cost.js`

### Phase 3 — Overhead Allocation
11. `CostAllocationRunModel.php`
12. `OverheadAllocationService.php`
13. `CostAllocationController.php` (full CRUD + approve)
14. Views: `allocation/` (index, create, show + partials, _modals)
15. `cost_allocation.js`

### Phase 4 — Variance & Period Close
16. `CostVarianceModel.php` + Migration: tạo bảng `cost_variances`
17. `CostPeriodSummaryModel.php` + Migration: tạo bảng `cost_period_summary`
18. `VarianceComputeService.php`
19. `AutoAccounting_costing.php` — GL posting
20. `PeriodCloseService.php`
21. `VarianceController.php` + `PeriodCloseController.php`
22. Views: `variance/` + `period_close/`

### Phase 5 — Landed Cost & Reports
23. `LandedCostModel.php`
24. `LandedCostService.php`
25. `LandedCostController.php`
26. Views: `landed_cost/`
27. `CostReportController.php` + Views: `reports/`
28. Export Excel services

---

## 13. Database Migration

> File: `app/migrations/2026_04_21_create_costing_tables.sql`

```sql
-- ============================================================
-- Migration: Costing Module — New Tables
-- Date: 2026-04-21
-- ============================================================

-- Bảng 1: cost_variances
CREATE TABLE IF NOT EXISTS `cost_variances` (
  -- [xem SQL đầy đủ ở mục 3.1]
);

-- Bảng 2: cost_period_summary
CREATE TABLE IF NOT EXISTS `cost_period_summary` (
  -- [xem SQL đầy đủ ở mục 3.2]
);

-- Index bổ sung cho item_costs (nếu chưa có)
ALTER TABLE `item_costs`
  ADD UNIQUE KEY IF NOT EXISTS `uk_item_cost` (`product_id`, `site_id`, `cost_type_id`);

-- Index bổ sung wip_resource_transactions
ALTER TABLE `wip_resource_transactions`
  ADD KEY IF NOT EXISTS `idx_wrt_wo_date` (`work_order_id`, `transaction_date`);

-- Index bổ sung cost_allocation_details
ALTER TABLE `cost_allocation_details`
  ADD KEY IF NOT EXISTS `idx_cad_run_wo` (`run_id`, `work_order_id`);
```

---

## 14. Nơi Tra Cứu

| Cần | Tìm tại |
|-----|---------|
| Schema bảng costing | `app/db_schema.sql` line ~1499 (cost_*) và ~7883 (wip_*) |
| BOM cost rollup hiện tại | `app/controllers/production/BomController.php` line ~1012 |
| WIP GL entries | `app/services/finance/AutoAccounting_wip_consumption.php` |
| WIP resource transactions | `app/views/production/bom/show.php` tab "costing" |
| GL period control | `app/models/finance/GlPeriodModel.php::getAvailableModules()` |
| Mass cost roll-up view | `app/views/production/bom/mass_cost_update.php` |
| Inventory costing method | `app/controllers/inventory/InventoryConfigController.php` |
| Sales WO cost tracking | `app/services/sales/SalesOrderCostTrackingService.php` |
