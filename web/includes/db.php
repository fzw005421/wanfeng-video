<?php
/**
 * 晚风影视 - 数据库操作封装
 * 支持 PDO 自动重连、列缓存
 */

// ==================== 缓存目录 ====================

function getCacheDir() {
    $dir = __DIR__ . '/../cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

// ==================== 列信息缓存 ====================

/**
 * 获取表列名（带文件缓存，避免每次都 SHOW COLUMNS）
 */
function getTableColumns($pdo, $tableName) {
    $cacheFile = getCacheDir() . '/columns_' . md5($tableName) . '.cache';
    $ttl = 3600; // 1 小时

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cached)) return $cached;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$tableName}`");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        @file_put_contents($cacheFile, json_encode($cols), LOCK_EX);
        return $cols ?: [];
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 清除表列缓存（用于表结构变更后）
 */
function clearColumnCache($tableName = null) {
    $dir = getCacheDir();
    if ($tableName) {
        $file = $dir . '/columns_' . md5($tableName) . '.cache';
        @unlink($file);
    } else {
        foreach (glob($dir . '/columns_*.cache') as $f) {
            @unlink($f);
        }
    }
}

// ==================== PDO 连接工厂 ====================

/**
 * 创建 PDO 连接
 */
function createPdoConnection($host, $port, $dbName, $user, $pass) {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        PDO::ATTR_PERSISTENT         => false, // 不使用持久连接，避免连接池耗尽
    ]);
}

/**
 * 判断是否为可重连的 MySQL 错误
 */
function isConnectionError($e) {
    $msg = $e->getMessage();
    $codes = [
        'MySQL server has gone away',
        'Connection timed out',
        'Lost connection',
        'Connection refused',
        'Packets out of order',
        'server has gone away',
    ];
    foreach ($codes as $c) {
        if (stripos($msg, $c) !== false) return true;
    }
    return false;
}

// ==================== 自有数据库连接 ====================

function getDbConnection() {
    static $pdo = null;
    static $lastCheck = 0;

    if ($pdo !== null) {
        // 每 5 秒做一次连接探活（快速路径避免每次都 ping）
        if (time() - $lastCheck > 5) {
            $lastCheck = time();
            try {
                $pdo->query('SELECT 1');
            } catch (PDOException $e) {
                if (isConnectionError($e)) {
                    $pdo = null; // 触发重连
                }
            }
        }
    }

    if ($pdo === null) {
        try {
            $pdo = createPdoConnection(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS);
            $lastCheck = time();
        } catch (PDOException $e) {
            jsonResponse(500, '数据库连接失败');
            exit;
        }
    }
    return $pdo;
}

// ==================== CMS 数据库连接（只读） ====================

function getCmsDbConnection() {
    static $pdo = null;
    static $lastCheck = 0;

    if ($pdo !== null) {
        if (time() - $lastCheck > 5) {
            $lastCheck = time();
            try {
                $pdo->query('SELECT 1');
            } catch (PDOException $e) {
                if (isConnectionError($e)) {
                    $pdo = null;
                }
            }
        }
    }

    if ($pdo === null) {
        try {
            $pdo = createPdoConnection(CMS_DB_HOST, CMS_DB_PORT, CMS_DB_NAME, CMS_DB_USER, CMS_DB_PASS);
            $lastCheck = time();
        } catch (PDOException $e) {
            jsonResponse(500, 'CMS数据库连接失败');
            exit;
        }
    }
    return $pdo;
}

// ==================== 带重连的执行包装 ====================

/**
 * 执行 PDO 操作，遇到连接错误自动重连一次
 */
function executeWithRetry($pdoGetter, $callback) {
    $maxRetries = 1;
    $attempt = 0;
    while ($attempt <= $maxRetries) {
        try {
            return $callback($pdoGetter());
        } catch (PDOException $e) {
            if (isConnectionError($e) && $attempt < $maxRetries) {
                // 强制重连
                $GLOBALS['_force_reconnect'] = true;
                $attempt++;
                usleep(100000); // 100ms
                continue;
            }
            throw $e;
        }
    }
}

// ==================== 查询函数 ====================

function dbQuery($sql, $params = [], $useCms = false) {
    $getter = $useCms ? 'getCmsDbConnection' : 'getDbConnection';
    return executeWithRetry($getter, function($pdo) use ($sql, $params) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    });
}

function dbQueryOne($sql, $params = [], $useCms = false) {
    $getter = $useCms ? 'getCmsDbConnection' : 'getDbConnection';
    return executeWithRetry($getter, function($pdo) use ($sql, $params) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    });
}

function dbExecute($sql, $params = [], $useCms = false) {
    $getter = $useCms ? 'getCmsDbConnection' : 'getDbConnection';
    return executeWithRetry($getter, function($pdo) use ($sql, $params) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    });
}

function dbLastInsertId($useCms = false) {
    $pdo = $useCms ? getCmsDbConnection() : getDbConnection();
    return $pdo->lastInsertId();
}

// ==================== 分页查询 ====================

function dbPaginate($sql, $params, $page, $pageSize, $useCms = false) {
    $pdo = $useCms ? getCmsDbConnection() : getDbConnection();

    $countSql = 'SELECT COUNT(*) as total FROM (' . $sql . ') as t';
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalRow = $stmt->fetch();
    $total = $totalRow ? (int)$totalRow['total'] : 0;

    $offset = ($page - 1) * $pageSize;
    $pagedSql = $sql . ' LIMIT ' . (int)$offset . ', ' . (int)$pageSize;
    $stmt2 = $pdo->prepare($pagedSql);
    $stmt2->execute($params);
    $list = $stmt2->fetchAll();

    return [
        'list' => $list,
        'total' => $total,
        'page' => $page,
        'page_size' => $pageSize,
        'total_pages' => ceil($total / $pageSize)
    ];
}
