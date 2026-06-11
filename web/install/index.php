<?php
/**
 * 晚风影视 - 安装向导
 */
$installed = file_exists(__DIR__ . '/../config/database.php');
if ($installed) {
    $configContent = file_get_contents(__DIR__ . '/../config/database.php');
    if (strpos($configContent, '{DB_HOST}') === false) {
        header('Location: /admin/');
        exit;
    }
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// 从POST中提取保存的数据库配置（步骤2→3传递用）
$saved = [];
$dbFields = ['db_host','db_port','db_user','db_pass','db_name',
             'cms_db_host','cms_db_port','cms_db_user','cms_db_pass','cms_db_name'];
foreach ($dbFields as $f) {
    $saved[$f] = $_POST[$f] ?? '';
}

// 处理所有POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'check_db') {
        $host = $_POST['db_host'] ?? '';
        $port = $_POST['db_port'] ?? '3306';
        $user = $_POST['db_user'] ?? '';
        $pass = $_POST['db_pass'] ?? '';
        $name = $_POST['db_name'] ?? '';
        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo json_encode(['code' => 200, 'msg' => '数据库连接成功']);
        } catch (Exception $e) {
            echo json_encode(['code' => 500, 'msg' => '连接失败: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'check_cms_db') {
        $host = $_POST['cms_db_host'] ?? '';
        $port = $_POST['cms_db_port'] ?? '3306';
        $user = $_POST['cms_db_user'] ?? '';
        $pass = $_POST['cms_db_pass'] ?? '';
        $name = $_POST['cms_db_name'] ?? '';
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            echo json_encode(['code' => 200, 'msg' => 'CMS数据库连接成功']);
        } catch (Exception $e) {
            echo json_encode(['code' => 500, 'msg' => '连接失败: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'go_step3') {
        // 步骤2→步骤3：保存POST数据，渲染步骤3时会把数据嵌入hidden
        $step = 3;
    }

    if ($action === 'install') {
        $db_host   = $_POST['db_host'] ?? '';
        $db_port   = $_POST['db_port'] ?? '3306';
        $db_user   = $_POST['db_user'] ?? '';
        $db_pass   = $_POST['db_pass'] ?? '';
        $db_name   = $_POST['db_name'] ?? '';
        $cms_db_host = $_POST['cms_db_host'] ?? '';
        $cms_db_port = $_POST['cms_db_port'] ?? '3306';
        $cms_db_user = $_POST['cms_db_user'] ?? '';
        $cms_db_pass = $_POST['cms_db_pass'] ?? '';
        $cms_db_name = $_POST['cms_db_name'] ?? '';
        $admin_user  = $_POST['admin_user'] ?? '';
        $admin_pass  = $_POST['admin_pass'] ?? '';

        if (empty($db_host) || empty($db_name) || empty($admin_user) || empty($admin_pass)) {
            $error = '请填写所有必填项';
        } elseif (strlen($admin_pass) < 6) {
            $error = '管理员密码至少6位';
        } else {
            try {
                $dsn = "mysql:host={$db_host};port={$db_port};charset=utf8mb4";
                $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `{$db_name}`");

                $sql = file_get_contents(__DIR__ . '/../sql/schema.sql');
                $pdo->exec($sql);

                $adminHash = password_hash($admin_pass, PASSWORD_BCRYPT, ['cost' => 10]);
                $stmt = $pdo->prepare("INSERT INTO wf_admins (username, password, role, status) VALUES (?, ?, 'super_admin', 1)");
                $stmt->execute([$admin_user, $adminHash]);

                $siteName = $_POST['site_name'] ?? '晚风影视';
                $pdo->prepare("UPDATE wf_settings SET setting_value = ? WHERE setting_key = 'site_name'")->execute([$siteName]);

                $configTemplate = file_get_contents(__DIR__ . '/../config/database.php');
                $replacements = [
                    '{DB_HOST}' => $db_host, '{DB_PORT}' => $db_port,
                    '{DB_USER}' => $db_user, '{DB_PASS}' => $db_pass,
                    '{DB_NAME}' => $db_name,
                    '{CMS_DB_HOST}' => $cms_db_host, '{CMS_DB_PORT}' => $cms_db_port,
                    '{CMS_DB_USER}' => $cms_db_user, '{CMS_DB_PASS}' => $cms_db_pass,
                    '{CMS_DB_NAME}' => $cms_db_name,
                ];
                $configContent = str_replace(array_keys($replacements), array_values($replacements), $configTemplate);
                file_put_contents(__DIR__ . '/../config/database.php', $configContent);

                $success = '晚风影视安装成功！';
                header('Refresh: 2; URL=/admin/login.php');
            } catch (Exception $e) {
                $error = '安装失败: ' . $e->getMessage();
            }
        }
    }
}

function renderSteps($current) {
    $steps = [1 => '环境检查', 2 => '数据库配置', 3 => '管理员设置', 4 => '安装完成'];
    $html = '<div class="steps">';
    foreach ($steps as $num => $label) {
        $cls = $num === $current ? ' active' : ($num < $current ? ' done' : '');
        $html .= "<div class=\"step{$cls}\"><span class=\"step-num\">{$num}</span><span class=\"step-label\">{$label}</span></div>";
        if ($num < count($steps)) $html .= '<div class="step-line"></div>';
    }
    $html .= '</div>';
    return $html;
}

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// 生成隐藏字段HTML（携带上一步的数据库配置）
function hiddenFields($data) {
    $html = '';
    foreach ($data as $k => $v) {
        $html .= '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '">';
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>晚风影视 - 安装向导</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="install-container">
    <div class="install-header">
        <h1>晚风影视</h1>
        <p>安装向导</p>
    </div>

    <?php echo renderSteps($step); ?>

    <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

    <?php if ($step === 1): ?>
    <!-- ============ 步骤1：环境检查 ============ -->
    <div class="install-body">
        <h3>系统环境检查</h3>
        <table class="check-table">
            <tr><td>PHP版本</td><td><?php echo phpversion(); ?></td><td><?php echo version_compare(PHP_VERSION, '7.4', '>=') ? '<span class="badge badge-ok">通过</span>' : '<span class="badge badge-fail">需要 >= 7.4</span>'; ?></td></tr>
            <tr><td>PDO扩展</td><td><?php echo extension_loaded('pdo') ? '已安装' : '未安装'; ?></td><td><?php echo extension_loaded('pdo') ? '<span class="badge badge-ok">通过</span>' : '<span class="badge badge-fail">未安装</span>'; ?></td></tr>
            <tr><td>PDO MySQL</td><td><?php echo extension_loaded('pdo_mysql') ? '已安装' : '未安装'; ?></td><td><?php echo extension_loaded('pdo_mysql') ? '<span class="badge badge-ok">通过</span>' : '<span class="badge badge-fail">未安装</span>'; ?></td></tr>
            <tr><td>JSON扩展</td><td><?php echo extension_loaded('json') ? '已安装' : '未安装'; ?></td><td><?php echo extension_loaded('json') ? '<span class="badge badge-ok">通过</span>' : '<span class="badge badge-fail">未安装</span>'; ?></td></tr>
            <tr><td>OpenSSL扩展</td><td><?php echo extension_loaded('openssl') ? '已安装' : '未安装'; ?></td><td><?php echo extension_loaded('openssl') ? '<span class="badge badge-ok">通过</span>' : '<span class="badge badge-fail">未安装</span>'; ?></td></tr>
            <tr><td>config目录可写</td><td><?php echo is_writable(__DIR__ . '/../config/') ? '可写' : '不可写'; ?></td><td><?php echo is_writable(__DIR__ . '/../config/') ? '<span class="badge badge-ok">通过</span>' : '<span class="badge badge-fail">请设置写入权限</span>'; ?></td></tr>
        </table>
        <?php $allPass = version_compare(PHP_VERSION, '7.4', '>=') && extension_loaded('pdo') && extension_loaded('pdo_mysql') && extension_loaded('json') && extension_loaded('openssl') && is_writable(__DIR__ . '/../config/'); ?>
        <?php if ($allPass): ?>
        <div class="btn-group"><a href="?step=2" class="btn btn-primary">下一步：数据库配置</a></div>
        <?php else: ?>
        <p style="color:#e74c3c;">请先满足以上所有环境要求后刷新页面重试</p>
        <?php endif; ?>
    </div>

    <?php elseif ($step === 2): ?>
    <!-- ============ 步骤2：数据库配置 ============ -->
    <div class="install-body">
        <form method="post" action="?step=3" id="step2Form">
            <input type="hidden" name="action" value="go_step3">

            <h3>自有数据库配置</h3>
            <p class="desc">用于存储用户、播放记录、系统配置等数据</p>
            <div class="form-row">
                <label>数据库主机 <span class="required">*</span></label>
                <input type="text" name="db_host" value="<?php echo h($saved['db_host'] ?: '127.0.0.1'); ?>" placeholder="默认 127.0.0.1">
            </div>
            <div class="form-row">
                <label>端口</label>
                <input type="text" name="db_port" value="<?php echo h($saved['db_port'] ?: '3306'); ?>" placeholder="默认 3306">
            </div>
            <div class="form-row">
                <label>数据库用户名 <span class="required">*</span></label>
                <input type="text" name="db_user" value="<?php echo h($saved['db_user'] ?: 'root'); ?>" placeholder="数据库用户名">
            </div>
            <div class="form-row">
                <label>数据库密码</label>
                <input type="password" name="db_pass" value="<?php echo h($saved['db_pass']); ?>" placeholder="数据库密码（可为空）">
            </div>
            <div class="form-row">
                <label>数据库名称 <span class="required">*</span></label>
                <input type="text" name="db_name" value="<?php echo h($saved['db_name'] ?: 'wanfeng_video'); ?>" placeholder="建议 wanfeng_video">
            </div>
            <button type="button" class="btn btn-outline btn-sm" onclick="checkDb()">测试连接</button>
            <span id="dbResult" class="test-result"></span>

            <hr>

            <h3>苹果CMS数据库配置（只读）</h3>
            <p class="desc">用于读取影视数据，平台不会修改CMS数据</p>
            <div class="form-row">
                <label>CMS数据库主机 <span class="required">*</span></label>
                <input type="text" name="cms_db_host" value="<?php echo h($saved['cms_db_host'] ?: '127.0.0.1'); ?>" placeholder="CMS数据库地址">
            </div>
            <div class="form-row">
                <label>端口</label>
                <input type="text" name="cms_db_port" value="<?php echo h($saved['cms_db_port'] ?: '3306'); ?>" placeholder="默认 3306">
            </div>
            <div class="form-row">
                <label>CMS数据库用户名 <span class="required">*</span></label>
                <input type="text" name="cms_db_user" value="<?php echo h($saved['cms_db_user']); ?>" placeholder="CMS数据库用户名">
            </div>
            <div class="form-row">
                <label>CMS数据库密码</label>
                <input type="password" name="cms_db_pass" value="<?php echo h($saved['cms_db_pass']); ?>" placeholder="CMS数据库密码（可为空）">
            </div>
            <div class="form-row">
                <label>CMS数据库名称 <span class="required">*</span></label>
                <input type="text" name="cms_db_name" value="<?php echo h($saved['cms_db_name']); ?>" placeholder="苹果CMS的数据库名">
            </div>
            <button type="button" class="btn btn-outline btn-sm" onclick="checkCmsDb()">测试连接</button>
            <span id="cmsDbResult" class="test-result"></span>

            <div class="btn-group">
                <a href="?step=1" class="btn btn-outline">上一步</a>
                <button type="submit" class="btn btn-primary">下一步：管理员设置</button>
            </div>
        </form>
    </div>

    <?php elseif ($step === 3): ?>
    <!-- ============ 步骤3：管理员设置 ============ -->
    <div class="install-body">
        <h3>管理员账号设置</h3>
        <form method="post" action="?step=4" id="finalForm" onsubmit="return checkForm()">
            <input type="hidden" name="action" value="install">
            <!-- 携带步骤2的数据库配置 -->
            <?php echo hiddenFields($saved); ?>

            <div class="form-row">
                <label>站点名称</label>
                <input type="text" name="site_name" value="晚风影视" placeholder="站点名称">
            </div>
            <div class="form-row">
                <label>管理员用户名 <span class="required">*</span></label>
                <input type="text" name="admin_user" value="admin" placeholder="管理员登录用户名" required>
            </div>
            <div class="form-row">
                <label>管理员密码 <span class="required">*</span></label>
                <input type="password" name="admin_pass" id="admin_pass" placeholder="至少6位密码" required minlength="6">
            </div>
            <div class="form-row">
                <label>确认密码 <span class="required">*</span></label>
                <input type="password" name="admin_pass2" id="admin_pass2" placeholder="再次输入密码" required>
            </div>

            <div class="btn-group">
                <a href="?step=2" class="btn btn-outline">上一步</a>
                <button type="submit" class="btn btn-primary">开始安装</button>
            </div>
        </form>
    </div>

    <?php elseif ($step === 4): ?>
    <!-- ============ 步骤4：安装完成 ============ -->
    <div class="install-body" style="text-align:center; padding: 40px 0;">
        <?php if ($success): ?>
            <h3 style="color:#27ae60;">安装成功</h3>
            <p style="margin:16px 0;">系统已成功安装，即将跳转至管理后台...</p>
            <p><a href="/admin/login.php" class="btn btn-primary">进入管理后台</a></p>
        <?php else: ?>
            <h3 style="color:#e74c3c;">安装出错</h3>
            <p style="margin:16px 0;"><?php echo $error ?: '未知错误'; ?></p>
            <a href="?step=1" class="btn btn-outline">重新安装</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
// 测试自有数据库连接
function checkDb() {
    var f = document.getElementById('step2Form');
    var data = new FormData();
    data.append('action', 'check_db');
    ['db_host','db_port','db_user','db_pass','db_name'].forEach(function(k) {
        data.append(k, f.querySelector('[name="' + k + '"]').value);
    });
    showResult('dbResult', data);
}

// 测试CMS数据库连接
function checkCmsDb() {
    var f = document.getElementById('step2Form');
    var data = new FormData();
    data.append('action', 'check_cms_db');
    ['cms_db_host','cms_db_port','cms_db_user','cms_db_pass','cms_db_name'].forEach(function(k) {
        data.append(k, f.querySelector('[name="' + k + '"]').value);
    });
    showResult('cmsDbResult', data);
}

function showResult(id, data) {
    var el = document.getElementById(id);
    el.textContent = '检测中...';
    el.className = 'test-result';
    fetch(window.location.href, { method: 'POST', body: data })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            el.textContent = res.msg;
            el.className = 'test-result ' + (res.code === 200 ? 'success' : 'fail');
        })
        .catch(function(e) {
            el.textContent = '请求失败: ' + e.message;
            el.className = 'test-result fail';
        });
}

// 步骤3提交前验证
function checkForm() {
    var p1 = document.getElementById('admin_pass').value;
    var p2 = document.getElementById('admin_pass2').value;
    if (p1 !== p2) { alert('两次输入的密码不一致'); return false; }
    if (p1.length < 6) { alert('密码至少6位'); return false; }
    return true;
}
</script>
</body>
</html>
