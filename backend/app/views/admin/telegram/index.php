<div class="page-header">
    <div>
        <div class="page-kicker">Quản trị toàn cục</div>
        <h2>Liên kết Telegram Bot (Dùng chung)</h2>
        <p>Cấu hình Bot Telegram dùng chung cho toàn bộ hệ thống (các chi nhánh đều dùng chung Bot này để nhận thông báo cào dữ liệu, captcha, ảnh AI).</p>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-title">Cấu hình Bot Token & Chat ID</div>
        <p class="sub mb-16">Nhập token của bot và Chat ID để nhận thông báo. Để lấy Chat ID nhanh, hãy nhắn tin cho bot rồi bấm nút "Lấy Chat ID từ tin nhắn mới nhất".</p>
        <form method="POST" action="<?= url('/admin/telegram/save') ?>">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label">Telegram Bot Token</label>
                <input class="form-control" type="password" name="TELEGRAM_BOT_TOKEN" placeholder="VD: 123456789:ABCDefgh..." autocomplete="off">
                <div class="sub">Trạng thái: <?= $isConfigured ? '<span style="color:var(--success)">Đã lưu Token</span>' : '<span style="color:var(--danger)">Chưa có Token</span>' ?></div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Telegram Chat ID</label>
                <input class="form-control" name="TELEGRAM_CHAT_ID" value="<?= e($chatId) ?>" placeholder="VD: 6861841372" autocomplete="off" id="telegram_chat_id">
                <div class="sub">Chat ID của nhóm hoặc cá nhân sẽ nhận thông báo.</div>
            </div>

            <button type="submit" class="btn btn-primary btn-full mt-16">Lưu cấu hình Telegram</button>
        </form>
    </div>

    <div class="card">
        <div class="card-title">Công cụ hỗ trợ</div>
        
        <div class="mt-16">
            <strong>1. Hướng dẫn lấy Chat ID tự động</strong>
            <ol style="margin-left:20px; color:var(--text-muted); font-size:14px; margin-top:8px; line-height:1.6;">
                <li>Mở Telegram, tìm kiếm bot của bạn.</li>
                <li>Nhắn một tin nhắn bất kỳ (ví dụ: <code>hello</code>) cho bot.</li>
                <li>Bấm nút bên dưới để tự động lấy Chat ID của bạn điền vào ô bên trái.</li>
            </ol>
            <form method="POST" action="<?= url('/admin/telegram/fetch-chat-id') ?>" style="margin-top:12px;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-accent">Lấy Chat ID từ tin nhắn mới nhất</button>
            </form>
        </div>

        <div class="mt-24">
            <strong>2. Gửi tin nhắn thử nghiệm</strong>
            <p class="sub mb-8">Kiểm tra xem bot đã gửi tin nhắn thành công tới Chat ID cấu hình chưa.</p>
            <form method="POST" action="<?= url('/admin/telegram/test') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-success" <?= !$isConfigured ? 'disabled' : '' ?>>Gửi tin thử nghiệm</button>
            </form>
        </div>
    </div>
</div>

<div class="card mt-24">
    <div class="card-title">Người dùng đã liên kết cá nhân (<?= count($connectedUsers) ?>)</div>
    <div class="table-wrap">
        <table class="table-main">
            <thead>
                <tr>
                    <th>Người dùng</th>
                    <th>Email</th>
                    <th>Chi nhánh</th>
                    <th>Telegram Chat ID</th>
                    <th>Ngày cập nhật</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($connectedUsers)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:32px; color:var(--text-muted);">Chưa có người dùng nào liên kết Telegram cá nhân.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($connectedUsers as $u): ?>
                        <tr>
                            <td>
                                <strong><?= e($u['full_name']) ?></strong>
                                <div class="sub">@<?= e($u['username']) ?></div>
                            </td>
                            <td><?= e($u['email']) ?></td>
                            <td><span class="badge badge-none"><?= e($u['site_name']) ?></span></td>
                            <td><code><?= e($u['telegram_chat_id']) ?></code></td>
                            <td><?= date('d/m/Y H:i', strtotime($u['updated_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
