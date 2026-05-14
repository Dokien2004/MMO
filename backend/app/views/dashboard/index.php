<div class="hero-card">
    <div class="hero-row">
        <div>
            <div class="page-kicker">Affiliate Pipeline</div>
            <h2 class="hero-title">Bảng điều khiển MMO</h2>
            <p class="hero-subtitle">Theo dõi toàn bộ luồng: cào sản phẩm, tạo link affiliate, viết content AI, gắn media và lên lịch đăng bài.</p>
        </div>
        <div class="hero-actions">
            <a class="btn btn-primary" href="<?= url('/scraper') ?>">Cào sản phẩm</a>
            <a class="btn btn-accent" href="<?= url('/contents') ?>">Xem content</a>
            <a class="btn btn-purple" href="<?= url('/posts') ?>">Lịch đăng</a>
        </div>
    </div>
</div>

<!-- Pipeline Visual -->
<div class="card mb-24">
    <div class="section-heading">
        <div class="card-title">
            <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            Luồng xử lý
        </div>
        <div class="section-note">Số liệu cập nhật theo DB hiện tại</div>
    </div>
    <div class="pipeline">
        <div class="pipeline-step">
            <div class="step-icon">SP</div>
            <span class="step-count"><?= (int)$productSummary['total'] ?></span>
            <span class="step-label">Sản phẩm</span>
        </div>
        <div class="pipeline-arrow"></div>
        <div class="pipeline-step">
            <div class="step-icon">LK</div>
            <span class="step-count"><?= (int)$linkSummary['total'] ?></span>
            <span class="step-label">Liên kết</span>
        </div>
        <div class="pipeline-arrow"></div>
        <div class="pipeline-step">
            <div class="step-icon">ND</div>
            <span class="step-count"><?= (int)$contentSummary['draft'] ?></span>
            <span class="step-label">Bản nháp</span>
        </div>
        <div class="pipeline-arrow"></div>
        <div class="pipeline-step">
            <div class="step-icon">OK</div>
            <span class="step-count"><?= (int)$contentSummary['approved'] ?></span>
            <span class="step-label">Đã duyệt</span>
        </div>
        <div class="pipeline-arrow"></div>
        <div class="pipeline-step">
            <div class="step-icon">LT</div>
            <span class="step-count"><?= (int)$postSummary['scheduled'] ?></span>
            <span class="step-label">Lên lịch</span>
        </div>
        <div class="pipeline-arrow"></div>
        <div class="pipeline-step">
            <div class="step-icon">GO</div>
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
    <div class="stat-card warning">
        <div class="label">Bán chạy theo ngưỡng</div>
        <div class="value"><?= (int)($productSummary['high_demand'] ?? 0) ?></div>
        <div class="sub">Sold ≥ <?= (int)($automationSettings['min_sold_count'] ?? 0) ?></div>
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
    <div class="stat-card purple">
        <div class="label">Lượt mua cao nhất</div>
        <div class="value"><?= number_format((int)($productSummary['max_sold_count'] ?? 0)) ?></div>
        <div class="sub">Theo dữ liệu đã sync</div>
    </div>
</div>

<!-- Quick Actions -->
<div class="grid-3">
    <div class="card">
        <div class="card-title">Tạo link affiliate thật</div>
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
        <div class="card-title">Viết content bằng AI</div>
        <form data-ajax method="POST" action="<?= url('/contents/generate-all') ?>">
            <div class="form-group">
                <label class="form-label">Nguồn sinh</label>
                <select class="form-control" name="provider">
                    <option value="template_engine">Mẫu có sẵn</option>
                    <option value="openai">OpenAI</option>
                    <option value="gemini">Gemini</option>
                    <option value="auto">Tự động fallback</option>
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
        <div class="card-title">Lên lịch kèm media</div>
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

<div class="grid-2">
    <div class="card">
        <div class="card-title">Auto-post hiện tại</div>
        <div class="status-stack">
            <div class="status-line"><span>Campaign mặc định</span><strong><?= e((string)$automationSettings['default_campaign_code']) ?></strong></div>
            <div class="status-line"><span>Provider</span><strong><?= e((string)$automationSettings['default_content_provider']) ?></strong></div>
            <div class="status-line"><span>Kênh đăng</span><strong><?= e((string)$automationSettings['default_channel']) ?></strong></div>
            <div class="status-line"><span>Tự publish</span><?= status_badge(!empty($automationSettings['auto_publish']) ? 'success' : 'failed') ?></div>
            <div class="status-line"><span>Fanpage API</span><?= status_badge(!empty($integrationStatus['fanpage_api_ready']) ? 'success' : 'failed') ?></div>
        </div>
        <a class="btn btn-ghost mt-16" href="<?= url('/settings') ?>">Mở cấu hình tự động hóa</a>
    </div>

    <div class="card">
        <div class="card-title">Sản phẩm có lượt mua cao</div>
        <?php if (empty($topSellingProducts)): ?>
            <div class="empty-state"><p>Chưa có sản phẩm nào đạt ngưỡng lượt mua đã cấu hình.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Sản phẩm</th><th>Lượt mua</th><th>Trạng thái</th></tr></thead>
                    <tbody>
                    <?php foreach ($topSellingProducts as $product): ?>
                        <tr>
                            <td>
                                <strong><?= e((string)$product['product_name']) ?></strong>
                                <div class="sub"><?= e((string)$product['source_platform']) ?> · <?= e((string)$product['source_product_id']) ?></div>
                            </td>
                            <td><span class="metric-pill"><?= number_format((int)($product['sold_count'] ?? 0)) ?></span></td>
                            <td><?= status_badge((string)($product['status'] ?? 'new')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
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
