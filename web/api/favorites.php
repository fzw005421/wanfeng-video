<?php
/**
 * 晚风影视 - 收藏接口
 */

function handleFavorites($method, $segments, $params) {
    $userId = getCurrentUserId();
    if (!$userId) {
        jsonResponse(401, '请先登录');
    }

    $action = $segments[1] ?? 'list';

    switch ($action) {
        case 'list':
            handleFavList($userId, $params);
            break;
        case 'toggle':
            handleFavToggle($userId, $params);
            break;
        case 'check':
            handleFavCheck($userId, $params);
            break;
        default:
            jsonResponse(404, '接口不存在');
    }
}

function handleFavList($userId, $params) {
    $page = max(1, (int)($params['page'] ?? 1));
    $pageSize = min(50, (int)($params['page_size'] ?? 20));

    // 直接查自有数据库，不依赖 CMS
    $sql = "SELECT id, vod_id, vod_name, vod_pic, vod_remarks, vod_score, vod_year, vod_area, created_at
            FROM wf_favorites
            WHERE user_id = ?
            ORDER BY created_at DESC";

    $result = dbPaginate($sql, [$userId], $page, $pageSize);

    jsonResponse(200, '获取成功', $result);
}

function handleFavToggle($userId, $params) {
    $vodId = (int)($params['vod_id'] ?? 0);
    if ($vodId <= 0) {
        jsonResponse(400, '参数错误');
    }

    $exists = dbQueryOne("SELECT id FROM wf_favorites WHERE user_id = ? AND vod_id = ?", [$userId, $vodId]);

    if ($exists) {
        dbExecute("DELETE FROM wf_favorites WHERE user_id = ? AND vod_id = ?", [$userId, $vodId]);
        jsonResponse(200, '已取消收藏', ['favorited' => false]);
    } else {
        // 收藏时保存影片元数据到自有数据库
        $vodName    = $params['vod_name'] ?? '';
        $vodPic     = $params['vod_pic'] ?? '';
        $vodRemarks = $params['vod_remarks'] ?? '';
        $vodScore   = $params['vod_score'] ?? '';
        $vodYear    = $params['vod_year'] ?? '';
        $vodArea    = $params['vod_area'] ?? '';

        dbExecute(
            "INSERT INTO wf_favorites (user_id, vod_id, vod_name, vod_pic, vod_remarks, vod_score, vod_year, vod_area)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$userId, $vodId, $vodName, $vodPic, $vodRemarks, $vodScore, $vodYear, $vodArea]
        );
        jsonResponse(200, '收藏成功', ['favorited' => true]);
    }
}

function handleFavCheck($userId, $params) {
    $vodId = (int)($params['vod_id'] ?? 0);
    if ($vodId <= 0) {
        jsonResponse(400, '参数错误');
    }

    $exists = dbQueryOne("SELECT id FROM wf_favorites WHERE user_id = ? AND vod_id = ?", [$userId, $vodId]);
    jsonResponse(200, '获取成功', ['favorited' => (bool)$exists]);
}
