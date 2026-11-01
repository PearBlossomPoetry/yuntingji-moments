SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `admin`;
CREATE TABLE IF NOT EXISTS `admin` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员表';

DROP TABLE IF EXISTS `settings`;
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL DEFAULT '',
  `setting_value` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统设置表';

DROP TABLE IF EXISTS `posts`;
CREATE TABLE IF NOT EXISTS `posts` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `content` text COMMENT '动态内容',
  `images` text COMMENT '图片URL数组，JSON格式',
  `videos` text COMMENT '视频URL数组，JSON格式',
  `video_covers` text COMMENT '视频封面URL数组，JSON格式',
  `live_photos` text COMMENT '实况图数据数组，JSON格式 [{image:"url",video:"url"},...]',
  `location_name` varchar(255) DEFAULT NULL COMMENT '地点名称',
  `location_lat` decimal(10,7) DEFAULT NULL COMMENT '纬度',
  `location_lng` decimal(10,7) DEFAULT NULL COMMENT '经度',
  `nickname` varchar(100) DEFAULT NULL COMMENT '单条动态昵称，为空则使用全局昵称',
  `avatar` varchar(500) DEFAULT NULL COMMENT '单条动态头像，为空则使用全局头像',
  `nickname_color` varchar(20) DEFAULT NULL COMMENT '单条动态昵称颜色，为空则使用全局昵称颜色',
  `is_top` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否置顶 0否 1是',
  `comment_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启评论',
  `like_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启点赞',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 0隐藏 1显示 2删除',
  `published_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '发布时间',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_is_top` (`is_top`),
  KEY `idx_published_at` (`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='动态表';

DROP TABLE IF EXISTS `tags`;
CREATE TABLE IF NOT EXISTS `tags` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '',
  `color` varchar(20) DEFAULT '#409eff' COMMENT '标签颜色',
  `bg_color` varchar(20) DEFAULT '#e8f4ff' COMMENT '背景颜色',
  `sort` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='标签表';

DROP TABLE IF EXISTS `post_tags`;
CREATE TABLE IF NOT EXISTS `post_tags` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` int(11) unsigned NOT NULL DEFAULT '0',
  `tag_id` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `post_tag` (`post_id`,`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='动态标签关联表';

DROP TABLE IF EXISTS `music`;
CREATE TABLE IF NOT EXISTS `music` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL DEFAULT '0' COMMENT '关联动态ID（正数=动态，负数=广告）',
  `name` varchar(255) DEFAULT NULL COMMENT '歌曲名',
  `artist` varchar(255) DEFAULT NULL COMMENT '艺术家',
  `cover` varchar(500) DEFAULT NULL COMMENT '封面URL',
  `url` varchar(500) DEFAULT NULL COMMENT '音乐链接',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='音乐表';

DROP TABLE IF EXISTS `ads`;
CREATE TABLE IF NOT EXISTS `ads` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `style` tinyint(1) NOT NULL DEFAULT '1' COMMENT '广告样式 1卡片式 2大图式',
  `avatar` varchar(500) DEFAULT NULL COMMENT '头像URL，为空则显示"广告"文字',
  `title` varchar(255) NOT NULL DEFAULT '' COMMENT '品牌名称/标题',
  `subtitle` varchar(255) DEFAULT '' COMMENT '副标题',
  `content` text COMMENT '广告文字内容',
  `image` varchar(500) DEFAULT NULL COMMENT '广告图片',
  `card_title` varchar(255) DEFAULT NULL COMMENT '卡片式标题',
  `card_desc` varchar(255) DEFAULT NULL COMMENT '卡片式描述',
  `button_text` varchar(50) DEFAULT '立即查看' COMMENT '按钮文字',
  `button_color` varchar(20) DEFAULT '#07C160' COMMENT '按钮颜色',
  `link` varchar(500) DEFAULT NULL COMMENT '跳转链接',
  `brand_logo` varchar(500) DEFAULT NULL COMMENT '品牌logo（大图式）',
  `brand_name` varchar(255) DEFAULT NULL COMMENT '品牌名称（大图式）',
  `nickname_color` varchar(20) DEFAULT '#5d6d9e' COMMENT '广告昵称颜色',
  `is_top` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否置顶 0否 1是',
  `comment_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启评论',
  `like_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启点赞',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 0隐藏 1显示 2删除',
  `sort` int(11) NOT NULL DEFAULT '0',
  `published_at` datetime DEFAULT NULL COMMENT '发布时间 留空使用当前时间',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_sort` (`sort`),
  KEY `idx_published_at` (`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='广告表';

DROP TABLE IF EXISTS `likes`;
CREATE TABLE IF NOT EXISTS `likes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL DEFAULT '0' COMMENT '关联动态ID（正数=动态，负数=广告）',
  `ip` varchar(45) NOT NULL DEFAULT '',
  `nickname` varchar(100) DEFAULT NULL COMMENT '访客昵称',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `post_ip` (`post_id`,`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='点赞表';

DROP TABLE IF EXISTS `comments`;
CREATE TABLE IF NOT EXISTS `comments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL DEFAULT '0' COMMENT '关联动态ID（正数=动态，负数=广告）',
  `nickname` varchar(100) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '',
  `website` varchar(255) DEFAULT NULL,
  `avatar` varchar(500) DEFAULT NULL COMMENT '头像URL',
  `content` text NOT NULL,
  `parent_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '回复的评论ID，0为直接评论',
  `ip` varchar(45) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 0隐藏 1显示',
  `approve_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '审核状态 0待审核 1已通过 2已拒绝',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='评论表';

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE IF NOT EXISTS `banned_words` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `word` varchar(100) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `word` (`word`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='违禁词表';

CREATE TABLE IF NOT EXISTS `banned_ips` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `reason` varchar(255) DEFAULT '',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IP黑名单表';

CREATE TABLE IF NOT EXISTS `visitor_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `carrier` varchar(50) DEFAULT '' COMMENT '运营商',
  `country` varchar(50) DEFAULT '',
  `region` varchar(50) DEFAULT '' COMMENT '省份',
  `city` varchar(50) DEFAULT '' COMMENT '城市',
  `isp` varchar(100) DEFAULT '',
  `browser` varchar(50) DEFAULT '' COMMENT '浏览器',
  `os` varchar(50) DEFAULT '' COMMENT '操作系统',
  `device` varchar(20) DEFAULT '' COMMENT '设备',
  `model` varchar(100) DEFAULT '' COMMENT '手机型号',
  `referer` varchar(500) DEFAULT '' COMMENT '来源页',
  `url` varchar(500) DEFAULT '' COMMENT '访问地址',
  `page_type` varchar(30) DEFAULT '' COMMENT '操作类型',
  `target` varchar(100) DEFAULT '' COMMENT '操作对象',
  `user_agent` varchar(500) DEFAULT '',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ip` (`ip`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='访客访问记录';

INSERT INTO `tags` (`name`, `color`, `bg_color`, `sort`) VALUES
('网易云', '#f56c6c', '#fef0f0', 1),
('企业微信', '#409eff', '#e8f4ff', 2),
('广告', '#909399', '#f4f4f5', 3);

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name', '云汀记'),
('header_title', '云汀记'),
('avatar', 'http://q.qlogo.cn/headimg_dl?dst_uin=1239399954&spec=640&img_type=jpg'),
('nickname', '云汀记'),
('nickname_color', '#5d6d9e'),
('ad_nickname_color', '#5d6d9e'),
('signature', '欢迎使用云汀记程序'),
('cover_image', 'assets/image/bj.png'),
('page_size', '12'),
('load_mode', 'manual'),
('pjax_enabled', '0'),
('admin_pjax_enabled', '0'),
('email_notify_enabled', '0'),
('smtp_host', ''),
('smtp_port', '465'),
('smtp_user', ''),
('smtp_pass', ''),
('email_subject', '【{site_name}】你收到一条新评论'),
('email_template', '<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>新评论提醒</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f8; font-family:''Segoe UI'',''Helvetica Neue'',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f6f8;">
<tr>
<td align="center" style="padding:40px 20px;">
<table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px; width:100%; background:#ffffff; border-radius:16px; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
<!-- Header -->
<tr>
<td style="background:#1a1a2e; padding:25px 40px; border-radius:12px 12px 0 0;">
<table width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td style="font-size:24px; font-weight:700; color:#ffffff; letter-spacing:-0.5px;">
Instant
</td>
</tr>
<tr>
<td style="font-size:14px; color:rgba(255,255,255,0.55); padding-top:6px;">
{site_name} · 新评论提醒
</td>
</tr>
</table>
</td>
</tr>
<!-- Body -->
<tr>
<td style="padding:40px 40px 32px 40px;">
<p style="margin:0 0 8px 0; font-size:18px; font-weight:650; color:#1a1a2e; line-height:1.2;">
<span style="color:#1a1a2e; font-weight:500;">你好</span> {nickname}
</p>
<p style="margin:0 0 28px 0; font-size:15px; line-height:1.6; color:#6b7280;">
你收到了一条新评论 内容如下：
</p>
<!-- Comment Box -->
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; margin:0 0 28px 0;">
<tr>
<td style="padding:24px 28px;">
<p style="margin:0 0 8px 0; font-size:13px; color:#6b7280; font-weight:500; letter-spacing:0.5px;">
评论内容
</p>
<p style="margin:0; font-size:16px; font-weight:500; color:#1a1a2e; line-height:1.6;">
{content}
</p>
</td>
</tr>
</table>
<!-- CTA Button -->
<table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 28px 0;">
<tr>
<td style="background:#5d6d9e; border-radius:10px; text-align:center;">
<a href="{post_link}" style="display:inline-block; padding:10px 32px; color:#ffffff; text-decoration:none; font-size:12px; font-weight:600; border-radius:10px;">查看详情</a>
</td>
</tr>
</table>
<!-- Info Rows -->
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #f1f5f9; padding-top:20px;">
<tr>
<td style="padding:10px 0;">
<table width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td style="font-size:14px; color:#9ca3af;">评论时间</td>
<td align="right" style="font-size:14px; color:#1a1a2e; font-weight:600;">{time}</td>
</tr>
</table>
</td>
</tr>
<tr>
<td style="padding:10px 0; border-top:1px solid #f1f5f9;">
<table width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td style="font-size:14px; color:#9ca3af;">评论者</td>
<td align="right" style="font-size:14px; color:#1a1a2e; font-weight:600;">{commenter}</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
<!-- Footer -->
<tr>
<td style="background:#f8fafc; padding:24px 40px; border-top:1px solid #f1f5f9; border-radius:0 0 16px 16px;">
<p style="margin:0; font-size:12px; color:#9ca3af; line-height:1.8;">
这是一封系统自动发送的邮件 请勿直接回复
</p>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>'),
('menu_icon_html', ''),
('ajax_enabled', '0'),
('admin_ajax_enabled', '0'),
('ajax_loading_css', '.loading-14 {\n    width: 48px;\n    height: 48px;\n    border-radius: 50%;\n    display: inline-block;\n    border-top: 4px solid #ddd;\n    border-right: 4px solid transparent;\n    animation: loading-14 1s linear infinite;\n}\n.loading-14:after {\n    content: "";\n    position: absolute;\n    left: 0;\n    top: 0;\n    width: 48px;\n    height: 48px;\n    border-radius: 50%;\n    border-bottom: 4px solid #ff3d00;\n    border-left: 4px solid transparent;\n}\n@keyframes loading-14 {\n    0% {\n    transform: rotate(0);\n}\n    to {\n    transform: rotate(360deg);\n}\n}'),
('admin_ajax_loading_css', 'width:1px;height:1px;margin:20px;'),
('font_url', ''),
('comment_need_approve', '0'),
('divider_color', '#f0f0f0'),
('divider_width', '0.5px'),
('global_font_size', '13'),
('sign_font_size', '11'),
('header_font_size', '15'),
('load_more_text', '加载更多'),
('end_tip_text', '没有更多啦'),
('line_text', '- - -'),
('line_color', '#e0e0e0'),
('cover_height', '220'),
('avatar_width', '52'),
('avatar_height', '52'),
('post_avatar_width', '40'),
('post_avatar_height', '40'),
('site_keywords', '博客,朋友圈,云汀记'),
('site_description', '一款精致的、轻量的、干净清爽的全新朋友圈程序'),
('music_parse_mode', 'official'),
('music_api_url', ''),
('avatar_shape', 'square'),
('music_platform', 'netease');

INSERT INTO `posts` (`content`, `nickname`, `nickname_color`, `is_top`, `status`) VALUES
('欢迎使用云汀记！看到此条动态 代表你已成功安装本程序！', '', '', 0, 1);

DROP TABLE IF EXISTS `pages`;
CREATE TABLE IF NOT EXISTS `pages` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '' COMMENT '页面标题',
  `slug` varchar(100) NOT NULL DEFAULT '' COMMENT '页面别名URL',
  `content` longtext COMMENT '页面内容（自定义模板=HTML内容）',
  `page_content` longtext COMMENT '友链模板的页面内容（支持 =名称=链接 友链语法，仅在template=friendlink时使用）',
  `template` varchar(20) NOT NULL DEFAULT 'custom' COMMENT '页面模板 custom=自定义页面 friendlink=友情链接页面',
  `layout` varchar(20) NOT NULL DEFAULT 'grid' COMMENT '布局样式 grid=网格一行两个（仅友链模板有效）',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 0隐藏 1显示',
  `enable_comments` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启留言 0关闭 1开启',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_status` (`status`),
  KEY `idx_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='独立页面表';

INSERT INTO `pages` (`title`, `slug`, `content`, `page_content`, `template`, `layout`, `sort`, `status`) VALUES
('关于', 'about', '<h3>关于</h3>\n<p>这里是一个示例的 <font color="EE2C2C"><b>独立页面 - 关于</b></font> 内容 \n你可以在后台管理中自由编辑这段内容 并且支持完整的 <font color="#009641"><b>HTML与Markdown</b></font> 双语法供你创作</p>\n\n>独立页面功能让你可以创建任意数量的自定义页面 如关于、友链、留言、作品集等 页面会自动显示在右上角的菜单中 并支持排序', NULL, 'custom', 'grid', 1, 1),
('友链', 'links', '', '<h3>友链</h3>\n<p>这里是一个示例的 <font color="EE2C2C"><b>独立页面 - 友链</b></font> 内容 \n你可以在后台管理中自由编辑这段内容 支持完整的 <font color="#009641"><b>HTML与Markdown</b></font> 双语法供你创作</p>\n\n>独立页面功能让你可以创建任意数量的自定义页面 如关于、友链、留言、作品集等 页面会自动显示在右上角的菜单中 并支持排序\n\n<h3>朋友们</h3>\n=云汀记=https://imoe.top', 'friendlink', 'grid', 2, 1);

CREATE TABLE IF NOT EXISTS `articles` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '' COMMENT '文章标题',
  `slug` varchar(100) NOT NULL DEFAULT '' COMMENT '文章别名URL',
  `content` longtext COMMENT '文章内容（支持HTML）',
  `summary` varchar(500) DEFAULT NULL COMMENT '极简卡片自定义摘要',
  `cover_image` varchar(500) DEFAULT NULL COMMENT '封面图URL',
  `bg_color` varchar(20) DEFAULT NULL COMMENT '自定义背景色（两种模式均可使用）',
  `bg_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '卡片模式 0=无图极简卡片 1=杂志风大图',
  `is_top` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否置顶 0否 1是',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 0隐藏 1显示 2删除',
  `comment_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启评论 0关闭 1开启',
  `view_count` int(11) NOT NULL DEFAULT '0' COMMENT '浏览次数',
  `published_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '发布时间',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_status` (`status`),
  KEY `idx_published_at` (`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文章表';
