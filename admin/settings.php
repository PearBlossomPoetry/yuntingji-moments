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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$page_title = '前台设置';
$page_id = 'settings';

$settings = get_all_settings();
$default_color = $settings['nickname_color'] ?? '#5d6d9e';

if (isset($_FILES['font_file']) && $_FILES['font_file']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $ext = strtolower(pathinfo($_FILES['font_file']['name'], PATHINFO_EXTENSION));
    $allowed = ['ttf', 'otf', 'woff', 'woff2'];
    if (!in_array($ext, $allowed)) {
        json_response(0, '仅支持 TTF/OTF/WOFF 字体文件');
    }
    $filename = 'font_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $filepath = $uploadDir . $filename;
    if (move_uploaded_file($_FILES['font_file']['tmp_name'], $filepath)) {
        $url = '/uploads/' . $filename;
        json_response(1, '上传成功', ['url' => $url]);
    } else {
        json_response(0, '上传失败');
    }
}

if (isset($_FILES['upload_image']) && $_FILES['upload_image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $ext = strtolower(pathinfo($_FILES['upload_image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed)) {
        json_response(0, '仅支持 JPG/PNG/GIF/WEBP 图片文件');
    }
    $filename = 'img_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $filepath = $uploadDir . $filename;
    if (move_uploaded_file($_FILES['upload_image']['tmp_name'], $filepath)) {
        $url = 'uploads/' . $filename;
        json_response(1, '上传成功', ['url' => $url]);
    } else {
        json_response(0, '上传失败');
    }
}

if (isset($_POST['ajax_save']) || isset($_POST['form_save'])) {
    $csrf_ok = false;
    if (!empty($_POST['csrf_token']) && !empty($_SESSION['csrf_token'])) {
        $csrf_ok = hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }
    if (!$csrf_ok) {
        if (isset($_POST['ajax_save'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['code' => 0, 'msg' => '安全令牌无效，请刷新页面重试']);
            exit;
        } else {
            $_SESSION['admin_toast'] = '安全令牌无效，请刷新页面重试';
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
    $fields = ['page_size', 'load_mode', 'site_name', 'site_keywords', 'site_description', 'menu_icon_html', 'font_url', 'nickname_color', 'ad_nickname_color', 'divider_color', 'divider_width', 'global_font_size', 'load_more_text', 'end_tip_text', 'line_text', 'line_color', 'comment_need_approve', 'music_parse_mode', 'music_api_url'];
    foreach ($fields as $key) {
        $value = mysqli_real_escape_string($conn, $_POST[$key] ?? '');
        mysqli_query($conn, "UPDATE settings SET setting_value = '$value' WHERE setting_key = '$key'");
        if (mysqli_affected_rows($conn) == 0) {
            mysqli_query($conn, "INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$value') ON DUPLICATE KEY UPDATE setting_value = '$value'");
        }
    }
    if (isset($_POST['form_save'])) {
        $_SESSION['admin_toast'] = '保存成功';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    json_response(1, '保存成功');
}

$toast_msg = $_SESSION['admin_toast'] ?? '';
if ($toast_msg) {
    unset($_SESSION['admin_toast']);
}

$divider_width_raw = $settings['divider_width'] ?? '0.5px';
$divider_width_val = floatval($divider_width_raw);
$divider_width_display = (strpos($divider_width_raw, 'px') !== false) ? $divider_width_raw : $divider_width_raw . 'px';
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
        .ap-btn-sm { padding: 8px 16px; font-size: 13px; border-radius: 10px; }

        .form-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
        }
        @media (max-width: 768px) {
                .form-grid { grid-template-columns: 1fr; gap: 16px; }
        }

        .form-group {
                margin-bottom: 0;
        }
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
        select.form-input { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 32px; }
        textarea.form-input { height: auto; padding: 10px 12px; resize: vertical; line-height: 1.5; min-height: 40px; }
        .form-tips {
                font-size: 12px;
                color: #9ca3af;
                margin-top: 6px;
                line-height: 1.4;
        }
        .color-row {
                display: flex;
                align-items: center;
                gap: 8px;
        }
        .color-row .form-input { width: 90px; flex-shrink: 0; }
        .color-row input[type="color"] {
                width: 40px;
                height: 40px;
                padding: 2px;
                border: 2px solid #f0f0f0;
                border-radius: 10px;
                background: #fafafa;
                cursor: pointer;
                flex-shrink: 0;
        }
        .range-row {
                display: flex;
                align-items: center;
                gap: 12px;
        }
        .range-row input[type="range"] {
                flex: 1;
                height: 6px;
                -webkit-appearance: none;
                background: #e5e7eb;
                border-radius: 3px;
                outline: none;
        }
        .range-row input[type="range"]::-webkit-slider-thumb {
                -webkit-appearance: none;
                width: 18px;
                height: 18px;
                background: #5b73e8;
                border-radius: 50%;
                cursor: pointer;
                box-shadow: 0 2px 6px rgba(91,115,232,0.3);
        }
        .range-val {
                font-size: 13px;
                font-weight: 600;
                color: #5b73e8;
                min-width: 40px;
                text-align: right;
        }

        .font-upload-row {
                display: flex;
                gap: 8px;
                align-items: center;
        }
        .font-upload-row .form-input { flex: 1; }

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

        @media (min-width: 769px) {
                .compact-grid {
                margin-bottom: 32px;
                }
        }

        @media (max-width: 768px) {
                .ap-card-header { padding: 16px 20px; }
                .ap-card-body { padding: 20px; }
                .ap-btn { padding: 8px 14px; font-size: 13px; border-radius: 12px; }
                .section-divider { margin: 20px 0; }

                .compact-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 6px;
                margin-bottom: 20px;
                }
                .compact-grid .form-label {
                font-size: 11px;
                margin-bottom: 3px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                }
                .compact-grid .color-row {
                gap: 3px;
                }
                .compact-grid .color-row .form-input {
                height: 32px;
                font-size: 11px;
                padding: 0 5px;
                min-width: 0;
                }
                .compact-grid .color-row input[type="color"] {
                width: 24px;
                height: 24px;
                padding: 1px;
                flex-shrink: 0;
                }
                .compact-grid .form-tips {
                display: none;
                }
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
                            $id = $item[0];
                            $name = $item[1];
                            $is_active = ($id === $page_id);
                                ?>
                                <li>
                            <a href="<?php echo $id; ?>.php" class="nav-item <?php echo $is_active ? 'active' : ''; ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium <?php echo $is_active ? '' : 'text-gray-600'; ?>" data-page="<?php echo $id; ?>">
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
                $id = $item[0];
                $name = $item[1];
                $icon = $item[2];
                $is_active = ($id === $page_id);
                ?>
                <li>
                <a href="<?php echo $id; ?>.php" class="nav-item <?php echo $is_active ? 'active' : ''; ?> flex items-center gap-3 px-4 py-3 text-sm font-medium <?php echo $is_active ? '' : 'text-gray-600'; ?>" data-page="<?php echo $id; ?>">
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
                <div class="ap-card-title">前台显示设置</div>
                <button type="submit" form="settingsForm" class="ap-btn ap-btn-primary"><i class="fas fa-save"></i> 保存</button>
        </div>
        <div class="ap-card-body">
                <form id="settingsForm" method="post">
                <?php echo csrf_token_field(); ?>
                <div class="section-title-inline">基础设置</div>
                <div class="form-grid">
                    <div class="form-group">
                                <label class="form-label">网站标题</label>
                                <input type="text" name="site_name" class="form-input" value="<?php echo htmlspecialchars($settings['site_name'] ?? '云汀记'); ?>" placeholder="云汀记">
                                <div class="form-tips">浏览器标签栏 &lt;title&gt; 显示</div>
                    </div>
                    <div class="form-group">
                                <label class="form-label">网站关键词（SEO）</label>
                                <input type="text" name="site_keywords" class="form-input" value="<?php echo htmlspecialchars($settings['site_keywords'] ?? '博客,朋友圈,云汀记'); ?>" placeholder="博客,朋友圈,云汀记">
                                <div class="form-tips">多个关键词用英文逗号分隔，用于搜索引擎 SEO &lt;meta keywords&gt;</div>
                    </div>
                    <div class="form-group">
                                <label class="form-label">网站描述（SEO）</label>
                                <textarea name="site_description" class="form-input" rows="2" placeholder="一句话描述你的网站"><?php echo htmlspecialchars($settings['site_description'] ?? '一款精致的、轻量的、干净清爽的全新朋友圈程序'); ?></textarea>
                                <div class="form-tips">用于搜索引擎 SEO &lt;meta description&gt;，建议80-200字</div>
                    </div>
                    <div class="form-group">
                                <label class="form-label">每页动态显示数量</label>
                                <input type="number" name="page_size" class="form-input" value="<?php echo intval($settings['page_size'] ?? 12); ?>" min="1" max="50">
                                <div class="form-tips">默认12条，超过显示加载更多</div>
                    </div>
                    <div class="form-group">
                                <label class="form-label">加载模式</label>
                                <select name="load_mode" class="form-input">
                            <option value="manual" <?php echo ($settings['load_mode'] ?? 'manual') === 'manual' ? 'selected' : ''; ?>>手动加载</option>
                            <option value="auto" <?php echo ($settings['load_mode'] ?? '') === 'auto' ? 'selected' : ''; ?>>自动加载</option>
                                </select>
                                <div class="form-tips">手动显示按钮，自动滚动到底加载</div>
                    </div>
                    <div class="form-group">
                                <label class="form-label">评论审核开关</label>
                                <select name="comment_need_approve" class="form-input">
                            <option value="0" <?php echo ($settings['comment_need_approve'] ?? '0') === '0' ? 'selected' : ''; ?>>关闭（直接显示）</option>
                            <option value="1" <?php echo ($settings['comment_need_approve'] ?? '') === '1' ? 'selected' : ''; ?>>开启（需审核）</option>
                                </select>
                                <div class="form-tips">开启后新评论需管理员审核</div>
                    </div>
                </div>

                <div class="section-divider"></div>

                <div class="section-title-inline">文字与字体</div>
                <div class="form-group">
                    <label class="form-label">全局自定义字体链接</label>
                    <div class="font-upload-row">
                                <input type="text" name="font_url" id="fontUrlInput" class="form-input" value="<?php echo htmlspecialchars($settings['font_url'] ?? ''); ?>" placeholder="https://imoe.top/font.ttf">
                                <input type="file" id="fontFileInput" accept=".ttf,.otf,.woff,.woff2" style="display:none;" onchange="uploadFontFile(this)">
                                <button type="button" class="ap-btn ap-btn-sm" onclick="document.getElementById('fontFileInput').click()" style="background:#f3f4f6;color:#4b5563;">
                            <i class="fas fa-upload"></i> 本地上传
                                </button>
                    </div>
                    <div class="form-tips">填写字体URL或本地上传，留空使用系统默认字体</div>
                </div>

                <div class="form-grid" style="margin-top:16px;">
                    <div class="form-group">
                                <label class="form-label">加载更多按钮文字</label>
                                <input type="text" name="load_more_text" class="form-input" value="<?php echo htmlspecialchars($settings['load_more_text'] ?? '加载更多'); ?>" placeholder="加载更多">
                    </div>
                    <div class="form-group">
                                <label class="form-label">已到底提示文字</label>
                                <input type="text" name="end_tip_text" class="form-input" value="<?php echo htmlspecialchars($settings['end_tip_text'] ?? '没有更多啦'); ?>" placeholder="没有更多啦">
                    </div>
                </div>

                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label">右上角菜单自定义图标HTML</label>
                    <input type="text" name="menu_icon_html" class="form-input" value="<?php echo htmlspecialchars($settings['menu_icon_html'] ?? ''); ?>" placeholder='<i class="fa-solid fa-bars"></i>'>
                    <div class="form-tips">支持任意HTML标签，留空使用默认汉堡菜单图标</div>
                </div>

                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label">全局字体大小（除个性签名和顶部外）</label>
                    <div class="range-row">
                                <input type="range" name="global_font_size" min="12" max="20" value="<?php echo htmlspecialchars($settings['global_font_size'] ?? '13'); ?>" oninput="document.getElementById('gfsVal').textContent=this.value+'px'">
                                <span class="range-val" id="gfsVal"><?php echo htmlspecialchars($settings['global_font_size'] ?? '13'); ?>px</span>
                    </div>
                    <div class="form-tips">前台文字基础大小，个性签名和顶部标题在个人设置中单独调节</div>
                </div>

                <div class="section-divider"></div>

                <div class="section-title-inline">颜色与样式</div>
                <div class="form-grid compact-grid">
                    <div class="form-group">
                                <label class="form-label">昵称默认颜色</label>
                                <div class="color-row">
                            <input type="text" class="form-input" value="<?php echo htmlspecialchars($settings['nickname_color'] ?? '#5d6d9e'); ?>" oninput="this.nextElementSibling.value=this.value">
                            <input type="color" name="nickname_color" value="<?php echo htmlspecialchars($settings['nickname_color'] ?? '#5d6d9e'); ?>">
                                </div>
                                <div class="form-tips">前台昵称、标签、点赞等默认颜色</div>
                    </div>
                    <div class="form-group">
                                <label class="form-label">广告昵称默认颜色</label>
                                <div class="color-row">
                            <input type="text" class="form-input" value="<?php echo htmlspecialchars($settings['ad_nickname_color'] ?? '#5d6d9e'); ?>" oninput="this.nextElementSibling.value=this.value">
                            <input type="color" name="ad_nickname_color" value="<?php echo htmlspecialchars($settings['ad_nickname_color'] ?? '#5d6d9e'); ?>">
                                </div>
                                <div class="form-tips">广告区域品牌名称默认颜色</div>
                    </div>
                    <div class="form-group">
                                <label class="form-label">分割横线颜色</label>
                                <div class="color-row">
                            <input type="text" class="form-input" value="<?php echo htmlspecialchars($settings['divider_color'] ?? '#f0f0f0'); ?>" oninput="this.nextElementSibling.value=this.value">
                            <input type="color" name="divider_color" value="<?php echo htmlspecialchars($settings['divider_color'] ?? '#f0f0f0'); ?>">
                                </div>
                                <div class="form-tips">动态之间的分割横线颜色</div>
                    </div>
                    <div class="form-group">
                                <label class="form-label">横线颜色</label>
                                <div class="color-row">
                            <input type="text" class="form-input" value="<?php echo htmlspecialchars($settings['line_color'] ?? '#cccccc'); ?>" oninput="this.nextElementSibling.value=this.value">
                            <input type="color" name="line_color" value="<?php echo htmlspecialchars($settings['line_color'] ?? '#cccccc'); ?>">
                                </div>
                                <div class="form-tips">加载更多/没有更多啦两侧横线颜色</div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                                <label class="form-label">分割横线粗细</label>
                                <div class="range-row">
                            <input type="range" name="divider_width" min="0.5" max="3" step="0.5" value="<?php echo $divider_width_val; ?>" oninput="document.getElementById('dwVal').textContent=this.value+'px'">
                            <span class="range-val" id="dwVal"><?php echo htmlspecialchars($divider_width_display); ?></span>
                                </div>
                    </div>
                    <div class="form-group">
                                <label class="form-label">横线样式字符</label>
                                <input type="text" name="line_text" class="form-input" value="<?php echo htmlspecialchars($settings['line_text'] ?? '---'); ?>" placeholder="---">
                                <div class="form-tips">如 --- 或 ─── 或 ===</div>
                    </div>
                </div>

                <div class="section-divider"></div>

                <div class="section-title-inline">音乐解析设置</div>
                <div class="form-grid">
                    <div class="form-group">
                                <label class="form-label">解析模式</label>
                                <select name="music_parse_mode" class="form-input" id="musicParseMode">
                            <option value="official" <?php echo ($settings['music_parse_mode'] ?? 'official') === 'official' ? 'selected' : ''; ?>>官方接口（默认）</option>
                            <option value="custom" <?php echo ($settings['music_parse_mode'] ?? '') === 'custom' ? 'selected' : ''; ?>>自定义接口</option>
                                </select>
                                <div class="form-tips">官方接口默认调用各平台官方API进行全网解析</div>
                    </div>
                </div>
                <div class="form-group" id="musicApiUrlGroup" style="margin-top:16px; <?php echo ($settings['music_parse_mode'] ?? 'official') === 'custom' ? '' : 'display:none;'; ?>">
                    <label class="form-label">自定义解析接口地址</label>
                    <input type="text" name="music_api_url" class="form-input" value="<?php echo htmlspecialchars($settings['music_api_url'] ?? ''); ?>" placeholder="https://api.imoe.top/api/music">
                    <div class="form-tips">填写第三方音乐解析API地址，格式如 https://api.imoe.top/api/music</div>
                </div>

                <input type="hidden" name="form_save" value="1">
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
const CSRF_TOKEN = '<?php echo csrf_token_value(); ?>';
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

function uploadFontFile(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const formData = new FormData();
    formData.append('font_file', file);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if (res.code == 1) {
            document.getElementById('fontUrlInput').value = res.data.url;
            showToast('字体上传成功', 'success');
        } else {
            showToast(res.msg || '上传失败', 'error');
        }
    })
    .catch(() => showToast('上传失败', 'error'));
}

document.addEventListener('DOMContentLoaded', function() {
    var mpMode = document.getElementById('musicParseMode');
    if (mpMode) {
        mpMode.addEventListener('change', function() {
            var group = document.getElementById('musicApiUrlGroup');
            if (this.value === 'custom') {
                group.style.display = '';
            } else {
                group.style.display = 'none';
            }
        });
    }
    <?php if ($toast_msg): ?>
    showToast('<?php echo htmlspecialchars($toast_msg, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo (strpos($toast_msg, '失败') !== false || strpos($toast_msg, '无效') !== false || strpos($toast_msg, '未选择') !== false) ? 'error' : 'success'; ?>');
    <?php endif; ?>
});
</script>
</body>
</html>
