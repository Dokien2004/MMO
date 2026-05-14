# Factory ERP — Phương Án Triển Khai Vận Hành Toàn Bộ

**Phiên bản**: v1.1 | **Ngày tạo**: 20/04/2026 | **Cập nhật**: 21/04/2026 (Session 39 — PDO placeholder audit)  
**Deadline Go-Live toàn bộ**: 30/11/2026  
**Thời gian triển khai**: 7 tháng (05/2026 → 11/2026)

---

## I. TỔNG QUAN HIỆN TRẠNG (Tính đến 21/04/2026)

### 1.1 Thống kê mã nguồn (full scan 21/04/2026)

| Metric | Số lượng |
|--------|----------|
| Tổng PHP files | ~700 files |
| Tổng dòng code | ~670,000+ dòng |
| Controllers | 150 files (~63,700 dòng) |
| Models | 147 files (~61,800 dòng) |
| Services | 203 files (~63,800 dòng) |
| Views | 925 files (~152,600 dòng) |
| JavaScript modules | 128 files |
| Configuration | 13 files (~10,000 dòng) |
| Helpers | 78 files (~16,400 dòng) |
| DTOs | 51 files |
| Requests | 69 files |
| Permissions | 189 permission codes |
| Lookup types | 30+ categories |
| Document sequences | 20+ auto-numbered types |
| Menu items | 80+ items |
| Schema | 8,735 dòng (`db_schema.sql`) |
| Empty PHP | 2 (known placeholders: `views/core/approval/dashboard.php`, `views/purchasing/orders/_show_flow.php`) |

### 1.2 Module Completion Scorecard (verified 21/04/2026)

| # | Module | Controllers | Models | Services | Views | JS | Score | Trạng thái |
|---|--------|------------|--------|----------|-------|----|-------|-----------|
| 1 | **Purchasing** | 10 | 7 | 15 | 73 | 7 | **100%** | ✅ Production-ready |
| 2 | **Sales** | 6 | 9 | 20 | 47 | 6 | **100%** | ✅ Production-ready |
| 3 | **Production** | 9 | 16 | 25 | 76 | 11 | **100%** | ✅ Production-ready |
| 4 | **Finance** | 19 | 16 | 25 | 92 | 14 | **100%** | ✅ Production-ready |
| 5 | **HR** | 24 | 24 | 24 | 137 | 22 | **100%** | ✅ Production-ready |
| 6 | **Quality** | 7 | 8 | 11 | 45 | 7 | **100%** | ✅ Production-ready |
| 7 | **Master Data** | 9 | 14 | 5+3(IO) | 63 | 9 | **95%** | ⚠️ Minor gaps |
| 8 | **Inventory** | 26 | 20 | 25 | ~197 | 20 | **95%** | ⚠️ EmailService gap |
| 9 | **Asset** | 5 | 3 | 6 | ~30 | 10 | **70%** | 🔴 Cần hoàn thiện |
| 10 | **PM** | 8 | 10 | 12 | ~20 | 8 | **55%** | 🔴 Cần hoàn thiện |
| 11 | **Core/Systems** | 13 | 12 | 1+ | ~60 | 4+ | **100%** | ✅ Nền tảng ổn |
| 12 | **Portal** | 2 | — | — | ~40 | — | **80%** | ⚠️ PWA cơ bản |

### 1.3 Feature Matrix chi tiết

| Feature | PUR | SAL | PRD | FIN | HR | QUA | INV | MD | AST | PM |
|---------|-----|-----|-----|-----|----|----|-----|----|----|-----|
| CRUD đầy đủ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Workflow/Approval | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | N/A¹ | N/A | ⚠️ | ✅ |
| Mobile views | ✅4 | ✅4 | ✅4 | ✅4 | ✅7 | ✅4 | ✅4+PDA | N/A | ❌ | ❌ |
| Import Excel | ✅2 | ✅2 | ✅1 | ✅JE | ✅ | ✅1 | ✅1 | ✅2 | ❌ | ❌ |
| Export Excel | ✅2 | ✅2 | ✅4 | ✅3 | ✅6 | ✅2 | ✅8 | ✅2 | ⚠️ | ❌ |
| Print views | ✅2 | ✅2 | ✅WO+BOM | ✅AP+AR | ✅7 | ✅2 | ✅7 | ✅2 | ❌ | ❌ |
| Show partials | ✅21 | ✅16 | ✅15 | ✅21 | ✅6 | ✅9 | ✅83 | ✅18 | ✅5 | ✅14 |
| Email Service | ✅2 | ✅2 | ✅2 | ✅ | ✅ | ✅ | ❌ | N/A | ✅2 | ✅2 |
| Dashboard KPI | ✅3 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | N/A | ✅ | ✅ |
| JS show page | ✅ | ✅2 | ✅2 | ✅4 | ✅2 | ✅2 | ✅4 | ✅3 | ✅ | ❌ |
| DTOs | ✅3 | ✅4 | ✅4 | ✅2 | ✅5 | ✅1 | ✅9 | ✅2 | ✅1 | ❌ |
| Request validators | ✅3 | ✅2 | ✅2 | ✅2 | ✅6 | — | ✅12 | ✅5 | ✅2 | ❌ |

> ¹ Inventory: Module giao dịch kho — mỗi Service tự xử lý workflow (approve/cancel/reverse), không cần formal WorkflowService.

### 1.4 Security Audit Status

| Phase | Scope | Issues | Status |
|-------|-------|--------|--------|
| Phase 1 (CRITICAL) | EMULATE_PREPARES, XSS, CSRF, path case | 12 | ✅ Fixed |
| Phase 2 (HIGH) | FinanceConstants, checkLockedDate, hardcode | 7 | ✅ Fixed |
| Phase 3 (Inventory) | Hardcode status, magic numbers, dead code | 3 sub-phases | ✅ Fixed |
| Phase 4 (Site Isolation) | 95 raw SQL queries across 45 files | 95 queries | ✅ Fixed |
| Purchasing Phase 7 | Race condition, div-by-zero, FK-safe upsert | 22 issues | ✅ Fixed |

---

## II. KIẾN TRÚC TRIỂN KHAI

### 2.1 Môi trường

```
┌─────────────────────────────────────────────────────┐
│                   PRODUCTION                        │
│                                                     │
│  ┌──────────┐  ┌──────────┐  ┌──────────────────┐  │
│  │  Apache   │  │  MySQL   │  │   Cron Jobs      │  │
│  │  + PHP    │  │  8.0+    │  │   (6 scheduled)  │  │
│  │  7.4+     │  │  UTF8MB4 │  │                  │  │
│  └──────────┘  └──────────┘  └──────────────────┘  │
│                                                     │
│  ┌──────────────────────────────────────────────┐   │
│  │  Factory ERP Application                     │   │
│  │  ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐ ┌──────┐  │   │
│  │  │ HR  │ │ FIN │ │ PUR │ │ INV │ │ PROD │  │   │
│  │  └─────┘ └─────┘ └─────┘ └─────┘ └──────┘  │   │
│  │  ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐ ┌──────┐  │   │
│  │  │ SAL │ │ QUA │ │  MD │ │ AST │ │  PM  │  │   │
│  │  └─────┘ └─────┘ └─────┘ └─────┘ └──────┘  │   │
│  └──────────────────────────────────────────────┘   │
│                                                     │
│  ┌──────────────────────────────────────────────┐   │
│  │  External Integrations                       │   │
│  │  • ZKTeco (máy chấm công fingerprint)        │   │
│  │  • SMTP (email notifications)                │   │
│  │  • PDA scanners (warehouse)                  │   │
│  └──────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────┘
```

### 2.2 Multi-Tenant Architecture

```
Request → index.php → bootstrap.php → App.php (Router)
                                          ↓
                                   Controller (CSRF + Auth + Permission)
                                          ↓
                                   Service (Business Logic)
                                          ↓
                                   Model (ORM + site_id auto-filter)
                                          ↓
                                   MySQL (site-scoped data)
```

Mọi data đều scoped theo `site_id`:
- `BaseModel` auto-filter `WHERE site_id = :current_site`
- Products/Partners: JOIN qua `*_site_assignments` table
- Child tables: JOIN qua parent table có `site_id`
- 189 permission codes kiểm soát truy cập theo role

### 2.3 Cron Jobs cần cấu hình

| Job | File | Lịch | Mô tả |
|-----|------|------|-------|
| Auto lock attendance | `cron_jobs/auto_lock_attendance.php` | Daily 23:59 | Khóa chấm công cuối ngày |
| Daily attendance sync | `cron_jobs/daily_attendance.php` | Daily 00:30 | Đồng bộ từ máy chấm công |
| Leave allocation | `cron_jobs/leave_allocation.php` | Monthly 1st | Cấp phép nghỉ phép hàng tháng |
| Daily alerts | `cron_jobs/daily_alerts.php` | Daily 08:00 | Gửi cảnh báo (PO/WO/expiry) |
| Inventory cron | `cron_jobs/cron_inventory.php` | Daily 01:00 | Tính lại tồn kho, stock alerts |
| Worker report | `cron_jobs/daily_worker_report.php` | Daily 06:00 | Báo cáo nhân công hàng ngày |

---

## III. KẾ HOẠCH TRIỂN KHAI CHI TIẾT (7 PHASES)

### PHASE 1: HẠ TẦNG & DỮ LIỆU GỐC
**Thời gian**: 01/05/2026 → 31/05/2026 (4 tuần)

#### 1.1 Hạ tầng Production (Tuần 1-2)

| Hạng mục | Chi tiết | Người thực hiện | Checklist |
|----------|----------|----------------|-----------|
| Server | MySQL 8.0+ (UTF-8mb4, InnoDB), Apache + mod_rewrite, PHP 7.4+ | IT Infra | ☐ |
| SSL/HTTPS | Certificate cài đặt, force HTTPS redirect | IT Infra | ☐ |
| Backup | Daily full backup + hourly binlog, retention 30 ngày | IT Infra | ☐ |
| Backup test | Restore thử 1 lần, verify data integrity | IT Infra | ☐ |
| Monitoring | CPU/Memory/Disk alerts, MySQL slow query log | IT Infra | ☐ |
| Environment | `.env.production` (DB, APP_KEY, SMTP, APP_ENV=production) | Dev | ☐ |
| Error logging | `app/logs/` writable, log rotation 7 ngày | Dev | ☐ |
| Session | Secure cookie params, CSRF token init | Dev | ☐ |
| Cron jobs | 6 cron tasks (xem §2.3) configured & tested | IT Infra + Dev | ☐ |

#### 1.2 Import Master Data (Tuần 3-4)

| Dữ liệu | Nguồn | Phương thức | Số lượng ước tính | Checklist |
|----------|-------|-------------|-------------------|-----------|
| **Sites/Plants** | Manual | Admin UI | 1-3 sites | ☐ |
| **Departments** | Excel | Admin import | 10-30 departments | ☐ |
| **Users + Roles** | Manual | Admin UI | 50-100 users | ☐ |
| **Permission matrix** | Config | `permissions_list.php` sync | 189 codes | ☐ |
| **Products (SKU)** | Excel | Products import tool | 500-5,000 SKUs | ☐ |
| **Partners (KH/NCC)** | Excel | Partners import tool | 100-500 partners | ☐ |
| **Warehouses + Bins** | Manual | Admin UI | 3-10 warehouses | ☐ |
| **UOM (đơn vị tính)** | Manual | UOM SPA UI | 20-50 units | ☐ |
| **Product Categories** | Manual | Category tree UI | 20-100 categories | ☐ |
| **Employees** | Excel | HR import tool | 200-1,000 employees | ☐ |
| **Chart of Accounts** | Excel | COA import/manual | 200-500 accounts | ☐ |
| **GL Periods** | Manual | Finance UI | 12 periods FY2026 | ☐ |
| **Cost Centers** | Manual | Finance UI | 10-30 cost centers | ☐ |
| **Tax codes** | Manual | Tax setup UI | 5-10 tax rates | ☐ |
| **Document sequences** | Config | `document_sequences_list.php` | 20+ types | ☐ |
| **Approval workflows** | Manual | Workflow config UI | 5-10 workflows | ☐ |

#### 1.3 Deliverables Phase 1

- ☐ Server production hoạt động ổn định (uptime > 99%)
- ☐ Database schema deployed (`app/db_schema.sql`)
- ☐ Master Data import xong & đối chiếu
- ☐ Users/roles/permissions gán xong
- ☐ Cron jobs chạy đúng lịch
- ☐ Backup tested (restore 1 lần thành công)
- ☐ HTTPS hoạt động
- ☐ Email SMTP tested (gửi/nhận OK)

---

### PHASE 2: GO-LIVE HR + FINANCE
**Thời gian**: 01/06/2026 → 30/06/2026 (4 tuần)

#### 2.1 Scope triển khai

**HR Module (100% ready)**

| Entity | Controllers | Features | Cross-module |
|--------|------------|----------|-------------|
| Employee | EmployeeController | CRUD, import/export, print, show (6 partials) | → Finance (payroll GL) |
| Attendance | AttendanceController | Chấm công, ZKTeco sync, symbols, mobile | → Payroll |
| Leave Request | LeaveRequestController | Nộp/duyệt/hủy, balance tracking, email notify | → Attendance |
| Overtime | OvertimeRequestController | Nộp/duyệt tăng ca, email notify | → Payroll |
| Contract | ContractController | Hợp đồng lao động, renewal tracking | — |
| Payroll | PayrollController | Tính lương, payslip, export, print | → Finance (JE) |
| Leave Balance | LeaveBalanceController | Cấp phát/điều chỉnh phép | — |
| Job Titles | JobTitleController | Danh mục chức danh | — |
| Work Shifts | WorkshiftsController | Ca làm việc, rotation | → Attendance |
| Leave Types | LeaveTypeController | Loại nghỉ phép, quota rules | → Leave Balance |
| Holidays | HolidayController | Lịch nghỉ lễ | → Attendance |
| Attendance Symbol | AttendanceSymbolController | Ký hiệu chấm công (P, K, NB...) | → Attendance |
| Config | ConfigController | Cấu hình HR module | — |

**Finance Module (100% ready)**

| Entity | Controllers | Features | Cross-module |
|--------|------------|----------|-------------|
| Chart of Accounts | CoaController | CRUD, tree view, import | Nền tảng GL |
| Journal Entry | JournalEntryController | Tạo/duyệt/reverse, import Excel, export | GL core |
| GL Period | GlperiodController | Mở/đóng kỳ kế toán | Tất cả modules |
| Cost Center | CostCenterController | CRUD cost centers | GL allocation |
| Exchange Rate | ExchangeRateController | Tỷ giá hối đoái | Multi-currency |
| Tax | TaxController | Mã thuế VAT/GTGT | AP/AR |
| Accounting Rules | AccountingRulesController | Auto GL mapping rules | Auto-posting |
| Payment Terms | PaymentTermController | Điều khoản thanh toán | AP/AR |
| Project (Finance) | ProjectController | Dự án cho cost tracking | GL allocation |

#### 2.2 Kế hoạch tuần

| Tuần | Công việc | Chi tiết | Checklist |
|------|-----------|---------|-----------|
| **Tuần 1** | Deploy HR | Cài đặt HR module, kết nối ZKTeco, import employee data, test chấm công | ☐ |
| **Tuần 2** | Deploy Finance | Cài đặt Finance, import COA + GL periods, test bút toán, cấu hình auto-accounting rules | ☐ |
| **Tuần 3** | UAT | HR team test: chấm công thực tế 1 tuần, nộp đơn nghỉ → duyệt → balance update. Finance team test: nhập JE → duyệt → post → báo cáo | ☐ |
| **Tuần 4** | Training + Go-live | Đào tạo HR team (10-15 người), Finance team (5-8 người). Go-live. Song hành 2 tuần | ☐ |

#### 2.3 Test Scenarios

**HR Test Cases:**

| # | Scenario | Steps | Expected Result |
|---|----------|-------|----------------|
| HR-01 | Chấm công từ máy | Quẹt vân tay → sync → hiển thị attendance | Record attendance + symbol |
| HR-02 | Nộp đơn nghỉ phép | Employee nộp → Manager duyệt → HR xử lý | Balance giảm, email notify |
| HR-03 | Nộp tăng ca | Employee nộp → Manager duyệt | OT hours ghi nhận |
| HR-04 | Tính lương tháng | Chạy payroll → review → approve → print payslip | Payslip PDF, GL entry created |
| HR-05 | Import nhân viên | Upload Excel → validate → confirm | Employee records created |
| HR-06 | Hợp đồng sắp hết | Contract expiry < 30 ngày | Dashboard alert, email notify |
| HR-07 | Mobile chấm công | Truy cập mobile view → xem lịch chấm công | Card-based responsive UI |

**Finance Test Cases:**

| # | Scenario | Steps | Expected Result |
|---|----------|-------|----------------|
| FIN-01 | Tạo bút toán | Tạo JE → Debit/Credit balance → Submit | JE status = Submitted |
| FIN-02 | Duyệt bút toán | Reviewer approve → Post to GL | GL balances updated |
| FIN-03 | Reverse bút toán | Select JE → Reverse → New JE created | Reverse JE with opposite amounts |
| FIN-04 | Đóng kỳ | Close GL period → Lock date | No more postings to closed period |
| FIN-05 | Import JE | Upload Excel → validate → confirm | Multiple JE created |
| FIN-06 | Trial Balance | Chạy report → verify balances | Debit = Credit, matches |
| FIN-07 | Locked date check | Post JE to locked period | Error: Period is locked |

#### 2.4 Deliverables Phase 2

- ☐ HR chấm công hoạt động từ máy ZKTeco
- ☐ Workflow nghỉ phép/tăng ca online (email notifications)
- ☐ Payroll tháng 7/2026 chạy trên hệ thống mới
- ☐ GL hoạt động, bút toán có audit trail
- ☐ Auto-accounting rules configured
- ☐ Training completed cho HR + Finance teams
- ☐ UAT sign-off từ key users

#### 2.5 Rollback Plan

Nếu go-live thất bại:
1. Tắt ERP, thông báo users quay lại quy trình cũ
2. Export dữ liệu đã nhập trên ERP (nếu có)
3. Fix bugs → re-test → schedule go-live lại (delay max 1 tuần)
4. HR: tiếp tục chấm công Excel, payroll spreadsheet
5. Finance: tiếp tục sổ cái thủ công/phần mềm cũ

---

### PHASE 3: GO-LIVE PURCHASING + INVENTORY
**Thời gian**: 01/07/2026 → 31/07/2026 (4 tuần)

#### 3.1 Scope triển khai

**Purchasing Module (100% ready)**

| Entity | Features | Cross-module |
|--------|----------|-------------|
| Purchase Request | PR tạo/duyệt/convert to PO, import Excel | → PO |
| Purchase Order | PO tạo/duyệt/nhận hàng/close, multi-level approval | → Inventory (GRN), Finance (AP) |
| PO Shipment | Tracking shipment, receive partial | → Inventory Receipt |
| Purchase Return | Return to Vendor (RTV) | → Inventory, Finance |
| Reports | PO status, GRN register, supplier performance | Dashboard 3 views |
| Mobile | 4 mobile views (PO list, PO detail, PR list, PR detail) | — |

**Inventory Module (95% ready)**

| Entity | Features | Cross-module |
|--------|----------|-------------|
| Inventory Receipt | Nhận hàng từ PO, manual receipt, print | ← Purchasing |
| Inventory Transfer | Chuyển kho, multi-step approval | Internal |
| Material Issue | Xuất NVL sản xuất | → Production (WO) |
| Material Return | Trả NVL dư | ← Production |
| Delivery Note | Xuất hàng bán | ← Sales (SO) |
| Stock Card | Thẻ kho real-time | Report |
| Lot History | Truy xuất nguồn gốc lô | Report |
| PDA Scanner | Quét barcode nhận/xuất/kiểm | Mobile |
| Inventory Audit | Kiểm kê kho | Periodic |
| Opening Stock | Import tồn đầu kỳ | One-time |
| Material Requisition | Yêu cầu xuất kho | Internal |
| WIP Issue | Xuất NVL vào WIP | → Production |
| GI Request | Yêu cầu xuất kho tổng hợp | Internal |
| Stock Adjustment | Điều chỉnh tồn kho | Periodic |

**Pre-deployment task**: Bổ sung Inventory EmailService (gap 5% duy nhất)

#### 3.2 Kế hoạch tuần

| Tuần | Công việc | Chi tiết | Checklist |
|------|-----------|---------|-----------|
| **Tuần 1** | Deploy Purchasing | PO/PR workflow, approval routing, test multi-level approve | ☐ |
| **Tuần 2** | Deploy Inventory | Import opening stock, test receipt/transfer/issue, PDA setup | ☐ |
| **Tuần 3** | UAT cross-module | Test: PO → GRN → Inventory Receipt → AP Invoice → GL auto-posting. Test PDA scanning | ☐ |
| **Tuần 4** | Training + Go-live | Purchasing team (5-8 người), Warehouse team (10-15 người). Go-live + song hành 2 tuần | ☐ |

#### 3.3 Test Scenarios

| # | Scenario | Steps | Expected Result |
|---|----------|-------|----------------|
| PUR-01 | Tạo PR → Convert PO | Tạo PR → Approve → Convert to PO | PO created from PR lines |
| PUR-02 | PO multi-level approve | PO > threshold → Level 1 → Level 2 approve | PO status = Approved |
| PUR-03 | Nhận hàng (GRN) | PO approved → Receive → Confirm qty | Inventory Receipt created |
| INV-01 | Nhận hàng vào kho | Receipt confirmed → stock tăng | WarehouseStock.qty_on_hand ↑ |
| INV-02 | Chuyển kho | Transfer request → approve → confirm | Stock move from WH A → WH B |
| INV-03 | Xuất NVL | Material Issue → approve → confirm | Stock giảm, WIP tăng |
| INV-04 | PDA quét barcode | Scan barcode → confirm receipt/issue | Mobile card UI, stock updated |
| INV-05 | Kiểm kê | Create audit → count → confirm → adjust | Stock adjusted, GL entry |
| CROSS-01 | PO → Receipt → AP | PO → GRN → AP Invoice auto-create | AP Invoice linked to PO |
| CROSS-02 | Opening stock | Import Excel opening stock → verify | Stock balances match |

#### 3.4 Deliverables Phase 3

- ☐ Quy trình mua hàng end-to-end online
- ☐ Quản lý tồn kho real-time
- ☐ PDA scanner cho warehouse
- ☐ Auto GL entries khi nhập/xuất kho
- ☐ Opening stock imported & đối chiếu
- ☐ Inventory EmailService bổ sung xong

---

### PHASE 4: GO-LIVE PRODUCTION + QUALITY
**Thời gian**: 01/08/2026 → 31/08/2026 (4 tuần)

#### 4.1 Scope triển khai

**Production Module (100% ready)**

| Entity | Features | Cross-module |
|--------|----------|-------------|
| BOM (Bill of Materials) | Multi-level BOM, costing, explosion, where-used | → WO, Inventory |
| Work Order | WO tạo/release/complete/close, traveler print | → Inventory (issue/receipt) |
| Production Plan | MPS planning, WO auto-generation | → WO |
| Shop Floor | Real-time production tracking, machine status | → WO |
| WIP Move | Move transactions, backflush | → Inventory, Finance |
| WIP Completion | Thành phẩm nhập kho | → Inventory |
| Routing/Stages | Quy trình sản xuất, thời gian chuẩn | → WO, ShopFloor |
| Drawing | Quản lý bản vẽ kỹ thuật | → BOM, WO |

**Quality Module (100% ready)**

| Entity | Features | Cross-module |
|--------|----------|-------------|
| QA Inspection | IQC/PQC/FQC, result recording, pass/fail | → Inventory (hold/release) |
| QA Specification | Tiêu chuẩn kiểm tra (spec headers + details) | → Inspection |
| QA Defect | Defect codes, tracking, reporting | → Inspection |
| QC Types | Loại kiểm tra (incoming/process/final) | Config |
| Dashboard | Inspection rates, defect Pareto, trend charts | Report |

#### 4.2 Kế hoạch tuần

| Tuần | Công việc | Chi tiết | Checklist |
|------|-----------|---------|-----------|
| **Tuần 1** | Deploy Production | BOM setup, WO workflow, routing config. Test: tạo WO → release → issue NVL → báo sản lượng | ☐ |
| **Tuần 2** | Deploy Quality | QC spec setup, inspection workflow. Tích hợp IQC (kiểm hàng nhập), PQC, FQC | ☐ |
| **Tuần 3** | UAT cross-module | WO → Material Issue → Shop Floor → QC → WIP Completion → FG Receipt | ☐ |
| **Tuần 4** | Training + Go-live | Production team (15-20 người), QC team (5-10 người). Go-live + song hành | ☐ |

#### 4.3 Test Scenarios

| # | Scenario | Steps | Expected Result |
|---|----------|-------|----------------|
| PRD-01 | Tạo BOM | Create BOM → add components → approve | BOM active, cost calculated |
| PRD-02 | BOM explosion | Explode BOM 3 levels → material list | All raw materials listed |
| PRD-03 | Tạo WO | Create WO from plan → release → print traveler | WO released, traveler printed |
| PRD-04 | Xuất NVL cho WO | WO released → Material Issue → confirm | Stock giảm, WIP tăng |
| PRD-05 | Báo sản lượng | Shop floor → report qty → WIP move | WIP balance updated |
| PRD-06 | Hoàn thành WO | WIP completion → FG receipt → close WO | FG stock tăng, WO closed |
| QUA-01 | Kiểm hàng nhập (IQC) | Receipt → auto-create inspection → record results | Pass: release stock. Fail: hold |
| QUA-02 | Kiểm trong SX (PQC) | WO stage → create inspection → pass/fail | Pass: continue. Fail: rework |
| QUA-03 | Kiểm thành phẩm (FQC) | FG completion → inspection → approve | FG released to ship |
| CROSS-01 | Full production cycle | BOM → WO → Issue → ShopFloor → QC → Complete → FG | End-to-end verified |

#### 4.4 Deliverables Phase 4

- ☐ Quy trình sản xuất end-to-end online
- ☐ BOM management + costing hoạt động
- ☐ QC inspection tích hợp production flow
- ☐ Dashboard sản xuất real-time (OEE, scrap rate, WO status)
- ☐ Shop floor mobile views hoạt động

---

### PHASE 5: GO-LIVE SALES + HOÀN THIỆN AR/AP
**Thời gian**: 01/09/2026 → 30/09/2026 (4 tuần)

#### 5.1 Scope triển khai

**Sales Module (100% ready)**

| Entity | Features | Cross-module |
|--------|----------|-------------|
| Sales Quote | Báo giá → duyệt → convert to SO | → SO |
| Sales Order | Đơn hàng → approve → ship → invoice | → Inventory, Finance |
| Shipment | Tracking giao hàng, partial ship | → Delivery Note |
| Price List | Bảng giá theo customer/product | → SO/SQ |
| Dashboard | Revenue, pending SO, backorders, top customers | Report |

**Finance mở rộng (AP + AR)**

| Entity | Features | Cross-module |
|--------|----------|-------------|
| AP Invoice | Công nợ phải trả từ PO | ← Purchasing |
| AP Payment | Thanh toán NCC | → GL |
| AR Invoice | Công nợ phải thu từ SO | ← Sales |
| AR Receipt | Thu tiền KH | → GL |
| AP Reports | Aging, vendor balances, payment schedule | Report |
| AR Reports | Aging, customer balances, collection | Report |
| Balance Sheet | Bảng cân đối kế toán | Report |
| Trial Balance | Bảng cân đối phát sinh | Report |

#### 5.2 Kế hoạch tuần

| Tuần | Công việc | Chi tiết | Checklist |
|------|-----------|---------|-----------|
| **Tuần 1** | Deploy Sales | SQ/SO workflow, price list setup. Test: báo giá → SO → approve | ☐ |
| **Tuần 2** | Deploy AR/AP đầy đủ | AP Invoice (từ PO), AR Invoice (từ SO), payment/receipt. Import số dư công nợ đầu kỳ | ☐ |
| **Tuần 3** | UAT full cycle | SO → pick → ship → AR Invoice → receipt → GL. PO → GRN → AP Invoice → payment → GL | ☐ |
| **Tuần 4** | Training + Go-live | Sales team (5-10 người), Accounting mở rộng AR/AP. Go-live + song hành | ☐ |

#### 5.3 Test Scenarios

| # | Scenario | Steps | Expected Result |
|---|----------|-------|----------------|
| SAL-01 | Báo giá → Đơn hàng | Create SQ → approve → convert to SO | SO created from SQ lines |
| SAL-02 | SO → Giao hàng | SO approved → create shipment → confirm delivery | Delivery Note, stock giảm |
| SAL-03 | Partial shipment | SO 100 pcs → ship 60 → ship 40 | 2 shipments, SO = Shipped |
| SAL-04 | SO → AR Invoice | Shipment confirmed → AR Invoice auto-create | AR linked to SO |
| SAL-05 | Thu tiền KH | AR Invoice → create receipt → apply | AR balance giảm, GL updated |
| FIN-08 | AP Payment | AP Invoice → create payment → confirm | AP balance giảm, GL updated |
| FIN-09 | AP Aging report | Run AP aging → verify buckets | Current/30/60/90/120+ correct |
| FIN-10 | AR Aging report | Run AR aging → verify buckets | Current/30/60/90/120+ correct |
| CROSS-01 | Order-to-Cash | SQ → SO → Ship → AR Invoice → Receipt → GL | Full revenue cycle |
| CROSS-02 | Procure-to-Pay | PR → PO → GRN → AP Invoice → Payment → GL | Full expense cycle |

#### 5.4 Deliverables Phase 5

- ☐ Quy trình bán hàng end-to-end online
- ☐ AR/AP hoàn chỉnh với auto GL posting
- ☐ Báo cáo tài chính đầy đủ (TB, BS, AP/AR Aging)
- ☐ Cross-module flows (Order-to-Cash, Procure-to-Pay) verified

---

### PHASE 6: HOÀN THIỆN & GO-LIVE ASSET + PM
**Thời gian**: 01/10/2026 → 31/10/2026 (4 tuần)

#### 6.1 Development Tasks — Asset Module (70% → 100%)

| # | Task | Files cần tạo/sửa | LOC ước tính | Tuần |
|---|------|-------------------|-------------|------|
| A-01 | Consolidate JS: `asset.js` | `public/js/modules/asset/asset.js` (new) | ~400 | T1 |
| A-02 | Mobile views (4 files) | `app/views/assets/asset/mobile_*.php` | ~300 | T1 |
| A-03 | Print view: Asset Register | `app/views/assets/reports/print_register.php` | ~150 | T1 |
| A-04 | AssetExportService | `app/services/asset/AssetExportService.php` | ~250 | T1 |
| A-05 | Import wiring | Edit ManagerController + AssetService | ~100 | T2 |
| A-06 | GL depreciation posting | Edit DepreciationService + AutoAccounting | ~200 | T2 |
| A-07 | AssetDTOs completion | Edit existing DTOs | ~50 | T2 |
| | **Total Asset dev** | | **~1,450** | |

#### 6.2 Development Tasks — PM Module (55% → 100%)

| # | Task | Files cần tạo/sửa | LOC ước tính | Tuần |
|---|------|-------------------|-------------|------|
| P-01 | Mobile views (4 files) | `app/views/pm/*/mobile_*.php` | ~400 | T1 |
| P-02 | ProjectExportService | `app/services/pm/ProjectExportService.php` | ~300 | T1 |
| P-03 | TaskExportService | `app/services/pm/TaskExportService.php` | ~200 | T1 |
| P-04 | Print views (2 files) | `app/views/pm/project/print.php`, `report/print.php` | ~300 | T2 |
| P-05 | PM DTOs (3 files) | `app/dtos/pm/ProjectDTO.php`, `TaskDTO.php`, `AcceptanceDTO.php` | ~300 | T2 |
| P-06 | PM Request validators (4 files) | `app/requests/pm/Store*.php`, `Update*.php` | ~300 | T2 |
| P-07 | PmDashboardHelper | `app/helpers/pm/PmDashboardHelper.php` | ~150 | T2 |
| P-08 | PmCalculationHelper | `app/helpers/pm/PmCalculationHelper.php` | ~100 | T2 |
| | **Total PM dev** | | **~2,050** | |

#### 6.3 Kế hoạch tuần

| Tuần | Công việc | Chi tiết | Checklist |
|------|-----------|---------|-----------|
| **Tuần 1** | Dev Asset + PM (phần 1) | JS consolidation, mobile views, export services | ☐ |
| **Tuần 2** | Dev Asset + PM (phần 2) | Print, DTOs, requests, helpers, GL integration | ☐ |
| **Tuần 3** | UAT Asset + PM | Test: tạo tài sản → khấu hao → bàn giao → thanh lý → GL. Test: project → task → nghiệm thu → warranty | ☐ |
| **Tuần 4** | Training + Go-live | Asset team (3-5 người), PM team (5-8 người). Go-live | ☐ |

#### 6.4 Test Scenarios

| # | Scenario | Steps | Expected Result |
|---|----------|-------|----------------|
| AST-01 | Tạo tài sản | Create asset → assign department → upload image | Asset registered |
| AST-02 | Khấu hao tháng | Run depreciation → review → post GL | GL entries, book value giảm |
| AST-03 | Bàn giao | Handover asset → new employee signs | Location/department updated |
| AST-04 | Thanh lý | Dispose asset → approve → GL entry | Asset disposed, GL posted |
| AST-05 | Bảo trì | Create maintenance → assign → complete | Maintenance history logged |
| AST-06 | Kiểm kê TS | Stocktake → count → confirm → adjust | Asset records updated |
| PM-01 | Tạo project | Create → assign team → set milestones | Project active |
| PM-02 | Quản lý task | Create tasks → assign → track progress | Gantt/Kanban updated |
| PM-03 | Nghiệm thu | Create acceptance → approve → sign | Acceptance signed |
| PM-04 | Khiếu nại | Create complaint → assign → resolve | Complaint closed |
| PM-05 | Bảo hành | Create warranty → claim → resolve | Warranty claim resolved |

#### 6.5 Deliverables Phase 6

- ☐ Asset module 100% — mobile, print, export, GL integration
- ☐ PM module 100% — mobile, export, print, DTOs/requests
- ☐ Tất cả 10+ modules đã go-live
- ☐ Verify: `Get-ChildItem -Recurse app/ -Filter *.php | Where-Object { $_.Length -eq 0 }` = 0 (trừ 2 placeholder)

---

### PHASE 7: ỔN ĐỊNH & CHUYỂN GIAO
**Thời gian**: 01/11/2026 → 30/11/2026 (4 tuần)

#### 7.1 Kế hoạch tuần

| Tuần | Công việc | Chi tiết | Checklist |
|------|-----------|---------|-----------|
| **Tuần 1** | Bug fixing tổng thể | Fix tất cả bugs phát sinh từ 5 phase. Performance tuning (slow queries, missing indexes) | ☐ |
| **Tuần 2** | Báo cáo nâng cao | P&L theo phòng ban, Budget vs Actual, Inventory aging, Production efficiency, KPI dashboards | ☐ |
| **Tuần 3** | Tắt hệ thống cũ | Chuyển 100% sang ERP. Đối chiếu số liệu cuối cùng (GL, Stock, AR/AP balances). Archive data cũ | ☐ |
| **Tuần 4** | Bàn giao chính thức | User manual per module. Admin guide (backup, monitoring, troubleshooting). Hypercare kết thúc → BAU | ☐ |

#### 7.2 Performance Optimization Checklist

| Hạng mục | Chi tiết | Checklist |
|----------|---------|-----------|
| MySQL indexes | Audit slow query log → add missing indexes | ☐ |
| Query optimization | Identify N+1 queries, JOIN optimization | ☐ |
| PHP OPcache | Enable OPcache cho production | ☐ |
| Static assets | CDN hoặc Apache mod_expires cho JS/CSS/images | ☐ |
| Session cleanup | Session GC probability tuning | ☐ |
| Log rotation | Verify log rotation hoạt động (7-day retention) | ☐ |
| Backup verify | Monthly restore test procedure documented | ☐ |

#### 7.3 Bàn giao tài liệu

| Tài liệu | Ngôn ngữ | Đối tượng | Checklist |
|-----------|---------|-----------|-----------|
| User Manual - HR | Tiếng Việt | HR team | ☐ |
| User Manual - Finance | Tiếng Việt | Accounting team | ☐ |
| User Manual - Purchasing | Tiếng Việt | Purchasing team | ☐ |
| User Manual - Inventory | Tiếng Việt | Warehouse team | ☐ |
| User Manual - Production | Tiếng Việt | Production team | ☐ |
| User Manual - Sales | Tiếng Việt | Sales team | ☐ |
| User Manual - Quality | Tiếng Việt | QC team | ☐ |
| User Manual - Asset | Tiếng Việt | Admin/Accounting | ☐ |
| User Manual - PM | Tiếng Việt | PM team | ☐ |
| Admin Guide | Tiếng Việt | IT team | ☐ |
| Troubleshooting FAQ | Tiếng Việt | IT + Super Users | ☐ |

#### 7.4 Deliverables Phase 7 (Final)

- ☐ Zero critical bugs outstanding
- ☐ Performance: page load < 3s, report < 10s
- ☐ Hệ thống cũ tắt hoàn toàn
- ☐ Số liệu đối chiếu khớp (GL, Stock, AR/AP)
- ☐ Tài liệu bàn giao đầy đủ (11 documents)
- ☐ Hypercare period kết thúc
- ☐ BAU support process documented

---

## IV. NGUỒN LỰC

### 4.1 Team cần thiết

| Vai trò | Số lượng | Phase | Trách nhiệm |
|---------|----------|-------|-------------|
| Project Manager | 1 | 1-7 | Quản lý tiến độ, risk, stakeholder |
| PHP Developer (Senior) | 1 | 1-7 | Backend dev, bug fixing, Asset+PM completion |
| PHP Developer (Mid) | 1 | 2-6 | Support dev, testing, view fixes |
| Frontend Developer | 1 | 2-6 | JS modules, mobile views, UI polish |
| QA Tester | 1-2 | 2-7 | UAT, regression testing mỗi phase |
| IT Infrastructure | 1 | 1, 7 | Server setup, backup, monitoring, performance |
| Business Analyst | 1 | 1-5 | Requirements, data mapping, user training |
| Key Users (per module) | 2-3/module | 2-6 | UAT testing, feedback, training support |

### 4.2 Effort Estimate (person-days)

| Phase | PM | Dev Senior | Dev Mid | Frontend | QA | IT | BA | Total |
|-------|----|-----------:|--------:|---------:|---:|---:|---:|------:|
| Phase 1 | 10 | 8 | 5 | 0 | 0 | 10 | 8 | **41** |
| Phase 2 | 8 | 5 | 5 | 3 | 5 | 2 | 5 | **33** |
| Phase 3 | 8 | 5 | 5 | 3 | 5 | 2 | 5 | **33** |
| Phase 4 | 8 | 5 | 5 | 3 | 5 | 1 | 5 | **32** |
| Phase 5 | 8 | 5 | 5 | 3 | 5 | 1 | 5 | **32** |
| Phase 6 | 10 | 15 | 10 | 8 | 5 | 1 | 3 | **52** |
| Phase 7 | 10 | 8 | 5 | 3 | 5 | 5 | 5 | **41** |
| **Total** | **62** | **51** | **40** | **23** | **30** | **22** | **36** | **264** |

---

## V. RỦI RO & BIỆN PHÁP

| # | Rủi ro | Mức độ | Xác suất | Biện pháp phòng ngừa | Biện pháp xử lý |
|---|--------|--------|----------|----------------------|-----------------|
| R1 | Data migration sai lệch | Cao | Trung bình | Import thử 3 lần trước go-live, đối chiếu từng batch | Re-import, manual correction, rollback |
| R2 | Users không chịu dùng | Cao | Trung bình | Training kỹ, super-user hỗ trợ, UI thân thiện | Change management, executive sponsor push |
| R3 | Bug critical sau go-live | Trung bình | Trung bình | UAT kỹ mỗi phase, chạy song song 2 tuần | Hotfix < 24h, rollback plan sẵn |
| R4 | Server performance issues | Trung bình | Thấp | Load test trước mỗi phase, index optimization | Scale up server, query optimization |
| R5 | Asset/PM dev trễ deadline | Trung bình | Trung bình | Buffer 2 tuần ở Phase 7, clear dev tasks | Reduce scope Phase 6, prioritize critical features |
| R6 | Key person nghỉ việc | Cao | Thấp | Cross-training, documentation đầy đủ | Backup person assigned cho mỗi role |
| R7 | ZKTeco integration fail | Thấp | Thấp | Test kết nối sớm ở Phase 1 | Fallback: manual attendance entry |
| R8 | Cross-module data inconsistency | Cao | Thấp | Transaction + FK constraints, audit log | Manual reconciliation, DB fix scripts |

---

## VI. MILESTONE & SIGN-OFF

| Milestone | Date | Deliverable | Sign-off |
|-----------|------|-------------|----------|
| M1 | 31/05/2026 | Hạ tầng + Master Data ready | IT Manager + Business Owner |
| M2 | 30/06/2026 | HR + Finance go-live | HR Director + CFO |
| M3 | 31/07/2026 | Purchasing + Inventory go-live | Procurement Manager + Warehouse Manager |
| M4 | 31/08/2026 | Production + Quality go-live | Production Director + QC Manager |
| M5 | 30/09/2026 | Sales + AR/AP go-live | Sales Director + CFO |
| M6 | 31/10/2026 | Asset + PM go-live (all modules) | CFO + PM Manager |
| M7 | 30/11/2026 | Stabilization complete, handover | CEO + All Department Heads |

---

## VII. PHỤ LỤC

### 7.1 Cross-Module Integration Map

```
                    ┌──────────┐
                    │  Master  │
                    │   Data   │
                    └────┬─────┘
                         │ (Products, Partners, Warehouses, UOM)
          ┌──────────────┼──────────────┐
          ▼              ▼              ▼
    ┌──────────┐  ┌──────────┐   ┌──────────┐
    │Purchasing│  │  Sales   │   │Production│
    │ PR → PO  │  │ SQ → SO  │   │BOM → WO  │
    └────┬─────┘  └────┬─────┘   └────┬─────┘
         │              │              │
         ▼              ▼              ▼
    ┌──────────────────────────────────────┐
    │            INVENTORY                 │
    │  Receipt ← PO    Delivery ← SO      │
    │  Issue → WO      Transfer (internal) │
    │  PDA Scanner     Lot Tracking        │
    └──────────────────┬───────────────────┘
                       │
         ┌─────────────┼─────────────┐
         ▼             ▼             ▼
    ┌──────────┐ ┌──────────┐ ┌──────────┐
    │ Quality  │ │  Finance │ │   HR     │
    │IQC/PQC   │ │GL/AP/AR  │ │Attendance│
    │FQC/Defect│ │JE/Reports│ │Payroll   │
    └──────────┘ └────┬─────┘ └──────────┘
                      │
              ┌───────┼───────┐
              ▼       ▼       ▼
         ┌──────┐ ┌──────┐ ┌──────┐
         │Asset │ │  PM  │ │Portal│
         │FA/Dep│ │Proj/ │ │PWA   │
         │Maint │ │Task  │ │Self  │
         └──────┘ └──────┘ └──────┘
```

### 7.2 Document Flow Standards

| Flow | Documents | Status Lifecycle |
|------|-----------|-----------------|
| **Procure-to-Pay** | PR → PO → GRN → AP Invoice → AP Payment | Draft → Submitted → Approved → Received → Invoiced → Paid → Closed |
| **Order-to-Cash** | SQ → SO → Delivery → AR Invoice → AR Receipt | Draft → Submitted → Approved → Shipped → Invoiced → Paid → Closed |
| **Plan-to-Produce** | Plan → WO → Material Issue → WIP → FG Receipt | Draft → Released → In Progress → Completed → Closed |
| **Record-to-Report** | JE → GL → TB → BS/P&L | Draft → Submitted → Approved → Posted |
| **Hire-to-Pay** | Employee → Contract → Attendance → Payroll → Payslip | Active → On-contract → Tracked → Calculated → Paid |
| **Asset Lifecycle** | Acquire → Capitalize → Depreciate → Maintain → Dispose | Draft → Active → In-Service → Maintained → Disposed |
| **Project Lifecycle** | Plan → Execute → Monitor → Accept → Close | Planning → In Progress → On Hold → Acceptance → Completed → Warranty |

### 7.3 Go-Live Readiness Checklist (per Phase)

```
PRE-GO-LIVE:
  ☐ UAT sign-off từ key users (documented)
  ☐ Data migration verified (reconciliation report)
  ☐ Backup tested (restore 1 lần thành công)
  ☐ Rollback plan documented & reviewed
  ☐ Training completed (attendance log signed)
  ☐ Cron jobs verified
  ☐ Mobile views tested trên thiết bị thực
  ☐ Permission matrix reviewed & approved
  ☐ Email notifications tested
  ☐ PHP syntax check pass: php -l (all files)
  ☐ Zero empty PHP files (trừ 2 placeholder)
  ☐ No merge conflict markers
  ☐ Performance: page load < 3s

POST-GO-LIVE:
  ☐ Monitor error logs 24h đầu tiên
  ☐ Daily reconciliation 1 tuần đầu
  ☐ Hotfix response < 4h cho critical bugs
  ☐ Weekly progress report
  ☐ Song hành 2 tuần (old + new system)
  ☐ Tắt hệ thống cũ sau confirm OK
```

---

**Tài liệu liên quan:**
- `.github/MODULE_COMPLETION_ROADMAP.md` — Chi tiết completion status per module
- `.github/oracle-erp/modules/` — Audit reports per module
- `AGENTS.md` — Technical architecture reference
- `.github/copilot-instructions.md` — Coding conventions & patterns

---

*Tạo bởi: AI Assistant | Ngày: 20/04/2026 | Version: 1.0*
