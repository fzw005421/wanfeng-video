<?php
/**
 * 晚风影视 - 初始化引导文件
 * 性能优化：懒加载 DB 连接，全局限流
 */

// 检查是否已安装
if (!file_exists(__DIR__ . '/../config/database.php')) {
    header('Location: /install/');
    exit;
}

// 加载数据库配置
require_once __DIR__ . '/../config/database.php';

// 加载核心函数
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 错误报告（生产环境关闭显示）
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// 会话启动（如果未启动）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==================== 全局限流（高访客保护） ====================

/**
 * 对全局 API 请求做宽松限流，防止短时间大量请求打崩服务器
 * 60 秒内最多 200 次请求（同一 IP）
 */
function applyGlobalRateLimit() {
    if (!rateLimitCheck('global_api', 200, 60)) {
        header('HTTP/1.1 429 Too Many Requests');
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Retry-After: 5');
        echo json_encode([
            'code' => 429,
            'msg' => '请求太频繁，请稍后再试'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 仅在 API 请求下启用
if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
    applyGlobalRateLimit();
}

// 数据库连接改为懒加载 —— 只在首次调用 getDbConnection/getCmsDbConnection 时建立
// 不再在 init 阶段预连接
