<div class="page-header">
    <div>
        <h2>Affiliate Links</h2>
        <p>Quản lý affiliate link đã tạo</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card accent"><div class="label">Tổng links</div><div class="value"><?= (int)$linkSummary['total'] ?></div></div>
    <div class="stat-card success"><div class="label">Active</div><div class="value"><?= (int)$linkSummary['active'] ?></div></div>
    <div class="stat-card warning"><div class="label">Expired</div><div class="value"><?= (int)$linkSummary['expired'] ?></div></div>
    <div class="stat-card danger"><div class="label">Error</div><div class="value"><?= (int)$linkSummary['error'] ?></div></div>
</div>

<div class="card">
    <div class="card-title">🔗 Danh sách affiliate links</div>
    <?php if (empty($links)): ?>
        <div class="empty-state"><p>Chưa có affiliate link nào. Vào trang <a href="<?= url('/products') ?>">Sản phẩm</a> để tạo link.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Sản phẩm</th><th>Campaign</th><th>Affiliate URL</th><th>Status</th><th>Ngày tạo</th></tr></thead>
                <tbody>
                <?php foreach ($links as $link): ?>
                    <tr>
                        <td>#<?= (int)$link['id'] ?></td>
                        <td>
                            <strong>Product #<?= (int)$link['product_id'] ?></strong>
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
