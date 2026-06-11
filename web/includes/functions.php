<?php
/**
 * 晚风影视 - 通用函数库
 */

/**
 * 输出JSON响应并终止
 */
function jsonResponse($code, $msg, $data = null) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

    $response = [
        'code' => $code,
        'msg' => $msg
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * 获取客户端真实IP
 */
function getClientIp() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        return $_SERVER['HTTP_X_REAL_IP'];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * 密码哈希
 */
function passwordHash($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
}

/**
 * 密码验证
 */
function passwordVerify($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * 生成Token（简化方案：base64编码的随机字符串+过期时间）
 */
function generateToken($userId) {
    $payload = [
        'uid' => $userId,
        'exp' => time() + 86400 * 30, // 30天有效期
        'iat' => time(),
        'nonce' => bin2hex(random_bytes(16))
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    return base64_encode($json . '.' . md5($json . 'wanfeng_video_salt_2024'));
}

/**
 * 验证Token并返回用户ID
 */
function verifyToken($token) {
    if (empty($token)) {
        return false;
    }

    $decoded = base64_decode($token);
    if ($decoded === false) {
        return false;
    }

    $parts = explode('.', $decoded, 2);
    if (count($parts) !== 2) {
        return false;
    }

    $json = $parts[0];
    $sign = $parts[1];

    // 验证签名
    if ($sign !== md5($json . 'wanfeng_video_salt_2024')) {
        return false;
    }

    $payload = json_decode($json, true);
    if (!$payload || !isset($payload['uid']) || !isset($payload['exp'])) {
        return false;
    }

    // 检查过期
    if ($payload['exp'] < time()) {
        return false;
    }

    return (int)$payload['uid'];
}

/**
 * 获取当前登录用户ID（API用）
 */
function getCurrentUserId() {
    $token = '';

    // 方式1: Apache getallheaders()
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $token = str_replace('Bearer ', '', $headers['Authorization']);
        } elseif (isset($headers['authorization'])) {
            $token = str_replace('Bearer ', '', $headers['authorization']);
        }
    }

    // 方式2: $_SERVER 变量（Apache mod_rewrite + E=HTTP_AUTHORIZATION）
    if (empty($token)) {
        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
            if (!empty($_SERVER[$key])) {
                $token = str_replace('Bearer ', '', $_SERVER[$key]);
                break;
            }
        }
    }

    // 方式3: 从 apache_request_headers 获取（备用）
    if (empty($token) && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach (['Authorization', 'authorization'] as $key) {
            if (!empty($headers[$key])) {
                $token = str_replace('Bearer ', '', $headers[$key]);
                break;
            }
        }
    }

    if (empty($token)) {
        return false;
    }

    return verifyToken($token);
}

/**
 * 获取系统设置（带 5 分钟文件缓存）
 */
function getSetting($key, $default = '') {
    static $cache = null;
    $cacheFile = __DIR__ . '/../cache/settings.cache';
    $ttl = 300;

    // 加载缓存
    if ($cache === null) {
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (is_array($cached)) $cache = $cached;
        }
    }

    if ($cache !== null && array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    // 缓存未命中，从数据库加载
    $row = dbQueryOne("SELECT setting_value FROM wf_settings WHERE setting_key = ?", [$key]);
    $value = $row ? $row['setting_value'] : $default;

    // 更新缓存
    if ($cache === null) $cache = [];
    $cache[$key] = $value;
    @file_put_contents($cacheFile, json_encode($cache), LOCK_EX);

    return $value;
}

/**
 * 设置系统设置（同时更新缓存）
 */
function setSetting($key, $value) {
    dbExecute(
        "INSERT INTO wf_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?",
        [$key, $value, $value]
    );

    // 清除设置缓存
    $cacheFile = __DIR__ . '/../cache/settings.cache';
    @unlink($cacheFile);
}

// ==================== 简易请求限流 ====================

/**
 * 简易限流检查（基于 IP + 时间窗口）
 * @param string $key 限流标识
 * @param int $maxRequests 窗口内最大请求数
 * @param int $windowSeconds 时间窗口秒数
 * @return bool true=允许, false=拒绝
 */
function rateLimitCheck($key, $maxRequests = 30, $windowSeconds = 10) {
    $dir = __DIR__ . '/../cache/ratelimit';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $ip = getClientIp();
    $file = $dir . '/' . md5($key . $ip) . '.rl';
    $now = time();

    $records = [];
    if (file_exists($file)) {
        $records = json_decode(file_get_contents($file), true) ?: [];
    }

    // 清理过期记录
    $records = array_filter($records, function($t) use ($now, $windowSeconds) {
        return ($now - $t) < $windowSeconds;
    });

    if (count($records) >= $maxRequests) {
        return false;
    }

    $records[] = $now;
    @file_put_contents($file, json_encode(array_values($records)), LOCK_EX);
    return true;
}

/**
 * 输入安全过滤
 */
function safeInput($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

/**
 * 相对时间格式化
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return '刚刚';
    if ($diff < 3600) return floor($diff / 60) . '分钟前';
    if ($diff < 86400) return floor($diff / 3600) . '小时前';
    if ($diff < 2592000) return floor($diff / 86400) . '天前';
    return date('Y-m-d', $time);
}
