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
        <h2>Tạo content & ảnh AI</h2>
        <p>Nghiệp vụ viết content, duyệt content và tạo ảnh AI nằm ở đây. Lên lịch đăng chuyển sang trang Đăng bài.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-ghost" href="<?= url('/links') ?>">Tạo affiliate link</a>
        <a class="btn btn-success" href="<?= url('/posts') ?>">Lịch đăng</a>
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
            <div class="grid-2 compact-grid">
                <div class="form-group">
                    <label class="form-label">Nguồn sinh</label>
                    <select class="form-control" name="provider">
                        <option value="auto" <?= $defaultProvider === 'auto' ? 'selected' : '' ?>>Tự động fallback</option>
                        <option value="openai" <?= $defaultProvider === 'openai' ? 'selected' : '' ?>>OpenAI/9router</option>
                        <option value="gemini" <?= $defaultProvider === 'gemini' ? 'selected' : '' ?>>Gemini</option>
                        <option value="template_engine" <?= $defaultProvider === 'template_engine' ? 'selected' : '' ?>>Mẫu có sẵn</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Số sản phẩm xử lý</label>
                    <input class="form-control" name="limit" type="number" min="1" max="50" value="10">
                </div>
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
                        <td>
                            <strong class="item-title"><?= e((string)$product['product_name']) ?></strong>
                            <div class="sub">SP #<?= (int)$product['id'] ?> · <?= number_format((int)($product['sold_count'] ?? 0)) ?> đã bán</div>
                        </td>
                        <td><button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e((string)$product['affiliate_url']) ?>">Copy aff</button></td>
                        <td>
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

<div class="card">
    <div class="section-heading">
        <div class="card-title">Danh sách nội dung</div>
        <div class="section-note">Duyệt nội dung và tạo/tạo lại ảnh AI. Lên lịch đăng thực hiện ở trang Đăng bài.</div>
    </div>
    <?php if (empty($contents)): ?>
        <div class="empty-state"><p>Chưa có nội dung nào. Hãy tạo affiliate link trước, sau đó quay lại đây để sinh content.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table-main table-compact">
                <thead><tr><th>Mã</th><th>Nội dung</th><th>Nguồn sinh</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                <tbody>
                <?php foreach ($contents as $content): ?>
                    <tr>
                        <td>#<?= (int)$content['id'] ?><div class="sub">SP #<?= (int)$content['product_id'] ?></div></td>
                        <td style="max-width:400px">
                            <strong><?= e((string)$content['title']) ?></strong>
                            <div class="sub" style="margin-top:4px"><?= e((string)$content['hashtags']) ?></div>
                            <div class="mono mt-8" style="max-height:80px;overflow:hidden"><?= e(mb_substr((string)$content['body'], 0, 200)) ?>…</div>
                            <?php if (!empty($content['media_url'] ?? '')): ?>
                                <div class="mt-8">
                                    <?php if (($content['media_type'] ?? '') === 'image'): ?>
                                        <a href="<?= e((string)$content['media_url']) ?>" target="_blank" rel="noreferrer">
                                            <img class="media-thumb" src="<?= e((string)$content['media_url']) ?>" alt="Media content #<?= (int)$content['id'] ?>">
                                        </a>
                                    <?php elseif (($content['media_type'] ?? '') === 'video'): ?>
                                        <video class="media-thumb" src="<?= e((string)$content['media_url']) ?>" controls></video>
                                    <?php endif; ?>
                                    <a href="<?= e((string)$content['media_url']) ?>" target="_blank" rel="noreferrer" class="text-xs">Mở media <?= e((string)($content['media_type'] ?? '')) ?> ↗</a>
                                </div>
                            <?php elseif (!empty($content['media_prompt'] ?? '')): ?>
                                <div class="sub mt-8">Media: <?= e((string)($content['media_status'] ?? 'pending')) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-active"><?= e((string)$content['ai_provider']) ?></span>
                            <?php if (!empty($content['media_type'] ?? '') && ($content['media_type'] ?? 'none') !== 'none'): ?>
                                <div class="sub mt-8">Media: <?= e((string)$content['media_type']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= status_badge((string)$content['status']) ?></td>
                        <td>
                            <div class="btn-group">
                                <?php if (empty($content['media_url'] ?? '') || ($content['media_type'] ?? 'none') === 'none'): ?>
                                    <form data-ajax method="POST" action="<?= url('/contents/generate-image') ?>">
                                        <input type="hidden" name="content_id" value="<?= (int)$content['id'] ?>">
                                        <button type="submit" class="btn btn-purple btn-sm">Tạo ảnh AI</button>
                                    </form>
                                <?php else: ?>
                                    <form data-ajax method="POST" action="<?= url('/contents/generate-image') ?>">
                                        <input type="hidden" name="content_id" value="<?= (int)$content['id'] ?>">
                                        <button type="submit" class="btn btn-ghost btn-sm" data-confirm="Tạo lại ảnh AI cho content này?">Tạo lại ảnh</button>
                                    </form>
                                <?php endif; ?>
                                <form data-ajax method="POST" action="<?= url('/contents/generate-video') ?>">
                                    <input type="hidden" name="content_id" value="<?= (int)$content['id'] ?>">
                                    <button type="submit" class="btn btn-accent btn-sm" data-confirm="Tạo video sản phẩm cho content này? Media hiện tại sẽ được đổi sang video.">Tạo video</button>
                                </form>
                                <?php if (($content['status'] ?? '') === 'draft'): ?>
                                    <form data-ajax method="POST" action="<?= url('/contents/approve') ?>">
                                        <input type="hidden" name="content_id" value="<?= (int)$content['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm">Duyệt</button>
                                    </form>
                                    <form data-ajax method="POST" action="<?= url('/contents/reject') ?>">
                                        <input type="hidden" name="content_id" value="<?= (int)$content['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Từ chối</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
