<div class="page-header">
    <div>
        <h2>Nội dung</h2>
        <p>Quản lý draft content — duyệt hoặc từ chối trước khi đăng bài</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card accent"><div class="label">Tổng</div><div class="value"><?= (int)$contentSummary['total'] ?></div></div>
    <div class="stat-card warning"><div class="label">Draft</div><div class="value"><?= (int)$contentSummary['draft'] ?></div></div>
    <div class="stat-card success"><div class="label">Approved</div><div class="value"><?= (int)$contentSummary['approved'] ?></div></div>
    <div class="stat-card danger"><div class="label">Rejected</div><div class="value"><?= (int)$contentSummary['rejected'] ?></div></div>
    <div class="stat-card"><div class="label">Used</div><div class="value"><?= (int)$contentSummary['used'] ?></div></div>
</div>

<div class="card">
    <div class="card-title">📄 Danh sách nội dung</div>
    <?php if (empty($contents)): ?>
        <div class="empty-state"><p>Chưa có content nào. Vào <a href="<?= url('/products') ?>">Sản phẩm</a> để sinh draft.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Nội dung</th><th>Provider</th><th>Status</th><th>Hành động</th></tr></thead>
                <tbody>
                <?php foreach ($contents as $content): ?>
                    <tr>
                        <td>#<?= (int)$content['id'] ?><div class="sub">SP #<?= (int)$content['product_id'] ?></div></td>
                        <td style="max-width:400px">
                            <strong><?= e((string)$content['title']) ?></strong>
                            <div class="sub" style="margin-top:4px"><?= e((string)$content['hashtags']) ?></div>
                            <div class="mono mt-8" style="max-height:80px;overflow:hidden"><?= e(mb_substr((string)$content['body'], 0, 200)) ?>…</div>
                        </td>
                        <td><span class="badge badge-active"><?= e((string)$content['ai_provider']) ?></span></td>
                        <td><?= status_badge((string)$content['status']) ?></td>
                        <td>
                            <div class="btn-group">
                                <?php if (($content['status'] ?? '') === 'draft'): ?>
                                    <form data-ajax method="POST" action="<?= url('/contents/approve') ?>">
                                        <input type="hidden" name="content_id" value="<?= (int)$content['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                    </form>
                                    <form data-ajax method="POST" action="<?= url('/contents/reject') ?>">
                                        <input type="hidden" name="content_id" value="<?= (int)$content['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (($content['status'] ?? '') === 'approved'): ?>
                                    <form data-ajax method="POST" action="<?= url('/posts/schedule') ?>">
                                        <input type="hidden" name="content_id" value="<?= (int)$content['id'] ?>">
                                        <input type="hidden" name="channel" value="fanpage_manual">
                                        <input type="hidden" name="scheduled_at" value="">
                                        <button type="submit" class="btn btn-accent btn-sm">Schedule</button>
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
