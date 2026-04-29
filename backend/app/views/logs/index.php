<div class="page-header">
    <div>
        <h2>Nhật ký</h2>
        <p>Lịch sử hoạt động pipeline</p>
    </div>
</div>

<div class="card">
    <div class="card-title">
        <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:var(--accent);fill:none;stroke-width:2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Job Logs
    </div>
    <?php if (empty($logs)): ?>
        <div class="empty-state"><p>Chưa có log nào.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Task</th><th>Status</th><th>Chi tiết</th><th>Thời gian</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>#<?= (int)$log['id'] ?></td>
                        <td><strong><?= e((string)$log['task_name']) ?></strong></td>
                        <td><?= status_badge((string)$log['status']) ?></td>
                        <td>
                            <?php if (!empty($log['error_message'])): ?>
                                <span class="text-danger text-sm"><?= e((string)$log['error_message']) ?></span>
                            <?php else: ?>
                                <span class="text-muted text-sm">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted text-sm"><?= e((string)$log['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
