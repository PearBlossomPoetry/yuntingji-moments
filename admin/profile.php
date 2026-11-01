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
$page_title = '个人设置';
$page_id = 'profile';

$settings = get_all_settings();

if (isset($_FILES['upload_image']) && $_FILES['upload_image']['error'] === UPLOAD_ERR_OK) {
    if (!csrf_validate()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code' => 0, 'msg' => '安全令牌无效，请刷新页面重试']);
        exit;
    }
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $ext = strtolower(pathinfo($_FILES['upload_image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico', 'svg'];
    if (!in_array($ext, $allowed)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code' => 0, 'msg' => '仅支持 JPG/PNG/GIF/WEBP/ICO/SVG 图片文件']);
        exit;
    }
    $filename = 'img_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $filepath = $uploadDir . $filename;
    if (move_uploaded_file($_FILES['upload_image']['tmp_name'], $filepath)) {
        $url = 'uploads/' . $filename;
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code' => 1, 'msg' => '上传成功', 'data' => ['url' => $url]]);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code' => 0, 'msg' => '上传失败']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $_SESSION['admin_toast'] = '安全令牌无效，请刷新页面重试';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    $fields = ['favicon', 'avatar', 'avatar_shape', 'avatar_width', 'avatar_height', 'nickname', 'signature', 'cover_image', 'cover_height', 'post_avatar_width', 'post_avatar_height', 'sign_font_size', 'nickname_font_size'];
    foreach ($fields as $key) {
        $value = mysqli_real_escape_string($conn, $_POST[$key] ?? '');
        mysqli_query($conn, "UPDATE settings SET setting_value = '$value' WHERE setting_key = '$key'");
        if (mysqli_affected_rows($conn) == 0) {
            mysqli_query($conn, "INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$value') ON DUPLICATE KEY UPDATE setting_value = '$value'");
        }
    }
    $_SESSION['admin_toast'] = '保存成功';
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$toast_msg = $_SESSION['admin_toast'] ?? '';
if ($toast_msg) {
    unset($_SESSION['admin_toast']);
}

$sign_font_size = intval($settings['sign_font_size'] ?? 11);
$nickname_font_size = intval($settings['nickname_font_size'] ?? 15);

function preview_url($url) {
    if (empty($url)) return '';
    if (preg_match('#^(https?:)?//#i', $url) || strpos($url, '/') === 0) return $url;
    return '../' . $url;
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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; gap: 16px; }
        }

        .form-group { margin-bottom: 0; }
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

        .range-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .range-row input[type="range"] {
            flex: 1;
            max-width: 200px;
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
            min-width: 50px;
            text-align: right;
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

        .preview-img {
            margin-top: 10px;
            border-radius: 8px;
            border: 2px solid #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            object-fit: cover;
        }

        .compact-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-top: 16px;
        }
        .compact-grid .form-label {
            font-size: 13px;
            margin-bottom: 6px;
        }
        @media (max-width: 768px) {
            .compact-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            .compact-grid .form-label {
                font-size: 12px;
                margin-bottom: 4px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .compact-grid .form-input {
                height: 38px;
                font-size: 14px;
                padding: 0 10px;
            }
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
            <img src="<?php echo htmlspecialchars(preview_url($settings['avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($settings['site_name'] ?? '云汀记') . '&background=5b73e8&color=fff&size=128')); ?>" class="w-11 h-11 rounded-full" alt="User">
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
            <div class="ap-card-title">个人资料</div>
            <button type="submit" form="profileForm" class="ap-btn ap-btn-primary"><i class="fas fa-save"></i> 保存</button>
        </div>
        <div class="ap-card-body">
            <form id="profileForm" method="POST" action="">
                <?php echo csrf_token_field(); ?>
                <div class="section-title-inline">基础信息</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">个人昵称</label>
                        <input type="text" name="nickname" class="form-input" value="<?php echo htmlspecialchars($settings['nickname'] ?? ''); ?>" placeholder="请输入昵称">
                    </div>
                    <div class="form-group">
                        <label class="form-label">个性签名</label>
                        <input type="text" name="signature" class="form-input" value="<?php echo htmlspecialchars($settings['signature'] ?? ''); ?>" placeholder="请输入个性签名">
                    </div>
                </div>

                <div class="section-divider"></div>

                <div class="section-title-inline">头像设置</div>
                <div class="form-group">
                    <label class="form-label">头像链接</label>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="text" name="avatar" id="avatarInput" class="form-input" value="<?php echo htmlspecialchars($settings['avatar'] ?? ''); ?>" placeholder="https://..." style="flex:1;">
                        <input type="file" id="avatarFileInput" accept="image/*" style="display:none;" onchange="uploadImageFile(this, 'avatarInput')">
                        <button type="button" class="ap-btn" style="padding:8px 14px;font-size:13px;border-radius:10px;background:#f3f4f6;color:#4b5563;flex-shrink:0;white-space:nowrap;" onclick="document.getElementById('avatarFileInput').click()">
                            <i class="fas fa-upload"></i> 本地上传
                        </button>
                    </div>
                    <?php if (!empty($settings['avatar'])): ?>
                    <img src="<?php echo htmlspecialchars(preview_url($settings['avatar'])); ?>" class="preview-img" id="avatarPreview" style="width:52px;height:52px;border-radius:50%;" alt="avatar">
                    <?php endif; ?>
                </div>

                <div class="form-group" style="margin-top:20px;">
                    <label class="form-label">头像形状</label>
                    <div style="display:flex;gap:18px;align-items:center;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;color:#374151;">
                            <input type="radio" name="avatar_shape" value="circle" <?php echo ($settings['avatar_shape'] ?? 'square') === 'circle' ? 'checked' : ''; ?>> 圆形
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;color:#374151;">
                            <input type="radio" name="avatar_shape" value="square" <?php echo ($settings['avatar_shape'] ?? 'square') === 'square' ? 'checked' : ''; ?>> 方形（当前）
                        </label>
                    </div>
                    <div class="form-tips">前台头像显示形状：圆形或方形</div>
                </div>

                <div class="compact-grid">
                    <div class="form-group">
                        <label class="form-label">头像宽度</label>
                        <input type="number" name="avatar_width" class="form-input" value="<?php echo htmlspecialchars($settings['avatar_width'] ?? '52'); ?>" placeholder="52">
                    </div>
                    <div class="form-group">
                        <label class="form-label">头像高度</label>
                        <input type="number" name="avatar_height" class="form-input" value="<?php echo htmlspecialchars($settings['avatar_height'] ?? '52'); ?>" placeholder="52">
                    </div>
                    <div class="form-group">
                        <label class="form-label">动态头像宽度</label>
                        <input type="number" name="post_avatar_width" class="form-input" value="<?php echo htmlspecialchars($settings['post_avatar_width'] ?? '40'); ?>" placeholder="40">
                    </div>
                    <div class="form-group">
                        <label class="form-label">动态头像高度</label>
                        <input type="number" name="post_avatar_height" class="form-input" value="<?php echo htmlspecialchars($settings['post_avatar_height'] ?? '40'); ?>" placeholder="40">
                    </div>
                </div>

                <div class="section-divider"></div>

                <div class="section-title-inline">封面设置</div>
                <div class="form-group">
                    <label class="form-label">封面链接</label>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="text" name="cover_image" id="coverInput" class="form-input" value="<?php echo htmlspecialchars($settings['cover_image'] ?? ''); ?>" placeholder="https://..." style="flex:1;">
                        <input type="file" id="coverFileInput" accept="image/*" style="display:none;" onchange="uploadImageFile(this, 'coverInput')">
                        <button type="button" class="ap-btn" style="padding:8px 14px;font-size:13px;border-radius:10px;background:#f3f4f6;color:#4b5563;flex-shrink:0;white-space:nowrap;" onclick="document.getElementById('coverFileInput').click()">
                            <i class="fas fa-upload"></i> 本地上传
                        </button>
                    </div>
                    <?php if (!empty($settings['cover_image'])): ?>
                    <img src="<?php echo htmlspecialchars(preview_url($settings['cover_image'])); ?>" class="preview-img" id="coverPreview" style="width:100%;max-width:400px;height:120px;" alt="cover">
                    <?php endif; ?>
                </div>
                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label">封面背景图高度</label>
                    <input type="number" name="cover_height" class="form-input" value="<?php echo htmlspecialchars($settings['cover_height'] ?? '220'); ?>" placeholder="220" style="width:140px;">
                    <div class="form-tips">封面背景图的高度，默认 220px</div>
                </div>

                <div class="section-divider"></div>

                <div class="section-title-inline">网站图标与字体</div>
                <div class="form-group">
                    <label class="form-label">网站图标 (favicon)</label>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="text" name="favicon" id="faviconInput" class="form-input" value="<?php echo htmlspecialchars($settings['favicon'] ?? ''); ?>" placeholder="留空则使用头像作为默认图标" style="flex:1;">
                        <input type="file" id="faviconFileInput" accept="image/*,.ico,.svg" style="display:none;" onchange="uploadImageFile(this, 'faviconInput')">
                        <button type="button" class="ap-btn" style="padding:8px 14px;font-size:13px;border-radius:10px;background:#f3f4f6;color:#4b5563;flex-shrink:0;white-space:nowrap;" onclick="document.getElementById('faviconFileInput').click()">
                            <i class="fas fa-upload"></i> 本地上传
                        </button>
                    </div>
                    <div class="form-tips">浏览器标签栏图标链接，支持 .ico / .png / .jpg / .svg</div>
                    <?php if (!empty($settings['favicon'])): ?>
                    <img src="<?php echo htmlspecialchars(preview_url($settings['favicon'])); ?>" class="preview-img" id="faviconPreview" style="width:32px;height:32px;" alt="favicon">
                    <?php endif; ?>
                </div>
                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label">昵称字体大小</label>
                    <div class="range-row">
                        <input type="range" name="nickname_font_size" min="12" max="24" value="<?php echo $nickname_font_size; ?>" oninput="document.getElementById('nfsVal').textContent=this.value+'px'">
                        <span class="range-val" id="nfsVal"><?php echo $nickname_font_size; ?>px</span>
                    </div>
                </div>
                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label">个性签名字体大小</label>
                    <div class="range-row">
                        <input type="range" name="sign_font_size" min="10" max="18" value="<?php echo $sign_font_size; ?>" oninput="document.getElementById('sfsVal').textContent=this.value+'px'">
                        <span class="range-val" id="sfsVal"><?php echo $sign_font_size; ?>px</span>
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

function previewUrl(url) {
    if (!url) return '';
    if (/^(https?:)?\/\//i.test(url) || url.startsWith('/')) return url;
    return '../' + url;
}

function updateOrCreatePreview(inputId, previewId, url, styleCss) {
    let preview = document.getElementById(previewId);
    const displayUrl = previewUrl(url);
    if (preview) {
        preview.src = displayUrl;
        return;
    }
    const input = document.getElementById(inputId);
    if (!input) return;
    const container = input.closest('.form-group');
    if (!container) return;
    preview = document.createElement('img');
    preview.id = previewId;
    preview.src = displayUrl;
    preview.className = 'preview-img';
    preview.style.cssText = styleCss;
    preview.alt = previewId.replace('Preview', '').toLowerCase();
    container.appendChild(preview);
}

function uploadImageFile(input, targetInputId) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const formData = new FormData();
    formData.append('upload_image', file);
    formData.append('csrf_token', '<?php echo csrf_token_value(); ?>');

    fetch('', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.code == 1) {
                document.getElementById(targetInputId).value = res.data.url;
                if (targetInputId === 'avatarInput') {
                    updateOrCreatePreview('avatarInput', 'avatarPreview', res.data.url, 'width:52px;height:52px;border-radius:50%;');
                } else if (targetInputId === 'coverInput') {
                    updateOrCreatePreview('coverInput', 'coverPreview', res.data.url, 'width:100%;max-width:400px;height:120px;');
                } else if (targetInputId === 'faviconInput') {
                    updateOrCreatePreview('faviconInput', 'faviconPreview', res.data.url, 'width:32px;height:32px;');
                }
                showToast('图片上传成功', 'success');
            } else {
                showToast(res.msg || '上传失败', 'error');
            }
        })
        .catch(() => showToast('上传失败', 'error'));
}

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($toast_msg): ?>
    showToast('<?php echo htmlspecialchars($toast_msg, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo (strpos($toast_msg, '失败') !== false || strpos($toast_msg, '错误') !== false) ? 'error' : 'success'; ?>');
    <?php endif; ?>
});
</script>
</body>
</html>
