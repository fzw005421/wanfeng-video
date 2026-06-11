<?php
/**
 * 晚风影视 - 管理后台 仪表盘
 */
require_once __DIR__ . '/includes.php';
$stats = getStats();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>仪表盘 - 晚风影视管理后台</title>
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <!-- 侧边栏 -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <h2>晚风影视</h2>
            <small>管理后台</small>
        </div>
        <?php echo renderSidebar('index.php'); ?>
        <div class="sidebar-footer">
            <a href="/admin/logout.php">退出登录</a>
        </div>
    </aside>

    <!-- 主内容 -->
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">仪表盘</div>
            <div class="topbar-right">
                <span>管理员：<?php echo $adminUsername; ?></span>
            </div>
        </div>
        <div class="content-area">
            <!-- 统计卡片 -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">U</div>
                    <div class="stat-info">
                        <h4><?php echo $stats['users']; ?></h4>
                        <p>注册用户</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon teal">V</div>
                    <div class="stat-info">
                        <h4><?php echo $stats['vods']; ?></h4>
                        <p>影视资源</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">H</div>
                    <div class="stat-info">
                        <h4><?php echo $stats['history']; ?></h4>
                        <p>播放记录</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange">F</div>
                    <div class="stat-info">
                        <h4><?php echo $stats['favorites']; ?></h4>
                        <p>收藏记录</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple">P</div>
                    <div class="stat-info">
                        <h4><?php echo $stats['parse_apis']; ?></h4>
                        <p>解析接口</p>
                    </div>
                </div>
            </div>

            <!-- 最新用户 -->
            <div class="card">
                <div class="card-header">
                    <h3>最新注册用户</h3>
                    <a href="/admin/users.php" class="btn btn-outline btn-sm">查看全部</a>
                </div>
                <div class="card-body">
                    <?php
                    $latestUsers = dbQuery("SELECT id, username, nickname, last_login, status, created_at FROM wf_users ORDER BY id DESC LIMIT 8");
                    if ($latestUsers): ?>
                    <table>
                        <thead>
                            <tr><th>ID</th><th>用户名</th><th>昵称</th><th>最后登录</th><th>状态</th><th>注册时间</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($latestUsers as $u): ?>
                            <tr>
                                <td><?php echo $u['id']; ?></td>
                                <td><?php echo safeInput($u['username']); ?></td>
                                <td><?php echo safeInput($u['nickname'] ?: '-'); ?></td>
                                <td><?php echo $u['last_login'] ?: '-'; ?></td>
                                <td><span class="badge <?php echo $u['status'] == 1 ? 'badge-success' : 'badge-danger'; ?>"><?php echo $u['status'] == 1 ? '正常' : '禁用'; ?></span></td>
                                <td><?php echo $u['created_at']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">暂无用户数据</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
