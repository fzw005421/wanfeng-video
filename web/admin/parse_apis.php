<?php
/**
 * 晚风影视 - 管理后台 解析接口管理
 */
require_once __DIR__ . '/includes.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $urlTemplate = trim($_POST['url_template'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $status = (int)($_POST['status'] ?? 1);
        $id = (int)($_POST['id'] ?? 0);

        if (empty($name) || empty($urlTemplate)) {
            $msg = '<div class="alert alert-error">接口名称和URL模板为必填</div>';
        } else {
            if ($id > 0) {
                dbExecute("UPDATE wf_parse_apis SET name=?, url_template=?, sort_order=?, status=? WHERE id=?",
                    [$name, $urlTemplate, $sortOrder, $status, $id]);
            } else {
                dbExecute("INSERT INTO wf_parse_apis (name, url_template, sort_order, status) VALUES (?, ?, ?, ?)",
                    [$name, $urlTemplate, $sortOrder, $status]);
            }
            $msg = '<div class="alert alert-success">保存成功</div>';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        dbExecute("DELETE FROM wf_parse_apis WHERE id = ?", [$id]);
        $msg = '<div class="alert alert-success">已删除</div>';
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $api = dbQueryOne("SELECT status FROM wf_parse_apis WHERE id = ?", [$id]);
        if ($api) {
            $ns = $api['status'] == 1 ? 0 : 1;
            dbExecute("UPDATE wf_parse_apis SET status = ? WHERE id = ?", [$ns, $id]);
        }
        $msg = '<div class="alert alert-success">状态已更新</div>';
    }
}

$result = dbQuery("SELECT * FROM wf_parse_apis ORDER BY sort_order ASC, id ASC");
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>解析接口管理 - 晚风影视管理后台</title>
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-brand"><h2>晚风影视</h2><small>管理后台</small></div>
        <?php echo renderSidebar('parse_apis.php'); ?>
        <div class="sidebar-footer"><a href="/admin/logout.php">退出登录</a></div>
    </aside>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">解析接口管理</div>
            <div class="topbar-right"><span>管理员：<?php echo $adminUsername; ?></span></div>
        </div>
        <div class="content-area">
            <?php echo $msg; ?>
            <div class="card">
                <div class="card-header">
                    <h3>解析接口列表</h3>
                    <button class="btn btn-primary btn-sm" onclick="openModal(0)">添加接口</button>
                </div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr><th>ID</th><th>接口名称</th><th>URL模板</th><th>排序</th><th>状态</th><th>操作</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result as $api): ?>
                            <tr>
                                <td><?php echo $api['id']; ?></td>
                                <td><?php echo safeInput($api['name']); ?></td>
                                <td style="max-width:300px;word-break:break-all;font-size:13px;"><?php echo safeInput($api['url_template']); ?></td>
                                <td><?php echo $api['sort_order']; ?></td>
                                <td><span class="badge <?php echo $api['status']==1?'badge-success':'badge-danger'; ?>"><?php echo $api['status']==1?'启用':'禁用'; ?></span></td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-outline btn-sm" onclick="openModal(<?php echo $api['id']; ?>, '<?php echo safeInput(addslashes($api['name'])); ?>', '<?php echo safeInput(addslashes($api['url_template'])); ?>', <?php echo $api['sort_order']; ?>, <?php echo $api['status']; ?>)">编辑</button>
                                        <form method="post" style="display:inline">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?php echo $api['id']; ?>">
                                            <button type="submit" class="btn btn-outline btn-sm"><?php echo $api['status']==1?'禁用':'启用'; ?></button>
                                        </form>
                                        <form method="post" style="display:inline" onsubmit="return confirm('确认删除？')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $api['id']; ?>">
                                            <button type="submit" class="btn btn-outline btn-sm" style="color:#e74c3c;">删除</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($result)): ?>
                            <tr><td colspan="6"><div class="empty-state">暂无解析接口，请添加</div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div style="margin-top:16px;padding:12px;background:#fffbe6;border-radius:6px;font-size:13px;color:#8c6d00;">
                        URL模板说明：使用 <code>{url}</code> 作为占位符代表视频原始链接。<br>示例：<code>http://154.201.94.39/2.php?url={url}</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 编辑模态框 -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="modalTitle">添加解析接口</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="action" value="create" id="formAction">
                <input type="hidden" name="id" id="apiId">
                <div class="form-group">
                    <label>接口名称</label>
                    <input type="text" name="name" id="apiName" required placeholder="例如：解析线路1">
                </div>
                <div class="form-group">
                    <label>URL模板</label>
                    <input type="url" name="url_template" id="apiUrl" required placeholder="http://xxx.com/api.php?url={url}">
                    <span class="help-text">{url} 会被替换为视频原始链接</span>
                </div>
                <div class="form-group">
                    <label>排序 (数值越小越靠前)</label>
                    <input type="number" name="sort_order" id="apiSort" value="0">
                </div>
                <div class="form-group">
                    <label>状态</label>
                    <select name="status" id="apiStatus">
                        <option value="1">启用</option>
                        <option value="0">禁用</option>
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
function openModal(id, name, url, sort, status) {
    document.getElementById('apiId').value = id || '';
    document.getElementById('apiName').value = name || '';
    document.getElementById('apiUrl').value = url || '';
    document.getElementById('apiSort').value = sort || 0;
    document.getElementById('apiStatus').value = status !== undefined ? status : 1;
    document.getElementById('formAction').value = id ? 'edit' : 'create';
    document.getElementById('modalTitle').textContent = id ? '编辑解析接口' : '添加解析接口';
    document.getElementById('editModal').classList.add('show');
}
function closeModal() { document.getElementById('editModal').classList.remove('show'); }
document.getElementById('editModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
</script>
</body>
</html>
