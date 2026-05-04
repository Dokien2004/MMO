<?php
/**
 * Login page — standalone layout (no sidebar).
 * Dark premium theme matching app design.
 */
$flash = get_flash();
$error = $error ?? null;
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= asset('/css/login.css') ?>">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <div class="icon">A</div>
                <h1><?= e(APP_NAME) ?></h1>
                <p>Đăng nhập để tiếp tục</p>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                    <?= e($flash['text']) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="<?= url('/login') ?>" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="form-group">
                    <label for="username">Tên đăng nhập</label>
                    <input type="text" id="username" name="username" placeholder="admin" required autofocus
                           value="<?= e($_POST['username'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="password">Mật khẩu</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn-login">Đăng nhập</button>
            </form>
        </div>
        <div class="login-footer">
            <?= e(APP_NAME) ?> · v1.0
        </div>
    </div>
</body>
</html>
