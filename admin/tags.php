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
$page_title = '标签管理';
$page_id = 'tags';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_tag'])) {
    $csrf_ok = false;
    if (!empty($_POST['csrf_token']) && !empty($_SESSION['csrf_token'])) {
        $csrf_ok = hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }
    if (!$csrf_ok) {
        $_SESSION['admin_toast'] = '安全令牌无效，请刷新页面重试';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    $name = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
    $color = mysqli_real_escape_string($conn, $_POST['color'] ?? '#409eff');
    $bg_color = mysqli_real_escape_string($conn, $_POST['bg_color'] ?? '#e8f4ff');
    $sort = intval($_POST['sort'] ?? 0);
    if (!$name) {
        $_SESSION['admin_toast'] = '标签名称不能为空';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    if (isset($_POST['edit_id']) && $_POST['edit_id']) {
        $eid = intval($_POST['edit_id']);
        mysqli_query($conn, "UPDATE tags SET name='$name',color='$color',bg_color='$bg_color',sort=$sort WHERE id=$eid");
        $_SESSION['admin_toast'] = '更新成功';
    } else {
        mysqli_query($conn, "INSERT INTO tags (name,color,bg_color,sort) VALUES ('$name','$color','$bg_color',$sort)");
        $_SESSION['admin_toast'] = '添加成功';
    }
    header('Location: tags.php');
    exit;
}

if (isset($_GET['ajax_delete'])) {
    $csrf_ok = false;
    if (!empty($_SERVER['HTTP_X_CSRF_TOKEN']) && !empty($_SESSION['csrf_token'])) {
        $csrf_ok = hash_equals($_SESSION['csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN']);
    } elseif (!empty($_GET['csrf_token']) && !empty($_SESSION['csrf_token'])) {
        $csrf_ok = hash_equals($_SESSION['csrf_token'], $_GET['csrf_token']);
    }
    if (!$csrf_ok) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code'=>0,'msg'=>'安全令牌无效，请刷新页面重试']);
        exit;
    }
    $id = intval($_GET['ajax_delete']);
    mysqli_query($conn, "DELETE FROM tags WHERE id = $id");
    mysqli_query($conn, "DELETE FROM post_tags WHERE tag_id = $id");
    json_response(1, '删除成功');
}

$tags = [];
$r = mysqli_query($conn, "SELECT * FROM tags ORDER BY sort ASC");
if ($r) while ($row = mysqli_fetch_assoc($r)) {
    $cr = mysqli_query($conn, "SELECT COUNT(*) as c FROM post_tags WHERE tag_id = " . $row['id']);
    $row['post_count'] = $cr ? mysqli_fetch_assoc($cr)['c'] : 0;
    $tags[] = $row;
}
$edit_tag = null;
if (isset($_GET['edit'])) {
    $er = mysqli_query($conn, "SELECT * FROM tags WHERE id = " . intval($_GET['edit']));
    $edit_tag = mysqli_fetch_assoc($er);
}

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
        .ap-card-title span {
                font-weight: 400;
                color: #9ca3af;
                font-size: 15px;
                margin-left: 6px;
        }
        .ap-card-body { padding: 16px 20px; }

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
                background: linear-gradient(135deg, #2ca87f 0%, #25976d 100%);
                color: #fff;
                box-shadow: 0 3px 10px rgba(44,168,127,0.18);
        }
        .ap-btn-success:hover { transform: translateY(-1px); box-shadow: 0 5px 14px rgba(44,168,127,0.25); }
        .ap-btn-warning {
                background: linear-gradient(135deg, #e58a00 0%, #d17a00 100%);
                color: #fff;
                box-shadow: 0 3px 10px rgba(229,138,0,0.18);
        }
        .ap-btn-warning:hover { transform: translateY(-1px); box-shadow: 0 5px 14px rgba(229,138,0,0.25); }
        .ap-btn-danger {
                background: linear-gradient(135deg, #dc2626 0%, #c22222 100%);
                color: #fff;
                box-shadow: 0 3px 10px rgba(220,38,38,0.18);
        }
        .ap-btn-danger:hover { transform: translateY(-1px); box-shadow: 0 5px 14px rgba(220,38,38,0.25); }
        .ap-btn-sm { padding: 10px 16px; font-size: 14px; border-radius: 12px; }
        .ap-btn-xs { padding: 6px 10px; font-size: 12px; border-radius: 8px; }

        .ap-badge {
                display: inline-flex;
                align-items: center;
                padding: 3px 10px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                white-space: nowrap;
                flex-shrink: 0;
        }
        .ap-badge-green { background: #dcfce7; color: #166534; }
        .ap-badge-yellow { background: #fef3c7; color: #92400e; }
        .ap-badge-blue { background: #dbeafe; color: #1e40af; }
        .ap-badge-red { background: #fee2e2; color: #991b1b; }
        .ap-badge-purple { background: #f3e8ff; color: #6b21a8; }
        .ap-badge-gray { background: #f3f4f6; color: #6b7280; }

        .ap-list {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
        }
        .ap-list-item {
                background: #fff;
                border-radius: 16px;
                padding: 12px 14px;
                border: 1px solid #f0f0f0;
                box-shadow: 0 1px 3px rgba(0,0,0,0.02);
                transition: all 0.2s ease;
                display: flex;
                align-items: center;
                gap: 10px;
                min-width: 0;
        }
        .ap-list-item:hover {
                box-shadow: 0 4px 12px rgba(0,0,0,0.05);
                border-color: #e5e7eb;
        }
        .ap-tag-info {
                flex: 1;
                min-width: 0;
                display: flex;
                align-items: center;
                gap: 10px;
                overflow: hidden;
        }
        .ap-tag-name {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                flex-shrink: 0;
                max-width: 140px;
        }
        .ap-tag-meta {
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: 12px;
                color: #9ca3af;
                white-space: nowrap;
                flex-shrink: 0;
        }
        .ap-tag-meta i { font-size: 11px; }

        .ap-empty {
                text-align: center;
                padding: 60px 20px;
                color: #9ca3af;
                font-size: 15px;
                grid-column: 1 / -1;
        }
        .ap-empty i { font-size: 48px; color: #e5e7eb; margin-bottom: 12px; display: block; }

        .ap-form-input {
                height: 44px;
                padding: 0 16px;
                border: 2px solid #f0f0f0;
                border-radius: 12px;
                font-size: 15px;
                color: #374151;
                outline: none;
                transition: all 0.2s;
                background: #fafafa;
                font-weight: 500;
                width: 100%;
                box-sizing: border-box;
        }
        .ap-form-input:focus { border-color: #5b73e8; background: #fff; box-shadow: 0 0 0 4px rgba(91,115,232,0.08); }

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

        .confirm-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.5);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
        }
        .confirm-overlay.show {
                opacity: 1;
                visibility: visible;
        }
        .confirm-box {
                background: #fff;
                border-radius: 16px;
                padding: 16px 20px;
                max-width: 240px;
                width: 100%;
                text-align: center;
                transform: scale(0.9);
                transition: transform 0.3s ease;
        }
        .confirm-overlay.show .confirm-box {
                transform: scale(1);
        }
        .confirm-box h3 {
                font-size: 15px;
                font-weight: 700;
                color: #1f2937;
                margin-bottom: 8px;
        }
        .confirm-box p {
                font-size: 13px;
                color: #6b7280;
                margin-bottom: 14px;
                line-height: 1.4;
        }
        .confirm-box .confirm-actions {
                display: flex;
                gap: 8px;
        }
        .confirm-box .confirm-actions a,
        .confirm-box .confirm-actions button {
                flex: 1;
                padding: 8px 10px;
                border-radius: 10px;
                font-size: 13px;
                font-weight: 600;
                text-decoration: none;
                border: none;
                cursor: pointer;
                text-align: center;
        }
        .confirm-box .confirm-actions .cancel {
                background: #f3f4f6;
                color: #6b7280;
        }
        .confirm-box .confirm-actions .confirm {
                background: #dc2626;
                color: #fff;
        }

        .fade-in { animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity:0; transform: translateY(8px); } to { opacity:1; transform: translateY(0); } }

        .color-preset-panel {
                margin-top: 12px;
                padding: 8px;
                background: #fafafa;
                border-radius: 10px;
                border: 1px solid #f0f0f0;
        }
        .color-preset-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 6px;
        }
        .color-preset-title {
                font-size: 12px;
                font-weight: 600;
                color: #4b5563;
        }
        .color-preset-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(24px, 1fr));
                gap: 2px;
        }
        .color-preset-btn {
                position: relative;
                width: 100%;
                aspect-ratio: 1;
                border-radius: 4px;
                border: 2px solid transparent;
                cursor: pointer;
                transition: all 0.1s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
                padding: 0;
                background: none;
        }
        .color-preset-btn:hover {
                transform: scale(1.15);
                box-shadow: 0 2px 4px rgba(0,0,0,0.15);
                z-index: 2;
        }
        .color-preset-btn.active {
                border-color: #5b73e8;
                box-shadow: 0 0 0 2px rgba(91,115,232,0.15);
                z-index: 2;
        }
        .color-preset-btn .preset-text {
                font-size: 8px;
                font-weight: 700;
                line-height: 1;
                padding: 0;
                text-align: center;
        }
        .color-preset-random {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 3px 8px;
                border-radius: 6px;
                font-size: 11px;
                font-weight: 600;
                color: #5b73e8;
                background: #eef2ff;
                border: none;
                cursor: pointer;
                transition: all 0.2s;
        }
        .color-preset-random:hover {
                background: #dbe4ff;
        }
        .color-preset-random:active {
                transform: scale(0.95);
        }
        .tag-live-preview {
                margin-top: 6px;
                padding: 6px 10px;
                background: #fff;
                border-radius: 8px;
                border: 1px dashed #e5e7eb;
                display: flex;
                align-items: center;
                gap: 8px;
        }
        .tag-live-preview-label {
                font-size: 11px;
                color: #9ca3af;
                font-weight: 500;
        }
        @media (max-width: 768px) {
                .color-preset-grid {
                grid-template-columns: repeat(auto-fill, minmax(22px, 1fr));
                gap: 2px;
                }
                .color-preset-btn {
                border-radius: 3px;
                }
                .color-preset-panel {
                padding: 6px;
                }
                .tag-live-preview {
                padding: 5px 8px;
                }
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
                .ap-card-body { padding: 16px 20px; }
                .ap-list { grid-template-columns: 1fr; }
                .ap-list-item { padding: 10px 12px; gap: 8px; }
                .ap-tag-name { max-width: 120px; }
                .ap-tag-meta { gap: 6px; font-size: 11px; }
                .ap-btn { padding: 8px 14px; font-size: 13px; border-radius: 12px; }
                .ap-btn-xs { padding: 5px 8px; font-size: 11px; border-radius: 8px; }
                .ap-badge { padding: 2px 8px; font-size: 11px; }
        }
    </style>
</head>
<body class="text-gray-700 antialiased">

<div class="confirm-overlay" id="confirmDialog">
    <div class="confirm-box">
        <h3><i class="fas fa-exclamation-triangle" style="color:#e58a00;margin-right:4px;font-size:14px;"></i>确认操作</h3>
        <p id="confirmMsg">确认删除？</p>
        <div class="confirm-actions">
                <button class="cancel" onclick="closeConfirm()">取消</button>
                <button class="confirm" id="confirmBtn" onclick="doConfirmAction()">确认</button>
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

<div class="fade-in">

    <div class="ap-card mb-5">
        <div class="ap-card-header">
                <div class="ap-card-title"><?php echo $edit_tag ? '编辑标签' : '添加标签'; ?></div>
                <?php if ($edit_tag): ?>
                <a href="tags.php" class="ap-btn ap-btn-sm" style="background:rgba(0,0,0,0.05);color:#666;"><i class="fas fa-times"></i> 取消</a>
                <?php endif; ?>
        </div>
        <div class="ap-card-body">
                <form id="tagForm" method="POST" action="">
                <?php echo csrf_token_field(); ?>
                <input type="hidden" name="edit_id" id="editId" value="<?php echo $edit_tag['id'] ?? ''; ?>">

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:14px;font-weight:500;color:#4b5563;margin-bottom:8px;">标签名称</label>
                    <input type="text" name="name" id="tagName" class="ap-form-input" value="<?php echo htmlspecialchars($edit_tag['name'] ?? ''); ?>" required placeholder="例如：企业微信">
                </div>

                <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:16px;margin-bottom:20px;">
                    <div>
                                <label style="display:block;font-size:14px;font-weight:500;color:#4b5563;margin-bottom:8px;">文字颜色</label>
                                <input type="color" name="color" id="tagColor" class="ap-form-input" value="<?php echo htmlspecialchars($edit_tag['color'] ?? '#409eff'); ?>" style="padding:4px;height:44px;">
                    </div>
                    <div>
                                <label style="display:block;font-size:14px;font-weight:500;color:#4b5563;margin-bottom:8px;">背景颜色</label>
                                <input type="color" name="bg_color" id="tagBg" class="ap-form-input" value="<?php echo htmlspecialchars($edit_tag['bg_color'] ?? '#e8f4ff'); ?>" style="padding:4px;height:44px;">
                    </div>
                    <div>
                                <label style="display:block;font-size:14px;font-weight:500;color:#4b5563;margin-bottom:8px;">排序</label>
                                <input type="number" name="sort" id="tagSort" class="ap-form-input" value="<?php echo $edit_tag['sort'] ?? 0; ?>">
                    </div>
                </div>

                <div class="color-preset-panel">
                    <div class="color-preset-header">
                                <span class="color-preset-title"><i class="fas fa-palette" style="margin-right:4px;color:#5b73e8;"></i>快速配色</span>
                                <button type="button" class="color-preset-random" onclick="randomPreset()">
                            <i class="fas fa-dice"></i> 随机
                                </button>
                    </div>
                    <div class="color-preset-grid" id="presetGrid"></div>
                    <div class="tag-live-preview">
                                <span class="tag-live-preview-label">实时预览：</span>
                                <span id="livePreviewBadge" class="ap-badge" style="background:<?php echo htmlspecialchars($edit_tag['bg_color'] ?? '#e8f4ff'); ?>;color:<?php echo htmlspecialchars($edit_tag['color'] ?? '#409eff'); ?>;">
                            <?php echo htmlspecialchars($edit_tag['name'] ?? '标签预览'); ?>
                                </span>
                    </div>
                </div>

                <div style="display:flex;gap:10px;align-items:center;margin-top:20px;">
                    <button type="submit" name="save_tag" class="ap-btn ap-btn-primary">
                                <i class="fas fa-save"></i> <?php echo $edit_tag ? '更新' : '添加'; ?>
                    </button>
                    <?php if ($edit_tag): ?>
                    <a href="tags.php" class="ap-btn" style="background:rgba(0,0,0,0.05);color:#666;">取消</a>
                    <?php endif; ?>
                </div>
                </form>
        </div>
    </div>

    <div class="ap-card">
        <div class="ap-card-header">
                <div class="ap-card-title">标签列表 <span>（<?php echo count($tags); ?>个）</span></div>
        </div>
        <div class="ap-card-body">
                <div class="ap-list">
                <?php foreach ($tags as $tag): ?>
                <div class="ap-list-item" data-id="<?php echo $tag['id']; ?>">
                    <div class="ap-tag-info">
                                <span class="ap-badge ap-tag-name" style="background:<?php echo $tag['bg_color']; ?>;color:<?php echo $tag['color']; ?>;">
                            <?php echo htmlspecialchars($tag['name']); ?>
                                </span>
                                <span class="ap-tag-meta">
                            <span><i class="fas fa-sort-numeric-down" style="color:#9ca3af;"></i> 排序<?php echo $tag['sort']; ?></span>
                            <span><i class="fas fa-link" style="color:#60a5fa;"></i> <?php echo $tag['post_count']; ?>次使用</span>
                                </span>
                    </div>
                    <div style="display:flex;gap:6px;flex-shrink:0;">
                                <a href="?edit=<?php echo $tag['id']; ?>" class="ap-btn ap-btn-xs ap-btn-primary"><i class="fas fa-edit"></i></a>
                                <button onclick="confirmDelete(<?php echo $tag['id']; ?>)" class="ap-btn ap-btn-xs ap-btn-danger"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($tags)): ?>
                <div class="ap-empty">
                    <i class="fas fa-inbox"></i>
                    <div>暂无标签</div>
                </div>
                <?php endif; ?>
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
let pendingDeleteId = null;

function confirmDelete(id) {
    pendingDeleteId = id;
    document.getElementById('confirmMsg').textContent = '确认删除这个标签？关联的文章标签也会被移除。';
    document.getElementById('confirmDialog').classList.add('show');
}

function closeConfirm() {
    document.getElementById('confirmDialog').classList.remove('show');
    pendingDeleteId = null;
}

function doConfirmAction() {
    if (!pendingDeleteId) return;
    const id = pendingDeleteId;
    closeConfirm();

    fetch('?ajax_delete=' + id + '&csrf_token=' + encodeURIComponent(CSRF_TOKEN))
    .then(r => r.json())
    .then(data => {
        if (data.code == 1 || data.success === true) {
            const item = document.querySelector('.ap-list-item[data-id="' + id + '"]');
            if (item) {
                item.style.transition = 'all 0.3s ease';
                item.style.opacity = '0';
                item.style.transform = 'translateX(-20px)';
                setTimeout(() => item.remove(), 300);
            }
            showToast('删除成功', 'success');
            const titleSpan = document.querySelector('.ap-card-title span');
            if (titleSpan) {
                const match = titleSpan.textContent.match(/\d+/);
                if (match) {
                    const newCount = parseInt(match[0]) - 1;
                    titleSpan.textContent = '（' + newCount + '个）';
                }
            }
        } else {
            showToast(data.msg || '删除失败', 'error');
        }
    })
    .catch(() => showToast('网络错误', 'error'));
}

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

const colorPresets = [
{ name: '纯黑', color: '#000000', bg: '#f8fafc' },
{ name: 'slate', color: '#1e293b', bg: '#f1f5f9' },
{ name: '岩灰', color: '#374151', bg: '#f3f4f6' },
{ name: '铁灰', color: '#4b5563', bg: '#f9fafb' },
{ name: '锌灰', color: '#52525b', bg: '#f4f4f5' },
{ name: '暗红', color: '#450a0a', bg: '#fecaca' },
{ name: '深红', color: '#7f1d1d', bg: '#fee2e2' },
{ name: '殷红', color: '#991b1b', bg: '#fecaca' },
{ name: '绯红', color: '#b91c1c', bg: '#fca5a5' },
{ name: '亮红', color: '#dc2626', bg: '#fee2e2' },
{ name: '玫瑰', color: '#e11d48', bg: '#ffe4e6' },
{ name: '珊瑚', color: '#f43f5e', bg: '#fff1f2' },
{ name: '浆果', color: '#be123c', bg: '#fce7f3' },
{ name: '深粉', color: '#831843', bg: '#fbcfe8' },
{ name: '桃粉', color: '#9d174d', bg: '#fce7f3' },
{ name: '品红', color: '#c026d3', bg: '#fae8ff' },
{ name: '桃红', color: '#ec4899', bg: '#fdf2f8' },
{ name: '赭石', color: '#431407', bg: '#ffedd5' },
{ name: '栗壳', color: '#7c2d12', bg: '#fed7aa' },
{ name: '焦糖', color: '#9a3412', bg: '#ffedd5' },
{ name: '赤陶', color: '#c2410c', bg: '#fdba74' },
{ name: '橘红', color: '#ea580c', bg: '#fff7ed' },
{ name: '南瓜', color: '#f97316', bg: '#ffedd5' },
{ name: '橙黄', color: '#fb923c', bg: '#fff7ed' },
{ name: '土黄', color: '#713f12', bg: '#fef9c3' },
{ name: '杏黄', color: '#854d0e', bg: '#fef08a' },
{ name: '柠檬', color: '#a16207', bg: '#fef9c3' },
{ name: '金黄', color: '#ca8a04', bg: '#fef08a' },
{ name: '亮黄', color: '#eab308', bg: '#fefce8' },
{ name: '墨绿', color: '#052e16', bg: '#86efac' },
{ name: '深绿', color: '#14532d', bg: '#bbf7d0' },
{ name: '森林', color: '#166534', bg: '#dcfce7' },
{ name: '翠绿', color: '#15803d', bg: '#bbf7d0' },
{ name: '鲜绿', color: '#22c55e', bg: '#f0fdf4' },
{ name: '青柠', color: '#3f6212', bg: '#ecfccb' },
{ name: '草绿', color: '#65a30d', bg: '#f7fee7' },
{ name: '苹果绿', color: '#84cc16', bg: '#ecfccb' },
{ name: '松石', color: '#0f766e', bg: '#ccfbf1' },
{ name: '薄荷', color: '#115e59', bg: '#d1fae5' },
{ name: '深渊', color: '#083344', bg: '#cffafe' },
{ name: '深海', color: '#164e63', bg: '#a5f3fc' },
{ name: '钢青', color: '#0e7490', bg: '#cffafe' },
{ name: '天青', color: '#0369a1', bg: '#e0f2fe' },
{ name: '湖蓝', color: '#0284c7', bg: '#bae6fd' },
{ name: '钴蓝', color: '#2563eb', bg: '#dbeafe' },
{ name: '亮蓝', color: '#1d4ed8', bg: '#eff6ff' },
{ name: '午夜蓝', color: '#1e3a8a', bg: '#dbeafe' },
{ name: '靛蓝', color: '#4338ca', bg: '#e0e7ff' },
{ name: '深靛', color: '#3730a3', bg: '#c7d2fe' },
{ name: '深紫', color: '#4c1d95', bg: '#ede9fe' },
{ name: '紫黑', color: '#581c87', bg: '#f3e8ff' },
{ name: '茄紫', color: '#6b21a8', bg: '#e9d5ff' },
{ name: '葡萄', color: '#7e22ce', bg: '#f3e8ff' },
{ name: '亮紫', color: '#9333ea', bg: '#faf5ff' },
{ name: '紫晶', color: '#a855f7', bg: '#f3e8ff' },
{ name: '微信', color: '#07c160', bg: '#e6f7ed' },
{ name: '支付宝', color: '#1677ff', bg: '#e6f4ff' },
{ name: '淘宝', color: '#ff5000', bg: '#fff0e6' },
{ name: '京东', color: '#e4393c', bg: '#fdecec' },
{ name: '抖音', color: '#000000', bg: '#f2f2f2' },
{ name: '小红书', color: '#ff2442', bg: '#ffeef1' },
{ name: 'B站', color: '#00a1d6', bg: '#e6f7fc' },
{ name: '微博', color: '#e6162d', bg: '#fde8eb' },
{ name: 'QQ', color: '#12b7f5', bg: '#e6f7fe' },
{ name: '钉钉', color: '#3370ff', bg: '#ebf0ff' },
{ name: '知乎', color: '#0066ff', bg: '#e6f0ff' },
{ name: 'GitHub', color: '#24292f', bg: '#f6f8fa' },
{ name: 'Dracula', color: '#f8f8f2', bg: '#282a36' },
{ name: 'Monokai', color: '#f8f8f2', bg: '#272822' },
{ name: 'Nord', color: '#d8dee9', bg: '#2e3440' },
{ name: 'Gruvbox', color: '#ebdbb2', bg: '#282828' },
{ name: 'OneDark', color: '#abb2bf', bg: '#282c34' },
{ name: '樱花', color: '#9f1239', bg: '#ffe4e6' },
{ name: '覆盆子', color: '#881337', bg: '#fce4ec' },
{ name: '咖啡', color: '#78350f', bg: '#fef3c7' },
{ name: '拿铁', color: '#451a03', bg: '#fef3c7' },
{ name: '奶茶', color: '#92400e', bg: '#fef3c7' },
{ name: '焦糖玛奇朵', color: '#b45309', bg: '#fef3c7' },
{ name: '抹茶', color: '#14532d', bg: '#dcfce7' },
{ name: '蓝莓', color: '#0d47a1', bg: '#e3f2fd' },
{ name: '薰衣草', color: '#7c3aed', bg: '#ede9fe' },
{ name: '极光', color: '#059669', bg: '#d1fae5' },
{ name: '日落', color: '#c2410c', bg: '#ffedd5' },
{ name: '火龙果', color: '#be185d', bg: '#fce7f3' },
{ name: '蜂蜜', color: '#f59e0b', bg: '#fffbeb' },
{ name: '孔雀', color: '#0d9488', bg: '#ccfbf1' },
{ name: '牛油果', color: '#3f6212', bg: '#f7fee7' },
{ name: '紫罗兰', color: '#5b21b6', bg: '#ede9fe' },
{ name: '极简白', color: '#171717', bg: '#fafafa' },
{ name: '极简灰', color: '#525252', bg: '#fafafa' },
{ name: '极简红', color: '#dc2626', bg: '#fef2f2' },
{ name: '极简蓝', color: '#2563eb', bg: '#eff6ff' },
{ name: '极简绿', color: '#16a34a', bg: '#f0fdf4' },
{ name: '极简黄', color: '#ca8a04', bg: '#fefce8' },
{ name: '极简青', color: '#0891b2', bg: '#ecfeff' },
{ name: '芒果', color: '#d97706', bg: '#fef3c7' },
{ name: '奇异果', color: '#65a30d', bg: '#ecfccb' },
{ name: '草莓', color: '#be123c', bg: '#ffe4e6' },
{ name: '柑橘', color: '#ea580c', bg: '#ffedd5' },
{ name: '巧克力', color: '#7c2d12', bg: '#ffedd5' }
];

function initPresetGrid() {
    const grid = document.getElementById('presetGrid');
    if (!grid) return;
    grid.innerHTML = '';
    colorPresets.forEach((preset, index) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'color-preset-btn';
        btn.style.background = preset.bg;
        btn.title = preset.name;
        btn.dataset.index = index;
        btn.innerHTML = '<span class="preset-text" style="color:' + preset.color + ';">A</span>';
        btn.onclick = function() {
            applyPreset(index);
        };
        grid.appendChild(btn);
    });
    highlightCurrentPreset();
}

function applyPreset(index) {
    const preset = colorPresets[index];
    if (!preset) return;
    document.getElementById('tagColor').value = preset.color;
    document.getElementById('tagBg').value = preset.bg;
    highlightCurrentPreset();
    updateLivePreview();
}

function highlightCurrentPreset() {
    const currentColor = document.getElementById('tagColor').value.toLowerCase();
    const currentBg = document.getElementById('tagBg').value.toLowerCase();
    const buttons = document.querySelectorAll('.color-preset-btn');
    buttons.forEach(btn => {
        const idx = parseInt(btn.dataset.index);
        const preset = colorPresets[idx];
        if (preset.color.toLowerCase() === currentColor && preset.bg.toLowerCase() === currentBg) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
}

function randomPreset() {
    const idx = Math.floor(Math.random() * colorPresets.length);
    applyPreset(idx);
    showToast('已随机切换为「' + colorPresets[idx].name + '」', 'success');
}

function updateLivePreview() {
    const name = document.getElementById('tagName').value || '标签预览';
    const color = document.getElementById('tagColor').value;
    const bg = document.getElementById('tagBg').value;
    const badge = document.getElementById('livePreviewBadge');
    if (badge) {
        badge.textContent = name;
        badge.style.color = color;
        badge.style.background = bg;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    initPresetGrid();

    const tagName = document.getElementById('tagName');
    const tagColor = document.getElementById('tagColor');
    const tagBg = document.getElementById('tagBg');
    if (tagName) tagName.addEventListener('input', updateLivePreview);
    if (tagColor) tagColor.addEventListener('input', function() { highlightCurrentPreset(); updateLivePreview(); });
    if (tagBg) tagBg.addEventListener('input', function() { highlightCurrentPreset(); updateLivePreview(); });

    <?php if ($toast_msg): ?>
    showToast('<?php echo htmlspecialchars($toast_msg, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo (strpos($toast_msg, '失败') !== false || strpos($toast_msg, '无效') !== false || strpos($toast_msg, '未选择') !== false || strpos($toast_msg, '不能为空') !== false) ? 'error' : 'success'; ?>');
    <?php endif; ?>
});

</script>
</body>
</html>
