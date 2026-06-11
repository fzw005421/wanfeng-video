<?php
/**
 * 晚风影视 - 播放记录接口
 */

function handleHistory($method, $segments, $params) {
    $userId = getCurrentUserId();
    if (!$userId) {
        jsonResponse(401, '请先登录');
    }

    $action = $segments[1] ?? 'list';

    switch ($action) {
        case 'list':
            handleHistoryList($userId, $params);
            break;
        case 'save':
            handleHistorySave($userId, $params);
            break;
        case 'delete':
            $id = (int)($segments[2] ?? 0);
            handleHistoryDelete($userId, $id);
            break;
        case 'clear':
            handleHistoryClear($userId);
            break;
        default:
            // 兼容 RESTful 风格: DELETE /api/history/{id} (action 是数字)
            if (is_numeric($action)) {
                handleHistoryDelete($userId, (int)$action);
                break;
            }
            jsonResponse(404, '接口不存在');
    }
}

function handleHistoryList($userId, $params) {
    $page = max(1, (int)($params['page'] ?? 1));
    $pageSize = min(50, max(1, (int)($params['page_size'] ?? 20)));

    $sql = "SELECT id, vod_id, vod_name, vod_pic, episode_index, episode_name,
                   play_position, duration, parse_api_id, updated_at, created_at
            FROM wf_play_history WHERE user_id = ? ORDER BY updated_at DESC";

    $result = dbPaginate($sql, [$userId], $page, $pageSize);

    jsonResponse(200, '获取成功', $result);
}

function handleHistorySave($userId, $params) {
    $vodId = (int)($params['vod_id'] ?? 0);
    $episodeIndex = (int)($params['episode_index'] ?? 1);
    $playPosition = (int)($params['play_position'] ?? 0);
    $duration = (int)($params['duration'] ?? 0);
    $vodName = $params['vod_name'] ?? '';
    $vodPic = $params['vod_pic'] ?? '';
    $episodeName = $params['episode_name'] ?? '';
    $parseApiId = (int)($params['parse_api_id'] ?? 0);

    if ($vodId <= 0) {
        jsonResponse(400, '参数错误');
    }

    dbExecute(
        "INSERT INTO wf_play_history
         (user_id, vod_id, vod_name, vod_pic, episode_index, episode_name, play_position, duration, parse_api_id, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
         vod_name = VALUES(vod_name),
         vod_pic = VALUES(vod_pic),
         episode_name = VALUES(episode_name),
         play_position = VALUES(play_position),
         duration = VALUES(duration),
         parse_api_id = VALUES(parse_api_id),
         updated_at = NOW()",
        [$userId, $vodId, $vodName, $vodPic, $episodeIndex, $episodeName, $playPosition, $duration, $parseApiId]
    );

    jsonResponse(200, '保存成功');
}

function handleHistoryDelete($userId, $id) {
    if ($id <= 0) {
        jsonResponse(400, '参数错误');
    }

    $deleted = dbExecute("DELETE FROM wf_play_history WHERE id = ? AND user_id = ?", [$id, $userId]);
    if ($deleted > 0) {
        jsonResponse(200, '删除成功');
    } else {
        jsonResponse(404, '记录不存在');
    }
}

function handleHistoryClear($userId) {
    $deleted = dbExecute("DELETE FROM wf_play_history WHERE user_id = ?", [$userId]);
    jsonResponse(200, '已清空所有播放记录', ['deleted' => $deleted]);
}
