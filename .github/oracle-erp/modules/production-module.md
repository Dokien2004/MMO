# Production Module — Oracle ERP Compliance Documentation

> Tài liệu này mô tả kiến trúc, trạng thái, và luồng xử lý của module Production theo chuẩn Oracle E-Business Suite (EBS).
> Cập nhật: Session 12 (April 14, 2026) — Score: **96%**

---

## 1. Tổng quan Module

Module Production quản lý toàn bộ vòng đời sản xuất:

```
MPS (Production Plan) → Work Order → Material Issue → WIP Move → Completion → FG Receipt
```

Bao gồm: BOM management, Routing/Operations, Shop Floor Control, WIP tracking, Drawing management.

### Entities chính

| Entity | Controller | Model | Bảng DB |
|--------|-----------|-------|---------|
| Work Order | `WorkOrderController` | `WorkOrderModel` | `work_orders` |
| WO Materials | *(inline)* | *(via WorkOrderModel)* | `work_order_materials` |
| WO Operations | *(inline)* | `WorkOrderOperation` | `work_order_operations` |
| BOM | `BomController` | `BomModel` | `boms` |
| BOM Lines | *(inline)* | *(via BomModel)* | `bom_details` |
| Production Plan | `ProductionPlanController` | `ProductionPlanModel` | `production_plans` |
| Plan Items | *(inline)* | *(via ProductionPlanModel)* | `production_plan_details` |
| Routing | *(via BomController)* | `Routing` | `routings` |
| Routing Operations | *(inline)* | `RoutingOperation` | `routing_operations` |
| Routing Stages | *(inline)* | `RoutingStage` | `routing_stages` |
| Work Center | `config/SettingsController` | `WorkCenter` | `work_centers` |
| Shop Floor | `ShopFloorController` | `ShopFloorModel` | *(aggregated from work_order_operations)* |
| WIP Move | `WipMoveController` | `WipMoveModel` | `wip_move_transactions` |
| WIP Completion | *(via WipMove)* | `WipCompletionModel` | `wip_completions` |
| Drawing | `DrawingController` | `DrawingModel` | `product_drawings` |
| Operation Attribute Sets | `OperationAttributeSetsController` | `OperationAttributeSet` | `operation_attribute_sets` |
| Production Report | `ProductionReportController` | *(via WorkOrderModel)* | *(aggregated)* |

---

## 2. Oracle EBS Mapping

| Oracle EBS Module | Factory ERP Equivalent |
|-------------------|------------------------|
| WIP (Work in Process) | `WorkOrderController` + `WipMoveController` |
| WIP Discrete Jobs | `work_orders` + `work_order_materials` + `work_order_operations` |
| WIP Move Transactions | `wip_move_transactions` (fm/to operation_step tracking) |
| WIP Completions | `wip_completions` + `wip_completion_lines` |
| WIP Material Requisitions | `wip_material_requisitions` |
| BOM (Bills of Material) | `BomController` + `BomService` + explosion/revision services |
| BOM Item Catalog | `boms` + `bom_details` + `bom_outputs` + `bom_resources` |
| BOM Explosion | `BomExplosionService` (recursive + cache: `bom_explosion_cache`) |
| Routing | `Routing` + `RoutingOperation` + `RoutingStage` |
| Work Centers / Resources | `work_centers` (cost/hour, machines, efficiency) |
| MPS (Master Production Schedule) | `ProductionPlanController` + `ProductionPlanService` |
| Shop Floor Management | `ShopFloorController` (real-time dashboard + task scheduling) |
| Engineering (ECO) | `DrawingController` (versioning + approval) |

---

## 3. Work Order Workflow

### Status Flow (Oracle WIP Discrete Job Lifecycle)

```
planned → released → in_progress → completed → closed
                                              ↘ cancelled
```

| Status | Oracle WIP Equivalent | Mô tả |
|--------|----------------------|-------|
| `planned` | Unreleased | Đã lên kế hoạch, chưa phát lệnh |
| `released` | Released | Đã phát lệnh sản xuất |
| `in_progress` | Running | Đang sản xuất (có WIP move) |
| `completed` | Complete | Hoàn thành sản xuất |
| `closed` | Closed | Đã đóng (tính toán hoàn tất) |
| `cancelled` | Cancelled | Đã hủy |

### Constants (ProductionConstants.php)

```php
const WO_PLANNED = 'planned';
const WO_RELEASED = 'released';
const WO_IN_PROGRESS = 'in_progress';
const WO_COMPLETED = 'completed';
const WO_CLOSED = 'closed';
const WO_CANCELLED = 'cancelled';
```

### Key Actions

| Action | Permission | Điều kiện |
|--------|-----------|-----------|
| Create | `production.create` | Bất kỳ |
| Edit | `production.edit` | Status = planned |
| Release | `production.edit` | Status = planned → released |
| Cancel | `production.edit` | Status = planned/released |
| Close | `production.edit` | Status = completed → closed |
| Print Traveller | `production.view` | Bất kỳ status |
| Export | `production.view` | List export (14-col Excel) |
| Import | `production.create` | Bulk WO create from template |
| BOM Change | `production.edit` | Re-snapshot BOM on released WO |
| Add Material | `production.edit` | Manual material addition |

### Service Classes

```
app/services/production/
├── WorkOrderService.php              # Core CRUD + status transitions (163L)
├── WorkOrderExportService.php        # ✅ 14-col Excel (overdue highlight, summary, freezePane) (159L)
└── WorkOrderImportService.php        # ✅ parseExcelForPreview + generateTemplate (instruction sheet) (245L)
```

---

## 4. BOM Workflow

### Status Flow (Oracle BOM Lifecycle)

```
draft → pending_approval → approved → released → obsolete
                        ↘ rejected → draft (recall/edit)
```

| Status | Oracle BOM Equivalent | Mô tả |
|--------|----------------------|-------|
| `draft` | New / Engineering | Đang soạn |
| `pending_approval` | Pending Approval | Chờ duyệt |
| `approved` | Approved | Đã duyệt (chưa sản xuất) |
| `released` | Released / Active | Đã phát hành (dùng cho sản xuất) |
| `obsolete` | Obsolete | Lỗi thời |

### Constants (ProductionConstants.php)

```php
const BOM_DRAFT = 'draft';
const BOM_PENDING_APPROVAL = 'pending_approval';
const BOM_APPROVED = 'approved';
const BOM_RELEASED = 'released';
const BOM_OBSOLETE = 'obsolete';
```

### Key Actions

| Action | Permission | Điều kiện |
|--------|-----------|-----------|
| Create | `production.create` | Bất kỳ |
| Edit | `production.edit` | Status = draft |
| Submit | `production.create` | draft → pending_approval |
| Approve | `production.config` | pending_approval → approved |
| Reject | `production.config` | pending_approval → draft |
| Release | `production.config` | approved → released |
| Obsolete | `production.config` | released → obsolete |
| Copy | `production.create` | Bất kỳ status |
| Where-Used | `production.view` | Tra cứu BOM cha |
| Mass Replace | `production.edit` | Thay thế material hàng loạt |
| Explode | `production.view` | Multi-level BOM explosion |
| Mass Cost Update | `production.config` | Recalculate recursive costs |

### Service Classes

```
app/services/production/
├── BomService.php                    # Core CRUD (665L)
├── BomCalculationService.php         # Cost calculation (recursive) (172L)
├── BomWorkflowService.php            # Approval routing (267L)
├── BomLifecycleService.php           # Status transitions (draft→approved→released→obsolete) (255L)
├── BomExplosionService.php           # Multi-level explosion with caching (511L)
├── BomRevisionService.php            # Version history & snapshots (338L)
├── BomExportService.php              # Indented BOM to Excel (135L)
├── BomEmailService.php               # BOM approval emails (59L)
└── BomIndustrySyncService.php        # Industry config sync (265L)
```

---

## 5. Production Plan (MPS) Workflow

### Status Flow

```
draft → confirmed → released → in_progress → completed → closed
                                                        ↘ cancelled
```

| Status | Mô tả |
|--------|-------|
| `draft` | Đang soạn kế hoạch |
| `confirmed` | Đã xác nhận |
| `released` | Đã phát lệnh (tạo WO từ plan items) |
| `in_progress` | Đang thực hiện |
| `completed` | Hoàn tất |
| `closed` | Đã đóng |
| `cancelled` | Đã hủy |

### Constants (ProductionConstants.php)

```php
const PLAN_DRAFT = 'draft';
const PLAN_CONFIRMED = 'confirmed';
const PLAN_RELEASED = 'released';
const PLAN_IN_PROGRESS = 'in_progress';
const PLAN_COMPLETED = 'completed';
const PLAN_CLOSED = 'closed';
const PLAN_CANCELLED = 'cancelled';
```

### Key Actions

| Action | Method | Điều kiện |
|--------|--------|-----------|
| Create | `store()` | Bất kỳ |
| Edit | `update()` | Status = draft |
| Approve | `approve()` | draft → confirmed |
| Recall | `recall()` | confirmed → draft |
| Release WOs | `releaseWorkOrders()` | Tạo WO từ plan items |
| Delete | `delete()` | Status = draft |

### Service Classes

```
app/services/production/
└── ProductionPlanService.php         # MPS management + WO generation
```

---

## 6. View Architecture (Oracle Standard V2)

### Work Order Views

```
app/views/production/workorder/       # 15 files, 2,699 lines
├── index.php                  # List with filter/search/pagination
├── index_mobile.php           # ✅ Orange gradient, card-based, client-side filter
├── create.php                 # Form tạo mới
├── edit.php                   # Form chỉnh sửa
├── _form.php                  # Shared create/edit form
├── show.php                   # ✅ Shell ~160 lines, 7 tab partials
├── _show_header.php           # WO number + status badge + action buttons
├── _show_info_card.php        # Product, qty, dates, work center, BOM, progress
├── _show_materials_tab.php    # Materials tab: BOM lines vs issued qty + AJAX CRUD
├── _show_operations_tab.php   # Operations tab: routing stages planned/actual
├── _show_wip_transactions.php # WIP moves + completions tab
├── _show_cost_card.php        # Cost analysis tab
├── _show_history.php          # Approval + status change log
├── _modals.php                # Shared modals
└── print_traveler.php         # ✅ A4 WO Traveller (header + BOM + routing + sign-off)

public/js/modules/production/
├── workorder.js               # WO form + AJAX (1,373L)
└── workorder_show.js          # ✅ Workflow AJAX + materials CRUD + drawing change (247L)
```

### BOM Views

```
app/views/production/bom/             # 17 files, 3,724 lines
├── index.php                  # List with filter/search/pagination
├── index_mobile.php           # Mobile BOM list
├── create.php                 # Form tạo mới
├── edit.php                   # Form chỉnh sửa
├── _form.php                  # Shared create/edit form
├── show.php                   # Shell ~797 lines, 4 partials linked
├── _show_header.php           # ✅ BOM title + status badges + action buttons
├── _show_info_card.php        # ✅ General tab: 3-col info/tech-specs/mini-timeline
├── _show_items_table.php      # ✅ Components + outputs tables (dynamic via industry_config)
├── _show_history.php          # ✅ Audit trail + diff viewer + workflow badges
├── _show_action_bar.php       # BOM-specific action buttons
├── _modals.php                # Shared modals
├── explode.php                # Multi-level BOM explosion view
├── where_used.php             # Where-used analysis view
├── mass_replace.php           # Mass material replacement view
├── mass_cost_update.php       # Mass cost recalculation view
└── print.php                  # BOM print view

public/js/modules/production/
├── bom.js                     # BOM form + line management + mass operations (1,017L)
└── bom_show.js                # BOM show page interactions (72L)
```

### Production Plan Views

```
app/views/production/planning/        # 11 files, 2,157 lines
├── index.php                  # List with filter/search/pagination
├── index_mobile.php           # ✅ Purple gradient, card-based, client-side filter
├── create.php                 # Form tạo mới
├── edit.php                   # Form chỉnh sửa
├── _form.php                  # Shared create/edit form
├── _form_table.php            # Line items table partial
├── show.php                   # ✅ Shell ~164 lines (refactored)
├── _show_header.php           # ✅ Top header + action buttons (approve, create WO)
├── _show_info_card.php        # ✅ Plan info + 5-step workflow stepper + summary stats
├── _show_items_table.php      # ✅ 12-col product table + WO badges + urgency flags
└── _modals.php                # Shared modals

public/js/modules/production/
└── production-plan.js         # Plan form + line management (788L)
```

### Other Production Views

```
app/views/production/
├── shopfloor/                          # 4 files, 416 lines
│   ├── dashboard.php                  # Real-time shop floor dashboard
│   ├── dashboard_mobile.php           # Mobile shop floor view
│   ├── machine_view.php               # Machine-level Kanban board
│   └── _modals.php                    # Shared modals
├── wipmove/                            # 3 files, 1,062 lines
│   ├── index.php                      # WIP move transactions list
│   ├── create.php                     # New WIP move form
│   └── show.php                       # Move transaction detail
├── wipcompletion/                      # 5 files, 849 lines
│   ├── index.php                      # WIP completion list
│   ├── create.php                     # New completion form
│   ├── show.php                       # Completion detail
│   ├── _form.php                      # Shared form
│   └── _modals.php                    # Shared modals
├── drawing/                            # 6 files, 876 lines
│   ├── index.php                      # Drawing list
│   ├── create.php / edit.php          # Drawing forms
│   ├── show.php                       # Drawing detail + version history
│   ├── _form.php                      # Shared form
│   └── _modals.php                    # Shared modals
├── config/                             # 3 files, 1,091 lines
│   ├── index.php                      # Production configuration (work centers, etc.)
│   ├── routing_form.php               # Routing form
│   └── _modals.php                    # Config modals
├── report/                             # 2 files, 360 lines
│   ├── index.php                      # Production reports list
│   └── wo_report.php                  # Work order detailed report
└── operation_attribute_sets/           # 8 files, 1,024 lines
    ├── index/create/edit              # CRUD views
    ├── _form.php / _modals.php        # Shared form + modals
    ├── _modals_enum.php               # Enum value modals
    ├── _modal_field.php               # Field modals
    └── _table_fields.php              # Fields table partial

public/js/modules/production/
├── shopfloor.js               # Real-time drag & sort + task start/report (218L)
├── wipcompletion.js           # WIP completion forms (606L)
├── drawing.js                 # Drawing lifecycle (196L)
├── operation-attribute-set.js # Attribute management (366L)
└── production_config.js       # Config page (284L)
```

---

## 7. Data Models

### `work_orders` (44 columns)

Key columns:
- `id`, `code`, `site_id`, `status`
- `product_id` → products, `sku`, `product_name`
- `bom_id` → boms (snapshot), `routing_id` → routings
- `qty_planned`, `qty_produced`, `qty_defective`
- `priority` (LOW/NORMAL/HIGH/URGENT/CRITICAL)
- `start_date`, `completion_date`, `due_date`, `actual_start_date`, `actual_completion_date`
- `work_center_id` → work_centers
- `wip_supply_type` (PUSH/PULL/PHANTOM)
- `customer_id`, `sales_order_id`, `sales_order_line_id` (demand source)
- `production_plan_id` → production_plans
- `grouping_key` (batch grouping)
- `specs` (JSON — product specifications)
- `bom_data_json` (JSON — frozen BOM snapshot)
- `note`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`

### `work_order_materials` (18 columns)

Key columns:
- `id`, `work_order_id`, `product_id`, `sku`
- `qty_required`, `qty_issued`, `qty_consumed`, `qty_backflushed`
- `supply_type` (PUSH/PULL/PHANTOM/BULK)
- `supply_warehouse_id`, `operation_seq`
- `is_manual` (manual addition vs BOM snapshot)
- `substitute_of` (material substitution tracking)

### `work_order_operations` (25 columns)

Key columns:
- `id`, `work_order_id`, `operation_seq`, `operation_code`, `operation_name`
- `work_center_id`, `routing_stage_id`
- `qty_queued`, `qty_running`, `qty_to_move`, `qty_completed`, `qty_rejected`
- `setup_time_planned`, `run_time_planned`, `setup_time_actual`, `run_time_actual`
- `status` (PENDING/RELEASED/IN_PROGRESS/COMPLETED/SKIPPED/CANCELLED)
- `started_at`, `completed_at`

### `boms` (42 columns)

Key columns:
- `id`, `code`, `site_id`, `product_id`, `product_name`
- `industry_type`, `version`, `revision`
- `status` (draft/pending_approval/approved/released/obsolete)
- `is_primary`, `alternate_designator`
- `cycle_time_sec`, `min_batch_size`, `max_batch_size`
- `estimated_material_cost`, `estimated_labor_cost`, `estimated_overhead_cost`, `total_estimated_cost`
- `scrap_rate_percent`, `yield_percent`
- `approved_by`, `approved_at`, `released_by`, `released_at`
- `specs` (JSON — technical specifications)

### `bom_details` (25 columns)

Key columns:
- `id`, `bom_id`, `product_id`, `sku`
- `quantity`, `uom_id`, `waste_rate`
- `operation_seq` (which operation consumes this)
- `supply_type` (PUSH/PULL/PHANTOM)
- `supply_warehouse_id`, `preferred_partner_id`
- `is_critical`, `alternative_group`
- `attributes` (JSON)

### `production_plans` (22 columns)

Key columns:
- `id`, `code`, `site_id`, `name`
- `status` (draft/confirmed/released/in_progress/completed/closed/cancelled)
- `plan_type`, `fiscal_year`, `fiscal_month`
- `start_date`, `end_date`
- `total_planned_qty`, `total_produced_qty`
- `confirmed_by`, `confirmed_at`
- `note`, `created_by`, `updated_by`

### `production_plan_details`

Key columns:
- `id`, `plan_id`, `product_id`, `sku`
- `planned_qty`, `produced_qty`
- `bom_id`, `routing_id`
- `start_date`, `due_date`
- `priority`, `work_center_id`

### `wip_move_transactions` (22 columns)

Key columns:
- `id`, `work_order_id`, `site_id`
- `fm_operation_seq`, `fm_intraoperation_step` (Queue/Run/To Move/Reject/Scrap)
- `to_operation_seq`, `to_intraoperation_step`
- `quantity`, `uom_id`
- `transaction_date`, `transaction_type` (MOVE/RETURN/COMPLETION/SCRAP)
- `reason_code`, `reference`

### `wip_completions` (23 columns)

Key columns:
- `id`, `work_order_id`, `site_id`
- `product_id`, `quantity`, `uom_id`
- `warehouse_id`, `lot_number`
- `status` (draft/confirmed/posted)
- `completion_date`, `posted_date`
- `cost_per_unit`, `total_cost`

### Supporting Tables

| Table | Columns | Purpose |
|-------|---------|---------|
| `routings` | 13 | Manufacturing process definition (code, name, is_primary) |
| `routing_operations` | 24 | Operation sequence (setup/cycle/queue time, yield, overlap) |
| `routing_stages` | 19 | Stage setup (work_center, labor_cost, standard_workers) |
| `work_centers` | 16 | Machine master (cost_per_hour, num_machines, efficiency%) |
| `bom_outputs` | — | Co-products / by-products |
| `bom_resources` | — | Resource requirements per BOM |
| `bom_revisions` | — | Version history with snapshots |
| `bom_explosion_cache` | — | Pre-calculated multi-level explosions |
| `bom_industry_configs` | — | Industry-specific BOM configuration |
| `product_drawings` | — | Technical drawing versioning |
| `wip_material_requisitions` | 24 | Material issue documents (BNI-MR phieu) |
| `wip_resource_transactions` | — | Labor/machine time recording |
| `wip_period_balances` | — | WIP value by period |
| `wip_scrap_reasons` | — | Scrap/reject reason codes |
| `wip_material_allocations` | — | Material reservation |
| `wip_osp_links` | — | Outside processing links |
| `operation_attribute_sets` | — | Custom operation attributes |

---

## 8. Controllers Detail

### WorkOrderController (15+ methods)

| Method | URL Pattern | Purpose |
|--------|------------|---------|
| `index()` | `GET /production/work-orders` | List WOs (desktop + mobile routing) |
| `show($id)` | `GET /production/work-orders/show/{id}` | WO detail (5-tab partials) |
| `create()` | `GET /production/work-orders/create` | Create form |
| `store()` | `POST /production/work-orders/store` | Create WO |
| `edit($id)` | `GET /production/work-orders/edit/{id}` | Edit form |
| `update($id)` | `POST /production/work-orders/update/{id}` | Update WO |
| `release($id)` | `POST /production/work-orders/release/{id}` | Release to production |
| `cancel($id)` | `POST /production/work-orders/cancel/{id}` | Cancel WO |
| `close($id)` | `POST /production/work-orders/close/{id}` | Close WO |
| `changeBom($id)` | `POST /production/work-orders/changeBom/{id}` | Re-snapshot BOM |
| `addMaterial($id)` | `POST /production/work-orders/addMaterial/{id}` | Manual material add |
| `substituteMaterial()` | `POST /production/work-orders/substituteMaterial` | Replace material |
| `print($id)` | `GET /production/work-orders/print/{id}` | Print WO Traveller |
| `export()` | `GET /production/work-orders/export` | Excel export (14 cols) |
| `download_template()` | `GET /production/work-orders/download_template` | Import template |
| `import_process()` | `POST /production/work-orders/import_process` | Import from Excel |

### BomController (20+ methods)

| Method | URL Pattern | Purpose |
|--------|------------|---------|
| `index()` | `GET /production/bom` | List BOMs |
| `create()` / `store()` | Create BOM |
| `show($id)` | BOM detail (4 partials) |
| `edit($id)` / `update($id)` | Edit BOM |
| `delete($id)` | Soft delete |
| `copy($id)` | Duplicate BOM |
| `submit($id)` | Submit for approval |
| `approve($id)` / `reject($id)` | Approval actions |
| `release($id)` | Release for production |
| `obsolete($id)` | Mark obsolete |
| `whereUsed($id)` | Where-used analysis |
| `massReplace()` | Bulk material replacement |
| `explode($id)` | Multi-level explosion |
| `massCostUpdate()` | Recalculate all BOM costs |
| `ajax_*` (11 methods) | Product search, unit conversion, etc. |

### ProductionPlanController (12+ methods)

| Method | URL Pattern | Purpose |
|--------|------------|---------|
| `index()` | `GET /production/production-plan` | List plans (desktop + mobile) |
| `create()` / `store()` | Create plan |
| `show($id)` | Plan detail (3 partials) |
| `edit($id)` / `update($id)` | Edit plan |
| `approve($id)` | Confirm plan |
| `recall($id)` | Recall to draft |
| `delete($id)` | Delete draft plan |
| `releaseWorkOrders($id)` | Generate WOs from plan items |

### Other Controllers

| Controller | Methods | Purpose |
|-----------|---------|---------|
| `ShopFloorController` | 6 | Real-time dashboard, machine Kanban, task start/report, sorting |
| `WipMoveController` | 8 | WIP movement API (store, reverse, search WO, get operations) |
| `DrawingController` | 9 | Drawing CRUD, version history, download, approve, revise |
| `OperationAttributeSetsController` | 12+ | Attribute sets + definitions CRUD with AJAX |
| `ProductionReportController` | 3 | Reports: WO report, export |

---

## 9. Services Architecture (23 total)

```
app/services/production/
├── WorkOrderService.php              # Core CRUD + status transitions (163L)
├── WorkOrderExportService.php        # ✅ 14-col Excel export (159L)
├── WorkOrderImportService.php        # ✅ Excel import (preview + template) (245L)
├── BomService.php                    # BOM CRUD operations (665L)
├── BomCalculationService.php         # Recursive cost calculation (172L)
├── BomWorkflowService.php            # BOM approval routing (267L)
├── BomLifecycleService.php           # Status transitions (full lifecycle) (255L)
├── BomExplosionService.php           # Multi-level explosion + caching (511L)
├── BomRevisionService.php            # Version snapshots + history (338L)
├── BomExportService.php              # Indented BOM Excel export (135L)
├── BomEmailService.php               # BOM approval email notifications (59L)
├── BomIndustrySyncService.php        # Industry config synchronization (265L)
├── ProductionPlanService.php         # MPS management + WO generation (379L)
├── ProductionPlanExportService.php   # Plan Excel export (132L)
├── RoutingService.php                # Routing CRUD (23L — thin delegation)
├── RoutingStageService.php           # Stage management (23L — thin delegation)
├── WorkCenterService.php             # Work center capacity (23L — thin delegation)
├── WipMoveService.php                # Movement transactions (534L)
├── WipCompletionService.php          # Production receipt to FG (605L)
├── WipInventoryService.php           # WIP stock tracking (247L)
├── DrawingService.php                # Drawing lifecycle + versioning (129L)
├── ProductionReportExportService.php # Report Excel export (116L)
└── ProductionEmailService.php        # Email notifications (215L)
```

---

## 10. DTOs & Request Validators

### DTOs (12 files)

```
app/dtos/production/
├── BomDTO.php                        # BOM header data
├── BomLineDTO.php                    # BOM component lines
├── WorkOrderDTO.php                  # WO header data
├── ProductionPlanDTO.php             # Plan header
├── RoutingDTO.php                    # Routing header
├── RoutingStageDTO.php               # Routing stage data
├── DrawingDTO.php                    # Drawing metadata
├── WorkCenterDTO.php                 # Work center data
├── WipCompletionDTO.php              # Completion header
├── WipCompletionLineDTO.php          # Completion lines
├── OperationAttributeSetDTO.php      # Attribute set
└── OperationAttributeDefinitionDTO.php # Attribute definitions
```

### Request Validators (17 files)

```
app/requests/production/
├── BaseProductionRequest.php         # Common validation rules
├── BaseRoutingRequest.php            # Routing-specific base
├── BomStoreRequest.php               # BOM create validation
├── BomUpdateRequest.php              # BOM update validation
├── WorkOrderStoreRequest.php         # WO create validation
├── WorkOrderUpdateRequest.php        # WO update validation
├── ProductionPlanStoreRequest.php    # Plan create validation
├── ProductionPlanUpdateRequest.php   # Plan update validation
├── RoutingStoreRequest.php           # Routing create
├── RoutingUpdateRequest.php          # Routing update
├── RoutingStageStoreRequest.php      # Stage create
├── RoutingStageUpdateRequest.php     # Stage update
├── DrawingStoreRequest.php           # Drawing create
├── DrawingUpdateRequest.php          # Drawing update
├── WipCompletionStoreRequest.php     # Completion create
├── WorkCenterStoreRequest.php        # Work center create
└── WorkCenterUpdateRequest.php       # Work center update
```

---

## 11. JavaScript Modules (10 files, ~5,167 lines)

```
public/js/modules/production/
├── workorder.js               # WO form (create/edit) + line management (1,373L)
├── workorder_show.js          # ✅ WO show interactions (release, materials CRUD, drawing) (247L)
├── bom.js                     # BOM form + component/output management + mass operations (1,017L)
├── bom_show.js                # BOM show page interactions (72L)
├── production-plan.js         # Plan form + line items (788L)
├── shopfloor.js               # Real-time dashboard (drag & sort, task start/report) (218L)
├── wipcompletion.js           # WIP completion forms (606L)
├── drawing.js                 # Drawing lifecycle management (196L)
├── operation-attribute-set.js # Attribute CRUD (366L)
└── production_config.js       # Configuration page (284L)
```

---

## 12. Menu Structure (6 Sub-groups)

```
Production
├── MPS / Kế hoạch
│   ├── Production Plan           # /production/production-plan
│   └── Shop Floor Control        # /production/shop-floor
├── Lệnh sản xuất
│   ├── Work Orders               # /production/work-orders
│   └── WIP Move Transactions     # /production/wip-move
├── BOM / Định mức
│   └── Bills of Material         # /production/bom
├── Engineering / Kỹ thuật
│   ├── Routing Stages            # /production/bom (routing tab)
│   ├── Technical Drawings        # /production/drawing
│   └── Operation Attribute Sets  # /production/operation-attribute-sets
├── Báo cáo
│   └── Production Reports        # /production/production-report
└── Cấu hình
    └── Production Config         # /production/config (work centers, etc.)
```

---

## 13. Architectural Patterns

### BOM Snapshot Pattern
Khi tạo Work Order, BOM được "frozen" (snapshot) vào `bom_data_json` JSON column. Thay đổi BOM gốc không ảnh hưởng WO đang chạy. Có thể re-snapshot via `changeBom()`.

### Intraoperation WIP Tracking (Oracle WIP)
`wip_move_transactions` theo dõi material movement giữa các operation steps:
- **Queue** → **Run** → **To Move** → **Reject/Scrap**
- `fm_operation_seq` + `fm_intraoperation_step` → `to_operation_seq` + `to_intraoperation_step`

### Multi-level BOM Explosion
`BomExplosionService` thực hiện recursive explosion:
1. Check cyclic dependency (`checkCyclicDependency()`)
2. Explode level by level (với `bom_explosion_cache` for performance)
3. Calculate aggregate material requirements + costs

### Generated Columns
MySQL virtual columns for computed values:
- `spec_width`, `spec_gsm` (derived from JSON `specs`)
- `total_wip_value` (computed from qty × cost)

### Site Isolation
All production tables have `site_id`. BaseModel auto-filter ensures multi-tenant isolation.

### Approval Workflow
BOM uses full approval workflow: `BomWorkflowService` + `BomLifecycleService`
Work Order: simple status transition (planned → released → in_progress → completed → closed)

---

## 14. Permissions

```php
// app/config/permissions_list.php — Production section
'production.view'               // Xem danh sách lệnh sản xuất
'production.create'             // Tạo lệnh sản xuất / BOM / Plan
'production.edit'               // Sửa / release / cancel / close
'production.config'             // Cấu hình sản xuất + BOM approve
'production.report'             // Xem báo cáo sản xuất

// Operation Attribute Sets
'production.op_attribute_set.view'
'production.op_attribute_set.create'
'production.op_attribute_set.edit'
'production.op_attribute_set.delete'
```

---

## 15. Pending Work

### High Priority

- [ ] `ProductionReportController` — Enable full reports (WO completion %, OEE, material consumption, variance)
- [ ] BOM indented export to Excel (multi-level explode) — `BomExportService` enhancement
- [ ] WIP Accounting: Material consumption → WIP GL entry → FG completion → AutoAccounting

### Medium Priority

- [ ] Variance analysis: Standard cost vs actual cost per WO
- [ ] Capacity planning: Work Center load vs available hours
- [ ] Scrap/Reject tracking and GL write-off entries
- [ ] Shop floor data collection improvements (labor time, machine time)
- [ ] WO import wizard UI (3-step: template → upload/preview → confirm)

### Low Priority

- [ ] BOM mobile views (show_mobile.php for field engineers)
- [ ] WO mobile show (for shop floor operators)
- [ ] Production dashboard enhancements (Chart.js OEE trend, material consumption)
- [ ] Integration: SO → Production Plan → WO automatic creation chain

---

## 16. Security Notes

1. **Site Isolation**: All queries filtered by `site_id` via BaseModel (`$isSiteSpecific = true`)
2. **Permission Gates**: `requirePermission('production.X')` on every controller action
3. **CSRF**: All POST forms include `csrf_field()`, AJAX uses `csrf_token` in FormData
4. **SQL**: All parameterized via `$db->bind()` — no string concatenation
5. **BOM Snapshot**: Prevents unauthorized BOM modification after WO release
6. **Import Validation**: Excel import validates SKU existence, date formats, numeric ranges before processing
7. **File Upload**: Drawing uploads validated via `FileUploader` service (MIME + extension)
