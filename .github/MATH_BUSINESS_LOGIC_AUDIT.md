# 🔢 MATH & BUSINESS LOGIC AUDIT — Factory ERP

> **Ngày audit:** 09/04/2026 (Session 29)
> **Cập nhật:** 21/04/2026 (Session 39) — AR aging dashboard schema fix: `ar_invoices.due_date` không tồn tại, đã đổi `FinanceDashboardHelper::getArAging()` sang `invoice_date` với Net30 buckets; FIN-H2 vẫn còn hiệu lực cho `ArReportModel` (báo cáo chi tiết AR aging)
> **Phiên cũ:** 09/04/2026 (Session 32) — allow_negative_stock consistency fix across 4 files, SessionManager PDO fix
> **Phạm vi:** Toàn bộ logic tính toán & nghiệp vụ trên 8 modules
> **Mục đích:** Review tính chính xác của công thức toán học, phát hiện lỗi logic nghiệp vụ, đảm bảo tính nhất quán trước GoLive
> **Status:** 9/15 CRITICAL ✅ FIXED & VERIFIED | 6 CRITICAL remaining | HR module 100% VERIFIED

---

## 📊 TỔNG QUAN

| Module | CRITICAL | HIGH | MEDIUM | LOW | Total Issues |
|--------|----------|------|--------|-----|-------------|
| **Finance** | 3 | 4 | 6 | 5 | 18 |
| **Inventory** | 0 | 4 | 9 | 4 | 17 |
| **HR / Payroll** | 6 | 5 | 8 | 3 | 22 |
| **Purchasing** | 3 | 4 | 7 | 4 | 18 |
| **Sales** | (included above) | | | | |
| **Production** | 1 | 5 | 4 | 3 | 13 |
| **Quality** | 0 | 0 | 4 | 3 | 7 |
| **Asset** | 2 | 4 | 4 | 0 | 10 |
| **TỔNG** | **15** | **26** | **42** | **22** | **105** |

### Phân bố theo loại lỗi

| Loại lỗi | Count | Ví dụ |
|-----------|-------|-------|
| Float vs BCMath | 12 | WAC, FIFO amount, tax, exchange rate |
| Client ↔ Server mismatch | 5 | AR tax base, SO discount, PO UOM |
| Division by Zero thiếu guard | 8 | Attendance hours, depreciation, margin |
| Sai công thức nghiệp vụ | 10 | Payroll key mismatch, AR aging, asset SUM |
| Hardcoded values | 8 | Insurance cap, work hours, work days |
| Rounding inconsistency | 7 | JE tolerance, tax 2dp vs 4dp, epsilon |
| Race condition | 2 | WIP reservation, inventory reserve |
| Data integrity | 6 | Merge collision, column name wrong, negative stock bypass |

---

## 🔴 CRITICAL ISSUES (15)

### Finance

| ID | Vấn đề | File | Chi tiết |
|----|--------|------|----------|
| **FIN-C1** ✅ | `ApPaymentModel::getPaymentAccounts()` — Merge collision, 2 queries merged | `ApPaymentModel.php` L99-118 | **FIXED (Session 30):** Loại bỏ query thứ 2 bị merge sai (undefined `$partnerId`, `$siteId`). Method giờ chỉ trả về accounts 111/112. |
| **FIN-C2** ✅ | AR Invoice tax: Server tính thuế trên GROSS, Client tính trên NET | `ArInvoiceController.php` L140-148 | **FIXED (Session 30):** Server giờ tính: `netAmount = qty × price - discount`, `tax = netAmount × rate`. Đúng TT219/2013. |
| **FIN-C3** | AP Aging dashboard dùng `total_amount` thay vì outstanding balance | `FinanceDashboardController.php` L56 | Hóa đơn 100M đã thanh toán 90M vẫn hiện 100M trong aging bucket. Thổi phồng số liệu nợ phải trả. |

### HR / Payroll

| ID | Vấn đề | File | Chi tiết |
|----|--------|------|----------|
| **HR-C1** ✅ | PayrollController key mismatch — truy cập nested keys không tồn tại | `PayrollController.php` L249-280 | **FIXED (Session 30):** Sửa tất cả keys trong Controller thành flat keys khớp với PayrollCalculator: `standard_work_days`, `ot_hours_normal`, `basic_salary`, v.v. |
| **HR-C2** ✅ | `getTimesheetData()` chỉ lấy `SUM(ot_hours)` — mất OT weekend/holiday | `PayrollCalculator.php` L355-420 | **FIXED (Session 30→31):** [1] Đổi `work_hours` → `actual_work_hours` (đúng schema). [2] Bỏ logic dùng `holiday_id` (column NEVER populated). [3] Dùng JOIN `hr_holidays` để tách `ot_sunday_hours` thành weekend vs holiday OT. [4] Thêm `deleted_at IS NULL` cho soft delete. |
| **HR-C3** ✅ | `calculateWorkDays()` hardcode Mon-Fri | `PayrollController.php` L375-410 | **FIXED (Session 30→31):** Dùng cấu hình `weekend_days` từ `attendance_configurations` (sửa từ `hr_configs` sai tên bảng) + loại ngày lễ (`hr_holidays` + `deleted_at IS NULL`). |
| **HR-C4** ✅ | Leave duration trừ giờ ăn trưa cả ngày weekend/holiday | `LeaveRequestController.php` L455-500 | **FIXED (Session 30→31):** Chỉ trừ lunch cho ngày làm việc (query `attendance_configurations` — sửa từ `hr_configs` sai tên bảng + `hr_holidays` với `deleted_at IS NULL`). |
| **HR-C5** ✅ | Bulk leave dùng shift của employee đầu tiên cho tất cả | `LeaveRequestController.php` L542-660 | **FIXED (Session 30→31):** [1] Tính `calculateLeaveDurationOnServer()` riêng cho từng NV. [2] Balance deduction cũng dùng `$empLeaveHours` thay vì `$totalHours` (client). |
| **HR-C6** ✅ | Night minutes double-counting với overnight shifts | `AttendanceCalculator.php` L1073-1105 | **FIXED (Session 30):** Window 2 giờ chỉ tính từ `night_start` đến 23:59:59 cùng ngày (tránh overlap với window 1 của ngày hôm sau). |

### Purchasing / Sales

| ID | Vấn đề | File | Chi tiết |
|----|--------|------|----------|
| **PUR-C1** ✅ | `QuantityUpdater` query cột `conversion_factor` — không tồn tại | `QuantityUpdater.php` L233-260 | **FIXED (Session 30):** Đổi cả 2 queries và 2 property access từ `conversion_factor` → `conversion_rate` khớp DB schema. |
| **PUR-C2** | SO JS `calcRow()` thiếu line-level discount | `sales_order.js` ~L725 | Server tính `amount = qty × price × (1 - disc%)`, JS tính `amount = qty × price` (bỏ qua discount). User thấy 1 số, DB lưu số khác. |
| **PUR-C3** | `FinancialCalculator.calculateTotals()` dead code sai logic currency | `FinancialCalculator.php` L55 | Orphaned method tính `header_discount / exchange_rate` nhưng code chính lưu header_discount bằng PO currency. Nếu ai gọi method này = sai total. |

### Production

| ID | Vấn đề | File | Chi tiết |
|----|--------|------|----------|
| **PROD-C1** | Phantom BOM double-waste compounding | `BomExplosionService.php` L110-210 | Khi explode phantom BOM, `extendedQty` (đã gồm waste phantom) được truyền làm multiplier cho children. Children áp thêm waste riêng → waste compound `1.05 × 1.10 = 1.155` thay vì `1.15`. |

### Asset

| ID | Vấn đề | File | Chi tiết |
|----|--------|------|----------|
| **AST-C1** | Declining balance không dừng ở salvage value | `AssetModel.php` L310-420 | Formula: `(cost - accumulated) × (2/useful_life_months)` chỉ dừng ở 0, không dừng ở salvage_value. Vi phạm IAS 16 — tài sản bị khấu hao dưới giá trị còn lại. |
| **AST-C2** | Report `getDepreciatingAssets()` thiếu trừ salvage value | `ReportController.php` L68-85 | Formula: `original_cost / useful_life_months` thay vì `(original_cost - salvage_value) / useful_life_months`. Tất cả báo cáo KH hàng tháng bị tính CAO hơn thực tế. |

---

## 🟠 HIGH ISSUES (25)

### Finance (4)

| ID | Vấn đề | File |
|----|--------|------|
| FIN-H1 | AP Payment tolerance 10đ — quá lớn cho foreign currency (10 USD = 250K VND) | `ApPaymentModel.php` L161 |
| FIN-H2 | AR Aging dùng `invoice_date` thay vì `due_date` (khác AP Aging dùng đúng) | `ArReportModel.php` L35 |
| FIN-H3 | JE balance tolerance: Controller = 1.0, Model = 0.01, AutoAccounting = 0.01 | `JournalEntryController.php` L200 |
| FIN-H4 | ApPaymentController load model path sai `purchasing/ApPaymentModel` → `finance/` | `ApPaymentController.php` L16 |

### Inventory (3)

| ID | Vấn đề | File |
|----|--------|------|
| INV-H1 | Core WAC dùng native float, không dùng bcmath (InventoryCalculationHelper::weightedAvgCost có bcmath nhưng KHÔNG ĐƯỢC GỌI) | `WarehouseStockModel.php` L325 |
| INV-H2 | `MaterialReturnService::upsertWipStock()` duplicate WAC formula cũng dùng native float | `MaterialReturnService.php` L430 |
| INV-H3 | `MaterialIssueService::approve()` FIFO line amount accumulated bằng native float | `MaterialIssueService.php` L810 |
| INV-H4 ✅ | `allow_negative_stock` chỉ check ở Transfer approve/reverse — các flow khác (create, WipIssue, MaterialIssue, StockAdjustment) bypass cấu hình | 4 files — **FIXED (Session 32):** Applied `checkAllowNegative()` nhất quán across 4 files, 8 check points. Pattern: cache per-warehouse trước foreach, gọi TRƯỚC FOR UPDATE. |

### HR / Payroll (5)

| ID | Vấn đề | File |
|----|--------|------|
| HR-H1 ✅ | `recalculateTimesheet()` hardcode `effectiveLeaveHours = 0` | `AttendanceCalculator.php` L1291 — **FIXED (Session 30):** Tính overlap shift ∩ leave (cùng logic main flow). |
| HR-H2 ~~FP~~ | `AttendanceController` division by zero khi `$ts->standard_hours = 0` | `AttendanceController.php` L1619 — **FALSE POSITIVE:** Đã có guard `$ts->standard_hours > 0`. |
| HR-H3 | Không có probation salary adjustment (85% theo Điều 26 Luật LĐ) | Toàn bộ PayrollCalculator — **INFO:** Cần feature mới (probation flag + 85% multiplier). |
| HR-H4 ~~FP~~ | Daily rate fallback `?: 26` khi standard_days = 0/null (mask data error) | `PayrollCalculator.php` L68 — **FALSE POSITIVE:** Elvis operator `?: 26` đã handle 0/null/false. |
| HR-H5 ✅ | Leave balance check dùng `total_hours` từ client (tamper-able) | `LeaveRequestController.php` L575 — **FIXED (Session 30):** Bulk leave giờ tính per-employee trên server. |

### Purchasing / Sales (4)

| ID | Vấn đề | File |
|----|--------|------|
| PUR-H1 | PO tax rounding 2dp vs 4dp (Model vs FinancialCalculator) | `PurchaseOrder.php` L604 vs `FinancialCalculator.php` L107 |
| PUR-H2 | SO Import dùng `round()` thay vì BCMath (khác SalesCalculationService) | `SalesOrderImportService.php` L412 |
| PUR-H3 | PO status filter khác nhau cho PR quantity aggregation | `PurchaseOrderService.php` vs `QuantityUpdater.php` |
| PUR-H4 | `DetailValidator` thiếu `from_uom_id` filter trong UOM conversion lookup | `DetailValidator.php` L290 |

### Production (5)

| ID | Vấn đề | File |
|----|--------|------|
| PROD-H1 | BOM cost rollup: setup cost × outputQty khi stdLotSize=1 (thay vì amortize) | `BomModel.php` L260 |
| PROD-H2 | WO material snapshot: `effectivity_date <= CURDATE()` loại bỏ NULL rows | `WorkOrderModel.php` L230 |
| PROD-H3 | Plan→WO: `$yieldFactor` không floored → division by zero nếu yield=0 | `ProductionPlanService.php` L235 |
| PROD-H4 | WIP Move backflush: `$woPlanQty` fallback 1 → consume toàn bộ material | `WipMoveService.php` L60 |
| PROD-H5 | WIP Completion: WAC với `newQty ≤ 0` → negative denominator flips cost sign | `WipCompletionService.php` L175 |

### Asset (4)

| ID | Vấn đề | File |
|----|--------|------|
| AST-H1 | Depreciation method rate hardcoded `2.0` (double declining) — không cấu hình được | `AssetModel.php` L310 |
| AST-H2 | `useful_life_months = 0` → division by zero trong SQL (cả SL và DB) | `AssetModel.php`, `ReportController.php` |
| AST-H3 | `get_all_assets()` dùng `SUM(accumulated_depreciation)` thay vì `MAX()` | `AssetModel.php` |
| AST-H4 | Asset disposal dùng `accumulated_depreciation` denormalized (có thể stale) | `AssetModel.php` L590 |

---

## 🟡 MEDIUM ISSUES (42)

### Finance (6)

| ID | Vấn đề | File |
|----|--------|------|
| FIN-M1 | AutoAccounting: `$amount = qty × unit_cost` không round trước khi INSERT | 8 AutoAccounting services |
| FIN-M2 | AP Invoice: `$grandTotal` accumulated từ rounded lines nhưng bản thân không round | `ApInvoiceModel.php` L263 |
| FIN-M3 | AutoAccounting entered amount: `$debit / $rate` không round | `AutoAccounting.php` L577 |
| FIN-M4 | AP Invoice `getRemainingQtyForGrnDetail()` không tính QC adjustment | `ApInvoiceModel.php` L207 |
| FIN-M5 | Income Statement `HAVING net_amount != 0` — float equality | `IncomeStatementController.php` L210 |
| FIN-M6 | ExchangeRateModel return `0` khi không tìm thấy rate (gây nhân/chia 0) | `ExchangeRateModel.php` L128 |

### Inventory (9)

| ID | Vấn đề | File |
|----|--------|------|
| INV-M1 | `auditSummary()` dùng native float trong class toàn bcmath | `InventoryCalculationHelper.php` L130 |
| INV-M2 | `convertUom()` silent fallback trả qty gốc khi rate ≤ 0 | `InventoryCalculationHelper.php` L160 |
| INV-M3 | Stock aging `floor((time() - strtotime) / 86400)` — DST off-by-one | `InventoryReportingHelper.php` L90 |
| INV-M4 | `turnoverRatio()` dùng native float (inconsistent) | `InventoryReportingHelper.php` L120 |
| INV-M5 | UOM conversion + tolerance dùng native float — boundary false rejection | `InventoryReceiptItemProcessor.php` L80 |
| INV-M6 | `OpeningStockHelper::parseNumber()` — "1.500,25" → `1.5` (European locale) | `OpeningStockHelper.php` L30 |
| INV-M7 | GRN receipt total: `qty × cost × exchange_rate` — 3 float multiplications chain | `InventoryReceiptModel.php` L750, L879 |
| INV-M8 | Material return: UOM conversion × cost = 2 chained float multiplications | `MaterialReturnService.php` L248 |
| INV-M9 | PO status completion `99.9%` threshold dùng native float | `InventoryReceiptModel.php` L1740 |

### HR / Payroll (8)

| ID | Vấn đề | File |
|----|--------|------|
| HR-M1 | OT hourly rate hardcoded `/8` — sai cho ca 10h, 12h | `PayrollCalculator.php` L247 |
| HR-M2 | Insurance cap hardcoded `36000000` VND | `PayrollCalculator.php` L163 |
| HR-M3 | Missing punch: `checkIn == checkOut` flagged (edge: same-second swipe) | `AttendanceCalculator.php` L1170 |
| HR-M4 | Lunch break hardcoded `12:00-13:00` trong leave overlap calculation | `AttendanceCalculator.php` ~L600 |
| HR-M5 | Leave allocation excludes probation employees (vi phạm Điều 113 Luật LĐ) | `LeaveAllocationService.php` ~L200 |
| HR-M6 | Leave request JS hardcode Mon-Sat workdays (khác PayrollController Mon-Fri) | `leave_request.js` L555 |
| HR-M7 | Duplicate constants: AttendanceCalculator vs AttendanceLogClassifier | Cả 2 files |
| HR-M8 | `attendance_helper.php` dùng `mysqli` (toàn app dùng PDO) | `attendance_helper.php` |

### Purchasing / Sales (7)

| ID | Vấn đề | File |
|----|--------|------|
| PUR-M1 | Div/0 trong cost tracking: `marginPct = (price-cost)/price` khi price=0 | `SalesOrderCostTrackingService.php` |
| PUR-M2 | Div/0 shipment overage: `overage / prQty` khi prQty=0 | `DetailValidator.php` |
| PUR-M3 | PO tax breakdown back-calculated từ line_total (rounding drift) | `PurchaseOrderController.php` L185 |
| PUR-M4 | `bcRound()` edge case cho null/empty values | `SalesCalculationService.php` L21 |
| PUR-M5 | PO line total: PHP `qty × rate × price` vs JS `qty × (price × rate)` — float associativity | `PurchaseOrder.php` L597 |
| PUR-M6 | PR line total tính trong controller, không trong model/service | `PurchaseRequestController.php` L224 |
| PUR-M7 | Sales Dashboard growth: 0% khi prev=0 thay vì "N/A" | `SalesDashboardController.php` L63 |

### Production (4)

| ID | Vấn đề | File |
|----|--------|------|
| PROD-M1 | Plan vs WO formula order khác nhau cho cùng BOM data | `ProductionPlanService.php` vs `WorkOrderModel.php` |
| PROD-M2 | Co-product `cost_allocation_percent` có thể > 100% (no validation) | `WipCompletionService.php` L175 |
| PROD-M3 | `WipInventoryService::reserveStockForWo()` thiếu `FOR UPDATE` lock | `WipInventoryService.php` |
| PROD-M4 | BOM `$outputQty` fallback `max(0.0001)` → astronomically large unit cost | `BomModel.php` L260 |

### Quality (4)

| ID | Vấn đề | File |
|----|--------|------|
| QC-M1 | `calculateQcStatus()` không tích hợp AQL accept/reject numbers | `QaInspectionService.php` L450 |
| QC-M2 | Measurement boundary inclusive (`< min` / `> max`) — cần document rõ | `inspection.js` L356 |
| QC-M3 | Qty constraint `pass + reject + rework ≤ inspected` chỉ validate JS, thiếu server | `inspection.js` L436 |
| QC-M4 | Qty-based QC status: ACCEPT nếu `qtyPass >= qtyInspected` (bỏ qua AQL) | `QaInspectionService.php` |

### Asset (4)

| ID | Vấn đề | File |
|----|--------|------|
| AST-M1 | `MAX(accumulated_depreciation)` giả sử monotonic tăng (correction reversal sẽ sai) | `AssetModel.php` |
| AST-M2 | Disposal không có `beginTransaction/commit` wrapper | `AssetModel.php` L590 |
| AST-M3 | Dashboard book value dùng denormalized columns (stale giữa batch runs) | `DashboardController.php` L75 |
| AST-M4 | Report depreciation ignores `depreciation_method` (luôn dùng SL formula) | `ReportController.php` L68 |

---

## ✅ POSITIVE FINDINGS (Triển khai tốt)

### Finance
- ✅ JE Balance validation sau mỗi auto-generated JE (AutoAccounting)
- ✅ `FOR UPDATE` locking trên AP Invoice rows trước khi đọc paid amount
- ✅ AP Invoice line qty validation vs QC-passed qty với tolerance 0.0001
- ✅ Period lock checks nhất quán trên tất cả controllers
- ✅ Site isolation — tất cả queries có `site_id` filter
- ✅ AP Report Aging dùng đúng `due_date` và `balance = total - paid`
- ✅ Exchange rate bidirectional lookup với `1 / rate`

### Inventory
- ✅ `InventoryCalculationHelper::weightedAvgCost()` — bcmath hoàn hảo (tuy chưa được gọi trong hot path)
- ✅ FIFO layer consumption: `FOR UPDATE` + `min(remaining, available)` per layer
- ✅ FEFO allocation: `ORDER BY expiry_date ASC` + row-level locks
- ✅ `GREATEST(0, quantity_reserved - :qty)` — prevent negative reservation (12+ locations)
- ✅ Negative stock guard configurable per warehouse + site fallback — **Session 32: applied consistently across Transfer, WipIssue, MaterialIssue, StockAdjustment (4 files, 8 check points)**
- ✅ `checkAllowNegative()` pattern: `COALESCE(warehouse, org_params, 0)` — gọi TRƯỚC FOR UPDATE loops (tránh PDO singleton conflict)
- ✅ `FOR UPDATE` nhất quán trên tất cả approve/reverse methods
- ✅ Transaction + rollback wrapper trên tất cả write operations
- ✅ Pro-rata last-line-gets-remainder pattern (prevent rounding loss)
- ✅ NULL-safe `<=>` cho bin_id/lot_id comparisons

### Purchasing / Sales
- ✅ `SalesCalculationService` dùng bcmath (`bcmul`, `bcsub`, `bcRound`) — precision 4
- ✅ bcmath custom `bcRound()` function đúng chuẩn
- ✅ PO tolerance receipt 2-tier check (hard reject + configurable tolerance)

### Production
- ✅ BOM multi-level recursive explosion với cycle detection (`$visited` array)
- ✅ WO material snapshot: yield factor floored `GREATEST(0.0001)`
- ✅ WIP Move `FOR UPDATE` trên stock rows trước mutation
- ✅ Production report: `qty_planned > 0` guard cho completion percentage
- ✅ Optimistic locking trong `WipIssueService` (compare `updated_at`)
- ✅ Config-driven costing method (WAC/Standard/FIFO) từ `organization_parameters`

### Quality
- ✅ AQL sampling lookup từ pre-seeded ISO 2859-1 data (không tự tính sai)
- ✅ Pareto analysis: division by zero guarded, correct cumulative 80/20
- ✅ Server-side measurement validation matches client-side

---

## 📋 ƯU TIÊN SỬA — Priority Matrix

### 🔴 P1: Sửa ngay (ảnh hưởng tính chính xác dữ liệu)

| # | ID | Module | Vấn đề | Impact | Effort |
|---|-----|--------|--------|--------|--------|
| 1 | HR-C1 | HR | PayrollController key mismatch → payroll slips all-zero | **Lương sai** | ~20L |
| 2 | HR-C2 | HR | getTimesheetData() mất OT weekend/holiday → trả thiếu OT | **Lương sai** | ~10L |
| 3 | HR-C3 | HR | calculateWorkDays() hardcode Mon-Fri → daily rate sai | **Lương sai** | ~15L |
| 4 | FIN-C1 | Finance | ApPaymentModel merge collision → AP Payment crash | **Runtime error** | ~30L |
| 5 | FIN-C2 | Finance | AR Invoice tax on GROSS vs NET → thuế sai | **Sai thuế** | ~5L |
| 6 | PUR-C1 | PO | QuantityUpdater `conversion_factor` column sai | **SQL error** | ~1L |
| 7 | AST-C1 | Asset | Declining balance không dừng ở salvage value | **Sai KH** | ~5L |
| 8 | AST-C2 | Asset | Report missing salvage value in formula | **Sai báo cáo** | ~3L |
| 9 | AST-H3 | Asset | `get_all_assets()` SUM vs MAX inconsistency | **Sai book value** | ~3L |

### 🟠 P2: Sửa sớm (ảnh hưởng tính toán nghiệp vụ)

| # | ID | Module | Vấn đề | Impact | Effort |
|---|-----|--------|--------|--------|--------|
| 10 | HR-C4 | HR | Leave lunch deduction trên weekends | Sai leave hours | ~15L |
| 11 | HR-C6 | HR | Night minutes double-counting | Sai OT đêm | ~20L |
| 12 | PUR-C2 | Sales | SO JS thiếu line discount | UX mismatch | ~5L |
| 13 | FIN-C3 | Finance | AP Aging dùng total_amount thay balance | Sai aging report | ~10L |
| 14 | FIN-H2 | Finance | AR Aging dùng invoice_date thay due_date | Sai aging report | ~3L |
| 15 | FIN-H3 | Finance | JE tolerance inconsistency (1.0 vs 0.01) | Confusing UX | ~3L |
| 16 | PROD-C1 | Production | Phantom BOM double-waste | Sai MRP | ~10L |
| 17 | PROD-H2 | Production | WO effectivity_date loại NULL rows | Missing materials | ~5L |
| 18 | INV-H1 | Inventory | Core WAC dùng float thay bcmath | Cumulative drift | ~20L |

### 🟡 P3: Nên sửa (hardcode, inconsistency, edge cases)

| # | ID | Module | Vấn đề | Effort |
|---|-----|--------|--------|--------|
| 19 | HR-M1 | HR | OT hourly rate `/8` hardcoded | ~5L |
| 20 | HR-M2 | HR | Insurance cap 36M hardcoded | ~3L |
| 21 | HR-H3 | HR | Probation salary 85% missing | ~15L |
| 22 | HR-M5 | HR | Probation employees excluded from leave | ~5L |
| 23 | FIN-H1 | Finance | AP Payment tolerance 10 = currency-agnostic | ~5L |
| 24 | FIN-M6 | Finance | ExchangeRate returns 0 (should null/throw) | ~10L |
| 25 | INV-M2 | Inventory | UOM conversion silent fallback | ~5L |
| 26 | INV-M6 | Inventory | OpeningStockHelper European locale parse | ~10L |
| 27 | PUR-H4 | Purchasing | Missing from_uom_id filter in conversion | ~3L |
| 28 | PROD-H1 | Production | BOM setup cost ×outputQty khi lot=1 | ~5L |
| 29 | PROD-H3 | Production | Plan yield factor not floored | ~3L |
| 30 | AST-H2 | Asset | Division by zero: useful_life_months = 0 | ~5L |

---

## 🏗️ KIẾN TRÚC — Vấn đề xuyên suốt

### 1. Float vs BCMath — Split Personality

| Module | Production Code | Helper/Reference Code |
|--------|-----------------|----------------------|
| Inventory | `WarehouseStockModel::updateStock()` — **native float** | `InventoryCalculationHelper::weightedAvgCost()` — **bcmath** ✅ |
| Sales | `SalesCalculationService` — **bcmath** ✅ | Import uses `round()` |
| Purchasing | `PurchaseOrder::calculateTotals()` — **native float** | `FinancialCalculator` — **native float** |
| Finance | AutoAccounting — **native float** | N/A |
| Production | WIP costing — **native float** | N/A |
| Asset | Depreciation — **SQL arithmetic** | N/A |

**Khuyến nghị:** Thống nhất dùng bcmath cho tất cả financial calculations. Ưu tiên:
1. WAC (Inventory) — highest volume
2. AutoAccounting (Finance) — direct GL impact
3. FIFO costing (Material Issue) — accumulated errors

### 2. Epsilon/Tolerance Inconsistency

| Context | Tolerance | File |
|---------|-----------|------|
| JE balance (Controller) | 1.0 | JournalEntryController.php |
| JE balance (Model) | 0.01 | JournalEntryModel.php |
| JE balance (AutoAccounting) | 0.01 | AutoAccounting.php |
| WIP Move available | 0.0001 | WipMoveService.php |
| WIP Completion shortage | 0.001 | WipCompletionService.php |
| QC pending qty | 0.0001 | QaInspectionService.php |
| AP Payment overpayment | 10 (absolute) | ApPaymentModel.php |
| PO completion | 99.9% | InventoryReceiptModel.php |
| Stock aging | `floor(seconds/86400)` | InventoryReportingHelper.php |

**Khuyến nghị:** Define central epsilon constants:
```php
// app/config/config.php
define('EPSILON_FINANCIAL', 0.01);  // For money & JE balance
define('EPSILON_QUANTITY', 0.0001); // For stock qty comparisons
define('EPSILON_PERCENTAGE', 0.01); // For % comparisons
```

### 3. Client ↔ Server Calculation Mismatches

| Area | Client Logic | Server Logic | Match? |
|------|-------------|-------------|--------|
| AR Invoice tax base | After discount (NET) | Before discount (GROSS) | ❌ **Server sai** |
| SO line discount | Missing | Applied | ❌ **Client thiếu** |
| PO UOM × price | `price × uomRate` then `qty × price` | `qty × rate × price` | ⚠️ Float order |
| Leave work days | Mon-Sat (6 days) | Mon-Fri (5 days) | ❌ **Inconsistent** |
| SQ line discount | Applied | Applied | ✅ |

### 4. Vietnamese Labor Law Compliance

| Requirement | Status | Issue |
|-------------|--------|-------|
| OT weekday 150% | ✅ | — |
| OT weekend 200% | ❌ | HR-C2: OT weekend hours not aggregated |
| OT holiday 300% | ❌ | HR-C2: OT holiday hours not aggregated |
| Night premium 30% | ⚠️ | HR-C6: Double-counted for overnight shifts |
| Probation 85% salary | ❌ | HR-H3: Not implemented |
| Probation leave accrual | ❌ | HR-M5: Excluded from allocation |
| BHXH/BHYT/BHTN rates | ✅ | Correct rates |
| TNCN progressive brackets | ✅ | Correct 7-band implementation |
| Personal deduction 11M | ✅ | Current per Circular 111 |
| Insurance cap 20× min wage | ⚠️ | HR-M2: Hardcoded 36M, not config-driven |
| Standard work hours Mon-Sat | ❌ | HR-C3: Hardcoded Mon-Fri only |

---

## 📝 GHI CHÚ CHO QUẢN LÝ

### Rủi ro cao nhất
1. **Payroll** — 3 CRITICAL bugs (key mismatch, missing OT types, wrong work days) = lương sai toàn bộ
2. **Asset Depreciation** — Declining balance + report formula sai = khấu hao sai
3. **Finance AP** — Merge collision = AP Payment form crash

### Cần kiểm tra dữ liệu hiện có
- Payroll slips đã tạo có giá trị gần 0 không? (HR-C1)
- Asset book value trên báo cáo list vs detail có khác nhau không? (AST-H3)
- AR Invoice tax amount có khác giữa client submitted vs server stored? (FIN-C2)

### Trade-offs
- Float vs bcmath: Sửa hot path (WAC, FIFO) cần test regression kỹ vì ảnh hưởng toàn bộ inventory valuation
- Leave calculation: Cần quyết định business rule (Mon-Fri vs Mon-Sat) trước khi sửa code
- Phantom BOM waste: Compound vs additive — cần xác nhận ý đồ nghiệp vụ

---

*Tài liệu này được tạo tự động bởi AI audit. Mọi findings cần verify thủ công trước khi sửa.*
*Last updated: 09/04/2026*
