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
$page_title = '违禁词与IP';
$page_id = 'ban_manage';

if (isset($_POST['ajax_add_word'])) {
    csrf_validate_or_die(true);
    $word = trim($_POST['word'] ?? '');
    if (empty($word)) json_response(0, '违禁词不能为空');
    $esc_word = mysqli_real_escape_string($conn, $word);
    if (mysqli_query($conn, "INSERT INTO banned_words (word) VALUES ('$esc_word') ON DUPLICATE KEY UPDATE word='$esc_word'")) {
        $new_id = mysqli_insert_id($conn);
        json_response(1, '添加成功', ['id' => $new_id, 'time' => date('Y-m-d H:i:s')]);
    } else {
        json_response(0, '添加失败');
    }
}

if (isset($_POST['ajax_edit_word'])) {
    csrf_validate_or_die(true);
    $id = intval($_POST['id'] ?? 0);
    $word = trim($_POST['word'] ?? '');
    if (empty($word)) json_response(0, '违禁词不能为空');
    $esc_word = mysqli_real_escape_string($conn, $word);
    mysqli_query($conn, "UPDATE banned_words SET word = '$esc_word' WHERE id = $id");
    json_response(1, '修改成功');
}

if (isset($_POST['ajax_delete_word'])) {
    csrf_validate_or_die(true);
    $id = intval($_POST['id'] ?? 0);
    mysqli_query($conn, "DELETE FROM banned_words WHERE id = $id");
    json_response(1, '删除成功');
}

if (isset($_POST['ajax_add_ip'])) {
    csrf_validate_or_die(true);
    $ip = trim($_POST['ip'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    if (empty($ip)) json_response(0, 'IP地址不能为空');
    $esc_ip = mysqli_real_escape_string($conn, $ip);
    $esc_reason = mysqli_real_escape_string($conn, $reason);
    if (mysqli_query($conn, "INSERT INTO banned_ips (ip, reason) VALUES ('$esc_ip', '$esc_reason') ON DUPLICATE KEY UPDATE reason='$esc_reason'")) {
        $new_id = mysqli_insert_id($conn);
        json_response(1, '添加成功', ['id' => $new_id, 'time' => date('Y-m-d H:i:s')]);
    } else {
        json_response(0, '添加失败');
    }
}

if (isset($_POST['ajax_edit_ip'])) {
    csrf_validate_or_die(true);
    $id = intval($_POST['id'] ?? 0);
    $ip = trim($_POST['ip'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    if (empty($ip)) json_response(0, 'IP地址不能为空');
    $esc_ip = mysqli_real_escape_string($conn, $ip);
    $esc_reason = mysqli_real_escape_string($conn, $reason);
    mysqli_query($conn, "UPDATE banned_ips SET ip = '$esc_ip', reason = '$esc_reason' WHERE id = $id");
    json_response(1, '修改成功');
}

if (isset($_POST['ajax_delete_ip'])) {
    csrf_validate_or_die(true);
    $id = intval($_POST['id'] ?? 0);
    mysqli_query($conn, "DELETE FROM banned_ips WHERE id = $id");
    json_response(1, '删除成功');
}

$words = [];
$wr = mysqli_query($conn, "SELECT * FROM banned_words ORDER BY created_at DESC");
if ($wr) while ($w = mysqli_fetch_assoc($wr)) $words[] = $w;

$ips = [];
$ir = mysqli_query($conn, "SELECT * FROM banned_ips ORDER BY created_at DESC");
if ($ir) while ($i = mysqli_fetch_assoc($ir)) $ips[] = $i;

$toast_msg = $_SESSION['admin_toast'] ?? '';
if ($toast_msg) {
    unset($_SESSION['admin_toast']);
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
            padding: 18px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #f5f5f5;
        }
        .ap-card-title {
            font-size: 16px;
            font-weight: 700;
            color: #1f2937;
            letter-spacing: -0.2px;
        }
        .ap-card-body { padding: 16px 20px 20px; }

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

        .ban-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .ban-input-row {
            display: flex;
            gap: 10px;
            margin-bottom: 16px;
            align-items: center;
        }
        .ban-input-row .form-input { flex: 1; }

        .ban-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            align-items: start;
        }

        .ban-item {
            display: flex;
            flex-direction: column;
            padding: 14px 16px;
            background: #fff;
            border: 1.5px solid #f0f0f0;
            border-radius: 14px;
            transition: all 0.2s ease;
            box-sizing: border-box;
            position: relative;
        }
        .ban-item:hover {
            border-color: #e5e7eb;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            transform: translateY(-2px);
        }

        .ban-item-content {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 0;
        }
        .ban-item-word, .ban-item-ip {
            font-size: 15px;
            font-weight: 700;
            color: #1f2937;
            word-break: break-all;
            line-height: 1.35;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .ban-item-reason {
            font-size: 11px;
            color: #6b7280;
            background: #f3f4f6;
            padding: 3px 10px;
            border-radius: 20px;
            white-space: nowrap;
            width: fit-content;
            font-weight: 500;
            line-height: 1;
        }
        .ban-item-time {
            font-size: 11px;
            color: #9ca3af;
        }

        .ban-item-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #f5f5f5;
        }

        .ban-empty {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px 20px;
            color: #9ca3af;
            font-size: 14px;
        }

        .btn-edit, .btn-save, .btn-cancel, .btn-del {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 13px;
            flex-shrink: 0;
            position: relative;
        }
        .btn-edit { background: #eff6ff; color: #3b82f6; }
        .btn-edit:hover { background: #3b82f6; color: #fff; }
        .btn-edit:active { transform: scale(0.92); }
        .btn-save { background: #dcfce7; color: #166534; }
        .btn-save:hover { background: #166534; color: #fff; }
        .btn-save:active { transform: scale(0.92); }
        .btn-cancel { background: #f3f4f6; color: #6b7280; }
        .btn-cancel:hover { background: #6b7280; color: #fff; }
        .btn-cancel:active { transform: scale(0.92); }
        .btn-del { background: #fef2f2; color: #dc2626; }
        .btn-del:hover { background: #dc2626; color: #fff; }
        .btn-del:active { transform: scale(0.92); }

        .edit-input {
            height: 34px;
            padding: 0 10px;
            border: 2px solid #5b73e8;
            border-radius: 8px;
            font-size: 14px;
            color: #374151;
            outline: none;
            background: #fff;
            font-weight: 500;
            width: 100%;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
            margin-bottom: 4px;
        }
        .edit-input-sm {
            height: 28px;
            padding: 0 10px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 12px;
            color: #6b7280;
            outline: none;
            background: #fafafa;
            font-weight: 500;
            width: 100%;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
            margin-bottom: 4px;
        }

        .confirm-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.25s ease;
        }
        .confirm-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        .confirm-box {
            background: #fff;
            border-radius: 20px;
            padding: 20px;
            max-width: 260px;
            width: 100%;
            text-align: center;
            transform: scale(0.9);
            transition: transform 0.25s cubic-bezier(0.4,0,0.2,1);
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        .confirm-overlay.show .confirm-box {
            transform: scale(1);
        }
        .confirm-box h3 {
            font-size: 16px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .confirm-box p {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 18px;
            line-height: 1.4;
        }
        .confirm-box .confirm-actions {
            display: flex;
            gap: 10px;
        }
        .confirm-box .confirm-actions button {
            flex: 1;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
        }
        .confirm-box .confirm-actions .cancel {
            background: #f3f4f6;
            color: #374151;
        }
        .confirm-box .confirm-actions .cancel:hover { background: #e5e7eb; }
        .confirm-box .confirm-actions .confirm {
            background: #dc2626;
            color: #fff;
        }
        .confirm-box .confirm-actions .confirm:hover { background: #b91c1c; }

        @media (max-width: 768px) {
            .ap-card-header { padding: 14px 16px; }
            .ap-card-body { padding: 12px 16px 16px; }
            .ap-btn { padding: 8px 14px; font-size: 13px; border-radius: 12px; }
            .ap-btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 8px; }

            .ban-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .ban-input-row {
                flex-wrap: wrap;
                gap: 8px;
            }
            .ban-input-row .form-input {
                flex: 1 1 100%;
                min-width: 0;
            }
            .ban-input-row .ap-btn {
                width: 100%;
                justify-content: center;
            }

            .ban-item { padding: 12px 14px; }
            .ban-item-word, .ban-item-ip { font-size: 14px; }
            .ban-item-reason { font-size: 10px; padding: 2px 8px; }
            .ban-item-time { font-size: 10px; }
            .ban-item-footer { margin-top: 8px; padding-top: 8px; }

            .btn-edit, .btn-save, .btn-cancel, .btn-del {
                width: 34px;
                height: 34px;
                font-size: 12px;
                border-radius: 8px;
            }

            .confirm-box { padding: 18px; border-radius: 18px; }
            .confirm-box h3 { font-size: 15px; }
            .confirm-box p { font-size: 13px; margin-bottom: 16px; }
            .confirm-box .confirm-actions button {
                padding: 9px 12px;
                font-size: 13px;
                border-radius: 10px;
            }
        }
    </style>
</head>
<body class="text-gray-700 antialiased">

<div class="confirm-overlay" id="confirmDialog">
    <div class="confirm-box">
        <h3><i class="fas fa-exclamation-triangle" style="color:#f59e0b;font-size:18px;"></i>确认操作</h3>
        <p id="confirmMsg">确认删除？</p>
        <div class="confirm-actions">
            <button class="cancel" onclick="closeConfirm()">取消</button>
            <button class="confirm" onclick="doConfirmAction()">确认</button>
        </div>
    </div>
</div>

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

<div class="fade-in" style="max-width:900px;">
    <div class="ban-grid">

        <div class="ap-card">
            <div class="ap-card-header">
                <div class="ap-card-title">违禁词 (<span id="wordCount"><?php echo count($words); ?></span>)</div>
            </div>
            <div class="ap-card-body">
                <div class="ban-input-row">
                    <input type="text" class="form-input" id="wordInput" placeholder="输入违禁词，多个用逗号分隔" onkeydown="if(event.key==='Enter')addWord()">
                    <button class="ap-btn ap-btn-primary" onclick="addWord()"><i class="fas fa-plus"></i> 添加</button>
                </div>
                <div class="ban-list" id="wordList">
                    <?php if (empty($words)): ?>
                    <div class="ban-empty" id="wordEmpty"><i class="fas fa-inbox" style="font-size:20px;margin-bottom:8px;color:#d1d5db;display:block;"></i>暂无违禁词</div>
                    <?php else: foreach ($words as $w): ?>
                    <div class="ban-item" data-id="<?php echo $w['id']; ?>" id="word-<?php echo $w['id']; ?>">
                        <div class="ban-item-content" id="word-main-<?php echo $w['id']; ?>" data-time="<?php echo $w['created_at']; ?>">
                            <span class="ban-item-word" id="word-text-<?php echo $w['id']; ?>"><?php echo htmlspecialchars($w['word']); ?></span>
                            <span class="ban-item-time"><?php echo $w['created_at']; ?></span>
                        </div>
                        <div class="ban-item-footer" id="word-actions-<?php echo $w['id']; ?>">
                            <button class="btn-edit" onclick="editWord(<?php echo $w['id']; ?>)" title="编辑"><i class="fas fa-pen"></i></button>
                            <button class="btn-del" onclick="confirmDelete('word', <?php echo $w['id']; ?>)" title="删除"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <div class="ap-card">
            <div class="ap-card-header">
                <div class="ap-card-title">IP黑名单 (<span id="ipCount"><?php echo count($ips); ?></span>)</div>
            </div>
            <div class="ap-card-body">
                <div class="ban-input-row">
                    <input type="text" class="form-input" id="ipInput" placeholder="输入IP地址">
                    <input type="text" class="form-input" id="ipReasonInput" placeholder="原因（选填）">
                    <button class="ap-btn ap-btn-primary" onclick="addIp()"><i class="fas fa-plus"></i> 添加</button>
                </div>
                <div class="ban-list" id="ipList">
                    <?php if (empty($ips)): ?>
                    <div class="ban-empty" id="ipEmpty"><i class="fas fa-inbox" style="font-size:20px;margin-bottom:8px;color:#d1d5db;display:block;"></i>暂无黑名单IP</div>
                    <?php else: foreach ($ips as $i): ?>
                    <div class="ban-item" data-id="<?php echo $i['id']; ?>" id="ip-<?php echo $i['id']; ?>">
                        <div class="ban-item-content" id="ip-main-<?php echo $i['id']; ?>" data-time="<?php echo $i['created_at']; ?>">
                            <div class="ban-item-ip" id="ip-text-<?php echo $i['id']; ?>"><?php echo htmlspecialchars($i['ip']); ?></div>
                            <?php if ($i['reason']): ?><span class="ban-item-reason" id="ip-reason-<?php echo $i['id']; ?>"><?php echo htmlspecialchars($i['reason']); ?></span><?php endif; ?>
                            <span class="ban-item-time"><?php echo $i['created_at']; ?></span>
                        </div>
                        <div class="ban-item-footer" id="ip-actions-<?php echo $i['id']; ?>">
                            <button class="btn-edit" onclick="editIp(<?php echo $i['id']; ?>)" title="编辑"><i class="fas fa-pen"></i></button>
                            <button class="btn-del" onclick="confirmDelete('ip', <?php echo $i['id']; ?>)" title="删除"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
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

let pendingDelete = { type: null, id: null };

function confirmDelete(type, id) {
    pendingDelete = { type, id };
    const msg = type === 'word' ? '确认删除该违禁词？' : '确认删除该IP？';
    document.getElementById('confirmMsg').textContent = msg;
    document.getElementById('confirmDialog').classList.add('show');
}

function closeConfirm() {
    document.getElementById('confirmDialog').classList.remove('show');
    pendingDelete = { type: null, id: null };
}

function doConfirmAction() {
    if (!pendingDelete.id) return;
    const { type, id } = pendingDelete;
    closeConfirm();
    if (type === 'word') deleteWord(id);
    else deleteIp(id);
}

function editWord(id) {
    const main = document.getElementById('word-main-' + id);
    const currentWord = document.getElementById('word-text-' + id).textContent;
    main.dataset.original = main.innerHTML;
    main.innerHTML = '<input type="text" class="edit-input" value="' + currentWord.replace(/"/g, '&quot;') + '" id="edit-word-input-' + id + '" onkeydown="if(event.key===\'Enter\')saveWord(' + id + ')">';
    document.getElementById('edit-word-input-' + id).focus();

    const actions = document.getElementById('word-actions-' + id);
    actions.innerHTML = '<button class="btn-save" onclick="saveWord(' + id + ')" title="保存"><i class="fas fa-check"></i></button>' +
        '<button class="btn-cancel" onclick="cancelEditWord(' + id + ')" title="取消"><i class="fas fa-times"></i></button>';
}

function saveWord(id) {
    const input = document.getElementById('edit-word-input-' + id);
    const word = input.value.trim();
    if (!word) return showToast('违禁词不能为空', 'error');

    const data = new FormData();
    data.append('ajax_edit_word', '1');
    data.append('id', id);
    data.append('word', word);
    data.append('csrf_token', CSRF_TOKEN);

    fetch('', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.code == 1) {
                showToast('修改成功', 'success');
                const main = document.getElementById('word-main-' + id);
                const time = main.dataset.time;
                main.innerHTML = '<span class="ban-item-word" id="word-text-' + id + '">' + word.replace(/</g, '&lt;') + '</span>' +
                    '<span class="ban-item-time">' + time + '</span>';
                const actions = document.getElementById('word-actions-' + id);
                actions.innerHTML = '<button class="btn-edit" onclick="editWord(' + id + ')" title="编辑"><i class="fas fa-pen"></i></button>' +
                    '<button class="btn-del" onclick="confirmDelete(\'word\', ' + id + ')" title="删除"><i class="fas fa-trash"></i></button>';
            } else {
                showToast(res.msg || '修改失败', 'error');
            }
        })
        .catch(() => showToast('网络错误', 'error'));
}

function cancelEditWord(id) {
    const main = document.getElementById('word-main-' + id);
    main.innerHTML = main.dataset.original;
    const actions = document.getElementById('word-actions-' + id);
    actions.innerHTML = '<button class="btn-edit" onclick="editWord(' + id + ')" title="编辑"><i class="fas fa-pen"></i></button>' +
        '<button class="btn-del" onclick="confirmDelete(\'word\', ' + id + ')" title="删除"><i class="fas fa-trash"></i></button>';
}

function editIp(id) {
    const main = document.getElementById('ip-main-' + id);
    const ipText = document.getElementById('ip-text-' + id);
    const reasonEl = document.getElementById('ip-reason-' + id);
    const currentIp = ipText.textContent;
    const currentReason = reasonEl ? reasonEl.textContent : '';

    main.dataset.original = main.innerHTML;
    main.innerHTML = '<input type="text" class="edit-input" value="' + currentIp.replace(/"/g, '&quot;') + '" id="edit-ip-input-' + id + '" placeholder="IP地址" onkeydown="if(event.key===\'Enter\')saveIp(' + id + ')">' +
        '<input type="text" class="edit-input-sm" value="' + currentReason.replace(/"/g, '&quot;') + '" id="edit-reason-input-' + id + '" placeholder="原因（选填）">';

    const actions = document.getElementById('ip-actions-' + id);
    actions.innerHTML = '<button class="btn-save" onclick="saveIp(' + id + ')" title="保存"><i class="fas fa-check"></i></button>' +
        '<button class="btn-cancel" onclick="cancelEditIp(' + id + ')" title="取消"><i class="fas fa-times"></i></button>';
}

function saveIp(id) {
    const ipInput = document.getElementById('edit-ip-input-' + id);
    const reasonInput = document.getElementById('edit-reason-input-' + id);
    const ip = ipInput.value.trim();
    const reason = reasonInput ? reasonInput.value.trim() : '';
    if (!ip) return showToast('IP地址不能为空', 'error');

    const data = new FormData();
    data.append('ajax_edit_ip', '1');
    data.append('id', id);
    data.append('ip', ip);
    data.append('reason', reason);
    data.append('csrf_token', CSRF_TOKEN);

    fetch('', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.code == 1) {
                showToast('修改成功', 'success');
                const main = document.getElementById('ip-main-' + id);
                const time = main.dataset.time;
                let reasonHtml = reason ? '<span class="ban-item-reason" id="ip-reason-' + id + '">' + reason.replace(/</g, '&lt;') + '</span>' : '';
                main.innerHTML = '<div class="ban-item-ip" id="ip-text-' + id + '">' + ip.replace(/</g, '&lt;') + '</div>' + reasonHtml +
                    '<span class="ban-item-time">' + time + '</span>';
                const actions = document.getElementById('ip-actions-' + id);
                actions.innerHTML = '<button class="btn-edit" onclick="editIp(' + id + ')" title="编辑"><i class="fas fa-pen"></i></button>' +
                    '<button class="btn-del" onclick="confirmDelete(\'ip\', ' + id + ')" title="删除"><i class="fas fa-trash"></i></button>';
            } else {
                showToast(res.msg || '修改失败', 'error');
            }
        })
        .catch(() => showToast('网络错误', 'error'));
}

function cancelEditIp(id) {
    const main = document.getElementById('ip-main-' + id);
    main.innerHTML = main.dataset.original;
    const actions = document.getElementById('ip-actions-' + id);
    actions.innerHTML = '<button class="btn-edit" onclick="editIp(' + id + ')" title="编辑"><i class="fas fa-pen"></i></button>' +
        '<button class="btn-del" onclick="confirmDelete(\'ip\', ' + id + ')" title="删除"><i class="fas fa-trash"></i></button>';
}

function addWord() {
    const input = document.getElementById('wordInput');
    const word = input.value.trim();
    if (!word) return showToast('请输入违禁词', 'error');

    const data = new FormData();
    data.append('ajax_add_word', '1');
    data.append('word', word);
    data.append('csrf_token', CSRF_TOKEN);

    fetch('', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.code == 1) {
                showToast(res.msg, 'success');
                input.value = '';
                const empty = document.getElementById('wordEmpty');
                if (empty) empty.remove();
                const list = document.getElementById('wordList');
                const div = document.createElement('div');
                div.className = 'ban-item fade-in';
                div.dataset.id = res.data.id;
                div.id = 'word-' + res.data.id;
                div.innerHTML = '<div class="ban-item-content" id="word-main-' + res.data.id + '" data-time="' + res.data.time + '">' +
                    '<span class="ban-item-word" id="word-text-' + res.data.id + '">' + word.replace(/</g, '&lt;') + '</span>' +
                    '<span class="ban-item-time">' + res.data.time + '</span></div>' +
                    '<div class="ban-item-footer" id="word-actions-' + res.data.id + '">' +
                    '<button class="btn-edit" onclick="editWord(' + res.data.id + ')" title="编辑"><i class="fas fa-pen"></i></button>' +
                    '<button class="btn-del" onclick="confirmDelete(\'word\', ' + res.data.id + ')" title="删除"><i class="fas fa-trash"></i></button></div>';
                list.insertBefore(div, list.firstChild);
                const cnt = document.getElementById('wordCount');
                cnt.textContent = parseInt(cnt.textContent) + 1;
            } else {
                showToast(res.msg || '添加失败', 'error');
            }
        })
        .catch(() => showToast('网络错误', 'error'));
}

function deleteWord(id) {
    const data = new FormData();
    data.append('ajax_delete_word', '1');
    data.append('id', id);
    data.append('csrf_token', CSRF_TOKEN);

    fetch('', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.code == 1) {
                showToast(res.msg, 'success');
                const item = document.getElementById('word-' + id);
                if (item) {
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(20px)';
                    setTimeout(() => {
                        item.remove();
                        const list = document.getElementById('wordList');
                        if (!list.querySelector('.ban-item')) {
                            list.innerHTML = '<div class="ban-empty" id="wordEmpty"><i class="fas fa-inbox" style="font-size:20px;margin-bottom:8px;color:#d1d5db;display:block;"></i>暂无违禁词</div>';
                        }
                    }, 300);
                }
                const cnt = document.getElementById('wordCount');
                cnt.textContent = Math.max(0, parseInt(cnt.textContent) - 1);
            } else {
                showToast(res.msg || '删除失败', 'error');
            }
        })
        .catch(() => showToast('网络错误', 'error'));
}

function addIp() {
    const ipInput = document.getElementById('ipInput');
    const reasonInput = document.getElementById('ipReasonInput');
    const ip = ipInput.value.trim();
    const reason = reasonInput.value.trim();
    if (!ip) return showToast('请输入IP地址', 'error');

    const data = new FormData();
    data.append('ajax_add_ip', '1');
    data.append('ip', ip);
    data.append('reason', reason);
    data.append('csrf_token', CSRF_TOKEN);

    fetch('', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.code == 1) {
                showToast(res.msg, 'success');
                ipInput.value = '';
                reasonInput.value = '';
                const empty = document.getElementById('ipEmpty');
                if (empty) empty.remove();
                const list = document.getElementById('ipList');
                const div = document.createElement('div');
                div.className = 'ban-item fade-in';
                div.dataset.id = res.data.id;
                div.id = 'ip-' + res.data.id;
                let reasonHtml = reason ? '<span class="ban-item-reason" id="ip-reason-' + res.data.id + '">' + reason.replace(/</g, '&lt;') + '</span>' : '';
                div.innerHTML = '<div class="ban-item-content" id="ip-main-' + res.data.id + '" data-time="' + res.data.time + '">' +
                    '<div class="ban-item-ip" id="ip-text-' + res.data.id + '">' + ip.replace(/</g, '&lt;') + '</div>' + reasonHtml +
                    '<span class="ban-item-time">' + res.data.time + '</span></div>' +
                    '<div class="ban-item-footer" id="ip-actions-' + res.data.id + '">' +
                    '<button class="btn-edit" onclick="editIp(' + res.data.id + ')" title="编辑"><i class="fas fa-pen"></i></button>' +
                    '<button class="btn-del" onclick="confirmDelete(\'ip\', ' + res.data.id + ')" title="删除"><i class="fas fa-trash"></i></button></div>';
                list.insertBefore(div, list.firstChild);
                const cnt = document.getElementById('ipCount');
                cnt.textContent = parseInt(cnt.textContent) + 1;
            } else {
                showToast(res.msg || '添加失败', 'error');
            }
        })
        .catch(() => showToast('网络错误', 'error'));
}

function deleteIp(id) {
    const data = new FormData();
    data.append('ajax_delete_ip', '1');
    data.append('id', id);
    data.append('csrf_token', CSRF_TOKEN);

    fetch('', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.code == 1) {
                showToast(res.msg, 'success');
                const item = document.getElementById('ip-' + id);
                if (item) {
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(20px)';
                    setTimeout(() => {
                        item.remove();
                        const list = document.getElementById('ipList');
                        if (!list.querySelector('.ban-item')) {
                            list.innerHTML = '<div class="ban-empty" id="ipEmpty"><i class="fas fa-inbox" style="font-size:20px;margin-bottom:8px;color:#d1d5db;display:block;"></i>暂无黑名单IP</div>';
                        }
                    }, 300);
                }
                const cnt = document.getElementById('ipCount');
                cnt.textContent = Math.max(0, parseInt(cnt.textContent) - 1);
            } else {
                showToast(res.msg || '删除失败', 'error');
            }
        })
        .catch(() => showToast('网络错误', 'error'));
}

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($toast_msg): ?>
    showToast('<?php echo htmlspecialchars($toast_msg, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo (strpos($toast_msg, '失败') !== false || strpos($toast_msg, '无效') !== false || strpos($toast_msg, '未选择') !== false) ? 'error' : 'success'; ?>');
    <?php endif; ?>
});
</script>
</body>
</html>
