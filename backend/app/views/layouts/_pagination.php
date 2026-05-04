<?php
/**
 * PAGINATION COMPONENT CHUẨN - Dùng cho TẤT CẢ trang index
 * 
 * Cách dùng trong view:
 *   <?php 
 *     $pagination = $data['pagination'] ?? null;
 *     $entityLabel = 'sản phẩm'; // Tên entity (VD: đơn hàng, nhân viên...)
 *     require APPROOT . '/views/layouts/_pagination.php'; 
 *   ?>
 * 
 * Yêu cầu: $pagination phải có cấu trúc chuẩn từ BaseModel::paginate():
 *   [
 *     'current_page'  => int,
 *     'per_page'      => int,
 *     'total_records' => int,
 *     'total_pages'   => int,
 *     'from'          => int,
 *     'to'            => int,
 *     'has_prev'      => bool,
 *     'has_next'      => bool,
 *   ]
 */

if (empty($pagination) || !is_array($pagination)) return;

$currentPage  = (int)($pagination['current_page'] ?? 1);
$perPage      = (int)($pagination['per_page'] ?? DEFAULT_PAGE_SIZE);
$totalRecords = (int)($pagination['total_records'] ?? 0);
$totalPages   = (int)($pagination['total_pages'] ?? 1);
$from         = (int)($pagination['from'] ?? 0);
$to           = (int)($pagination['to'] ?? 0);
$hasPrev      = $pagination['has_prev'] ?? ($currentPage > 1);
$hasNext      = $pagination['has_next'] ?? ($currentPage < $totalPages);

// Label entity (VD: "sản phẩm", "đơn hàng") - nếu chưa set thì mặc định
if (!isset($entityLabel)) $entityLabel = 'bản ghi';

// Các option per_page cho dropdown
$pageSizeOptions = defined('PAGE_SIZE_OPTIONS') ? PAGE_SIZE_OPTIONS : [10, 25, 50, 100, 200];

// Build URL giữ nguyên tất cả query params hiện tại
$urlParams = $_GET;
unset($urlParams['page']); // Sẽ được thêm lại
?>

<div class="card-footer bg-white border-top py-2">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 px-2">
        
        <!-- Bên trái: Thông tin record + Per-page selector -->
        <div class="d-flex align-items-center gap-3">
            <small class="text-muted">
                <?php if ($totalRecords > 0): ?>
                    Hiển thị <strong><?= number_format($from) ?></strong>-<strong><?= number_format($to) ?></strong> 
                    / <strong><?= number_format($totalRecords) ?></strong> <?= e($entityLabel) ?>
                <?php else: ?>
                    Không có <?= e($entityLabel) ?> nào
                <?php endif; ?>
            </small>
            
            <!-- Dropdown chọn số dòng -->
            <div class="d-flex align-items-center gap-1">
                <select class="form-select form-select-sm" 
                        id="pgPerPage" 
                        style="width: auto; min-width: 70px;"
                        onchange="window.location.href=this.value">
                    <?php foreach ($pageSizeOptions as $size): 
                        $sizeParams = array_merge($urlParams, ['per_page' => $size, 'page' => 1]);
                    ?>
                        <option value="?<?= e(http_build_query($sizeParams)) ?>" 
                                <?= ($perPage == $size) ? 'selected' : '' ?>>
                            <?= $size ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted d-none d-sm-inline">dòng/trang</small>
            </div>
        </div>

        <!-- Bên phải: Nút phân trang -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Phân trang">
            <ul class="pagination pagination-sm mb-0">
                <!-- Trang đầu -->
                <li class="page-item <?= !$hasPrev ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?php $urlParams['page'] = 1; echo e(http_build_query($urlParams)); ?>" title="Trang đầu">
                        <i class="fa fa-angle-double-left"></i>
                    </a>
                </li>
                <!-- Trước -->
                <li class="page-item <?= !$hasPrev ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?php $urlParams['page'] = max(1, $currentPage - 1); echo e(http_build_query($urlParams)); ?>">
                        Trước
                    </a>
                </li>

                <?php
                // Hiển thị tối đa 5 trang xung quanh trang hiện tại (sliding window)
                $range = 2;
                $start = max(1, $currentPage - $range);
                $end   = min($totalPages, $currentPage + $range);

                // Trang 1 + dấu ... nếu start > 2
                if ($start > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php $urlParams['page'] = 1; echo e(http_build_query($urlParams)); ?>">1</a>
                    </li>
                <?php endif;
                if ($start > 2): ?>
                    <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                <?php endif;

                // Các trang trong window
                for ($i = $start; $i <= $end; $i++): ?>
                    <li class="page-item <?= ($i == $currentPage) ? 'active' : '' ?>">
                        <a class="page-link" href="?<?php $urlParams['page'] = $i; echo e(http_build_query($urlParams)); ?>"><?= $i ?></a>
                    </li>
                <?php endfor;

                // Dấu ... + trang cuối nếu end < totalPages - 1
                if ($end < $totalPages - 1): ?>
                    <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                <?php endif;
                if ($end < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php $urlParams['page'] = $totalPages; echo e(http_build_query($urlParams)); ?>"><?= $totalPages ?></a>
                    </li>
                <?php endif; ?>

                <!-- Sau -->
                <li class="page-item <?= !$hasNext ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?php $urlParams['page'] = min($totalPages, $currentPage + 1); echo e(http_build_query($urlParams)); ?>">
                        Sau
                    </a>
                </li>
                <!-- Trang cuối -->
                <li class="page-item <?= !$hasNext ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?php $urlParams['page'] = $totalPages; echo e(http_build_query($urlParams)); ?>" title="Trang cuối">
                        <i class="fa fa-angle-double-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>

    </div>
</div>
