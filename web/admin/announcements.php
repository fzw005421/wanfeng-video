<?php
/**
 * 晚风影视 - 管理后台 公告管理
 */
require_once __DIR__ . '/includes.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'edit') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $status = (int)($_POST['status'] ?? 1);
        $id = (int)($_POST['id'] ?? 0);

        if (empty($title)) {
            $msg = '<div class="alert alert-error">标题不能为空</div>';
        } else {
            if ($id > 0) {
                dbExecute("UPDATE wf_announcements SET title=?, content=?, status=? WHERE id=?", [$title, $content, $status, $id]);
            } else {
                dbExecute("INSERT INTO wf_announcements (title, content, status) VALUES (?, ?, ?)", [$title, $content, $status]);
            }
            $msg = '<div class="alert alert-success">保存成功</div>';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        dbExecute("DELETE FROM wf_announcements WHERE id = ?", [$id]);
        $msg = '<div class="alert alert-success">已删除</div>';
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$result = dbPaginate("SELECT * FROM wf_announcements ORDER BY id DESC", [], $page, 15);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>公告管理 - 晚风影视管理后台</title>
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-brand"><h2>晚风影视</h2><small>管理后台</small></div>
        <?php echo renderSidebar('announcements.php'); ?>
        <div class="sidebar-footer"><a href="/admin/logout.php">退出登录</a></div>
    </aside>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">公告管理</div>
            <div class="topbar-right"><span>管理员：<?php echo $adminUsername; ?></span></div>
        </div>
        <div class="content-area">
            <?php echo $msg; ?>
            <div class="card">
                <div class="card-header">
                    <h3>公告列表</h3>
                    <button class="btn btn-primary btn-sm" onclick="openModal(0)">发布公告</button>
                </div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr><th>ID</th><th>标题</th><th>状态</th><th>发布时间</th><th>操作</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result['list'] as $a): ?>
                            <tr>
                                <td><?php echo $a['id']; ?></td>
                                <td><?php echo safeInput($a['title']); ?></td>
                                <td><span class="badge <?php echo $a['status']==1?'badge-success':'badge-default'; ?>"><?php echo $a['status']==1?'已发布':'草稿'; ?></span></td>
                                <td><?php echo $a['created_at']; ?></td>
                                <td>
                                    <button class="btn btn-outline btn-sm" onclick="openModal(<?php echo $a['id']; ?>, '<?php echo safeInput(addslashes($a['title'])); ?>', '<?php echo safeInput(addslashes($a['content'])); ?>', <?php echo $a['status']; ?>)">编辑</button>
                                    <form method="post" style="display:inline" onsubmit="return confirm('确认删除？')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                        <button type="submit" class="btn btn-outline btn-sm" style="color:#e74c3c;">删除</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($result['list'])): ?>
                            <tr><td colspan="5"><div class="empty-state">暂无公告</div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <?php if ($result['total_pages'] > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $result['total_pages']; $i++):
                            $class = $i == $page ? 'current' : '';
                            echo "<a href=\"?page={$i}\" class=\"{$class}\">{$i}</a>";
                        endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 编辑模态框 -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="modalTitle">发布公告</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="action" value="create" id="formAction">
                <input type="hidden" name="id" id="annId">
                <div class="form-group">
                    <label>标题</label>
                    <input type="text" name="title" id="annTitle" required placeholder="公告标题">
                </div>
                <div class="form-group">
                    <label>内容</label>
                    <textarea name="content" id="annContent" rows="5" placeholder="公告内容"></textarea>
                </div>
                <div class="form-group">
                    <label>状态</label>
                    <select name="status" id="annStatus">
                        <option value="1">发布</option>
                        <option value="0">草稿</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id, title, content, status) {
    document.getElementById('annId').value = id || '';
    document.getElementById('annTitle').value = title || '';
    document.getElementById('annContent').value = content || '';
    document.getElementById('annStatus').value = status || 1;
    document.getElementById('formAction').value = id ? 'edit' : 'create';
    document.getElementById('modalTitle').textContent = id ? '编辑公告' : '发布公告';
    document.getElementById('editModal').classList.add('show');
}
function closeModal() {
    document.getElementById('editModal').classList.remove('show');
}
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
</body>
</html>
