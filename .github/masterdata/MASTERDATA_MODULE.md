# Module: Master Data — Tài liệu chi tiết

> Cập nhật: 2026-04-21 — Schema-sync verified (full scan); Warehouses Oracle Upgrade Complete (exceptions, try/catch, XSS, JS extraction, modals extraction)
> Master Data là module nền tảng cung cấp dữ liệu tham chiếu cho toàn bộ hệ thống ERP.

---

## Tổng quan

| Metric | Giá trị |
|--------|---------|  
| **Controllers** | 8 files (3,531 lines) |
| **Models** | 14 files (5,142 lines) |
| **Services** | 5 core + 3 io (2,658 lines) |
| **DTOs** | 2 files (401 lines) |
| **Requests** | 5 files (940 lines) |
| **Helpers** | 1 file (42 lines) |
| **Views** | 8 subfolders, 57 files (estimated) |
| **JS** | 9 files across `product/`, `partner/`, `uom/`, `warehouse/` (3,720+ lines) |

## Entity Scorecard (vs Purchasing 100%)

| Entity | Score | Service | DTO/Request | \_form | \_modals | Show Partials | JS External | Import/Export | Print | Partner Mapping |
|--------|:-----:|:-------:|:-----------:|:------:|:--------:|:-------------:|:-----------:|:-------------:|:-----:|:---------------:|
| **Products** | **95%** | ✅ 1+3io | ✅/✅ | ✅ 888L | ✅ | ✅ 11 partials | ✅ 4 files | ✅/✅ | ✅ | ✅ full CRUD+import/export |
| **Partners** | **95%** | ✅ 717L (CRUD+sync+import) | ✅/✅ | ✅ 553L | ✅ | ✅ 7 partials | ✅ 3 files | ✅/✅ | ✅ | N/A |
| **UOM** | **85%** | ✅ 3 (292+204+436L) | N/A | N/A (SPA) | ✅ | N/A | ✅ 1 file | ❌/❌ | ❌ | N/A |
| **AttrSets** | **55%** | ❌ | ❌/❌ | ✅ | ✅ | ❌ | ✅ 1 file | ❌/❌ | ❌ | N/A |
| **Categories** | **50%** | ❌ | ❌/✅ 1 | ✅ | ❌ | ❌ | ❌ | ❌/❌ | ❌ | N/A |
| **Warehouses** | **85%** | ❌ (not needed) | ❌/❌ | N/A (SPA) | ✅ 2 files | ❌ | ✅ 1 file | ❌/❌ | ❌ | N/A |
| **Tooling** | **25%** | ❌ | ❌/❌ | ❌ | ❌ | ❌ | ❌ inline | ❌/❌ | ❌ | N/A |

**Products 95% (not 100%)**: Master data không cần workflow/email/mobile/dashboard (khác với Purchasing). 5% còn lại: `_form.php` 888L chưa split, Product.php 1,213L borderline mega-model.

---

## Cấu trúc file hiện tại

```
app/controllers/masterdata/          # 8 controllers
├── ProductsController.php           # 1,326L — Sản phẩm (CRUD + import/export + show + print + partner mapping)
├── PartnersController.php           # 667L — Đối tác KH/NCC/Nhà thầu (thin controller, delegates to PartnerService)
├── PartnerConfigController.php      # Cấu hình partner (auto-code, classification)
├── WarehousesController.php         # 260L — Kho + bins (try/catch, enum whitelist)
├── UomController.php                # 293L — ĐVT (class, unit, conversion) — AJAX SPA, lazy-load services
├── ProductCategoriesController.php  # 211L — Danh mục sản phẩm (tree)
├── AttributeSetsController.php      # 437L — Bộ thuộc tính (dynamic attributes)
└── ToolingController.php            # 312L — Dụng cụ / khuôn mẫu (QR scan)

app/models/masterdata/               # 14 models
├── Product.php                      # 1,300L — SKU, attributes JSON, multi-UOM, import, partner mapping
├── ProductCategory.php              # Category tree (parent_id)
├── ProductAttributeSet.php          # Liên kết product ↔ attribute set
├── ProductImportHistory.php         # Lịch sử import sản phẩm
├── Partner.php                      # 433L — KH/NCC/Nhà thầu (pure data access, no business logic)
├── PartnerConfig.php                # Cấu hình partner types
├── PartnerImportHistory.php         # Lịch sử import partner
├── Warehouse.php                    # 480L — Kho + bins (BusinessException, transaction safety)
├── UomClassModel.php                # Nhóm ĐVT
├── UomUnitModel.php                 # ĐVT cụ thể
├── UomConversionModel.php           # Quy đổi ĐVT
├── AttributeSet.php                 # Bộ thuộc tính
├── AttributeDefinition.php          # Định nghĩa thuộc tính
└── AttributeEnumValue.php           # Giá trị enum

app/services/masterdata/             # 5 services
├── ProductService.php               # ~371L — CRUD + smart name + attr defs + enum cache + print data
├── PartnerService.php               # 717L — Partner CRUD + sync child tables + import coordination
├── UomClassService.php              # 204L — UOM class CRUD + validation + exception handling
├── UomUnitService.php               # 436L — UOM unit CRUD + reference check + cache + SQL injection safelists
└── UomConversionService.php         # 292L — UOM conversion CRUD + atomic updateGlobal + validation

app/services/io/                     # 3 import/export services
├── ProductExportService.php         # 486L — Export products Excel
├── ProductImportService.php         # 419L — Import products Excel
└── ProductImportResultExportService.php # 256L — Export import result

app/dtos/masterdata/                 # 2 DTOs
├── ProductDTO.php
└── PartnerDTO.php

app/requests/masterdata/             # 5 request validators (UOM 5 files đã xóa — dead code, validation trong service)
├── ProductFormRequest.php
├── ProductImportRequest.php
├── PartnerFormRequest.php
├── PartnerImportRequest.php
└── ProductCategoryFormRequest.php

app/helpers/masterdata/
└── MasterdataConstants.php

app/views/masterdata/                # 8 subfolders
├── products/                        # 22 files (4,118 lines total)
│   ├── index.php (455L), add.php (18L), edit.php (27L)
│   ├── show.php (82L shell + 11 partials)
│   ├── _form.php (888L), _modals.php (119L)
│   ├── print.php (223L), import.php (673L), import_history.php (211L)
│   ├── partner_mapping.php (311L), partner_mapping_import.php (503L)
│   └── _show_*.php: header(18), general(106), logistics(38), planning(57),
│       finance(51), partners(177), advanced(28), translations(47),
│       documents(31), action_bar(23), scripts(32)
├── partners/                        # index, add, edit, show(85L shell + 7 partials), print, import, _form(553L), _modals
├── partner_config/                  # index
├── productcategories/               # index, create, edit, _form(286L)
├── attribute_sets/                  # index, create, edit, _form(264L), _modals, _table_fields
├── tooling/                         # index, create(107L), edit(101L), show, scan (QR)
├── uom/                            # index, _modals (SPA via AJAX)
└── warehouses/                      # index(165L), bins(200L), _modals(93L), _bin_modals(96L)

public/js/modules/
├── product/                         # product.js(927L), product-show.js(457L),
│                                    # partner-mapping.js(811L), attribute-set.js(414L)
├── partner/                         # partner.js(280L), partner-form.js(412L), partner-show.js(120L)
├── uom/                            # uom.js
└── warehouse/                      # warehouse.js(175L) — index + bins combined
```

---

## Chi tiết: Products (Entity ưu tiên)

### ✅ show.php — Refactored (Session 17)

**Trước**: 619L monolithic | **Sau**: 101L shell + 11 partials

| Partial | Lines | Nội dung |
|---------|:-----:|----------|
| `_show_header.php` | 18 | Back button + title + status badge |
| `_show_general.php` | 98 | Image + SKU/name/type/UOM/prices + description |
| `_show_logistics.php` | 39 | Weight, origin, HS code + dynamic attributes |
| `_show_planning.php` | 57 | Buy/Sell policies, warehouse/stock, QC link |
| `_show_finance.php` | 39 | Site pricing + GL accounts table |
| `_show_partners.php` | 177 | Suppliers table + Customers table |
| `_show_advanced.php` | 28 | UOM conversions table |
| `_show_translations.php` | 48 | Multi-language (EN/CN/KR/JP) |
| `_show_documents.php` | 31 | File attachments list |
| `_show_action_bar.php` | 14 | Sticky delete + edit buttons |
| `_show_scripts.php` | 34 | JS config objects + script tags |
| **Total** | **583** | + shell 101L = 684L (well-organized) |

### _form.php (916L) — Section Map

| Section | Lines | Size | Potential Split |
|---------|-------|------|-----------------|
| CSS | 1-70 | 70 | Keep |
| Form setup | 72-82 | 11 | Keep |
| Tab Nav | 84-95 | 12 | Keep |
| Tab: General | 97-278 | 182 | Possible `_form_general.php` |
| Tab: Planning | 280-370 | 91 | Possible `_form_planning.php` |
| Tab: Logistics | 372-465 | 94 | Possible `_form_logistics.php` |
| Tab: Finance | 467-570 | 104 | Possible `_form_finance.php` |
| Tab: Partners | 572-810 | 238 | Possible `_form_partners.php` |
| Tab: Advanced | 812-855 | 44 | Small |
| Tab: Language | 857-885 | 29 | Small |
| Tab: Drawings | 887-900 | 14 | Tiny |
| Sticky Actions | 900-905 | 6 | Keep |
| Inline JS | 906-916 | 11 | Move to config |

**Note**: _form.php phức tạp (916L) nhưng chuẩn Purchasing cũng có _form lớn. Ưu tiên show partials trước.

### ProductsController.php (1,326L) — 29 Public Methods

| Method | ~Line | HTTP | Purpose | Perm | CSRF |
|--------|-------|------|---------|:----:|:----:|
| `__construct` | 29 | — | Init models & services | N/A | N/A |
| `index()` | 59 | GET | List + filters + pagination | ✅ | — |
| `add()` | 112 | GET/POST | Create product | ✅ | ✅ |
| `edit($id)` | 165 | GET/POST | Update product | ✅ | ✅ |
| `import()` | 274 | GET/POST | Import Excel | ✅ | ✅ |
| `export()` | 321 | GET | Export Excel | ✅ | — |
| `download_template()` | 332 | GET | Template download | ✅ | — |
| `loadFormView()` | 360 | — | Load dropdowns (private helper) | — | — |
| `print($id)` | 522 | GET | Print A4 ISO form | ✅ | — |
| `partnerMapping()` | 556 | GET | Partner mapping grid page | ✅ | — |
| `partnerMappingData()` | 601 | GET | AJAX: mapping data (search+limit 20) | ✅ | — |
| `partnerMappingSearch()` | 631 | GET | AJAX: search unlinked products | ✅ | — |
| `partnerMappingUpdate()` | 651 | POST | AJAX: bulk save (updates+inserts) | ✅ | ✅ |
| `partnerMappingDelete()` | 710 | POST | AJAX: delete mapping | ✅ | ✅ |
| `partnerMappingExport()` | 738 | GET | Export mapping Excel | ✅ | — |
| `partnerMappingImportView()` | 804 | GET | Import wizard page | ✅ | — |
| `partnerMappingDownloadTemplate()` | 842 | GET | Download mapping template | ✅ | — |
| `partnerMappingImportPreview()` | 930 | POST | AJAX: parse Excel → preview | ✅ | ✅ |
| `partnerMappingImportConfirm()` | 1088 | POST | AJAX: confirm import → DB | ✅ | ✅ |
| `delete($id)` | 1165 | POST | Soft delete | ✅ | ✅ |
| `show($id)` | 1183 | GET | Detail page (tabs+partials) | ✅ | — |
| `import_history()` | 1219 | GET | Import history listing | ✅ | — |
| `download_result()` | 1258 | GET | Download import result file | ✅ | — |
| `import_progress()` | 1309 | GET | AJAX: import progress polling | ✅ | — |
| `getAttributeSetDefinitions()` | 1345 | GET | AJAX: attribute set fields | ✅ | — |
| `getEnumValues()` | 1369 | GET | AJAX: enum dropdown values | ✅ | — |
| `clearEnumCache()` | 1383 | GET | Clear APCu enum cache | ✅ | — |
| `ajax_get_bins()` | 1397 | GET | AJAX: bins by warehouse | ✅ | — |
| `ajax_get_product_by_id()` | 1414 | GET | AJAX: product details by ID | ✅ | — |
| `ajax_get_accounting_categories()` | 1461 | GET | AJAX: cascading dropdown | ✅ | — |

**✅ All 29 methods have permission checks. All POST methods have CSRF validation.**

### Product.php Model (1,300L) — 38 Methods

| Category | Methods | ~Lines | Notes |
|----------|---------|--------|-------|
| Core Read | `getAllProducts`, `getProductsCursor`, `countProducts`, `getProductById`, `getDistinctAttributeSets`, `checkSkuExists`, `findIdBySku` | 260 | Solid |
| Relations | `getUomConversions`, `getProductPartners`, `getActiveDrawings`, `getTranslations`, `getAttachments`, `getAttachmentById` | 80 | OK |
| Write/Sync | `upsertSiteAssignment`, `syncProductPartners`, `syncUomConversions`, `syncTranslations`, `addAttachment`, `deleteAttachment`, `deleteProduct`, `canDeleteProduct` | 260 | OK |
| Import | `getImportReferenceData`, `checkExistingSKUs`, `importProductRow`, `getAttributeTemplate`, `refreshInventoryBalances`, `findBySku` | 250 | Could extract to ProductImportModel |
| Search/API | `getTrackingType`, `searchWithCost`, `searchBySkuOrName`, `searchActiveProducts`, `searchWithStock` | 80 | Used by PO/PR/SO |
| Partner Mapping | `getPartnerMappings`, `countPartnerMappings`, `bulkUpdatePartnerMappings`, `searchUnlinkedProducts`, `insertPartnerMappings`, `deletePartnerMapping` | 170 | Could extract to ProductPartnerModel |

**Note**: 1,300L borderline mega-model. Partner mapping (6 methods) + Import (6 methods) are candidates for extraction.

---

## Upgrade Plan: Products → 100%

### ✅ Phase 1: Show Partials — DONE (Session 17)
- show.php 619L → 101L shell + 11 `_show_*.php` partials
- Inline JS extracted → `_show_scripts.php` (34L)

### ✅ Phase 2: Permissions + Print + Controller Cleanup — DONE (Session 18)
- ✅ Granular permissions: +5 mới (`product.import`, `product.export`, `product.print`, `product.view_cost`, `product.view_price`) → tổng 12 permissions
- ✅ Permission-gated UI: Giá Vốn/Giá Bán ẩn theo `view_cost`/`view_price`, action bar theo `can_edit`/`can_delete`/`can_print`
- ✅ Controller: `import()` → `product.import`, `export()` → `product.export`, `download_template()` thêm `product.import`
- ✅ Fat methods → ProductService: `getAttributeSetDefinitionsForJs()`, `getEnumValuesById()`, `clearEnumValuesCache()`, `getProductPrintData()`
- ✅ Print view `print.php` (A4 ISO format, permission-gated pricing, suppliers/customers/UOM/attributes)

### ✅ Phase 3: Partner Mapping Feature — DONE (Sessions 19-25)
- ✅ Core CRUD: Inline editable grid + bulk save (updates + inserts)
- ✅ Add Products: Modal search unlinked → append to grid → bulk insert
- ✅ Delete: Per-row AJAX delete with confirm → server-side site-scoped DELETE
- ✅ Import/Export: Excel export + dedicated import wizard (preview → confirm pattern from Purchasing)
- ✅ Smart Filter: AJAX search (limit 20) + auto-load on partner selection
- ✅ Select2 searchable partner dropdown + auto-submit on change
- ✅ Keyboard shortcuts: Ctrl+S save, Ctrl+D fill down, paste handler, cell navigation
- ✅ PhpSpreadsheet 2.0+ API migration: `setCellValue([col,row])` syntax

### ✅ Phase 4: Oracle Quality Audit — DONE (Session 25)
- ✅ All 29 controller methods have permission checks
- ✅ All POST methods have CSRF validation (import() fixed)
- ✅ All AJAX endpoints use `$this->json()` (no raw `echo json_encode`)
- ✅ PhpSpreadsheet deprecated API (`*ByColumnAndRow`) fully eliminated
- ✅ Product.php model documented with 38 method audit

### ✅ Phase 5: Partners Oracle Upgrade — DONE (Session 26)
- ✅ show.php 479L monolithic → 85L shell + 7 `_show_*.php` partials (header, profile, general, finance, locations, contacts, banks, scripts)
- ✅ print.php — A4 ISO format with company header, form info, 5 sections
- ✅ print() controller method with type-based permission check
- ✅ partner-show.js — Tab persistence, keyboard shortcuts (ESC/Ctrl+P/Ctrl+E), sortable tables
- ✅ 3x raw `echo json_encode` → `$this->json()` (ajax_search, import x2)
- ✅ 4x missing permission checks added (ajax_check_global, ajax_search, ajax_term_schedule, download_template)

### ✅ Phase 6: Partners Service Layer Refactoring — DONE (Session 27)
- ✅ Partner.php model 1,028L → 433L (58% reduction) — pure data access, no business logic
- ✅ PartnerService.php 300L → 717L — now handles CRUD (createPartner, updatePartner, deletePartner, assignPartnerToSite) + sync child tables (locations, contacts, banks, site assignment) + import
- ✅ PartnersController.php 710L → 667L — delegates all CRUD to PartnerService (thin controller pattern)
- ✅ Import flow simplified: controller reuses existing service instance instead of creating new one
- ✅ Matches Purchasing standard: Controller → Service (business logic) → Model (data access)

### ✅ Phase 7: UOM Oracle Quality Audit — DONE (Session 28)
- ✅ **Delete bug fixed**: UomClassService + UomUnitService trả string thay vì throw exception → controller luôn hiện success. Đã sửa throw BusinessException
- ✅ **Atomic conversion update**: UomController::updateConversion() dùng delete+recreate (non-atomic, data loss risk) → thêm `updateGlobal()` method trong UomConversionService với transaction
- ✅ **deleteGlobal() error handling**: return false → throw exception (consistent error propagation)
- ✅ **get_unit() error handling**: findById throws NotFoundException nhưng controller check `if ($unit)` → proper try/catch
- ✅ **Dead code removed**: 5 UOM Request classes (528 lines) — zero references, validation đã có trong service layer
- ✅ **Lazy-load refactor**: `new UomUnitService()` trong constructor → `$this->service('masterdata/UomUnitService')` qua helper methods (cached, consistent)
- ✅ **SQL injection surface removed**: `findById($id, $columns='*')` với `$columns` interpolated → removed param, hardcoded `SELECT *`
- ✅ **XSS defense**: integer outputs `$unit->id`, `$unit->decimals`, `$class->id`, `$conv->id` → `(int)` cast
- ✅ PHP syntax check: all 8 modified files clean

#### Part 2 — Deep Scan + Cross-Module Audit
- ✅ **SQL injection in `where()`** (UomUnitService): `$allowedColumns` safelist — only `['id','code','name','class_id','conversion_rate','decimals']` permitted. Unrecognized columns logged + rejected
- ✅ **SQL injection in `hasReference()`** (UomUnitService): `$allowedRefs` map with table→columns whitelist (products→3 cols, bom_resources, uom_conversions, uom_classes). Rejected attempts logged
- ✅ **XSS in `_modals.php`**: `$class->id` and `$unit->id` in select option values → `(int)` cast (3 occurrences: class_id select, from_uom_id, to_uom_id)
- ✅ **Cross-module audit**: 27 files reference UOM across all modules — 0 breaking changes confirmed
- ✅ **getConversionRate() callers verified**: PurchaseRequestController, PurchaseOrderController + all others handle `null` properly
- ✅ **UI consistency**: 100% match with Purchasing standard (Bootstrap 5 modals, CSRF tokens, toast notifications)
- ✅ **Input type casting**: All POST data cast at controller boundary — `(int)class_id`, `(float)conversion_rate`, `(int)decimals`, `(int)from_uom_id`, `(int)to_uom_id` (defense-in-depth matching Purchasing)
- ✅ **Modal form validation**: Added `required` attribute to unit name + class name inputs (server already validates, now client-side too)
- ✅ PHP syntax check: all 9 UOM module files clean

**Score: 60% → 80% (Part 1) → 85% (Part 2)** | Remaining 15%: no import/export, no print view (acceptable for config-type entity)

**Known Accepted Issues (Won't Fix):**
- Model CRUD overrides bypass BaseModel audit logging — risk too high for 27 cross-module dependents
- UomUnitModel can't use parent `delete()` — `uom_units` table lacks `deleted_by` column
- Cross-module direct `new UomUnitService()` instantiation — project-wide pattern, not UOM-specific

### ✅ Phase 8: Warehouses Oracle Quality Upgrade — DONE (Session 29)

**Model (Warehouse.php):**
- ✅ All 6 CRUD methods return string → throw `BusinessException` (addWarehouse, updateWarehouse, deleteWarehouse, addBin, updateBin, deleteBin)
- ✅ `deleteWarehouse()` transaction safety: `beginTransaction()/commit()` wrapped in try/catch with `rollBack()` on exception
- ✅ `deleteBin()` reconstructed (was merged with updateBin due to missing closing brace)
- ✅ `require_once BusinessException.php` added (not in autoloader)

**Controller (WarehousesController.php):**
- ✅ All 6 write methods: `$res === true` pattern → `try/catch (Exception $e)` with flash messages
- ✅ `store()` + `update()`: enum whitelist for `locator_control` (none/prespecified/dynamic) and `warehouse_type` (standard/bonded/cold_storage)
- ✅ `$_POST` null coalescing: `$_POST['code']` → `$_POST['code'] ?? ''` (all fields)

**Views — XSS Defense:**
- ✅ `index.php`: 7 XSS fixes — `echo $wh->field` → `echo e($wh->field)`, IDs `(int)$wh->id`
- ✅ `bins.php`: 10 XSS fixes — warehouse header, site info, bin table, info panel all escaped with `e()`

**Views — Extraction (Oracle Standard V2):**
- ✅ `_modals.php` (93L) — Warehouse save + delete modals extracted from index.php
- ✅ `_bin_modals.php` (96L) — Bin save + delete modals extracted from bins.php
- ✅ `index.php` 311L → 165L (47% reduction) — modals + JS removed, includes + config block
- ✅ `bins.php` 352L → 200L (43% reduction) — modals + JS removed, includes + config block

**JavaScript — Centralized:**
- ✅ `public/js/modules/warehouse/warehouse.js` (175L) — handles both index + bins pages
- ✅ `WAREHOUSE_CONFIG` object passed from PHP (page type + URLs)
- ✅ Auto-init via `DOMContentLoaded` based on `WAREHOUSE_CONFIG.page`
- ✅ No global function pollution (except `openSaveModal`/`openBinModal` for button onclick)

**UOM findById Signature Fix (found during upgrade):**
- ✅ `UomUnitModel::findById($id)` → `findById($id, $columns = '*')` (match BaseModel)
- ✅ `UomClassModel::findById($id)` → `findById($id, $columns = '*')` (match BaseModel)
- ✅ `UomConversionModel::findById($id)` → `findById($id, $columns = '*')` (match BaseModel)
- ✅ Full project scan: 1,514 PHP files → 0 syntax errors

**Score: 35% → 85%** | Remaining 15%: no import/export, no print view (acceptable for config-type entity — same as UOM)

### ⏳ Phase 9: Remaining (Future Sessions)
- [ ] Products `_form.php` split (888L) — functional nhưng lớn, ưu tiên thấp (8 tabs well-organized)
- [ ] Product.php Model split (1,300L) → tách Partner Mapping + Import methods
- [ ] Partners _form.php split (553L) — 5 tabs, acceptable size
- [ ] Tooling/Warehouses nâng cấp (xem Entity Scorecard)

---

## Đặc điểm riêng của Master Data

- **Products** hỗ trợ **dynamic attributes** (JSON column) qua Attribute Sets — mỗi ngành có bộ thuộc tính riêng
- **Partners** là bảng unified cho KH, NCC, Nhà thầu — phân loại qua `partner_type`
- **UOM** dùng pattern SPA (AJAX modals) — không có create/edit views riêng. Services throw exceptions consistently, controller lazy-loads via `$this->service()`
- **Warehouses** quản lý cả Bins (vị trí kho con). SPA pattern (AJAX modals) giống UOM. Model throws BusinessException, controller try/catch. External JS at `warehouse/warehouse.js`
- **Tooling** hỗ trợ QR scan (`scan.php`) — **MODEL BỊ BROKEN** (cần fix)
- JS files nằm ở `public/js/modules/product/`, `partner/`, `uom/` (không gom vào `masterdata/`)

---

## Critical Issues

| # | Issue | Severity | Entity | Status |
|---|-------|----------|--------|--------|
| 1 | Tooling model file MISSING — runtime error | 🔴 CRITICAL | Tooling | Open |
| 2 | Product.php 1,300L (borderline mega) → tách Import + Partner mapping | 🟡 MEDIUM | Products | Documented |
| 3 | ~~show.php 619L monolithic~~ → 11 partials (Session 17) | ✅ DONE | Products | Fixed |
| 4 | ~~Controller fat methods~~ → ProductService (Session 18) | ✅ DONE | Products | Fixed |
| 5 | ~~Thiếu print view~~ → print.php (Session 18) | ✅ DONE | Products | Fixed |
| 6 | ~~Permissions thiếu granular~~ → 12 permissions (Session 18) | ✅ DONE | Products | Fixed |
| 7 | ~~Partner mapping CRUD missing~~ → full feature (Sessions 19-25) | ✅ DONE | Products | Fixed |
| 8 | ~~Raw echo json_encode~~ → $this->json() (Audit) | ✅ DONE | Products | Fixed |
| 9 | ~~CSRF missing on import()~~ → validateCSRF() added | ✅ DONE | Products | Fixed |
| 10 | ~~PhpSpreadsheet deprecated API~~ → 2.0+ syntax | ✅ DONE | Products | Fixed |
| 11 | _form.php 888L — lớn nhưng functional (8 tabs) | 🟢 LOW | Products | Acceptable |
| 12 | ~~Partners show.php 466L monolithic~~ → 7 partials (Session 26) | ✅ DONE | Partners | Fixed |
| 13 | ~~Warehouses thiếu form/JS extraction~~ → _modals.php + _bin_modals.php + warehouse.js | ✅ DONE | Warehouses | Fixed |
| 14 | Tooling thiếu toàn bộ architecture | 🟡 HIGH | Tooling | Open |
| 15 | ~~PartnersController raw echo json_encode~~ → $this->json() | ✅ DONE | Partners | Fixed |
| 16 | ToolingController raw echo json_encode (9x) | 🟡 MEDIUM | Tooling | Open |
| 17 | ~~Partners missing permission checks (4 AJAX)~~ → added | ✅ DONE | Partners | Fixed |
| 18 | ~~Partners missing print view~~ → print.php (Session 26) | ✅ DONE | Partners | Fixed |
| 19 | ~~Partner.php 1,028L mega-model~~ → 433L (CRUD → PartnerService) | ✅ DONE | Partners | Fixed |
| 20 | ~~UOM delete bug~~ → services throw BusinessException (Session 28) | ✅ DONE | UOM | Fixed |
| 21 | ~~UOM non-atomic conversion update~~ → updateGlobal() with transaction | ✅ DONE | UOM | Fixed |
| 22 | ~~UOM $columns SQL injection surface~~ → removed param from findById | ✅ DONE | UOM | Fixed |
| 23 | ~~UOM dead Request classes (528L)~~ → deleted (validation in services) | ✅ DONE | UOM | Fixed |
| 24 | ~~UOM direct `new Service()` in constructor~~ → lazy-load `$this->service()` | ✅ DONE | UOM | Fixed |
| 25 | ~~UOM `where()` SQL injection~~ → `$allowedColumns` safelist | ✅ DONE | UOM | Fixed |
| 26 | ~~UOM `hasReference()` SQL injection~~ → `$allowedRefs` table/column whitelist | ✅ DONE | UOM | Fixed |
| 27 | ~~UOM `_modals.php` XSS~~ → `(int)` cast on select option values | ✅ DONE | UOM | Fixed |
| 28 | UOM model CRUD overrides bypass BaseModel audit logging | 🟢 LOW | UOM | Won't Fix (acceptable risk) |
| 29 | ~~UOM POST data not type-cast~~ → `(int)/(float)` at controller boundary | ✅ DONE | UOM | Fixed |
| 30 | ~~UOM modal name inputs missing `required`~~ → added HTML required attr | ✅ DONE | UOM | Fixed |
| 31 | ~~Warehouses model returns error strings~~ → throw BusinessException (6 methods) | ✅ DONE | Warehouses | Fixed |
| 32 | ~~Warehouses controller `$res===true` pattern~~ → try/catch + enum whitelist | ✅ DONE | Warehouses | Fixed |
| 33 | ~~Warehouses XSS in index.php (7) + bins.php (10)~~ → `e()` + `(int)` cast | ✅ DONE | Warehouses | Fixed |
| 34 | ~~Warehouses ~200L inline JS~~ → `warehouse/warehouse.js` (175L) | ✅ DONE | Warehouses | Fixed |
| 35 | ~~Warehouses no `_modals.php`~~ → `_modals.php` + `_bin_modals.php` | ✅ DONE | Warehouses | Fixed |
| 36 | ~~UOM findById signature breaks PHP~~ → added `$columns='*'` param (3 models) | ✅ DONE | UOM | Fixed |
| 37 | ~~Warehouses deleteWarehouse() no rollback~~ → try/catch with rollBack() | ✅ DONE | Warehouses | Fixed |
| 38 | Warehouses no import/export, no print | 🟢 LOW | Warehouses | Acceptable (config entity) |
