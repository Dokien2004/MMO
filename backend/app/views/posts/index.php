<div class="page-header">
    <div>
        <h2>Đăng bài</h2>
        <p>Quản lý lịch đăng và trạng thái bài viết</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card accent"><div class="label">Tổng</div><div class="value"><?= (int)$postSummary['total'] ?></div></div>
    <div class="stat-card warning"><div class="label">Scheduled</div><div class="value"><?= (int)$postSummary['scheduled'] ?></div></div>
    <div class="stat-card success"><div class="label">Thành công</div><div class="value"><?= (int)$postSummary['success'] ?></div></div>
    <div class="stat-card danger"><div class="label">Thất bại</div><div class="value"><?= (int)$postSummary['failed'] ?></div></div>
</div>

<div class="card">
    <div class="card-title">✈️ Danh sách bài đăng</div>
    <?php if (empty($posts)): ?>
        <div class="empty-state"><p>Chưa có bài đăng nào. Approve content rồi schedule tại trang <a href="<?= url('/contents') ?>">Nội dung</a>.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Content</th><th>Channel</th><th>Lịch đăng</th><th>Status</th><th>Hành động</th></tr></thead>
                <tbody>
                <?php foreach ($posts as $post): ?>
                    <tr>
                        <td>#<?= (int)$post['id'] ?></td>
                        <td>
                            <strong>Content #<?= (int)$post['content_id'] ?></strong>
                            <div class="sub">SP #<?= (int)$post['product_id'] ?></div>
                        </td>
                        <td><span class="badge badge-active"><?= e((string)$post['channel']) ?></span></td>
                        <td>
                            <div class="text-sm">
                                <span class="text-muted">Schedule:</span> <?= e((string)$post['scheduled_at']) ?>
                            </div>
                            <?php if (!empty($post['posted_at'])): ?>
                                <div class="text-sm mt-8">
                                    <span class="text-muted">Posted:</span> <?= e((string)$post['posted_at']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($post['result_note'])): ?>
                                <div class="sub mt-8"><?= e((string)$post['result_note']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= status_badge((string)$post['status']) ?></td>
                        <td>
                            <?php if (($post['status'] ?? '') === 'scheduled'): ?>
                                <div class="btn-group">
                                    <form data-ajax method="POST" action="<?= url('/posts/mark-success') ?>">
                                        <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                                        <input type="hidden" name="result_note" value="Đã đăng thủ công trên Fanpage">
                                        <button type="submit" class="btn btn-success btn-sm">✓ Posted</button>
                                    </form>
                                    <form data-ajax method="POST" action="<?= url('/posts/mark-failed') ?>">
                                        <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                                        <input type="hidden" name="result_note" value="Cần đăng lại thủ công">
                                        <button type="submit" class="btn btn-danger btn-sm">✕ Failed</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span class="text-muted text-sm">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
