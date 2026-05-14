<?php
$defaultCampaign = (string)($automationSettings['default_campaign_code'] ?? 'MVP-LAPTOP');
$eligibleProducts = array_values(array_filter($products ?? [], static function (array $product): bool {
    return empty($product['affiliate_url'] ?? '') && !empty($product['product_url'] ?? '');
}));
?>

<div class="page-header">
    <div>
        <div class="page-kicker">Affiliate Links</div>
        <h2>Tạo & quản lý link affiliate</h2>
        <p>Lưu link affiliate thật lấy từ App Shopee, không tự chế link bằng affiliate_id.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-ghost" href="<?= url('/products') ?>">Xem sản phẩm</a>
        <a class="btn btn-purple" href="<?= url('/contents') ?>">Tạo content</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card accent"><div class="label">Tổng liên kết</div><div class="value"><?= (int)$linkSummary['total'] ?></div></div>
    <div class="stat-card success"><div class="label">Hoạt động</div><div class="value"><?= (int)$linkSummary['active'] ?></div></div>
    <div class="stat-card warning"><div class="label">Hết hạn</div><div class="value"><?= (int)$linkSummary['expired'] ?></div></div>
    <div class="stat-card danger"><div class="label">Lỗi</div><div class="value"><?= (int)$linkSummary['error'] ?></div></div>
</div>

<div class="card">
    <div class="section-heading">
        <div>
            <div class="card-title">Lưu link affiliate Shopee thật</div>
            <div class="section-note">Mở App Shopee → Tôi → Chương trình Tiếp thị liên kết → chọn sản phẩm → Chia sẻ để nhận hoa hồng → copy link shope.ee/... rồi dán vào từng sản phẩm.</div>
        </div>
        <div class="mini-stats"><span><strong><?= count($eligibleProducts) ?></strong> sản phẩm chưa có link</span></div>
    </div>
    <form data-ajax method="POST" action="<?= url('/links/generate-all') ?>">
        <div class="grid-3 compact-grid">
            <div class="form-group">
                <label class="form-label">Mã chiến dịch</label>
                <input class="form-control" name="campaign_code" value="<?= e($defaultCampaign) ?>" placeholder="VD: SHOPEE-MAY">
            </div>
            <div class="form-group">
                <label class="form-label">Số sản phẩm xử lý</label>
                <input class="form-control" name="limit" type="number" min="1" max="50" value="10">
            </div>
            <div class="form-group form-group-button">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-accent btn-full">Đồng bộ link đã dán</button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-title">Sản phẩm chưa có affiliate link</div>
    <?php if (empty($eligibleProducts)): ?>
        <div class="empty-state"><p>Không còn sản phẩm nào cần tạo link. Có thể chuyển sang bước tạo content.</p><a class="btn btn-purple" href="<?= url('/contents') ?>">Tạo content</a></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table-main table-compact">
                <thead><tr><th>Sản phẩm</th><th>Giá / lượt bán</th><th>Link gốc</th><th>Dán link affiliate thật</th></tr></thead>
                <tbody>
                <?php foreach ($eligibleProducts as $product): ?>
                    <tr>
                        <td>
                            <strong class="item-title"><?= e((string)$product['product_name']) ?></strong>
                            <div class="sub">SP #<?= (int)$product['id'] ?> · <?= e((string)$product['source_platform']) ?></div>
                        </td>
                        <td>
                            <div class="product-price"><?= number_format((float)($product['price'] ?? 0), 0, ',', '.') ?> ₫</div>
                            <div class="sub"><?= number_format((int)($product['sold_count'] ?? 0)) ?> đã bán</div>
                        </td>
                        <td><a href="<?= e((string)$product['product_url']) ?>" target="_blank" rel="noreferrer" class="text-xs">Link gốc ↗</a></td>
                        <td>
                            <form data-ajax method="POST" action="<?= url('/links/generate') ?>" style="min-width:280px">
                                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                                <input type="hidden" name="campaign_code" value="<?= e($defaultCampaign) ?>">
                                <input class="form-control" name="affiliate_url" placeholder="Dán link shope.ee/..." style="margin-bottom:6px" autocomplete="off">
                                <button type="submit" class="btn btn-accent btn-sm">Lưu link aff</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-title">Danh sách liên kết</div>
    <?php if (empty($links)): ?>
        <div class="empty-state"><p>Chưa có liên kết nào. Dùng form bên trên để tạo affiliate link.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table-main table-compact">
                <thead><tr><th>Mã</th><th>Sản phẩm</th><th>Chiến dịch</th><th>Đường dẫn affiliate</th><th>Trạng thái</th><th>Ngày tạo</th></tr></thead>
                <tbody>
                <?php foreach ($links as $link): ?>
                    <tr>
                        <td>#<?= (int)$link['id'] ?></td>
                        <td>
                            <strong>SP #<?= (int)$link['product_id'] ?></strong>
                            <div class="sub"><?= e((string)$link['source_platform']) ?></div>
                        </td>
                        <td><span class="badge badge-active"><?= e((string)$link['campaign_code']) ?></span></td>
                        <td>
                            <div class="copyable-link">
                                <div class="mono truncate" title="<?= e((string)$link['affiliate_url']) ?>"><?= e((string)$link['affiliate_url']) ?></div>
                                <button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e((string)$link['affiliate_url']) ?>">Copy</button>
                            </div>
                            <div class="link-actions">
                                <a href="<?= e((string)$link['affiliate_url']) ?>" target="_blank" rel="noreferrer" class="text-xs">Test link ↗</a>
                                <a href="<?= e((string)$link['original_url']) ?>" target="_blank" rel="noreferrer" class="text-xs">Link gốc ↗</a>
                            </div>
                        </td>
                        <td><?= status_badge((string)$link['status']) ?></td>
                        <td class="text-muted text-sm"><?= e((string)($link['created_at'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
