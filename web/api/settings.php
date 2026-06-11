<?php
/**
 * 晚风影视 - 系统设置接口（客户端用）
 */

function handleSettings($method, $params) {
    // 获取公开设置（无需登录）
    $settings = [
        'site_name' => getSetting('site_name', '晚风影视'),
        'site_logo' => getSetting('site_logo', ''),
        'app_version' => getSetting('app_version', '1.0.0'),
    ];

    jsonResponse(200, '获取成功', $settings);
}
