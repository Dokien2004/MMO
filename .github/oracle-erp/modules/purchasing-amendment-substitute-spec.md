# Purchasing Amendment + Item Substitution — Oracle EBS Spec

> **Phạm vi**: Nâng cấp Purchasing module để xử lý 3 tình huống chuẩn Oracle EBS R12
> mà ERP hiện chưa hỗ trợ:
> 1. Sửa PO sau khi đã có phiếu nhập kho (Partial Amendment)
> 2. PR yêu cầu mã A — thị trường thiếu — PO mua mã B (Item Substitution)
> 3. Một phần đúng mã PR, phần còn lại mua mã thay thế (Mixed Fulfillment)
>
> **Trạng thái**: Phase 1 — DB schema + helper layer (session 2026-05-11).
> Phase 2 (UI form, notification flow) sẽ làm sau khi nghiệm thu Phase 1.

## 1. Oracle EBS Reference

| Feature | Oracle Object | ERP tương đương (sau upgrade) |
|---------|--------------|-------------------------------|
| Change Order trên PO đã GRN | `PO_CHANGE_REQUESTS` + `PO_LINE_LOCATIONS_ALL.quantity` (with `quantity_received` guard) | `purchase_orders.is_partial_amendment` + `PurchaseOrder::validateAmendmentLines()` |
| Item Substitution | `PO_LINES_ALL.item_id` ≠ `PO_REQ_DISTRIBUTIONS.item_id` + `MTL_RELATED_ITEMS` | `purchase_order_details.substitute_for_pr_detail_id` + `substitute_reason` + `substitute_approved_by` |
| Cancel Remaining | `PO_LINE_LOCATIONS_ALL.cancel_flag` + `quantity_cancelled` | `purchase_order_details.quantity_cancelled` + `purchase_order_shipments.quantity_cancelled` |
| Substitute fulfillment tracking trên PR | `PO_REQUISITION_LINES_ALL.quantity_delivered` (split by item) | `purchase_request_details.quantity_substituted` |

## 2. Schema Changes (Migration `2026_05_11_pr_po_amendment_substitute_upgrade.sql`)

### `purchase_request_details` (PR line)

| Column | Type | Mục đích |
|--------|------|----------|
| `quantity_ordered` *(cũ)* | DECIMAL(15,4) | Đã đặt PO bằng **đúng mã PR** |
| `quantity_substituted` *(mới)* | DECIMAL(15,4) | Đã đặt PO bằng **mã thay thế** |
| `quantity_cancelled` *(mới)* | DECIMAL(15,4) | Buyer hủy phần dư (Cancel Remaining) |

**Status rule**: `fully_ordered` ⇔ `(ordered + substituted + cancelled) >= quantity_approved`

### `purchase_orders` (PO header)

| Column | Mục đích |
|--------|----------|
| `is_partial_amendment` *(mới)* | Cờ TINYINT — đặt = 1 khi `initiate()` chạy trên PO có `cached_total_received > 0`. UI dùng cờ này để khóa các dòng đã nhận. |

### `purchase_order_details` (PO line)

| Column | Mục đích |
|--------|----------|
| `pr_detail_id` *(cũ)* | Vẫn link tới PR line gốc. Substitute = link bình thường nhưng `product_id` khác. |
| `substitute_for_pr_detail_id` *(mới)* | Phụ trợ — query nhanh các substitute lines (tránh JOIN-compare). |
| `substitute_reason` *(mới)* | Bắt buộc khi `pod.product_id ≠ prd.product_id`. |
| `substitute_approved_by` / `_at` | Workflow: PR requester acknowledge substitute trước khi PO submit. |
| `quantity_cancelled` *(mới)* | Buyer hủy phần chưa nhận. `qty_remaining = quantity − received − cancelled`. |

### `purchase_order_shipments`

| Column | Mục đích |
|--------|----------|
| `quantity_cancelled` *(mới)* | Hủy theo từng đợt giao. Khi user xóa shipment đã có 1 phần GRN, không xóa cứng — chuyển phần chưa nhận vào `quantity_cancelled`. |

### `pr_allocation_details`

| Column | Mục đích |
|--------|----------|
| `substitute_product_id` *(mới)* | Allocation Workbench: phân bổ 1 PR line cho NCC2 với mã thay thế. |
| `substitute_reason` *(mới)* | Lý do dùng mã khác trên allocation. |

## 3. Code Changes

### 3.1 `app/models/purchasing/PurchaseOrder.php`

**A. Mở rộng amendable status**:
```php
public static function getAmendableStatuses(): array {
    // [UPGRADE 2026-05] Cho phép amendment cả khi PO đã nhận một phần
    return [self::STATUS_APPROVED, self::STATUS_PARTIAL_RECEIVED];
}
```

**B. Method mới `validateAmendmentLines()`**:
```php
/**
 * [PARTIAL AMENDMENT] Kiểm tra rule per-line khi sửa PO đã có GRN
 *   - quantity (mới) >= quantity_received  (không cho hạ thấp dưới đã nhận)
 *   - product_id của dòng đã có GRN không được đổi (tạo dòng mới + cancel cũ)
 *   - shipment.quantity_ordered (mới) >= shipment.quantity_received
 * @return array<string,string> Errors map [line_uid => message]; empty = pass.
 */
public function validateAmendmentLines(int $poId, array $newLines): array
```

### 3.2 `app/services/purchasing/PurchaseOrderAmendmentService.php`

**Bỏ hard-block** `cached_total_received > 0`, thay bằng:
- Vẫn cho phép `initiate()`
- Set `purchase_orders.is_partial_amendment = 1`
- Trả về `['warning' => 'PO này đã có GRN. Bạn chỉ được sửa các dòng/đợt giao chưa nhận hết.']`

### 3.3 `app/helpers/purchasing/QuantityUpdater.php`

**A. `_recalculateOrderedQty()`** — tách 2 cột:
```sql
SELECT
  COALESCE(SUM(CASE WHEN pod.product_id = prd.product_id
                    THEN pod.quantity ELSE 0 END), 0) AS qty_original,
  COALESCE(SUM(CASE WHEN pod.product_id <> prd.product_id
                    THEN pod.quantity ELSE 0 END), 0) AS qty_substitute
FROM purchase_order_details pod
JOIN purchase_orders po          ON pod.po_id = po.id
JOIN purchase_request_details prd ON pod.pr_detail_id = prd.id
WHERE pod.pr_detail_id = :pr_detail_id
  AND po.deleted_at IS NULL
  AND po.status NOT IN ('cancelled','rejected')
```
Sau đó `UPDATE purchase_request_details SET quantity_ordered = :orig, quantity_substituted = :subst`.

**B. `_updatePrStatus()`** — fully_ordered:
```php
$totalFulfilled = $detail->quantity_ordered + $detail->quantity_substituted + $detail->quantity_cancelled;
$isFullyOrdered = $totalFulfilled >= max(1, (float)$detail->quantity_approved);
```

**C. `_refreshCachedAggregates()`** — thêm `cached_sum_substituted` (nếu PR header có cột; nếu không thì bỏ qua, chỉ giữ ordered cũ).

## 4. Tình huống nghiệp vụ — End-to-End

### Scenario A: Sửa PO sau khi đã nhập 30/100

**Trước upgrade**: PO đã GRN 30 → click "Sửa đổi" → ❌ "Không thể sửa đổi đơn hàng đã có phiếu nhập kho."

**Sau upgrade**:
1. Buyer click "Sửa đổi" → `initiate()` ✅ (set `is_partial_amendment = 1`)
2. Form edit hiển thị: dòng có `received=30`, `quantity=100`, có thể hạ về **70** (≥ 30) hoặc tăng lên 150
3. Validate: `70 >= 30` → ✅; `25 >= 30` → ❌ "Không thể giảm dưới SL đã nhận (30)"
4. Submit → workflow approve → revision_num++ → archive snapshot

### Scenario B: PR mã `BOLT-M8` thiếu hàng → mua `BOLT-M8-SS` (inox thay thế)

1. PR có 1 line: `BOLT-M8` qty 1000, status `approved`
2. Buyer mở Allocation: chọn NCC, đổi `substitute_product_id = BOLT-M8-SS`, ghi `substitute_reason = "Mã M8 hết hàng tới T6/2026, thay bằng inox cùng spec"`
3. Convert PR→PO: PO line có `product_id = BOLT-M8-SS`, `pr_detail_id = <PR-line-id>`, `substitute_for_pr_detail_id = <PR-line-id>`, `substitute_reason = ...`
4. Workflow gửi notification cho PR requester acknowledge → ghi `substitute_approved_by`
5. PR requester accept → PO submit → approval flow bình thường
6. Sau approve, `QuantityUpdater::_recalculateOrderedQty()` chạy:
   - `quantity_ordered = 0` (không có PO line nào cùng mã)
   - `quantity_substituted = 1000`
7. PR status → `fully_ordered` (vì ordered + substituted + cancelled >= approved)

### Scenario C: PR 1000, mua 600 đúng mã + 400 thay thế

1. PR line `BOLT-M8` qty 1000, approved
2. Buyer tạo PO #1: `BOLT-M8` qty 600 (`pr_detail_id=X`, `substitute_for_pr_detail_id=NULL`)
3. Buyer tạo PO #2: `BOLT-M8-SS` qty 400 (`pr_detail_id=X`, `substitute_for_pr_detail_id=X`, `substitute_reason="..."`)
4. `_recalculateOrderedQty()` cho PR line X:
   - `qty_original = 600`, `qty_substitute = 400`
   - PR `quantity_ordered = 600`, `quantity_substituted = 400`
5. Total fulfilled = 600 + 400 + 0 = 1000 ≥ 1000 → `fully_ordered`

### Scenario D: Buyer hủy phần dư (Cancel Remaining)

1. PR `quantity_approved = 1000`, đã `quantity_ordered = 700`. Còn 300 chưa mua.
2. Thị trường tăng giá đột biến → Buyer quyết định không mua tiếp.
3. UI nút "Đóng phần dư" → set `purchase_request_details.quantity_cancelled = 300`
4. Status → `fully_ordered` (700 + 0 + 300 = 1000)

## 5. Rollout Plan

| Step | Action | Owner | Verify |
|------|--------|-------|--------|
| 1 | Backup live DB | DBA | `mysqldump erpmesco_erp_test > backup_2026-05-11.sql` |
| 2 | Chạy migration `2026_05_11_pr_po_amendment_substitute_upgrade.sql` | DBA | Query verify ở cuối file SQL — phải trả 11 cột |
| 3 | Deploy code Phase 1 (model + service + helper) | DevOps | `php -l` các file đã sửa, no errors |
| 4 | Smoke test: tạo PR → PO → GRN một phần → sửa PO | Tester | Form edit hiển thị warning, validate qty đúng rule |
| 5 | Smoke test: Allocation chọn substitute → PO sub → PR fulfilled | Tester | PR status chuyển fully_ordered, `quantity_substituted > 0` |
| 6 | Phase 2 (UI substitute picker, acknowledge workflow) | Dev | Tách session sau |

## 6. Rollback

Nếu cần rollback Phase 1:

```sql
-- Khôi phục code cũ (revert git) trước, sau đó:
ALTER TABLE purchase_request_details
  DROP COLUMN quantity_substituted,
  DROP COLUMN quantity_cancelled,
  DROP INDEX idx_prd_fulfillment;

ALTER TABLE purchase_orders DROP COLUMN is_partial_amendment;

ALTER TABLE purchase_order_details
  DROP FOREIGN KEY fk_pod_substitute_pr,
  DROP FOREIGN KEY fk_pod_substitute_user,
  DROP INDEX idx_pod_substitute,
  DROP COLUMN substitute_for_pr_detail_id,
  DROP COLUMN substitute_reason,
  DROP COLUMN substitute_approved_by,
  DROP COLUMN substitute_approved_at,
  DROP COLUMN quantity_cancelled;

ALTER TABLE purchase_order_shipments DROP COLUMN quantity_cancelled;

ALTER TABLE pr_allocation_details
  DROP FOREIGN KEY fk_prad_substitute_product,
  DROP COLUMN substitute_product_id,
  DROP COLUMN substitute_reason;
```

⚠️ Rollback sẽ MẤT data trên các cột mới — phải export trước nếu đã sử dụng.

## 7. Test Checklist

- [ ] Migration chạy 1 lần thành công, query verify trả 11 cột
- [ ] PHP syntax OK trên 3 file: PurchaseOrder.php, PurchaseOrderAmendmentService.php, QuantityUpdater.php
- [ ] PO `approved` không GRN → click "Sửa đổi" → vẫn vào amending bình thường (regression)
- [ ] PO `partial_received` → click "Sửa đổi" → vào amending với `is_partial_amendment=1`
- [ ] Sửa qty < received → server reject với message rõ ràng
- [ ] Tạo PO substitute (product khác PR) → `pod.is_substitute` không cần (đã derive được từ product_id mismatch)
- [ ] Sau khi PO substitute approved → PR `quantity_substituted` tăng đúng SL
- [ ] PR fulfilled khi `(ordered + substituted + cancelled) >= approved`
- [ ] Cancel Remaining 300/1000 → status = fully_ordered ngay
- [ ] Audit log ghi nhận `AMEND_INITIATED` với `is_partial = 1`

## 8. Files Modified

| File | Loại |
|------|------|
| `app/migrations/2026_05_11_pr_po_amendment_substitute_upgrade.sql` | NEW |
| `.github/oracle-erp/modules/purchasing-amendment-substitute-spec.md` | NEW (tài liệu này) |
| `app/models/purchasing/PurchaseOrder.php` | EDIT — `getAmendableStatuses()`, `validateAmendmentLines()` |
| `app/services/purchasing/PurchaseOrderAmendmentService.php` | EDIT — relax block + set partial flag |
| `app/helpers/purchasing/QuantityUpdater.php` | EDIT — split ordered/substituted + status rule mới |

## 9. Out of Scope (Phase 2)

- UI form edit PO trong amending mode (lock dòng đã GRN)
- Substitute picker trong Allocation Workbench
- Notification + acknowledge workflow cho PR requester
- Report "Substitution Variance" (so sánh giá mã gốc vs mã thay thế)
- Cron auto-close PR có `(ordered + substituted) >= approved * threshold` quá X ngày
