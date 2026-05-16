<div class="page-header">
    <div>
        <div class="page-kicker">Automation Settings</div>
        <h2>Tự động hóa</h2>
        <p>Cấu hình AI, 9router, Fanpage và tiêu chí ưu tiên sản phẩm bán chạy.</p>
    </div>
</div>

<div class="action-strip">
    <div>
        <strong>Trạng thái nhanh</strong>
        <div class="sub">9router/OpenAI-compatible: <?= !empty($integrationStatus['openai_configured']) ? 'đã cấu hình' : 'chưa cấu hình' ?> · Ảnh AI: <?= !empty($integrationStatus['image_api_configured']) ? 'đã cấu hình' : 'chưa cấu hình' ?> · Video: <?= !empty($integrationStatus['video_configured']) ? 'sẵn sàng' : 'chưa cấu hình' ?> · Fanpage API: <?= !empty($integrationStatus['fanpage_api_ready']) ? 'sẵn sàng' : 'chưa sẵn sàng' ?></div>
    </div>
    <div class="btn-group">
        <a class="btn btn-accent" href="<?= url('/contents') ?>">Content</a>
        <a class="btn btn-success" href="<?= url('/posts') ?>">Đăng bài</a>
    </div>
</div>

<style>
.settings-tabs {
    display: flex;
    gap: 16px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 24px;
}
.settings-tab {
    padding: 8px 16px 12px;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    font-weight: 500;
    color: var(--text-muted);
    transition: all 0.2s;
}
.settings-tab:hover {
    color: var(--text-main);
}
.settings-tab.active {
    color: var(--accent);
    border-bottom-color: var(--accent);
}
.tab-pane {
    display: none;
    animation: fadeIn 0.3s ease;
}
.tab-pane.active {
    display: block;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="settings-tabs">
    <div class="settings-tab active" onclick="switchTab('tab-automation', this)">Tự động hóa & Rules</div>
    <div class="settings-tab" onclick="switchTab('tab-integrations', this)">API Keys & Tích hợp</div>
    <div class="settings-tab" onclick="switchTab('tab-status', this)">Trạng thái hệ thống</div>
</div>

<div id="tab-automation" class="tab-pane active">
    <div class="grid-2">
        <div class="card">
            <div class="card-title">Cấu hình đăng tự động</div>
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
            <div class="card-title">Tiêu chí lấy sản phẩm bán chạy</div>
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
</div>

<div id="tab-integrations" class="tab-pane">
    <div class="card">
        <div class="card-title">API & tài khoản tích hợp</div>
        <p class="sub mb-16">Điền key/token cần thiết để hệ thống tự sinh content và đăng Fanpage. Để trống nếu muốn giữ giá trị cũ; nhập <code>__CLEAR__</code> để xoá một secret.</p>
        <form data-ajax method="POST" action="<?= url('/settings/integrations') ?>">
            <!-- Bắt đầu phần form inputs integrations -->
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
                    <div class="sub">Model chính, ví dụ <code>cx/gpt-5.5</code>.</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">OpenAI-compatible Fallback Models</label>
                <input class="form-control" name="OPENAI_FALLBACK_MODELS" value="<?= e((string)($integrationConfig['OPENAI_FALLBACK_MODELS'] ?? 'minimax/MiniMax-M2.7,if/iflow-rome-30ba3b,gemini/gemini-3-flash-preview')) ?>" placeholder="minimax/MiniMax-M2.7,if/iflow-rome-30ba3b,gemini/gemini-3-flash-preview">
                <div class="sub">Khi model chính chết/token hết hạn, hệ thống thử lần lượt các model này qua router/base URL bên dưới.</div>
            </div>

            <div class="form-group">
                <label class="form-label">OpenAI-compatible Base URL</label>
                <input class="form-control" name="OPENAI_BASE_URL" value="<?= e((string)($integrationConfig['OPENAI_BASE_URL'] ?? 'https://api.openai.com/v1')) ?>" placeholder="http://127.0.0.1:20128/v1">
                <div class="sub">Dùng để trỏ sang 9router/local model router. Ví dụ: <code>http://127.0.0.1:20128/v1</code></div>
            </div>

            <div class="grid-2 compact-grid">
                <div class="form-group">
                    <label class="form-label">Image OpenAI API Key</label>
                    <input class="form-control" type="password" name="IMAGE_OPENAI_API_KEY" placeholder="<?= e((string)($integrationConfig['IMAGE_OPENAI_API_KEY_MASKED'] ?? 'Để trống nếu dùng 9router local')) ?>" autocomplete="off">
                    <div class="sub">Trạng thái: <?= !empty($integrationConfig['IMAGE_OPENAI_API_KEY_SET']) || !empty($integrationConfig['OPENAI_API_KEY_SET']) ? 'có key để tạo ảnh' : 'chưa có key tạo ảnh' ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Image Model</label>
                    <input class="form-control" name="IMAGE_MODEL" value="<?= e((string)($integrationConfig['IMAGE_MODEL'] ?? 'gemini/gemini-2.5-flash-image')) ?>">
                    <div class="sub">Model chính. Nếu bị quota/429 sẽ thử model fallback bên dưới.</div>
                </div>
            </div>

            <div class="grid-2 compact-grid">
                <div class="form-group">
                    <label class="form-label">Image Fallback Model</label>
                    <input class="form-control" name="IMAGE_FALLBACK_MODEL" value="<?= e((string)($integrationConfig['IMAGE_FALLBACK_MODEL'] ?? 'minimax/minimax-image-01,cx/gpt-5.4-image')) ?>" placeholder="minimax/minimax-image-01,cx/gpt-5.4-image">
                    <div class="sub">Dùng khi model chính hết quota/lỗi rate limit. Có thể nhập nhiều model, phân tách bằng dấu phẩy.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Image Base URL</label>
                    <input class="form-control" name="IMAGE_BASE_URL" value="<?= e((string)($integrationConfig['IMAGE_BASE_URL'] ?? 'http://127.0.0.1:20128/v1')) ?>">
                </div>
            </div>

            <div class="grid-2 compact-grid">
                <div class="form-group">
                    <label class="form-label">Image Size</label>
                    <input class="form-control" name="IMAGE_SIZE" value="<?= e((string)($integrationConfig['IMAGE_SIZE'] ?? '1024x1024')) ?>" placeholder="1024x1024">
                </div>
                <div class="form-group">
                    <label class="form-label">Image Provider</label>
                    <select class="form-control" name="IMAGE_PROVIDER">
                        <option value="direct" <?= ($integrationConfig['IMAGE_PROVIDER'] ?? 'direct') === 'direct' ? 'selected' : '' ?>>Direct 9router/OpenAI-compatible</option>
                        <option value="meigen" <?= ($integrationConfig['IMAGE_PROVIDER'] ?? 'direct') === 'meigen' ? 'selected' : '' ?>>MeiGen API rồi fallback direct</option>
                    </select>
                    <div class="sub">Chọn MeiGen để dùng REST API/generative workflow; nếu lỗi sẽ tự fallback về cấu hình direct.</div>
                </div>
            </div>

            <div class="grid-2 compact-grid">
                <div class="form-group">
                    <label class="form-label">MeiGen API Token</label>
                    <input class="form-control" type="password" name="MEIGEN_API_TOKEN" placeholder="<?= e((string)($integrationConfig['MEIGEN_API_TOKEN_MASKED'] ?? 'Chưa cấu hình')) ?>" autocomplete="off">
                    <div class="sub">Trạng thái: <?= !empty($integrationConfig['MEIGEN_API_TOKEN_SET']) ? 'đã lưu token' : 'chưa có token' ?>. API token cần purchased credits.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">MeiGen Base URL</label>
                    <input class="form-control" name="MEIGEN_BASE_URL" value="<?= e((string)($integrationConfig['MEIGEN_BASE_URL'] ?? 'https://www.meigen.ai/api')) ?>">
                </div>
            </div>

            <div class="grid-2 compact-grid">
                <div class="form-group">
                    <label class="form-label">MeiGen Model</label>
                    <input class="form-control" name="MEIGEN_MODEL" value="<?= e((string)($integrationConfig['MEIGEN_MODEL'] ?? 'gpt-image-2')) ?>" placeholder="gpt-image-2">
                    <div class="sub">Ví dụ: <code>gpt-image-2</code>, <code>nanobanana-2</code>, <code>seedream-5.0-lite</code>, <code>z-image-turbo</code>.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">MeiGen Aspect Ratio</label>
                    <input class="form-control" name="MEIGEN_ASPECT_RATIO" value="<?= e((string)($integrationConfig['MEIGEN_ASPECT_RATIO'] ?? 'auto')) ?>" placeholder="auto">
                </div>
            </div>

            <div class="grid-2 compact-grid">
                <div class="form-group">
                    <label class="form-label">MeiGen Resolution</label>
                    <input class="form-control" name="MEIGEN_RESOLUTION" value="<?= e((string)($integrationConfig['MEIGEN_RESOLUTION'] ?? '1K')) ?>" placeholder="1K">
                </div>
                <div class="form-group">
                    <label class="form-label">MeiGen Quality</label>
                    <input class="form-control" name="MEIGEN_QUALITY" value="<?= e((string)($integrationConfig['MEIGEN_QUALITY'] ?? 'low')) ?>" placeholder="low">
                    <div class="sub">Chỉ áp dụng cho GPT Image 2: <code>low</code>, <code>medium</code>, <code>high</code>.</div>
                </div>
            </div>

            <div class="grid-2 compact-grid">
                <div class="form-group">
                    <label class="form-label">Video Provider</label>
                    <select class="form-control" name="VIDEO_PROVIDER">
                        <option value="local" <?= ($integrationConfig['VIDEO_PROVIDER'] ?? 'local') === 'local' ? 'selected' : '' ?>>Local FFmpeg promo video</option>
                        <option value="meigen" <?= ($integrationConfig['VIDEO_PROVIDER'] ?? 'local') === 'meigen' ? 'selected' : '' ?>>MeiGen AI video</option>
                        <option value="kling" <?= ($integrationConfig['VIDEO_PROVIDER'] ?? 'local') === 'kling' ? 'selected' : '' ?>>Kling AI video</option>
                        <option value="direct" <?= ($integrationConfig['VIDEO_PROVIDER'] ?? 'local') === 'direct' ? 'selected' : '' ?>>AI video API / router</option>
                    </select>
                    <div class="sub">MVP hiện dùng local FFmpeg để tạo video dọc nhanh, không tốn token.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Video Model</label>
                    <input class="form-control" name="VIDEO_MODEL" value="<?= e((string)($integrationConfig['VIDEO_MODEL'] ?? 'seedance-2-0')) ?>" placeholder="seedance-2-0">
                </div>
            </div>

            <div class="grid-2 compact-grid">
                <div class="form-group">
                    <label class="form-label">Video Size</label>
                    <input class="form-control" name="VIDEO_SIZE" value="<?= e((string)($integrationConfig['VIDEO_SIZE'] ?? '720x1280')) ?>" placeholder="720x1280">
                    <div class="sub">Khuyến nghị video dọc: <code>720x1280</code> hoặc <code>1080x1920</code>.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Video Duration Seconds</label>
                    <input class="form-control" type="number" min="4" max="30" name="VIDEO_DURATION_SECONDS" value="<?= e((string)($integrationConfig['VIDEO_DURATION_SECONDS'] ?? '8')) ?>">
                </div>
            </div>

            <div class="grid-2 compact-grid">
                <div class="form-group">
                    <label class="form-label">Video Aspect Ratio</label>
                    <input class="form-control" name="VIDEO_ASPECT_RATIO" value="<?= e((string)($integrationConfig['VIDEO_ASPECT_RATIO'] ?? '9:16')) ?>" placeholder="9:16">
                </div>
                <div class="form-group">
                    <label class="form-label">Video Resolution</label>
                    <input class="form-control" name="VIDEO_RESOLUTION" value="<?= e((string)($integrationConfig['VIDEO_RESOLUTION'] ?? '720p')) ?>" placeholder="720p">
                </div>
            </div>

            <div class="grid-2 compact-grid">
                <div class="form-group">
                    <label class="form-label">Video API Key</label>
                    <input class="form-control" type="password" name="VIDEO_API_KEY" placeholder="<?= e((string)($integrationConfig['VIDEO_API_KEY_MASKED'] ?? 'Chưa cấu hình')) ?>" autocomplete="off">
                    <div class="sub">Dành cho provider AI video riêng. Nếu dùng MeiGen thì dùng <b>MeiGen API Token</b>; nếu dùng Kling thì dùng Access/Secret bên dưới.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Video Base URL</label>
                    <input class="form-control" name="VIDEO_BASE_URL" value="<?= e((string)($integrationConfig['VIDEO_BASE_URL'] ?? '')) ?>" placeholder="https://.../v1">
                </div>
            </div>

            <div class="grid-2 compact-grid">
                <div class="form-group">
                    <label class="form-label">Kling Access Key</label>
                    <input class="form-control" type="password" name="KLING_ACCESS_KEY" placeholder="<?= e((string)($integrationConfig['KLING_ACCESS_KEY_MASKED'] ?? 'Chưa cấu hình')) ?>" autocomplete="off">
                    <div class="sub">Trạng thái: <?= !empty($integrationConfig['KLING_ACCESS_KEY_SET']) ? 'đã lưu access key' : 'chưa có access key' ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Kling Secret Key</label>
                    <input class="form-control" type="password" name="KLING_SECRET_KEY" placeholder="<?= e((string)($integrationConfig['KLING_SECRET_KEY_MASKED'] ?? 'Chưa cấu hình')) ?>" autocomplete="off">
                    <div class="sub">Trạng thái: <?= !empty($integrationConfig['KLING_SECRET_KEY_SET']) ? 'đã lưu secret key' : 'chưa có secret key' ?></div>
                </div>
            </div>

            <div class="grid-2 compact-grid">
                <div class="form-group">
                    <label class="form-label">Kling Base URL</label>
                    <input class="form-control" name="KLING_BASE_URL" value="<?= e((string)($integrationConfig['KLING_BASE_URL'] ?? 'https://api.klingai.com')) ?>" placeholder="https://api.klingai.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Kling Model / Mode</label>
                    <div class="grid-2 compact-grid">
                        <input class="form-control" name="KLING_MODEL" value="<?= e((string)($integrationConfig['KLING_MODEL'] ?? 'kling-v1-6')) ?>" placeholder="kling-v1-6">
                        <select class="form-control" name="KLING_MODE">
                            <option value="std" <?= ($integrationConfig['KLING_MODE'] ?? 'std') === 'std' ? 'selected' : '' ?>>std</option>
                            <option value="pro" <?= ($integrationConfig['KLING_MODE'] ?? 'std') === 'pro' ? 'selected' : '' ?>>pro</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="grid-2 compact-grid">
                <div class="form-group">
                    <label class="form-label">Facebook Page ID</label>
                    <input class="form-control" name="FACEBOOK_PAGE_ID" value="<?= e((string)($integrationConfig['FACEBOOK_PAGE_ID'] ?? '')) ?>" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="form-label">Facebook Page Access Token</label>
                    <input class="form-control" type="password" name="FACEBOOK_PAGE_ACCESS_TOKEN" placeholder="<?= e((string)($integrationConfig['FACEBOOK_PAGE_ACCESS_TOKEN_MASKED'] ?? 'Chưa cấu hình')) ?>" autocomplete="off">
                    <div class="sub">Trạng thái: <?= !empty($integrationConfig['FACEBOOK_PAGE_ACCESS_TOKEN_SET']) ? 'đã lưu token' : 'chưa có token' ?>. Cần lấy token Page từ: <code>/me/accounts → Page Ép Phê → access_token</code>. Không dán User Token/App Token.</div>
                </div>
            </div>
            <div class="action-strip mt-16" style="background:var(--bg-hover); padding:12px; border-radius:8px;">
                <div>
                    <strong>Kiểm tra Facebook Token</strong>
                    <div class="sub">Bấm để kiểm tra token còn hạn, đúng Page Token của Ép Phê và có quyền đăng bài hay không.</div>
                </div>
                <button type="button" class="btn btn-success" data-post-action="<?= url('/settings/check-facebook-token') ?>">Kiểm tra token</button>
            </div>

            <div class="grid-2 compact-grid">
                <div class="form-group">
                    <label class="form-label">Shopee Affiliate ID</label>
                    <input class="form-control" name="SHOPEE_AFFILIATE_ID" value="<?= e((string)($integrationConfig['SHOPEE_AFFILIATE_ID'] ?? '')) ?>" autocomplete="off">
                    <div class="sub">Dùng để tạo link Shopee affiliate thật.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Product Import Token</label>
                    <input class="form-control" type="password" name="PRODUCT_IMPORT_TOKEN" placeholder="<?= e((string)($integrationConfig['PRODUCT_IMPORT_TOKEN_MASKED'] ?? 'Chưa cấu hình')) ?>" autocomplete="off">
                    <div class="sub">Token bảo vệ API import sản phẩm ngoài hệ thống.</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Public App URL</label>
                <input class="form-control" name="APP_PUBLIC_URL" value="<?= e((string)($integrationConfig['APP_PUBLIC_URL'] ?? 'https://mmo.sys-erp.id.vn')) ?>" placeholder="https://mmo.sys-erp.id.vn">
                <div class="sub">Dùng để Facebook tải ảnh/media khi publish bài.</div>
            </div>

            <div class="hint-box mt-16">
                Secrets/API key được lưu trong DB theo từng site (<code>site_integration_configs</code>). <code>local.php</code> chỉ còn làm fallback/cấu hình gốc.
            </div>
            <button type="submit" class="btn btn-primary btn-full mt-16">Lưu API & tài khoản</button>
            <!-- Kết thúc phần form inputs integrations -->
        </form>
    </div>
</div>

<div id="tab-status" class="tab-pane">
    <div class="grid-2">
        <div class="card">
            <div class="card-title">Trạng thái tích hợp</div>
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
                    <span>AI tạo ảnh</span>
                    <?= status_badge(!empty($integrationStatus['image_api_configured']) ? 'success' : 'failed') ?>
                </div>
                <div class="sub">Provider: <?= e(image_provider()) ?> · direct model <?= e(image_model()) ?> · fallback <?= e(image_fallback_model() !== '' ? image_fallback_model() : 'tắt') ?> · size <?= e(image_size()) ?></div>
                <div class="status-line">
                    <span>Tạo video sản phẩm</span>
                    <?= status_badge(video_provider() === 'local' || video_api_key() !== '' ? 'success' : 'none') ?>
                </div>
                <div class="sub">Provider: <?= e(video_provider()) ?> · model <?= e(video_model()) ?> · Kling <?= e(kling_model()) ?>/<?= e(kling_mode()) ?> · ratio <?= e(video_aspect_ratio()) ?> · resolution <?= e(video_resolution()) ?> · size <?= e(video_size()) ?> · duration <?= (int)video_duration_seconds() ?>s</div>
                <div class="status-line">
                    <span>MeiGen API</span>
                    <?= status_badge(!empty($integrationStatus['meigen_configured']) ? 'success' : 'none') ?>
                </div>
                <div class="sub">Model MeiGen: <?= e(meigen_model()) ?> · ratio <?= e(meigen_aspect_ratio()) ?> · resolution <?= e(meigen_resolution()) ?> · quality <?= e(meigen_quality()) ?></div>
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
                <div class="status-line">
                    <span>Telegram thông báo job nền</span>
                    <?= status_badge(!empty($integrationStatus['telegram_configured']) ? 'success' : 'failed') ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Top sản phẩm theo lượt mua</div>
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
</div>

<script>
function switchTab(tabId, el) {
    document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
    document.querySelectorAll('.settings-tab').forEach(tab => tab.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    if (el) el.classList.add('active');
}
// Nếu trên URL có param tab, tự động chuyển tab
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    if (tab && document.getElementById('tab-' + tab)) {
        document.querySelectorAll('.settings-tab').forEach(t => {
            if (t.getAttribute('onclick').includes(tab)) t.click();
        });
    }
});
</script>
