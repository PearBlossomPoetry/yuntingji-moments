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

$page_title = isset($_GET['id']) ? '编辑广告' : '发布广告';
$page_id = 'ads';

$id = intval($_GET['id'] ?? 0);
$ad = ['id'=>0,'style'=>1,'avatar'=>'','title'=>'','subtitle'=>'','content'=>'','image'=>'','card_title'=>'','card_desc'=>'','button_text'=>'立即查看','button_color'=>'#07C160','link'=>'','brand_logo'=>'','brand_name'=>'','nickname_color'=>'#5d6d9e','is_top'=>0,'comment_enabled'=>1,'like_enabled'=>1,'status'=>1,'published_at'=>''];

if ($id) {
    $result = mysqli_query($conn, "SELECT * FROM ads WHERE id = $id");
    if ($row = mysqli_fetch_assoc($result)) $ad = $row;
}

$settings = get_all_settings();
$default_color = $settings['nickname_color'] ?? '#5d6d9e';

$toast_msg = $_SESSION['admin_toast'] ?? '';
if ($toast_msg) {
    unset($_SESSION['admin_toast']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $style = intval($_POST['style'] ?? 1);
    $avatar = mysqli_real_escape_string($conn, $_POST['avatar'] ?? '');
    $title = mysqli_real_escape_string($conn, $_POST['title'] ?? '');
    $subtitle = mysqli_real_escape_string($conn, $_POST['subtitle'] ?? 'Sponsored');
    $content = mysqli_real_escape_string($conn, $_POST['content'] ?? '');
    $image = mysqli_real_escape_string($conn, $_POST['image'] ?? '');
    $card_title = mysqli_real_escape_string($conn, $_POST['card_title'] ?? '');
    $card_desc = mysqli_real_escape_string($conn, $_POST['card_desc'] ?? '');
    $button_text = mysqli_real_escape_string($conn, $_POST['button_text'] ?? '立即查看');
    $button_color = mysqli_real_escape_string($conn, $_POST['button_color'] ?? '#07C160');
    $link = mysqli_real_escape_string($conn, $_POST['link'] ?? '');
    $brand_logo = mysqli_real_escape_string($conn, $_POST['brand_logo'] ?? '');
    $brand_name = mysqli_real_escape_string($conn, $_POST['brand_name'] ?? '');
    $nickname_color = mysqli_real_escape_string($conn, $_POST['nickname_color'] ?? '#5d6d9e');
    $status = intval($_POST['status'] ?? 1);
    $is_top = intval($_POST['is_top'] ?? 0);
    $comment_enabled = intval($_POST['comment_enabled'] ?? 0);
    $like_enabled = intval($_POST['like_enabled'] ?? 0);
    $published_at_raw = trim($_POST['published_at'] ?? '');
    $published_at = empty($published_at_raw) ? date('Y-m-d H:i:s') : $published_at_raw;
    $published_at_esc = mysqli_real_escape_string($conn, $published_at);

    if ($id) {
        mysqli_query($conn, "UPDATE ads SET style=$style, avatar='$avatar', title='$title', subtitle='$subtitle', content='$content', image='$image', card_title='$card_title', card_desc='$card_desc', button_text='$button_text', button_color='$button_color', link='$link', brand_logo='$brand_logo', brand_name='$brand_name', nickname_color='$nickname_color', is_top=$is_top, comment_enabled=$comment_enabled, like_enabled=$like_enabled, status=$status, published_at='$published_at_esc' WHERE id=$id");
    } else {
        mysqli_query($conn, "INSERT INTO ads (style, avatar, title, subtitle, content, image, card_title, card_desc, button_text, button_color, link, brand_logo, brand_name, nickname_color, is_top, comment_enabled, like_enabled, status, published_at) VALUES ($style, '$avatar', '$title', '$subtitle', '$content', '$image', '$card_title', '$card_desc', '$button_text', '$button_color', '$link', '$brand_logo', '$brand_name', '$nickname_color', $is_top, $comment_enabled, $like_enabled, $status, '$published_at_esc')");
        $id = mysqli_insert_id($conn);
    }

    $_SESSION['admin_toast'] = '保存成功';
    $redirect = strtok($_SERVER['REQUEST_URI'], '?');
    if ($id) {
        $redirect .= '?id=' . $id;
    }
    header('Location: ' . $redirect);
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

        .media-input {
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            line-height: 1.6;
            min-height: 100px;
            padding: 12px;
            height: auto;
        }

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
            <div style="display:flex; gap gap:10px;">
                <a href="ads.php" class="ap-btn ap-btn-secondary"><i class="fas fa-arrow-left"></i> 返回列表</a>
                <button type="button" onclick="document.getElementById('adForm').submit();" class="ap-btn ap-btn-primary" id="adSaveBtn"><i class="fas fa-save"></i> 保存</button>
            </div>
        </div>
        <div class="ap-card-body">
            <form id="adForm" method="POST" action="">

                <div class="section-title-inline">基础设置</div>

                <div class="form-grid" style="margin-bottom:20px;">
                    <div class="form-group">
                        <label class="form-label">广告样式</label>
                        <select name="style" class="form-input" id="adStyle" onchange="toggleStyle()">
                            <option value="1" <?php echo $ad['style']==1?'selected':''; ?>>卡片式</option>
                            <option value="2" <?php echo $ad['style']==2?'selected':''; ?>>大图式</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">状态</label>
                        <select name="status" class="form-input" id="adStatus">
                            <option value="1" <?php echo $ad['status']==1?'selected':''; ?>>显示</option>
                            <option value="0" <?php echo $ad['status']==0?'selected':''; ?>>隐藏</option>
                        </select>
                    </div>
                </div>

                <div class="form-group-block" id="exampleWrap">
                    <label class="form-label">广告示例</label>
                    <div id="cardPreview" style="border:1px solid #f0f0f0;border-radius:10px;overflow:hidden;display:flex;background:#fafafa;margin-top:4px;max-width:300px;"></div>
                    <div style="margin-top:10px;display:flex;gap:10px;">
                        <button type="button" class="ap-btn ap-btn-secondary" style="padding:6px 12px;font-size:12px;" onclick="fillExample()">填入示例</button>
                        <button type="button" class="ap-btn" style="padding:6px 12px;font-size:12px;background:#fee2e2;color:#dc2626;" onclick="clearAdForm()">删除内容</button>
                    </div>
                </div>

                <div class="form-group-block">
                    <label class="form-label">头像URL链接（留空显示"广告"文字）</label>
                    <input type="url" name="avatar" class="form-input" placeholder="头像URL链接" id="adAvatar" value="<?php echo htmlspecialchars($ad['avatar']); ?>">
                </div>

                <div class="form-group-block">
                    <label class="form-label">品牌名称</label>
                    <input type="text" name="title" class="form-input" placeholder="品牌名称" id="adTitle" value="<?php echo htmlspecialchars($ad['title']); ?>" required>
                </div>

                <div class="form-group-block">
                    <label class="form-label">名称颜色</label>
                    <div class="color-row">
                        <input type="text" class="form-input" id="adNickColorText" value="<?php echo htmlspecialchars($ad['nickname_color'] ?? '#5d6d9e'); ?>" placeholder="#5d6d9e" oninput="document.getElementById('adNickColor').value=this.value">
                        <input type="color" name="nickname_color" id="adNickColor" value="<?php echo htmlspecialchars($ad['nickname_color'] ?? '#5d6d9e'); ?>" oninput="document.getElementById('adNickColorText').value=this.value">
                    </div>
                    <div class="form-tips">本条广告动态昵称颜色</div>
                </div>

                <div class="form-group-block">
                    <label class="form-label">名称副标题</label>
                    <input type="text" name="subtitle" class="form-input" id="adSubtitle" value="<?php echo htmlspecialchars($ad['subtitle']); ?>">
                </div>

                <div class="form-group-block">
                    <label class="form-label">广告内容</label>
                    <textarea name="content" class="form-input media-input" rows="3" id="adContent" oninput="updatePreview()" placeholder="输入广告内容..."><?php echo htmlspecialchars($ad['content']); ?></textarea>
                </div>

                <div class="form-group-block">
                    <label class="form-label">图片URL链接</label>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="url" name="image" class="form-input" id="adImage" value="<?php echo htmlspecialchars($ad['image']); ?>" style="flex:1;" oninput="updatePreview()">
                        <input type="file" id="adImageFileInput" accept="image/*" style="display:none;" onchange="uploadAdImage(this)">
                        <button type="button" class="ap-btn" style="padding:8px 14px;font-size:13px;border-radius:10px;background:#f3f4f6;color:#4b5563;flex-shrink:0;white-space:nowrap;" onclick="document.getElementById('adImageFileInput').click()">
                            <i class="fas fa-upload"></i> 本地上传
                        </button>
                    </div>
                </div>

                <div class="section-divider"></div>

                <div class="section-title-inline">样式字段</div>

                <div id="cardFields" style="<?php echo $ad['style']==2?'display:none;':''; ?>">
                    <div class="form-group-block">
                        <label class="form-label">卡片标题</label>
                        <input type="text" name="card_title" class="form-input" id="adCardTitle" value="<?php echo htmlspecialchars($ad['card_title']); ?>">
                    </div>
                    <div class="form-group-block">
                        <label class="form-label">卡片描述</label>
                        <input type="text" name="card_desc" class="form-input" id="adCardDesc" value="<?php echo htmlspecialchars($ad['card_desc']); ?>">
                    </div>
                </div>

                <div id="imageFields" style="<?php echo $ad['style']==1?'display:none;':''; ?>">
                    <div class="form-group-block">
                        <label class="form-label">品牌URL头像2（留空显示"广告"文字）</label>
                        <input type="url" name="brand_logo" class="form-input" id="adBrandLogo" value="<?php echo htmlspecialchars($ad['brand_logo']); ?>">
                    </div>
                    <div class="form-group-block">
                        <label class="form-label">品牌名称</label>
                        <input type="text" name="brand_name" class="form-input" id="adBrandName" value="<?php echo htmlspecialchars($ad['brand_name']); ?>">
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">按钮文字</label>
                            <input type="text" name="button_text" class="form-input" id="adBtnText" value="<?php echo htmlspecialchars($ad['button_text']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">按钮颜色</label>
                            <div class="color-row">
                                <input type="text" class="form-input" id="adBtnColorText" value="<?php echo htmlspecialchars($ad['button_color']); ?>" oninput="document.getElementById('adBtnColor').value=this.value">
                                <input type="color" name="button_color" id="adBtnColor" value="<?php echo htmlspecialchars($ad['button_color']); ?>" oninput="document.getElementById('adBtnColorText').value=this.value">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section-divider"></div>

                <div class="section-title-inline">链接与发布</div>

                <div class="form-group-block">
                    <label class="form-label">跳转链接</label>
                    <input type="url" name="link" class="form-input" placeholder="https://imoe.top" id="adLink" value="<?php echo htmlspecialchars($ad['link']); ?>">
                </div>

                <div class="form-group-block">
                    <label class="form-label">发布时间</label>
                    <input type="datetime-local" name="published_at" class="form-input" value="<?php echo $ad['published_at'] ? date('Y-m-d\TH:i', strtotime($ad['published_at'])) : ''; ?>" id="adPublishedAt">
                    <div class="form-tips" style="margin-top:4px;">留空则使用当前时间</div>
                </div>

                <div class="select-grid">
                    <div class="form-group">
                        <label class="form-label">评论开关</label>
                        <select name="comment_enabled" class="form-input">
                            <option value="1" <?php echo ($ad['comment_enabled'] ?? 1) ? 'selected' : ''; ?>>开启</option>
                            <option value="0" <?php echo ($ad['comment_enabled'] ?? 1) ? '' : 'selected'; ?>>关闭</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">点赞开关</label>
                        <select name="like_enabled" class="form-input">
                            <option value="1" <?php echo ($ad['like_enabled'] ?? 1) ? 'selected' : ''; ?>>开启</option>
                            <option value="0" <?php echo ($ad['like_enabled'] ?? 1) ? '' : 'selected'; ?>>关闭</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">是否置顶</label>
                        <select name="is_top" class="form-input">
                            <option value="1" <?php echo $ad['is_top'] ? 'selected' : ''; ?>>是</option>
                            <option value="0" <?php echo $ad['is_top'] ? '' : 'selected'; ?>>否</option>
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
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
}

function toggleSubmenu(id, el) {
    const submenu = document.getElementById(id);
    const arrow = el.querySelector('.submenu-arrow');
    const isOpen = submenu.classList.contains('open');
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
    const existing = document.querySelector('.ap-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'ap-toast';
    const iconClass = type === 'success' ? 'fa-check' : 'fa-exclamation';
    const iconBg = type === 'success' ? 'success' : 'error';
    toast.innerHTML = '<div class="ap-toast-icon ' + iconBg + '"><i class="fas ' + iconClass + '"></i></div><span>' + msg + '</span>';
    document.body.appendChild(toast);

    requestAnimationFrame(() => {
        toast.classList.add('show');
    });

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 400);
    }, 3000);
}

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($toast_msg): ?>
    showToast('<?php echo htmlspecialchars($toast_msg, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo (strpos($toast_msg, '失败') !== false || strpos($toast_msg, '错误') !== false) ? 'error' : 'success'; ?>');
    <?php endif; ?>
});

document.getElementById('adNickColor').addEventListener('input', function() {
    document.getElementById('adNickColorText').value = this.value;
});

document.getElementById('adForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('adSaveBtn');
    btn.textContent = '保存中...';
    var formData = new FormData(this);
    formData.append('ajax_save_ad', '1');
    fetch(window.location.href, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(data => {
        if (data.code === 1) {
            showToast('保存成功');
            btn.textContent = '保存广告';
            setTimeout(() => {
                window.location.href = 'ads.php';
            }, 800);
        } else {
            showToast(data.msg || '保存失败');
            btn.textContent = '保存广告';
        }
    })
    .catch(() => { showToast('保存失败'); btn.textContent = '保存广告'; });
});

function toggleStyle(){
    var s=document.getElementById('adStyle').value;
    document.getElementById('cardFields').style.display=s=='1'?'block':'none';
    document.getElementById('imageFields').style.display=s=='2'?'block':'none';
    document.getElementById('exampleWrap').style.display='block';
    updatePreview();
}

function updatePreview(){
    var style=document.getElementById('adStyle').value;
    var title=document.getElementById('adTitle').value||'星露谷物语';
    var subtitle=document.getElementById('adSubtitle').value||'入选2025年度热门游玩榜';
    var content=document.getElementById('adContent').value||'一款开放式乡村模拟经营类游戏';
    var cardTitle=document.getElementById('adCardTitle').value||'开放式乡村 | 模拟经营';
    var cardDesc=document.getElementById('adCardDesc').value||'点击查看更多';
    var image=document.getElementById('adImage').value||'';
    var brandName=document.getElementById('adBrandName').value||'星露谷物语';
    var avatar=document.getElementById('adAvatar').value||'';
    var box=document.getElementById('cardPreview');
    if(style=='1'){
        var cardImg = image ? ensurePrefix(image) : '../assets/image/kapian.png';
        box.innerHTML='<div style="flex-shrink:0;width:80px;height:80px;background:#eee;display:flex;align-items:center;justify-content:center;"><img src="'+esc(cardImg)+'" style="width:100%;height:100%;object-fit:cover;"></div><div style="padding:8px 10px;flex:1;min-width:0;"><div style="font-size:13px;color:#333;font-weight:500;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'+esc(cardTitle)+'</div><div style="font-size:11px;color:#999;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'+esc(cardDesc)+'</div></div>';
    } else {
        var bigImg = image ? ensurePrefix(image) : '../assets/image/datu.png';
        box.innerHTML='<div style="width:100%;"><img src="'+esc(bigImg)+'" style="width:100%;height:120px;object-fit:cover;display:block;"><div style="display:flex;align-items:center;padding:8px 10px;gap:8px;"><div style="display:flex;align-items:center;gap:6px;flex:1;">'+(avatar?'<img src="'+esc(avatar)+'" style="width:20px;height:20px;border-radius:50%;object-fit:cover;">':'<div style="width:20px;height:20px;border-radius:50%;background:#5d6d9e;display:flex;align-items:center;justify-content:center;color:#fff;font-size:8px;">广告</div>')+'<span style="font-size:12px;color:#333;font-weight:500;">'+esc(brandName||title)+'</span></div></div></div>';
    }
}

function clearAdForm(){
    document.getElementById('adTitle').value='';
    document.getElementById('adSubtitle').value='';
    document.getElementById('adContent').value='';
    document.getElementById('adCardTitle').value='';
    document.getElementById('adCardDesc').value='';
    document.getElementById('adImage').value='';
    document.getElementById('adBrandName').value='';
    updatePreview();
    showToast('已清空');
}

function fillExample(){
    var style=document.getElementById('adStyle').value;
    document.getElementById('adTitle').value='星露谷物语';
    document.getElementById('adSubtitle').value='入选2025年度热门游玩榜';
    document.getElementById('adContent').value='一款开放式乡村模拟经营类游戏';
    if(style=='1'){
        document.getElementById('adCardTitle').value='开放式乡村 | 模拟经营';
        document.getElementById('adCardDesc').value='点击查看更多';
        document.getElementById('adImage').value='assets/image/kapian.png';
    }else{
        document.getElementById('adImage').value='assets/image/datu.png';
    }
    document.getElementById('adBrandName').value='星露谷物语';
    updatePreview();
    showToast('已填入示例');
}

function uploadAdImage(input) {
    if (!input.files || !input.files[0]) return;
    var formData = new FormData();
    formData.append('image', input.files[0]);
    fetch('../api/upload.php', { method: 'POST', body: formData })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (res.code == 1 && res.data && res.data.url) {
                document.getElementById('adImage').value = res.data.url;
                updatePreview();
                showToast('图片上传成功', 'success');
            } else {
                showToast(res.msg || '上传失败', 'error');
            }
        })
        .catch(function(){ showToast('上传失败', 'error'); });
}

function esc(t){const d=document.createElement('div');d.textContent=t;return d.innerHTML;}
function ensurePrefix(p){ if(!p) return p; if(/^(https?:)?\/\//.test(p) || p.charAt(0)==='/' || p.indexOf('../')===0) return p; return '../'+p; }

toggleStyle();
</script>
</body>
</html>
