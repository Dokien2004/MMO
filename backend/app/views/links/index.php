<div class="page-header">
    <div>
        <h2>Liên kết Affiliate</h2>
        <p>Quản lý liên kết affiliate đã tạo</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card accent"><div class="label">Tổng liên kết</div><div class="value"><?= (int)$linkSummary['total'] ?></div></div>
    <div class="stat-card success"><div class="label">Hoạt động</div><div class="value"><?= (int)$linkSummary['active'] ?></div></div>
    <div class="stat-card warning"><div class="label">Hết hạn</div><div class="value"><?= (int)$linkSummary['expired'] ?></div></div>
    <div class="stat-card danger"><div class="label">Lỗi</div><div class="value"><?= (int)$linkSummary['error'] ?></div></div>
</div>

<div class="card">
    <div class="card-title">🔗 Danh sách liên kết</div>
    <?php if (empty($links)): ?>
        <div class="empty-state"><p>Chưa có liên kết nào. Vào trang <a href="<?= url('/products') ?>">Sản phẩm</a> để tạo.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
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
                        <td><div class="mono truncate" title="<?= e((string)$link['affiliate_url']) ?>"><?= e((string)$link['affiliate_url']) ?></div></td>
                        <td><?= status_badge((string)$link['status']) ?></td>
                        <td class="text-muted text-sm"><?= e((string)($link['created_at'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
