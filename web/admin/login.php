<?php
/**
 * 晚风影视 - 管理后台登录
 */

require_once __DIR__ . '/../includes/init.php';

$error = '';

// 已登录则跳转
if (!empty($_SESSION['admin_id'])) {
    header('Location: /admin/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } else {
        $admin = dbQueryOne("SELECT id, username, password, role, status FROM wf_admins WHERE username = ?", [$username]);

        if (!$admin) {
            $error = '用户名或密码错误';
        } elseif ($admin['status'] != 1) {
            $error = '账号已被禁用';
        } elseif (!passwordVerify($password, $admin['password'])) {
            $error = '用户名或密码错误';
        } else {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];

            dbExecute("UPDATE wf_admins SET last_login = NOW(), login_ip = ? WHERE id = ?", [getClientIp(), $admin['id']]);

            header('Location: /admin/');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台 - <?php echo getSetting('site_name', '晚风影视'); ?></title>
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body class="login-page">
<div class="login-box">
    <div class="login-header">
        <h2>晚风影视</h2>
        <p>管理后台</p>
    </div>
    <?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    <form method="post" class="login-form">
        <div class="form-group">
            <label>用户名</label>
            <input type="text" name="username" placeholder="请输入管理员账号" required autofocus>
        </div>
        <div class="form-group">
            <label>密码</label>
            <input type="password" name="password" placeholder="请输入密码" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">登 录</button>
    </form>
</div>
</body>
</html>
