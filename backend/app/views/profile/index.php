<?php
$isConnected = !empty($user['telegram_chat_id']);
$chatId = $user['telegram_chat_id'] ?? '';
$botUser = $botUsername ?? '';
$hasBotConfig = !empty($botConfigured);
$deepLink = $botUser ? "https://t.me/{$botUser}?start=uid_" . (int)$user['id'] : '';
?>
<div class="page-header">
    <div>
        <div class="page-kicker">Hồ sơ cá nhân</div>
        <h2>Thông tin tài khoản</h2>
        <p>Quản lý thông tin và kết nối Telegram của bạn.</p>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-title">Thông tin cơ bản</div>
        <div class="form-group mt-16">
            <label class="form-label">Tên đầy đủ</label>
            <input class="form-control" type="text" value="<?= e($user['full_name'] ?? '') ?>" readonly>
        </div>
        <div class="form-group">
            <label class="form-label">Tên đăng nhập</label>
            <input class="form-control" type="text" value="<?= e($user['username'] ?? '') ?>" readonly>
        </div>
        <div class="form-group">
            <label class="form-label">Email</label>
            <input class="form-control" type="email" value="<?= e($user['email'] ?? '') ?>" readonly>
        </div>
    </div>

    <div class="card" id="tele-card">
        <div class="card-title">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
            Kết nối Telegram
        </div>
        <p class="sub mb-16">Nhận thông báo captcha Shopee, cào dữ liệu, ảnh AI… trực tiếp vào Telegram cá nhân.</p>

        <?php if ($isConnected): ?>
            <!-- ĐÃ KẾT NỐI -->
            <div class="tele-connected-box">
                <div class="tele-badge success">
                    <span class="tele-dot"></span>
                    Đã kết nối
                </div>
                <div class="sub mt-8">Chat ID: <code><?= e($chatId) ?></code></div>
            </div>
            <button type="button" class="btn btn-danger btn-full mt-16" id="btn-disconnect">Ngắt kết nối</button>

        <?php elseif (!$hasBotConfig): ?>
            <!-- CHƯA CẤU HÌNH BOT -->
            <div class="tele-badge idle">
                <span class="tele-dot"></span>
                Bot chưa được cấu hình
            </div>
            <p class="sub mt-8">Liên hệ quản trị viên để cấu hình Telegram Bot.</p>

        <?php else: ?>
            <!-- CHƯA KẾT NỐI — FLOW KẾT NỐI -->
            <div id="step-connect">
                <div class="tele-badge idle mb-16">
                    <span class="tele-dot"></span>
                    Chưa kết nối
                </div>
                <button type="button" class="btn btn-primary btn-full" id="btn-connect">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                    Kết nối Telegram
                </button>
            </div>

            <div id="step-waiting" style="display:none">
                <div class="tele-waiting-box">
                    <div class="tele-spinner"></div>
                    <div>
                        <strong>Đang chờ kết nối…</strong>
                        <div class="sub">Bấm <b>Start</b> trên Telegram, hệ thống sẽ tự nhận diện bạn.</div>
                    </div>
                </div>
                <button type="button" class="btn btn-ghost btn-full mt-12" id="btn-cancel">Hủy</button>
            </div>

            <div id="step-success" style="display:none">
                <div class="tele-badge success">
                    <span class="tele-dot"></span>
                    Kết nối thành công!
                </div>
                <div class="sub mt-8" id="success-chatid"></div>
            </div>
        <?php endif; ?>

        <div id="tele-error" class="tele-result-err mt-12" style="display:none"></div>
    </div>
</div>

<style>
.tele-badge {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 8px 16px; border-radius: 8px;
    font-weight: 600; font-size: 14px;
}
.tele-badge.success { background: rgba(16,185,129,.15); color: #10b981; }
.tele-badge.idle    { background: rgba(156,163,175,.15); color: var(--text-muted); }
.tele-dot {
    width: 8px; height: 8px; border-radius: 50%; display: inline-block;
}
.tele-badge.success .tele-dot { background: #10b981; box-shadow: 0 0 6px #10b981; }
.tele-badge.idle .tele-dot    { background: #9ca3af; }
.tele-result-err { padding: 12px; border-radius: 8px; background: rgba(239,68,68,.1); color: #ef4444; font-size: 13px; }
.tele-connected-box { padding: 16px; border-radius: 10px; background: rgba(16,185,129,.06); border: 1px solid rgba(16,185,129,.15); }
.tele-waiting-box {
    display: flex; align-items: center; gap: 16px;
    padding: 20px; border-radius: 12px;
    background: rgba(59,130,246,.06); border: 1px solid rgba(59,130,246,.15);
}
.tele-spinner {
    width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
    border: 3px solid rgba(59,130,246,.2); border-top-color: #3b82f6;
    animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<script>
(function() {
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var baseUrl   = '<?= url('') ?>';
    var deepLink  = '<?= e($deepLink) ?>';
    var pollTimer = null;
    var pollCount = 0;
    var maxPolls  = 30; // max 60 giây (30 x 2s)

    function postJson(path) {
        var fd = new FormData();
        fd.append('csrf_token', csrfToken);
        return fetch(baseUrl + path, {
            method: 'POST', body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function(r) { return r.json(); });
    }

    function showError(msg) {
        var el = document.getElementById('tele-error');
        el.textContent = '❌ ' + msg;
        el.style.display = 'block';
    }

    function hideError() {
        document.getElementById('tele-error').style.display = 'none';
    }

    // ── KẾT NỐI ──
    var btnConnect = document.getElementById('btn-connect');
    if (btnConnect) {
        btnConnect.addEventListener('click', function() {
            hideError();
            // Mở Telegram deep link trong tab mới
            if (deepLink) {
                window.open(deepLink, '_blank');
            }
            // Chuyển sang giao diện chờ
            document.getElementById('step-connect').style.display = 'none';
            document.getElementById('step-waiting').style.display = 'block';
            // Bắt đầu poll
            pollCount = 0;
            pollTimer = setInterval(doPoll, 2500);
        });
    }

    function doPoll() {
        pollCount++;
        if (pollCount > maxPolls) {
            clearInterval(pollTimer);
            document.getElementById('step-waiting').style.display = 'none';
            document.getElementById('step-connect').style.display = 'block';
            showError('Hết thời gian chờ. Hãy bấm Start trên Telegram rồi thử lại.');
            return;
        }
        postJson('/profile/telegram/connect').then(function(res) {
            if (res.success) {
                clearInterval(pollTimer);
                document.getElementById('step-waiting').style.display = 'none';
                document.getElementById('step-success').style.display = 'block';
                document.getElementById('success-chatid').textContent = 'Chat ID: ' + (res.data?.chat_id || '');
                setTimeout(function() { location.reload(); }, 2000);
            }
            // Nếu chưa thành công → tiếp tục poll, không hiện lỗi
        }).catch(function() {
            // Lỗi mạng → bỏ qua, tiếp tục poll
        });
    }

    // ── HỦY ──
    var btnCancel = document.getElementById('btn-cancel');
    if (btnCancel) {
        btnCancel.addEventListener('click', function() {
            clearInterval(pollTimer);
            document.getElementById('step-waiting').style.display = 'none';
            document.getElementById('step-connect').style.display = 'block';
        });
    }

    // ── NGẮT KẾT NỐI ──
    var btnDisconnect = document.getElementById('btn-disconnect');
    if (btnDisconnect) {
        btnDisconnect.addEventListener('click', function() {
            if (!confirm('Bạn có chắc muốn ngắt kết nối Telegram?')) return;
            hideError();
            btnDisconnect.disabled = true;
            btnDisconnect.textContent = 'Đang ngắt…';
            postJson('/profile/telegram/disconnect').then(function(res) {
                if (res.success) location.reload();
                else { showError(res.message); btnDisconnect.disabled = false; btnDisconnect.textContent = 'Ngắt kết nối'; }
            }).catch(function() {
                showError('Lỗi kết nối server.');
                btnDisconnect.disabled = false;
                btnDisconnect.textContent = 'Ngắt kết nối';
            });
        });
    }
})();
</script>
