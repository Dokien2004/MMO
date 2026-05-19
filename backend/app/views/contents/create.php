<?php
$defaultProvider = (string)($automationSettings['default_content_provider'] ?? 'auto');
if ($defaultProvider === '') {
    $defaultProvider = 'auto';
}
$contentByProductId = [];
foreach (($contents ?? []) as $content) {
    $contentByProductId[(int)($content['product_id'] ?? 0)] = $content;
}
$productsNeedContent = array_values(array_filter($products ?? [], static function (array $product) use ($contentByProductId): bool {
    return !empty($product['affiliate_url'] ?? '') && !isset($contentByProductId[(int)($product['id'] ?? 0)]);
}));
$contentsNeedImage = array_values(array_filter($contents ?? [], static function (array $content): bool {
    return empty($content['media_url'] ?? '') || (($content['media_type'] ?? 'none') === 'none');
}));
$contentsNeedVideo = array_values(array_filter($contents ?? [], static function (array $content): bool {
    return (($content['media_type'] ?? 'none') !== 'video') || empty($content['media_url'] ?? '');
}));
?>

<div class="page-header">
    <div>
        <div class="page-kicker">AI Content Studio</div>
        <h2>Tạo Content AI</h2>
        <p>Sinh content, tạo ảnh và video cho sản phẩm affiliate. Duyệt & lên lịch → trang <a href="<?= url('/posts/facebook') ?>">Đăng bài</a></p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-ghost" href="<?= url('/links') ?>">Tạo affiliate link</a>
        <a class="btn btn-success" href="<?= url('/contents/list') ?>">Duyệt & Lịch đăng</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card accent"><div class="label">Tổng</div><div class="value"><?= (int)$contentSummary['total'] ?></div></div>
    <div class="stat-card warning"><div class="label">Bản nháp</div><div class="value"><?= (int)$contentSummary['draft'] ?></div></div>
    <div class="stat-card success"><div class="label">Đã duyệt</div><div class="value"><?= (int)$contentSummary['approved'] ?></div></div>
    <div class="stat-card danger"><div class="label">Từ chối</div><div class="value"><?= (int)$contentSummary['rejected'] ?></div></div>
    <div class="stat-card"><div class="label">Đã dùng</div><div class="value"><?= (int)$contentSummary['used'] ?></div></div>
</div>

<div class="product-action-grid">
    <div class="card product-action-card">
        <div class="action-card-head">
            <div class="action-icon purple">✍️</div>
            <div>
                <div class="card-title">Sinh content AI</div>
                <p class="section-note">Tạo bản nháp cho sản phẩm đã có affiliate link nhưng chưa có content.</p>
            </div>
        </div>
        <div class="mini-stats">
            <span><strong><?= count($productsNeedContent) ?></strong> chờ viết</span>
            <span><strong><?= e($defaultProvider) ?></strong> provider</span>
        </div>
        <form data-ajax method="POST" action="<?= url('/contents/generate-all') ?>">
            <div class="form-group">
                <label class="form-label">Số sản phẩm xử lý</label>
                <input class="form-control" name="limit" type="number" min="1" max="50" value="10">
            </div>
            <button type="submit" class="btn btn-purple btn-full">Sinh bản nháp hàng loạt</button>
        </form>
    </div>

    <div class="card product-action-card">
        <div class="action-card-head">
            <div class="action-icon success">🖼️</div>
            <div>
                <div class="card-title">Tạo ảnh AI</div>
                <p class="section-note">Tạo ảnh cho các content chưa có media.</p>
            </div>
        </div>
        <div class="mini-stats">
            <span><strong><?= count($contentsNeedImage) ?></strong> chờ ảnh</span>
            <span><strong><?= e(image_model()) ?></strong> model</span>
        </div>
        <form data-ajax method="POST" action="<?= url('/contents/generate-images') ?>">
            <div class="form-group">
                <label class="form-label">Số content xử lý</label>
                <input class="form-control" name="limit" type="number" min="1" max="50" value="5">
            </div>
            <button type="submit" class="btn btn-success btn-full">Tạo ảnh AI hàng loạt</button>
        </form>
    </div>

    <div class="card product-action-card">
        <div class="action-card-head">
            <div class="action-icon warning">🎬</div>
            <div>
                <div class="card-title">Tạo video sản phẩm</div>
                <p class="section-note">Tạo video dọc ngắn từ content sản phẩm để đăng Reels/TikTok/Facebook.</p>
            </div>
        </div>
        <div class="mini-stats">
            <span><strong><?= count($contentsNeedVideo) ?></strong> chờ video</span>
            <span><strong><?= e(video_model()) ?></strong> model</span>
        </div>
        <form data-ajax method="POST" action="<?= url('/contents/generate-videos') ?>">
            <div class="form-group">
                <label class="form-label">Số content xử lý</label>
                <input class="form-control" name="limit" type="number" min="1" max="20" value="3">
            </div>
            <button type="submit" class="btn btn-accent btn-full">Tạo video hàng loạt</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="section-heading">
        <div>
            <div class="card-title">Sản phẩm sẵn sàng tạo content</div>
            <div class="section-note">Chỉ hiển thị sản phẩm đã có affiliate link và chưa có content.</div>
        </div>
    </div>
    <?php if (empty($productsNeedContent)): ?>
        <div class="empty-state"><p>Không còn sản phẩm nào đang chờ viết content.</p><a class="btn btn-ghost" href="<?= url('/links') ?>">Kiểm tra link affiliate</a></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table-main table-compact">
                <thead><tr><th>Sản phẩm</th><th>Affiliate</th><th>Tạo content</th></tr></thead>
                <tbody>
                <?php foreach ($productsNeedContent as $product): ?>
                    <tr>
                        <td data-label="Sản phẩm">
                            <strong class="item-title"><?= e((string)$product['product_name']) ?></strong>
                            <div class="sub">SP #<?= (int)$product['id'] ?> · <?= number_format((int)($product['sold_count'] ?? 0)) ?> đã bán</div>
                        </td>
                        <td data-label="Affiliate"><button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e((string)$product['affiliate_url']) ?>">Copy aff</button></td>
                        <td data-label="Tạo content">
                            <form data-ajax method="POST" action="<?= url('/contents/generate') ?>">
                                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                                <input type="hidden" name="provider" value="<?= e($defaultProvider) ?>">
                                <button type="submit" class="btn btn-purple btn-sm">Sinh content</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>