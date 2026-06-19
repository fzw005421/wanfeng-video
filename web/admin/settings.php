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
    } elseif ($action === 'save_version') {
        setSetting('latest_version', trim($_POST['latest_version'] ?? '1.0.0'));
        setSetting('force_update', isset($_POST['force_update']) ? '1' : '0');
        setSetting('update_notes', trim($_POST['update_notes'] ?? ''));
        setSetting('update_url', trim($_POST['update_url'] ?? ''));
        $msg = '<div class="alert alert-success">版本控制设置已保存</div>';
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
$latestVersion = getSetting('latest_version', '1.0.0');
$forceUpdate = getSetting('force_update', '0');
$updateNotes = getSetting('update_notes', '');
$updateUrl = getSetting('update_url', '');
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

            <!-- 版本控制 -->
            <div class="card">
                <div class="card-header"><h3>版本控制</h3></div>
                <div class="card-body">
                    <form method="post" style="max-width:500px;">
                        <input type="hidden" name="action" value="save_version">
                        <div class="form-group">
                            <label>最新版本号</label>
                            <input type="text" name="latest_version" value="<?php echo safeInput($latestVersion); ?>" required placeholder="如 1.0.1">
                            <span class="help-text">前端 package.json 中的版本号应与该值同步</span>
                        </div>
                        <div class="form-group">
                            <label>版本更新说明</label>
                            <textarea name="update_notes" rows="4" placeholder="在此填写新版本的更新内容，支持换行。&#10;例如：&#10;1. 修复了某某bug&#10;2. 新增了某某功能"><?php echo safeInput($updateNotes); ?></textarea>
                            <span class="help-text">显示在客户端更新弹窗中，支持多行文本</span>
                        </div>
                        <div class="form-group">
                            <label>下载地址</label>
                            <input type="url" name="update_url" value="<?php echo safeInput($updateUrl); ?>" placeholder="如 https://github.com/xxx/releases/latest">
                            <span class="help-text">客户端点击"立即更新"时打开的下载页面</span>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="force_update" value="1" <?php if ($forceUpdate === '1') echo 'checked'; ?>>
                                强制更新（开启后旧版客户端将无法使用）
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary">保存版本设置</button>
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
