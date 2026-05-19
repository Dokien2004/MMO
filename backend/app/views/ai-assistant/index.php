<?php
/** @var array $integrationStatus */

$skillCategories = [
    'content' => [
        ['key' => 'copywriting', 'label' => '📝 Copywriting', 'desc' => 'AIDA, PAS, FAB'],
        ['key' => 'article-writing', 'label' => '📄 Bài viết', 'desc' => 'Blog, Tutorial'],
        ['key' => 'social-media-content', 'label' => '📱 Social Post', 'desc' => 'FB, TikTok, IG'],
        ['key' => 'product-description', 'label' => '🏷️ Mô tả SP', 'desc' => 'SEO product copy'],
    ],
    'seo' => [
        ['key' => 'seo-keyword-researcher', 'label' => '🔍 SEO Keywords', 'desc' => 'Nghiên cứu từ khóa'],
        ['key' => 'prompt-engineering', 'label' => '⚙️ Prompt Engineer', 'desc' => 'Tối ưu prompt'],
    ],
    'tools' => [
        ['key' => 'web-scraping', 'label' => '🕷️ Web Scraping', 'desc' => 'Cào dữ liệu'],
        ['key' => 'affiliate-link-injector', 'label' => '🔗 Chèn Affiliate', 'desc' => 'Gắn link tự động'],
    ],
];
?>

<style>
.ai-container {
    display: grid;
    grid-template-columns: 280px 1fr 320px;
    gap: 16px;
    height: calc(100vh - 140px);
    min-height: 500px;
}

.ai-sidebar {
    background: var(--bg-elevated);
    border-radius: 12px;
    border: 1px solid var(--border);
    padding: 16px;
    overflow-y: auto;
}

.ai-sidebar h3 {
    font-size: 13px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 12px;
    margin-top: 0;
}

.skill-category {
    margin-bottom: 20px;
}

.skill-category-title {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 8px;
}

.skill-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 8px 10px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--bg);
    color: var(--text);
    cursor: pointer;
    font-size: 13px;
    text-align: left;
    transition: all 0.15s;
    margin-bottom: 4px;
}

.skill-btn:hover {
    border-color: var(--accent);
    background: var(--bg-hover);
}

.skill-btn.active {
    border-color: var(--accent);
    background: var(--accent-bg);
    color: var(--accent);
}

.skill-btn-label {
    font-weight: 500;
    flex: 1;
}

.skill-btn-desc {
    font-size: 11px;
    color: var(--text-muted);
}

.ai-chat-panel {
    background: var(--bg-elevated);
    border-radius: 12px;
    border: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.chat-header {
    padding: 14px 18px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
}

.chat-header-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), var(--accent-secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.chat-header-info h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
}

.chat-header-info p {
    margin: 0;
    font-size: 11px;
    color: var(--text-muted);
}

.ai-status-badge {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    color: var(--text-muted);
}

.status-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #22c55e;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.msg {
    display: flex;
    gap: 10px;
    max-width: 90%;
}

.msg.user {
    align-self: flex-end;
    flex-direction: row-reverse;
}

.msg.assistant {
    align-self: flex-start;
}

.msg-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    flex-shrink: 0;
    color: #fff;
}

.msg.user .msg-avatar {
    background: var(--bg-hover);
    color: var(--text-muted);
}

.msg-bubble {
    padding: 10px 14px;
    border-radius: 14px;
    font-size: 13px;
    line-height: 1.5;
    background: var(--bg);
    border: 1px solid var(--border);
}

.msg.user .msg-bubble {
    background: var(--accent);
    border-color: var(--accent);
    color: #fff;
}

.msg.assistant .msg-bubble {
    background: var(--accent-bg);
}

.chat-input-area {
    padding: 12px 16px;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 8px;
    align-items: flex-end;
}

.chat-input {
    flex: 1;
    padding: 10px 14px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: var(--bg);
    color: var(--text);
    font-size: 13px;
    resize: none;
    min-height: 42px;
    max-height: 120px;
    font-family: inherit;
}

.chat-input:focus {
    outline: none;
    border-color: var(--accent);
}

.chat-send-btn {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: var(--accent);
    border: none;
    color: #fff;
    font-size: 18px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background 0.15s;
}

.chat-send-btn:hover {
    background: var(--accent-hover);
}

.chat-send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Quick Actions Panel */
.ai-actions-panel {
    background: var(--bg-elevated);
    border-radius: 12px;
    border: 1px solid var(--border);
    padding: 16px;
    overflow-y: auto;
}

.panel-title {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.form-group {
    margin-bottom: 12px;
}

.form-label {
    display: block;
    font-size: 12px;
    color: var(--text-muted);
    margin-bottom: 5px;
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 8px 10px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--bg);
    color: var(--text);
    font-size: 13px;
    box-sizing: border-box;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent);
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 13px;
    cursor: pointer;
    border: none;
    transition: all 0.15s;
    font-family: inherit;
}

.btn-primary {
    background: var(--accent);
    color: #fff;
}

.btn-primary:hover {
    background: var(--accent-hover);
}

.btn-secondary {
    background: var(--bg-hover);
    color: var(--text);
}

.btn-secondary:hover {
    background: var(--border);
}

.btn-block {
    width: 100%;
}

.generate-result {
    margin-top: 14px;
    padding: 12px;
    border-radius: 10px;
    background: var(--bg);
    border: 1px solid var(--border);
    font-size: 13px;
    line-height: 1.5;
    white-space: pre-wrap;
    max-height: 200px;
    overflow-y: auto;
}

.generate-result:empty {
    display: none;
}

.skill-active-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 6px;
    background: var(--accent-bg);
    border: 1px solid var(--accent);
    font-size: 11px;
    color: var(--accent);
    margin-bottom: 10px;
}

.quick-starters {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 14px;
}

.quick-starter {
    padding: 5px 10px;
    border-radius: 6px;
    background: var(--bg);
    border: 1px solid var(--border);
    font-size: 11px;
    color: var(--text-sec);
    cursor: pointer;
    transition: all 0.15s;
}

.quick-starter:hover {
    border-color: var(--accent);
    color: var(--accent);
}

@media (max-width: 1024px) {
    .ai-container {
        grid-template-columns: 1fr;
        height: auto;
    }
    .ai-sidebar { display: none; }
    .ai-actions-panel { display: none; }
}
</style>

<div class="page-header" style="margin-bottom:16px">
    <div>
        <div class="page-kicker">🤖 MMO AI</div>
        <h2>Trợ lý AI</h2>
    </div>
</div>

<div class="ai-container">
    <!-- LEFT: Skill Sidebar -->
    <div class="ai-sidebar">
        <h3>📚 Skills</h3>

        <div class="skill-category">
            <div class="skill-category-title">Viết Content</div>
            <?php foreach ($skillCategories['content'] as $s): ?>
                <button class="skill-btn" data-skill="<?= e($s['key']) ?>">
                    <span class="skill-btn-label"><?= $s['label'] ?></span>
                    <span class="skill-btn-desc"><?= e($s['desc']) ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="skill-category">
            <div class="skill-category-title">SEO & Prompt</div>
            <?php foreach ($skillCategories['seo'] as $s): ?>
                <button class="skill-btn" data-skill="<?= e($s['key']) ?>">
                    <span class="skill-btn-label"><?= $s['label'] ?></span>
                    <span class="skill-btn-desc"><?= e($s['desc']) ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="skill-category">
            <div class="skill-category-title">Công cụ</div>
            <?php foreach ($skillCategories['tools'] as $s): ?>
                <button class="skill-btn" data-skill="<?= e($s['key']) ?>">
                    <span class="skill-btn-label"><?= $s['label'] ?></span>
                    <span class="skill-btn-desc"><?= e($s['desc']) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- CENTER: Chat -->
    <div class="ai-chat-panel">
        <div class="chat-header">
            <div class="chat-header-icon">🤖</div>
            <div class="chat-header-info">
                <h3>Trợ lý AI</h3>
                <p>Copywriting • SEO • Content • Prompt Engineering</p>
            </div>
            <div class="ai-status-badge">
                <div class="status-dot"></div>
                Online
            </div>
        </div>

        <div class="chat-messages" id="chatMessages">
            <div class="msg assistant">
                <div class="msg-avatar">🤖</div>
                <div class="msg-bubble">
                    Chào bạn! 👋 Mình là trợ lý AI của MMO. Mình có thể giúp bạn:
                    <br>• <strong>Viết content</strong> (copywriting, bài viết, social post)
                    <br>• <strong>Nghiên cứu từ khóa</strong> SEO
                    <br>• <strong>Tạo mô tả sản phẩm</strong> chuẩn Shopee/Amazon
                    <br>• <strong>Tối ưu prompt</strong> cho AI
                    <br><br>
                    Bạn cần gì hôm nay?
                </div>
            </div>
        </div>

        <div class="quick-starters">
            <button class="quick-starter" data-prompt="Viết bài review sản phẩm cho Facebook">📝 Review Facebook</button>
            <button class="quick-starter" data-prompt="Tạo caption TikTok viral">🎵 TikTok Caption</button>
            <button class="quick-starter" data-prompt="Nghiên cứu từ khóa SEO cho sản phẩm">🔍 SEO Keywords</button>
            <button class="quick-starter" data-prompt="Viết mô tả sản phẩm chuẩn SEO">🏷️ Mô tả SP</button>
        </div>

        <div class="chat-input-area">
            <textarea
                id="chatInput"
                class="chat-input"
                placeholder="Nhập câu hỏi... VD: Viết bài review sản phẩm áo thun nam cho Facebook"
                rows="1"
                onkeydown="if(event.key==='Enter' && !event.shiftKey) { event.preventDefault(); sendChat(); }"
            ></textarea>
            <button class="chat-send-btn" id="sendBtn" onclick="sendChat()">➤</button>
        </div>
    </div>

    <!-- RIGHT: Actions Panel -->
    <div class="ai-actions-panel">
        <div class="panel-title">⚡ Tạo Content Nhanh</div>

        <div class="form-group">
            <label class="form-label">Chọn sản phẩm</label>
            <select class="form-control" id="productSelect" onchange="loadProductData()">
                <option value="">— Chọn sản phẩm —</option>
                <?php
                $pdo = db_pdo();
                $siteId = (int)currentSiteId();
                $stmt = $pdo->prepare('SELECT id, product_name, price, source_platform FROM user_selected_products WHERE site_id = ? ORDER BY created_at DESC LIMIT 50');
                $stmt->execute([$siteId]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" data-name="<?= e($p['product_name']) ?>" data-price="<?= e($p['price']) ?>" data-platform="<?= e($p['source_platform']) ?>">
                        <?= e($p['product_name']) ?> — <?= number_format((float)$p['price'], 0, ',', '.') ?>đ
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Nền tảng</label>
            <select class="form-control" id="platformSelect">
                <option value="facebook">📘 Facebook</option>
                <option value="tiktok">🎵 TikTok</option>
                <option value="instagram">📷 Instagram</option>
                <option value="threads">💬 Threads</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Loại content</label>
            <select class="form-control" id="contentTypeSelect">
                <option value="copywriting">📝 Copywriting (AIDA/PAS)</option>
                <option value="product-description">🏷️ Mô tả sản phẩm SEO</option>
                <option value="social-post">📱 Social Post</option>
                <option value="seo-keyword">🔍 Nghiên cứu từ khóa</option>
            </select>
        </div>

        <button class="btn btn-primary btn-block" onclick="generateContent()">
            🚀 Tạo Content
        </button>

        <div id="generateResult" class="generate-result" style="display:none"></div>

        <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border)">
            <div class="panel-title" style="font-size:12px">📋 Hướng dẫn</div>
            <div style="font-size:12px; color:var(--text-muted); line-height:1.6">
                <strong>1.</strong> Chọn sản phẩm từ dropdown<br>
                <strong>2.</strong> Chọn nền tảng & loại content<br>
                <strong>3.</strong> Bấm "Tạo Content"<br>
                <strong>4.</strong> Copy kết quả áp dụng vào content
            </div>
        </div>
    </div>
</div>

<script>
let lastProductId = null;

function loadProductData() {
    const sel = document.getElementById('productSelect');
    const id = sel.value;
    if (id && id !== lastProductId) {
        lastProductId = id;
        // Auto-suggest to chat
        const option = sel.options[sel.selectedIndex];
        const name = option.dataset.name || '';
        const platform = document.getElementById('platformSelect').value;
        document.getElementById('chatInput').value = `Tạo content cho sản phẩm "${name}" trên ${platform}`;
    }
}

function sendChat() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    if (!message) return;

    const messagesEl = document.getElementById('chatMessages');

    // Append user message
    const userMsg = document.createElement('div');
    userMsg.className = 'msg user';
    userMsg.innerHTML = '<div class="msg-avatar">👤</div><div class="msg-bubble">' + escapeHtml(message) + '</div>';
    messagesEl.appendChild(userMsg);

    input.value = '';
    input.style.height = 'auto';

    // Scroll to bottom
    messagesEl.scrollTop = messagesEl.scrollHeight;

    // Show typing indicator
    const typing = document.createElement('div');
    typing.className = 'msg assistant';
    typing.id = 'typingIndicator';
    typing.innerHTML = '<div class="msg-avatar">🤖</div><div class="msg-bubble" style="color:var(--text-muted);font-style:italic">Đang suy nghĩ... ⏳</div>';
    messagesEl.appendChild(typing);
    messagesEl.scrollTop = messagesEl.scrollHeight;

    // Get context
    const productId = document.getElementById('productSelect').value;
    const platform = document.getElementById('platformSelect').value;

    // Send AJAX
    const formData = new FormData();
    formData.append('message', message);
    if (productId) formData.append('product_id', productId);
    formData.append('platform', platform);

    fetch('<?= url('/ai-assistant/chat') ?>', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        const typingEl = document.getElementById('typingIndicator');
        if (typingEl) typingEl.remove();

        const assistantMsg = document.createElement('div');
        assistantMsg.className = 'msg assistant';
        assistantMsg.innerHTML = '<div class="msg-avatar">🤖</div><div class="msg-bubble">' + (data.reply ? data.reply.replace(/\n/g, '<br>') : 'Không có phản hồi.') + '</div>';
        messagesEl.appendChild(assistantMsg);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    })
    .catch(err => {
        const typingEl = document.getElementById('typingIndicator');
        if (typingEl) typingEl.remove();
        const errMsg = document.createElement('div');
        errMsg.className = 'msg assistant';
        errMsg.innerHTML = '<div class="msg-avatar">🤖</div><div class="msg-bubble" style="color:#ef4444">❌ Lỗi: ' + escapeHtml(err.message) + '</div>';
        messagesEl.appendChild(errMsg);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    });
}

function generateContent() {
    const productId = document.getElementById('productSelect').value;
    const platform = document.getElementById('platformSelect').value;
    const contentType = document.getElementById('contentTypeSelect').value;
    const resultEl = document.getElementById('generateResult');

    if (!productId) {
        alert('Vui lòng chọn sản phẩm trước!');
        return;
    }

    resultEl.style.display = 'block';
    resultEl.textContent = '⏳ Đang tạo content...';

    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('platform', platform);
    formData.append('type', contentType);

    fetch('<?= url('/ai-assistant/generate') ?>', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            resultEl.textContent = '❌ Lỗi: ' + data.error;
        } else {
            resultEl.textContent = data.result || 'Không có kết quả.';
        }
    })
    .catch(err => {
        resultEl.textContent = '❌ Lỗi: ' + err.message;
    });
}

// Quick starter buttons
document.querySelectorAll('.quick-starter').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('chatInput').value = this.dataset.prompt;
        document.getElementById('chatInput').focus();
    });
});

// Skill buttons highlight
document.querySelectorAll('.skill-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.skill-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const skill = this.dataset.skill;
        const typeMap = {
            'copywriting': 'copywriting',
            'article-writing': 'social-post',
            'social-media-content': 'social-post',
            'product-description': 'product-description',
            'seo-keyword-researcher': 'seo-keyword',
            'prompt-engineering': 'copywriting',
        };
        const select = document.getElementById('contentTypeSelect');
        if (typeMap[skill]) {
            select.value = typeMap[skill];
        }
    });
});

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Auto-resize textarea
document.getElementById('chatInput').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});
</script>