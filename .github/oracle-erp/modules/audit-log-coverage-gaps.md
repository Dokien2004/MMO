# Audit Log Coverage — Gap Analysis & Fix Log
**Ngày thực hiện:** 20/04/2026  
**Phiên:** Session 4 (Security + Audit completeness)  
**Audit scope:** Tất cả workflow events (approve/reject/cancel/reverse/close) trong toàn hệ thống

---

## 1. Cơ chế ghi log trong hệ thống

| Cơ chế | Mô tả |
|--------|-------|
| **BaseModel auto-log** | `protected $useAuditLog = true` → ghi `sys_audit_logs` tự động khi `create()` / `update()` / `delete()` qua ORM. Chỉ ghi _field changes_, không ghi event type rõ ràng. |
| **Controller `writeAuditLog()`** | `$this->writeAuditLog($eventType, $table, $recordId, $oldValues, $newValues)` — ghi workflow events có tên rõ ràng (APPROVE, CANCEL, REVERSE...) vào `sys_audit_logs.event_type`. |
| **Service INSERT trực tiếp** | Purchasing, Finance, Sales, PM services ghi thẳng `INSERT INTO sys_audit_logs` bên trong service method — dùng khi service không kế thừa Controller. |

**Lưu ý quan trọng:** Khi model dùng raw SQL `UPDATE` bypass ORM (ví dụ: `$this->db->query("UPDATE ...")`), `useAuditLog = true` KHÔNG có hiệu lực — BaseModel chỉ log khi gọi qua `$model->update()` / `$model->create()` / `$model->delete()`.

---

## 2. Coverage trước khi fix

### ✅ Đã có audit log đầy đủ

| Module | Events được log | Cơ chế |
|--------|----------------|--------|
| Purchasing | PO/PR create, approve, reject, cancel, recall, convert | Service `INSERT INTO sys_audit_logs` |
| Finance – AP | AP Invoice: approve, reject, void | `ApInvoiceWorkflowService::writeAuditLog()` |
| Finance – AR | AR Invoice: submit, approve, reject, recall, void | `ArInvoiceWorkflowService::writeAuditLog()` |
| Sales | SO/SQ: submit, approve, cancel | `SalesOrderService` + `SalesQuoteService` |
| Inventory | IR/IT/MI/WI/SA: approve, cancel, reverse + Pick: finish, reopen, cancel | Controller `writeAuditLog()` — 9 controllers |
| Asset | Handover, dispose, delete, revalue, upgrade, config | `ManagerController.writeAuditLog()` |
| PM | Project activity log | `ProjectActivityService` → `sys_audit_logs` |
| Master data CRUD | Products, Partners, Warehouses, UOM... | BaseModel `useAuditLog=true` |

### ❌ Gaps xác định (trước fix)

| # | Module | Event thiếu | Vị trí | Mức độ |
|---|--------|-------------|--------|--------|
| G1 | Finance – GL | Journal Entry: **POST** (draft→posted) | `JournalEntryController::post()` → `JournalEntryModel::postEntry()` raw SQL | 🔴 Critical | ✅ Fixed |
| G2 | Finance – GL | GL Period: **CLOSE / REOPEN** | `GlperiodController` → `GlPeriodModel::closePeriod()` / `reopenPeriod()` raw SQL | 🔴 Critical | ✅ Fixed |
| G3 | HR | Leave Request: **APPROVE / REJECT / RECALL / CANCEL** | `LeaveRequestService::approveRequest()`, `LeaveRequestWorkflowService` | 🟠 High | ✅ Fixed |
| G4 | HR | Overtime Request: **APPROVE / REJECT** | `OvertimeRequestService::approveRequest()` / `rejectRequest()` | 🟠 High | ✅ Fixed |
| G5 | HR | Attendance shift change: **APPROVE / REJECT** | `AttendanceController::approve_shift_change()` / `reject_shift_change()` | 🟡 Medium | ✅ Fixed |
| G6 | Production | Work Order: **RELEASE / UNRELEASE / CANCEL** | `WorkOrderController` → `WorkOrderModel` raw SQL bypass ORM | 🟡 Medium | ✅ Fixed |
| G7 | Quality | QA Spec: **SUBMIT / APPROVE / REJECT** | `QaSpecificationController` — không có writeAuditLog | 🟡 Medium | ✅ Fixed |
| G8 | Quality | QA Inspection: **REJECT** | `QaInspectionController::reject()` — không có writeAuditLog | 🟢 Low | ✅ Fixed |

---

## 3. Phương án fix

### Chiến lược chọn

Ưu tiên thêm `$this->writeAuditLog()` tại **controller layer** (không sửa sâu vào service/model) để:
- Không phá vỡ business logic hiện có
- Đảm bảo log được ghi ngay cả khi service dùng raw SQL bypass BaseModel
- Dễ rollback nếu cần

**Ngoại lệ:** HR Leave/Overtime — workflow delegated hoàn toàn vào service. Thêm log vào controller wrapper sau khi service trả `success: true`.

---

## 4. Fix Log

### G1 — Finance: JE Post ✅ ĐÃ FIX

**File:** `app/controllers/finance/JournalEntryController.php`  
**Method:** `post($id)`  
**Thay đổi:** Thêm `$this->writeAuditLog('POST_GL', 'journal_entries', $id, ['status' => 'draft'], ['status' => 'posted'])` sau khi `postEntry()` trả `true`.

```php
if ($result === true) {
    $this->writeAuditLog('POST_GL', 'journal_entries', $id,
        ['status' => 'draft'],
        ['status' => 'posted']
    );
    flash('journal_msg', 'Đã ghi sổ (Post) thành công. Bút toán đã bị khóa.');
}
```

---

### G2 — Finance: GL Period Close / Reopen ✅ ĐÃ FIX

**File:** `app/controllers/finance/GlperiodController.php`  
**Methods:** `close_period($id)`, `reopen_period($id)`  
**Thay đổi:** Thêm `writeAuditLog` sau khi API trả `success: true`.

```php
// close_period
if ($result['success']) {
    $this->writeAuditLog('CLOSE_PERIOD', 'gl_periods', $id,
        ['status' => 'OPEN'],
        ['status' => $permanently ? 'PERMANENTLY_CLOSED' : 'CLOSED', 'module' => $module]
    );
}

// reopen_period
if ($result['success']) {
    $this->writeAuditLog('REOPEN_PERIOD', 'gl_periods', $id,
        ['status' => 'CLOSED'],
        ['status' => 'OPEN', 'module' => $module]
    );
}
```

---

### G3 — HR: Leave Request Workflow ✅ ĐÃ FIX

**File:** `app/controllers/hr/LeaveRequestController.php`  
**Methods:** `approve($id)`, `reject($id)`, `recall($id)`, `cancel($id)`  
**Thay đổi:** Thêm `writeAuditLog` trong controller sau khi service/workflow trả success.

| Event | old_values | new_values |
|-------|-----------|-----------|
| APPROVE | `{status: PENDING}` | `{status: APPROVED}` |
| REJECT | `{status: PENDING}` | `{status: REJECTED, reason: $comment}` |
| RECALL | `{status: PENDING}` | `{status: DRAFT}` |
| CANCEL | `{status: DRAFT/PENDING}` | `{status: CANCELLED, reason: $reason}` |

---

### G4 — HR: Overtime Request Workflow ✅ ĐÃ FIX

**File:** `app/controllers/hr/OvertimeRequestController.php`  
**Methods:** `approve($id)`, `reject($id)`  
**Thay đổi:** Thêm `writeAuditLog` sau khi service trả `success: true`.

---

### G5 — HR: Attendance Shift Change ✅ ĐÃ FIX

**File:** `app/controllers/hr/AttendanceController.php`  
**Methods:** `approve_shift_change($adjustmentId)`, `reject_shift_change($adjustmentId)`  
**Thay đổi:** Thêm `writeAuditLog` sau khi model method thành công.

---

### G6 — Production: Work Order Status Events ✅ ĐÃ FIX

**File:** `app/controllers/production/WorkOrderController.php`  
**Methods:** `release($id)`, `unrelease($id)`, `cancel($id)`  
**Thay đổi:** Thêm `writeAuditLog` sau khi service/model báo thành công.

| Event | Table | old_status | new_status |
|-------|-------|-----------|-----------|
| WO_RELEASE | work_orders | planned | released |
| WO_UNRELEASE | work_orders | released | planned |
| WO_CANCEL | work_orders | planned/released | cancelled |

---

### G7 — Quality: QA Specification Workflow ✅ ĐÃ FIX

**File:** `app/controllers/quality/QaSpecificationController.php`  
**Methods:** `submit($headerId)`, `approve($headerId)`, `reject($headerId)`  
**Thay đổi:** Thêm `writeAuditLog` sau khi thao tác thành công.

---

### G8 — Quality: QA Inspection Reject ✅ ĐÃ FIX

**File:** `app/controllers/quality/QaInspectionController.php`  
**Method:** `reject($id)`  
**Thay đổi:** Thêm `writeAuditLog('REJECT', 'qa_inspections', $id, ...)`.

---

## 5. Session 4 — Finance Raw SQL Gaps (phát hiện khi audit toàn project)

**Ngày phát hiện:** 20/04/2026 (cùng session)  
**Câu hỏi:** "Tất cả các hành động sửa trên toàn bộ project đã ghi log chưa trước và sau thay đổi?"  
**Phương pháp:** Grep raw SQL `UPDATE/DELETE` trong controllers + services, kiểm tra từng luồng có `writeAuditLog` hay không.

| # | Module | Event thiếu | Vị trí | Nguyên nhân | Mức độ | Trạng thái |
|---|--------|-------------|--------|-------------|--------|-----------|
| G9 | Finance – AP | AP Invoice: **POST_GL** (approved→posted) | `ApInvoiceController::post_gl()` | Raw SQL UPDATE, không qua ORM | 🔴 Critical | ✅ Fixed |
| G10 | Finance – AP | AP Payment: **VOID** (posted→voided) | `ApPaymentController::void()` → `ApPaymentModel::voidPayment()` raw SQL | Model dùng raw SQL UPDATE | 🔴 Critical | ✅ Fixed |
| G11 | Finance – AR | AR Receipt: **POST_GL** (draft→posted) | `ArReceiptController::post_gl()` → `ArReceiptModel::postReceipt()` raw SQL | Model dùng raw SQL UPDATE | 🔴 Critical | ✅ Fixed |
| G12 | Finance – AR | AR Receipt: **VOID** (posted→voided) | `ArReceiptController::void()` → `ArReceiptModel::voidReceipt()` raw SQL | Model dùng raw SQL UPDATE | 🔴 Critical | ✅ Fixed |

### Tại sao `useAuditLog = true` không đủ?

`ApInvoiceModel`, `ApPaymentModel`, `ArReceiptModel` đều có `useAuditLog = true`, nhưng các method `postEntry()`, `voidPayment()`, `postReceipt()`, `voidReceipt()` dùng **raw SQL** `$this->db->query("UPDATE ...")` thay vì ORM `$this->update($id, $data)`. BaseModel chỉ intercept qua `update()`, không intercept raw SQL.

### Fix G9 — AP Invoice POST_GL ✅

```php
// app/controllers/finance/ApInvoiceController.php::post_gl()
$this->db->commit();
$this->writeAuditLog('AP_POST_GL', 'ap_invoices', $id,
    ['status' => 'approved'],
    ['status' => 'posted', 'journal_entry_id' => $jeId]
);
```

### Fix G10 — AP Payment VOID ✅

```php
// app/controllers/finance/ApPaymentController.php::void()
$this->payModel->voidPayment($id, $this->getCurrentUserId(), $reason);
$this->writeAuditLog('AP_VOID', 'ap_payments', $id,
    ['status' => 'posted'],
    ['status' => 'voided', 'void_reason' => $reason]
);
```

### Fix G11 — AR Receipt POST_GL ✅

```php
// app/controllers/finance/ArReceiptController.php::post_gl()
$this->receiptModel->postReceipt($id, $siteId, $this->getCurrentUserId());
$this->writeAuditLog('AR_POST_GL', 'ar_receipts', $id,
    ['status' => 'draft'],
    ['status' => 'posted']
);
```

### Fix G12 — AR Receipt VOID ✅

```php
// app/controllers/finance/ArReceiptController.php::void()
$this->receiptModel->voidReceipt($id, $this->getCurrentSiteId(), $this->getCurrentUserId(), $reason);
$this->writeAuditLog('AR_VOID', 'ar_receipts', $id,
    ['status' => 'posted'],
    ['status' => 'voided', 'void_reason' => $reason]
);
```

---

## 6. Coverage sau khi fix (tổng hợp)

| Module | Trước fix | Sau fix |
|--------|----------|---------|
| Finance (GL) | ❌ Không log JE post, GL period changes | ✅ Log POST_GL, CLOSE_PERIOD, REOPEN_PERIOD |
| Finance (AP Invoice) | ✅ APPROVE/REJECT/VOID | ✅ + **AP_POST_GL** (G9) |
| Finance (AP Payment) | ❌ Không log VOID | ✅ Log **AP_VOID** (G10) |
| Finance (AR Receipt) | ❌ Không log POST/VOID | ✅ Log **AR_POST_GL** + **AR_VOID** (G11, G12) |
| Finance (AR Invoice) | ✅ APPROVE/REJECT/VOID + `post()` qua ORM | ✅ Đã đủ |
| HR (Leave) | ❌ Không log workflow events | ✅ Log APPROVE/REJECT/RECALL/CANCEL |
| HR (Overtime) | ❌ Không log workflow events | ✅ Log APPROVE/REJECT |
| HR (Attendance) | ❌ Không log shift change decisions | ✅ Log APPROVE/REJECT shift change |
| Production (WO) | ❌ Không log lifecycle events | ✅ Log WO_RELEASE/UNRELEASE/CANCEL |
| Quality (Spec) | ❌ Không log workflow | ✅ Log SUBMIT/APPROVE/REJECT |
| Quality (Inspection) | ❌ Không log reject | ✅ Log REJECT |

**Tổng kết:** 12 gaps xác định → 0 gaps còn lại.

---

## 7. Known acceptable gaps (không phải gaps thực sự)

| Vị trí | Lý do không cần sys_audit_logs |
|--------|-------------------------------|
| `insertBatch()` trong BaseModel | Dùng cho bulk import; `ProductImportHistory` / `PartnerImportHistory` đã là audit trail riêng |
| `warehouse_stocks` updates trong inventory services | Tracked qua `inventory_transactions` ledger (chi tiết hơn sys_audit_logs) |
| `SalesATPService` reservation updates | Tracked qua `inventory_reservations` + SO quantity updates |
| `SessionManager` DELETE user_sessions | Non-business data — session volatile |
| `LookupSyncService` DELETE sys_lookups orphans | System config sync — không phải business data |
| `useAuditLog = false` models: PushSubscriptionModel, AttendanceBackupModel, FingerprintModel, TimeLogs... | Non-business: machine sync, PWA push tokens, read-only reports |

---

## 6. Mô hình `sys_audit_logs` reference

```sql
sys_audit_logs (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  site_id      INT NOT NULL,
  table_name   VARCHAR(100),
  record_id    INT,
  event_type   VARCHAR(50),   -- 'APPROVE', 'REJECT', 'POST_GL', 'WO_RELEASE'...
  old_values   JSON,
  new_values   JSON,
  user_id      INT,
  ip_address   VARCHAR(45),
  user_agent   VARCHAR(255),
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
)
```

`Controller::writeAuditLog()` tự động điền `site_id`, `user_id`, `ip_address`, `user_agent` từ session.
