<?php
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
require_once '../config.php';
require_once __DIR__ . '/backup_lib.php';

register_shutdown_function(function () {
    if (function_exists('ytj_maybe_auto_backup')) {
        ytj_maybe_auto_backup();
    }
});
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$settings = get_all_settings();

$current_version = '1.5.0';
$update_api_url = 'https://api.imoe.top/moments/update.json';
$download_package_url = 'https://imoe.top/moments/releases/latest/download/update.zip';

$announcements = [
    [
        'id'    => 1,
        'date'  => '2025-06-23',
        'title' => '授权管理系统即将上线',
        'body'  => '全新的授权管理平台正在开发中，支持在线授权验证、域名绑定、到期提醒、自动更新推送等功能，敬请期待。',
        'type'  => 'warning',
        'link'  => 'https://imoe.top/announce/auth-system'
    ],
    [
        'id'    => 2,
        'date'  => '2025-06-20',
        'title' => 'v1.5.0 正式发布',
        'body'  => '朋友圈系统首个稳定版本上线 支持动态发布 评论互动、广告管理、标签系统、文件上传等功能。',
        'type'  => 'success',
        'link'  => ''
    ],
    [
        'id'    => 3,
        'date'  => '2025-06-18',
        'title' => '后台 UI 全面升级',
        'body'  => '全新设计体系 重构所有管理页面 提升操作体验和移动端适配',
        'type'  => 'info',
        'link'  => ''
    ],
];

$cache_file = sys_get_temp_dir() . '/moments_update_cache.json';
$cache_time = 300;
$remote_info = null;
$update_available = false;
$latest_version = $current_version;

if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
    $remote_info = json_decode(file_get_contents($cache_file), true);
} else {
    $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
    $resp = @file_get_contents($update_api_url, false, $ctx);
    if ($resp) {
        $remote_info = json_decode($resp, true);
        if ($remote_info) file_put_contents($cache_file, $resp);
    }
}

if ($remote_info && !empty($remote_info['version'])) {
    $latest_version = $remote_info['version'];
    $update_available = version_compare($latest_version, $current_version, '>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_online_update'])) {
    if (!csrf_validate()) {
        $_SESSION['admin_toast'] = '安全令牌无效，请刷新页面重试';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $zip_url = $remote_info['package_url'] ?? $download_package_url;
    $temp_zip = sys_get_temp_dir() . '/moments_update_' . time() . '.zip';
    $extract_to = sys_get_temp_dir() . '/moments_update_' . time();

    $zip_content = @file_get_contents($zip_url, false, stream_context_create(['http' => ['timeout' => 30]]));
    if ($zip_content === false) {
        $update_result = '下载更新包失败，请检查网络或手动下载覆盖包';
    } else {
        file_put_contents($temp_zip, $zip_content);

        $expected_sha = $remote_info['package_sha256'] ?? '';
        if ($expected_sha && hash_file('sha256', $temp_zip) !== strtolower($expected_sha)) {
            @unlink($temp_zip);
            $update_result = '更新包校验失败，可能被篡改';
        } else {
            $zip = new ZipArchive();
            if ($zip->open($temp_zip) === true) {
                $zip->extractTo($extract_to);
                $zip->close();
                $source = $extract_to;
                if (is_dir($source)) {
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::SELF_FIRST
                    );
                    foreach ($iterator as $item) {
                        $dest_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
                        if ($item->isDir()) {
                            if (!is_dir($dest_path)) mkdir($dest_path, 0755, true);
                        } else {
                            copy($item->getPathname(), $dest_path);
                        }
                    }
                    $update_result = 'success';
                } else {
                    $update_result = '解压目录异常';
                }
                @unlink($temp_zip);
                @array_map('unlink', glob("$extract_to/*"));
                @rmdir($extract_to);
            } else {
                $update_result = '解压失败，压缩包可能损坏';
            }
        }
    }
    $_SESSION['admin_toast'] = $update_result === 'success' ? '更新成功，请刷新页面' : '更新失败：' . $update_result;
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$page_title = '后台首页';
$page_id = 'index';
$toast_msg = $_SESSION['admin_toast'] ?? '';
if ($toast_msg) unset($_SESSION['admin_toast']);
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
        .ap-btn-success {
            background: linear-gradient(135deg, #2ca87f 0%, #239670 100%);
            color: #fff;
            box-shadow: 0 3px 10px rgba(44,168,127,0.18);
        }
        .ap-btn-success:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 14px rgba(44,168,127,0.25);
        }
        .ap-btn-info {
            background: linear-gradient(135deg, #3ec9d6 0%, #2db5c2 100%);
            color: #fff;
            box-shadow: 0 3px 10px rgba(62,201,214,0.18);
        }
        .ap-btn-info:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 14px rgba(62,201,214,0.25);
        }
        .ap-btn-warning {
            background: linear-gradient(135deg, #e58a00 0%, #cc7a00 100%);
            color: #fff;
            box-shadow: 0 3px 10px rgba(229,138,0,0.18);
        }
        .ap-btn-warning:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 14px rgba(229,138,0,0.25);
        }
        .ap-btn-danger {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: #fff;
            box-shadow: 0 3px 10px rgba(220,38,38,0.18);
        }
        .ap-btn-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 14px rgba(220,38,38,0.25);
        }
        .ap-btn-sm {
            padding: 6px 14px;
            font-size: 12px;
            border-radius: 10px;
        }
        .ap-btn-xs {
            padding: 4px 10px;
            font-size: 11px;
            border-radius: 8px;
        }

        .version-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
        }
        .version-badge.current { background: #eef2ff; color: #5b73e8; }
        .version-badge.latest { background: #e6f7f2; color: #2ca87f; }
        .version-badge.outdated { background: #fff4e6; color: #e58a00; }

        .announcement-item {
            padding: 16px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        .announcement-item:last-child { border-bottom: none; }
        .announcement-date {
            font-size: 12px;
            color: #9ca3af;
            font-weight: 500;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .announcement-title {
            font-size: 14px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .announcement-body {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.6;
        }
        .announcement-tag {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .tag-info { background: #e6f9fb; color: #3ec9d6; }
        .tag-success { background: #e6f7f2; color: #2ca87f; }
        .tag-warning { background: #fff4e6; color: #e58a00; }
        .tag-danger { background: #fef2f2; color: #dc2626; }

        .author-card {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .author-avatar {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            object-fit: cover;
            flex-shrink: 0;
            border: 2px solid #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .author-name {
            font-size: 16px;
            font-weight: 700;
            color: #1f2937;
        }
        .author-role {
            font-size: 12px;
            color: #9ca3af;
            font-weight: 500;
            margin-top: 2px;
        }
        .author-links {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }
        .author-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            color: #5b73e8;
            background: #eef2ff;
            text-decoration: none;
            transition: all 0.2s;
        }
        .author-link:hover { background: #dbe4ff; }

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
        }

        .fade-in { animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity:0; transform: translateY(8px); } to { opacity:1; transform: translateY(0); } }

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
        <div id="contentArea" class="fade-in space-y-6">

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <div class="ap-card">
                    <div class="ap-card-header">
                        <div class="ap-card-title"><i class="fas fa-bullhorn text-primary mr-2"></i>更新公告</div>
                        <div class="flex items-center gap-2">
                            <span class="version-badge <?php echo $update_available ? 'outdated' : 'current'; ?>">
                                <i class="fas fa-code-branch"></i>
                                当前 v<?php echo htmlspecialchars($current_version); ?>
                            </span>
                            <?php if ($update_available): ?>
                            <span class="version-badge latest">
                                <i class="fas fa-arrow-up"></i>
                                最新 v<?php echo htmlspecialchars($latest_version); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="ap-card-body">
                        <?php foreach ($announcements as $ann): ?>
                        <div class="announcement-item">
                            <div class="announcement-date">
                                <i class="far fa-calendar-alt"></i>
                                <?php echo htmlspecialchars($ann['date']); ?>
                            </div>
                            <div class="announcement-title">
                                <?php if (!empty($ann['link'])): ?>
                                <a href="<?php echo htmlspecialchars($ann['link']); ?>" target="_blank" class="text-primary">
                                    <?php echo htmlspecialchars($ann['title']); ?>
                                </a>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($ann['title']); ?>
                                <?php endif; ?>
                                <span class="announcement-tag tag-<?php echo $ann['type']; ?>"><?php echo $ann['type']; ?></span>
                            </div>
                            <div class="announcement-body"><?php echo htmlspecialchars($ann['body']); ?></div>
                        </div>
                        <?php endforeach; ?>
                        <div class="mt-5 pt-4 border-t border-gray-100">
                            <div class="flex flex-wrap items-center gap-3">
                                <?php if ($update_available): ?>
                                <form method="POST" action="" onsubmit="return confirm('确定执行在线更新？建议先备份数据库和文件！')" style="display:inline;">
                                    <?php echo csrf_token_field(); ?>
                                    <button type="submit" name="do_online_update" class="ap-btn ap-btn-success">
                                        <i class="fas fa-cloud-download-alt"></i> 在线更新到 v<?php echo htmlspecialchars($latest_version); ?>
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="ap-btn ap-btn-success" style="opacity:0.6;cursor:default;">
                                    <i class="fas fa-check-circle"></i> 已是最新版本
                                </span>
                                <?php endif; ?>
                                <a href="<?php echo htmlspecialchars($download_package_url); ?>" target="_blank" class="ap-btn ap-btn-info">
                                    <i class="fas fa-file-archive"></i> 下载覆盖包
                                </a>
                            </div>
                            <p class="text-xs text-gray-400 mt-3">
                                <i class="fas fa-info-circle mr-1"></i>
                                在线更新将自动下载并覆盖系统文件 下载覆盖包可手动替换 更新前请务必备份数据库和配置文件
                            </p>
                        </div>
                    </div>
                </div>

                <div class="ap-card">
                    <div class="ap-card-header">
                        <div class="ap-card-title"><i class="fas fa-user-circle text-primary mr-2"></i>作者信息</div>
                    </div>
                    <div class="ap-card-body">
                        <div class="author-card">
                            <img src="http://q.qlogo.cn/headimg_dl?dst_uin=1239399954&spec=640&img_type=jpg" class="author-avatar" alt="Author">
                            <div>
                                <div class="author-name">云汀记</div>
                                <div class="author-role">开发者 / 垃圾视觉设计师</div>
                                <div class="author-links">
                                    <a href="https://imoe.top" target="_blank" class="author-link"><i class="fas fa-globe"></i> 官网</a>

                                    <a href="mailto:1239399954@qq.com" class="author-link"><i class="fas fa-envelope"></i> 邮箱</a>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 pt-5 border-t border-gray-100">
                            <div class="text-sm font-semibold text-gray-700 mb-3">关于本项目</div>
                            <p class="text-sm text-gray-500 leading-relaxed">
                                朋友圈（Moments）是一款轻量级社交动态系统 灵感源自微信朋友圈 专为个人站长和小型站点设计
                                支持图文动态、评论互动、点赞、标签分类、广告位管理等功能 前后端分离 部署简单
                            </p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="px-2.5 py-1 rounded-lg bg-primary-light text-primary text-xs font-semibold">PHP 8+</span>
                                <span class="px-2.5 py-1 rounded-lg bg-primary-light text-primary text-xs font-semibold">MySQL 5.7+</span>
                                <span class="px-2.5 py-1 rounded-lg bg-primary-light text-primary text-xs font-semibold">QQ群：1134371175</span>
                            </div>
                        </div>

                        <div class="mt-5 pt-5 border-t border-gray-100">
                            <div class="text-sm font-semibold text-gray-700 mb-3">技术支持</div>
                            <div class="space-y-2 text-sm text-gray-500">
                                <div class="flex items-center gap-2"><i class="fas fa-check text-success text-xs"></i> 文档中心：<a href="https://imoe.top" target="_blank" class="text-primary">暂未上线</a></div>
                                 </div>
                        </div>
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
    document.body.appendTo(toast);

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
</script>
</body>
</html>
