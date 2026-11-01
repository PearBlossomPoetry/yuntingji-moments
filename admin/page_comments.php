<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require_once dirname(__FILE__) . '/../config.php';
$settings = get_all_settings();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
$page_title = '留言管理';
$page_id = 'page_comments';

define('PAGE_COMMENT_OFFSET', 100000000);
define('ARTICLE_COMMENT_OFFSET', 200000000);

$req_action = $_GET['action'] ?? $_POST['action'] ?? '';
$req_id = $_GET['id'] ?? $_POST['id'] ?? 0;
$req_ajax = $_GET['ajax'] ?? $_POST['ajax'] ?? '';
if ($req_action && $req_id && $req_ajax) {
    $csrf_ok = false;
    if (!empty($_SERVER['HTTP_X_CSRF_TOKEN']) && !empty($_SESSION['csrf_token'])) {
        $csrf_ok = hash_equals($_SESSION['csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN']);
    } elseif (!empty($_POST['csrf_token']) && !empty($_SESSION['csrf_token'])) {
        $csrf_ok = hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }
    if (!$csrf_ok) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code'=>0,'msg'=>'安全令牌无效，请刷新页面重试']);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    $id = intval($req_id);
    $action = $req_action;
    if ($action === 'delete') {
        mysqli_query($conn, "DELETE FROM comments WHERE id = $id OR parent_id = $id");
        echo '{"code":1,"msg":"已删除"}';
    } elseif ($action === 'hide') {
        mysqli_query($conn, "UPDATE comments SET status = 0 WHERE id = $id");
        $_SESSION['admin_toast'] = '已隐藏';
        echo '{"code":1,"msg":"ok"}';
    } elseif ($action === 'show') {
        mysqli_query($conn, "UPDATE comments SET status = 1 WHERE id = $id");
        $_SESSION['admin_toast'] = '已显示';
        echo '{"code":1,"msg":"ok"}';
    } elseif ($action === 'edit') {
        $content = trim($_POST['content'] ?? '');
        $nickname = trim($_POST['nickname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if (!$content || !$nickname || !$email) {
            echo '{"code":0,"msg":"昵称、邮箱和内容不能为空"}';
            exit;
        }
        $content_esc = mysqli_real_escape_string($conn, $content);
        $nickname_esc = mysqli_real_escape_string($conn, $nickname);
        $email_esc = mysqli_real_escape_string($conn, $email);
        $website_esc = mysqli_real_escape_string($conn, trim($_POST['website'] ?? ''));
        mysqli_query($conn, "UPDATE comments SET content='{$content_esc}', nickname='{$nickname_esc}', email='{$email_esc}', website='{$website_esc}' WHERE id = $id");
        $_SESSION['admin_toast'] = '修改成功';
        echo '{"code":1,"msg":"修改成功"}';
        exit;
    } elseif ($action === 'get') {
        $gr = mysqli_query($conn, "SELECT id, nickname, email, website, content FROM comments WHERE id = $id LIMIT 1");
        if ($gr && $row = mysqli_fetch_assoc($gr)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['code'=>1,'data'=>$row]);
        } else {
            echo '{"code":0,"msg":"评论不存在"}';
        }
        exit;
    } elseif ($action === 'reply') {
        $content = trim($_POST['content'] ?? '');
        if (!$content) {
            echo '{"code":0,"msg":"回复内容不能为空"}';
            exit;
        }
        $settings = get_all_settings();
        $admin_nickname = $settings['nickname'] ?? '管理员';
        $admin_avatar = $settings['avatar'] ?? '';
        $admin_email = $settings['smtp_user'] ?? 'admin@localhost';

        $original = mysqli_query($conn, "SELECT post_id FROM comments WHERE id = $id LIMIT 1");
        if (!$original || !($orig_row = mysqli_fetch_assoc($original))) {
            echo '{"code":0,"msg":"评论不存在"}';
            exit;
        }
        $post_id = intval($orig_row['post_id']);
        $content_esc = mysqli_real_escape_string($conn, $content);
        $nickname_esc = mysqli_real_escape_string($conn, $admin_nickname);
        $email_esc = mysqli_real_escape_string($conn, $admin_email);
        $avatar_esc = mysqli_real_escape_string($conn, $admin_avatar);
        $ip = get_real_ip();

        $insert_sql = "INSERT INTO comments (post_id, nickname, email, avatar, content, parent_id, status, ip)
                       VALUES ($post_id, '$nickname_esc', '$email_esc', '$avatar_esc', '$content_esc', $id, 1, '$ip')";
        if (mysqli_query($conn, $insert_sql)) {
            $_SESSION['admin_toast'] = '回复成功';
            echo '{"code":1,"msg":"回复成功"}';
        } else {
            echo '{"code":0,"msg":"回复失败：' . mysqli_error($conn) . '"}';
        }
        exit;
    } elseif ($action === 'approve') {
        mysqli_query($conn, "UPDATE comments SET status = 1, approve_status = 1 WHERE id = $id");
        $_SESSION['admin_toast'] = '已通过';
        echo '{"code":1,"msg":"ok"}';
    } elseif ($action === 'reject') {
        mysqli_query($conn, "UPDATE comments SET status = 0, approve_status = 2 WHERE id = $id");
        $_SESSION['admin_toast'] = '已拒绝';
        echo '{"code":1,"msg":"ok"}';
    }
    exit;
}

if (isset($_POST['action']) && isset($_POST['ajax']) && strpos($_POST['action'], 'batch_') === 0) {
    $csrf_ok = false;
    if (!empty($_SERVER['HTTP_X_CSRF_TOKEN']) && !empty($_SESSION['csrf_token'])) {
        $csrf_ok = hash_equals($_SESSION['csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN']);
    } elseif (!empty($_POST['csrf_token']) && !empty($_SESSION['csrf_token'])) {
        $csrf_ok = hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }
    if (!$csrf_ok) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code'=>0,'msg'=>'安全令牌无效，请刷新页面重试']);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];
    $ids = trim($_POST['ids'] ?? '');
    if (!$ids) {
        echo '{"code":0,"msg":"未选择任何项"}';
        exit;
    }
    $idArr = array_filter(array_map('intval', explode(',', $ids)));
    if (empty($idArr)) {
        echo '{"code":0,"msg":"选择无效"}';
        exit;
    }
    $idStr = implode(',', $idArr);
    $msg = '操作成功';
    if ($action === 'batch_delete') {
        $sql = "DELETE FROM comments WHERE id IN ($idStr) OR parent_id IN ($idStr)";
        $msg = '批量删除成功';
    } elseif ($action === 'batch_hide') {
        $sql = "UPDATE comments SET status = 0 WHERE id IN ($idStr)";
        $msg = '批量隐藏成功';
        $_SESSION['admin_toast'] = $msg;
    } elseif ($action === 'batch_show') {
        $sql = "UPDATE comments SET status = 1 WHERE id IN ($idStr)";
        $msg = '批量显示成功';
        $_SESSION['admin_toast'] = $msg;
    } elseif ($action === 'batch_approve') {
        $sql = "UPDATE comments SET status = 1, approve_status = 1 WHERE id IN ($idStr)";
        $msg = '批量通过成功';
        $_SESSION['admin_toast'] = $msg;
    } elseif ($action === 'batch_reject') {
        $sql = "UPDATE comments SET status = 0, approve_status = 2 WHERE id IN ($idStr)";
        $msg = '批量拒绝成功';
        $_SESSION['admin_toast'] = $msg;
    } else {
        echo '{"code":0,"msg":"未知批量操作"}';
        exit;
    }
    if (mysqli_query($conn, $sql)) {
        echo '{"code":1,"msg":"' . $msg . '"}';
    } else {
        echo '{"code":0,"msg":"操作失败：' . mysqli_error($conn) . '"}';
    }
    exit;
}

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$approveFilter = '';
if ($filter === 'approved') {
    $approveFilter = ' AND approve_status = 1';
} elseif ($filter === 'pending') {
    $approveFilter = ' AND approve_status = 0';
} elseif ($filter === 'rejected') {
    $approveFilter = ' AND approve_status = 2';
}

$total = 0;
$tr = mysqli_query($conn, "SELECT COUNT(*) as c FROM comments WHERE post_id <= -" . PAGE_COMMENT_OFFSET . $approveFilter);
if ($tr && $row = mysqli_fetch_assoc($tr)) {
    $total = intval($row['c']);
}
$total_pages = max(1, ceil($total / $per_page));

$pages_map = [];
$pr = mysqli_query($conn, "SELECT id, title, slug FROM pages");
if ($pr) {
    while ($row = mysqli_fetch_assoc($pr)) {
        $pages_map[intval($row['id'])] = $row;
    }
}

$articles_map = [];
$ar = mysqli_query($conn, "SELECT id, title, slug FROM articles");
if ($ar) {
    while ($row = mysqli_fetch_assoc($ar)) {
        $articles_map[intval($row['id'])] = $row;
    }
}

$comments = array();
$sql = "SELECT * FROM comments WHERE post_id <= -" . PAGE_COMMENT_OFFSET . $approveFilter . " ORDER BY created_at DESC LIMIT $offset, $per_page";
$r = mysqli_query($conn, $sql);
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) {
        $post_id = intval($row['post_id']);
        if ($post_id <= -ARTICLE_COMMENT_OFFSET) {
            $article_id = abs($post_id) - ARTICLE_COMMENT_OFFSET;
            $row['source_type'] = '文章';
            $row['source_id'] = $article_id;
            $row['source_title'] = isset($articles_map[$article_id]) ? $articles_map[$article_id]['title'] : '未知文章';
            $row['source_slug'] = isset($articles_map[$article_id]) ? $articles_map[$article_id]['slug'] : '';
            $row['source_link_suffix'] = '.article.html';
        } else {
            $page_id = abs($post_id) - PAGE_COMMENT_OFFSET;
            $row['source_type'] = '页面';
            $row['source_id'] = $page_id;
            $row['source_title'] = isset($pages_map[$page_id]) ? $pages_map[$page_id]['title'] : '未知页面';
            $row['source_slug'] = isset($pages_map[$page_id]) ? $pages_map[$page_id]['slug'] : '';
            $row['source_link_suffix'] = '.html';
        }
        $row['page_id'] = $row['source_id'];
        $row['page_title'] = $row['source_title'];
        $row['page_slug'] = $row['source_slug'];
        $comments[] = $row;
    }
}

$replies_map = array();
if (!empty($comments)) {
    $parent_ids = array();
    foreach ($comments as $c) {
        if ($c['parent_id'] == 0) {
            $parent_ids[] = intval($c['id']);
        }
    }
    if (!empty($parent_ids)) {
        $ids_str = implode(',', $parent_ids);
        $rr = mysqli_query($conn, "SELECT * FROM comments WHERE parent_id IN ($ids_str) ORDER BY created_at ASC");
        if ($rr) {
            while ($rp = mysqli_fetch_assoc($rr)) {
                $pid = intval($rp['parent_id']);
                if (!isset($replies_map[$pid])) $replies_map[$pid] = array();
                $rp_post_id = intval($rp['post_id']);
                if ($rp_post_id <= -ARTICLE_COMMENT_OFFSET) {
                    $rp_article_id = abs($rp_post_id) - ARTICLE_COMMENT_OFFSET;
                    $rp['source_type'] = '文章';
                    $rp['source_id'] = $rp_article_id;
                    $rp['source_title'] = isset($articles_map[$rp_article_id]) ? $articles_map[$rp_article_id]['title'] : '未知文章';
                    $rp['source_slug'] = isset($articles_map[$rp_article_id]) ? $articles_map[$rp_article_id]['slug'] : '';
                    $rp['source_link_suffix'] = '.article.html';
                } else {
                    $rp_page_id = abs($rp_post_id) - PAGE_COMMENT_OFFSET;
                    $rp['source_type'] = '页面';
                    $rp['source_id'] = $rp_page_id;
                    $rp['source_title'] = isset($pages_map[$rp_page_id]) ? $pages_map[$rp_page_id]['title'] : '未知页面';
                    $rp['source_slug'] = isset($pages_map[$rp_page_id]) ? $pages_map[$rp_page_id]['slug'] : '';
                    $rp['source_link_suffix'] = '.html';
                }
                $rp['page_id'] = $rp['source_id'];
                $rp['page_title'] = $rp['source_title'];
                $rp['page_slug'] = $rp['source_slug'];
                $replies_map[$pid][] = $rp;
            }
        }
    }
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
            gap: 6px;
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 13px;
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
        .ap-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 5px 14px rgba(91,115,232,0.25); }
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
        .ap-btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 10px; }
        .ap-btn-xs { padding: 5px 10px; font-size: 11px; border-radius: 8px; }

        .ap-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .ap-badge-green { background: #dcfce7; color: #166534; }
        .ap-badge-yellow { background: #fef3c7; color: #92400e; }
        .ap-badge-blue { background: #dbeafe; color: #1e40af; }
        .ap-badge-red { background: #fee2e2; color: #991b1b; }
        .ap-badge-gray { background: #f3f4f6; color: #6b7280; }
        .ap-badge-purple { background: #f3e8ff; color: #6b21a8; }

        .ap-select-all {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 13px;
            color: #6b7280;
            font-weight: 500;
            user-select: none;
            flex-shrink: 0;
        }
        .ap-select-all input[type="checkbox"] { display: none; }
        .ap-check-box {
            width: 18px;
            height: 18px;
            border: 2px solid #d1d5db;
            border-radius: 5px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            flex-shrink: 0;
            background: #fff;
        }
        .ap-check-box::after {
            content: '';
            width: 8px;
            height: 8px;
            background: #5b73e8;
            border-radius: 2px;
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
            min-width: 36px;
            height: 36px;
            padding: 0 12px;
            border-radius: 10px;
            font-size: 13px;
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

        .fade-in { animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity:0; transform: translateY(8px); } to { opacity:1; transform: translateY(0); } }

        .filter-bar {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f8f8f8;
        }
        .filter-btn {
            padding: 6px 14px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            background: #f3f4f6;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        .filter-btn:hover { background: #e5e7eb; color: #374151; }
        .filter-btn.active {
            background: linear-gradient(135deg, #5b73e8 0%, #4a62d7 100%);
            color: #fff;
            box-shadow: 0 2px 8px rgba(91,115,232,0.2);
        }

        .batch-bar {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .comment-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
        }
        @media (min-width: 769px) {
            .comment-list {
                grid-template-columns: 1fr 1fr;
            }
        }

        .comment-item {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #f0f0f0;
            padding: 16px;
            transition: all 0.3s ease;
        }
        .comment-item:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
            border-color: #e5e7eb;
        }

        .comment-row {
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        .comment-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
            background: #f3f4f6;
        }
        .comment-body {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .comment-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
        }
        .comment-name {
            font-size: 14px;
            font-weight: 700;
            color: #1f2937;
        }
        .comment-sub {
            font-size: 11px;
            color: #9ca3af;
            font-weight: 500;
            line-height: 1.4;
        }
        .comment-text {
            font-size: 13px;
            color: #4b5563;
            line-height: 1.6;
            word-break: break-word;
            font-weight: 500;
        }
        .comment-meta {
            font-size: 10px;
            color: #9ca3af;
            line-height: 1.5;
            font-weight: 500;
        }
        .comment-meta a { color: #5b73e8; text-decoration: none; }
        .comment-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            align-items: center;
            margin-top: 4px;
        }
        .comment-actions .ap-btn { box-shadow: none !important; }
        .comment-actions .ap-btn:hover { box-shadow: none !important; transform: translateY(-1px); }

        .replies {
            margin-left: 52px;
            margin-top: 12px;
            background: #f8f9fa;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .reply-row {
            border-bottom: 1px solid #e5e7eb;
            padding: 10px 12px;
        }
        .reply-row:last-child {
            border-bottom: none;
        }
        .reply-row .comment-avatar {
            width: 32px;
            height: 32px;
        }
        .reply-row .comment-name {
            font-size: 13px;
        }
        .reply-row .comment-text {
            font-size: 12px;
        }

        .ap-empty {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
            font-size: 15px;
        }
        .ap-empty i { font-size: 48px; color: #e5e7eb; margin-bottom: 12px; display: block; }

        .modal-overlay {
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
        .modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        .modal-box {
            background: #fff;
            border-radius: 20px;
            padding: 24px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 16px 48px rgba(0,0,0,0.12);
            transform: scale(0.95);
            transition: transform 0.3s ease;
        }
        .modal-overlay.show .modal-box {
            transform: scale(1);
        }
        .modal-title {
            font-size: 16px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 16px;
        }
        .modal-group { margin-bottom: 14px; }
        .modal-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 6px;
        }
        .modal-input, .modal-textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #f0f0f0;
            border-radius: 12px;
            font-size: 14px;
            color: #374151;
            outline: none;
            transition: all 0.2s;
            background: #fafafa;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        .modal-input:focus, .modal-textarea:focus {
            border-color: #5b73e8;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(91,115,232,0.08);
        }
        .modal-textarea { resize: vertical; line-height: 1.6; }
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
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
            .ap-card-body { padding: 16px; }
            .ap-btn { padding: 6px 12px; font-size: 12px; border-radius: 10px; }
            .comment-item { padding: 12px; border-radius: 14px; }
            .comment-row { gap: 10px; }
            .comment-avatar { width: 36px; height: 36px; }
            .comment-name { font-size: 13px; }
            .comment-text { font-size: 12px; }
            .reply-row .comment-avatar { width: 28px; height: 28px; }
            .replies {
                margin-left: 46px;
                border-radius: 10px;
            }
            .reply-row {
                padding: 8px 10px;
            }
            .filter-bar { gap: 6px; }
            .filter-btn { padding: 5px 10px; font-size: 11px; }
            .batch-bar { gap: 6px; }
            .modal-box { padding: 20px; border-radius: 16px; }
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
            $avatar_raw = trim($settings['avatar'] ?? '');
            if ($avatar_raw && !preg_match('#^(https?:)?//#i', $avatar_raw) && $avatar_raw[0] !== '/') {
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
            <div class="ap-card-title">留言列表 <span>（<?php echo $total; ?> 条）</span></div>
        </div>
        <div class="ap-card-body">

            <div class="filter-bar">
                <a href="page_comments.php" class="filter-btn <?php echo !isset($_GET['filter']) ? 'active' : ''; ?>">全部</a>
                <a href="page_comments.php?filter=parent" class="filter-btn <?php echo isset($_GET['filter']) && $_GET['filter']=='parent' ? 'active' : ''; ?>">主评论</a>
                <a href="page_comments.php?filter=reply" class="filter-btn <?php echo isset($_GET['filter']) && $_GET['filter']=='reply' ? 'active' : ''; ?>">回复</a>
                <a href="page_comments.php?filter=approved" class="filter-btn <?php echo isset($_GET['filter']) && $_GET['filter']=='approved' ? 'active' : ''; ?>">已通过</a>
                <a href="page_comments.php?filter=pending" class="filter-btn <?php echo isset($_GET['filter']) && $_GET['filter']=='pending' ? 'active' : ''; ?>">待审核</a>
                <a href="page_comments.php?filter=rejected" class="filter-btn <?php echo isset($_GET['filter']) && $_GET['filter']=='rejected' ? 'active' : ''; ?>">已拒绝</a>
            </div>

            <div class="batch-bar">
                <button class="ap-btn ap-btn-xs ap-btn-success" onclick="confirmBatchAction('batch_approve')"><i class="fas fa-check"></i> 批量通过</button>
                <button class="ap-btn ap-btn-xs ap-btn-danger" onclick="confirmBatchAction('batch_reject')"><i class="fas fa-times"></i> 批量拒绝</button>
                <button class="ap-btn ap-btn-xs ap-btn-warning" onclick="confirmBatchAction('batch_hide')"><i class="fas fa-eye-slash"></i> 批量隐藏</button>
                <button class="ap-btn ap-btn-xs ap-btn-success" onclick="confirmBatchAction('batch_show')"><i class="fas fa-eye"></i> 批量显示</button>
                <button class="ap-btn ap-btn-xs ap-btn-danger" onclick="confirmBatchAction('batch_delete')"><i class="fas fa-trash"></i> 批量删除</button>
                <label class="ap-select-all" style="margin-left:auto;">
                    <input type="checkbox" id="selectAll" onclick="toggleSelectAll()">
                    <span class="ap-check-box"></span>
                    <span>全选</span>
                </label>
            </div>

            <div class="comment-list">
                <?php foreach ($comments as $c): ?>
                <?php
                $isReply = intval($c['parent_id']) > 0;
                if ($filter == 'parent' && $isReply) continue;
                if ($filter == 'reply' && !$isReply) continue;
                ?>
                <div class="comment-item" data-id="<?php echo $c['id']; ?>">

                    <div class="comment-row">
                        <?php if ($c['avatar']): ?>
                        <img src="<?php echo htmlspecialchars($c['avatar']); ?>" class="comment-avatar" alt="">
                        <?php else: ?>
                        <div class="comment-avatar"></div>
                        <?php endif; ?>
                        <div class="comment-body">
                            <div class="comment-header">
                                <span class="comment-name"><?php echo htmlspecialchars($c['nickname']); ?></span>
                                <?php if ($c['status'] == 1): ?>
                                <span class="ap-badge ap-badge-green">已显示</span>
                                <?php else: ?>
                                <span class="ap-badge ap-badge-yellow">已隐藏</span>
                                <?php endif; ?>
                                <?php if ($isReply): ?>
                                <span class="ap-badge ap-badge-blue">回复</span>
                                <?php endif; ?>
                                <?php if ($c['approve_status'] == 0): ?>
                                <span class="ap-badge ap-badge-yellow">待审核</span>
                                <?php elseif ($c['approve_status'] == 1): ?>
                                <span class="ap-badge ap-badge-green">已通过</span>
                                <?php elseif ($c['approve_status'] == 2): ?>
                                <span class="ap-badge ap-badge-red">已拒绝</span>
                                <?php endif; ?>
                            </div>
                            <div class="comment-sub">
                                <?php echo htmlspecialchars($c['email']); ?> | IP：<?php echo htmlspecialchars($c['ip']); ?> | <?php echo $c['created_at']; ?>
                            </div>
                            <div class="comment-text"><?php echo nl2br(htmlspecialchars($c['content'])); ?></div>
                            <div class="comment-meta">
                                <?php if ($c['website']): ?>网址：<a href="<?php echo htmlspecialchars($c['website']); ?>" target="_blank"><?php echo htmlspecialchars($c['website']); ?></a> | <?php endif; ?>
                                来源：<a href="../<?php echo htmlspecialchars($c['source_slug']) . $c['source_link_suffix']; ?>" target="_blank"><?php echo htmlspecialchars($c['source_title']); ?></a>
                            </div>
                            <div class="comment-actions">
                                <button class="ap-btn ap-btn-xs ap-btn-primary" onclick="openEditModal(<?php echo $c['id']; ?>)"><i class="fas fa-edit"></i> 编辑</button>
                                <button class="ap-btn ap-btn-xs ap-btn-success" onclick="openReplyModal(<?php echo $c['id']; ?>)"><i class="fas fa-reply"></i> 回复</button>
                                <?php if ($c['approve_status'] == 0): ?>
                                <button class="ap-btn ap-btn-xs ap-btn-success" onclick="doAction('approve',<?php echo $c['id']; ?>)"><i class="fas fa-check"></i> 通过</button>
                                <button class="ap-btn ap-btn-xs ap-btn-danger" onclick="doAction('reject',<?php echo $c['id']; ?>)"><i class="fas fa-times"></i> 拒绝</button>
                                <?php elseif ($c['approve_status'] == 2): ?>
                                <button class="ap-btn ap-btn-xs ap-btn-success" onclick="doAction('approve',<?php echo $c['id']; ?>)"><i class="fas fa-check"></i> 重新通过</button>
                                <?php else: ?>
                                <?php if ($c['status']==1): ?>
                                <button class="ap-btn ap-btn-xs ap-btn-warning" onclick="doAction('hide',<?php echo $c['id']; ?>)"><i class="fas fa-eye-slash"></i> 隐藏</button>
                                <?php else: ?>
                                <button class="ap-btn ap-btn-xs ap-btn-success" onclick="doAction('show',<?php echo $c['id']; ?>)"><i class="fas fa-eye"></i> 显示</button>
                                <?php endif; ?>
                                <?php endif; ?>
                                <button class="ap-btn ap-btn-xs ap-btn-danger" onclick="confirmDelete(<?php echo $c['id']; ?>)"><i class="fas fa-trash"></i> 删除</button>
                                <label class="ap-select-all" style="margin-left:4px;">
                                    <input type="checkbox" class="select-item" value="<?php echo $c['id']; ?>" onclick="event.stopPropagation();">
                                    <span class="ap-check-box"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <?php if (!$isReply && isset($replies_map[intval($c['id'])]) && !empty($replies_map[intval($c['id'])])): ?>
                    <div class="replies">
                        <?php foreach ($replies_map[intval($c['id'])] as $rp): ?>
                        <div class="comment-row reply-row">
                            <?php if ($rp['avatar']): ?>
                            <img src="<?php echo htmlspecialchars($rp['avatar']); ?>" class="comment-avatar" alt="">
                            <?php else: ?>
                            <div class="comment-avatar"></div>
                            <?php endif; ?>
                            <div class="comment-body">
                                <div class="comment-header">
                                    <span class="comment-name"><?php echo htmlspecialchars($rp['nickname']); ?></span>
                                    <span class="ap-badge ap-badge-gray">回复</span>
                                    <span style="font-size:12px;font-weight:700;color:#5b73e8;"><?php echo htmlspecialchars($c['nickname']); ?></span>

                                </div>
                                <div class="comment-text"><?php echo htmlspecialchars($rp['content']); ?></div>
                                <div class="comment-meta"><?php echo $rp['created_at']; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php if (empty($comments)): ?>
                <div class="ap-empty">
                    <i class="fas fa-inbox"></i>
                    <div>暂无留言</div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="ap-pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                <a href="?page=<?php echo $i; ?><?php echo isset($_GET['filter']) ? '&filter=' . htmlspecialchars($_GET['filter']) : ''; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
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

<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-title"><i class="fas fa-edit" style="color:#5b73e8;margin-right:6px;"></i>编辑留言</div>
        <input type="hidden" id="editId">
        <div class="modal-group">
            <label class="modal-label">昵称</label>
            <input type="text" id="editNick" class="modal-input">
        </div>
        <div class="modal-group">
            <label class="modal-label">邮箱</label>
            <input type="email" id="editEmail" class="modal-input">
        </div>
        <div class="modal-group">
            <label class="modal-label">网址</label>
            <input type="text" id="editWebsite" class="modal-input">
        </div>
        <div class="modal-group">
            <label class="modal-label">内容</label>
            <textarea id="editContent" rows="4" class="modal-textarea"></textarea>
        </div>
        <div class="modal-actions">
            <button class="ap-btn" style="background:#f3f4f6;color:#6b7280;" onclick="closeEditModal()">取消</button>
            <button class="ap-btn ap-btn-primary" onclick="saveEdit()">保存</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="replyModal">
    <div class="modal-box">
        <div class="modal-title"><i class="fas fa-reply" style="color:#5b73e8;margin-right:6px;"></i>回复留言</div>
        <input type="hidden" id="replyId">
        <div class="modal-group">
            <label class="modal-label">回复内容</label>
            <textarea id="replyContent" rows="4" class="modal-textarea"></textarea>
        </div>
        <div class="modal-actions">
            <button class="ap-btn" style="background:#f3f4f6;color:#6b7280;" onclick="closeReplyModal()">取消</button>
            <button class="ap-btn ap-btn-primary" onclick="saveReply()">发送</button>
        </div>
    </div>
</div>

<div class="confirm-overlay" id="confirmDialog" style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10000;display:flex;align-items:center;justify-content:center;padding:20px;opacity:0;visibility:hidden;transition:all 0.3s ease;">
    <div class="confirm-box" style="background:#fff;border-radius:16px;padding:16px 20px;max-width:280px;width:100%;text-align:center;transform:scale(0.9);transition:transform 0.3s ease;">
        <h3 id="confirmTitle" style="font-size:15px;font-weight:700;color:#1f2937;margin-bottom:8px;"><i class="fas fa-exclamation-triangle" style="color:#e58a00;margin-right:4px;font-size:14px;"></i>确认删除</h3>
        <p id="confirmMsg" style="font-size:13px;color:#6b7280;margin-bottom:14px;line-height:1.4;">确认删除这条留言？</p>
        <div style="display:flex;gap:8px;">
            <button style="flex:1;padding:8px 10px;border-radius:10px;font-size:13px;font-weight:600;border:none;cursor:pointer;text-align:center;background:#f3f4f6;color:#6b7280;" onclick="closeConfirm()">取消</button>
            <button style="flex:1;padding:8px 10px;border-radius:10px;font-size:13px;font-weight:600;border:none;cursor:pointer;text-align:center;background:#dc2626;color:#fff;" onclick="doConfirm()">确认</button>
        </div>
    </div>
</div>

<script>
var CSRF_TOKEN = '<?php echo csrf_token_value(); ?>';
let pendingDeleteId = null;
let pendingBatchAction = null;

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

function doAction(action, id) {
    fetch('?ajax=1&action=' + action + '&id=' + id, { headers: { 'X-CSRF-Token': CSRF_TOKEN } })
        .then(r => r.json())
        .then(data => {
            if (data.code === 1) {
                if (action === 'delete') {
                    const el = document.querySelector('.comment-item[data-id="' + id + '"]');
                    if (el) {
                        el.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        el.style.opacity = '0';
                        el.style.transform = 'translateX(20px)';
                        setTimeout(() => el.remove(), 300);
                    }
                    showToast(data.msg || '已删除', 'success');
                } else {
                    location.reload();
                }
            } else {
                showToast(data.msg || '操作失败', 'error');
            }
        })
        .catch(() => showToast('网络错误', 'error'));
}

function showConfirmDialog(title, msg) {
    document.getElementById('confirmTitle').innerHTML = '<i class="fas fa-exclamation-triangle" style="color:#e58a00;margin-right:4px;font-size:14px;"></i>' + title;
    document.getElementById('confirmMsg').textContent = msg;
    document.getElementById('confirmDialog').style.opacity = '1';
    document.getElementById('confirmDialog').style.visibility = 'visible';
    document.querySelector('#confirmDialog .confirm-box').style.transform = 'scale(1)';
}

function confirmDelete(id) {
    pendingDeleteId = id;
    pendingBatchAction = null;
    showConfirmDialog('确认删除', '确认删除这条留言？');
}

function confirmBatchAction(action) {
    const ids = Array.from(document.querySelectorAll('.select-item:checked')).map(cb => cb.value);
    if (!ids.length) {
        showToast('未选择任何项', 'error');
        return;
    }

    const titles = {
        'batch_delete': '确认批量删除',
        'batch_approve': '确认批量通过',
        'batch_reject': '确认批量拒绝',
        'batch_hide': '确认批量隐藏',
        'batch_show': '确认批量显示'
    };
    const msgs = {
        'batch_delete': '确认删除选中的 ' + ids.length + ' 条留言？',
        'batch_approve': '确认通过选中的 ' + ids.length + ' 条留言？',
        'batch_reject': '确认拒绝选中的 ' + ids.length + ' 条留言？',
        'batch_hide': '确认隐藏选中的 ' + ids.length + ' 条留言？',
        'batch_show': '确认显示选中的 ' + ids.length + ' 条留言？'
    };

    pendingDeleteId = null;
    pendingBatchAction = action;
    showConfirmDialog(titles[action], msgs[action]);
}

function closeConfirm() {
    document.getElementById('confirmDialog').style.opacity = '0';
    document.getElementById('confirmDialog').style.visibility = 'hidden';
    document.querySelector('#confirmDialog .confirm-box').style.transform = 'scale(0.9)';
    pendingDeleteId = null;
    pendingBatchAction = null;
}

function doConfirm() {
    if (pendingDeleteId) {
        doAction('delete', pendingDeleteId);
    } else if (pendingBatchAction) {
        executeBatchAction(pendingBatchAction);
    }
    closeConfirm();
}

function executeBatchAction(action) {
    const ids = Array.from(document.querySelectorAll('.select-item:checked')).map(cb => cb.value).join(',');
    if (!ids) {
        showToast('未选择任何项', 'error');
        return;
    }
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', action);
    formData.append('ids', ids);

    fetch('', { method: 'POST', headers: { 'X-CSRF-Token': CSRF_TOKEN }, body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.code === 1) {
                if (action === 'batch_delete') {
                    ids.split(',').forEach(id => {
                        const el = document.querySelector('.comment-item[data-id="' + id + '"]');
                        if (el) el.remove();
                    });
                    document.getElementById('selectAll').checked = false;
                    showToast(data.msg || '批量删除成功', 'success');
                } else {
                    location.reload();
                }
            } else {
                showToast(data.msg || '操作失败', 'error');
            }
        })
        .catch(() => showToast('网络错误', 'error'));
}

function toggleSelectAll() {
    const all = document.getElementById('selectAll').checked;
    document.querySelectorAll('.select-item').forEach(cb => cb.checked = all);
}

function openEditModal(id) {
    document.getElementById('editModal').classList.add('show');
    document.getElementById('editId').value = id;
    fetch('?ajax=1&action=get&id=' + id, { headers: { 'X-CSRF-Token': CSRF_TOKEN } })
        .then(r => r.json())
        .then(data => {
            if (data.code === 1) {
                document.getElementById('editNick').value = data.data.nickname;
                document.getElementById('editEmail').value = data.data.email;
                document.getElementById('editWebsite').value = data.data.website || '';
                document.getElementById('editContent').value = data.data.content;
            } else {
                showToast(data.msg || '获取失败', 'error');
                closeEditModal();
            }
        })
        .catch(() => {
            showToast('网络错误', 'error');
            closeEditModal();
        });
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('show');
}

function saveEdit() {
    const id = document.getElementById('editId').value;
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'edit');
    formData.append('id', id);
    formData.append('nickname', document.getElementById('editNick').value);
    formData.append('email', document.getElementById('editEmail').value);
    formData.append('website', document.getElementById('editWebsite').value);
    formData.append('content', document.getElementById('editContent').value);

    fetch('', { method: 'POST', headers: { 'X-CSRF-Token': CSRF_TOKEN }, body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.code === 1) {
                closeEditModal();
                location.reload();
            } else {
                showToast(data.msg || '修改失败', 'error');
            }
        })
        .catch(() => showToast('网络错误', 'error'));
}

function openReplyModal(id) {
    document.getElementById('replyModal').classList.add('show');
    document.getElementById('replyId').value = id;
    document.getElementById('replyContent').value = '';
}

function closeReplyModal() {
    document.getElementById('replyModal').classList.remove('show');
}

function saveReply() {
    const id = document.getElementById('replyId').value;
    const content = document.getElementById('replyContent').value.trim();
    if (!content) {
        showToast('回复内容不能为空', 'error');
        return;
    }
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'reply');
    formData.append('id', id);
    formData.append('content', content);

    fetch('', { method: 'POST', headers: { 'X-CSRF-Token': CSRF_TOKEN }, body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.code === 1) {
                closeReplyModal();
                location.reload();
            } else {
                showToast(data.msg || '回复失败', 'error');
            }
        })
        .catch(() => showToast('网络错误', 'error'));
}

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($toast_msg): ?>
    showToast('<?php echo htmlspecialchars($toast_msg, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo (strpos($toast_msg, '失败') !== false || strpos($toast_msg, '错误') !== false || strpos($toast_msg, '未选择') !== false) ? 'error' : 'success'; ?>');
    <?php endif; ?>
});
</script>
</body>
</html>
