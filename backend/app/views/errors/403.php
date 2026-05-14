<?php
/**
 * 403 Forbidden page — shown when user lacks permission.
 */
?>
<div style="text-align:center; padding: 80px 20px;">
    <div style="font-size: 64px; margin-bottom: 16px; opacity: 0.3;"></div>
    <h2 style="font-size: 22px; font-weight: 600; margin-bottom: 8px;">Từ chối truy cập</h2>
    <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 24px;">
        Bạn không có quyền thực hiện thao tác này.
        <?php if (!empty($permission)): ?>
            <br><code style="font-size: 12px; color: #94a3b8; background: rgba(99,102,241,0.1); padding: 2px 8px; border-radius: 4px;"><?= e($permission) ?></code>
        <?php endif; ?>
    </p>
    <a href="<?= url('/') ?>" style="color: #6366f1; text-decoration: none; font-size: 14px;">← Về Dashboard</a>
</div>
