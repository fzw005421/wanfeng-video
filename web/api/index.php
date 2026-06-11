<?php
/**
 * 晚风影视 - API 路由入口
 * 所有客户端请求通过此文件路由
 */

// 跨域预检
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../includes/init.php';

// HTTP 缓存控制：禁用客户端缓存，但允许条件请求
header('Cache-Control: no-cache, must-revalidate, private');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
// 允许压缩传输
if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
    @ini_set('zlib.output_compression', 'On');
}

// 获取请求路径
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = preg_replace('#^/api#', '', $path);
$path = trim($path, '/');
$segments = $path ? explode('/', $path) : [];

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$params = array_merge($_GET, $input);

// 路由分发 — 全局 try/catch 确保所有错误都以 JSON 格式返回
try {
    $route = $segments[0] ?? '';

    switch ($route) {
        case 'login':
            require __DIR__ . '/login.php';
            handleLogin($params);
            break;

        case 'register':
            require __DIR__ . '/register.php';
            handleRegister($params);
            break;

        case 'user':
            require __DIR__ . '/user.php';
            handleUser($method, $segments, $params);
            break;

        case 'vod':
            require __DIR__ . '/vod.php';
            handleVod($method, $segments, $params);
            break;

        case 'play':
            require __DIR__ . '/play.php';
            handlePlay($params);
            break;

        case 'parse-apis':
            require __DIR__ . '/parse.php';
            handleParseApis($params);
            break;

        case 'history':
            require __DIR__ . '/history.php';
            handleHistory($method, $segments, $params);
            break;

        case 'favorite':
        case 'favorites':
            require __DIR__ . '/favorites.php';
            handleFavorites($method, $segments, $params);
            break;

        case 'announcements':
            require __DIR__ . '/announcements.php';
            handleAnnouncements($params);
            break;

        case 'settings':
            require __DIR__ . '/settings.php';
            handleSettings($method, $params);
            break;

        default:
            jsonResponse(404, '接口不存在');
    }
} catch (PDOException $e) {
    // 只记录日志，不暴露内部错误细节给客户端
    error_log('晚风影视 API SQL Error: ' . $e->getMessage());
    jsonResponse(500, '服务器内部错误，请稍后重试');
} catch (Throwable $e) {
    error_log('晚风影视 API Error: ' . $e->getMessage());
    jsonResponse(500, '服务器内部错误，请稍后重试');
}
