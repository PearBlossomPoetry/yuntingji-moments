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
$settings = get_all_settings();
$page_title = '访客记录';
$page_id = 'visitor_logs';

if (function_exists('ytj_ensure_visitor_table')) {
    ytj_ensure_visitor_table();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_validate_or_die(true);
    $action = $_POST['action'];
    if ($action === 'ban') {
        $ip = trim($_POST['ip'] ?? '');
        if ($ip === '') json_response(0, 'IP 不能为空');
        $esc_ip = mysqli_real_escape_string($conn, $ip);
        $reason = '访客记录拉黑';
        $esc_reason = mysqli_real_escape_string($conn, $reason);
        mysqli_query($conn, "INSERT INTO banned_ips (ip, reason) VALUES ('$esc_ip', '$esc_reason') ON DUPLICATE KEY UPDATE reason='$esc_reason'");
        json_response(1, '已拉黑 ' . $ip);
    } elseif ($action === 'unban') {
        $ip = trim($_POST['ip'] ?? '');
        if ($ip === '') json_response(0, 'IP 不能为空');
        $esc_ip = mysqli_real_escape_string($conn, $ip);
        mysqli_query($conn, "DELETE FROM banned_ips WHERE ip='$esc_ip'");
        json_response(1, '已解除 ' . $ip);
    } elseif ($action === 'clear') {
        mysqli_query($conn, "TRUNCATE TABLE visitor_logs");
        json_response(1, '已清空访客记录');
    }
    json_response(0, '未知操作');
}

$page = max(1, intval($_GET['p'] ?? 1));
$perpage = 30;
$search = trim($_GET['ip'] ?? '');
$where = '';
if ($search !== '') {
    $esc_search = mysqli_real_escape_string($conn, $search);
    $where = " WHERE ip LIKE '%$esc_search%'";
}
$total = 0;
$tr = mysqli_query($conn, "SELECT COUNT(*) AS c FROM visitor_logs $where");
if ($tr) $total = (int)mysqli_fetch_assoc($tr)['c'];
$total_pages = max(1, (int)ceil($total / $perpage));
$page = min($page, $total_pages);
$offset = ($page - 1) * $perpage;
$logs = [];
$res = mysqli_query($conn, "SELECT * FROM visitor_logs $where ORDER BY created_at DESC, id DESC LIMIT $perpage OFFSET $offset");
if ($res) { while ($row = mysqli_fetch_assoc($res)) $logs[] = $row; }

$banned = [];
$bres = mysqli_query($conn, "SELECT ip FROM banned_ips");
if ($bres) { while ($b = mysqli_fetch_assoc($bres)) $banned[$b['ip']] = true; }

$toast_msg = $_SESSION['admin_toast'] ?? '';
if ($toast_msg) unset($_SESSION['admin_toast']);

function carrier_badge($c) {
    return '<span class="tag tag-cn">' . htmlspecialchars($c) . '</span>';
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
    tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','sans-serif'] }, colors: { primary: '#5b73e8', 'primary-light': '#eef2ff', secondary: '#5b6b79', success: '#2ca87f', danger: '#dc2626', warning: '#e58a00', info: '#3ec9d6', dark: '#1d2630', body: '#f8f9fa' } } } };
    </script>
    <style>
        * { -webkit-tap-highlight-color: transparent; }
        body { font-family: 'Inter', sans-serif; background: #f4f7fa; }
        .sidebar-scroll::-webkit-scrollbar { width: 4px; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 4px; }
        .nav-item { transition: all 0.2s ease; border-radius: 12px; margin: 2px 12px; }
        .nav-item:hover { background: rgba(91, 115, 232, 0.06); color: #5b73e8; }
        .nav-item.active { background: rgba(91, 115, 232, 0.1); color: #5b73e8; }
        .nav-item.active .nav-icon { color: #5b73e8; }
        .nav-icon { color: #9ca3af; transition: color 0.2s; width: 20px; text-align: center; }
        .nav-item:hover .nav-icon { color: #5b73e8; }
        .section-title { font-size: 11px; letter-spacing: 0.08em; color: #6b7280; font-weight: 700; }
        .submenu { max-height: 0; overflow: hidden; transition: max-height 0.35s cubic-bezier(0.4,0,0.2,1); }
        .submenu.open { max-height: 600px; }
        .submenu-arrow { transition: transform 0.3s ease; font-size: 12px; color: #9ca3af; }
        .submenu-arrow.rotate { transform: rotate(90deg); color: #5b73e8; }
        .user-card { background: #f3f4f6; border-radius: 16px; transition: all 0.2s; }
        .user-card:hover { background: #e5e7eb; }
        .ap-card { background: #fff; border-radius: 20px; border: 1px solid #f0f0f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02), 0 4px 20px rgba(0,0,0,0.03); overflow: hidden; }
        .ap-card-header { padding: 18px 20px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f5f5f5; flex-wrap: wrap; gap: 12px; }
        .ap-card-title { font-size: 16px; font-weight: 700; color: #1f2937; letter-spacing: -0.2px; }
        .ap-card-body { padding: 0; }
        .ap-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 20px; border-radius: 14px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; border: none; outline: none; text-decoration: none; line-height: 1; white-space: nowrap; flex-shrink: 0; }
        .ap-btn:active { transform: scale(0.96); }
        .ap-btn-primary { background: linear-gradient(135deg, #5b73e8 0%, #4a62d7 100%); color: #fff; box-shadow: 0 3px 10px rgba(91,115,232,0.18); }
        .ap-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 5px 14px rgba(91,115,232,0.25); }
        .ap-btn-sm { padding: 7px 14px; font-size: 13px; border-radius: 10px; }
        .ap-btn-danger { background: #fff; color: #dc2626; border: 1px solid #fecaca; }
        .ap-btn-danger:hover { background: #fef2f2; }
        .ap-btn-ghost { background: #f3f4f6; color: #374151; }
        .ap-btn-ghost:hover { background: #e5e7eb; }
        .form-input { height: 40px; padding: 0 12px; border: 2px solid #f0f0f0; border-radius: 10px; font-size: 14px; color: #374151; outline: none; transition: all 0.2s; background: #fafafa; font-weight: 500; width: 100%; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        .form-input:focus { border-color: #5b73e8; }
        .vl-list { }
        .vl-item { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 12px 16px; border-bottom: 1px solid #f1f3f5; }
        .vl-item:last-child { border-bottom: none; }
        .vl-item:hover { background: #fafbff; }
        .vl-item-main { flex: 1; min-width: 0; }
        .vl-ip-row { display: flex; align-items: baseline; gap: 8px; margin-bottom: 4px; flex-wrap: wrap; }
        .vl-ip { font-weight: 600; color: #1f2937; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 15px; word-break: break-all; }
        .vl-id { font-size: 12px; color: #b6bcc4; font-weight: 500; flex-shrink: 0; }
        .vl-tags { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-bottom: 0; }
        .vl-meta { color: #9ca3af; font-size: 12px; line-height: 1.5; word-break: break-all; margin-bottom: 6px; }
        .vl-meta a { color: #5b73e8; text-decoration: none; }
        .vl-meta a:hover { text-decoration: underline; }
        .vl-dot { margin: 0 6px; color: #d1d5db; }
        .vl-actions { flex-shrink: 0; }
        .vl-muted { color: #9ca3af; font-size: 12px; }
        .vl-pager { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .vl-pager a, .vl-pager span { min-width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0 8px; border-radius: 9px; font-size: 13px; text-decoration: none; }
        .vl-pager a { background: #f3f4f6; color: #374151; }
        .vl-pager a:hover { background: #e5e7eb; }
        .vl-pager .cur { background: #5b73e8; color: #fff; }
        .vl-pager .disabled { color: #d1d5db; pointer-events: none; }
        .ap-toast { position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: flex; align-items: center; gap: 10px; padding: 10px 16px; background: #1e293b; color: #fff; border-radius: 14px; font-size: 13px; font-weight: 500; box-shadow: 0 8px 24px rgba(0,0,0,0.2); transform: translateX(120%); opacity: 0; transition: all 0.4s cubic-bezier(0.4,0,0.2,1); pointer-events: none; max-width: min(90vw, 360px); word-break: break-word; }
        .ap-toast.show { transform: translateX(0); opacity: 1; }
        .ap-toast-icon { width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 11px; }
        .ap-toast-icon.success { background: #2ca87f; color: #fff; }
        .ap-toast-icon.error { background: #dc2626; color: #fff; }

        .tag { display:inline-block; padding:2px 9px; border-radius:999px; font-size:12px; line-height:1.6; font-weight:600; white-space:nowrap; }
        .tag-cn { background:#e8f1ff; color:#007aff; }
        .tag-mobile { background:#ecfdf3; color:#16a34a; }
        .tag-pc { background:#f1f3f5; color:#4b5563; }
        .tag-black { background:#fee2e2; color:#ff3b30; }
        .ip-code { background:#f5f7fa; padding:2px 6px; border-radius:3px; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12px; color:#1f2937; }
        .label-text { color:#9ca3af; font-size:11px; font-weight:600; flex-shrink:0; }
        .status-badge { display:inline-block; padding:2px 9px; border-radius:999px; font-size:12px; font-weight:600; }
        .status-badge.ok { background:#ecfdf3; color:#16a34a; }
        .status-badge.ban { background:#fee2e2; color:#ff3b30; }

        .confirm-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 20px; opacity: 0; visibility: hidden; transition: all 0.3s ease; }
        .confirm-box { background: #fff; border-radius: 16px; padding: 16px 20px; max-width: 260px; width: 100%; text-align: center; transform: scale(0.9); transition: transform 0.3s ease; }
        .confirm-overlay.show { opacity: 1; visibility: visible; }
        .confirm-overlay.show .confirm-box { transform: scale(1); }
        .confirm-box h3 { font-size: 15px; font-weight: 700; color: #1f2937; margin-bottom: 8px; }
        .confirm-box p { font-size: 13px; color: #6b7280; margin-bottom: 14px; line-height: 1.4; }
        .confirm-box .confirm-actions { display: flex; gap: 8px; }
        .confirm-box .confirm-actions button { flex: 1; padding: 8px 10px; border-radius: 10px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; text-align: center; }
        .confirm-box .confirm-actions .cancel { background: #f3f4f6; color: #6b7280; }
        .confirm-box .confirm-actions .confirm { background: #dc2626; color: #fff; }

        @media (min-width: 769px) {
                .vl-list {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
                padding: 24px;
                }
                .vl-item { border-bottom: none; border-radius: 12px; background: #fff; border: 1px solid #f0f0f0; padding: 10px 14px; }
                .vl-item:hover { background: #fafbff; }
                .vl-item-main { min-width: 0; }
                .vl-ip-row { margin-bottom: 2px; }
                .vl-meta { margin-bottom: 4px; font-size: 11px; }
                .vl-tags { gap: 4px; }
                .vl-actions { align-self: center; }

                .ap-card-header:last-child {
                padding-right: 24px;
                padding-left: 24px;
                }
        }

        @media (max-width: 768px) {
                main { padding: 14px !important; }
                .ap-card-header { padding: 16px 16px; flex-direction: column; align-items: stretch; gap: 14px; }
                .ap-card-header > div:last-child { width: 100%; flex-wrap: wrap; gap: 10px; }
                .ap-card-header form { width: 100%; gap: 10px; }
                .ap-card-header .form-input { flex: 1; min-width: 0; }

                .vl-item { padding: 12px 14px; gap: 10px; }
                .vl-ip { font-size: 14px; }
                .vl-actions .ap-btn { padding: 7px 13px; font-size: 12px; }
                .vl-pager a, .vl-pager span { min-width: 30px; height: 30px; font-size: 12px; }
                .ap-btn-sm { padding: 7px 13px; font-size: 12px; border-radius: 10px; }
        }

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
    </style>
</head>
<body class="bg-gray-50">

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
                    <div class="ap-card">
                                <div class="ap-card-header">
                            <div class="ap-card-title">访客记录 <span class="vl-muted">(共 <?php echo $total; ?> 条)</span></div>
                            <div class="flex items-center gap-2">
                                <form method="get" class="flex items-center gap-2">
                                                <input type="text" name="ip" class="form-input" style="width:180px;" placeholder="按 IP 搜索" value="<?php echo htmlspecialchars($search); ?>">
                                                <button type="submit" class="ap-btn ap-btn-primary ap-btn-sm"><i class="fas fa-search"></i> 搜索</button>
                                                <?php if ($search !== ''): ?><a href="visitor_logs.php" class="ap-btn ap-btn-ghost ap-btn-sm">清空</a><?php endif; ?>
                                </form>
                                <button onclick="confirmClear(this)" class="ap-btn ap-btn-danger ap-btn-sm"><i class="fas fa-trash"></i> 清空记录</button>
                            </div>
                                </div>
                                <div class="ap-card-body">
                            <?php if (empty($logs)): ?>
                                <div style="padding:48px;text-align:center;color:#9ca3af;">
                                                <i class="fas fa-eye-slash" style="font-size:32px;display:block;margin-bottom:10px;"></i>
                                                暂无访客记录
                                </div>
                            <?php else: ?>
                            <div class="vl-list">
                                                <?php foreach ($logs as $lg): ?>
                                                <div class="vl-item">
                                        <div class="vl-item-main">
                                            <div class="vl-ip-row">
                                                                <span class="vl-ip"><?php echo htmlspecialchars($lg['ip']); ?></span>
                                                                <span class="vl-id">#<?php echo (int)$lg['id']; ?></span>
                                            </div>
                                            <div class="vl-meta">
                                                                来源：<?php if (!empty($lg['referer'])): ?><a href="<?php echo htmlspecialchars($lg['referer']); ?>" target="_blank" title="<?php echo htmlspecialchars($lg['referer']); ?>"><?php echo htmlspecialchars(mb_substr($lg['referer'], 0, 40)); ?><?php echo mb_strlen($lg['referer']) > 40 ? '…' : ''; ?></a><?php else: ?>直接访问<?php endif; ?>
                                            </div>
                                            <div class="vl-tags">
                                                                <?php echo carrier_badge($lg['carrier'] ?? '未知'); ?>
                                                                <?php
                                                                $dev_os = $lg['os'] ?? '未知';
                                                                $dev_model = trim($lg['model'] ?? '');
                                                                $dev_browser = $lg['browser'] ?? '';
                                                                $dev_text = $dev_os . ' / ' . ($dev_model !== '' ? $dev_model : $dev_browser);
                                                                $is_pc = (stripos($dev_os, 'Windows') !== false || stripos($dev_os, 'macOS') !== false);
                                                                $dev_cls = $is_pc ? 'tag-pc' : 'tag-mobile';
                                                                ?>
                                                                <span class="tag <?php echo $dev_cls; ?>"><?php echo htmlspecialchars($dev_text); ?></span>
                                                                <?php if (!empty($banned[$lg['ip']])): ?>
                                                    <span class="status-badge ban">已拉黑</span>
                                                                <?php else: ?>
                                                    <span class="status-badge ok">正常</span>
                                                                <?php endif; ?>
                                                                <span class="vl-muted"><?php echo htmlspecialchars($lg['created_at']); ?></span>
                                            </div>
                                        </div>
                                        <div class="vl-actions">
                                            <?php if (!empty($banned[$lg['ip']])): ?>
                                                                <button onclick="confirmBan('<?php echo htmlspecialchars($lg['ip']); ?>', 'unban', this)" class="ap-btn ap-btn-ghost ap-btn-sm"><i class="fas fa-unlock"></i> 解除</button>
                                            <?php else: ?>
                                                                <button onclick="confirmBan('<?php echo htmlspecialchars($lg['ip']); ?>', 'ban', this)" class="ap-btn ap-btn-danger ap-btn-sm"><i class="fas fa-ban"></i> 拉黑</button>
                                            <?php endif; ?>
                                        </div>
                                                </div>
                                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                                </div>
                                <?php if ($total_pages > 1): ?>
                                <div class="ap-card-header" style="border-top:1px solid #f5f5f5;border-bottom:none;">
                            <div class="vl-muted">第 <?php echo $page; ?> / <?php echo $total_pages; ?> 页</div>
                            <div class="vl-pager">
                                <?php
                                $base = 'visitor_logs.php?' . ($search !== '' ? 'ip=' . urlencode($search) . '&' : '');
                                if ($page > 1): ?><a href="<?php echo $base; ?>p=1">首页</a><a href="<?php echo $base; ?>p=<?php echo $page - 1; ?>">上一页</a><?php else: ?><span class="disabled">首页</span><span class="disabled">上一页</span><?php endif;
                                $start = max(1, $page - 3); $end = min($total_pages, $page + 3);
                                for ($i = $start; $i <= $end; $i++):
                                                if ($i === $page): ?><span class="cur"><?php echo $i; ?></span><?php else: ?><a href="<?php echo $base; ?>p=<?php echo $i; ?>"><?php echo $i; ?></a><?php endif;
                                endfor;
                                if ($page < $total_pages): ?><a href="<?php echo $base; ?>p=<?php echo $page + 1; ?>">下一页</a><a href="<?php echo $base; ?>p=<?php echo $total_pages; ?>">末页</a><?php else: ?><span class="disabled">下一页</span><span class="disabled">末页</span><?php endif;
                                ?>
                            </div>
                                </div>
                                <?php endif; ?>
                    </div>
                </div>
                </main>
        <footer class="app-footer py-5 px-4 lg:px-8 mt-auto">
                <div class="flex items-center justify-center">
                <p class="text-gray-500 font-medium">
                    <a href="https://imoe.top" target="_blank" class="text-primary font-bold">云汀记</a>
                    <span class="text-gray-400 mx-1">©</span>
                    <span>2025 朋友圈</span>
                </p>
                </div>
        </footer>
</div>

    <div class="confirm-overlay" id="vlConfirm">
        <div class="confirm-box">
                <h3><i class="fas fa-exclamation-triangle" style="color:#e58a00;margin-right:4px;font-size:14px;"></i>确认操作</h3>
                <p id="vlConfirmMsg">确认操作？</p>
                <div class="confirm-actions">
                <button class="cancel" onclick="closeVLConfirm()">取消</button>
                <button class="confirm" onclick="doVLConfirm()">确认</button>
                </div>
        </div>
    </div>

    <script>
    const CSRF_TOKEN = '<?php echo csrf_token_value(); ?>';
    function toggleSidebar() {
        var s = document.getElementById('sidebar');
        if (!s) return;
        if (s.classList.contains('-translate-x-full')) {
            s.classList.remove('-translate-x-full');
            document.getElementById('sidebarOverlay').classList.remove('hidden');
        } else {
            s.classList.add('-translate-x-full');
            document.getElementById('sidebarOverlay').classList.add('hidden');
        }
    }
    function toggleSubmenu(id, el) {
        var sub = document.getElementById(id);
        if (!sub) return;
        sub.classList.toggle('open');
        var ar = el.querySelector('.submenu-arrow');
        if (ar) ar.classList.toggle('rotate');
    }
    function showToast(msg, type) {
        type = type || 'success';
        var existing = document.querySelector('.ap-toast');
        if (existing) existing.remove();
        var toast = document.createElement('div');
        toast.className = 'ap-toast';
        var iconClass = type === 'success' ? 'fa-check' : 'fa-exclamation';
        var iconBg = type === 'success' ? 'success' : 'error';
        toast.innerHTML = '<div class="ap-toast-icon ' + iconBg + '"><i class="fas ' + iconClass + '"></i></div><span></span>';
        toast.lastChild.textContent = msg;
        document.body.appendChild(toast);
        requestAnimationFrame(function () { toast.classList.add('show'); });
        setTimeout(function () {
            toast.classList.remove('show');
            setTimeout(function () { toast.remove(); }, 400);
        }, 3000);
    }
    function postData(action, ip) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('ip', ip);
        fd.append('csrf_token', CSRF_TOKEN);
        return fetch('', { method: 'POST', body: fd }).then(function (r) { return r.json(); });
    }
    var vlPendingIp = '', vlPendingAction = '', vlPendingBtn = null;
    function confirmBan(ip, action, btn) {
        vlPendingIp = ip; vlPendingAction = action; vlPendingBtn = btn;
        var msg = action === 'ban' ? '确认拉黑该 IP？拉黑后将无法访问前台' : '确认解除对该 IP 的拉黑？';
        document.getElementById('vlConfirmMsg').textContent = msg;
        document.getElementById('vlConfirm').classList.add('show');
    }
    function confirmClear(btn) {
        vlPendingAction = 'clear'; vlPendingBtn = btn;
        document.getElementById('vlConfirmMsg').textContent = '确定清空全部访客记录？此操作不可恢复。';
        document.getElementById('vlConfirm').classList.add('show');
    }
    function closeVLConfirm() {
        document.getElementById('vlConfirm').classList.remove('show');
        vlPendingAction = ''; vlPendingIp = ''; vlPendingBtn = null;
    }
    function doVLConfirm() {
        var ip = vlPendingIp, action = vlPendingAction, btn = vlPendingBtn;
        closeVLConfirm();
        if (!action) return;
        if (action === 'clear') {
            btn.disabled = true;
            postData('clear', '').then(function (d) {
                if (d.code === 1) { location.reload(); }
                else { showToast(d.msg || '操作失败', 'error'); btn.disabled = false; }
            }).catch(function () { showToast('网络错误', 'error'); btn.disabled = false; });
            return;
        }
        btn.disabled = true;
        postData(action, ip).then(function (d) {
            if (d.code === 1) {
                var item = btn.closest('.vl-item');
                var statusBadge = item ? item.querySelector('.status-badge') : null;
                function setStatus(banned) {
                    if (!statusBadge) return;
                    statusBadge.className = 'status-badge ' + (banned ? 'ban' : 'ok');
                    statusBadge.textContent = banned ? '已拉黑' : '正常';
                }
                if (action === 'ban') {
                    var nb = document.createElement('button');
                    nb.className = 'ap-btn ap-btn-ghost ap-btn-sm';
                    nb.innerHTML = '<i class="fas fa-unlock"></i> 解除';
                    nb.onclick = function(){ confirmBan(ip, 'unban', nb); };
                    btn.parentNode.replaceChild(nb, btn);
                    setStatus(true);
                } else {
                    var nb = document.createElement('button');
                    nb.className = 'ap-btn ap-btn-danger ap-btn-sm';
                    nb.innerHTML = '<i class="fas fa-ban"></i> 拉黑';
                    nb.onclick = function(){ confirmBan(ip, 'ban', nb); };
                    btn.parentNode.replaceChild(nb, btn);
                    setStatus(false);
                }
                showToast(d.msg || '操作成功', 'success');
            } else { showToast(d.msg || '操作失败', 'error'); btn.disabled = false; }
        }).catch(function () { showToast('网络错误', 'error'); btn.disabled = false; });
    }
    <?php if ($toast_msg): ?>showToast(<?php echo json_encode($toast_msg); ?>, 'success');<?php endif; ?>
    </script>
</body>
</html>
