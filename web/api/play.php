<?php
/**
 * 晚风影视 - 视频播放/解析接口
 * 核心流程：从 mac_vod 获取原始链接 → 调用解析API → 返回m3u8地址
 */

function handlePlay($params) {
    $userId = getCurrentUserId();
    if (!$userId) {
        jsonResponse(401, '请先登录');
    }

    $vodId = (int)($params['vod_id'] ?? 0);
    $episodeIndex = (int)($params['episode_index'] ?? 1);
    $parseApiId = (int)($params['parse_api_id'] ?? 0);
    $sourceIndex = (int)($params['source_index'] ?? 0);

    if ($vodId <= 0) {
        jsonResponse(400, '请指定影片ID');
    }
    if ($episodeIndex < 1) {
        $episodeIndex = 1;
    }
    if ($sourceIndex < 0) {
        $sourceIndex = 0;
    }

    // 1. 从苹果CMS获取播放地址 — 播放地址在 mac_vod 表中
    $pdo = getCmsDbConnection();

    // 检查实际存在的列
    $cols = [];
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM " . CMS_DB_PREFIX . "vod");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (Exception $e) {
        jsonResponse(500, 'CMS数据库表结构读取失败');
    }

    $hasVodPlayUrl = in_array('vod_play_url', $cols);
    $hasVodPlayName = in_array('vod_play_name', $cols);

    if (!$hasVodPlayUrl) {
        jsonResponse(404, '该影片暂无播放地址（vod_play_url列不存在）');
    }

    // 构建列表达式（已含别名，不要在外层SQL重复加 as）
    $vodNameExpr = in_array('vod_name', $cols) ? 'v.vod_name' : "'' as vod_name";
    $vodPicExpr  = in_array('vod_pic', $cols)  ? 'v.vod_pic'  : "'' as vod_pic";
    $playNameExpr = $hasVodPlayName ? 'v.vod_play_name' : "'' as vod_play_name";

    $vod = dbQueryOne(
        "SELECT {$vodNameExpr}, {$vodPicExpr},
                {$playNameExpr}, v.vod_play_url
         FROM " . CMS_DB_PREFIX . "vod v WHERE v.vod_id = ?",
        [$vodId],
        true
    );

    if (!$vod || empty($vod['vod_play_url'])) {
        jsonResponse(404, '该影片暂无播放地址');
    }

    // 2. 解析播放列表 — 先按 $$$ 拆分播放源，再按 # 拆分剧集
    $urlSourceBlocks = explode('$$$', $vod['vod_play_url']);
    $nameSourceBlocks = explode('$$$', $vod['vod_play_name'] ?? '');

    // 选择对应来源的剧集块（越界回退到第0个）
    if (!isset($urlSourceBlocks[$sourceIndex])) {
        $sourceIndex = 0;
    }
    $sourceUrlBlock = trim($urlSourceBlocks[$sourceIndex]);
    $sourceNameBlock = trim($nameSourceBlocks[$sourceIndex] ?? '');

    if (empty($sourceUrlBlock)) {
        jsonResponse(404, '该播放源暂无播放地址');
    }

    $urlParts = explode('#', $sourceUrlBlock);
    $nameParts = explode('#', $sourceNameBlock);

    $originalUrl = '';
    $episodeName = '';

    if ($episodeIndex <= count($urlParts)) {
        $part = trim($urlParts[$episodeIndex - 1]);
        $pair = explode('$', $part, 2);
        $episodeName = $pair[0] ?? ('第' . $episodeIndex . '集');
        $originalUrl = $pair[1] ?? $part;

        // 处理未用$分隔的情况（整段是URL）
        if (!empty($pair[0]) && empty($pair[1]) && strpos($pair[0], 'http') === 0) {
            $originalUrl = $pair[0];
        }

        // 用 vod_play_name 中的名称（如果存在且不含URL）
        if (isset($nameParts[$episodeIndex - 1])) {
            $nameItem = trim($nameParts[$episodeIndex - 1]);
            if (!empty($nameItem) && strpos($nameItem, 'http') !== 0 && strpos($nameItem, '.m3u8') === false) {
                $episodeName = $nameItem;
            }
        }
    }

    if (empty($originalUrl) || strpos($originalUrl, 'http') !== 0) {
        jsonResponse(404, '该集数播放地址未找到或格式不正确');
    }

    // 3. 获取解析接口
    $parseApi = null;
    if ($parseApiId > 0) {
        $parseApi = dbQueryOne(
            "SELECT id, name, url_template FROM wf_parse_apis WHERE id = ? AND status = 1",
            [$parseApiId]
        );
    }

    // 如果未指定或用指定接口未找到，使用默认接口
    if (!$parseApi) {
        $parseApi = dbQueryOne(
            "SELECT id, name, url_template FROM wf_parse_apis WHERE status = 1 ORDER BY sort_order ASC, id ASC LIMIT 1"
        );
    }

    if (!$parseApi) {
        jsonResponse(500, '没有可用的解析接口，请联系管理员');
    }

    // 4. 调用解析API
    $parseUrl = str_replace('{url}', urlencode($originalUrl), $parseApi['url_template']);
    $parseResult = callParseApi($parseUrl);

    if (!$parseResult || !isset($parseResult['url']) || empty($parseResult['url'])) {
        jsonResponse(500, '视频解析失败，请尝试切换解析接口');
    }

    $videoUrl = $parseResult['url'];
    $videoType = $parseResult['type'] ?? 'hls';
    $vodName = $vod['vod_name'] ?? '';

    // 5. 保存播放记录（按用户+影片+集数去重，避免唯一键冲突）
    dbExecute(
        "INSERT INTO wf_play_history (user_id, vod_id, vod_name, vod_pic, episode_index, episode_name, parse_api_id, play_position, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())
         ON DUPLICATE KEY UPDATE
         vod_name = VALUES(vod_name),
         vod_pic = VALUES(vod_pic),
         episode_index = VALUES(episode_index),
         episode_name = VALUES(episode_name),
         parse_api_id = VALUES(parse_api_id),
         play_position = 0,
         updated_at = NOW()",
        [$userId, $vodId, $vodName, $vod['vod_pic'] ?? '', $episodeIndex, $episodeName, $parseApi['id']]
    );

    jsonResponse(200, '解析成功', [
        'video_url' => $videoUrl,
        'type' => $videoType,
        'vod_id' => $vodId,
        'vod_name' => $vodName,
        'episode_index' => $episodeIndex,
        'episode_name' => $episodeName,
        'parse_api_name' => $parseApi['name'],
        'parse_api_id' => $parseApi['id']
    ]);
}

/**
 * 调用解析API
 */
function callParseApi($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) return null;
    if ($httpCode !== 200) return null;

    $result = json_decode($response, true);
    return $result;
}
