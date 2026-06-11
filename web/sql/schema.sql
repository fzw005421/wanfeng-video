-- ============================================
-- 晚风影视 - 数据库架构
-- ============================================

-- 创建自有数据库（如果不存在）
-- CREATE DATABASE IF NOT EXISTS `wanfeng_video` DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `wanfeng_video`;

-- ----------------------------
-- 用户表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `wf_users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL COMMENT '密码哈希',
    `nickname` VARCHAR(100) DEFAULT '',
    `avatar` VARCHAR(255) DEFAULT '',
    `status` TINYINT(1) DEFAULT 1 COMMENT '1=正常 0=禁用',
    `last_login` DATETIME DEFAULT NULL,
    `login_ip` VARCHAR(50) DEFAULT '',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_username` (`username`),
    KEY `idx_status` (`status`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- ----------------------------
-- 播放记录表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `wf_play_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `vod_id` INT UNSIGNED NOT NULL COMMENT '苹果CMS影片ID',
    `vod_name` VARCHAR(255) DEFAULT '' COMMENT '影片名称快照',
    `vod_pic` VARCHAR(500) DEFAULT '' COMMENT '封面快照',
    `episode_index` INT UNSIGNED DEFAULT 1 COMMENT '当前集数',
    `episode_name` VARCHAR(100) DEFAULT '' COMMENT '集数名称',
    `play_position` INT UNSIGNED DEFAULT 0 COMMENT '播放进度(秒)',
    `duration` INT UNSIGNED DEFAULT 0 COMMENT '视频总时长(秒)',
    `parse_api_id` INT UNSIGNED DEFAULT 0 COMMENT '上次使用的解析接口',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_user_vod_ep` (`user_id`, `vod_id`, `episode_index`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='播放记录表';

-- ----------------------------
-- 收藏表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `wf_favorites` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `vod_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_user_vod` (`user_id`, `vod_id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='收藏表';

-- ----------------------------
-- 解析接口配置表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `wf_parse_apis` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL COMMENT '接口名称',
    `url_template` VARCHAR(500) NOT NULL COMMENT '解析URL模板，{url}为占位符',
    `sort_order` INT DEFAULT 0,
    `status` TINYINT(1) DEFAULT 1 COMMENT '1=启用 0=禁用',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_sort` (`sort_order`, `id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='解析接口配置表';

-- ----------------------------
-- 公告表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `wf_announcements` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT,
    `status` TINYINT(1) DEFAULT 1 COMMENT '1=发布 0=草稿',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_status` (`status`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='公告表';

-- ----------------------------
-- 推荐管理表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `wf_recommendations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `vod_id` INT UNSIGNED NOT NULL COMMENT '苹果CMS影片ID',
    `vod_name` VARCHAR(255) DEFAULT '' COMMENT '影片名称快照',
    `vod_pic` VARCHAR(500) DEFAULT '' COMMENT '封面快照',
    `position` VARCHAR(50) DEFAULT 'home' COMMENT '推荐位置: home=首页推荐 banner=轮播图',
    `sort_order` INT DEFAULT 0,
    `status` TINYINT(1) DEFAULT 1 COMMENT '1=启用 0=禁用',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_position` (`position`, `status`),
    KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='推荐管理表';

-- ----------------------------
-- 系统设置表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `wf_settings` (
    `setting_key` VARCHAR(100) PRIMARY KEY,
    `setting_value` TEXT,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统设置表';

-- ----------------------------
-- 管理员表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `wf_admins` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL COMMENT '密码哈希',
    `role` VARCHAR(20) DEFAULT 'admin' COMMENT 'admin / super_admin',
    `status` TINYINT(1) DEFAULT 1,
    `last_login` DATETIME DEFAULT NULL,
    `login_ip` VARCHAR(50) DEFAULT '',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员表';

-- ----------------------------
-- 插入默认设置
-- ----------------------------
INSERT INTO `wf_settings` (`setting_key`, `setting_value`) VALUES
('site_name', '晚风影视'),
('site_logo', ''),
('cms_db_host', ''),
('cms_db_port', '3306'),
('cms_db_user', ''),
('cms_db_pass', ''),
('cms_db_name', ''),
('app_version', '1.0.0')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- ----------------------------
-- 插入默认解析接口示例
-- ----------------------------
INSERT INTO `wf_parse_apis` (`name`, `url_template`, `sort_order`, `status`) VALUES
('默认线路', 'http://154.201.94.39/2.php?url={url}', 1, 1);
