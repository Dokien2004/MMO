<?php
/**
 * Module disabled page — shown when accessing a disabled module.
 */
?>
<div style="text-align:center; padding: 80px 20px;">
    <div style="font-size: 64px; margin-bottom: 16px; opacity: 0.3;">⚡</div>
    <h2 style="font-size: 22px; font-weight: 600; margin-bottom: 8px;">Module đang tắt</h2>
    <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 24px;">
        Module <strong><?= e($moduleCode ?? '???') ?></strong> hiện đang bị tắt bởi quản trị viên.
        <br>Liên hệ admin để kích hoạt.
    </p>
    <a href="<?= url('/') ?>" style="color: #6366f1; text-decoration: none; font-size: 14px;">← Về Dashboard</a>
</div>
