<?php
/**
 * 晚风影视 - 管理后台公共包含文件
 */

require_once __DIR__ . '/../includes/init.php';

// 检查管理员登录状态
if (empty($_SESSION['admin_id'])) {
    header('Location: /admin/login.php');
    exit;
}

$adminId = $_SESSION['admin_id'];
$adminUsername = $_SESSION['admin_username'] ?? '';
$adminRole = $_SESSION['admin_role'] ?? 'admin';

// 获取系统统计
function getStats() {
    $userCount = dbQueryOne("SELECT COUNT(*) as cnt FROM wf_users")['cnt'] ?? 0;
    $historyCount = dbQueryOne("SELECT COUNT(*) as cnt FROM wf_play_history")['cnt'] ?? 0;
    $favCount = dbQueryOne("SELECT COUNT(*) as cnt FROM wf_favorites")['cnt'] ?? 0;
    $vodCount = 0;
    try {
        $vodCount = dbQueryOne("SELECT COUNT(*) as cnt FROM " . CMS_DB_PREFIX . "vod WHERE vod_status = 1", [], true)['cnt'] ?? 0;
    } catch (Exception $e) {}
    $parseCount = dbQueryOne("SELECT COUNT(*) as cnt FROM wf_parse_apis WHERE status = 1")['cnt'] ?? 0;

    return [
        'users' => $userCount,
        'history' => $historyCount,
        'favorites' => $favCount,
        'vods' => $vodCount,
        'parse_apis' => $parseCount,
    ];
}

// 获取当前页面
$currentPage = basename($_SERVER['SCRIPT_NAME']);

// 侧边栏菜单
function renderSidebar($currentPage) {
    $menus = [
        ['name' => '仪表盘', 'url' => '/admin/', 'icon' => 'dashboard'],
        ['name' => '用户管理', 'url' => '/admin/users.php', 'icon' => 'users'],
        ['name' => '公告管理', 'url' => '/admin/announcements.php', 'icon' => 'announce'],
        ['name' => '推荐管理', 'url' => '/admin/recommendations.php', 'icon' => 'recommend'],
        ['name' => '解析接口管理', 'url' => '/admin/parse_apis.php', 'icon' => 'parse'],
        ['name' => '系统设置', 'url' => '/admin/settings.php', 'icon' => 'settings'],
    ];

    $html = '<ul class="sidebar-menu">';
    foreach ($menus as $menu) {
        $active = ($currentPage === basename($menu['url']) || ($currentPage === $menu['url'])) ? 'active' : '';
        $html .= '<li class="' . $active . '">';
        $html .= '<a href="' . $menu['url'] . '">';
        $html .= '<span class="menu-icon">' . getMenuIcon($menu['icon']) . '</span>';
        $html .= '<span class="menu-text">' . $menu['name'] . '</span>';
        $html .= '</a></li>';
    }
    $html .= '</ul>';
    return $html;
}

function getMenuIcon($icon) {
    $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="7" r="4"/><path d="M1 21v-2a4 4 0 014-4h8a4 4 0 014 4v2"/><circle cx="18" cy="7" r="3"/><path d="M16 19h6v-1a3 3 0 00-3-3h-1"/></svg>',
        'announce' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>',
        'recommend' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26"/></svg>',
        'parse' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>',
    ];
    return $icons[$icon] ?? '';
}
