# Oracle ERP Knowledge Base cho AI Agent

**Phiên bản**: v1.2 | **Cập nhật**: 21/04/2026  
**Mục đích**: Knowledge base chuẩn Oracle EBS giúp AI (Claude/Copilot) hỗ trợ lập trình Factory ERP theo enterprise-grade standards.

---

## Cấu Trúc Thư Mục

```
.github/oracle-erp/
│
├── README.md                          # File này - Tổng quan & hướng dẫn sử dụng
│
├── architecture/                      # Kiến trúc nền tảng Oracle EBS
│   ├── oracle-erp-mapping.md          # Map Factory ERP ↔ Oracle EBS modules
│   ├── enterprise-patterns.md         # Core patterns (Multi-Org, Flexfields, Profiles)
│   ├── data-flow-standards.md         # Document flow, status lifecycle, approval chains
│   └── product-module-zero-hardcode.md # Product EAV zero-hardcode architecture (v24/03)
│
├── modules/                           # Đặc tả từng module theo chuẩn Oracle
│   ├── inventory-inv.md               # Oracle Inventory (INV)
│   ├── purchasing-po.md               # Oracle Purchasing (PO)
│   ├── sales-om.md                    # Oracle Order Management (OM)
│   ├── production-wip.md              # Oracle WIP + BOM
│   ├── finance-gl-ap.md               # Oracle GL + AP + AR
│   ├── hr-hrms.md                     # Oracle HRMS
│   ├── quality-qa.md                  # Oracle Quality (QA)
│   ├── masterdata-inv-items.md        # Oracle INV Items/Setup
│   ├── asset-fa.md                    # Oracle Fixed Assets (FA)
│   └── project-pa.md                  # Oracle Project Accounting (PA)
│
├── patterns/                          # Design patterns Oracle-style
│   ├── workflow-engine.md             # Oracle Workflow & Approval patterns
│   ├── costing-methods.md             # Standard/Average/FIFO costing
│   ├── document-numbering.md          # Sequence generation & document coding
│   ├── multi-org-security.md          # Multi-Org, site scoping, data isolation
│   ├── period-control.md              # GL Period management (Open/Close/Frozen)
│   └── interface-patterns.md         # API, Integration, Import/Export patterns
│
├── compliance/                        # Theo dõi mức độ tuân thủ Oracle
│   ├── module-tracker.md              # Coverage % per module + action items
│   └── gap-analysis.md               # Chi tiết gaps vs Oracle EBS
│
├── templates/                         # Code templates cho code generation
│   ├── model-template.php             # Template Model class
│   ├── service-template.php           # Template Service class
│   ├── controller-template.php        # Template Controller class
│   ├── dto-template.php               # Template DTO class
│   ├── request-template.php           # Template Request class
│   └── migration-template.sql         # Template DB migration
│
└── database/                          # Database design standards
    ├── naming-conventions.md          # Table/column naming rules
    ├── audit-columns.md               # ⚠️ Chưa tạo — Standard audit trail & soft delete
    └── indexing-strategy.md           # ⚠️ Chưa tạo — Index design principles
```

---

## Cách Sử Dụng

### Khi AI Tạo Feature Mới
1. Đọc `architecture/oracle-erp-mapping.md` → Xác định module Oracle tương ứng
2. Đọc `modules/{module}.md` → Hiểu business rules & data model Oracle
3. Đọc `patterns/` → Apply đúng design patterns
4. Dùng `templates/` → Scaffold code nhanh theo chuẩn
5. Update `compliance/module-tracker.md` → Track progress

### Khi AI Sửa Bug / Refactor
1. Đọc `compliance/gap-analysis.md` → Xem issue đã được track chưa
2. Đọc `patterns/` → Đảm bảo fix theo đúng Oracle pattern
3. Đọc `database/` → Đảm bảo schema changes follow convention

### Khi AI Review Code
1. Cross-check với `architecture/enterprise-patterns.md`
2. Verify security theo `patterns/multi-org-security.md`
3. Check costing logic theo `patterns/costing-methods.md`

---

## Quy Tắc Vàng (Golden Rules)

| # | Rule | Oracle Equivalent |
|---|------|-------------------|
| 1 | Mọi transaction phải trong GL Period đang mở | Oracle Period Control |
| 2 | Mọi document có status lifecycle rõ ràng | Oracle Document Workflow |
| 3 | Costing phải theo Weighted Average (chuẩn VN) | Oracle Average Costing |
| 4 | Multi-site isolation bắt buộc | Oracle Multi-Org |
| 5 | Audit trail cho mọi thay đổi | Oracle Audit Trail |
| 6 | Sequence numbering không được gap | Oracle Document Sequences |
| 7 | 3-Way Match cho AP Invoice | Oracle AP 3-Way Matching |
| 8 | BOM phải có version control | Oracle BOM Revision |
| 9 | Inventory sub-ledger = GL balance | Oracle Subledger Accounting |
| 10 | Approval hierarchy configurable | Oracle AME (Approval Mgmt Engine) |
| 11 | **KHÔNG hardcode** — Mọi giá trị cấu hình phải qua config file hoặc DB | Oracle Lookups + Profile Options |
| 12 | Lookup values (status, type, dropdown) phải dùng `lookups_list.php` → sync DB | Oracle FND_LOOKUP_VALUES |
| 13 | **SQL query phải đúng 100%** tên bảng, tên cột — tra `app/db_schema.sql` trước khi viết | Oracle Data Dictionary |
| 14 | **Dùng Constants class** để so sánh status trong code — KHÔNG hardcode string | Oracle Lookup Functions |

---

## Module Oracle EBS Tương Ứng

| Factory ERP Module | Oracle EBS Module | Code |
|-------------------|-------------------|------|
| Master Data | Oracle Inventory (Items/Setup) | INV |
| Inventory/Logistics | Oracle Inventory | INV |
| Purchasing | Oracle Purchasing | PO |
| Sales | Oracle Order Management | OM |
| Production | Oracle WIP + BOM | WIP/BOM |
| Finance | Oracle GL + AP + AR | GL/AP/AR |
| HR | Oracle HRMS | HRMS |
| Quality | Oracle Quality | QA |
| Asset | Oracle Fixed Assets | FA |
| Project Management | Oracle Projects | PA |
