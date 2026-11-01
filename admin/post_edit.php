<?php
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$page_title = isset($_GET['id']) ? '编辑动态' : '发布动态';
$page_id = 'posts';

$id = intval($_GET['id'] ?? 0);
$post = [
    'id'              => 0,
    'content'         => '',
    'images'          => '[]',
    'videos'          => '[]',
    'video_covers'    => '[]',
    'location_name'   => '',
    'nickname'        => '',
    'avatar'          => '',
    'nickname_color'  => '',
    'is_top'          => 0,
    'status'          => 1,
    'comment_enabled' => 1,
    'like_enabled'    => 1,
    'published_at'    => ''
];
$post_tags  = [];
$post_music = null;

if ($id) {
    $result = mysqli_query($conn, "SELECT * FROM posts WHERE id = $id");
    if ($row = mysqli_fetch_assoc($result)) {
        $post = $row;
        $tag_result = mysqli_query($conn, "SELECT tag_id FROM post_tags WHERE post_id = $id");
        while ($t = mysqli_fetch_assoc($tag_result)) {
            $post_tags[] = $t['tag_id'];
        }
        $music_result = mysqli_query($conn, "SELECT * FROM music WHERE post_id = $id ORDER BY id ASC");
        $post_music = [];
        while ($m = mysqli_fetch_assoc($music_result)) {
            $post_music[] = $m;
        }
    }
}

$all_tags = [];
$tag_result = mysqli_query($conn, "SELECT * FROM tags ORDER BY sort ASC");
if ($tag_result) {
    while ($t = mysqli_fetch_assoc($tag_result)) {
        $all_tags[] = $t;
    }
}

$settings      = get_all_settings();
$default_color = $settings['nickname_color'] ?? '#5d6d9e';

$toast_msg = $_SESSION['admin_toast'] ?? '';
if ($toast_msg) {
    unset($_SESSION['admin_toast']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content             = mysqli_real_escape_string($conn, $_POST['content'] ?? '');
    $images              = $_POST['images'] ?? '';
    $videos              = $_POST['videos'] ?? '';
    $location_name       = mysqli_real_escape_string($conn, $_POST['location_name'] ?? '');
    $post_nickname       = mysqli_real_escape_string($conn, $_POST['post_nickname'] ?? '');
    $post_avatar         = mysqli_real_escape_string($conn, $_POST['post_avatar'] ?? '');
    $post_nickname_color = mysqli_real_escape_string($conn, $_POST['post_nickname_color'] ?? '');
    $is_top              = intval($_POST['is_top'] ?? 0);
    $comment_enabled     = intval($_POST['comment_enabled'] ?? 1);
    $like_enabled        = intval($_POST['like_enabled'] ?? 1);
    $status              = intval($_POST['status'] ?? 1);
    $published_at_raw    = trim($_POST['published_at'] ?? '');
    $published_at        = empty($published_at_raw) ? date('Y-m-d H:i:s') : $published_at_raw;
    $selected_tags       = $_POST['tags'] ?? [];

    $images_arr = [];
    if ($images) {
        $lines = array_filter(array_map('trim', explode("\n", $images)));
        foreach ($lines as $line) {
            if ($line) $images_arr[] = $line;
        }
    }
    if (count($images_arr) > 9) {
        $_SESSION['admin_toast'] = '图片上限9个，请检查图片';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    $images_json = mysqli_real_escape_string($conn, json_encode($images_arr));

    $videos_arr = [];
    if ($videos) {
        $lines = array_filter(array_map('trim', explode("\n", $videos)));
        foreach ($lines as $line) {
            if ($line) $videos_arr[] = $line;
        }
    }
    if (count($videos_arr) > 9) {
        $_SESSION['admin_toast'] = '视频上限9个，请检查视频';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    $videos_json = mysqli_real_escape_string($conn, json_encode($videos_arr));

    $video_covers     = $_POST['video_covers'] ?? '';
    $video_covers_arr = [];
    if ($video_covers) {
        $lines = array_filter(array_map('trim', explode("\n", $video_covers)));
        foreach ($lines as $line) {
            if ($line) $video_covers_arr[] = $line;
        }
    }
    $video_covers_json = mysqli_real_escape_string($conn, json_encode($video_covers_arr));

    if ($id) {
        mysqli_query($conn, "UPDATE posts SET
            content             = '$content',
            images              = '$images_json',
            videos              = '$videos_json',
            video_covers        = '$video_covers_json',
            location_name       = '$location_name',
            nickname            = '$post_nickname',
            avatar              = '$post_avatar',
            nickname_color      = '$post_nickname_color',
            is_top              = $is_top,
            comment_enabled     = $comment_enabled,
            like_enabled        = $like_enabled,
            status              = $status,
            published_at        = '$published_at'
            WHERE id = $id
        ");
    } else {
        mysqli_query($conn, "INSERT INTO posts (
            content, images, videos, video_covers, location_name,
            nickname, avatar, nickname_color, is_top,
            comment_enabled, like_enabled, status, published_at
        ) VALUES (
            '$content', '$images_json', '$videos_json', '$video_covers_json',
            '$location_name', '$post_nickname', '$post_avatar', '$post_nickname_color',
            $is_top, $comment_enabled, $like_enabled, $status, '$published_at'
        )");
        $id = mysqli_insert_id($conn);
    }

    mysqli_query($conn, "DELETE FROM post_tags WHERE post_id = $id");
    foreach ($selected_tags as $tag_id) {
        mysqli_query($conn, "INSERT INTO post_tags (post_id, tag_id) VALUES ($id, " . intval($tag_id) . ")");
    }

    mysqli_query($conn, "DELETE FROM music WHERE post_id = $id");
    $music_url = trim($_POST['music_url'] ?? '');
    if ($music_url) {
        $name   = mysqli_real_escape_string($conn, trim($_POST['music_name'] ?? ''));
        $artist = mysqli_real_escape_string($conn, trim($_POST['music_artist'] ?? ''));
        $cover  = mysqli_real_escape_string($conn, trim($_POST['music_cover'] ?? ''));
        $esc_url = mysqli_real_escape_string($conn, $music_url);
        mysqli_query($conn, "INSERT INTO music (post_id, name, artist, cover, url, sort)
            VALUES ($id, '$name', '$artist', '$cover', '$esc_url', 0)"
        );
    }

    $_SESSION['admin_toast'] = '保存成功';
    $current_file = basename($_SERVER['PHP_SELF']);
    header('Location: ' . $current_file . '?id=' . $id);
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title); ?> | <?php echo htmlspecialchars($settings['site_name'] ?? '云汀记'); ?></title>
    <script src="../assets/vendor/tailwind.js"></script>
    <link href="../assets/vendor/font-awesome/css/all.min.css" rel="stylesheet">
    <link href="../assets/vendor/fonts/inter.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter','sans-serif'] },
                    colors: {
                        primary: '#5b73e8',
                        'primary-light': '#eef2ff',
                        secondary: '#5b6b79',
                        success: '#2ca87f',
                        danger: '#dc2626',
                        warning: '#e58a00',
                        info: '#3ec9d6',
                        dark: '#1d2630',
                        body: '#f8f9fa'
                    }
                }
            }
        }
    </script>
    <style>
        * { -webkit-tap-highlight-color: transparent; }
        body { font-family: 'Inter', sans-serif; background: #f4f7fa; }
        .sidebar-scroll::-webkit-scrollbar { width: 4px; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 4px; }
        .nav-item {
            transition: all 0.2s ease;
            border-radius: 12px;
            margin: 2px 12px;
        }
        .nav-item:hover {
            background: rgba(91, 115, 232, 0.06);
            color: #5b73e8;
        }
        .nav-item.active {
            background: rgba(91, 115, 232, 0.1);
            color: #5b73e8;
        }
        .nav-item.active .nav-icon { color: #5b73e8; }
        .nav-icon {
            color: #9ca3af;
            transition: color 0.2s;
            width: 20px;
            text-align: center;
        }
        .nav-item:hover .nav-icon { color: #5b73e8; }
        .section-title {
            font-size: 11px;
            letter-spacing: 0.08em;
            color: #6b7280;
            font-weight: 700;
        }
        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.35s cubic-bezier(0.4,0,0.2,1);
        }
        .submenu.open { max-height: 600px; }
        .submenu-arrow {
            transition: transform 0.3s ease;
            font-size: 12px;
            color: #9ca3af;
        }
        .submenu-arrow.rotate {
            transform: rotate(90deg);
            color: #5b73e8;
        }
        .user-card {
            background: #f3f4f6;
            border-radius: 16px;
            transition: all 0.2s;
        }
        .user-card:hover { background: #e5e7eb; }

        .ap-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid #f0f0f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02), 0 4px 20px rgba(0,0,0,0.03);
            overflow: hidden;
        }
        .ap-card-header {
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #f8f8f8;
            flex-wrap: wrap;
            gap: 12px;
        }
        .ap-card-title {
            font-size: 17px;
            font-weight: 700;
            color: #1f2937;
            letter-spacing: -0.2px;
        }
        .ap-card-body { padding: 24px; }

        .ap-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            outline: none;
            text-decoration: none;
            line-height: 1;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .ap-btn:active { transform: scale(0.96); }
        .ap-btn-primary {
            background: linear-gradient(135deg, #5b73e8 0%, #4a62d7 100%);
            color: #fff;
            box-shadow: 0 3px 10px rgba(91,115,232,0.18);
        }
        .ap-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 14px rgba(91,115,232,0.25);
        }
        .ap-btn-secondary {
            background: #f3f4f6;
            color: #4b5563;
        }
        .ap-btn-secondary:hover { background: #e5e7eb; }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; gap: 16px; }
        }

        .form-group { margin-bottom: 0; }
        .form-group-block { margin-bottom: 20px; }
        .form-group-block:last-child { margin-bottom: 0; }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        .form-input {
            height: 40px;
            padding: 0 12px;
            border: 2px solid #f0f0f0;
            border-radius: 10px;
            font-size: 14px;
            color: #374151;
            outline: none;
            transition: all 0.2s;
            background: #fafafa;
            font-weight: 500;
            width: 100%;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        .form-input:focus {
            border-color: #5b73e8;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(91,115,232,0.08);
        }
        .form-input::placeholder { color: #c1c5cb; font-weight: 400; }
        .form-tips {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 6px;
            line-height: 1.4;
        }

        .section-divider {
            height: 1px;
            background: #f0f0f0;
            margin: 24px 0;
        }
        .section-title-inline {
            font-size: 13px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-title-inline::before {
            content: '';
            width: 4px;
            height: 16px;
            background: #5b73e8;
            border-radius: 2px;
        }

        .ap-toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            background: #1e293b;
            color: #fff;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 500;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            transform: translateX(120%);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4,0,0.2,1);
            pointer-events: none;
            max-width: min(90vw, 360px);
            word-break: break-word;
        }
        .ap-toast.show {
            transform: translateX(0);
            opacity: 1;
        }
        .ap-toast-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 11px;
        }
        .ap-toast-icon.success { background: #2ca87f; color: #fff; }
        .ap-toast-icon.error { background: #dc2626; color: #fff; }

        .app-footer {
            background: linear-gradient(to bottom, #ffffff, #f8fafc);
            border-top: 1px solid #e2e8f0;
        }
        .app-footer p {
            font-size: 13px;
            letter-spacing: 0.05em;
        }
        .app-footer a {
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .app-footer a:hover {
            opacity: 0.8;
            text-decoration: underline;
        }

        .fade-in { animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity:0; transform: translateY(8px); } to { opacity:1; transform: translateY(0); } }

        .color-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .color-row input[type="text"] { flex: 1; }
        .color-row input[type="color"] {
            width: 44px;
            height: 40px;
            border: 2px solid #f0f0f0;
            border-radius: 10px;
            background: #fafafa;
            cursor: pointer;
            padding: 2px;
            -webkit-appearance: none;
        }
        .color-row input[type="color"]::-webkit-color-swatch-wrapper { padding: 0; }
        .color-row input[type="color"]::-webkit-color-swatch { border: none; border-radius: 6px; }

        select.form-input,
        input[type="datetime-local"].form-input {
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 32px;
            cursor: pointer;
        }
        input[type="datetime-local"].form-input::-webkit-calendar-picker-indicator {
            opacity: 0;
            display: none;
        }

        .select-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }
        .select-grid .form-label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        @media (max-width: 768px) {
            .select-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }
            .select-grid .form-label { font-size: 12px; margin-bottom: 6px; }
        }

        .tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .tag-box {
            cursor: pointer;
            position: relative;
            flex-shrink: 0;
        }
        .tag-box input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        .tag-box-inner {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 40px;
            padding: 0 16px;
            border-radius: 10px;
            border: 2px solid #f0f0f0;
            background: #fafafa;
            font-size: 13px;
            font-weight: 500;
            color: #6b7280;
            transition: all 0.2s ease;
            white-space: nowrap;
            user-select: none;
        }
        .tag-box input:checked + .tag-box-inner {
            border-color: var(--tag-color);
            background: var(--tag-bg);
            color: var(--tag-color);
            box-shadow: 0 0 0 3px rgba(91,115,232,0.08);
            font-weight: 600;
        }
        .tag-box-inner:active {
            transform: scale(0.96);
        }

        .media-input {
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 13px;
            line-height: 1.6;
            min-height: 100px;
            padding: 12px;
            height: auto;
        }

        .upload-btn-group {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: #f3f4f6;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            line-height: 1;
        }
        .upload-btn:hover { background: #e5e7eb; }
        .upload-btn svg {
            width: 16px;
            height: 16px;
            fill: #6b7280;
        }
        .upload-btn input[type="file"] { display: none; }

        .music-section {
            background: #fafafa;
            border-radius: 12px;
            padding: 16px;
            border: 2px solid #f0f0f0;
        }
        .parse-music-btn {
            padding: 0 18px;
            background: #5b73e8;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .parse-music-btn:hover { background: #4a62d7; }
        .parse-music-btn:active { transform: scale(0.96); }

        @media (max-width: 768px) {
            .ap-toast {
                bottom: calc(20px + env(safe-area-inset-bottom, 0px));
                right: 12px;
                padding: 10px 14px;
                font-size: 12px;
                max-width: calc(100vw - 24px);
            }
            .ap-toast-icon { width: 22px; height: 22px; font-size: 11px; }
            .ap-card-header { padding: 16px 20px; }
            .ap-card-body { padding: 20px; }
            .ap-btn { padding: 8px 14px; font-size: 13px; border-radius: 12px; }
            .section-divider { margin: 20px 0; }
        }
    </style>
</head>
<body class="text-gray-700 antialiased">

<aside id="sidebar" class="fixed left-0 top-0 h-full w-[280px] bg-white z-40 transition-transform duration-300 lg:translate-x-0 -translate-x-full shadow-xl lg:shadow-none border-r border-gray-100">
    <div class="h-16 flex items-center px-6 pt-2">
        <div class="flex items-center gap-2">
            <span class="font-extrabold text-2xl text-primary tracking-tight">云汀记</span>
            <span class="text-xs font-semibold text-primary -mt-2">朋友圈程序</span>
            <span class="ml-2 px-2 py-0.5 text-[10px] font-semibold bg-green-100 text-green-600 rounded-md">v1.5.0</span>
        </div>
    </div>
    <div class="px-4 mb-4">
        <div class="user-card flex items-center gap-3 px-4 py-3">
            <?php
            $avatar_raw = $settings['avatar'] ?? '';
            if ($avatar_raw && !preg_match('#^https?://#i', $avatar_raw) && $avatar_raw[0] !== '/') {
                $avatar_raw = '../' . $avatar_raw;
            }
            $avatar_src = $avatar_raw ?: 'https://ui-avatars.com/api/?name=' . urlencode($settings['site_name'] ?? '云汀记') . '&background=5b73e8&color=fff&size=128';
            ?>
            <img src="<?php echo htmlspecialchars($avatar_src); ?>" class="w-11 h-11 rounded-full" alt="User">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($settings['site_name'] ?? '云汀记'); ?></p>
                <p class="text-xs text-gray-500 mt-1"><a href="https://imoe.top" target="_blank" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-primary-light text-primary font-medium hover:bg-[#dbe4ff] transition-colors">官网 https://imoe.top</a></p>
            </div>
        </div>
    </div>
    <div class="h-[calc(100vh-160px)] overflow-y-auto sidebar-scroll pb-8">
        <?php
        $menu_groups = [
            '仪表盘' => [
                ['index', '后台首页', 'fa-home', false]
            ],
            '内容管理' => [
                ['posts', '动态管理', 'fa-stream', true],
                ['articles', '文章管理', 'fa-file-alt', true],
                ['pages', '独立页面', 'fa-file', true],
                ['tags', '标签管理', 'fa-tags', true]
            ],
            '互动交流' => [
                ['comments', '评论管理', 'fa-comments', true],
                ['page_comments', '留言管理', 'fa-comment-dots', true]
            ],
            '营销推广' => [
                ['ads', '广告管理', 'fa-bullhorn', false]
            ],
            '系统设置' => [
                ['settings', '前台设置', 'fa-cog', true],
                ['email', '邮箱提醒', 'fa-envelope', true],
                ['ban_manage', '违禁词与IP', 'fa-ban', true],
                ['uploads', '上传管理', 'fa-cloud-upload-alt', true],
                ['security', '安全管理', 'fa-shield-alt', true]
            ],
            '账户管理' => [
                ['profile', '个人设置', 'fa-user-cog', false],
                ['backup', '数据备份', 'fa-download', false],
                ['visitor_logs', '访客记录', 'fa-eye', false]
            ]
        ];
        foreach ($menu_groups as $group_name => $items):
            $has_active = false;
            foreach ($items as $item) { if ($item[0] === $page_id) $has_active = true; }
        ?>
        <div class="px-6 mt-2 mb-2">
            <span class="section-title font-bold uppercase"><?php echo $group_name; ?></span>
        </div>
        <ul class="space-y-0.5">
            <?php
            $first_item = $items[0];
            $group_has_sub = count($items) > 1;
            $group_id = $group_name . '_group';
            if ($group_has_sub):
            ?>
            <li>
                <div class="nav-item has-submenu <?php echo $has_active ? 'active' : ''; ?> flex items-center gap-3 px-4 py-3 text-sm font-medium cursor-pointer select-none <?php echo $has_active ? '' : 'text-gray-600'; ?>" onclick="toggleSubmenu('<?php echo $group_id; ?>', this)">
                    <i class="fas <?php echo $first_item[2]; ?> nav-icon"></i>
                    <span class="flex-1"><?php echo $group_name; ?></span>
                    <i class="fas fa-chevron-right submenu-arrow <?php echo $has_active ? 'rotate' : ''; ?>"></i>
                </div>
                <div id="<?php echo $group_id; ?>" class="submenu <?php echo $has_active ? 'open' : ''; ?>">
                    <ul class="py-1">
                        <?php foreach ($items as $item):
                            $id_menu = $item[0];
                            $name = $item[1];
                            $is_active = ($id_menu === $page_id);
                        ?>
                        <li>
                            <a href="<?php echo $id_menu; ?>.php" class="nav-item <?php echo $is_active ? 'active' : ''; ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium <?php echo $is_active ? '' : ($id_menu === 'logout' ? 'text-red-500' : 'text-gray-600'); ?>" data-page="<?php echo $id_menu; ?>">
                                <span class="w-5 flex justify-center"><span class="w-1.5 h-1.5 rounded-full <?php echo $is_active ? 'bg-primary' : 'bg-gray-300'; ?>"></span></span>
                                <span><?php echo $name; ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </li>
            <?php else:
                $item = $items[0];
                $id_menu = $item[0];
                $name = $item[1];
                $icon = $item[2];
                $is_active = ($id_menu === $page_id);
            ?>
            <li>
                <a href="<?php echo $id_menu; ?>.php" class="nav-item <?php echo $is_active ? 'active' : ''; ?> flex items-center gap-3 px-4 py-3 text-sm font-medium <?php echo $is_active ? '' : ($id_menu === 'logout' ? 'text-red-500' : 'text-gray-600'); ?>" data-page="<?php echo $id_menu; ?>">
                    <i class="fas <?php echo $icon; ?> nav-icon"></i>
                    <span class="flex-1"><?php echo $name; ?></span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
        <?php endforeach; ?>
        <?php
        $logout_active = ($page_id === 'logout');
        ?>
        <div class="px-6 mt-4 mb-2">
            <span class="section-title font-bold uppercase">退出</span>
        </div>
        <ul class="space-y-0.5">
            <li>
                <a href="logout.php" class="nav-item <?php echo $logout_active ? 'active' : ''; ?> flex items-center gap-3 px-4 py-3 text-sm font-medium <?php echo $logout_active ? '' : 'text-red-500'; ?>" data-page="logout">
                    <i class="fas fa-sign-out-alt nav-icon <?php echo $logout_active ? '' : 'text-red-400'; ?>"></i>
                    <span class="flex-1">退出登录</span>
                </a>
            </li>
        </ul>
    </div>
    <div class="absolute bottom-0 left-0 right-0 px-6 py-3 border-t border-gray-100 bg-white">
        <p class="text-[10px] text-gray-400 text-center">
            <a href="https://imoe.top" target="_blank" class="text-primary hover:underline font-medium">云汀记</a> &copy; 2025 朋友圈
        </p>
    </div>
</aside>

<div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-30 hidden lg:hidden" onclick="toggleSidebar()"></div>

<div class="lg:ml-[280px] min-h-screen flex flex-col">
    <header class="h-16 bg-white border-b border-gray-100 flex items-center justify-between px-4 lg:px-8 sticky top-0 z-20">
        <div class="flex items-center gap-4">
            <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 text-gray-600">
                <i class="fas fa-bars text-lg"></i>
            </button>
            <div class="flex items-center gap-3">
                <h1 class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
            </div>
        </div>
    </header>

    <main id="mainContent" class="flex-1 p-4 lg:p-8">
        <div id="contentArea">

<div class="fade-in">
    <div class="ap-card">
        <div class="ap-card-header">
            <div class="ap-card-title"><?php echo $page_title; ?></div>
            <div style="display:flex; gap:10px;">
                <a href="posts.php" class="ap-btn ap-btn-secondary"><i class="fas fa-arrow-left"></i> 返回列表</a>
                <button type="button" onclick="document.getElementById('postForm').submit();" class="ap-btn ap-btn-primary" id="postSaveBtn"><i class="fas fa-save"></i> 保存</button>
            </div>
        </div>
        <div class="ap-card-body">
            <form id="postForm" method="POST" action="">

                <div class="section-title-inline">基础信息</div>

                <div class="form-group-block">
                    <label class="form-label">动态内容</label>
                    <textarea name="content" class="form-input media-input" rows="5" placeholder="分享点什么..." id="postContent"><?php echo htmlspecialchars($post['content']); ?></textarea>
                </div>

                <div class="form-grid" style="margin-bottom:20px;">
                    <div class="form-group">
                        <label class="form-label">本条动态昵称</label>
                        <input type="text" name="post_nickname" class="form-input" value="<?php echo htmlspecialchars($post['nickname'] ?? ''); ?>" placeholder="<?php echo htmlspecialchars($settings['nickname'] ?? '云汀记'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">本条动态头像</label>
                        <input type="url" name="post_avatar" class="form-input" value="<?php echo htmlspecialchars($post['avatar'] ?? ''); ?>" placeholder="<?php echo htmlspecialchars($settings['avatar'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group-block">
                    <label class="form-label">本条昵称颜色</label>
                    <div class="color-row">
                        <input type="text" class="form-input" value="<?php echo htmlspecialchars($post['nickname_color'] ?: ($settings['nickname_color'] ?? '#5d6d9e')); ?>" placeholder="#5d6d9e" oninput="this.nextElementSibling.value=this.value">
                        <input type="color" name="post_nickname_color" value="<?php echo htmlspecialchars($post['nickname_color'] ?: ($settings['nickname_color'] ?? '#5d6d9e')); ?>" oninput="this.previousElementSibling.value=this.value">
                    </div>
                </div>

                <div class="form-group-block">
                    <label class="form-label">本条动态位置</label>
                    <input type="text" name="location_name" class="form-input" placeholder="手动填写，例：杭州西湖公园" value="<?php echo htmlspecialchars($post['location_name']); ?>" id="postLocation">
                </div>

                <div class="section-divider"></div>

                <div class="section-title-inline">标签选择</div>
                <div class="form-group-block">
                    <div class="tag-list">
                        <?php foreach ($all_tags as $tag):
                            $name = htmlspecialchars($tag['name']);
                            $len  = mb_strlen($name, 'UTF-8');
                            if ($len > 6) {
                                $name = mb_substr($name, 0, 5, 'UTF-8') . '…';
                            }
                        ?>
                        <label class="tag-box">
                            <input type="checkbox" name="tags[]" value="<?php echo $tag['id']; ?>" <?php echo in_array($tag['id'], $post_tags) ? 'checked' : ''; ?>>
                            <span class="tag-box-inner" style="--tag-color: <?php echo $tag['color']; ?>; --tag-bg: <?php echo $tag['bg_color']; ?>;">
                                <?php echo $name; ?>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="section-divider"></div>

                <div class="section-title-inline">媒体文件</div>

                <div class="form-group-block">
                    <label class="form-label">图片链接（每行一个，最多9张）</label>
                    <textarea name="images" class="form-input media-input" rows="4" placeholder="图片URL链接" id="postImages"><?php
                        $imgs = json_decode($post['images'] ?? '[]', true) ?: [];
                        echo implode("\n", $imgs);
                    ?></textarea>
                    <div class="form-tips">已添加 <span id="imageCount"><?php echo count($imgs); ?></span> 张</div>
                    <div class="upload-btn-group">
                        <label class="upload-btn" id="btnUploadImg">
                            <svg viewBox="0 0 24 24"><path d="M9 16h6v-6h4l-7-7-7 7h4v6zm-4 2h14v2H5v-2z"/></svg>
                            <span>上传图片</span>
                            <input type="file" accept="image/*" multiple id="fileUploadImg">
                        </label>
                        <button type="button" class="upload-btn" onclick="clearImages()">
                            <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                            <span>清空图片</span>
                        </button>
                    </div>
                </div>

                <div class="form-group-block">
                    <label class="form-label">视频链接（每行一个，最多9个）</label>
                    <textarea name="videos" class="form-input media-input" rows="3" placeholder="视频URL链接" id="postVideos"><?php
                        $vids = json_decode($post['videos'] ?? '[]', true) ?: [];
                        echo implode("\n", $vids);
                    ?></textarea>
                    <div class="form-tips">已添加 <span id="videoCount"><?php echo count($vids); ?></span> 个</div>
                    <div class="upload-btn-group">
                        <label class="upload-btn" id="btnUploadVid">
                            <svg viewBox="0 0 24 24"><path d="M10 8v8l6-4-6-4zm9-3H5c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 12H5V7h14v10z"/></svg>
                            <span>上传视频</span>
                            <input type="file" accept="video/*" id="fileUploadVid">
                        </label>
                        <label class="upload-btn" id="btnUploadVidCover">
                            <svg viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                            <span>自定义封面</span>
                            <input type="file" accept="image/*" id="fileUploadVidImg">
                        </label>
                        <button type="button" class="upload-btn" onclick="clearVideos()">
                            <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                            <span>清空视频</span>
                        </button>
                        <span style="font-size:11px; color:#999; display:inline-flex; align-items:center; height:36px;">不填写封面自动截取视频封面</span>
                    </div>
                </div>

                <input type="hidden" name="video_covers" id="postVideoCovers" value="<?php echo htmlspecialchars(implode("\n", json_decode($post['video_covers'] ?? '[]', true) ?: [])); ?>">

                <div class="section-divider"></div>

                <div class="section-title-inline">背景音乐</div>
                <div class="form-group-block">
                    <div class="music-section">
                        <div class="form-group-block" style="margin-bottom:12px;">
                            <label style="font-size:13px; color:#666; margin-bottom:6px; display:block; font-weight:600;">音乐平台</label>
                            <select class="form-input" id="musicPlatform" style="margin-bottom:10px;">
                                <option value="netease">网易云音乐</option>
                                <option value="qq">QQ音乐</option>
                            </select>
                            <label style="font-size:13px; color:#666; margin-bottom:6px; display:block; font-weight:600;">音乐分享链接</label>
                            <div style="display:flex; gap:8px; align-items:stretch;">
                                <input type="url" class="form-input" id="musicShareUrl" placeholder="粘贴音乐分享链接，自动识别平台" style="flex:1;">
                                <button type="button" class="parse-music-btn" onclick="parseMusic()" style="display:flex; align-items:center; justify-content:center;">自动获取</button>
                            </div>
                            <div class="form-tips" style="margin-top:6px;">支持网易云音乐 / QQ音乐分享链接，点击自动获取歌曲信息</div>
                        </div>

                        <?php $m = (!empty($post_music) && is_array($post_music)) ? $post_music[0] : null; ?>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <input type="url" name="music_url" class="form-input" placeholder="音乐URL链接" value="<?php echo htmlspecialchars($m['url'] ?? ''); ?>">
                            <input type="url" name="music_cover" class="form-input" placeholder="封面URL链接" value="<?php echo htmlspecialchars($m['cover'] ?? ''); ?>">
                            <input type="text" name="music_name" class="form-input" placeholder="音乐歌名" value="<?php echo htmlspecialchars($m['name'] ?? ''); ?>">
                            <input type="text" name="music_artist" class="form-input" placeholder="音乐作者" value="<?php echo htmlspecialchars($m['artist'] ?? ''); ?>">
                        </div>
                        <div id="musicParseResult" style="display:none; margin-top:8px;">
                            <span style="color:#10b981; font-size:13px;">获取成功</span>
                        </div>
                    </div>
                </div>

                <div class="section-divider"></div>

                <div class="section-title-inline">发布设置</div>

                <div class="form-grid" style="margin-bottom:20px;">
                    <div class="form-group">
                        <label class="form-label">状态</label>
                        <select name="status" class="form-input" id="postStatus">
                            <option value="1" <?php echo $post['status'] == 1 ? 'selected' : ''; ?>>显示</option>
                            <option value="0" <?php echo $post['status'] == 0 ? 'selected' : ''; ?>>隐藏</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">发布时间</label>
                        <input type="datetime-local" name="published_at" class="form-input" value="<?php echo $post['published_at'] ? date('Y-m-d\TH:i', strtotime($post['published_at'])) : ''; ?>" id="postTime">
                        <div class="form-tips" style="margin-top:4px;">留空则使用当前时间</div>
                    </div>
                </div>

                <div class="select-grid">
                    <div class="form-group">
                        <label class="form-label">评论开关</label>
                        <select name="comment_enabled" class="form-input">
                            <option value="1" <?php echo ($post['comment_enabled'] ?? 1) ? 'selected' : ''; ?>>开启</option>
                            <option value="0" <?php echo ($post['comment_enabled'] ?? 1) ? '' : 'selected'; ?>>关闭</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">点赞开关</label>
                        <select name="like_enabled" class="form-input">
                            <option value="1" <?php echo ($post['like_enabled'] ?? 1) ? 'selected' : ''; ?>>开启</option>
                            <option value="0" <?php echo ($post['like_enabled'] ?? 1) ? '' : 'selected'; ?>>关闭</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">是否置顶</label>
                        <select name="is_top" class="form-input">
                            <option value="1" <?php echo $post['is_top'] ? 'selected' : ''; ?>>是</option>
                            <option value="0" <?php echo $post['is_top'] ? '' : 'selected'; ?>>否</option>
                        </select>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

        </div>
    </main>

    <footer class="app-footer py-5 px-4 lg:px-8 mt-auto">
        <div class="flex items-center justify-center">
            <p class="text-gray-500 font-medium">
                <a href="https://imoe.top" target="_blank" class="text-primary font-bold">云汀记</a>
                <span class="text-gray-400 mx-1">&copy;</span>
                <span>2025 朋友圈</span>
            </p>
        </div>
    </footer>
</div>

<script>

var _musicParseMode = '<?php echo htmlspecialchars($settings['music_parse_mode'] ?? 'official'); ?>';

function toggleSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
}

function toggleSubmenu(id, el) {
    var submenu = document.getElementById(id);
    var arrow = el.querySelector('.submenu-arrow');
    var isOpen = submenu.classList.contains('open');
    if (isOpen) {
        submenu.classList.remove('open');
        arrow.classList.remove('rotate');
    } else {
        submenu.classList.add('open');
        arrow.classList.add('rotate');
    }
}

function showToast(msg, type) {
    type = type || 'success';
    var existing = document.querySelector('.ap-toast');
    if (existing) existing.remove();

    var toast = document.createElement('div');
    toast.className = 'ap-toast';
    var iconClass = type === 'success' ? 'fa-check' : 'fa-exclamation';
    var iconBg = type === 'success' ? 'success' : 'error';
    toast.innerHTML = '<div class="ap-toast-icon ' + iconBg + '"><i class="fas ' + iconClass + '"></i></div><span>' + msg + '</span>';
    document.body.appendChild(toast);

    requestAnimationFrame(function() {
        toast.classList.add('show');
    });

    setTimeout(function() {
        toast.classList.remove('show');
        setTimeout(function() { toast.remove(); }, 400);
    }, 3000);
}

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($toast_msg): ?>
    showToast('<?php echo htmlspecialchars($toast_msg, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo (strpos($toast_msg, '失败') !== false || strpos($toast_msg, '错误') !== false) ? 'error' : 'success'; ?>');
    <?php endif; ?>
});

document.querySelectorAll('textarea, input[type="text"], input[type="url"], input[type="email"]').forEach(function(el){
    el.addEventListener('mousedown',function(){this.focus();});
    el.addEventListener('keydown',function(e){if(e.key==='a'&&e.ctrlKey){e.stopPropagation();}});
});

document.getElementById('postForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var imgLines = document.getElementById('postImages').value.trim().split('\n').filter(function(l){return l.trim();});
    if(imgLines.length > 9){showToast('图片上限9个，请检查图片','error');return;}
    var vidLines = document.getElementById('postVideos').value.trim().split('\n').filter(function(l){return l.trim();});
    if(vidLines.length > 9){showToast('视频上限9个，请检查视频','error');return;}
    var btn = document.getElementById('postSaveBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 保存中...';
    var formData = new FormData(this);
    formData.append('ajax_save_post', '1');
    fetch(window.location.href, {method: 'POST', body: formData})
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.code === 1) {
            showToast('保存成功');
            btn.innerHTML = '<i class="fas fa-save"></i> 保存';
            setTimeout(function() {
                if (data.data && data.data.id) {
                    window.location.href = 'post_edit.php?id=' + data.data.id;
                } else {
                    window.location.reload();
                }
            }, 600);
        } else {
            showToast(data.msg || '保存失败', 'error');
            btn.innerHTML = '<i class="fas fa-save"></i> 保存';
        }
    })
    .catch(function() { showToast('保存失败', 'error'); btn.innerHTML = '<i class="fas fa-save"></i> 保存'; });
});

function parseMusic(){
    var url = document.getElementById('musicShareUrl').value.trim();
    if(!url){showToast('请填写音乐分享链接','error');return;}
    var platform = document.getElementById('musicPlatform') ? document.getElementById('musicPlatform').value : 'netease';
    var btn = document.querySelector('.parse-music-btn');
    if(btn.textContent==='获取中...' || btn.textContent==='获取成功') return;
    btn.textContent='获取中...';
    btn.style.background='#e0e0e0';
    btn.style.color='#666';

    fetch('../api/music_parse.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'url='+encodeURIComponent(url)+'&platform='+encodeURIComponent(platform)
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
        if(data.code===1){
            fillMusicData(data.data);
            btn.textContent='获取成功';
            btn.style.background='#10b981';
            btn.style.color='#fff';
            showToast('音乐信息获取成功');
            setTimeout(function(){
                btn.textContent='自动获取';
                btn.style.background='';
                btn.style.color='';
            }, 2500);
        }else{
            showToast(data.msg || '解析失败', 'error');

            if(platform === 'netease' && _musicParseMode !== 'custom'){
                parseMusicFrontend(url, btn);
            }else{
                document.querySelector('input[name="music_url"]').value = '';
                document.querySelector('input[name="music_name"]').value = '';
                document.querySelector('input[name="music_artist"]').value = '';
                document.querySelector('input[name="music_cover"]').value = '';
                btn.textContent='自动获取';
                btn.style.background='';
                btn.style.color='';
            }
        }
    })
    .catch(function(){
        if(platform === 'netease' && _musicParseMode !== 'custom'){
            parseMusicFrontend(url, btn);
        }else{
            showToast('解析失败，请检查网络或链接','error');
            btn.textContent='自动获取';
            btn.style.background='';
            btn.style.color='';
        }
    });
}

function parseMusicFrontend(url, btn){
    if(!/^https?:\/\//.test(url)){
        var m = url.match(/(https?:\/\/[^\s（）()]+)/);
        if(m) url = m[1].replace(/[,.;]$/,'');
    }
    var songId = '';
    var m1 = url.match(/[?&]id=(\d+)/);
    var m2 = url.match(/\/song\/(\d+)/);
    if(m1) songId = m1[1];
    else if(m2) songId = m2[1];

    if(!songId){
        showToast('无法识别歌曲ID，请检查链接格式','error');
        btn.textContent='自动获取';
        btn.style.background='';
        btn.style.color='';
        return;
    }

    showToast('正在通过前端接口获取...');

    fetch('https://music.163.com/api/song/detail?ids=[' + songId + ']', {
        method: 'GET',
        credentials: 'omit'
    })
    .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
    .then(function(data){
        if(data.songs && data.songs.length > 0){
            var song = data.songs[0];
            var name = song.name || '';
            var artists = '';
            if(song.artists && song.artists.length){
                artists = song.artists.map(function(a){return a.name;}).join(' / ');
            }else if(song.ar && song.ar.length){
                artists = song.ar.map(function(a){return a.name;}).join(' / ');
            }
            var picUrl = '';
            if(song.album && song.album.picUrl) picUrl = song.album.picUrl;
            else if(song.al && song.al.picUrl) picUrl = song.al.picUrl;

            fillMusicData({name:name, artist:artists, cover:picUrl, url:''});

            var outerUrl = 'https://music.163.com/song/media/outer/url?id=' + songId + '.mp3';
            document.querySelector('input[name="music_url"]').value = outerUrl;
            return {outer:true};
        }else{
            throw new Error('未找到歌曲');
        }
    })
    .then(function(urlData){
        if(urlData && urlData.data && urlData.data.length > 0 && urlData.data[0].url){
            document.querySelector('input[name="music_url"]').value = urlData.data[0].url;
        }
        btn.textContent='获取成功';
        btn.style.background='#10b981';
        btn.style.color='#fff';
        showToast('音乐信息获取成功（前端接口）');
        setTimeout(function(){
            btn.textContent='自动获取';
            btn.style.background='';
            btn.style.color='';
        }, 2500);
    })
    .catch(function(err){
        console.error('音乐获取失败:', err);
        showToast('获取失败：' + (err.message || '网络错误或接口不可用'),'error');
        btn.textContent='自动获取';
        btn.style.background='';
        btn.style.color='';
    });
}

function fillMusicData(data){
    if(data.name) document.querySelector('input[name="music_name"]').value = data.name;
    if(data.artist) document.querySelector('input[name="music_artist"]').value = data.artist;
    if(data.cover) document.querySelector('input[name="music_cover"]').value = data.cover;
    if(data.url) document.querySelector('input[name="music_url"]').value = data.url;
}

document.querySelectorAll('input[type="color"]').forEach(function(c){
    c.addEventListener('input',function(){this.previousElementSibling.value=this.value;});
});

function insertPostPlaceholder(placeholder) {
    var textarea = document.getElementById('postContent');
    var code = '\n' + placeholder + '\n';
    insertAtCursorPost(textarea, code);
}

function wrapPostTag(tag) {
    var textarea = document.getElementById('postContent');
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    var val = textarea.value;
    var selected = val.substring(start, end);
    var before = val.substring(0, start);
    var after = val.substring(end);
    var tagName = tag.split(' ')[0];
    var openTag, closeTag;
    if (tag === 'a') {
        openTag = '<a href="#">';
        closeTag = '</a>';
    } else {
        openTag = '<' + tag + '>';
        closeTag = '</' + tagName + '>';
    }
    var replacement = selected ? openTag + selected + closeTag : openTag + closeTag;
    textarea.value = before + replacement + after;
    textarea.focus();
    textarea.selectionStart = start + openTag.length;
    textarea.selectionEnd = start + openTag.length + (selected ? selected.length : 0);
}

function insertPostTag(tag) {
    var textarea = document.getElementById('postContent');
    insertAtCursorPost(textarea, '<' + tag + '>');
}

function insertAtCursorPost(textarea, text) {
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    var before = textarea.value.substring(0, start);
    var after = textarea.value.substring(end);
    textarea.value = before + text + after;
    textarea.focus();
    var newPos = start + text.length;
    textarea.selectionStart = newPos;
    textarea.selectionEnd = newPos;
}

var ADMIN_STICKER_LIST=['3d眼镜','EDG','LPL','beluga','不好意思','不服吗','亲亲','伞兵','倚墙笑','值得肯定','偷偷看','傻笑','再见','出家人','击剑','加班','勉强笑','危险','发红包','吃手','吃瓜','吐血','吵架','呦吼','呲牙笑','哈士奇','哈士奇失去意识','哈士奇失望','哭泣','唱歌','喜欢','嘿哈','大笑','失去信号','失望','头秃','奋斗','好奇','好的','害羞','小丑','小偷','尬笑','尴尬','应援','开心','引起不适','微笑','思考','恶心','恶魔','恶魔恐惧','惊吓','惊吓白眼','惊讶','惬意','感动','愤怒','我看好你','手机相机','打咩','打牌','托腮','扶额','抠鼻','抬眼镜','拜托','捂嘴笑','捂脸','擦汗','放鞭炮','敬礼','整理发型','斗鸡眼','智慧的眼神','月饼','有没有搞错','正确','没招','波吉','泪奔','流汗微笑','流鼻涕','深思','滑稽','滑稽吃瓜','滑稽喝水','滑稽奶茶','滑稽柠檬','滑稽狂汗','滑稽被子','烦恼','熊熊','熊猫','熊猫唱歌','熊猫喜欢','熊猫失望','熊猫意外','熬夜','爆炸','牛年进宝','狂热','狗头','狗头围脖','狗头失望','狗头意外','狗头胖次','狗头花','狗头草','猪头','猪头意外','生病','电话','疑问','疼痛','痛哭','看穿一切','眩晕','睡觉','禁言','笑哭','纠结','绿帽','缺牙笑','翻白眼','老虎意外','耍酷','胡子','菜狗','菜狗花','蒙面滑稽','虎年进宝','被打','裂开','警告','读书','财神红包','超爱','这是啥','送福','送花','错误','阴险','难以置信','面具','饥渴','鬼脸','黑线','鼓掌'];
var ADMIN_STICKER_LIST_KAWA=['kawa-1','kawa-2','kawa-3','kawa-4','kawa-5','kawa-6','kawa-7','kawa-8','kawa-9','kawa-10','kawa-11','kawa-12','kawa-13','kawa-14','kawa-15','kawa-16','kawa-17','kawa-18','kawa-19','kawa-20','kawa-21','kawa-22','kawa-23','kawa-24','kawa-25','kawa-26','kawa-27','kawa-28','kawa-29','kawa-30','kawa-31','kawa-32'];
var ADMIN_STICKER_HEO_BASE='../assets/stickers2/';
var ADMIN_STICKER_KAWA_BASE='../assets/stickers/';

function toggleStickerPanel(){
    var p=document.getElementById('stickerPanel');
    if(p.style.display==='block'){
        p.style.display='none';
    }else{
        var g=document.getElementById('stickerGrid');
        if(!g.dataset.loaded){
            switchStickerTab('kawa');
            g.dataset.loaded='1';
        }
        p.style.display='block';
    }
}

function heoStickerFilename(name){
    var result='';
    for(var i=0;i<name.length;i++){
        var cp=name.charCodeAt(i);
        if(cp>127){result+='#U'+cp.toString(16).toLowerCase().padStart(4,'0');}
        else{result+=name.charAt(i);}
    }
    return encodeURIComponent(result+'.png');
}

function switchStickerTab(tab){
    var g=document.getElementById('stickerGrid');
    var h='';
    if(tab==='heo'){
        ADMIN_STICKER_LIST.forEach(function(s){
            var fn=heoStickerFilename(s);
            h+='<div class="sticker-grid-item" onclick="insertSticker(\''+s+'\',\'heo\')" title="'+s+'"><img src="'+ADMIN_STICKER_HEO_BASE+fn+'" alt="'+s+'" loading="lazy" onerror="this.style.display=\'none\';this.parentElement.style.opacity=\'0.2\';"></div>';
        });
    }else{
        ADMIN_STICKER_LIST_KAWA.forEach(function(s){
            var name=s.replace('kawa-','');
            h+='<div class="sticker-grid-item" onclick="insertSticker(\''+name+'\',\'kawa\')" title="'+s+'"><img src="'+ADMIN_STICKER_KAWA_BASE+encodeURIComponent(s)+'.png" alt="'+s+'" loading="lazy"></div>';
        });
    }
    g.innerHTML=h;
    document.querySelectorAll('.sticker-tab').forEach(function(t){
        if(t.dataset.tab===tab){
            t.classList.add('active');
            t.style.background='rgba(93,109,158,0.12)';
            t.style.color='#5d6d9e';
            t.style.fontWeight='600';
        }else{
            t.classList.remove('active');
            t.style.background='transparent';
            t.style.color='#888';
            t.style.fontWeight='400';
        }
    });
}

function insertSticker(name, type){
    var ta=document.getElementById('postContent');
    ta.focus();
    var code= type==='kawa' ? ':kawa-'+name+':' : ':heo-'+name+':';
    var start=ta.selectionStart, end=ta.selectionEnd, taVal=ta.value;
    ta.value=taVal.substring(0,start)+code+taVal.substring(end);
    ta.selectionStart=ta.selectionEnd=start+code.length;
    document.getElementById('stickerPanel').style.display='none';
}

document.addEventListener('click',function(e){
    var panel=document.getElementById('stickerPanel');
    var btn=panel?panel.closest('.form-group,.form-row').querySelector('.sticker-btn-inline,.sticker-btn'):null;
    if(panel && panel.style.display==='block' && !panel.contains(e.target) && (!btn||(!btn.contains(e.target)&&e.target!==btn))){
        panel.style.display='none';
    }
});

window.uploadState = { customVidCover: '' };

document.addEventListener('DOMContentLoaded', function() {
    var fileUploadImg = document.getElementById('fileUploadImg');
    if (fileUploadImg) {
        fileUploadImg.addEventListener('change', function(e) {
            handleImagesUpload(e.target.files);
        });
    }
    var fileUploadVid = document.getElementById('fileUploadVid');
    if (fileUploadVid) {
        fileUploadVid.addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                handleVideoUpload(e.target.files[0]);
            }
        });
    }
    var fileUploadVidImg = document.getElementById('fileUploadVidImg');
    if (fileUploadVidImg) {
        fileUploadVidImg.addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                handleVidCoverUpload(e.target.files[0]);
            }
        });
    }
});

function setBtnLoading(btn, loading) {
    if (!btn) return;
    if (loading) {
        btn.classList.add('uploading');
        btn.dataset.origText = btn.querySelector('span').textContent;
        btn.querySelector('span').textContent = '上传中...';
    } else {
        btn.classList.remove('uploading');
        if (btn.dataset.origText) {
            btn.querySelector('span').textContent = btn.dataset.origText;
        }
    }
}

function doUpload(formData, btn, onSuccess, onError) {
    setBtnLoading(btn, true);
    fetch('../api/upload.php', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        setBtnLoading(btn, false);
        if (data.code === 1) {
            onSuccess(data);
        } else {
            showToast(data.msg || '上传失败', 'error');
            if (onError) onError(data.msg);
        }
    })
    .catch(function(err) {
        setBtnLoading(btn, false);
        showToast('上传失败：请检查 ../api/upload.php 是否存在', 'error');
        if (onError) onError(err);
    });
}

function handleImagesUpload(files) {
    var btn = document.getElementById('btnUploadImg');
    var textarea = document.getElementById('postImages');
    var max = 9;
    var currentLines = textarea.value.trim().split('\n').filter(function(l) { return l.trim(); });
    var remaining = max - currentLines.length;
    if (remaining <= 0) {
        showToast('图片上限' + max + '个，请检查图片', 'error');
        document.getElementById('fileUploadImg').value = '';
        return;
    }
    var uploadFiles = Array.prototype.slice.call(files, 0, remaining);
    var formData = new FormData();
    uploadFiles.forEach(function(file) {
        formData.append('images[]', file);
    });
    doUpload(formData, btn, function(data) {
        if (data.data && data.data.urls && data.data.urls.length > 0) {
            data.data.urls.forEach(function(url) {
                var val = textarea.value.trim();
                textarea.value = val ? (val + '\n' + url) : url;
            });
            updateImageCount();
            showToast('上传成功 ' + data.data.urls.length + ' 张');
        }
        document.getElementById('fileUploadImg').value = '';
    }, function() {
        document.getElementById('fileUploadImg').value = '';
    });
}

function handleVideoUpload(file) {
    var btn = document.getElementById('btnUploadVid');
    var textarea = document.getElementById('postVideos');
    var max = 9;
    var currentLines = textarea.value.trim().split('\n').filter(function(l) { return l.trim(); });
    if (currentLines.length >= max) {
        showToast('视频上限' + max + '个，请检查视频', 'error');
        document.getElementById('fileUploadVid').value = '';
        return;
    }
    var formData = new FormData();
    formData.append('video', file);
    doUpload(formData, btn, function(data) {
        if (data.data && data.data.url) {
            var url = data.data.url;
            var val = textarea.value.trim();
            textarea.value = val ? (val + '\n' + url) : url;
            updateVideoCount();
            var customCover = window.uploadState.customVidCover;
            if (customCover) {
                var coverInput = document.getElementById('postVideoCovers');
                var cv = coverInput.value.trim();
                coverInput.value = cv ? (cv + '\n' + customCover) : customCover;
                window.uploadState.customVidCover = '';
                showToast('视频上传成功（使用自定义封面）');
                document.getElementById('fileUploadVid').value = '';
                document.getElementById('fileUploadVidImg').value = '';
            } else {
                showToast('视频上传成功，正在截取封面...');
                captureVideoCover(file).then(function(coverUrl) {
                    var coverInput = document.getElementById('postVideoCovers');
                    var cv = coverInput.value.trim();
                    coverInput.value = cv ? (cv + '\n' + coverUrl) : coverUrl;
                    showToast('封面截取成功');
                }).catch(function(err) {
                    showToast('封面截取失败：' + err.message, 'error');
                });
                document.getElementById('fileUploadVid').value = '';
            }
        }
    }, function() {
        document.getElementById('fileUploadVid').value = '';
    });
}

function handleVidCoverUpload(file) {
    var btn = document.getElementById('btnUploadVidCover');
    var formData = new FormData();
    formData.append('image', file);
    doUpload(formData, btn, function(data) {
        if (data.data && data.data.url) {
            window.uploadState.customVidCover = data.data.url;
            showToast('自定义封面已选择，请上传视频');
        }
        document.getElementById('fileUploadVidImg').value = '';
    }, function() {
        document.getElementById('fileUploadVidImg').value = '';
    });
}

function captureVideoCover(file) {
    return new Promise(function(resolve, reject) {
        var localUrl = URL.createObjectURL(file);
        var video = document.createElement('video');
        video.src = localUrl;
        video.muted = true;
        video.playsInline = true;
        video.preload = 'auto';
        video.onloadeddata = function() {
            video.currentTime = 0.1;
        };
        video.onseeked = function() {
            var canvas = document.createElement('canvas');
            canvas.width = video.videoWidth || 640;
            canvas.height = video.videoHeight || 480;
            var ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            URL.revokeObjectURL(localUrl);
            canvas.toBlob(function(blob) {
                if (!blob) { reject(new Error('canvas转blob失败')); return; }
                var formData = new FormData();
                formData.append('image', blob, 'cover_' + Date.now() + '.jpg');
                fetch('../api/upload.php', {method:'POST',body:formData})
                .then(function(r){return r.json();})
                .then(function(data){
                    if(data.code===1 && data.data && data.data.url){resolve(data.data.url);}
                    else{reject(new Error(data.msg||'封面上传失败'));}
                }).catch(reject);
            }, 'image/jpeg', 0.85);
        };
        video.onerror = function() {
            URL.revokeObjectURL(localUrl);
            reject(new Error('视频加载失败'));
        };
    });
}

function clearImages() {
    if (confirm('确定清空所有图片？')) {
        document.getElementById('postImages').value = '';
        updateImageCount();
    }
}
function clearVideos() {
    if (confirm('确定清空所有视频和封面？')) {
        document.getElementById('postVideos').value = '';
        document.getElementById('postVideoCovers').value = '';
        updateVideoCount();
    }
}
function updateImageCount() {
    var textarea = document.getElementById('postImages');
    var lines = textarea.value.trim().split('\n').filter(function(l) { return l.trim(); });
    document.getElementById('imageCount').textContent = lines.length;
}
function updateVideoCount() {
    var textarea = document.getElementById('postVideos');
    var lines = textarea.value.trim().split('\n').filter(function(l) { return l.trim(); });
    document.getElementById('videoCount').textContent = lines.length;
}
document.getElementById('postImages').addEventListener('input', updateImageCount);
document.getElementById('postVideos').addEventListener('input', updateVideoCount);
</script>
</body>
</html>
