<div class="page-header">
    <div>
        <h2>Dashboard</h2>
        <p>Tổng quan pipeline affiliate MVP</p>
    </div>
</div>

<!-- Pipeline Visual -->
<div class="card mb-24">
    <div class="card-title">
        <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Pipeline Flow
    </div>
    <div class="pipeline">
        <div class="pipeline-step">
            <div class="step-icon">📦</div>
            <span class="step-count"><?= (int)$productSummary['total'] ?></span>
            <span class="step-label">Sản phẩm</span>
        </div>
        <div class="pipeline-arrow"></div>
        <div class="pipeline-step">
            <div class="step-icon">🔗</div>
            <span class="step-count"><?= (int)$linkSummary['total'] ?></span>
            <span class="step-label">Links</span>
        </div>
        <div class="pipeline-arrow"></div>
        <div class="pipeline-step">
            <div class="step-icon">📝</div>
            <span class="step-count"><?= (int)$contentSummary['draft'] ?></span>
            <span class="step-label">Drafts</span>
        </div>
        <div class="pipeline-arrow"></div>
        <div class="pipeline-step">
            <div class="step-icon">✅</div>
            <span class="step-count"><?= (int)$contentSummary['approved'] ?></span>
            <span class="step-label">Approved</span>
        </div>
        <div class="pipeline-arrow"></div>
        <div class="pipeline-step">
            <div class="step-icon">📅</div>
            <span class="step-count"><?= (int)$postSummary['scheduled'] ?></span>
            <span class="step-label">Scheduled</span>
        </div>
        <div class="pipeline-arrow"></div>
        <div class="pipeline-step">
            <div class="step-icon">🚀</div>
            <span class="step-count"><?= (int)$postSummary['success'] ?></span>
            <span class="step-label">Posted</span>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card accent">
        <div class="label">Tổng sản phẩm</div>
        <div class="value"><?= (int)$productSummary['total'] ?></div>
        <div class="sub"><?= (int)$productSummary['new'] ?> mới</div>
    </div>
    <div class="stat-card success">
        <div class="label">Đã có link</div>
        <div class="value"><?= (int)$productSummary['linked'] ?></div>
    </div>
    <div class="stat-card purple">
        <div class="label">Content ready</div>
        <div class="value"><?= (int)$productSummary['content_ready'] ?></div>
    </div>
    <div class="stat-card accent">
        <div class="label">Tổng links</div>
        <div class="value"><?= (int)$linkSummary['total'] ?></div>
        <div class="sub"><?= (int)$linkSummary['active'] ?> active</div>
    </div>
    <div class="stat-card warning">
        <div class="label">Drafts chờ duyệt</div>
        <div class="value"><?= (int)$contentSummary['draft'] ?></div>
    </div>
    <div class="stat-card success">
        <div class="label">Approved</div>
        <div class="value"><?= (int)$contentSummary['approved'] ?></div>
    </div>
    <div class="stat-card accent">
        <div class="label">Posts scheduled</div>
        <div class="value"><?= (int)$postSummary['scheduled'] ?></div>
    </div>
    <div class="stat-card success">
        <div class="label">Posts thành công</div>
        <div class="value"><?= (int)$postSummary['success'] ?></div>
    </div>
</div>

<!-- Quick Actions -->
<div class="grid-3">
    <div class="card">
        <div class="card-title">⚡ Tạo link hàng loạt</div>
        <form data-ajax method="POST" action="<?= url('/links/generate-all') ?>">
            <div class="form-group">
                <label class="form-label">Campaign code</label>
                <input class="form-control" name="campaign_code" value="MVP-LAPTOP">
            </div>
            <div class="form-group">
                <label class="form-label">Số lượng</label>
                <input class="form-control" name="limit" type="number" min="1" max="20" value="5">
            </div>
            <button type="submit" class="btn btn-accent">Tạo link</button>
        </form>
    </div>
    <div class="card">
        <div class="card-title">✨ Sinh content hàng loạt</div>
        <form data-ajax method="POST" action="<?= url('/contents/generate-all') ?>">
            <div class="form-group">
                <label class="form-label">Provider</label>
                <select class="form-control" name="provider">
                    <option value="template_engine">Template Engine</option>
                    <option value="openai">OpenAI</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Số lượng</label>
                <input class="form-control" name="limit" type="number" min="1" max="20" value="5">
            </div>
            <button type="submit" class="btn btn-purple">Sinh draft</button>
        </form>
    </div>
    <div class="card">
        <div class="card-title">📅 Schedule bài approved</div>
        <form data-ajax method="POST" action="<?= url('/posts/schedule-all') ?>">
            <div class="form-group">
                <label class="form-label">Channel</label>
                <select class="form-control" name="channel">
                    <option value="fanpage_manual">Fanpage Manual</option>
                    <option value="fanpage_api">Fanpage API</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Số bài</label>
                <input class="form-control" name="limit" type="number" min="1" max="20" value="5">
            </div>
            <button type="submit" class="btn btn-success">Schedule</button>
        </form>
    </div>
</div>

<!-- Recent Logs -->
<div class="card">
    <div class="card-title">
        <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:var(--accent);fill:none;stroke-width:2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Nhật ký gần đây
    </div>
    <?php if (empty($recentLogs)): ?>
        <div class="empty-state"><p>Chưa có log nào.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Task</th><th>Status</th><th>Thời gian</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($recentLogs, 0, 5) as $log): ?>
                    <tr>
                        <td>#<?= (int)$log['id'] ?></td>
                        <td>
                            <strong><?= e((string)$log['task_name']) ?></strong>
                            <?php if (!empty($log['error_message'])): ?>
                                <div class="sub text-danger"><?= e((string)$log['error_message']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= status_badge((string)$log['status']) ?></td>
                        <td class="text-muted text-sm"><?= e((string)$log['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
