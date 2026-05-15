<?php
$allProducts = ($products ?? []);
$totalProducts = count($allProducts);
$withAffiliate = count(array_filter($allProducts, static fn(array $p): bool => !empty($p['affiliate_url'] ?? '')));
$withPrice = count(array_filter($allProducts, static fn(array $p): bool => (float)($p['price'] ?? 0) > 0));

$sortedBySold = $allProducts;
usort($sortedBySold, static fn(array $a, array $b): int => (int)($b['sold_count'] ?? 0) - (int)($a['sold_count'] ?? 0));
$topProducts = array_slice($sortedBySold, 0, 5);

$radarEligible = array_filter($allProducts, static fn(array $p): bool =>
    (int)($p['sold_count'] ?? 0) >= 20 && empty($p['affiliate_url'] ?? '')
);
usort($radarEligible, static fn(array $a, array $b): int => (int)($b['sold_count'] ?? 0) - (int)($a['sold_count'] ?? 0));
$topRadarEligible = array_slice($radarEligible, 0, 5);
?>
<style>
@media (max-width: 640px) {
    .page-header { flex-direction: column; gap: 12px }
    .page-header .hero-actions { width: 100%; justify-content: flex-start; flex-wrap: wrap }
    .page-header .hero-actions .btn { flex: 1; min-width: 140px; text-align: center }
    .stats-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 10px }
    .grid-12 { grid-template-columns: 1fr !important }
    .two-col-section { flex-direction: column !important }
    .publish-mode-grid { flex-direction: column !important }
    .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch }
    .table-main th, .table-main td { white-space: nowrap; font-size: 12px; padding: 6px 8px }
    .products-table tbody tr { display: table-row; }
    .quick-actions { flex-direction: column !important }
    .quick-actions .btn { width: 100%; text-align: center; justify-content: center }
    .radar-card-grid { grid-template-columns: 1fr !important }
    .top-sold-card { margin-top: 16px }
}
@media (max-width: 400px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 8px }
    .stat-card .value { font-size: 22px }
    .metric-pill { font-size: 11px; padding: 2px 7px }
}
.two-col-section { display: flex; gap: 16px; margin-bottom: 20px; flex-wrap: wrap }
.two-col-section > * { flex: 1; min-width: 280px }
.quick-actions { display: flex; gap: 10px; flex-wrap: wrap; margin: 12px 0 }
.quick-actions .btn { flex: 1; min-width: 120px; text-align: center; justify-content: center; display: flex; align-items: center; gap: 6px; padding: 10px 14px; font-size: 13px }
.radar-card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; margin-bottom: 20px }
.select-row-expanded { background: #f0f4ff !important }
.select-form-row td { padding: 10px 12px !important; border-bottom: 2px solid var(--accent) }
</style>

<div id="productsPage">
    <div class="page-header">
        <div>
            <div class="page-kicker">🛒 Quản lý</div>
            <h2>Sản phẩm</h2>
            <p>Xem sản phẩm đã cào, tạo link affiliate và chuyển sang tạo content.</p>
        </div>
        <div class="hero-actions">
            <a class="btn btn-purple" href="<?= url('/scraper') ?>">⚡ Radar</a>
            <a class="btn btn-accent" href="<?= url('/scraper') ?>">+ Cào thêm</a>
        </div>
    </div>

    <div class="stats-grid" style="margin-bottom:20px">
        <div class="stat-card accent"><div class="label">Tổng cộng</div><div class="value"><?= number_format($totalProducts) ?></div></div>
        <div class="stat-card success"><div class="label">Có link aff</div><div class="value"><?= number_format($withAffiliate) ?></div></div>
        <div class="stat-card"><div class="label">Có giá</div><div class="value"><?= number_format($withPrice) ?></div></div>
        <div class="stat-card purple"><div class="label">Cần tạo link</div><div class="value"><?= count($topRadarEligible) ?></div></div>
    </div>

    <div class="quick-actions">
        <a class="btn btn-primary" href="<?= url('/scraper') ?>">🔍 Phân tích Radar</a>
        <a class="btn btn-accent" href="<?= url('/links') ?>">🔗 Tạo Link</a>
        <a class="btn btn-success" href="<?= url('/contents') ?>">✍️ Tạo Content</a>
    </div>



    <div class="publish-mode-grid" style="margin-bottom:20px">
        <div class="card publish-mode-card ready" style="padding:16px">
            <div class="section-heading" style="margin-bottom:12px">
                <div class="card-title" style="font-size:13px">➕ Nhập thủ công</div>
            </div>
            <form data-ajax method="POST" action="<?= url('/products/store') ?>">
                <div class="form-group" style="margin-bottom:10px">
                    <label class="form-label" style="font-size:12px">Tên sản phẩm *</label>
                    <input class="form-control" name="product_name" required placeholder="VD: Áo thun nam trơn" style="font-size:14px;padding:8px 10px">
                </div>
                <div class="form-group" style="margin-bottom:10px">
                    <label class="form-label" style="font-size:12px">Link gốc *</label>
                    <input class="form-control" name="product_url" required placeholder="https://shopee.vn/..." style="font-size:14px;padding:8px 10px">
                </div>
                <div class="form-group" style="margin-bottom:10px">
                    <label class="form-label" style="font-size:12px">Link Affiliate</label>
                    <input class="form-control" name="affiliate_url" placeholder="https://s.shopee.vn/..." style="font-size:14px;padding:8px 10px">
                </div>
                <div class="grid-3 compact-grid" style="margin-bottom:10px">
                    <div class="form-group" style="margin-bottom:8px">
                        <label class="form-label" style="font-size:12px">Giá</label>
                        <input class="form-control" name="price" type="number" min="0" step="1000" placeholder="99000" style="font-size:14px;padding:8px 10px">
                    </div>
                    <div class="form-group" style="margin-bottom:8px">
                        <label class="form-label" style="font-size:12px">Lượt bán</label>
                        <input class="form-control" name="sold_count" type="number" min="0" value="0" style="font-size:14px;padding:8px 10px">
                    </div>
                    <div class="form-group" style="margin-bottom:8px">
                        <label class="form-label" style="font-size:12px">Nguồn</label>
                        <select class="form-control" name="source_platform" style="font-size:14px;padding:8px 10px">
                            <option value="shopee">Shopee</option>
                            <option value="manual">Nhập tay</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:10px">
                    <label class="form-label" style="font-size:12px">Ghi chú</label>
                    <textarea class="form-control" name="notes" rows="2" placeholder="Thông tin thêm..." style="font-size:14px;padding:8px 10px"></textarea>
                </div>
                <button class="btn btn-accent" type="submit" style="width:100%;font-size:14px;padding:10px">Lưu sản phẩm</button>
            </form>
        </div>

        <div class="card publish-mode-card" style="padding:16px">
            <div class="section-heading" style="margin-bottom:12px">
                <div class="card-title" style="font-size:13px">📥 Import Excel / CSV</div>
            </div>
            <form data-ajax method="POST" action="<?= url('/products/import') ?>" enctype="multipart/form-data">
                <div class="form-group" style="margin-bottom:10px">
                    <label class="form-label" style="font-size:12px">File .xlsx / .csv</label>
                    <input class="form-control" type="file" name="product_file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required style="font-size:14px">
                </div>
                <div class="form-group" style="margin-bottom:10px">
                    <label class="form-label" style="font-size:12px">Nguồn</label>
                    <select class="form-control" name="platform" style="font-size:14px;padding:8px 10px">
                        <option value="shopee">Shopee</option>
                        <option value="manual">Nhập tay</option>
                    </select>
                </div>
                <button class="btn btn-success" type="submit" style="width:100%;font-size:14px;padding:10px">Import sản phẩm</button>
                <div class="sub" style="margin-top:8px">
                    <a href="<?= url('/templates/products-import-template.csv') ?>" download style="font-size:12px">📥 Tải file mẫu (CSV)</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="section-heading" style="margin-bottom:12px">
            <div>
                <div class="card-title">Toàn bộ sản phẩm (<?= number_format($totalProducts) ?>)</div>
            </div>
        </div>

        <?php if (empty($allProducts)): ?>
            <div class="empty-state">
                <p>Chưa có sản phẩm nào. Hãy vào <strong>Product Radar</strong> để cào sản phẩm.</p>
                <a class="btn btn-accent" href="<?= url('/scraper') ?>">Đi tới Radar</a>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table-main table-compact products-table">
                    <thead>
                        <tr>
                            <th>Sản phẩm</th>
                            <th style="width:70px">Nguồn</th>
                            <th style="width:90px">Giá</th>
                            <th style="width:80px">Đã bán</th>
                            <th style="width:80px">Link Aff</th>
                            <th style="width:90px">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($allProducts as $product): ?>
                        <tr id="product-row-<?= (int)$product['id'] ?>">
                            <td>
                                <strong class="item-title" style="font-size:13px"><?= e((string)$product['product_name']) ?></strong>
                                <div class="item-meta sub" style="font-size:11px">#<?= (int)$product['id'] ?></div>
                                <?php if (!empty($product['product_url'])): ?>
                                    <a href="<?= e((string)$product['product_url']) ?>" target="_blank" rel="noreferrer" class="text-xs" style="color:var(--accent);display:inline-block;margin-top:2px">Mở ↗</a>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-<?= e((string)$product['source_platform']) ?>" style="font-size:11px"><?= e((string)$product['source_platform']) ?></span></td>
                            <td class="text-sm"><?= (float)($product['price'] ?? 0) > 0 ? number_format((float)$product['price'], 0, ',', '.') . ' ₫' : '—' ?></td>
                            <td><span class="metric-pill <?= (int)($product['sold_count'] ?? 0) >= 1000 ? 'hot' : '' ?>" style="font-size:11px"><?= number_format((int)($product['sold_count'] ?? 0)) ?></span></td>
                            <td style="font-size:11px">
                                <?php if (!empty($product['affiliate_url'])): ?>
                                    <span style="color:#22c55e" title="<?= e($product['affiliate_url']) ?>">✓ Có</span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted)">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button
                                    type="button"
                                    class="btn <?= empty($product['affiliate_url']) ? 'btn-accent' : 'btn-ghost' ?> btn-sm"
                                    data-select-product-trigger
                                    data-product-id="<?= (int)$product['id'] ?>"
                                    data-product-name="<?= e((string)$product['product_name']) ?>"
                                    data-product-url="<?= e((string)($product['product_url'] ?? '')) ?>"
                                    data-affiliate-url="<?= e((string)($product['affiliate_url'] ?? '')) ?>"
                                    data-status="<?= e((string)($product['status'] ?? 'pending')) ?>"
                                    data-notes="<?= e((string)($product['notes'] ?? '')) ?>"
                                    style="font-size:11px;padding:4px 8px"
                                >
                                    <?php if (empty($product['affiliate_url'])): ?>
                                        + Thêm link
                                    <?php else: ?>
                                        <i class="fas fa-edit"></i> Sửa
                                    <?php endif; ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
            <div class="pagination-wrap" style="display:flex;justify-content:center;gap:6px;margin-top:16px;flex-wrap:wrap">
                <?php if ($pagination['page'] > 1): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= url('/products?page=' . ($pagination['page'] - 1)) ?>">← Trang trước</a>
                <?php endif; ?>
                <?php for ($p = max(1, $pagination['page'] - 2); $p <= min($pagination['totalPages'], $pagination['page'] + 2); $p++): ?>
                    <?php if ($p == $pagination['page']): ?>
                        <span class="btn btn-accent btn-sm"><?= $p ?></span>
                    <?php else: ?>
                        <a class="btn btn-ghost btn-sm" href="<?= url('/products?page=' . $p) ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($pagination['page'] < $pagination['totalPages']): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= url('/products?page=' . ($pagination['page'] + 1)) ?>">Trang sau →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="modal-overlay" id="selectModal" style="display:none;">
        <div class="modal-box" style="max-width:560px;">
            <h3>Thêm vào My Products</h3>
            <p class="sub" style="margin-bottom:16px;">Thêm link affiliate và cập nhật thông tin sản phẩm.</p>
            <form data-ajax method="POST" action="<?= url('/products/select') ?>">
                <input type="hidden" name="product_id" id="select_product_id">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label">Tên sản phẩm *</label>
                    <input class="form-control" name="product_name" id="edit_product_name" required>
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label">Link Affiliate ✨</label>
                    <input class="form-control" name="affiliate_url" id="edit_affiliate_url" placeholder="Dán link affiliate tại đây..." style="border-color:var(--accent);">
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label">Link gốc</label>
                    <input class="form-control" name="product_url" id="edit_product_url">
                </div>
                <div class="grid-3" style="margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label">Trạng thái</label>
                        <select class="form-control" name="status" id="edit_status">
                            <option value="pending">Chờ xử lý</option>
                            <option value="active">Đang hoạt động</option>
                            <option value="paused">Tạm dừng</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:16px;">
                    <label class="form-label">Ghi chú</label>
                    <textarea class="form-control" name="notes" id="edit_notes" rows="2"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn btn-accent" style="flex:1;">Lưu vào My Products</button>
                    <button type="button" class="btn btn-cancel" data-close-select-modal>Hủy</button>
                </div>
            </form>
        </div>
    </div>
</div>
