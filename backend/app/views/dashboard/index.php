<div class="page-header">
    <div>
        <h2>Bảng điều khiển</h2>
        <p>Tổng quan pipeline affiliate MVP</p>
    </div>
</div>

<!-- Pipeline Visual -->
<div class="card mb-24">
    <div class="card-title">
        <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Luồng xử lý
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
            <span class="step-label">Liên kết</span>
        </div>
        <div class="pipeline-arrow"></div>
        <div class="pipeline-step">
            <div class="step-icon">📝</div>
            <span class="step-count"><?= (int)$contentSummary['draft'] ?></span>
            <span class="step-label">Bản nháp</span>
        </div>
        <div class="pipeline-arrow"></div>
        <div class="pipeline-step">
            <div class="step-icon">✅</div>
            <span class="step-count"><?= (int)$contentSummary['approved'] ?></span>
            <span class="step-label">Đã duyệt</span>
        </div>
        <div class="pipeline-arrow"></div>
        <div class="pipeline-step">
            <div class="step-icon">📅</div>
            <span class="step-count"><?= (int)$postSummary['scheduled'] ?></span>
            <span class="step-label">Lên lịch</span>
        </div>
        <div class="pipeline-arrow"></div>
        <div class="pipeline-step">
            <div class="step-icon">🚀</div>
            <span class="step-count"><?= (int)$postSummary['success'] ?></span>
            <span class="step-label">Đã đăng</span>
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
        <div class="label">Có nội dung</div>
        <div class="value"><?= (int)$productSummary['content_ready'] ?></div>
    </div>
    <div class="stat-card accent">
        <div class="label">Tổng liên kết</div>
        <div class="value"><?= (int)$linkSummary['total'] ?></div>
        <div class="sub"><?= (int)$linkSummary['active'] ?> hoạt động</div>
    </div>
    <div class="stat-card warning">
        <div class="label">Nháp chờ duyệt</div>
        <div class="value"><?= (int)$contentSummary['draft'] ?></div>
    </div>
    <div class="stat-card success">
        <div class="label">Đã duyệt</div>
        <div class="value"><?= (int)$contentSummary['approved'] ?></div>
    </div>
    <div class="stat-card accent">
        <div class="label">Đã lên lịch</div>
        <div class="value"><?= (int)$postSummary['scheduled'] ?></div>
    </div>
    <div class="stat-card success">
        <div class="label">Đăng thành công</div>
        <div class="value"><?= (int)$postSummary['success'] ?></div>
    </div>
</div>

<!-- Quick Actions -->
<div class="grid-3">
    <div class="card">
        <div class="card-title">⚡ Tạo liên kết hàng loạt</div>
        <form data-ajax method="POST" action="<?= url('/links/generate-all') ?>">
            <div class="form-group">
                <label class="form-label">Mã chiến dịch</label>
                <input class="form-control" name="campaign_code" value="MVP-LAPTOP">
            </div>
            <div class="form-group">
                <label class="form-label">Số lượng</label>
                <input class="form-control" name="limit" type="number" min="1" max="20" value="5">
            </div>
            <button type="submit" class="btn btn-accent btn-full">Tạo liên kết</button>
        </form>
    </div>
    <div class="card">
        <div class="card-title">✨ Sinh nội dung hàng loạt</div>
        <form data-ajax method="POST" action="<?= url('/contents/generate-all') ?>">
            <div class="form-group">
                <label class="form-label">Nguồn sinh</label>
                <select class="form-control" name="provider">
                    <option value="template_engine">Mẫu có sẵn</option>
                    <option value="openai">OpenAI</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Số lượng</label>
                <input class="form-control" name="limit" type="number" min="1" max="20" value="5">
            </div>
            <button type="submit" class="btn btn-purple btn-full">Sinh bản nháp</button>
        </form>
    </div>
    <div class="card">
        <div class="card-title">📅 Lên lịch đăng bài</div>
        <form data-ajax method="POST" action="<?= url('/posts/schedule-all') ?>">
            <div class="form-group">
                <label class="form-label">Kênh đăng</label>
                <select class="form-control" name="channel">
                    <option value="fanpage_manual">Fanpage (thủ công)</option>
                    <option value="fanpage_api">Fanpage (API tự động)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Số bài</label>
                <input class="form-control" name="limit" type="number" min="1" max="20" value="5">
            </div>
            <button type="submit" class="btn btn-success btn-full">Lên lịch</button>
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
        <div class="empty-state"><p>Chưa có nhật ký nào.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Mã</th><th>Tác vụ</th><th>Trạng thái</th><th>Thời gian</th></tr></thead>
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
