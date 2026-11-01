<?php
session_start();
require_once 'config.php';
if (!$conn) {
    die('数据库连接失败，请先完成安装');
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die('无权限访问，请先登录管理员后台');
}

$errors = [];
$success = [];

$r = mysqli_query($conn, "SHOW COLUMNS FROM `likes` LIKE 'post_id'");
if ($r && $row = mysqli_fetch_assoc($r)) {
    if (strpos($row['Type'], 'unsigned') !== false) {
        $sql = "ALTER TABLE `likes` MODIFY `post_id` int(11) NOT NULL DEFAULT '0' COMMENT '关联动态ID（正数=动态，负数=广告）'";
        if (mysqli_query($conn, $sql)) {
            $success[] = 'likes.post_id 已修改为支持负数（广告点赞）';
        } else {
            $errors[] = 'likes.post_id 修改失败: ' . mysqli_error($conn);
        }
    } else {
        $success[] = 'likes.post_id 已支持负数，无需修改';
    }
}

$r = mysqli_query($conn, "SHOW COLUMNS FROM `posts` LIKE 'video_covers'");
if ($r && mysqli_num_rows($r) == 0) {
    $sql = "ALTER TABLE `posts` ADD `video_covers` text COMMENT '视频封面URL数组，JSON格式' AFTER `videos`";
    if (mysqli_query($conn, $sql)) {
        $success[] = 'posts.video_covers 字段已添加';
    } else {
        $errors[] = 'posts.video_covers 添加失败: ' . mysqli_error($conn);
    }
} else {
    $success[] = 'posts.video_covers 字段已存在';
}

$pr = mysqli_query($conn, "SHOW COLUMNS FROM `posts` LIKE 'nickname'");
if ($pr && mysqli_num_rows($pr) == 0) {
    mysqli_query($conn, "ALTER TABLE `posts` ADD `nickname` varchar(100) DEFAULT NULL COMMENT '单条动态昵称' AFTER `location_lng`");
    mysqli_query($conn, "ALTER TABLE `posts` ADD `avatar` varchar(500) DEFAULT NULL COMMENT '单条动态头像' AFTER `nickname`");
    mysqli_query($conn, "ALTER TABLE `posts` ADD `nickname_color` varchar(20) DEFAULT NULL COMMENT '单条动态昵称颜色' AFTER `avatar`");
    $success[] = 'posts.nickname / avatar / nickname_color 字段已添加';
} else {
    $success[] = 'posts.nickname 字段已存在';
}
$pcr = mysqli_query($conn, "SHOW COLUMNS FROM `posts` LIKE 'nickname_color'");
if ($pcr && mysqli_num_rows($pcr) == 0) {
    mysqli_query($conn, "ALTER TABLE `posts` ADD `nickname_color` varchar(20) DEFAULT NULL COMMENT '单条动态昵称颜色' AFTER `avatar`");
    $success[] = 'posts.nickname_color 字段已添加';
}

$acr = mysqli_query($conn, "SHOW COLUMNS FROM `ads` LIKE 'nickname_color'");
if ($acr && mysqli_num_rows($acr) == 0) {
    $sql = "ALTER TABLE `ads` ADD `nickname_color` varchar(20) DEFAULT '#5d6d9e' COMMENT '广告昵称颜色' AFTER `brand_name`";
    if (mysqli_query($conn, $sql)) {
        $success[] = 'ads.nickname_color 字段已添加';
    } else {
        $errors[] = 'ads.nickname_color 添加失败: ' . mysqli_error($conn);
    }
} else {
    $success[] = 'ads.nickname_color 字段已存在';
}

$apr = mysqli_query($conn, "SHOW COLUMNS FROM `ads` LIKE 'published_at'");
if ($apr && mysqli_num_rows($apr) == 0) {
    $sql = "ALTER TABLE `ads` ADD `published_at` datetime DEFAULT NULL COMMENT '发布时间 留空使用当前时间' AFTER `sort`";
    if (mysqli_query($conn, $sql)) {
        mysqli_query($conn, "UPDATE `ads` SET `published_at` = `created_at` WHERE `published_at` IS NULL");
        $success[] = 'ads.published_at 字段已添加，已有数据已自动填充';
    } else {
        $errors[] = 'ads.published_at 添加失败: ' . mysqli_error($conn);
    }
} else {
    $success[] = 'ads.published_at 字段已存在';
}

$dr = mysqli_query($conn, "DELETE FROM posts WHERE content LIKE '%看到此动态%'");
if ($dr && mysqli_affected_rows($conn) > 0) {
    $success[] = '已删除 ' . mysqli_affected_rows($conn) . ' 条旧默认动态';
} else {
    $success[] = '无旧默认动态需删除';
}

$new_settings = [
    'site_name' => '云汀记',
    'header_title' => '云汀记',
    'divider_color' => '#f0f0f0',
    'divider_width' => '0.5px',
    'global_font_size' => '13',
    'sign_font_size' => '11',
    'nickname_font_size' => '15',
    'header_font_size' => '15',
    'load_more_text' => '加载更多',
    'end_tip_text' => '没有更多啦',
    'line_text' => '- - -',
    'line_color' => '#e0e0e0',
    'cover_height' => '220',
    'avatar_width' => '52',
    'avatar_height' => '52',
    'post_avatar_width' => '38',
    'post_avatar_height' => '38',
];
foreach ($new_settings as $key => $val) {
    $r = mysqli_query($conn, "SELECT * FROM `settings` WHERE setting_key = '$key'");
    if ($r && mysqli_num_rows($r) == 0) {
        $sql = "INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES ('$key', '$val')";
        if (mysqli_query($conn, $sql)) {
            $success[] = $key . ' 默认设置已添加';
        } else {
            $errors[] = $key . ' 设置添加失败: ' . mysqli_error($conn);
        }
    } else {
        $success[] = $key . ' 设置已存在，已更新为最新默认值';

        mysqli_query($conn, "UPDATE `settings` SET `setting_value` = '$val' WHERE `setting_key` = '$key'");
    }
}

$cover_image_val = 'assets/image/bj.png';
$cover_r = mysqli_query($conn, "SELECT * FROM `settings` WHERE setting_key = 'cover_image'");
if ($cover_r && mysqli_num_rows($cover_r) == 0) {
    mysqli_query($conn, "INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES ('cover_image', '$cover_image_val')");
    $success[] = 'cover_image 默认设置已添加';
} else {
    mysqli_query($conn, "UPDATE `settings` SET `setting_value` = '$cover_image_val' WHERE `setting_key` = 'cover_image'");
    $success[] = 'cover_image 已更新为最新默认值';
}

mysqli_query($conn, "DELETE FROM `settings` WHERE `setting_key` = 'sidebar_logo_color'");
if (mysqli_affected_rows($conn) > 0) {
    $success[] = 'sidebar_logo_color 已删除（功能已移除）';
}

$r = mysqli_query($conn, "SHOW COLUMNS FROM `posts` LIKE 'live_photos'");
if ($r && mysqli_num_rows($r) == 0) {
    $sql = "ALTER TABLE `posts` ADD `live_photos` text COMMENT '实况图数据数组，JSON格式 [{image:\"url\",video:\"url\"},...]' AFTER `video_covers`";
    if (mysqli_query($conn, $sql)) {
        $success[] = 'posts.live_photos 字段已添加';
    } else {
        $errors[] = 'posts.live_photos 添加失败: ' . mysqli_error($conn);
    }
} else {
    $success[] = 'posts.live_photos 字段已存在';
}

$pcr = mysqli_query($conn, "SHOW COLUMNS FROM `pages` LIKE 'page_content'");
if ($pcr && mysqli_num_rows($pcr) == 0) {
    $sql = "ALTER TABLE `pages` ADD `page_content` longtext COMMENT '友链模板的页面内容（支持 =名称=链接 友链语法，仅在template=friendlink时使用）' AFTER `content`";
    if (mysqli_query($conn, $sql)) {
        $success[] = 'pages.page_content 字段已添加';
    } else {
        $errors[] = 'pages.page_content 添加失败: ' . mysqli_error($conn);
    }
} else {
    $success[] = 'pages.page_content 字段已存在';
}

$tr = mysqli_query($conn, "SHOW COLUMNS FROM `pages` LIKE 'template'");
if ($tr && mysqli_num_rows($tr) == 0) {
    $sql = "ALTER TABLE `pages` ADD `template` varchar(20) NOT NULL DEFAULT 'custom' COMMENT '页面模板 custom=自定义页面 friendlink=友情链接页面' AFTER `page_content`";
    if (mysqli_query($conn, $sql)) {
        $success[] = 'pages.template 字段已添加';

        mysqli_query($conn, "UPDATE `pages` SET `template` = 'custom' WHERE `template` = '' OR `template` IS NULL");
    } else {
        $errors[] = 'pages.template 添加失败: ' . mysqli_error($conn);
    }
} else {
    $success[] = 'pages.template 字段已存在';
}

$r = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key = 'page_comments_per_page'");
if (!$r || mysqli_num_rows($r) == 0) {
    $esc_key = mysqli_real_escape_string($conn, 'page_comments_per_page');
    $sql = "INSERT INTO settings (setting_key, setting_value) VALUES ('$esc_key', '10')";
    if (mysqli_query($conn, $sql)) {
        $success[] = '全局每页留言数设置已添加（默认10）';
    } else {
        $errors[] = '全局每页留言数设置添加失败: ' . mysqli_error($conn);
    }
} else {
    $success[] = '全局每页留言数设置已存在';
}

$ar = mysqli_query($conn, "SHOW TABLES LIKE 'articles'");
if ($ar && mysqli_num_rows($ar) == 0) {
    $sql = "CREATE TABLE IF NOT EXISTS `articles` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文章表'";
    if (mysqli_query($conn, $sql)) {
        $success[] = 'articles 表已创建';
    } else {
        $errors[] = 'articles 表创建失败: ' . mysqli_error($conn);
    }
} else {
    $success[] = 'articles 表已存在';

    $ar_summary = mysqli_query($conn, "SHOW COLUMNS FROM `articles` LIKE 'summary'");
    if ($ar_summary && mysqli_num_rows($ar_summary) == 0) {
        $sql = "ALTER TABLE `articles` ADD `summary` varchar(500) DEFAULT NULL COMMENT '极简卡片自定义摘要' AFTER `content`";
        if (mysqli_query($conn, $sql)) {
            $success[] = 'articles.summary 字段已添加';
        } else {
            $errors[] = 'articles.summary 添加失败: ' . mysqli_error($conn);
        }
    } else {
        $success[] = 'articles.summary 字段已存在';
    }

    mysqli_query($conn, "ALTER TABLE `articles` MODIFY `bg_color` varchar(20) DEFAULT NULL COMMENT '自定义背景色（两种模式均可使用）'");
    mysqli_query($conn, "ALTER TABLE `articles` MODIFY `bg_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '卡片模式 0=无图极简卡片 1=杂志风大图'");
    mysqli_query($conn, "UPDATE `articles` SET `bg_mode` = 1 WHERE `bg_mode` = 2");
}

$check_col = mysqli_query($conn, "SHOW COLUMNS FROM comments LIKE 'approve_status'");
if (!$check_col || mysqli_num_rows($check_col) == 0) {
    $sql = "ALTER TABLE comments ADD COLUMN approve_status tinyint(1) NOT NULL DEFAULT '1' COMMENT '审核状态 0待审核 1已通过 2已拒绝' AFTER status";
    if (mysqli_query($conn, $sql)) {
        $success[] = 'comments.approve_status 字段已添加';
    } else {
        $errors[] = 'comments.approve_status 添加失败: ' . mysqli_error($conn);
    }
} else {
    $success[] = 'comments.approve_status 字段已存在';
}

$apr_check = mysqli_query($conn, "SELECT * FROM `settings` WHERE `setting_key` = 'comment_need_approve'");
if (!$apr_check || mysqli_num_rows($apr_check) == 0) {
    $esc_key = mysqli_real_escape_string($conn, 'comment_need_approve');
    $sql = "INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES ('$esc_key', '0')";
    if (mysqli_query($conn, $sql)) {
        $success[] = 'comment_need_approve 默认设置已添加（默认关闭审核）';
    } else {
        $errors[] = 'comment_need_approve 设置添加失败: ' . mysqli_error($conn);
    }
} else {
    $success[] = 'comment_need_approve 设置已存在';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>数据库升级</title>
<link rel="stylesheet" href="assets/css/upgrade.css.php">
</head>
<body>
<div class="box">
    <h1>数据库升级结果</h1>
    <?php foreach ($success as $msg): ?><div class="item success"><?php echo htmlspecialchars($msg); ?></div><?php endforeach; ?>
    <?php foreach ($errors as $msg): ?><div class="item error"><?php echo htmlspecialchars($msg); ?></div><?php endforeach; ?>
    <?php if (empty($success) && empty($errors)): ?><div class="item success">数据库已是最新版本</div><?php endif; ?>
    <a href="admin/" class="btn">进入后台</a>
</div>
</body>
</html>
