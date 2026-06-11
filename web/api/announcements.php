<?php
/**
 * 晚风影视 - 公告接口
 */

function handleAnnouncements($params) {
    $page = max(1, (int)($params['page'] ?? 1));
    $pageSize = min(20, (int)($params['page_size'] ?? 10));

    $sql = "SELECT id, title, content, created_at FROM wf_announcements WHERE status = 1 ORDER BY created_at DESC";
    $result = dbPaginate($sql, [], $page, $pageSize);

    jsonResponse(200, '获取成功', $result);
}
