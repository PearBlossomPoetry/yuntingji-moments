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

$page_title = '编辑文章';
$page_id    = 'articles';

$id      = intval($_GET['id'] ?? 0);
$is_edit = $id > 0;

$article = [
    'id'              => 0,
    'title'           => '',
    'slug'            => '',
    'content'         => '',
    'summary'         => '',
    'cover_image'     => '',
    'bg_color'        => '',
    'bg_mode'         => 0,
    'is_top'          => 0,
    'status'          => 1,
    'comment_enabled' => 1,
    'published_at'    => ''
];

if ($is_edit) {
    $r = mysqli_query($conn, "SELECT * FROM articles WHERE id = $id");
    if ($r && $row = mysqli_fetch_assoc($r)) {
        $article = $row;
        if (intval($article['bg_mode'] ?? 0) === 2) {
            $article['bg_mode'] = 1;
        }
        $page_title = '编辑文章';
    } else {
        header('Location: articles.php');
        exit;
    }
} else {
    $page_title = '发布文章';
}

$toast_msg = $_SESSION['admin_toast'] ?? '';
if ($toast_msg) {
    unset($_SESSION['admin_toast']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = intval($_POST['id'] ?? 0);
    $is_edit = $id > 0;

    $cover_image = trim($_POST['cover_image'] ?? '');
    if (!empty($_FILES['cover_file']['tmp_name'])) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fType   = $_FILES['cover_file']['type'];
        $fSize   = $_FILES['cover_file']['size'];

        if (!in_array($fType, $allowed)) {
            $_SESSION['admin_toast'] = '封面图格式不支持，仅支持 JPG/PNG/GIF/WEBP';
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } elseif ($fSize > 5 * 1024 * 1024) {
            $_SESSION['admin_toast'] = '封面图大小不能超过5MB';
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $ext = pathinfo($_FILES['cover_file']['name'], PATHINFO_EXTENSION);
            $ext = strtolower($ext);
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $ext = 'jpg';
            }
            $uploadDir = __DIR__ . '/../uploads/covers/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $newName    = 'cover_' . date('Ymd') . '_' . uniqid() . '.' . $ext;
            $targetPath = $uploadDir . $newName;

            if (move_uploaded_file($_FILES['cover_file']['tmp_name'], $targetPath)) {
                $cover_image = 'uploads/covers/' . $newName;
            } else {
                $_SESSION['admin_toast'] = '封面图上传失败';
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            }
        }
    }

    $title           = trim($_POST['title'] ?? '');
    $slug            = trim($_POST['slug'] ?? '');
    $content         = $_POST['content'] ?? '';
    $summary         = trim($_POST['summary'] ?? '');
    $bg_mode         = intval($_POST['bg_mode'] ?? 0);
    $bg_color        = trim($_POST['bg_color'] ?? '');
    $comment_enabled = intval($_POST['comment_enabled'] ?? 1);
    $status          = intval($_POST['status'] ?? 1);
    $is_top          = intval($_POST['is_top'] ?? 0);
    $published_at    = trim($_POST['published_at'] ?? '');

    if (empty($title)) {
        $_SESSION['admin_toast'] = '文章标题不能为空';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } elseif (empty($slug)) {
        $_SESSION['admin_toast'] = '文章别名不能为空';
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
        $esc_summary      = mysqli_real_escape_string($conn, $summary);
        $esc_cover_image  = mysqli_real_escape_string($conn, $cover_image);
        $esc_bg_color     = mysqli_real_escape_string($conn, $bg_color);
        $esc_published_at = mysqli_real_escape_string($conn, $published_at);

        $check_sql = "SELECT id FROM articles WHERE slug = '$esc_slug'" . ($is_edit ? " AND id != $id" : "");
        $check     = mysqli_query($conn, $check_sql);

        if ($check && mysqli_num_rows($check) > 0) {
            $_SESSION['admin_toast'] = '文章别名已存在 请使用其他别名';
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $check_page_sql = "SELECT id FROM pages WHERE slug = '$esc_slug'";
            $check_page     = mysqli_query($conn, $check_page_sql);

            if ($check_page && mysqli_num_rows($check_page) > 0) {
                $_SESSION['admin_toast'] = '该别名已被独立页面使用 请更换其他别名';
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            } else {
                if ($is_edit) {
                    mysqli_query($conn, "UPDATE articles SET
                        title           = '$esc_title',
                        slug            = '$esc_slug',
                        content         = '$esc_content',
                        summary         = '$esc_summary',
                        cover_image     = '$esc_cover_image',
                        bg_mode         = $bg_mode,
                        bg_color        = '$esc_bg_color',
                        comment_enabled = $comment_enabled,
                        status          = $status,
                        is_top          = $is_top,
                        published_at    = '$esc_published_at',
                        updated_at      = NOW()
                        WHERE id = $id
                    ");
                } else {
                    $now = date('Y-m-d H:i:s');
                    $pat = empty($published_at) ? $now : $esc_published_at;
                    mysqli_query($conn, "INSERT INTO articles (
                        title, slug, content, summary, cover_image, bg_mode, bg_color,
                        comment_enabled, status, is_top, view_count,
                        published_at, created_at, updated_at
                    ) VALUES (
                        '$esc_title', '$esc_slug', '$esc_content', '$esc_summary', '$esc_cover_image',
                        $bg_mode, '$esc_bg_color', $comment_enabled, $status, $is_top,
                        0, '$pat', NOW(), NOW()
                    )");
                    $id = mysqli_insert_id($conn);
                }
                $_SESSION['admin_toast'] = '保存成功';
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            }
        }
    }
}

$settings = get_all_settings();

$previewSrc = '';
if (!empty($article['cover_image'])) {
    $previewSrc = $article['cover_image'];
    if (strpos($previewSrc, './uploads/') === 0) {
        $previewSrc = str_replace('./uploads/', '../uploads/', $previewSrc);
    } elseif (strpos($previewSrc, 'uploads/') === 0) {
        $previewSrc = '../' . $previewSrc;
    }
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

        .upload-btn-group {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: #f3f4f6;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            line-height: 1;
        }
        .upload-btn:hover { background: #e5e7eb; }
        .upload-btn svg {
            width: 16px;
            height: 16px;
            fill: #6b7280;
        }
        .upload-btn input[type="file"] { display: none; }

        .media-input {
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 13px;
            line-height: 1.6;
            min-height: 160px;
            padding: 12px;
            height: auto;
        }

        .md-editor-bar {
            display: inline-flex;
            flex-direction: column;
            gap: 4px;
            position: relative;
            width: 100%;
        }
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
            color: #374151;
            cursor: pointer;
            transition: all 0.2s;
            line-height: 1;
            width: fit-content;
        }
        .md-editor-toggle:hover {
            background: #e5e7eb;
        }
        .md-editor-arrow {
            transition: transform 0.2s;
            margin-left: 4px;
        }
        .md-editor-arrow.rotate {
            transform: rotate(180deg);
        }
        .md-editor-panel {
            display: none;
            flex-wrap: wrap;
            gap: 6px;
            padding: 10px;
            background: #fafafa;
            border: 1px solid #f0f0f0;
            border-radius: 10px;
            margin-top: 4px;
            width: 100%;
            box-sizing: border-box;
            position: relative;
        }
        .md-editor-panel.show {
            display: flex;
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
            width: 18px;
            height: 18px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s;
        }
        .md-color-dot:hover {
            transform: scale(1.15);
            border-color: #5b73e8;
        }
        .md-color-picker {
            position: relative;
            width: 18px;
            height: 18px;
            overflow: hidden;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid #e5e7eb;
            flex-shrink: 0;
        }
        .md-color-picker input[type="color"] {
            position: absolute;
            left: -50%;
            top: -50%;
            width: 200%;
            height: 200%;
            padding: 0;
            border: none;
            cursor: pointer;
            opacity: 0;
        }
        .md-hint-panel-row {
            display: none;
            gap: 6px;
            padding: 6px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            z-index: 20;
            width: max-content;
        }
        .md-hint-panel-row.show {
            display: flex;
        }
        .md-hint-item {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.15s;
            line-height: 1;
        }
        .md-hint-item:hover {
            background: #f3f4f6;
        }
        .hint-icon-info, .hint-icon-warn, .hint-icon-danger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            font-size: 10px;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
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
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            flex-basis: 100%;
            width: 100%;
            box-sizing: border-box;
            margin-top: 4px;
        }
        .md-hr-panel.show {
            display: flex;
        }
        .md-hr-dot {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            cursor: pointer;
            border: 1px solid #e5e7eb;
            transition: all 0.15s;
        }
        .md-hr-dot:hover {
            border-color: #5b73e8;
        }

        .bgmode-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .bgmode-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background: #fff;
            border: 2px solid #f0f0f0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
        }
        .bgmode-option:hover {
            border-color: #dbe4ff;
            background: #f8faff;
        }
        .bgmode-option.active {
            border-color: #5b73e8;
            background: #eef2ff;
            color: #5b73e8;
        }
        .bgmode-option input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: #5b73e8;
            cursor: pointer;
            flex-shrink: 0;
        }
        .bgmode-sub-options {
            display: flex;
            gap: 10px;
        }
        .bgmode-sub-option {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            background: #fff;
            border: 2px solid #f0f0f0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            flex: 1;
            justify-content: center;
        }
        .bgmode-sub-option:hover {
            border-color: #dbe4ff;
            background: #f8faff;
        }
        .bgmode-sub-option.active {
            border-color: #5b73e8;
            background: #eef2ff;
            color: #5b73e8;
        }
        .bgmode-sub-option input[type="radio"] {
            width: 16px;
            height: 16px;
            accent-color: #5b73e8;
            cursor: pointer;
            flex-shrink: 0;
        }
        .pixel-thumb {
            cursor: pointer;
            transition: transform 0.15s;
        }
        .pixel-thumb:hover {
            transform: translateY(-2px);
        }
        .pixel-thumb:hover img {
            border-color: #5b73e8;
        }
        .pixel-thumb.selected img {
            border-color: #5b73e8;
            box-shadow: 0 0 0 2px rgba(91,115,232,0.2);
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
            <div class="ap-card-title"><?php echo $is_edit ? '编辑文章' : '发布文章'; ?></div>
            <div style="display:flex; gap:10px;">
                <a href="articles.php" class="ap-btn ap-btn-secondary"><i class="fas fa-arrow-left"></i> 返回列表</a>
                <button type="button" onclick="document.getElementById('articleForm').submit();" class="ap-btn ap-btn-primary"><i class="fas fa-save"></i> 保存</button>
            </div>
        </div>
        <div class="ap-card-body">
            <form method="post" action="" id="articleForm" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo intval($article['id']); ?>">

                <div class="section-title-inline">基础信息</div>

                <div class="form-group-block">
                    <label class="form-label">文章标题</label>
                    <input type="text" name="title" class="form-input" value="<?php echo htmlspecialchars($article['title']); ?>" placeholder="输入文章标题" required>
                </div>

                <div class="form-group-block">
                    <label class="form-label">文章别名</label>
                    <input type="text" name="slug" class="form-input" value="<?php echo htmlspecialchars($article['slug']); ?>" placeholder="例如：xxx" required pattern="[a-zA-Z0-9_-]+" style="font-family: inherit;">
                    <div class="form-tips">用于URL访问 列：https://imoe.top/xxx.html</div>
                </div>

                <div class="form-group-block">
                    <label class="form-label">文章内容（支持HTML和Markdown语法）</label>

                    <div style="margin:6px 0 10px;">
                        <div class="md-editor-bar">
                            <button type="button" class="md-editor-toggle" onclick="mdEditorToggle(this)">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                <span>插入 MD 语法</span>
                                <svg class="md-editor-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <div class="md-editor-panel" id="mdEditorPanel">
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','bold')" title="粗体"><b>B</b></button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','italic')" title="斜体"><i>I</i></button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','strikethrough')" title="删除线"><s>S</s></button>
                                <button type="button" class="md-editor-btn" onclick="mdColorToggle(this)" title="文字颜色" style="position:relative;">A<span style="display:inline-block;width:8px;height:3px;background:#f5222d;vertical-align:bottom;margin-left:1px;border-radius:1px;"></span></button>
                                <div class="md-color-panel" id="mdColorPanel">
                                    <span class="md-color-dot" style="background:#f5222d;" onclick="mdInsertColor('articleContent','#f5222d')" title="红色"></span>
                                    <span class="md-color-dot" style="background:#eb2f96;" onclick="mdInsertColor('articleContent','#eb2f96')" title="粉色"></span>
                                    <span class="md-color-dot" style="background:#faad14;" onclick="mdInsertColor('articleContent','#faad14')" title="黄色"></span>
                                    <span class="md-color-dot" style="background:#52c41a;" onclick="mdInsertColor('articleContent','#52c41a')" title="绿色"></span>
                                    <span class="md-color-dot" style="background:#8B4513;" onclick="mdInsertColor('articleContent','#8B4513')" title="棕色"></span>
                                    <span class="md-color-dot" style="background:#722ed1;" onclick="mdInsertColor('articleContent','#722ed1')" title="紫色"></span>
                                    <span class="md-color-picker" title="自选颜色"><input type="color" onchange="mdInsertColor('articleContent',this.value)"></span>
                                </div>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','h1')">H1</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','quote')">引用</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','tabs')">标签卡</button>
                                <button type="button" class="md-editor-btn" onclick="mdHintToggle(this)" title="提示框">提示框</button>
                                <div class="md-hint-panel-row" id="mdHintPanel">
                                    <span class="md-hint-item" onclick="mdInsertHint('articleContent','info')"><span class="hint-icon-info">i</span>信息</span>
                                    <span class="md-hint-item" onclick="mdInsertHint('articleContent','warning')"><span class="hint-icon-warn">!</span>警告</span>
                                    <span class="md-hint-item" onclick="mdInsertHint('articleContent','danger')"><span class="hint-icon-danger">!</span>危险</span>
                                </div>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','btn')">按钮链接</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','code')">代码块</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','inlinecode')">行内代码</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','link')">链接</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','image')">图片</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','ul')">无序列表</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','ol')">有序列表</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','task')">任务列表</button>
                                <button type="button" class="md-editor-btn" onclick="mdHrToggle(this)" title="分割线">分割线</button>
                                <div class="md-hr-panel" id="mdHrPanel">
                                    <span class="md-hr-dot" style="background:repeating-linear-gradient(45deg,transparent,transparent 3px,#ccc 3px,#ccc 6px);" onclick="mdInsertHr('articleContent','stripe')" title="斜纹 @@@@"></span>
                                    <span class="md-hr-dot" style="background:radial-gradient(circle,#bbb 1.5px,transparent 1.5px);background-size:6px 6px;" onclick="mdInsertHr('articleContent','dots')" title="圆点 @@@@@"></span>
                                    <span class="md-hr-dot" style="border-top:2px dashed #bbb;background:transparent;height:0;border-radius:0;width:24px;" onclick="mdInsertHr('articleContent','line')" title="虚线 @@@"></span>
                                </div>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','table')">表格</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','sup')">上标</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','sub')">下标</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','center')">居中</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','details')">折叠详情</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','kbd')">键盘按键</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','file')">文件</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','github')">Github</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','bilibili')" title="嵌入B站视频">B站视频</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','linkcard')" title="外链卡片">外链</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','tag')">彩签</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','graybox')">备注</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','mark')">高亮</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','figure')">图文</button>
                                <button type="button" class="md-editor-btn" onclick="mdInsert('articleContent','timeline')">时间线</button>
                            </div>
                        </div>
                    </div>

                    <textarea name="content" id="articleContent" class="form-input media-input" placeholder="输入文章内容 支持HTML和Markdown语法..." style="font-family: inherit; line-height: 1.6;"><?php echo htmlspecialchars($article['content']); ?></textarea>
                    <div class="form-tips">支持HTML和Markdown语法 HTML标签包括 &lt;p&gt;、&lt;img&gt;、&lt;a&gt;、&lt;div&gt;、&lt;h2&gt; 等</div>
                </div>

                <div class="section-divider"></div>

                <div class="section-title-inline">卡片模式</div>
                <div class="form-group-block">
                    <div class="bgmode-options">
                        <label class="bgmode-option<?php echo intval($article['bg_mode']) === 0 ? ' active' : ''; ?>" id="bgmodeLabel0" onclick="selectBgMode(0)">
                            <input type="radio" name="bg_mode" value="0" <?php echo intval($article['bg_mode']) === 0 ? ' checked' : ''; ?> onchange="selectBgMode(0)">
                            <span>无图模式（极简白卡）</span>
                        </label>
                        <label class="bgmode-option<?php echo intval($article['bg_mode']) === 1 ? ' active' : ''; ?>" id="bgmodeLabel1" onclick="selectBgMode(1)">
                            <input type="radio" name="bg_mode" value="1" <?php echo intval($article['bg_mode']) === 1 ? ' checked' : ''; ?> onchange="selectBgMode(1)">
                            <span>图片模式（杂志风大图）</span>
                        </label>
                    </div>
                </div>

                <div class="form-group-block<?php echo intval($article['bg_mode']) === 0 ? '' : ' hidden'; ?>" id="summaryRow">
                    <label class="form-label">自定义摘要</label>
                    <textarea name="summary" id="articleSummary" class="form-input" style="min-height:80px; height:auto; line-height:1.6; padding:10px 12px;" placeholder="输入极简卡片中标题下方显示的内容，留空则自动截取文章前40字"><?php echo htmlspecialchars($article['summary']); ?></textarea>
                    <div class="form-tips">留空时默认从文章第一个字截取40个字，超过自动截断</div>
                </div>

                <div class="form-group-block" id="coverImageRow">
                    <label class="form-label">文章封面图（选填）</label>
                    <input type="text" name="cover_image" class="form-input" id="coverUrl" value="<?php echo htmlspecialchars($article['cover_image']); ?>" placeholder="填写图片URL链接或本地上传" style="margin-bottom:8px;">

                    <div class="upload-btn-group">
                        <label class="upload-btn" onclick="document.getElementById('coverFile').click()">
                            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            <span>上传图片</span>
                        </label>
                        <button type="button" class="upload-btn" onclick="clearCover()">
                            <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                            <span>清空图片</span>
                        </button>
                        <input type="file" name="cover_file" id="coverFile" accept="image/*" style="display:none;" onchange="handleCoverUpload(this)">
                    </div>

                    <?php if ($previewSrc): ?>
                    <div style="margin-top:8px;" id="coverPreviewWrap">
                        <img src="<?php echo htmlspecialchars($previewSrc); ?>" style="width:200px; height:120px; border-radius:6px; object-fit:cover; display:block;" id="coverPreview" onerror="this.parentElement.style.display='none'">
                    </div>
                    <?php endif; ?>
                    <div class="form-tips">支持填写URL或本地上传图片（最大5MB）</div>
                </div>

                <div class="form-group-block<?php echo intval($article['bg_mode']) === 1 ? ' hidden' : ''; ?>" id="magazineBgRow">
                    <label class="form-label">卡片背景</label>
                    <div class="bgmode-sub-options" style="display:flex; gap:10px; margin-bottom:12px;">
                        <label class="bgmode-sub-option active" id="bgTypeColor" onclick="selectBgType('color')">
                            <input type="radio" name="bg_type" value="color" checked onchange="selectBgType('color')">
                            <span>纯色背景</span>
                        </label>
                        <label class="bgmode-sub-option" id="bgTypePixel" onclick="selectBgType('pixel')">
                            <input type="radio" name="bg_type" value="pixel" onchange="selectBgType('pixel')">
                            <span>像素配图</span>
                        </label>
                    </div>

                    <div id="colorBgPanel">
                        <input type="hidden" name="bg_color" id="bgColorHidden" value="<?php echo htmlspecialchars($article['bg_color']); ?>">
                        <div class="color-row">
                            <input type="text" id="bgColorText" class="form-input" value="<?php echo htmlspecialchars($article['bg_color']); ?>" placeholder="#000000" onchange="updateBgColor(this.value)">
                            <input type="color" id="bgColorPicker" class="form-input" value="<?php echo htmlspecialchars($article['bg_color'] ?: '#000000'); ?>" oninput="updateBgColor(this.value)">
                            <button type="button" onclick="randomBgColor()" style="padding:8px 14px; border:0.5px solid #ccc; border-radius:10px; background:#f5f5f5; font-size:13px; cursor:pointer; color:#555; white-space:nowrap; font-weight:600;">随机配色</button>
                            <button type="button" onclick="clearBgColor()" style="padding:8px 14px; border:0.5px solid #ccc; border-radius:10px; background:#f5f5f5; font-size:13px; cursor:pointer; color:#555; white-space:nowrap; font-weight:600;">清除颜色</button>
                        </div>
                        <div class="form-tips">选择或输入颜色值；留空则使用默认黑色。封面图会在文章详情页顶部显示，图片模式下也会在首页以杂志风展示</div>
                    </div>

                    <div id="pixelBgPanel" style="display:none;">
                        <div class="pixel-grid" style="display:grid; grid-template-columns:repeat(4, 1fr); gap:10px;">
                            <?php
                            $pixel_images = [
                                'pixel-sunset.svg' => '像素夕阳',
                                'pixel-city.svg' => '像素城市',
                                'pixel-forest.svg' => '像素森林',
                                'pixel-night.svg' => '像素夜晚',
                                'pixel-ocean.svg' => '像素海洋',
                                'pixel-mountain.svg' => '像素山脉',
                                'pixel-desert.svg' => '像素沙漠',
                                'pixel-snow.svg' => '像素雪景',
                            ];
                            foreach ($pixel_images as $file => $name):
                                $url = '../assets/pixels/' . $file;
                            ?>
                            <div class="pixel-thumb" onclick="selectPixelImage('<?php echo $url; ?>')" title="<?php echo $name; ?>">
                                <img src="<?php echo $url; ?>" alt="<?php echo $name; ?>" style="width:100%; aspect-ratio:3/2; object-fit:cover; border-radius:8px; border:2px solid transparent; cursor:pointer;">
                                <span style="font-size:11px; color:#666; text-align:center; display:block; margin-top:4px;"><?php echo $name; ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-tips">点击喜欢的像素配图一键添加为封面</div>
                    </div>
                </div>

                <div class="form-group-block<?php echo intval($article['bg_mode']) === 1 ? '' : ' hidden'; ?>" id="magazinePixelRow">
                    <label class="form-label">杂志背景 — 像素配图</label>
                    <div class="pixel-grid" style="display:grid; grid-template-columns:repeat(4, 1fr); gap:10px;">
                        <?php foreach ($pixel_images as $file => $name): $url = '../assets/pixels/' . $file; ?>
                        <div class="pixel-thumb" onclick="selectPixelImage('<?php echo $url; ?>')" title="<?php echo $name; ?>">
                            <img src="<?php echo $url; ?>" alt="<?php echo $name; ?>" style="width:100%; aspect-ratio:3/2; object-fit:cover; border-radius:8px; border:2px solid transparent; cursor:pointer;">
                            <span style="font-size:11px; color:#666; text-align:center; display:block; margin-top:4px;"><?php echo $name; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-tips">点击像素配图即作为杂志背景（同时设为文章封面）；自定义图片请在上方“文章封面图”处上传</div>
                </div>

                <div class="section-divider"></div>

                <div class="section-title-inline">发布设置</div>

                <div class="form-group-block">
                    <label class="form-label">发布时间</label>
                    <input type="datetime-local" name="published_at" class="form-input" value="<?php echo $article['published_at'] ? date('Y-m-d\TH:i', strtotime($article['published_at'])) : ''; ?>">
                    <div class="form-tips">留空则使用当前时间</div>
                </div>

                <div class="select-grid">
                    <div class="form-group">
                        <label class="form-label">评论开关</label>
                        <select name="comment_enabled" class="form-input">
                            <option value="1"<?php echo intval($article['comment_enabled']) === 1 ? ' selected' : ''; ?>>开启</option>
                            <option value="0"<?php echo intval($article['comment_enabled']) === 0 ? ' selected' : ''; ?>>关闭</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">文章状态</label>
                        <select name="status" class="form-input">
                            <option value="1"<?php echo intval($article['status']) === 1 ? ' selected' : ''; ?>>显示</option>
                            <option value="0"<?php echo intval($article['status']) === 0 ? ' selected' : ''; ?>>隐藏</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">是否置顶</label>
                        <select name="is_top" class="form-input">
                            <option value="0"<?php echo intval($article['is_top']) === 0 ? ' selected' : ''; ?>>否</option>
                            <option value="1"<?php echo intval($article['is_top']) === 1 ? ' selected' : ''; ?>>是</option>
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
    showToast('<?php echo htmlspecialchars($toast_msg, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo (strpos($toast_msg, '失败') !== false || strpos($toast_msg, '错误') !== false || strpos($toast_msg, '不能') !== false || strpos($toast_msg, '不支持') !== false || strpos($toast_msg, '超过') !== false || strpos($toast_msg, '已存在') !== false || strpos($toast_msg, '已被') !== false) ? 'error' : 'success'; ?>');
    <?php endif; ?>
    var coverUrl = document.getElementById('coverUrl');
    if (coverUrl && coverUrl.value) {
        document.querySelectorAll('.pixel-thumb').forEach(function(el) {
            if (el.getAttribute('onclick').indexOf(coverUrl.value) !== -1) {
                el.classList.add('selected');
                selectBgType('pixel');
            }
        });
    }
});

function mdEditorToggle(btn) {
    var panel = document.getElementById('mdEditorPanel');
    var arrow = btn.querySelector('.md-editor-arrow');
    if (panel.classList.contains('show')) {
        panel.classList.remove('show');
        arrow.classList.remove('rotate');
        document.querySelectorAll('.md-color-panel.show, .md-hint-panel-row.show, .md-hr-panel.show').forEach(function(p) {
            p.classList.remove('show');
        });
    } else {
        panel.classList.add('show');
        arrow.classList.add('rotate');
    }
}

function mdInsert(id, type) {
    var textarea = document.getElementById(id);
    if (!textarea) return;
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    var text = textarea.value;
    var before = text.substring(0, start);
    var after = text.substring(end, text.length);
    var insert = '';
    switch(type) {
        case 'bold': insert = '**粗体文字**'; break;
        case 'italic': insert = '*斜体文字*'; break;
        case 'strikethrough': insert = '~~删除线~~'; break;
        case 'h1': insert = '# 标题\n'; break;
        case 'quote': insert = '> 引用内容\n'; break;
        case 'tabs': insert = '[tabs]\n[tab name="标签页 1"]内容 1[/tab]\n[tab name="标签页 2"]内容 2[/tab]\n[/tabs]\n'; break;
        case 'btn': insert = '[btn url="https://example.com"]按钮文字[/btn]'; break;
        case 'code': insert = '```\n代码块\n```\n'; break;
        case 'inlinecode': insert = '`行内代码`'; break;
        case 'link': insert = '[链接文字](https://example.com)'; break;
        case 'image': insert = '![图片描述](图片URL)'; break;
        case 'ul': insert = '[ul-square]\n- 列表项1\n- 列表项2\n- 列表项3\n[/ul-square]\n'; break;
        case 'ol': insert = '[ol-gray-bg]\n1. 列表项1\n2. 列表项2\n3. 列表项3\n[/ol-gray-bg]\n'; break;
        case 'task': insert = '- [ ] 任务1\n- [x] 任务2\n- [ ] 任务3\n'; break;
        case 'table': insert = '| 表头1 | 表头2 |\n| --- | --- |\n| 内容1 | 内容2 |\n'; break;
        case 'sup': insert = '<sup>上标</sup>'; break;
        case 'sub': insert = '<sub>下标</sub>'; break;
        case 'center': insert = '<center>居中内容</center>'; break;
        case 'details': insert = '<details>\n<summary>折叠标题</summary>\n折叠内容\n</details>\n'; break;
        case 'kbd': insert = '<kbd>Ctrl</kbd> + <kbd>C</kbd>'; break;
        case 'file': insert = '[file url="http://example.com/download.zip"]示例文件[/file]'; break;
        case 'github': insert = '[github repo="typecho/typecho" /]'; break;
        case 'bilibili': insert = '[bilibili]https://m.bilibili.com/video/BV1SW411w7to[/bilibili]'; break;
        case 'linkcard': insert = '[link][<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 16 16"><path fill="currentColor" d="M9.5 6.5L10 0H9L2 9.5h4.5L6 16h1l7-9.5z"></path></svg>][标题][描述][链接][/link]\n\n[link][图片链接][标题][描述][链接][/link]\n'; break;
        case 'tag': insert = '[tag color="#f5222d"]彩色标签[/tag]'; break;
        case 'graybox': insert = '[graybox]备注内容[/graybox]'; break;
        case 'mark': insert = '[mark]高亮文字[/mark]'; break;
        case 'figure': insert = '[figure src="图片URL" caption="图注说明"]'; break;
        case 'timeline': insert = '[timeline]\n- 时间show\n- 时间时间show\n[/timeline]\n'; break;
        default: insert = '';
    }
    textarea.value = before + insert + after;
    textarea.selectionStart = start + insert.length;
    textarea.selectionEnd = start + insert.length;
    textarea.focus();
}

function mdColorToggle(btn) {
    var panel = document.getElementById('mdColorPanel');
    if (!panel) return;
    document.querySelectorAll('.md-hint-panel-row.show, .md-hr-panel.show').forEach(function(p) {
        p.classList.remove('show');
    });
    panel.classList.toggle('show');
}

function mdInsertColor(id, color) {
    var textarea = document.getElementById(id);
    if (!textarea) return;
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    var text = textarea.value;
    var before = text.substring(0, start);
    var after = text.substring(end, text.length);
    var insert = '<span style="color:' + color + '">彩色文字</span>';
    textarea.value = before + insert + after;
    textarea.selectionStart = start + insert.length;
    textarea.selectionEnd = start + insert.length;
    textarea.focus();
    document.querySelectorAll('.md-color-panel.show').forEach(function(p) { p.classList.remove('show'); });
}

function mdHintToggle(btn) {
    var panel = document.getElementById('mdHintPanel');
    if (!panel) return;
    document.querySelectorAll('.md-color-panel.show, .md-hr-panel.show').forEach(function(p) {
        p.classList.remove('show');
    });
    panel.classList.toggle('show');
}

function mdInsertHint(id, type) {
    var textarea = document.getElementById(id);
    if (!textarea) return;
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    var text = textarea.value;
    var before = text.substring(0, start);
    var after = text.substring(end, text.length);
    var insert = '';
    switch(type) {
        case 'info': insert = '[hint title="提示标题"]\n提示内容\n[/hint]\n'; break;
        case 'warning': insert = '[hint type="warning" title="警告标题"]\n警告内容\n[/hint]\n'; break;
        case 'danger': insert = '[hint type="danger" title="危险标题"]\n危险内容\n[/hint]\n'; break;
    }
    textarea.value = before + insert + after;
    textarea.selectionStart = start + insert.length;
    textarea.selectionEnd = start + insert.length;
    textarea.focus();
    document.querySelectorAll('.md-hint-panel-row.show').forEach(function(p) { p.classList.remove('show'); });
}

function mdHrToggle(btn) {
    var panel = document.getElementById('mdHrPanel');
    if (!panel) return;
    document.querySelectorAll('.md-color-panel.show, .md-hint-panel-row.show').forEach(function(p) {
        p.classList.remove('show');
    });
    panel.classList.toggle('show');
}

function mdInsertHr(id, type) {
    var textarea = document.getElementById(id);
    if (!textarea) return;
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    var text = textarea.value;
    var before = text.substring(0, start);
    var after = text.substring(end, text.length);
    var insert = '';
    switch(type) {
        case 'stripe': insert = '\n@@@@\n'; break;
        case 'dots': insert = '\n@@@@@\n'; break;
        case 'line': insert = '\n@@@\n'; break;
    }
    textarea.value = before + insert + after;
    textarea.selectionStart = start + insert.length;
    textarea.selectionEnd = start + insert.length;
    textarea.focus();
    document.querySelectorAll('.md-hr-panel.show').forEach(function(p) { p.classList.remove('show'); });
}

function selectBgMode(mode) {
    document.querySelectorAll('.bgmode-option').forEach(function(el) {
        el.classList.remove('active');
        el.querySelector('input').checked = false;
    });
    var target = document.getElementById('bgmodeLabel' + mode);
    if (target) {
        target.classList.add('active');
        target.querySelector('input').checked = true;
    }
    var summaryRow = document.getElementById('summaryRow');
    if (summaryRow) {
        if (mode === 0) summaryRow.classList.remove('hidden');
        else summaryRow.classList.add('hidden');
    }
    var magazineBgRow = document.getElementById('magazineBgRow');
    if (magazineBgRow) {
        if (mode === 0) magazineBgRow.classList.remove('hidden');
        else magazineBgRow.classList.add('hidden');
    }
    var magazinePixelRow = document.getElementById('magazinePixelRow');
    if (magazinePixelRow) {
        if (mode === 1) magazinePixelRow.classList.remove('hidden');
        else magazinePixelRow.classList.add('hidden');
    }
}

function selectBgType(type) {
    document.querySelectorAll('.bgmode-sub-option').forEach(function(el) {
        el.classList.remove('active');
        el.querySelector('input').checked = false;
    });
    var target = document.getElementById('bgType' + (type === 'color' ? 'Color' : 'Pixel'));
    if (target) {
        target.classList.add('active');
        target.querySelector('input').checked = true;
    }
    var colorPanel = document.getElementById('colorBgPanel');
    var pixelPanel = document.getElementById('pixelBgPanel');
    if (colorPanel) colorPanel.style.display = type === 'color' ? 'block' : 'none';
    if (pixelPanel) pixelPanel.style.display = type === 'pixel' ? 'block' : 'none';
}

function selectPixelImage(url) {
    document.getElementById('coverUrl').value = url;
    var wrap = document.getElementById('coverPreviewWrap');
    var img = document.getElementById('coverPreview');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.id = 'coverPreviewWrap';
        wrap.style.marginTop = '8px';
        img = document.createElement('img');
        img.id = 'coverPreview';
        img.style.width = '200px';
        img.style.height = '120px';
        img.style.objectFit = 'cover';
        img.style.borderRadius = '6px';
        img.style.display = 'block';
        wrap.appendChild(img);
        var tips = document.querySelector('#coverImageRow .form-tips');
        if (tips) tips.parentNode.insertBefore(wrap, tips);
        else document.getElementById('coverImageRow').appendChild(wrap);
    }
    img.src = url;
    wrap.style.display = '';
    document.querySelectorAll('.pixel-thumb').forEach(function(el) { el.classList.remove('selected'); });
    var clicked = event.currentTarget;
    if (clicked) clicked.classList.add('selected');
}

function handleCoverUpload(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        var wrap = document.getElementById('coverPreviewWrap');
        var img = document.getElementById('coverPreview');
        reader.onload = function(e) {
            if (!wrap) {
                wrap = document.createElement('div');
                wrap.id = 'coverPreviewWrap';
                wrap.style.marginTop = '8px';
                img = document.createElement('img');
                img.id = 'coverPreview';
                img.style.width = '200px';
                img.style.height = '120px';
                img.style.objectFit = 'cover';
                img.style.borderRadius = '6px';
                img.style.display = 'block';
                wrap.appendChild(img);
                var tips = document.querySelector('#coverImageRow .form-tips');
                if (tips) {
                    tips.parentNode.insertBefore(wrap, tips);
                } else {
                    document.getElementById('coverImageRow').appendChild(wrap);
                }
            }
            img.src = e.target.result;
            wrap.style.display = '';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function clearCover() {
    document.getElementById('coverUrl').value = '';
    document.getElementById('coverFile').value = '';
    var wrap = document.getElementById('coverPreviewWrap');
    if (wrap) wrap.style.display = 'none';
    var img = document.getElementById('coverPreview');
    if (img) img.src = '';
}

function updateBgColor(color) {
    var hex = color || '';
    document.getElementById('bgColorHidden').value = hex;
    document.getElementById('bgColorText').value = hex;
    if (hex) {
        document.getElementById('bgColorPicker').value = hex;
    }
}

function clearBgColor() {
    updateBgColor('');
    document.getElementById('bgColorPicker').value = '#000000';
}

function randomBgColor() {
    var color = '#' + Math.floor(Math.random() * 16777215).toString(16).padStart(6, '0');
    updateBgColor(color);
}
</script>
</body>
</html>
