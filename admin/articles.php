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
$page_title = '文章管理';
$page_id = 'articles';

if (isset($_GET['action']) && isset($_GET['id']) && !isset($_GET['ajax'])) {
    $csrf_ok = false;
    if (!empty($_GET['csrf_token']) && !empty($_SESSION['csrf_token'])) {
        $csrf_ok = hash_equals($_SESSION['csrf_token'], $_GET['csrf_token']);
    }
    if (!$csrf_ok) {
        $_SESSION['admin_toast'] = '安全令牌无效，请刷新页面重试';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'articles.php'));
        exit;
    }
    $id = intval($_GET['id']);
    $action = $_GET['action'];
    if ($action === 'hide') {
        mysqli_query($conn, "UPDATE articles SET status = 0 WHERE id = $id");
        $_SESSION['admin_toast'] = '已隐藏';
    }
    elseif ($action === 'show') {
        mysqli_query($conn, "UPDATE articles SET status = 1 WHERE id = $id");
        $_SESSION['admin_toast'] = '已显示';
    }
    elseif ($action === 'top') {
        mysqli_query($conn, "UPDATE articles SET is_top = 1 WHERE id = $id");
        $_SESSION['admin_toast'] = '已置顶';
    }
    elseif ($action === 'untop') {
        mysqli_query($conn, "UPDATE articles SET is_top = 0 WHERE id = $id");
        $_SESSION['admin_toast'] = '已取消置顶';
    }
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'articles.php'));
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === '1' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
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
    $id = intval($_GET['id']);
    $result = mysqli_query($conn, "DELETE FROM articles WHERE id = $id");
    header('Content-Type: application/json');
    echo json_encode(['success' => $result ? true : false, 'msg' => $result ? '删除成功' : '删除失败：' . mysqli_error($conn)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_article_comment_setting'])) {
    $csrf_ok = false;
    if (!empty($_POST['csrf_token']) && !empty($_SESSION['csrf_token'])) {
        $csrf_ok = hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }
    if (!$csrf_ok) {
        $_SESSION['admin_toast'] = '安全令牌无效，请刷新页面重试';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    $val = intval($_POST['comments_per_page'] ?? 10);
    if ($val < 1) $val = 10;
    if ($val > 100) $val = 100;
    set_setting('article_comments_per_page', strval($val));
    $_SESSION['admin_toast'] = '保存成功';
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && strpos($_POST['action'], 'batch_') === 0) {
    $csrf_ok = false;
    if (!empty($_POST['csrf_token']) && !empty($_SESSION['csrf_token'])) {
        $csrf_ok = hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }
    if (!$csrf_ok) {
        $_SESSION['admin_toast'] = '安全令牌无效，请刷新页面重试';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'articles.php'));
        exit;
    }
    $action = $_POST['action'];
    $ids = trim($_POST['ids'] ?? '');
    if (!$ids) {
        $_SESSION['admin_toast'] = '未选择任何项';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'articles.php'));
        exit;
    }
    $idArr = array_filter(array_map('intval', explode(',', $ids)));
    if (empty($idArr)) {
        $_SESSION['admin_toast'] = '选择无效';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'articles.php'));
        exit;
    }
    $idStr = implode(',', $idArr);
    $table = 'articles';
    if ($action === 'batch_delete') {
        $sql = "DELETE FROM {$table} WHERE id IN ($idStr)";
    } elseif ($action === 'batch_hide') {
        $sql = "UPDATE {$table} SET status = 0 WHERE id IN ($idStr)";
    } elseif ($action === 'batch_show') {
        $sql = "UPDATE {$table} SET status = 1 WHERE id IN ($idStr)";
    } else {
        $_SESSION['admin_toast'] = '未知批量操作';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'articles.php'));
        exit;
    }
    if (mysqli_query($conn, $sql)) {
        $_SESSION['admin_toast'] = '批量操作成功';
    } else {
        $_SESSION['admin_toast'] = '操作失败：' . mysqli_error($conn);
    }
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'articles.php'));
    exit;
}

$article_comments_per_page = intval(get_setting('article_comments_per_page', '10'));
if ($article_comments_per_page < 1) $article_comments_per_page = 10;

$is_mobile = isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/Mobile|Android|iPhone|iPad/i', $_SERVER['HTTP_USER_AGENT']);
$page = intval($_GET['page'] ?? 1);
$per_page = $is_mobile ? 10 : 12;
$offset = ($page - 1) * $per_page;

$total = 0;
$r = mysqli_query($conn, "SELECT COUNT(*) as c FROM articles");
if ($r) $total = mysqli_fetch_assoc($r)['c'];
$total_pages = ceil($total / $per_page);

$articles = [];
$r = mysqli_query($conn, "SELECT * FROM articles ORDER BY is_top DESC, published_at DESC LIMIT $offset, $per_page");
if ($r) while ($row = mysqli_fetch_assoc($r)) {
    $cc = mysqli_query($conn, "SELECT COUNT(*) as c FROM comments WHERE post_id = " . $row['id']);
    $row['comment_count'] = $cc ? mysqli_fetch_assoc($cc)['c'] : 0;
    $articles[] = $row;
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
        }
        .ap-badge-green { background: #dcfce7; color: #166534; }
        .ap-badge-yellow { background: #fef3c7; color: #92400e; }
        .ap-badge-blue { background: #dbeafe; color: #1e40af; }
        .ap-badge-red { background: #fee2e2; color: #991b1b; }
        .ap-badge-purple { background: #f3e8ff; color: #6b21a8; }
        .ap-badge-gray { background: #f3f4f6; color: #6b7280; }

        .ap-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
        }
        @media (min-width: 1024px) {
            .ap-list { grid-template-columns: repeat(2, 1fr); }
        }
        .ap-list-item {
            background: #fff;
            border-radius: 18px;
            padding: 10px 10px 0 10px;
            border: 1px solid #f0f0f0;
            box-shadow: 0 1px 4px rgba(0,0,0,0.02);
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
            overflow: hidden;
            min-width: 0;
            display: flex;
            flex-direction: column;
        }
        .ap-list-item:hover {
            box-shadow: 0 6px 24px rgba(0,0,0,0.05);
            transform: translateY(-2px);
            border-color: #e5e7eb;
        }
        .ap-item-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
            min-width: 0;
            overflow: hidden;
        }
        .ap-item-title {
            font-size: 15px;
            font-weight: 600;
            color: #1f2937;
            line-height: 1.5;
            overflow: hidden;
            flex: 1;
            min-width: 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            min-height: 3em;
        }
        .ap-item-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
            margin-bottom: 4px;
            min-height: 28px;
        }
        .ap-item-time {
            font-size: 12px;
            color: #9ca3af;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 4px;
            min-height: 20px;
        }
        .ap-item-stats {
            display: flex;
            gap: 10px;
            padding: 8px 0;
            border-top: 1px solid #f5f5f5;
            margin-bottom: 0;
            min-height: 32px;
            align-items: center;
        }
        .ap-stat {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #6b7280;
            font-weight: 500;
        }
        .ap-stat i { font-size: 14px; }
        .ap-stat svg { width: 16px; height: 16px; flex-shrink: 0; display: inline-block; }

        .ap-item-actions {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            overflow-y: hidden;
            white-space: nowrap;
            padding: 10px 0;
            margin-top: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            touch-action: pan-x;
            width: 100%;
            border-top: 1px solid #f0f0f0;
        }
        .ap-item-actions .ap-btn { box-shadow: none !important; flex-shrink: 0; }
        .ap-item-actions .ap-btn:hover { box-shadow: none !important; transform: translateY(-1px); }
        .ap-item-actions::-webkit-scrollbar { display: none; }
        .ap-item-actions .ap-select-all { flex-shrink: 0; }

        .ap-empty {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
            font-size: 15px;
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
        }
        .ap-form-input:focus { border-color: #5b73e8; background: #fff; box-shadow: 0 0 0 4px rgba(91,115,232,0.08); }

        .ap-select-all {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 14px;
            color: #6b7280;
            font-weight: 500;
            user-select: none;
            flex-shrink: 0;
        }
        .ap-select-all input[type="checkbox"] { display: none; }
        .ap-check-box {
            width: 20px;
            height: 20px;
            border: 2px solid #d1d5db;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            flex-shrink: 0;
            background: #fff;
        }
        .ap-check-box::after {
            content: '';
            width: 10px;
            height: 10px;
            background: #5b73e8;
            border-radius: 3px;
            transform: scale(0);
            transition: transform 0.2s cubic-bezier(0.4,0,0.2,1);
        }
        .ap-select-all input[type="checkbox"]:checked + .ap-check-box {
            border-color: #5b73e8;
            background: rgba(91,115,232,0.08);
        }
        .ap-select-all input[type="checkbox"]:checked + .ap-check-box::after { transform: scale(1); }

        .ap-pagination {
            display: flex;
            gap: 8px;
            margin-top: 24px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .ap-pagination a, .ap-pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 14px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        .ap-pagination a {
            background: #fff;
            color: #6b7280;
            border: 1px solid #e5e7eb;
        }
        .ap-pagination a:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
            color: #374151;
        }
        .ap-pagination span.current {
            background: #5b73e8;
            color: #fff;
            border: 1px solid #5b73e8;
            box-shadow: 0 2px 8px rgba(91,115,232,0.2);
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
            .ap-list-item { padding: 16px 16px 0 16px; border-radius: 16px; display: block; }
            .ap-item-header { margin-bottom: 10px; }
            .ap-item-title { font-size: 14px; min-height: auto; -webkit-line-clamp: unset; display: block; }
            .ap-item-meta { min-height: auto; }
            .ap-item-time { min-height: auto; }
            .ap-item-stats { gap: 12px; padding: 8px 0; margin-bottom: 0; border-top: 1px solid #f5f5f5; min-height: auto; }
            .ap-item-actions { gap: 6px; padding: 10px 0; margin-top: 0; }
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
            <div class="ap-card-title">文章列表 <span>（<?php echo $total; ?>篇）</span></div>
            <a href="article_edit.php" class="ap-btn ap-btn-primary"><i class="fas fa-plus"></i> 发布文章</a>
        </div>
        <div class="ap-card-body">
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
                <button class="ap-btn ap-btn-sm ap-btn-warning" onclick="batchSubmit('hide')"><i class="fas fa-eye-slash"></i> 批量隐藏</button>
                <button class="ap-btn ap-btn-sm ap-btn-success" onclick="batchSubmit('show')"><i class="fas fa-eye"></i> 批量显示</button>
                <button class="ap-btn ap-btn-sm ap-btn-danger" onclick="batchSubmit('delete')"><i class="fas fa-trash"></i> 批量删除</button>
                <div style="flex:1;"></div>
                <label class="ap-select-all">
                    <input type="checkbox" id="selectAll" onclick="toggleSelectAll()">
                    <span class="ap-check-box"></span>
                    <span class="ap-check-text">全选</span>
                </label>
            </div>

            <form id="batchForm" method="POST" action="" style="display:none;">
                <input type="hidden" name="action" id="batchAction" value="">
                <input type="hidden" name="ids" id="batchIds" value="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
            </form>

            <div class="ap-list">
                <?php foreach ($articles as $article): ?>
                <div class="ap-list-item" data-id="<?php echo $article['id']; ?>">
                    <div class="ap-item-header">
                        <div class="ap-item-title">
                            <?php echo htmlspecialchars(mb_substr($article['title'], 0, 50)) . (mb_strlen($article['title']) > 50 ? '...' : ''); ?>
                        </div>
                    </div>
                    <div class="ap-item-meta">
                        <span class="ap-badge ap-badge-blue"><?php echo htmlspecialchars($article['slug']); ?></span>
                        <?php if ($article['status']==1): ?>
                        <span class="ap-badge ap-badge-green">显示</span>
                        <?php else: ?>
                        <span class="ap-badge ap-badge-yellow">隐藏</span>
                        <?php endif; ?>
                        <?php if ($article['is_top']): ?><span class="ap-badge ap-badge-purple">置顶</span><?php endif; ?>
                        <?php if ($article['comment_enabled']): ?><span class="ap-badge ap-badge-blue">评论开</span><?php else: ?><span class="ap-badge ap-badge-yellow">评论关</span><?php endif; ?>
                    </div>
                    <div class="ap-item-time">
                        <i class="far fa-clock"></i> <?php echo $article['published_at']; ?>
                    </div>
                    <div class="ap-item-stats" style="flex-wrap:nowrap;white-space:nowrap;">
                        <div class="ap-stat"><i class="fas fa-eye" style="color:#60a5fa;"></i> <?php echo intval($article['view_count']); ?>浏览</div>
                        <div class="ap-stat"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="color:#a78bfa;"><path fill-rule="evenodd" d="m11 19l-2.293 2.293A1 1 0 0 1 7 20.586V19H3.5A1.5 1.5 0 0 1 2 17.5v-12A1.5 1.5 0 0 1 3.5 4h17A1.5 1.5 0 0 1 22 5.5v12a1.5 1.5 0 0 1-1.5 1.5z"></path></svg> <?php echo $article['comment_count']; ?>评论</div>
                    </div>
                    <div class="ap-item-stats">
                        <div class="ap-stat">
                            <?php
                            $bg_mode_labels = ['随机渐变', '自定义颜色', '封面图'];
                            echo '背景: ' . ($bg_mode_labels[$article['bg_mode']] ?? '未知');
                            if ($article['bg_mode'] == 1 && $article['bg_color']) {
                                echo ' (' . htmlspecialchars($article['bg_color']) . ')';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="ap-item-actions">
                        <a href="article_edit.php?id=<?php echo $article['id']; ?>" class="ap-btn ap-btn-xs ap-btn-primary"><i class="fas fa-edit"></i> 编辑</a>

                        <?php if ($article['is_top']): ?>
                        <a href="?action=untop&id=<?php echo $article['id']; ?>&csrf_token=<?php echo urlencode($csrf_token); ?>" class="ap-btn ap-btn-xs ap-btn-warning"><i class="fas fa-arrow-down"></i> 取消置顶</a>
                        <?php else: ?>
                        <a href="?action=top&id=<?php echo $article['id']; ?>&csrf_token=<?php echo urlencode($csrf_token); ?>" class="ap-btn ap-btn-xs ap-btn-success"><i class="fas fa-arrow-up"></i> 置顶</a>
                        <?php endif; ?>
                        <?php if ($article['status']==1): ?>
                        <a href="?action=hide&id=<?php echo $article['id']; ?>&csrf_token=<?php echo urlencode($csrf_token); ?>" class="ap-btn ap-btn-xs ap-btn-warning"><i class="fas fa-eye-slash"></i></a>
                        <?php else: ?>
                        <a href="?action=show&id=<?php echo $article['id']; ?>&csrf_token=<?php echo urlencode($csrf_token); ?>" class="ap-btn ap-btn-xs ap-btn-success"><i class="fas fa-eye"></i></a>
                        <?php endif; ?>
                        <button onclick="confirmDelete(<?php echo $article['id']; ?>)" class="ap-btn ap-btn-xs ap-btn-danger"><i class="fas fa-trash"></i></button>

                        <label class="ap-select-all" style="margin-left:2px;">
                            <input type="checkbox" class="select-item" value="<?php echo $article['id']; ?>" onclick="event.stopPropagation();">
                            <span class="ap-check-box"></span>
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($articles)): ?>
                <div class="ap-empty">
                    <i class="fas fa-inbox"></i>
                    <div>暂无文章</div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="ap-pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>">上一页</a>
                <?php else: ?>
                <span style="background:#fff;color:#d1d5db;border:1px solid #e5e7eb;border-radius:12px;min-width:40px;height:40px;display:inline-flex;align-items:center;justify-content:center;font-size:14px;font-weight:600;">上一页</span>
                <?php endif; ?>
                <span class="current"><?php echo $page; ?> / <?php echo $total_pages; ?></span>
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>">下一页</a>
                <?php else: ?>
                <span style="background:#fff;color:#d1d5db;border:1px solid #e5e7eb;border-radius:12px;min-width:40px;height:40px;display:inline-flex;align-items:center;justify-content:center;font-size:14px;font-weight:600;">下一页</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="ap-card">
        <div class="ap-card-header">
            <div class="ap-card-title">文章管理全局设置</div>
        </div>
        <div class="ap-card-body">
            <form method="POST" action="" id="commentForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <div style="display:flex;align-items:flex-start;gap:12px;flex-wrap:nowrap;">
                    <div style="flex:1;min-width:0;">
                        <label style="display:block;font-size:14px;font-weight:500;color:#4b5563;margin-bottom:8px;">每页显示留言数量</label>
                        <input type="number" name="comments_per_page" class="ap-form-input" value="<?php echo $article_comments_per_page; ?>" min="1" max="100" style="width:100%;">
                        <div style="font-size:12px;color:#9ca3af;margin-top:4px;">文章页评论板每页显示的留言数量（1-100），保存后立即生效</div>
                    </div>
                    <button type="submit" name="save_article_comment_setting" class="ap-btn ap-btn-primary" style="height:44px;flex-shrink:0;margin-top:26px;"><i class="fas fa-save"></i> 保存</button>
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
const CSRF_TOKEN = '<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>';
let pendingDeleteId = null;
let pendingBatchAction = null;

function batchSubmit(action) {
    const ids = Array.from(document.querySelectorAll('.select-item:checked')).map(cb => cb.value).join(',');
    if (!ids) {
        showToast('未选择任何项', 'error');
        return;
    }
    pendingDeleteId = null;
    pendingBatchAction = action;
    let msg = '';
    if (action === 'delete') msg = '确认批量删除选中的文章？';
    else if (action === 'hide') msg = '确认批量隐藏选中的文章？';
    else if (action === 'show') msg = '确认批量显示选中的文章？';
    else msg = '确认执行此批量操作？';
    document.getElementById('confirmMsg').textContent = msg;
    document.getElementById('confirmDialog').classList.add('show');
}

function confirmDelete(id) {
    pendingDeleteId = id;
    pendingBatchAction = null;
    document.getElementById('confirmMsg').textContent = '确认删除这篇文章？';
    document.getElementById('confirmDialog').classList.add('show');
}

function closeConfirm() {
    document.getElementById('confirmDialog').classList.remove('show');
    pendingDeleteId = null;
    pendingBatchAction = null;
}

function doConfirmAction() {
    if (pendingDeleteId) {
        const id = pendingDeleteId;
        closeConfirm();

        fetch('?ajax=1&action=delete&id=' + id + '&csrf_token=' + encodeURIComponent(CSRF_TOKEN))
            .then(r => r.json())
            .then(data => {
                if (data.success) {
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
                            titleSpan.textContent = '（' + newCount + '篇）';
                        }
                    }
                } else {
                    showToast(data.msg || '删除失败', 'error');
                }
            })
            .catch(() => showToast('网络错误', 'error'));
    } else if (pendingBatchAction) {
        const action = pendingBatchAction;
        closeConfirm();
        document.getElementById('batchAction').value = 'batch_' + action;
        const ids = Array.from(document.querySelectorAll('.select-item:checked')).map(cb => cb.value).join(',');
        document.getElementById('batchIds').value = ids;
        document.getElementById('batchForm').submit();
    }
}

function toggleSelectAll() {
    const all = document.getElementById('selectAll').checked;
    document.querySelectorAll('.select-item').forEach(cb => cb.checked = all);
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

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($toast_msg): ?>
    showToast('<?php echo htmlspecialchars($toast_msg, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo (strpos($toast_msg, '失败') !== false || strpos($toast_msg, '无效') !== false || strpos($toast_msg, '未选择') !== false) ? 'error' : 'success'; ?>');
    <?php endif; ?>
});
</script></body>
</html>
