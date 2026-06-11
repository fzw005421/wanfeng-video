<?php
/**
 * 晚风影视 - 管理后台 推荐管理
 */
require_once __DIR__ . '/includes.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $vodId = (int)($_POST['vod_id'] ?? 0);
        $position = $_POST['position'] ?? 'home';
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($vodId > 0) {
            // 从CMS获取影片信息做快照
            $vod = dbQueryOne("SELECT vod_name, vod_pic FROM " . CMS_DB_PREFIX . "vod WHERE vod_id = ?", [$vodId], true);
            dbExecute(
                "INSERT INTO wf_recommendations (vod_id, vod_name, vod_pic, position, sort_order, status) VALUES (?, ?, ?, ?, ?, 1)",
                [$vodId, $vod['vod_name'] ?? '', $vod['vod_pic'] ?? '', $position, $sortOrder]
            );
            $msg = '<div class="alert alert-success">推荐添加成功</div>';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        dbExecute("DELETE FROM wf_recommendations WHERE id = ?", [$id]);
        $msg = '<div class="alert alert-success">已移除推荐</div>';
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $rec = dbQueryOne("SELECT status FROM wf_recommendations WHERE id = ?", [$id]);
        if ($rec) {
            $ns = $rec['status'] == 1 ? 0 : 1;
            dbExecute("UPDATE wf_recommendations SET status = ? WHERE id = ?", [$ns, $id]);
        }
        $msg = '<div class="alert alert-success">状态已更新</div>';
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$position = $_GET['position'] ?? '';

$where = '1=1';
$params = [];
if ($position) {
    $where = 'position = ?';
    $params = [$position];
}

$sql = "SELECT * FROM wf_recommendations WHERE {$where} ORDER BY position, sort_order ASC, id DESC";
$result = dbPaginate($sql, $params, $page, 20);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>推荐管理 - 晚风影视管理后台</title>
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-brand"><h2>晚风影视</h2><small>管理后台</small></div>
        <?php echo renderSidebar('recommendations.php'); ?>
        <div class="sidebar-footer"><a href="/admin/logout.php">退出登录</a></div>
    </aside>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">推荐管理</div>
            <div class="topbar-right"><span>管理员：<?php echo $adminUsername; ?></span></div>
        </div>
        <div class="content-area">
            <?php echo $msg; ?>
            <div class="card">
                <div class="card-header">
                    <h3>推荐列表</h3>
                    <div class="btn-group">
                        <form class="form-inline" style="margin:0">
                            <select name="position" style="height:32px;border-radius:4px;border:1px solid #d9d9d9;padding:0 8px;">
                                <option value="">全部位置</option>
                                <option value="banner" <?php echo $position=='banner'?'selected':''; ?>>轮播图</option>
                                <option value="home" <?php echo $position=='home'?'selected':''; ?>>首页推荐</option>
                            </select>
                            <button type="submit" class="btn btn-outline btn-sm">筛选</button>
                        </form>
                        <button class="btn btn-primary btn-sm" onclick="openModal()">添加推荐</button>
                    </div>
                </div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr><th>ID</th><th>影片ID</th><th>影片名称</th><th>位置</th><th>排序</th><th>状态</th><th>操作</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result['list'] as $r): ?>
                            <tr>
                                <td><?php echo $r['id']; ?></td>
                                <td><?php echo $r['vod_id']; ?></td>
                                <td><?php echo safeInput($r['vod_name'] ?: '-'); ?></td>
                                <td><span class="badge <?php echo $r['position']=='banner'?'badge-success':'badge-default'; ?>"><?php echo $r['position']=='banner'?'轮播图':'首页推荐'; ?></span></td>
                                <td><?php echo $r['sort_order']; ?></td>
                                <td><span class="badge <?php echo $r['status']==1?'badge-success':'badge-danger'; ?>"><?php echo $r['status']==1?'启用':'禁用'; ?></span></td>
                                <td>
                                    <div class="btn-group">
                                        <form method="post" style="display:inline">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                            <button type="submit" class="btn btn-outline btn-sm"><?php echo $r['status']==1?'禁用':'启用'; ?></button>
                                        </form>
                                        <form method="post" style="display:inline" onsubmit="return confirm('确认移除推荐？')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                            <button type="submit" class="btn btn-outline btn-sm" style="color:#e74c3c;">移除</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($result['list'])): ?>
                            <tr><td colspan="7"><div class="empty-state">暂无推荐数据，请从苹果CMS中选择影片添加</div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 添加推荐模态框 -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>添加推荐</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>苹果CMS影片ID</label>
                    <input type="number" name="vod_id" required placeholder="请输入CMS中的影片ID">
                    <span class="help-text">在苹果CMS后台查看影片对应的vod_id</span>
                </div>
                <div class="form-group">
                    <label>推荐位置</label>
                    <select name="position">
                        <option value="home">首页推荐</option>
                        <option value="banner">轮播图</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>排序 (数值越小越靠前)</label>
                    <input type="number" name="sort_order" value="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">取消</button>
                <button type="submit" class="btn btn-primary">添加</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() { document.getElementById('addModal').classList.add('show'); }
function closeModal() { document.getElementById('addModal').classList.remove('show'); }
document.getElementById('addModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
</script>
</body>
</html>
