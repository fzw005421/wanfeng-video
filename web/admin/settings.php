<?php
/**
 * 晚风影视 - 管理后台 系统设置
 */
require_once __DIR__ . '/includes.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_site') {
        setSetting('site_name', trim($_POST['site_name'] ?? '晚风影视'));
        setSetting('site_logo', trim($_POST['site_logo'] ?? ''));
        $msg = '<div class="alert alert-success">站点设置已保存</div>';
    } elseif ($action === 'save_cms') {
        // 更新数据库配置文件
        $configFile = __DIR__ . '/../config/database.php';
        if (file_exists($configFile) && is_writable($configFile)) {
            $content = file_get_contents($configFile);
            $replaces = [
                "'" . CMS_DB_HOST . "'" => "'" . addslashes(trim($_POST['cms_db_host'] ?? '')) . "'",
                "'" . CMS_DB_PORT . "'" => "'" . addslashes(trim($_POST['cms_db_port'] ?? '3306')) . "'",
                "'" . CMS_DB_USER . "'" => "'" . addslashes(trim($_POST['cms_db_user'] ?? '')) . "'",
                "'" . CMS_DB_PASS . "'" => "'" . addslashes(trim($_POST['cms_db_pass'] ?? '')) . "'",
                "'" . CMS_DB_NAME . "'" => "'" . addslashes(trim($_POST['cms_db_name'] ?? '')) . "'",
            ];
            $content = str_replace(array_keys($replaces), array_values($replaces), $content);
            file_put_contents($configFile, $content);
            $msg = '<div class="alert alert-success">CMS数据库设置已保存，请勿频繁修改</div>';
        } else {
            $msg = '<div class="alert alert-error">配置文件不可写，请检查权限</div>';
        }
    } elseif ($action === 'change_admin_password') {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';

        if (empty($oldPass) || empty($newPass)) {
            $msg = '<div class="alert alert-error">请输入旧密码和新密码</div>';
        } elseif (strlen($newPass) < 6) {
            $msg = '<div class="alert alert-error">新密码至少6位</div>';
        } else {
            $admin = dbQueryOne("SELECT password FROM wf_admins WHERE id = ?", [$adminId]);
            if (!passwordVerify($oldPass, $admin['password'])) {
                $msg = '<div class="alert alert-error">旧密码错误</div>';
            } else {
                dbExecute("UPDATE wf_admins SET password = ? WHERE id = ?", [passwordHash($newPass), $adminId]);
                $msg = '<div class="alert alert-success">密码修改成功，请重新登录</div>';
            }
        }
    }
}

$siteName = getSetting('site_name', '晚风影视');
$siteLogo = getSetting('site_logo', '');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统设置 - 晚风影视管理后台</title>
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-brand"><h2>晚风影视</h2><small>管理后台</small></div>
        <?php echo renderSidebar('settings.php'); ?>
        <div class="sidebar-footer"><a href="/admin/logout.php">退出登录</a></div>
    </aside>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">系统设置</div>
            <div class="topbar-right"><span>管理员：<?php echo $adminUsername; ?></span></div>
        </div>
        <div class="content-area">
            <?php echo $msg; ?>

            <!-- 站点设置 -->
            <div class="card">
                <div class="card-header"><h3>站点设置</h3></div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="save_site">
                        <div class="form-group">
                            <label>站点名称</label>
                            <input type="text" name="site_name" value="<?php echo safeInput($siteName); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>站点Logo URL</label>
                            <input type="url" name="site_logo" value="<?php echo safeInput($siteLogo); ?>" placeholder="可选，留空使用文字标题">
                        </div>
                        <button type="submit" class="btn btn-primary">保存设置</button>
                    </form>
                </div>
            </div>

            <!-- CMS数据库设置 -->
            <div class="card">
                <div class="card-header"><h3>苹果CMS数据库设置</h3></div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="save_cms">
                        <div class="form-inline" style="margin-bottom:12px;">
                            <div class="form-group">
                                <label>主机</label>
                                <input type="text" name="cms_db_host" value="<?php echo CMS_DB_HOST; ?>" required style="width:160px">
                            </div>
                            <div class="form-group">
                                <label>端口</label>
                                <input type="text" name="cms_db_port" value="<?php echo CMS_DB_PORT; ?>" style="width:100px">
                            </div>
                            <div class="form-group">
                                <label>用户名</label>
                                <input type="text" name="cms_db_user" value="<?php echo CMS_DB_USER; ?>" required style="width:140px">
                            </div>
                            <div class="form-group">
                                <label>密码</label>
                                <input type="password" name="cms_db_pass" value="<?php echo CMS_DB_PASS; ?>" style="width:140px">
                            </div>
                            <div class="form-group">
                                <label>数据库名</label>
                                <input type="text" name="cms_db_name" value="<?php echo CMS_DB_NAME; ?>" required style="width:140px">
                            </div>
                        </div>
                        <span class="help-text" style="display:block;margin-bottom:12px;">修改后需要刷新页面生效。本平台对CMS数据库仅做只读操作。</span>
                        <button type="submit" class="btn btn-primary">保存CMS设置</button>
                    </form>
                </div>
            </div>

            <!-- 修改密码 -->
            <div class="card">
                <div class="card-header"><h3>修改管理员密码</h3></div>
                <div class="card-body">
                    <form method="post" style="max-width:400px;">
                        <input type="hidden" name="action" value="change_admin_password">
                        <div class="form-group">
                            <label>旧密码</label>
                            <input type="password" name="old_password" required>
                        </div>
                        <div class="form-group">
                            <label>新密码</label>
                            <input type="password" name="new_password" required minlength="6" placeholder="至少6位">
                        </div>
                        <button type="submit" class="btn btn-primary">修改密码</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
