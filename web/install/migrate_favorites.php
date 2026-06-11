<?php
/**
 * 晚风影视 - 收藏表升级
 * 为 wf_favorites 添加影片元数据字段，不再依赖 CMS 数据库
 * 访问方式: http://你的服务器/api/... 或在浏览器打开此文件
 */

require_once __DIR__ . '/../includes/init.php';

header('Content-Type: text/plain; charset=utf-8');

$columns = [
    ['name' => 'vod_name',    'type' => 'VARCHAR(255)', 'default' => "''"],
    ['name' => 'vod_pic',     'type' => 'VARCHAR(500)', 'default' => "''"],
    ['name' => 'vod_remarks', 'type' => 'VARCHAR(100)', 'default' => "''"],
    ['name' => 'vod_score',   'type' => 'VARCHAR(20)',  'default' => "''"],
    ['name' => 'vod_year',    'type' => 'VARCHAR(20)',  'default' => "''"],
    ['name' => 'vod_area',    'type' => 'VARCHAR(100)', 'default' => "''"],
];

$pdo = getDbConnection();

// 获取现有列
$stmt = $pdo->query("SHOW COLUMNS FROM wf_favorites");
$existing = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

echo "当前 wf_favorites 列: " . implode(', ', $existing) . "\n\n";

$after = 'vod_id';  // 在 vod_id 之后添加
foreach ($columns as $col) {
    if (in_array($col['name'], $existing)) {
        echo "✓ {$col['name']} — 已存在，跳过\n";
    } else {
        $sql = "ALTER TABLE wf_favorites ADD COLUMN {$col['name']} {$col['type']} DEFAULT {$col['default']} AFTER {$after}";
        try {
            $pdo->exec($sql);
            echo "✓ {$col['name']} — 添加成功\n";
        } catch (Exception $e) {
            echo "✗ {$col['name']} — 失败: " . $e->getMessage() . "\n";
        }
    }
    $after = $col['name'];
}

// 验证
$stmt = $pdo->query("SHOW COLUMNS FROM wf_favorites");
$final = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
echo "\n升级后 wf_favorites 列: " . implode(', ', $final) . "\n";
echo "\n迁移完成！\n";
