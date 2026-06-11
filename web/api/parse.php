<?php
/**
 * 晚风影视 - 解析接口列表
 */

function handleParseApis($params) {
    $apis = dbQuery(
        "SELECT id, name, sort_order FROM wf_parse_apis WHERE status = 1 ORDER BY sort_order ASC, id ASC"
    );

    jsonResponse(200, '获取成功', $apis);
}
