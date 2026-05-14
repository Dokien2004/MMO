# Alert & Notification Centralization Plan

> **Ngày tạo**: 2026-04-07 | **Cập nhật**: 21/04/2026  
> **Trạng thái**: 📋 Planned (chưa triển khai)  
> **Mục tiêu**: Thống nhất toàn bộ alert/confirm/toast trong ứng dụng về 1 file JS duy nhất

---

## 1. Tình trạng hiện tại (Khảo sát 2026-04-07)

### Tóm tắt

| Pattern | Số lượng | Thư viện | Vấn đề |
|---------|----------|----------|--------|
| Copy-paste `showAlert()` | ~28 file JS | Bootstrap alert div | Trùng lặp nghiêm trọng, mỗi file tự define |
| Raw `confirm()` | ~50+ chỗ gọi | Native browser | UX xấu, không style được |
| Raw `alert()` | ~60+ chỗ gọi | Native browser | UX xấu, block thread |
| `Swal.fire()` trực tiếp | ~80+ chỗ gọi | SweetAlert2 @11 | OK nhưng không qua wrapper thống nhất |
| `INV.confirm/toast()` | ~20+ chỗ gọi | Custom Bootstrap modal | Riêng Inventory, không dùng Swal |
| HR `showConfirm/Success()` | ~10+ chỗ gọi | Swal wrapper | Riêng HR (hr-common.js) |
| Global `showNotification()` | 1 definition | Swal toast (footer.php) | Có sẵn nhưng gần như không ai gọi |
| `flash()` server-side | ~100+ view calls | Swal toast (PHP render) | Hoạt động tốt, giữ nguyên |

### Hạ tầng đã có sẵn

- **SweetAlert2 @11** — load global qua `header.php` (CSS) + `footer.php` (JS)
- **`showNotification()`** — đã define trong `footer.php`, dùng `Swal.fire()` toast mode
- **`flash()`** — server-side, render `<script>Swal.fire()</script>` — đang hoạt động tốt

### Chi tiết files có `showAlert()` trùng lặp

**HR Module** (~12 files):
`employee.js`, `attendance.js`, `contract.js`, `leave_request.js`, `payroll.js`, `holiday.js`, `work_shift.js`, `overtime_request.js`, `leave_type.js`, `attendance_symbol.js`, `performance.js`, `hr-dashboard.js`

**Finance Module** (~5 files):
`tax.js`, `payment_term.js`, `glperiod.js`, `exchange_rate.js`, `ar_invoice_show.js` (as `_arShowAlert`)

**Production Module** (~5 files):
`bom.js`, `workorder_show.js` (as `woShowAlert`), `wipcompletion.js` (as `showNotification`), `production_config.js`, `operation-attribute-set.js` (as `showSuccessAlert`)

**Master Data** (~4 files):
`product.js`, `partner-mapping.js`, `attribute-set.js` (as `showSuccessAlert`), `uom.js`

**Other**:
`form.js` (asset), `transactions.js` (asset), `stock_adjustment.js`, `pick_execute.js`, `pick_confirm.js`, `pricelist-show.js`, `misa-sync.js`, `module-config.js`, `project_products.js`

### Chi tiết raw `confirm()` / `alert()` nặng nhất

| Module | Raw `alert()` | Raw `confirm()` | Files |
|--------|--------------|-----------------|-------|
| Production | ~35 | ~12 | `shopfloor.js`, `wipcompletion.js`, `operation-attribute-set.js`, `workorder_show.js` |
| Finance | ~9 | ~12 | `ap_invoice_show.js`, `ar_invoice_show.js`, `tax.js`, `ar_invoice_form.js` |
| Partner | ~4 | — | `partner.js`, `partner-form.js` |

### Module đã dùng Swal tốt (tham chiếu)

- **Quality** — `inspection.js`, `defect.js`, `characteristic.js`, `qcstatus.js`, `qctype.js` — **fully Swal-native, nhất quán nhất**
- **Sales show pages** — `salesquote_show.js`, `salesorder_show.js` — Swal with loading states
- **Purchasing** — `purchase_return.js` — Swal with graceful fallback

---

## 2. Giải pháp: `public/js/core/app-alerts.js`

### 2.1 API Design

```javascript
// File: public/js/core/app-alerts.js
// Load: footer.php (trước các module JS)
// Dependency: SweetAlert2 @11 (đã load global)

const App = window.App || {};

/**
 * Toast notification (góc phải trên, auto-dismiss 3s)
 * Thay thế: 28× showAlert(), showSuccessAlert(), showToast()
 * 
 * @param {string} msg     - Nội dung thông báo
 * @param {string} type    - 'success' | 'error' | 'warning' | 'info'
 * @param {number} timer   - Auto-close ms (default 3000)
 */
App.toast = function(msg, type = 'success', timer = 3000) { ... };

/**
 * Confirm dialog (có Hủy + Xác nhận)
 * Thay thế: 50× confirm(), 11× showConfirm()
 * 
 * @param {string}   msg       - Câu hỏi xác nhận
 * @param {Function} onConfirm - Callback khi bấm Xác nhận
 * @param {object}   options   - { title, confirmText, cancelText, type }
 */
App.confirm = function(msg, onConfirm, options = {}) { ... };

/**
 * Alert dialog (chỉ có nút OK)
 * Thay thế: 60× alert()
 * 
 * @param {string} msg   - Nội dung cảnh báo
 * @param {string} type  - 'error' | 'warning' | 'info' | 'success'
 * @param {string} title - Tiêu đề (optional)
 */
App.alert = function(msg, type = 'info', title = '') { ... };

/**
 * Loading overlay (chờ AJAX)
 * Thay thế: Swal.showLoading() rải rác
 */
App.loading = function(msg = 'Đang xử lý...') { ... };
App.closeLoading = function() { ... };

window.App = App;
```

### 2.2 Implementation Notes

- Tất cả dùng `Swal.fire()` bên trong
- Fallback `confirm()` / `alert()` nếu `typeof Swal === 'undefined'`
- `App.toast()` dùng Swal toast position `top-end` (giống `showNotification()` hiện tại)
- `App.confirm()` return Promise cho async/await pattern
- Giữ `_modals.php` cho **form-based modals** (delete confirm with data display, import modal...) — chỉ replace **simple confirm/alert**

---

## 3. Chiến lược triển khai (Progressive, không breaking)

### Phase 1 — Tạo file + Load global ⏳
- [ ] Tạo `public/js/core/app-alerts.js`
- [ ] Thêm `<script src="js/core/app-alerts.js">` vào `footer.php` (sau SweetAlert2)
- [ ] **0 breaking change** — file mới, chưa sửa gì cũ

### Phase 2 — Module mới dùng `App.*` ⏳
- [ ] Mọi module mới hoặc đang refactor → dùng `App.toast()`, `App.confirm()`, `App.alert()`
- [ ] Cập nhật coding instructions (`.github/copilot-instructions.md`)

### Phase 3 — Migrate từng module (khi upgrade) ⏳

Thứ tự ưu tiên (theo số lượng trùng lặp):

| Priority | Module | Files cần sửa | Effort |
|----------|--------|---------------|--------|
| 1 | **HR** | 12 files `showAlert()` + hr-common.js | Medium |
| 2 | **Production** | 5 files `showAlert()` + ~35 `alert()` | High |
| 3 | **Finance** | 5 files `showAlert()` + ~12 `confirm()` | Medium |
| 4 | **Master Data** | 4 files `showAlert()` | Low |
| 5 | **Inventory** | Migrate `INV.toast/confirm` → `App.*` | Medium |
| 6 | **Assets** | 2 files | Low |
| 7 | **PM** | 2 files | Low |

Mỗi module migrate:
1. Replace `showAlert(msg, type)` → `App.toast(msg, type)`
2. Replace `confirm('...')` → `App.confirm('...', callback)`
3. Replace `alert('...')` → `App.alert('...')`
4. Xóa local `showAlert()` function definition
5. Test lại toàn bộ AJAX interactions

### Phase 4 — Cleanup ⏳
- [ ] Xóa `showNotification()` khỏi `footer.php` (replaced by `App.toast`)
- [ ] Xóa `INV.toast/alert/confirm` khỏi `inventory-common.js` (nếu đã migrate hết)
- [ ] Xóa `showAlert/showConfirm/showSuccess/showError` khỏi `hr-common.js`

---

## 4. Quy tắc sau khi hoàn thành

```
✅ App.toast(msg, type)      — Mọi thông báo nhẹ (AJAX success/error)
✅ App.confirm(msg, callback) — Mọi xác nhận hành động
✅ App.alert(msg, type)       — Mọi cảnh báo bắt buộc đọc
✅ App.loading() / closeLoading() — Mọi chờ AJAX

❌ KHÔNG dùng alert(), confirm() — Native browser
❌ KHÔNG define showAlert() local — Trùng lặp
❌ KHÔNG gọi Swal.fire() trực tiếp — Qua App.* wrapper
```

### Ngoại lệ giữ nguyên
- `flash()` server-side → giữ nguyên (render Swal toast từ PHP)
- `_modals.php` Bootstrap modals → giữ nguyên (form-based confirm với data display)

---

## 5. Không làm

- ❌ Không replace toàn bộ 110+ chỗ cùng lúc (rủi ro cao)
- ❌ Không xóa `_modals.php` (cần cho form-based modals)
- ❌ Không thêm thư viện mới (SweetAlert2 đã đủ)
- ❌ Không tạo module-specific wrapper (1 file `app-alerts.js` duy nhất)
