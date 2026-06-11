<?php
/**
 * 晚风影视 - Web入口
 */

// 检查是否已安装
$installed = file_exists(__DIR__ . '/config/database.php');

if (!$installed) {
    header('Location: /install/');
    exit;
}

// 检查是否配置完成（非模板占位符）
$configContent = file_get_contents(__DIR__ . '/config/database.php');
if (strpos($configContent, '{DB_HOST}') !== false) {
    header('Location: /install/');
    exit;
}

// 默认跳转到管理后台
header('Location: /admin/');
exit;
