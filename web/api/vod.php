<?php
/**
 * 晚风影视 - 影视数据接口
 * 从苹果CMS数据库（只读）获取影视数据
 */

function handleVod($method, $segments, $params) {
    $action = $segments[1] ?? 'list';

    switch ($action) {
        case 'list':
            handleVodList($params);
            break;
        case 'recommend':
            handleVodRecommend($params);
            break;
        case 'search':
            handleVodSearch($params);
            break;
        default:
            if (is_numeric($action)) {
                handleVodDetail((int)$action);
            } else {
                jsonResponse(404, '接口不存在');
            }
    }
}

// ---- 获取 mac_vod 表实际存在的列（带文件缓存） ----
function getVodColumns($pdo) {
    return getTableColumns($pdo, CMS_DB_PREFIX . 'vod');
}

// ---- 构建安全的 SELECT 字段 ----
function buildVodSelectFields($alias = 'v') {
    $pdo = getCmsDbConnection();
    $allFields = [
        'vod_id', 'vod_name', 'vod_sub', 'vod_en',
        'type_id', 'type_id_1',
        'vod_class', 'vod_tag',
        'vod_pic', 'vod_pic_thumb', 'vod_pic_slide',
        'vod_blurb', 'vod_content',
        'vod_year', 'vod_area', 'vod_lang',
        'vod_actor', 'vod_director', 'vod_writer',
        'vod_remarks', 'vod_note',
        'vod_state', 'vod_version', 'vod_serial',
        'vod_total', 'vod_isend', 'vod_weekday',
        'vod_duration', 'vod_time',
        'vod_hits', 'vod_hits_day', 'vod_hits_week', 'vod_hits_month',
        'vod_score', 'vod_score_all', 'vod_score_num',
        'vod_up', 'vod_down',
        'vod_time_add', 'vod_time_hits', 'vod_time_make',
        'vod_trysee', 'vod_jumpurl',
        'vod_server', 'vod_play_url', 'vod_down_url',
        'vod_rel_vod', 'vod_rel_art',
        'vod_status',
    ];
    $cols = getVodColumns($pdo);
    $existing = [];
    foreach ($allFields as $f) {
        if (in_array($f, $cols)) {
            $existing[] = "{$alias}.{$f}";
        }
    }
    return implode(', ', $existing);
}

// ---- 获取状态过滤列 ----
function getVodStatusCol($pdo) {
    $cols = getVodColumns($pdo);
    if (in_array('vod_status', $cols)) return 'vod_status = 1';
    if (in_array('vod_state', $cols)) return "vod_state = '1'";
    return '1 = 1';
}

// ---- 格式化影片数据 ----
function formatVodItem($item) {
    $intFields = [
        'vod_id', 'type_id', 'type_id_1', 'vod_total', 'vod_serial',
        'vod_hits', 'vod_hits_day', 'vod_hits_week', 'vod_hits_month',
        'vod_up', 'vod_down', 'vod_score_num'
    ];
    foreach ($intFields as $f) {
        if (isset($item[$f])) $item[$f] = (int)$item[$f];
    }
    $floatFields = ['vod_score', 'vod_score_all', 'vod_duration'];
    foreach ($floatFields as $f) {
        if (isset($item[$f])) $item[$f] = (float)$item[$f];
    }
    // 修复图片URL
    if (!empty($item['vod_pic']) && strpos($item['vod_pic'], 'http') !== 0) {
        $item['vod_pic'] = fixImageUrl($item['vod_pic']);
    }
    return $item;
}

// ==================== 影视列表 ====================
function handleVodList($params) {
    $page = max(1, (int)($params['page'] ?? 1));
    $pageSize = min(50, max(1, (int)($params['page_size'] ?? 20)));
    $typeId = (int)($params['type_id'] ?? 0);
    $order = $params['order'] ?? 'vod_time';

    $allowedOrders = ['vod_time', 'vod_hits', 'vod_score', 'vod_id', 'vod_time_add'];
    if (!in_array($order, $allowedOrders)) {
        $order = 'vod_time';
    }

    $pdo = getCmsDbConnection();
    $statusCol = getVodStatusCol($pdo);
    $cols = getVodColumns($pdo);

    $where = "WHERE {$statusCol}";
    $bindParams = [];

    if ($typeId > 0) {
        $where .= ' AND type_id = ?';
        $bindParams[] = $typeId;
    }

    // 选择实际存在的排序列
    $orderCol = in_array($order, $cols) ? $order : 'vod_id';

    $selectFields = buildVodSelectFields('v');

    $sql = "SELECT {$selectFields}
            FROM " . CMS_DB_PREFIX . "vod v {$where} ORDER BY v.{$orderCol} DESC";

    $result = dbPaginate($sql, $bindParams, $page, $pageSize, true);

    foreach ($result['list'] as &$vod) {
        $vod = formatVodItem($vod);
    }

    jsonResponse(200, '获取成功', $result);
}

// ==================== 推荐影视 ====================
function handleVodRecommend($params) {
    $page = max(1, (int)($params['page'] ?? 1));
    $pageSize = min(30, max(1, (int)($params['page_size'] ?? 10)));
    $position = $params['position'] ?? 'home';

    $recList = dbPaginate(
        "SELECT r.vod_id, r.vod_name, r.vod_pic, r.sort_order
         FROM wf_recommendations r
         WHERE r.position = ? AND r.status = 1
         ORDER BY r.sort_order ASC",
        [$position],
        $page,
        $pageSize
    );

    // 批量加载缺失的 CMS 数据（修复 N+1 查询）
    $missingIds = [];
    foreach ($recList['list'] as $rec) {
        if (empty($rec['vod_name'])) {
            $missingIds[] = (int)$rec['vod_id'];
        }
    }

    $cmsData = [];
    if (!empty($missingIds)) {
        $pdo = getCmsDbConnection();
        $selectFields = buildVodSelectFields('v');
        // 批量查询代替逐条查询
        $placeholders = implode(',', array_fill(0, count($missingIds), '?'));
        $rows = dbQuery(
            "SELECT {$selectFields}
             FROM " . CMS_DB_PREFIX . "vod v
             WHERE v.vod_id IN ({$placeholders}) AND " . getVodStatusCol($pdo),
            $missingIds,
            true
        );
        foreach ($rows as $row) {
            $row = formatVodItem($row);
            $cmsData[(int)$row['vod_id']] = $row;
        }
    }

    foreach ($recList['list'] as &$rec) {
        $vid = (int)$rec['vod_id'];
        if (empty($rec['vod_name']) && isset($cmsData[$vid])) {
            $vod = $cmsData[$vid];
            $rec['vod_name'] = $vod['vod_name'] ?? '';
            $rec['vod_pic'] = $vod['vod_pic'] ?? '';
            $rec['vod_remarks'] = $vod['vod_remarks'] ?? '';
            $rec['vod_score'] = $vod['vod_score'] ?? '';
            $rec['vod_year'] = $vod['vod_year'] ?? '';
            $rec['vod_area'] = $vod['vod_area'] ?? '';
        }
        if (!empty($rec['vod_pic']) && strpos($rec['vod_pic'], 'http') !== 0) {
            $rec['vod_pic'] = fixImageUrl($rec['vod_pic']);
        }
    }

    jsonResponse(200, '获取成功', $recList);
}

// ==================== 搜索 ====================
function handleVodSearch($params) {
    $keyword = trim($params['kw'] ?? $params['keyword'] ?? '');
    if (empty($keyword)) {
        jsonResponse(400, '请输入搜索关键词');
    }

    $page = max(1, (int)($params['page'] ?? 1));
    $pageSize = min(50, max(1, (int)($params['page_size'] ?? 20)));

    $like = '%' . $keyword . '%';
    $selectFields = buildVodSelectFields('v');
    $pdo = getCmsDbConnection();

    $sql = "SELECT {$selectFields}
            FROM " . CMS_DB_PREFIX . "vod v
            WHERE " . getVodStatusCol($pdo) . "
            AND (v.vod_name LIKE ? OR v.vod_actor LIKE ? OR v.vod_director LIKE ?)
            ORDER BY v.vod_time_add DESC";

    $result = dbPaginate($sql, [$like, $like, $like], $page, $pageSize, true);

    foreach ($result['list'] as &$vod) {
        $vod = formatVodItem($vod);
    }

    jsonResponse(200, '获取成功', $result);
}

// ==================== 影视详情 ====================
function handleVodDetail($vodId) {
    $pdo = getCmsDbConnection();
    $selectFields = buildVodSelectFields('v');

    $vod = dbQueryOne(
        "SELECT {$selectFields}
         FROM " . CMS_DB_PREFIX . "vod v WHERE v.vod_id = ? AND " . getVodStatusCol($pdo),
        [$vodId],
        true
    );

    if (!$vod) {
        jsonResponse(404, '影片不存在或已下架');
    }

    $vod = formatVodItem($vod);

    // 播放地址在 mac_vod.vod_play_url 字段中
    // 苹果CMS多源格式: vod_server="源1$$$源2", vod_play_url="源1剧集#...#$$$源2剧集#..."
    $vod['play_from'] = $vod['vod_server'] ?? '';
    if (!empty($vod['vod_play_url'])) {
        $vod['sources'] = parsePlayUrls(
            $vod['vod_server'] ?? '',
            $vod['vod_play_name'] ?? '',
            $vod['vod_play_url']
        );
        // 向后兼容：episodes 取第一个来源的剧集列表
        $vod['episodes'] = !empty($vod['sources'][0]['episodes']) ? $vod['sources'][0]['episodes'] : [];
    } else {
        $vod['sources'] = [];
        $vod['episodes'] = [];
    }

    // 获取分类名称
    if (!empty($vod['type_id'])) {
        $type = dbQueryOne(
            "SELECT type_name FROM " . CMS_DB_PREFIX . "type WHERE type_id = ?",
            [$vod['type_id']],
            true
        );
        $vod['type_name'] = $type['type_name'] ?? '';
    }

    jsonResponse(200, '获取成功', $vod);
}

// ==================== 解析播放地址（支持多播放源） ====================
// 苹果CMS格式: vod_play_url = "源1剧集#源1剧集$$$源2剧集#源2剧集"
//              vod_server = "源1名称$$$源2名称"
//              vod_play_name = "源1集名#源1集名$$$源2集名#源2集名"
function parsePlayUrls($vodServer, $vodPlayName, $vodPlayUrl) {
    $sources = [];

    // 先按 $$$ 拆分播放源
    $urlSourceParts = explode('$$$', $vodPlayUrl);
    $nameSourceParts = explode('$$$', $vodPlayName);

    // vod_server 分隔符兼容: 尝试 $$$，如果数量不匹配则尝试单个 $
    $serverParts = explode('$$$', $vodServer);
    $urlBlockCount = count($urlSourceParts);
    if (count($serverParts) < $urlBlockCount) {
        $serverParts = explode('$', $vodServer);
        // 如果 $ 拆分后数量仍不够，尝试中文顿号、逗号
        if (count($serverParts) < $urlBlockCount) {
            $serverParts = preg_split('/[,，、]/u', $vodServer);
        }
    }

    foreach ($urlSourceParts as $srcIdx => $srcEpisodeBlock) {
        $srcEpisodeBlock = trim($srcEpisodeBlock);
        if (empty($srcEpisodeBlock)) continue;

        // 来源名称
        $srcName = trim($serverParts[$srcIdx] ?? ('播放源' . ($srcIdx + 1)));
        // 该来源的剧集名称列表
        $srcEpNames = trim($nameSourceParts[$srcIdx] ?? '');

        $episodes = [];
        // 按 # 拆分该来源下的剧集
        $epItems = explode('#', $srcEpisodeBlock);
        $epNameItems = explode('#', $srcEpNames);

        $epIdx = 0;
        foreach ($epItems as $i => $item) {
            $item = trim($item);
            if (empty($item)) continue;
            $epIdx++;

            // 按 $ 拆分剧集名称和URL
            $pair = explode('$', $item, 2);
            $epName = $pair[0] ?? ('第' . $epIdx . '集');
            $epUrl  = $pair[1] ?? $item;

            // 如果第一个$前的部分是URL（没有剧集名称），则整段为URL
            if (!empty($pair[0]) && strpos($pair[0], 'http') === 0) {
                $epName = '第' . $epIdx . '集';
                $epUrl  = $pair[0];
            }

            // 优先使用 vod_play_name 中的名称（如果不含URL特征）
            if (isset($epNameItems[$i])) {
                $nameItem = trim($epNameItems[$i]);
                if (!empty($nameItem) && strpos($nameItem, 'http') !== 0 && strpos($nameItem, '.m3u8') === false) {
                    $epName = $nameItem;
                }
            }

            $episodes[] = [
                'index' => $epIdx,
                'name'  => trim($epName),
                'url'   => trim($epUrl)
            ];
        }

        if (!empty($episodes)) {
            $sources[] = [
                'name'     => $srcName,
                'episodes' => $episodes
            ];
        }
    }

    return $sources;
}

// ==================== 修正图片URL ====================
function fixImageUrl($url) {
    if (empty($url)) return '';
    if (strpos($url, 'http') === 0) return $url;
    if (strpos($url, '//') === 0) return 'https:' . $url;
    return $url;
}
