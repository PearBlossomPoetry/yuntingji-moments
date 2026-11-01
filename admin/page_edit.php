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

$page_title = '编辑页面';
$page_id = 'pages';

$page_data = [
    'id'       => 0,
    'title'    => '',
    'slug'     => '',
    'content'  => '',
    'page_content' => '',
    'template' => 'custom',
    'layout'   => 'grid',
    'sort'     => 0,
    'status'   => 1
];

$id      = intval($_GET['id'] ?? 0);
$is_edit = $id > 0;

if ($is_edit) {
    $r = mysqli_query($conn, "SELECT * FROM pages WHERE id = $id");
    if ($r && $row = mysqli_fetch_assoc($r)) {
        $page_data = $row;
    } else {
        header('Location: pages.php');
        exit;
    }
}

$toast_msg = $_SESSION['admin_toast'] ?? '';
if ($toast_msg) {
    unset($_SESSION['admin_toast']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim($_POST['title'] ?? '');
    $slug    = trim($_POST['slug'] ?? '');
    $content = $_POST['content'] ?? '';
    $page_content = $_POST['page_content'] ?? '';
    $template = in_array($_POST['template'] ?? '', ['custom', 'friendlink'])
        ? ($_POST['template'] ?? 'custom')
        : 'custom';

    $layout  = 'grid';
    $sort    = intval($_POST['sort'] ?? 0);
    $status  = intval($_POST['status'] ?? 1);

    if (empty($title) || empty($slug)) {
        $_SESSION['admin_toast'] = '标题和别名不能为空';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } elseif (!preg_match('/^[a-z0-9_-]+$/i', $slug)) {
        $_SESSION['admin_toast'] = '别名只能包含字母、数字、下划线和连字符';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } else {
        $esc_title        = mysqli_real_escape_string($conn, $title);
        $esc_slug         = mysqli_real_escape_string($conn, $slug);
        $esc_content      = mysqli_real_escape_string($conn, $content);
        $esc_page_content = mysqli_real_escape_string($conn, $page_content);
        $esc_template     = mysqli_real_escape_string($conn, $template);
        $esc_layout       = mysqli_real_escape_string($conn, $layout);

        $check_sql = "SELECT id FROM pages WHERE slug = '$esc_slug'" . ($is_edit ? " AND id != $id" : "");
        $check     = mysqli_query($conn, $check_sql);

        if ($check && mysqli_num_rows($check) > 0) {
            $_SESSION['admin_toast'] = '页面别名已存在，请使用其他别名';
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $check_article_sql = "SELECT id FROM articles WHERE slug = '$esc_slug'";
            $check_article     = mysqli_query($conn, $check_article_sql);

            if ($check_article && mysqli_num_rows($check_article) > 0) {
                $_SESSION['admin_toast'] = '该别名已被文章使用，请更换其他别名';
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            } else {
                if ($is_edit) {
                    mysqli_query($conn, "UPDATE pages SET
                        title        = '$esc_title',
                        slug         = '$esc_slug',
                        content      = '$esc_content',
                        page_content = '$esc_page_content',
                        template     = '$esc_template',
                        layout       = '$esc_layout',
                        sort         = $sort,
                        status       = $status
                        WHERE id = $id
                    ");
                } else {
                    mysqli_query($conn, "INSERT INTO pages (
                        title, slug, content, page_content, template, layout, sort, status
                    ) VALUES (
                        '$esc_title', '$esc_slug', '$esc_content', '$esc_page_content',
                        '$esc_template', '$esc_layout', $sort, $status
                    )");
                    $id = mysqli_insert_id($conn);
                }
                $_SESSION['admin_toast'] = '保存成功';

                $current_file = basename($_SERVER['PHP_SELF']);
                header('Location: ' . $current_file . '?id=' . $id);
                exit;
            }
        }
    }
}

$settings = get_all_settings();
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

        select.form-input {
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 32px;
            cursor: pointer;
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
            .select-grid { grid-template-columns: repeat(3, 1fr); gap: 10px; }
            .select-grid .form-label { font-size: 12px; margin-bottom: 6px; }
        }

        .template-section { display: none; }
        .template-section.active { display: block; }

        .form-textarea {
            width: 100%;
            min-height: 160px;
            padding: 12px;
            border: 2px solid #f0f0f0;
            border-radius: 10px;
            font-size: 14px;
            color: #374151;
            outline: none;
            transition: all 0.2s;
            background: #fafafa;
            font-weight: 500;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
            resize: vertical;
            line-height: 1.6;
        }
        .form-textarea:focus {
            border-color: #5b73e8;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(91,115,232,0.08);
        }

        .md-editor-bar { position: relative; margin-bottom: 10px; }
        .md-editor-toggle {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: #4b5563;
            cursor: pointer;
            transition: all 0.2s;
        }
        .md-editor-toggle:hover { background: #e5e7eb; }
        .md-editor-arrow { transition: transform 0.2s; }
        .md-editor-toggle.open .md-editor-arrow { transform: rotate(180deg); }
        .md-editor-panel {
            display: none;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
            padding: 10px;
            background: #f8f9fa;
            border: 1px solid #f0f0f0;
            border-radius: 12px;
            align-items: center;
        }
        .md-editor-btn {
            padding: 5px 10px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #4b5563;
            cursor: pointer;
            transition: all 0.15s;
            vertical-align: middle;
        }
        .md-editor-btn:hover {
            border-color: #5b73e8;
            color: #5b73e8;
            background: rgba(91,115,232,0.06);
        }
        .md-color-panel {
            display: none;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            vertical-align: middle;
        }
        .md-color-panel.show {
            display: inline-flex;
        }
        .md-color-dot {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s;
        }
        .md-color-dot:hover { border-color: #5b73e8; transform: scale(1.1); }
        .md-color-picker {
            width: 20px;
            height: 20px;
            overflow: hidden;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid #e5e7eb;
        }
        .md-color-picker input[type="color"] {
            width: 40px;
            height: 40px;
            padding: 0;
            border: none;
            margin: -10px;
            cursor: pointer;
        }
        .md-hint-panel-row {
            display: none;
            gap: 8px;
            padding: 8px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            position: absolute;
            z-index: 10;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .md-hint-item {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            background: #f3f4f6;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
        }
        .md-hint-item:hover { background: #e5e7eb; }
        .hint-icon-info, .hint-icon-warn, .hint-icon-danger {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
            color: #fff;
        }
        .hint-icon-info { background: #3ec9d6; }
        .hint-icon-warn { background: #e58a00; }
        .hint-icon-danger { background: #dc2626; }
        .md-hr-panel {
            display: none;
            gap: 8px;
            padding: 8px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            flex-basis: 100%;
            width: 100%;
            box-sizing: border-box;
            margin-top: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .md-hr-dot {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            cursor: pointer;
            border: 1px solid #e5e7eb;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            outline: none;
            text-decoration: none;
            line-height: 1;
            white-space: nowrap;
            background: #f3f4f6;
            color: #4b5563;
        }
        .btn:hover { background: #e5e7eb; }
        .btn-primary {
            background: linear-gradient(135deg, #5b73e8 0%, #4a62d7 100%);
            color: #fff;
            box-shadow: 0 3px 10px rgba(91,115,232,0.18);
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 14px rgba(91,115,232,0.25);
        }
        .btn-success {
            background: linear-gradient(135deg, #2ca87f 0%, #23946b 100%);
            color: #fff;
            box-shadow: 0 3px 10px rgba(44,168,127,0.18);
        }
        .btn-success:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 14px rgba(44,168,127,0.25);
        }
        .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 8px; }

        .editor-toolbar { margin-bottom: 6px; }

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
            <div class="ap-card-title"><?php echo $is_edit ? '编辑页面' : '新建页面'; ?></div>
            <div class="flex gap-2">
                <a href="pages.php" class="ap-btn ap-btn-secondary"><i class="fas fa-arrow-left"></i> 返回列表</a>
                <button type="button" onclick="document.getElementById('pageForm').submit();" class="ap-btn ap-btn-primary" id="pageSaveBtn"><i class="fas fa-save"></i> 保存</button>
            </div>
        </div>
        <div class="ap-card-body">

            <form method="post" action="" id="pageForm">

                <div class="section-title-inline">基础信息</div>

                <div class="form-group-block">
                    <label class="form-label">页面标题</label>
                    <input type="text" name="title" class="form-input" value="<?php echo htmlspecialchars($page_data['title']); ?>" placeholder="例：关于" required>
                </div>

                <div class="form-group-block">
                    <label class="form-label">页面别名</label>
                    <input type="text" name="slug" class="form-input" value="<?php echo htmlspecialchars($page_data['slug']); ?>" placeholder="例：xxx" required pattern="[a-zA-Z0-9_-]+">
                    <div class="form-tips">用于URL访问 列：https://imoe.top/xxx.html</div>
                </div>

                <div class="select-grid">
                    <div class="form-group">
                        <label class="form-label">页面排序</label>
                        <input type="number" name="sort" class="form-input" value="<?php echo intval($page_data['sort']); ?>" placeholder="0">
                        <div class="form-tips">数字越小越靠前</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">页面状态</label>
                        <select name="status" class="form-input">
                            <option value="1"<?php echo $page_data['status'] == 1 ? ' selected' : ''; ?>>显示</option>
                            <option value="0"<?php echo $page_data['status'] == 0 ? ' selected' : ''; ?>>隐藏</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">页面模板</label>
                        <select name="template" id="templateSelect" class="form-input" onchange="toggleTemplate()">
                            <option value="custom"<?php echo ($page_data['template'] ?? 'custom') === 'custom' ? ' selected' : ''; ?>>自定义</option>
                            <option value="friendlink"<?php echo ($page_data['template'] ?? '') === 'friendlink' ? ' selected' : ''; ?>>友情链接</option>
                        </select>
                        <div class="form-tips">选择页面模板类型</div>
                    </div>
                </div>

                <div class="section-divider"></div>

                <div class="template-section" id="customSection">
                    <div class="section-title-inline">自定义页面内容</div>
                    <div class="form-group-block">
                        <label class="form-label">页面内容（支持HTML和Markdown语法）</label>

                        <div class="md-editor-bar">
                            <button type="button" class="md-editor-toggle" onclick="mdEditorToggle(this)">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                <span>插入 MD 语法</span>
                                <svg class="md-editor-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <div class="md-editor-panel" style="display:none;">
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','bold')" title="粗体"><b>B</b></button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','italic')" title="斜体"><i>I</i></button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','strikethrough')" title="删除线"><s>S</s></button>
                                <button type="button" class="md-editor-btn" onclick="mdColorToggle(this)" title="文字颜色" style="position:relative;">A<span style="display:inline-block;width:8px;height:3px;background:#f5222d;vertical-align:bottom;margin-left:1px;border-radius:1px;"></span></button>
                                <div class="md-color-panel" style="display:none;">
                                    <span class="md-color-dot" style="background:#f5222d;" onclick="mdInsertColor('customContent','#f5222d')" title="红色"></span>
                                    <span class="md-color-dot" style="background:#eb2f96;" onclick="mdInsertColor('customContent','#eb2f96')" title="粉色"></span>
                                    <span class="md-color-dot" style="background:#faad14;" onclick="mdInsertColor('customContent','#faad14')" title="黄色"></span>
                                    <span class="md-color-dot" style="background:#52c41a;" onclick="mdInsertColor('customContent','#52c41a')" title="绿色"></span>
                                    <span class="md-color-dot" style="background:#8B4513;" onclick="mdInsertColor('customContent','#8B4513')" title="棕色"></span>
                                    <span class="md-color-dot" style="background:#722ed1;" onclick="mdInsertColor('customContent','#722ed1')" title="紫色"></span>
                                    <span class="md-color-picker" title="自选颜色"><input type="color" onchange="mdInsertColor('customContent',this.value)"></span>
                                </div>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','h1')">H1</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','quote')">引用</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','tabs')">标签卡</button>
                                <button type="button" class="md-editor-btn" onclick="mdHintToggle(this)" title="提示框">提示框</button>
                                <div class="md-hint-panel-row">
                                    <span class="md-hint-item" onclick="mdInsertHint('customContent','info')"><span class="hint-icon-info">i</span>信息</span>
                                    <span class="md-hint-item" onclick="mdInsertHint('customContent','warning')"><span class="hint-icon-warn">!</span>警告</span>
                                    <span class="md-hint-item" onclick="mdInsertHint('customContent','danger')"><span class="hint-icon-danger">!</span>危险</span>
                                </div>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','btn')">按钮链接</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','code')">代码块</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','inlinecode')">行内代码</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','link')">链接</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','image')">图片</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','ul')">无序列表</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','ol')">有序列表</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','task')">任务列表</button>
                                <button type="button" class="md-editor-btn" onclick="mdHrToggle(this)" title="分割线">分割线</button>
                                <div class="md-hr-panel" style="display:none;">
                                    <span class="md-hr-dot" style="background:repeating-linear-gradient(45deg,transparent,transparent 3px,#ccc 3px,#ccc 6px);" onclick="mdInsertHr('customContent','stripe')" title="斜纹 @@@@"></span>
                                    <span class="md-hr-dot" style="background:radial-gradient(circle,#bbb 1.5px,transparent 1.5px);background-size:6px 6px;" onclick="mdInsertHr('customContent','dots')" title="圆点 @@@@@"></span>
                                    <span class="md-hr-dot" style="border-top:2px dashed #bbb;background:transparent;height:0;border-radius:0;width:24px;" onclick="mdInsertHr('customContent','line')" title="虚线 @@@"></span>
                                </div>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','table')">表格</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','sup')">上标</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','sub')">下标</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','center')">居中</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','details')">折叠详情</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','kbd')">键盘按键</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','file')">文件</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','github')">Github</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','bilibili')" title="嵌入B站视频">B站视频</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','linkcard')" title="外链卡片">外链</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','tag')">彩签</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','graybox')">备注</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','mark')">高亮</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','figure')">图文</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('customContent','timeline')">时间线</button>
                            </div>
                        </div>

                        <textarea name="content" id="customContent" class="form-textarea" placeholder="输入页面内容 支持HTML和Markdown语法..."><?php
$about_default = '<h2>关于我</h2>
<p>这里是一个示例的 <strong>独立页面</strong>内容 你可以在后台管理中自由编辑这段内容 支持完整的HTML标签</p>
<blockquote>
    独立页面功能让你可以创建任意数量的自定义页面 如关于我、友情链接、留言板、作品集等 页面会自动显示在右上角的菜单中。
</blockquote>';
echo htmlspecialchars($page_data['content'] ?? $about_default);
?></textarea>
                        <div class="form-tips">支持HTML和Markdown语法 标签包括 &lt;p&gt;、&lt;img&gt;、&lt;a&gt;、&lt;div&gt; 等</div>
                    </div>
                </div>

                <div class="template-section" id="friendlinkSection">

                    <div class="section-title-inline">友链页面内容</div>
                    <div class="form-group-block">
                        <label class="form-label">页面内容（支持HTML和Markdown语法）</label>

                        <div class="md-editor-bar">
                            <button type="button" class="md-editor-toggle" onclick="mdEditorToggle(this)">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                <span>插入 MD 语法</span>
                                <svg class="md-editor-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <div class="md-editor-panel" style="display:none;">
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','bold')" title="粗体"><b>B</b></button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','italic')" title="斜体"><i>I</i></button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','strikethrough')" title="删除线"><s>S</s></button>
                                <button type="button" class="md-editor-btn" onclick="mdColorToggle(this)" title="文字颜色" style="position:relative;">A<span style="display:inline-block;width:8px;height:3px;background:#f5222d;vertical-align:bottom;margin-left:1px;border-radius:1px;"></span></button>
                                <div class="md-color-panel" style="display:none;">
                                    <span class="md-color-dot" style="background:#f5222d;" onclick="mdInsertColor('flinkPageContent','#f5222d')" title="红色"></span>
                                    <span class="md-color-dot" style="background:#eb2f96;" onclick="mdInsertColor('flinkPageContent','#eb2f96')" title="粉色"></span>
                                    <span class="md-color-dot" style="background:#faad14;" onclick="mdInsertColor('flinkPageContent','#faad14')" title="黄色"></span>
                                    <span class="md-color-dot" style="background:#52c41a;" onclick="mdInsertColor('flinkPageContent','#52c41a')" title="绿色"></span>
                                    <span class="md-color-dot" style="background:#8B4513;" onclick="mdInsertColor('flinkPageContent','#8B4513')" title="棕色"></span>
                                    <span class="md-color-dot" style="background:#722ed1;" onclick="mdInsertColor('flinkPageContent','#722ed1')" title="紫色"></span>
                                    <span class="md-color-picker" title="自选颜色"><input type="color" onchange="mdInsertColor('flinkPageContent',this.value)"></span>
                                </div>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','h1')">H1</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','h4')">H4</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','h5')">H5</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','h6')">H6</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','quote')">引用</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','tabs')">标签卡</button>
                                <button type="button" class="md-editor-btn" onclick="mdHintToggle(this)" title="提示框">提示框</button>
                                <div class="md-hint-panel-row">
                                    <span class="md-hint-item" onclick="mdInsertHint('flinkPageContent','info')"><span class="hint-icon-info">i</span>信息</span>
                                    <span class="md-hint-item" onclick="mdInsertHint('flinkPageContent','warning')"><span class="hint-icon-warn">!</span>警告</span>
                                    <span class="md-hint-item" onclick="mdInsertHint('flinkPageContent','danger')"><span class="hint-icon-danger">!</span>危险</span>
                                </div>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','btn')">按钮链接</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','code')">代码块</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','inlinecode')">行内代码</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','link')">链接</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','image')">图片</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','ul')">无序列表</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','ol')">有序列表</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','task')">任务列表</button>
                                <button type="button" class="md-editor-btn" onclick="mdHrToggle(this)" title="分割线">分割线</button>
                                <div class="md-hr-panel" style="display:none;">
                                    <span class="md-hr-dot" style="background:repeating-linear-gradient(45deg,transparent,transparent 3px,#ccc 3px,#ccc 6px);" onclick="mdInsertHr('flinkPageContent','stripe')" title="斜纹 @@@@"></span>
                                    <span class="md-hr-dot" style="background:radial-gradient(circle,#bbb 1.5px,transparent 1.5px);background-size:6px 6px;" onclick="mdInsertHr('flinkPageContent','dots')" title="圆点 @@@@@"></span>
                                    <span class="md-hr-dot" style="border-top:2px dashed #bbb;background:transparent;height:0;border-radius:0;width:24px;" onclick="mdInsertHr('flinkPageContent','line')" title="虚线 @@@"></span>
                                </div>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','table')">表格</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','sup')">上标</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','sub')">下标</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','center')">居中</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','details')">折叠详情</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','kbd')">键盘按键</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','file')">文件</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','github')">Github</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','bilibili')" title="嵌入B站视频">B站视频</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','linkcard')" title="外链卡片">外链</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','tag')">彩签</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','graybox')">备注</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','mark')">高亮</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','figure')">图文</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('flinkPageContent','timeline')">时间线</button>
                            </div>
                        </div>

                        <div class="editor-toolbar">
                            <button type="button" class="btn btn-success" onclick="insertFriendlink()">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                    <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                    <line x1="12" y1="8" x2="12" y2="16"></line>
                                    <line x1="8" y1="12" x2="16" y2="12"></line>
                                </svg>
                                插入友链
                            </button>
                        </div>

                        <textarea name="page_content" id="flinkPageContent" class="form-textarea" placeholder="输入页面内容 支持HTML和Markdown语法..."><?php
$flink_default = '<h3>申请友链</h3>
<p>交换友情链接 按照以下格式请发送至邮箱1239399954@qq.com</p>
<pre>站点名称：云汀记
站点地址：https://ihk.hk
站点描述：这是一段网站描述</pre>
<h3>朋友们</h3>
=云汀记=https://imoe.top';
echo htmlspecialchars($page_data['page_content'] ?? $flink_default);
?></textarea>
                        <div class="form-tips">支持HTML和Markdown语法 点击上方"插入友链"按格式 <code>=名称=链接</code> 或 <code>=名称=链接=描述</code> 添加友链卡片 一行一个友链</div>
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

function toggleTemplate() {
    const template = document.getElementById('templateSelect').value;
    document.getElementById('customSection').style.display = template === 'custom' ? 'block' : 'none';
    document.getElementById('friendlinkSection').style.display = template === 'friendlink' ? 'block' : 'none';
}

function mdEditorToggle(btn) {
    const panel = btn.nextElementSibling;
    if (panel) {
        const isHidden = panel.style.display === 'none' || panel.style.display === '';
        panel.style.display = isHidden ? 'flex' : 'none';
        btn.classList.toggle('open', isHidden);
    }
}

function insertText(id, before, after, placeholder) {
    const textarea = document.getElementById(id);
    if (!textarea) return;
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    const selected = text.substring(start, end) || placeholder;
    const newText = before + selected + after;
    textarea.value = text.substring(0, start) + newText + text.substring(end);
    textarea.selectionStart = start + before.length;
    textarea.selectionEnd = start + before.length + selected.length;
    textarea.focus();
}

function mdInsert(id, type) {
    const map = {
        'bold': ['**', '**', '粗体'],
        'italic': ['*', '*', '斜体'],
        'strikethrough': ['~~', '~~', '删除线'],
        'h1': ['\n# ', '\n', '标题'],
        'h4': ['\n#### ', '\n', '标题'],
        'h5': ['\n##### ', '\n', '标题'],
        'h6': ['\n###### ', '\n', '标题'],
        'quote': ['\n> ', '\n', '引用'],
        'tabs': ['[tabs]\n[tab name="标签页 1"]内容 1[/tab]\n[tab name="标签页 2"]内容 2[/tab]\n[/tabs]\n', '', ''],
        'btn': ['[btn url="https://example.com"]按钮文字[/btn]', '', ''],
        'code': ['\n```\n', '\n```\n', '代码'],
        'inlinecode': ['`', '`', '行内代码'],
        'link': ['[链接文字](https://)', '', ''],
        'image': ['![图片描述](https://)', '', ''],
        'ul': ['[ul-square]\n- 列表项1\n- 列表项2\n- 列表项3\n[/ul-square]\n', '', ''],
        'ol': ['[ol-gray-bg]\n1. 列表项1\n2. 列表项2\n3. 列表项3\n[/ol-gray-bg]\n', '', ''],
        'task': ['\n- [ ] ', '\n', '任务'],
        'tag': ['[tag color="#f5222d"]', '[/tag]', '彩色标签'],
        'graybox': ['[graybox]', '[/graybox]', '备注内容'],
        'mark': ['[mark]', '[/mark]', '高亮文字'],
        'figure': ['[figure src="图片URL" caption="图注说明"]', '', ''],
        'timeline': ['[timeline]\n- 时间show\n- 时间时间show\n[/timeline]\n', '', ''],
        'table': ['\n| 表头1 | 表头2 |\n| --- | --- |\n| 内容1 | 内容2 |\n', '', ''],
        'sup': ['<sup>', '</sup>', '上标'],
        'sub': ['<sub>', '</sub>', '下标'],
        'center': ['<center>\n', '\n</center>', '居中内容'],
        'details': ['\n<details>\n<summary>点击展开</summary>\n\n', '\n\n</details>\n', '详情内容'],
        'kbd': ['<kbd>', '</kbd>', '按键'],
        'file': ['[file url="http://example.com/download.zip"]示例文件[/file]', '', ''],
        'github': ['[github repo="typecho/typecho" /]', '', ''],
        'bilibili': ['[bilibili]https://m.bilibili.com/video/BV1SW411w7to[/bilibili]', '', ''],
        'linkcard': ['[link][<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 16 16"><path fill="currentColor" d="M9.5 6.5L10 0H9L2 9.5h4.5L6 16h1l7-9.5z"></path></svg>][标题][描述][链接][/link]\n\n[link][图片链接][标题][描述][链接][/link]\n', '', '']
    };
    const [before, after, placeholder] = map[type] || ['', '', ''];
    insertText(id, before, after, placeholder);
}

function mdColorToggle(btn) {
    const panel = btn.nextElementSibling;
    if (panel && panel.classList.contains('md-color-panel')) {
        const isHidden = panel.style.display === 'none' || panel.style.display === '';
        document.querySelectorAll('.md-color-panel').forEach(function(p) {
            if (p !== panel) p.style.display = 'none';
        });
        panel.style.display = isHidden ? 'inline-flex' : 'none';
    }
}

function mdInsertColor(id, color) {
    insertText(id, '<span style="color:' + color + '">', '</span>', '彩色文字');
    document.querySelectorAll('.md-color-panel').forEach(p => p.style.display = 'none');
}

function mdHintToggle(btn) {
    const panel = btn.nextElementSibling;
    if (panel && panel.classList.contains('md-hint-panel-row')) {
        const isHidden = panel.style.display === 'none' || panel.style.display === '';
        document.querySelectorAll('.md-hint-panel-row').forEach(function(p) {
            if (p !== panel) p.style.display = 'none';
        });
        panel.style.display = isHidden ? 'inline-flex' : 'none';
    }
}

function mdInsertHint(id, type) {
    const map = {
        'info': ['[hint title="提示标题"]\n提示内容\n[/hint]\n', '', ''],
        'warning': ['[hint type="warning" title="警告标题"]\n警告内容\n[/hint]\n', '', ''],
        'danger': ['[hint type="danger" title="危险标题"]\n危险内容\n[/hint]\n', '', '']
    };
    const [before, after, placeholder] = map[type];
    insertText(id, before, after, placeholder);
    document.querySelectorAll('.md-hint-panel-row').forEach(p => p.style.display = 'none');
}

function mdHrToggle(btn) {
    const panel = btn.nextElementSibling;
    if (panel && panel.classList.contains('md-hr-panel')) {
        const isHidden = panel.style.display === 'none' || panel.style.display === '';
        document.querySelectorAll('.md-hr-panel').forEach(function(p) {
            if (p !== panel) p.style.display = 'none';
        });
        panel.style.display = isHidden ? 'inline-flex' : 'none';
    }
}

function mdInsertHr(id, type) {
    const map = {
        'stripe': '\n@@@@\n',
        'dots': '\n@@@@@\n',
        'line': '\n@@@\n'
    };
    const textarea = document.getElementById(id);
    if (!textarea) return;
    const start = textarea.selectionStart;
    textarea.value = textarea.value.substring(0, start) + map[type] + textarea.value.substring(start);
    textarea.selectionStart = textarea.selectionEnd = start + map[type].length;
    textarea.focus();
    document.querySelectorAll('.md-hr-panel').forEach(p => p.style.display = 'none');
}

function insertFriendlink() {
    insertText('flinkPageContent', '\n=云汀记=https://imoe.top\n', '', '');
}

document.addEventListener('DOMContentLoaded', function() {
    toggleTemplate();

    <?php if ($toast_msg): ?>
    showToast('<?php echo htmlspecialchars($toast_msg, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo (strpos($toast_msg, '失败') !== false || strpos($toast_msg, '错误') !== false || strpos($toast_msg, '不能为空') !== false || strpos($toast_msg, '已存在') !== false || strpos($toast_msg, '已被') !== false || strpos($toast_msg, '只能包含') !== false) ? 'error' : 'success'; ?>');
    <?php endif; ?>
});
</script>
</body>
</html>
