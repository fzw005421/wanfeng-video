<?php
/**
 * 晚风影视 - 管理后台 用户管理
 */
require_once __DIR__ . '/includes.php';

$msg = '';
$error = '';

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_status') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $user = dbQueryOne("SELECT status FROM wf_users WHERE id = ?", [$uid]);
        if ($user) {
            $newStatus = $user['status'] == 1 ? 0 : 1;
            dbExecute("UPDATE wf_users SET status = ? WHERE id = ?", [$newStatus, $uid]);
            $msg = $newStatus == 1 ? '用户已启用' : '用户已禁用';
        }
    } elseif ($action === 'reset_password') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $newPass = $_POST['new_password'] ?? '';
        if (strlen($newPass) < 6) {
            $error = '密码至少6位';
        } else {
            dbExecute("UPDATE wf_users SET password = ? WHERE id = ?", [passwordHash($newPass), $uid]);
            $msg = '密码已重置';
        }
    }
}

// 查询
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');

$where = '1=1';
$params = [];
if ($search) {
    $where = '(username LIKE ? OR nickname LIKE ?)';
    $params = ['%' . $search . '%', '%' . $search . '%'];
}

$sql = "SELECT id, username, nickname, status, last_login, login_ip, created_at FROM wf_users WHERE {$where} ORDER BY id DESC";
$result = dbPaginate($sql, $params, $page, 20);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理 - 晚风影视管理后台</title>
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-brand"><h2>晚风影视</h2><small>管理后台</small></div>
        <?php echo renderSidebar('users.php'); ?>
        <div class="sidebar-footer"><a href="/admin/logout.php">退出登录</a></div>
    </aside>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">用户管理</div>
            <div class="topbar-right"><span>管理员：<?php echo $adminUsername; ?></span></div>
        </div>
        <div class="content-area">
            <?php if ($msg): ?><div class="alert alert-success"><?php echo $msg; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3>用户列表</h3>
                    <form class="search-box" method="get">
                        <input type="text" name="search" placeholder="搜索用户名/昵称..." value="<?php echo safeInput($search); ?>">
                        <button type="submit" class="btn btn-primary btn-sm">搜索</button>
                        <?php if ($search): ?><a href="?" class="btn btn-outline btn-sm">清除</a><?php endif; ?>
                    </form>
                </div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr><th>ID</th><th>用户名</th><th>昵称</th><th>最后登录</th><th>IP</th><th>状态</th><th>注册时间</th><th>操作</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result['list'] as $u): ?>
                            <tr>
                                <td><?php echo $u['id']; ?></td>
                                <td><?php echo safeInput($u['username']); ?></td>
                                <td><?php echo safeInput($u['nickname'] ?: '-'); ?></td>
                                <td><?php echo $u['last_login'] ?: '-'; ?></td>
                                <td><?php echo $u['login_ip'] ?: '-'; ?></td>
                                <td><span class="badge <?php echo $u['status'] == 1 ? 'badge-success' : 'badge-danger'; ?>"><?php echo $u['status'] == 1 ? '正常' : '禁用'; ?></span></td>
                                <td><?php echo $u['created_at']; ?></td>
                                <td>
                                    <div class="btn-group">
                                        <form method="post" style="display:inline" onsubmit="return confirm('确认<?php echo $u['status'] == 1 ? '禁用' : '启用'; ?>该用户？')">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" class="btn btn-outline btn-sm"><?php echo $u['status'] == 1 ? '禁用' : '启用'; ?></button>
                                        </form>
                                        <button class="btn btn-outline btn-sm" onclick="showResetPwd(<?php echo $u['id']; ?>, '<?php echo safeInput($u['username']); ?>')">重置密码</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($result['list'])): ?>
                            <tr><td colspan="8"><div class="empty-state">暂无用户数据</div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- 分页 -->
                    <?php if ($result['total_pages'] > 1): ?>
                    <div class="pagination">
                        <?php
                        $queryStr = $search ? '&search=' . urlencode($search) : '';
                        if ($page > 1) echo '<a href="?page=' . ($page - 1) . $queryStr . '">上一页</a>';
                        for ($i = max(1, $page - 2); $i <= min($result['total_pages'], $page + 2); $i++) {
                            $class = $i == $page ? 'current' : '';
                            echo "<a href=\"?page={$i}{$queryStr}\" class=\"{$class}\">{$i}</a>";
                        }
                        if ($page < $result['total_pages']) echo '<a href="?page=' . ($page + 1) . $queryStr . '">下一页</a>';
                        ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 重置密码模态框 -->
<div class="modal-overlay" id="resetPwdModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>重置用户密码</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="resetUid">
                <p>正在为用户 <strong id="resetUname"></strong> 重置密码</p>
                <div class="form-group" style="margin-top:12px">
                    <label>新密码</label>
                    <input type="password" name="new_password" placeholder="至少6位" required minlength="6">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">取消</button>
                <button type="submit" class="btn btn-primary">确认重置</button>
            </div>
        </form>
    </div>
</div>

<script>
function showResetPwd(uid, uname) {
    document.getElementById('resetUid').value = uid;
    document.getElementById('resetUname').textContent = uname;
    document.getElementById('resetPwdModal').classList.add('show');
}
function closeModal() {
    document.getElementById('resetPwdModal').classList.remove('show');
}
document.getElementById('resetPwdModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
</body>
</html>
