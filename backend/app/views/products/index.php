<div class="page-header">
    <div>
        <h2>Sản phẩm</h2>
        <p>Đồng bộ và quản lý sản phẩm affiliate</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card accent"><div class="label">Tổng</div><div class="value"><?= (int)$productSummary['total'] ?></div></div>
    <div class="stat-card"><div class="label">Mới</div><div class="value"><?= (int)$productSummary['new'] ?></div></div>
    <div class="stat-card success"><div class="label">Đã link</div><div class="value"><?= (int)$productSummary['linked'] ?></div></div>
    <div class="stat-card purple"><div class="label">Content ready</div><div class="value"><?= (int)$productSummary['content_ready'] ?></div></div>
    <div class="stat-card success"><div class="label">Đã đăng</div><div class="value"><?= (int)$productSummary['posted'] ?></div></div>
</div>

<div class="grid-12">
    <!-- Sync Form -->
    <div class="card">
        <div class="card-title">📦 Đồng bộ sản phẩm theo đợt</div>
        <p class="text-muted text-sm" style="margin-bottom:16px">Paste mảng JSON sản phẩm. Mỗi record cần <code style="background:var(--bg-elevated);padding:2px 6px;border-radius:4px;font-size:11px">source_product_id</code>, <code style="background:var(--bg-elevated);padding:2px 6px;border-radius:4px;font-size:11px">product_name</code>, <code style="background:var(--bg-elevated);padding:2px 6px;border-radius:4px;font-size:11px">product_url</code>.</p>
        <form data-ajax method="POST" action="<?= url('/sync/manual') ?>">
            <div class="form-group">
                <label class="form-label">Nguồn</label>
                <select class="form-control" name="platform">
                    <option value="affiliate_api">Affiliate API</option>
                    <option value="shopee">Shopee</option>
                    <option value="tiktokshop">TikTok Shop</option>
                    <option value="lazada">Lazada</option>
                    <option value="manual">Manual</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">JSON Payload</label>
                <textarea class="form-control" name="products_json"><?= e($samplePayload) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Đồng bộ sản phẩm</button>
        </form>
    </div>

    <!-- Batch Actions -->
    <div class="card">
        <div class="card-title">⚡ Tạo link hàng loạt</div>
        <form data-ajax method="POST" action="<?= url('/links/generate-all') ?>">
            <div class="form-group">
                <label class="form-label">Campaign code</label>
                <input class="form-control" name="campaign_code" value="MVP-LAPTOP">
            </div>
            <div class="form-group">
                <label class="form-label">Số SP xử lý</label>
                <input class="form-control" name="limit" type="number" min="1" max="20" value="5">
            </div>
            <button type="submit" class="btn btn-accent" style="width:100%">Tạo link hàng loạt</button>
        </form>
        <hr style="border:none;border-top:1px solid var(--border);margin:20px 0">
        <div class="card-title">✨ Sinh draft hàng loạt</div>
        <form data-ajax method="POST" action="<?= url('/contents/generate-all') ?>">
            <div class="form-group">
                <label class="form-label">Provider</label>
                <select class="form-control" name="provider">
                    <option value="template_engine">Template Engine</option>
                    <option value="openai">OpenAI</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Số SP xử lý</label>
                <input class="form-control" name="limit" type="number" min="1" max="20" value="5">
            </div>
            <button type="submit" class="btn btn-purple" style="width:100%">Sinh draft content</button>
        </form>
    </div>
</div>

<!-- Products Table -->
<div class="card">
    <div class="card-title">📋 Danh sách sản phẩm</div>
    <?php if (empty($products)): ?>
        <div class="empty-state"><p>Chưa có sản phẩm nào được đồng bộ.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Sản phẩm</th><th>Giá</th><th>Status</th><th>Content</th><th>Hành động</th></tr></thead>
                <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td>#<?= (int)$product['id'] ?></td>
                        <td>
                            <strong><?= e((string)$product['product_name']) ?></strong>
                            <div class="sub"><?= e((string)$product['source_platform']) ?> · <?= e((string)$product['source_product_id']) ?></div>
                            <a href="<?= e((string)$product['product_url']) ?>" target="_blank" rel="noreferrer" class="text-xs">Mở link gốc ↗</a>
                        </td>
                        <td class="text-sm"><?= number_format((float)($product['price'] ?? 0), 0, ',', '.') ?> ₫</td>
                        <td><?= status_badge((string)$product['status']) ?></td>
                        <td><?= status_badge((string)($product['content_status'] ?? 'none')) ?></td>
                        <td>
                            <div class="btn-group">
                                <form data-ajax method="POST" action="<?= url('/links/generate') ?>">
                                    <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                                    <input type="hidden" name="campaign_code" value="MVP-LAPTOP">
                                    <button type="submit" class="btn btn-accent btn-sm">Tạo link</button>
                                </form>
                                <form data-ajax method="POST" action="<?= url('/contents/generate') ?>">
                                    <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                                    <input type="hidden" name="provider" value="template_engine">
                                    <button type="submit" class="btn btn-purple btn-sm">Sinh draft</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
