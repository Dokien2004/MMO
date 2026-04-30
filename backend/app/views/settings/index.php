<div class="page-header">
    <div>
        <h2>Tự động hóa</h2>
        <p>Cấu hình đăng tự động và tiêu chí ưu tiên sản phẩm bán chạy</p>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-title">⚙️ Cấu hình đăng tự động</div>
        <form data-ajax method="POST" action="<?= url('/settings/automation') ?>">
            <div class="form-group">
                <label class="form-label">Mã chiến dịch mặc định</label>
                <input class="form-control" name="default_campaign_code" value="<?= e((string)$automationSettings['default_campaign_code']) ?>">
            </div>
            <div class="grid-2 compact-grid">
                <div class="form-group">
                    <label class="form-label">Nguồn nội dung</label>
                    <select class="form-control" name="default_content_provider">
                        <option value="template_engine" <?= $automationSettings['default_content_provider'] === 'template_engine' ? 'selected' : '' ?>>Mẫu có sẵn</option>
                        <option value="openai" <?= $automationSettings['default_content_provider'] === 'openai' ? 'selected' : '' ?>>OpenAI</option>
                        <option value="gemini" <?= $automationSettings['default_content_provider'] === 'gemini' ? 'selected' : '' ?>>Gemini</option>
                        <option value="auto" <?= $automationSettings['default_content_provider'] === 'auto' ? 'selected' : '' ?>>Tự động fallback</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Kênh đăng mặc định</label>
                    <select class="form-control" name="default_channel">
                        <option value="fanpage_manual" <?= $automationSettings['default_channel'] === 'fanpage_manual' ? 'selected' : '' ?>>Fanpage thủ công</option>
                        <option value="fanpage_api" <?= $automationSettings['default_channel'] === 'fanpage_api' ? 'selected' : '' ?>>Fanpage API</option>
                    </select>
                </div>
            </div>
            <div class="grid-2 compact-grid">
                <div class="form-group">
                    <label class="form-label">Số lượng mỗi đợt</label>
                    <input class="form-control" type="number" min="1" max="50" name="sync_limit" value="<?= (int)$automationSettings['sync_limit'] ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Chu kỳ publish (phút)</label>
                    <input class="form-control" type="number" min="5" max="1440" name="publish_interval_minutes" value="<?= (int)$automationSettings['publish_interval_minutes'] ?>">
                </div>
            </div>
            <div class="setting-switches">
                <input type="hidden" name="auto_approve" value="0">
                <label class="switch-row">
                    <input type="checkbox" name="auto_approve" value="1" <?= !empty($automationSettings['auto_approve']) ? 'checked' : '' ?>>
                    <span>Tự duyệt bản nháp sau khi sinh</span>
                </label>
                <input type="hidden" name="auto_schedule" value="0">
                <label class="switch-row">
                    <input type="checkbox" name="auto_schedule" value="1" <?= !empty($automationSettings['auto_schedule']) ? 'checked' : '' ?>>
                    <span>Tự lên lịch bài sau khi duyệt</span>
                </label>
                <input type="hidden" name="auto_publish" value="0">
                <label class="switch-row">
                    <input type="checkbox" name="auto_publish" value="1" <?= !empty($automationSettings['auto_publish']) ? 'checked' : '' ?>>
                    <span>Tự publish khi dùng `fanpage_api`</span>
                </label>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Lưu cấu hình</button>
        </form>
    </div>

    <div class="card">
        <div class="card-title">🔥 Tiêu chí lấy sản phẩm bán chạy</div>
        <form data-ajax method="POST" action="<?= url('/settings/automation') ?>">
            <input type="hidden" name="default_campaign_code" value="<?= e((string)$automationSettings['default_campaign_code']) ?>">
            <input type="hidden" name="default_content_provider" value="<?= e((string)$automationSettings['default_content_provider']) ?>">
            <input type="hidden" name="default_channel" value="<?= e((string)$automationSettings['default_channel']) ?>">
            <input type="hidden" name="sync_limit" value="<?= (int)$automationSettings['sync_limit'] ?>">
            <input type="hidden" name="publish_interval_minutes" value="<?= (int)$automationSettings['publish_interval_minutes'] ?>">
            <input type="hidden" name="auto_approve" value="<?= !empty($automationSettings['auto_approve']) ? '1' : '0' ?>">
            <input type="hidden" name="auto_schedule" value="<?= !empty($automationSettings['auto_schedule']) ? '1' : '0' ?>">
            <input type="hidden" name="auto_publish" value="<?= !empty($automationSettings['auto_publish']) ? '1' : '0' ?>">

            <div class="form-group">
                <label class="form-label">Ngưỡng lượt mua tối thiểu</label>
                <input class="form-control" type="number" min="0" name="min_sold_count" value="<?= (int)$automationSettings['min_sold_count'] ?>">
            </div>
            <input type="hidden" name="top_selling_only" value="0">
            <label class="switch-row">
                <input type="checkbox" name="top_selling_only" value="1" <?= !empty($automationSettings['top_selling_only']) ? 'checked' : '' ?>>
                <span>Chỉ lấy sản phẩm có lượt mua cao hơn ngưỡng</span>
            </label>
            <div class="hint-box mt-16">
                Worker sẽ ưu tiên sản phẩm có trường `sold_count`, `order_count` hoặc `sales_count`.
            </div>
            <button type="submit" class="btn btn-accent btn-full mt-16">Lưu tiêu chí lấy sản phẩm</button>
        </form>
    </div>
</div>


<div class="card mt-16">
    <div class="card-title">🔐 API & tài khoản tích hợp</div>
    <p class="sub mb-16">Điền key/token cần thiết để hệ thống tự sinh content và đăng Fanpage. Để trống nếu muốn giữ giá trị cũ; nhập <code>__CLEAR__</code> để xoá một secret.</p>
    <form data-ajax method="POST" action="<?= url('/settings/integrations') ?>">
        <div class="grid-2 compact-grid">
            <div class="form-group">
                <label class="form-label">Gemini API Key</label>
                <input class="form-control" type="password" name="GEMINI_API_KEY" placeholder="<?= e((string)($integrationConfig['GEMINI_API_KEY_MASKED'] ?? 'Chưa cấu hình')) ?>" autocomplete="off">
                <div class="sub">Trạng thái: <?= !empty($integrationConfig['GEMINI_API_KEY_SET']) ? 'đã lưu key' : 'chưa có key' ?></div>
            </div>
            <div class="form-group">
                <label class="form-label">Gemini Model</label>
                <input class="form-control" name="GEMINI_MODEL" value="<?= e((string)($integrationConfig['GEMINI_MODEL'] ?? 'gemini-1.5-flash')) ?>">
            </div>
        </div>

        <div class="grid-2 compact-grid">
            <div class="form-group">
                <label class="form-label">OpenAI API Key</label>
                <input class="form-control" type="password" name="OPENAI_API_KEY" placeholder="<?= e((string)($integrationConfig['OPENAI_API_KEY_MASKED'] ?? 'Chưa cấu hình')) ?>" autocomplete="off">
                <div class="sub">Trạng thái: <?= !empty($integrationConfig['OPENAI_API_KEY_SET']) ? 'đã lưu key' : 'chưa có key' ?></div>
            </div>
            <div class="form-group">
                <label class="form-label">OpenAI Model</label>
                <input class="form-control" name="OPENAI_MODEL" value="<?= e((string)($integrationConfig['OPENAI_MODEL'] ?? 'gpt-4o-mini')) ?>">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">OpenAI-compatible Base URL</label>
            <input class="form-control" name="OPENAI_BASE_URL" value="<?= e((string)($integrationConfig['OPENAI_BASE_URL'] ?? 'https://api.openai.com/v1')) ?>" placeholder="http://127.0.0.1:20128/v1">
            <div class="sub">Dùng để trỏ sang 9router/local model router. Ví dụ: <code>http://127.0.0.1:20128/v1</code></div>
        </div>

        <div class="grid-2 compact-grid">
            <div class="form-group">
                <label class="form-label">Facebook Page ID</label>
                <input class="form-control" name="FACEBOOK_PAGE_ID" value="<?= e((string)($integrationConfig['FACEBOOK_PAGE_ID'] ?? '')) ?>" autocomplete="off">
            </div>
            <div class="form-group">
                <label class="form-label">Facebook Page Access Token</label>
                <input class="form-control" type="password" name="FACEBOOK_PAGE_ACCESS_TOKEN" placeholder="<?= e((string)($integrationConfig['FACEBOOK_PAGE_ACCESS_TOKEN_MASKED'] ?? 'Chưa cấu hình')) ?>" autocomplete="off">
                <div class="sub">Trạng thái: <?= !empty($integrationConfig['FACEBOOK_PAGE_ACCESS_TOKEN_SET']) ? 'đã lưu token' : 'chưa có token' ?></div>
            </div>
        </div>

        <div class="hint-box mt-16">
            Secrets được lưu vào <code>backend/app/config/local.php</code> trên server, file này đã bị ignore và không push lên Git.
        </div>
        <button type="submit" class="btn btn-primary btn-full mt-16">Lưu API & tài khoản</button>
    </form>
</div>


<div class="grid-2">
    <div class="card">
        <div class="card-title">🔌 Trạng thái tích hợp</div>
        <div class="status-stack">
            <div class="status-line">
                <span>OpenAI-compatible / 9router</span>
                <?= status_badge(!empty($integrationStatus['openai_configured']) ? 'success' : 'none') ?>
            </div>
            <?php if (!empty($integrationStatus['openai_router_configured'])): ?>
                <div class="sub">Đang dùng router: <?= e(openai_base_url()) ?> · model <?= e(openai_model()) ?></div>
            <?php endif; ?>
            <div class="status-line">
                <span>Gemini API / 9router</span>
                <?= status_badge(!empty($integrationStatus['gemini_configured']) ? 'success' : 'none') ?>
            </div>
            <?php if (!empty($integrationStatus['gemini_router_configured'])): ?>
                <div class="sub">Đang dùng Gemini qua 9router: <?= e(openai_model()) ?></div>
            <?php endif; ?>
            <div class="status-line">
                <span>Facebook Page ID</span>
                <?= status_badge(!empty($integrationStatus['facebook_page_id_configured']) ? 'success' : 'failed') ?>
            </div>
            <div class="status-line">
                <span>Facebook Access Token</span>
                <?= status_badge(!empty($integrationStatus['facebook_access_token_configured']) ? 'success' : 'failed') ?>
            </div>
            <div class="status-line">
                <span>Fanpage API sẵn sàng</span>
                <?= status_badge(!empty($integrationStatus['fanpage_api_ready']) ? 'success' : 'failed') ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-title">🏆 Top sản phẩm theo lượt mua</div>
        <?php if (empty($topSellingProducts)): ?>
            <div class="empty-state"><p>Chưa có dữ liệu `sold_count` để xếp hạng.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Sản phẩm</th><th>Lượt mua</th><th>Giá</th></tr></thead>
                    <tbody>
                    <?php foreach ($topSellingProducts as $product): ?>
                        <tr>
                            <td>
                                <strong><?= e((string)$product['product_name']) ?></strong>
                                <div class="sub"><?= e((string)$product['source_platform']) ?> · <?= e((string)$product['source_product_id']) ?></div>
                            </td>
                            <td><span class="metric-pill"><?= number_format((int)($product['sold_count'] ?? 0)) ?></span></td>
                            <td><?= number_format((float)($product['price'] ?? 0), 0, ',', '.') ?> ₫</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
