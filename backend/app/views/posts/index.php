<?php
$postsByContentId = [];
foreach (($posts ?? []) as $post) {
    $postsByContentId[(int)($post['content_id'] ?? 0)] = $post;
}
$approvedUnscheduled = array_values(array_filter($contents ?? [], static function (array $content) use ($postsByContentId): bool {
    return ($content['status'] ?? '') === 'approved' && !isset($postsByContentId[(int)($content['id'] ?? 0)]);
}));
$manualScheduledCount = count(array_filter($posts ?? [], static fn(array $post): bool => ($post['status'] ?? '') === 'scheduled' && ($post['channel'] ?? '') === 'fanpage_manual'));
$apiScheduledCount = count(array_filter($posts ?? [], static fn(array $post): bool => ($post['status'] ?? '') === 'scheduled' && ($post['channel'] ?? '') === 'fanpage_api'));
$defaultChannel = (string)($automationSettings['default_channel'] ?? 'fanpage_manual');
$defaultStartLocal = date('Y-m-d\TH:i', time() + 3600);
$defaultInterval = (int)($automationSettings['publish_interval_minutes'] ?? 15);
$buildPostText = static function (?array $content): string {
    if (!$content) return '';
    return trim(implode("\n\n", array_filter([
        (string)($content['title'] ?? ''),
        (string)($content['body'] ?? ''),
        (string)($content['call_to_action'] ?? ''),
        (string)($content['hashtags'] ?? ''),
    ])));
};
?>

<div class="page-header">
    <div>
        <div class="page-kicker">Publishing Queue</div>
        <h2>Đăng bài Facebook</h2>
        <p>Lên lịch, đăng thủ công hoặc publish tự động lên Fanpage bằng Facebook Graph API.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-purple" href="<?= url('/contents') ?>">Duyệt nội dung</a>
        <a class="btn btn-ghost" href="<?= url('/settings') ?>">Cấu hình Facebook</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card accent"><div class="label">Tổng</div><div class="value"><?= (int)$postSummary['total'] ?></div></div>
    <div class="stat-card warning"><div class="label">Đã lên lịch</div><div class="value"><?= (int)$postSummary['scheduled'] ?></div></div>
    <div class="stat-card success"><div class="label">Thành công</div><div class="value"><?= (int)$postSummary['success'] ?></div></div>
    <div class="stat-card danger"><div class="label">Thất bại</div><div class="value"><?= (int)$postSummary['failed'] ?></div></div>
</div>

<div class="publish-mode-grid">
    <div class="card publish-mode-card <?= $fanpageApiReady ? 'ready' : 'blocked' ?>">
        <div class="action-card-head">
            <div class="action-icon success">🚀</div>
            <div>
                <div class="card-title">Đăng tự động lên Facebook</div>
                <p class="section-note">Dùng Facebook Page ID + Page Access Token để publish thật qua Graph API.</p>
            </div>
        </div>
        <div class="status-stack compact-status">
            <div class="status-line"><span>Fanpage API</span><?= status_badge($fanpageApiReady ? 'success' : 'failed') ?></div>
            <div class="status-line"><span>Bài API đang chờ</span><strong><?= $apiScheduledCount ?></strong></div>
        </div>
        <?php if ($fanpageApiReady): ?>
            <form data-ajax method="POST" action="<?= url('/posts/publish-due') ?>" class="mt-16">
                <input type="hidden" name="limit" value="5">
                <button type="submit" class="btn btn-success btn-full">Đăng ngay các bài đến hạn</button>
            </form>
        <?php else: ?>
            <div class="hint-box mt-16">Chưa cấu hình Facebook Page ID hoặc Page Access Token. Vào Settings để bật đăng tự động.</div>
        <?php endif; ?>
    </div>

    <div class="card publish-mode-card">
        <div class="action-card-head">
            <div class="action-icon purple">🗓️</div>
            <div>
                <div class="card-title">Tạo lịch đăng</div>
                <p class="section-note">Lấy content đã duyệt và đưa vào hàng đợi đăng bài.</p>
            </div>
        </div>
        <div class="mini-stats">
            <span><strong><?= count($approvedUnscheduled) ?></strong> content đã duyệt chưa lên lịch</span>
            <span><strong><?= e($defaultChannel) ?></strong> kênh mặc định</span>
        </div>
        <form data-ajax method="POST" action="<?= url('/posts/schedule-all') ?>">
            <div class="grid-2 compact-grid">
                <div class="form-group">
                    <label class="form-label">Kênh đăng</label>
                    <select class="form-control" name="channel">
                        <option value="fanpage_api" <?= $defaultChannel === 'fanpage_api' ? 'selected' : '' ?>>Facebook API tự động</option>
                        <option value="fanpage_manual" <?= $defaultChannel === 'fanpage_manual' ? 'selected' : '' ?>>Facebook thủ công</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Số bài</label>
                    <input class="form-control" name="limit" type="number" min="1" max="20" value="5">
                </div>
            </div>
            <div class="grid-2 compact-grid">
                <div class="form-group">
                    <label class="form-label">Giờ bắt đầu</label>
                    <input class="form-control" type="datetime-local" name="scheduled_at" value="<?= e($defaultStartLocal) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Cách nhau (phút)</label>
                    <input class="form-control" type="number" min="1" max="1440" name="interval_minutes" value="<?= max(1, $defaultInterval) ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-purple btn-full">Tạo lịch cho content đã duyệt</button>
        </form>
    </div>

    <div class="card publish-mode-card">
        <div class="action-card-head">
            <div class="action-icon accent">✋</div>
            <div>
                <div class="card-title">Đăng thủ công</div>
                <p class="section-note">Copy nội dung/media rồi tự đăng lên Fanpage, sau đó đánh dấu đã đăng.</p>
            </div>
        </div>
        <div class="mini-stats">
            <span><strong><?= $manualScheduledCount ?></strong> bài thủ công chờ đăng</span>
        </div>
        <div class="hint-box">Phù hợp khi token Facebook chưa sẵn sàng hoặc Boss muốn kiểm tra bài trước khi publish.</div>
    </div>
</div>

<div class="card">
    <div class="section-heading">
        <div>
            <div class="card-title">Chọn content để lên lịch</div>
            <div class="section-note">Nghiệp vụ lên lịch nằm riêng tại đây: chọn nhiều content approved, chọn giờ bắt đầu và khoảng cách giữa các bài.</div>
        </div>
        <div class="mini-stats"><span><strong><?= count($approvedUnscheduled) ?></strong> content sẵn sàng</span></div>
    </div>
    <?php if (empty($approvedUnscheduled)): ?>
        <div class="empty-state"><p>Không còn content approved nào chưa lên lịch.</p><a class="btn btn-purple" href="<?= url('/contents') ?>">Duyệt content</a></div>
    <?php else: ?>
        <form id="postBulkScheduleForm" data-ajax method="POST" action="<?= url('/posts/schedule-selected') ?>">
            <div class="grid-4 compact-grid">
                <div class="form-group">
                    <label class="form-label">Kênh đăng</label>
                    <select class="form-control" name="channel">
                        <option value="fanpage_api" <?= $defaultChannel === 'fanpage_api' ? 'selected' : '' ?>>Facebook API tự động</option>
                        <option value="fanpage_manual" <?= $defaultChannel === 'fanpage_manual' ? 'selected' : '' ?>>Facebook thủ công</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Giờ bắt đầu</label>
                    <input class="form-control" type="datetime-local" name="scheduled_at" value="<?= e($defaultStartLocal) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Cách nhau (phút)</label>
                    <input class="form-control" type="number" min="1" max="1440" name="interval_minutes" value="<?= max(1, $defaultInterval) ?>">
                </div>
                <div class="form-group form-group-button">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-success btn-full">Lên lịch content đã chọn</button>
                </div>
            </div>
            <div class="table-wrap mt-16">
                <table class="table-main table-compact posts-table">
                    <thead>
                    <tr>
                        <th class="select-col"><input type="checkbox" data-toggle-checks=".post-bulk-check" title="Chọn tất cả"></th>
                        <th>Content</th>
                        <th>Media</th>
                        <th>Trạng thái</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($approvedUnscheduled as $content): ?>
                        <tr>
                            <td class="select-col"><input class="post-bulk-check" type="checkbox" name="content_ids[]" value="<?= (int)$content['id'] ?>"></td>
                            <td>
                                <strong class="item-title"><?= e((string)($content['title'] ?? ('Content #' . (int)$content['id']))) ?></strong>
                                <div class="sub">Content #<?= (int)$content['id'] ?> · SP #<?= (int)($content['product_id'] ?? 0) ?></div>
                                <div class="post-preview-text"><?= e(mb_substr((string)($content['body'] ?? ''), 0, 150)) ?><?= mb_strlen((string)($content['body'] ?? '')) > 150 ? '…' : '' ?></div>
                            </td>
                            <td>
                                <?php if (!empty($content['media_url'] ?? '')): ?>
                                    <a href="<?= e((string)$content['media_url']) ?>" target="_blank" rel="noreferrer">
                                        <?php if (($content['media_type'] ?? '') === 'image'): ?>
                                            <img class="media-thumb media-thumb-sm" src="<?= e((string)$content['media_url']) ?>" alt="Media content #<?= (int)$content['id'] ?>">
                                        <?php else: ?>
                                            Mở media ↗
                                        <?php endif; ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted text-sm">Chưa có media</span>
                                <?php endif; ?>
                            </td>
                            <td><?= status_badge((string)($content['status'] ?? 'draft')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    <?php endif; ?>
</div>

<div class="card">
    <div class="section-heading">
        <div>
            <div class="card-title">Hàng đợi đăng bài</div>
            <div class="section-note">Bài kênh <strong>fanpage_api</strong> có thể đăng tự động; bài <strong>fanpage_manual</strong> dùng copy/đánh dấu.</div>
        </div>
        <div class="btn-group">
            <a class="btn btn-ghost btn-sm" href="<?= url('/contents') ?>">Nội dung</a>
            <a class="btn btn-ghost btn-sm" href="<?= url('/settings') ?>">Settings</a>
        </div>
    </div>
    <?php if (empty($posts)): ?>
        <div class="empty-state">
            <p>Chưa có bài đăng nào. Hãy duyệt nội dung rồi tạo lịch đăng.</p>
            <a class="btn btn-purple" href="<?= url('/contents') ?>">Đi tới nội dung</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table-main table-compact posts-table">
                <thead>
                <tr>
                    <th>Bài đăng</th>
                    <th>Kênh</th>
                    <th>Lịch / kết quả</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($posts as $post): ?>
                    <?php
                    $content = $postContents[(int)$post['content_id']] ?? null;
                    $isScheduled = ($post['status'] ?? '') === 'scheduled';
                    $isApi = ($post['channel'] ?? '') === 'fanpage_api';
                    $postText = $buildPostText($content);
                    ?>
                    <tr>
                        <td class="post-content-cell">
                            <strong class="item-title"><?= $content ? e((string)$content['title']) : ('Nội dung #' . (int)$post['content_id']) ?></strong>
                            <div class="sub">Post #<?= (int)$post['id'] ?> · SP #<?= (int)$post['product_id'] ?> · Content #<?= (int)$post['content_id'] ?></div>
                            <?php if ($content): ?>
                                <div class="post-preview-text"><?= e(mb_substr((string)($content['body'] ?? ''), 0, 180)) ?><?= mb_strlen((string)($content['body'] ?? '')) > 180 ? '…' : '' ?></div>
                            <?php endif; ?>
                            <?php if ($content && !empty($content['media_url'] ?? '')): ?>
                                <div class="mt-8">
                                    <?php if (($content['media_type'] ?? '') === 'image'): ?>
                                        <img class="media-thumb media-thumb-sm" src="<?= e((string)$content['media_url']) ?>" alt="Media content #<?= (int)$content['id'] ?>">
                                    <?php elseif (($content['media_type'] ?? '') === 'video'): ?>
                                        <video class="media-thumb media-thumb-sm" src="<?= e((string)$content['media_url']) ?>" controls></video>
                                    <?php endif; ?>
                                    <a href="<?= e((string)$content['media_url']) ?>" target="_blank" rel="noreferrer" class="text-xs">Mở media ↗</a>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $isApi ? 'badge-success' : 'badge-pending' ?>"><?= e((string)$post['channel']) ?></span>
                            <div class="section-note mt-8"><?= $isApi ? 'Có thể publish tự động' : 'Đăng thủ công rồi xác nhận' ?></div>
                        </td>
                        <td>
                            <div class="text-sm"><span class="text-muted">Lên lịch:</span> <?= e((string)$post['scheduled_at']) ?></div>
                            <?php if (!empty($post['posted_at'])): ?>
                                <div class="text-sm mt-8"><span class="text-muted">Đã đăng:</span> <?= e((string)$post['posted_at']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($post['remote_post_id'])): ?>
                                <div class="sub mt-8">FB ID: <?= e((string)$post['remote_post_id']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($post['result_note'])): ?>
                                <div class="sub mt-8"><?= e((string)$post['result_note']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= status_badge((string)$post['status']) ?></td>
                        <td>
                            <?php if ($isScheduled): ?>
                                <div class="product-row-actions">
                                    <?php if ($isApi): ?>
                                        <form data-ajax method="POST" action="<?= url('/posts/publish') ?>">
                                            <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                                            <button type="submit" class="btn btn-success btn-sm" <?= $fanpageApiReady ? '' : 'disabled' ?>>Đăng ngay FB</button>
                                        </form>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e($postText) ?>">Copy nội dung</button>
                                    <?php if ($content && !empty($content['media_url'] ?? '')): ?>
                                        <button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e(app_absolute_url((string)$content['media_url'])) ?>">Copy ảnh</button>
                                    <?php endif; ?>
                                    <form data-ajax method="POST" action="<?= url('/posts/mark-success') ?>">
                                        <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                                        <input type="hidden" name="result_note" value="Đã đăng thủ công trên Fanpage">
                                        <button type="submit" class="btn btn-accent btn-sm">Đã đăng thủ công</button>
                                    </form>
                                    <form data-ajax method="POST" action="<?= url('/posts/mark-failed') ?>">
                                        <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                                        <input type="hidden" name="result_note" value="Cần đăng lại thủ công">
                                        <button type="submit" class="btn btn-danger btn-sm">Báo lỗi</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span class="text-muted text-sm">Đã xử lý</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
