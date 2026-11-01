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
$page_title = '上传管理';
$page_id = 'uploads';

$upload_dir = __DIR__ . '/../uploads';
$upload_url_base = 'uploads/';

function json_response($code, $msg) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => $code, 'msg' => $msg]);
    exit;
}

if (isset($_GET['ajax']) && isset($_GET['action'])) {
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
    $action = $_GET['action'];

    if ($action === 'delete' && isset($_GET['file'])) {
        $file = $_GET['file'];
        $file_path = realpath($upload_dir . '/' . $file);
        $real_upload_dir = realpath($upload_dir);
        if ($file_path && strpos($file_path, $real_upload_dir) === 0 && file_exists($file_path)) {
            if (unlink($file_path)) {
                $dir = dirname($file_path);
                while ($dir !== $real_upload_dir && @rmdir($dir)) {
                    $dir = dirname($dir);
                }
                json_response(1, '删除成功');
            } else {
                json_response(0, '删除失败');
            }
        } else {
            json_response(0, '文件不存在或路径非法');
        }
    }

    if ($action === 'clear_all') {
        function clearDirectory($dir) {
            $files = array_diff(scandir($dir), array('.','..'));
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    clearDirectory($path);
                    rmdir($path);
                } else {
                    unlink($path);
                }
            }
        }
        if (is_dir($upload_dir)) {
            clearDirectory($upload_dir);
            json_response(1, '已清空所有上传文件');
        } else {
            json_response(0, '上传目录不存在');
        }
    }

    if ($action === 'create_dir') {
        if (!is_dir($upload_dir)) {
            if (mkdir($upload_dir, 0755, true)) {
                json_response(1, '目录创建成功');
            } else {
                json_response(0, '目录创建失败');
            }
        } else {
            json_response(1, '目录已存在');
        }
    }

    json_response(0, '未知操作');
}

$files = [];
function scanUploads($dir, $base_url, &$files) {
    if (!is_dir($dir)) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $rel_path = str_replace(realpath($dir) . '/', '', realpath($file->getPathname()));
            $files[] = [
            'name' => $file->getFilename(),
            'path' => $rel_path,
            'url' => $base_url . $rel_path,
            'size' => $file->getSize(),
            'time' => $file->getMTime(),
            'type' => getFileType($file->getFilename())
            ];
        }
    }
}
function getFileType($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $image_exts = ['jpg','jpeg','png','gif','webp','bmp'];
    $video_exts = ['mp4','webm','mov','avi','mkv'];
    if (in_array($ext, $image_exts)) return 'image';
    if (in_array($ext, $video_exts)) return 'video';
    return 'other';
}
function formatSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes/1024, 1) . ' KB';
    if ($bytes < 1073741824) return round($bytes/1048576, 1) . ' MB';
    return round($bytes/1073741824, 2) . ' GB';
}
scanUploads($upload_dir, $upload_url_base, $files);
usort($files, function($a, $b) { return $b['time'] - $a['time']; });
$total_files = count($files);
$total_size = array_sum(array_column($files, 'size'));
$image_count = count(array_filter($files, function($f){return $f['type']==='image';}));
$video_count = count(array_filter($files, function($f){return $f['type']==='video';}));
$upload_dir_exists = is_dir($upload_dir);

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

        .ap-empty {
                text-align: center;
                padding: 60px 20px;
                color: #9ca3af;
                font-size: 15px;
        }
        .ap-empty i { font-size: 48px; color: #e5e7eb; margin-bottom: 12px; display: block; }

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

        .stats-bar {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
                padding: 16px;
        }
        .stats-item {
                background: #fff;
                border-radius: 16px;
                padding: 20px;
                display: flex;
                align-items: center;
                gap: 14px;
                border: 1px solid #f0f0f0;
                box-shadow: 0 1px 3px rgba(0,0,0,0.02);
                transition: all 0.25s ease;
        }
        .stats-item:hover {
                box-shadow: 0 4px 16px rgba(0,0,0,0.06);
                transform: translateY(-1px);
        }
        .stats-icon {
                width: 48px;
                height: 48px;
                border-radius: 14px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 20px;
                flex-shrink: 0;
                transition: all 0.35s cubic-bezier(0.4,0,0.2,1);
                position: relative;
        }
        .stats-icon::before {
                content: '';
                position: absolute;
                inset: 0;
                border-radius: 14px;
                opacity: 0.12;
        }
        .stats-icon.blue { color: #5b73e8; }
        .stats-icon.blue::before { background: #5b73e8; }
        .stats-icon.green { color: #2ca87f; }
        .stats-icon.green::before { background: #2ca87f; }
        .stats-icon.orange { color: #e58a00; }
        .stats-icon.orange::before { background: #e58a00; }
        .stats-icon.cyan { color: #3ec9d6; }
        .stats-icon.cyan::before { background: #3ec9d6; }
        .stats-item:hover .stats-icon {
                transform: translateY(-2px) scale(1.06);
        }
        .stats-info {
                display: flex;
                flex-direction: column;
                gap: 4px;
        }
        .stats-num {
                font-size: 22px;
                font-weight: 800;
                color: #1f2937;
                line-height: 1;
                letter-spacing: -0.5px;
                font-variant-numeric: tabular-nums;
        }
        .stats-text {
                font-size: 13px;
                color: #9ca3af;
                font-weight: 500;
        }

        .file-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
                padding: 12px;
        }
        @media (min-width: 768px) {
                .stats-bar { grid-template-columns: repeat(4, 1fr); gap: 12px; }
                .file-grid { grid-template-columns: repeat(3, 1fr); gap: 12px; padding: 16px; }
        }
        @media (min-width: 1024px) {
                .file-grid { grid-template-columns: repeat(4, 1fr); }
        }
        @media (min-width: 1280px) {
                .file-grid { grid-template-columns: repeat(5, 1fr); }
        }

        .file-card {
                background: #fff;
                border-radius: 16px;
                border: 1px solid #f0f0f0;
                overflow: hidden;
                transition: all 0.3s ease;
                display: flex;
                flex-direction: column;
        }
        .file-card:hover {
                box-shadow: 0 6px 24px rgba(0,0,0,0.05);
                transform: translateY(-2px);
                border-color: #e5e7eb;
        }
        .file-thumb {
                position: relative;
                aspect-ratio: 1;
                background: #f8f9fa;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
        }
        .file-thumb img {
                width: 100%;
                height: 100%;
                object-fit: cover;
        }
        .file-type-tag {
                position: absolute;
                top: 6px;
                left: 6px;
                padding: 2px 6px;
                border-radius: 20px;
                font-size: 9px;
                font-weight: 600;
                color: #fff;
                z-index: 2;
        }
        .file-type-tag.image { background: #5b73e8; }
        .file-type-tag.video { background: #e58a00; }
        .file-type-tag.other { background: #6b7280; }
        .video-icon, .other-icon {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 6px;
                color: #9ca3af;
        }
        .video-icon svg { width: 32px; height: 32px; }
        .other-icon svg { width: 32px; height: 32px; }
        .video-icon span, .other-icon span {
                font-size: 11px;
                font-weight: 500;
        }
        .file-info {
                padding: 10px;
                flex: 1;
                display: flex;
                flex-direction: column;
        }
        .file-name {
                font-size: 12px;
                font-weight: 600;
                color: #1f2937;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                margin-bottom: 4px;
        }
        .file-meta {
                display: flex;
                justify-content: space-between;
                font-size: 10px;
                color: #9ca3af;
                margin-bottom: 8px;
        }
        .file-actions {
                display: flex;
                gap: 4px;
                margin-top: auto;
        }
        .file-actions .ap-btn { flex: 1; padding: 5px 4px; font-size: 11px; }

        .empty-uploads {
                text-align: center;
                padding: 60px 20px;
                color: #9ca3af;
        }
        .empty-uploads svg {
                width: 64px;
                height: 64px;
                color: #e5e7eb;
                margin-bottom: 16px;
        }
        .empty-uploads p {
                font-size: 15px;
                margin-bottom: 4px;
        }
        .empty-uploads .sub {
                font-size: 13px;
                color: #d1d5db;
        }
        .dir-missing {
                text-align: center;
                padding: 60px 20px;
        }
        .dir-missing p {
                font-size: 15px;
                color: #9ca3af;
                margin-bottom: 16px;
        }

        .bottom-bar {
                display: flex;
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
                padding: 14px 16px;
        }
        @media (min-width: 768px) {
                .bottom-bar {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                padding: 16px 20px;
                }
        }
        .bottom-bar .info {
                font-size: 13px;
                color: #6b7280;
        }
        .bottom-bar .info strong {
                color: #1f2937;
                font-weight: 600;
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
                .ap-btn { padding: 8px 14px; font-size: 13px; border-radius: 12px; }
                .ap-btn-xs { padding: 5px 6px; font-size: 10px; border-radius: 6px; }

                .stats-bar { padding: 12px; gap: 8px; }
                .stats-item { padding: 14px; gap: 10px; }
                .stats-icon { width: 40px; height: 40px; font-size: 16px; border-radius: 12px; }
                .stats-icon::before { border-radius: 12px; }
                .stats-num { font-size: 18px; }
                .stats-text { font-size: 12px; }

                .file-grid { gap: 10px; padding: 12px; }
                .file-info { padding: 10px; }
                .file-actions .ap-btn { padding: 5px 4px; font-size: 11px; }

                .bottom-bar { padding: 14px 16px; }
                .bottom-bar .info { text-align: center; font-size: 13px; }
                .bottom-bar .ap-btn { width: 100%; justify-content: center; }
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
                <h1 class="text-lg font-bold text-gray-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
                </div>
        </div>
    </header>

    <main id="mainContent" class="flex-1 p-4 lg:p-8">
        <div id="contentArea">

<div class="fade-in">

    <div class="ap-card mb-5">
        <div class="stats-bar">
                <div class="stats-item">
                <div class="stats-icon blue"><i class="fas fa-folder-open"></i></div>
                <div class="stats-info">
                    <div class="stats-num"><?php echo $total_files; ?></div>
                    <div class="stats-text">文件总数</div>
                </div>
                </div>
                <div class="stats-item">
                <div class="stats-icon green"><i class="fas fa-database"></i></div>
                <div class="stats-info">
                    <div class="stats-num"><?php echo formatSize($total_size); ?></div>
                    <div class="stats-text">占用空间</div>
                </div>
                </div>
                <div class="stats-item">
                <div class="stats-icon orange"><i class="fas fa-image"></i></div>
                <div class="stats-info">
                    <div class="stats-num"><?php echo $image_count; ?></div>
                    <div class="stats-text">图片文件</div>
                </div>
                </div>
                <div class="stats-item">
                <div class="stats-icon cyan"><i class="fas fa-video"></i></div>
                <div class="stats-info">
                    <div class="stats-num"><?php echo $video_count; ?></div>
                    <div class="stats-text">视频文件</div>
                </div>
                </div>
        </div>
    </div>

    <div class="ap-card mb-5">
        <div class="ap-card-header">
                <div class="ap-card-title">文件列表 <span>（<?php echo $total_files; ?> 个）</span></div>
        </div>
        <div class="ap-card-body" style="padding:0;">
                <?php if (!$upload_dir_exists): ?>
                <div class="dir-missing">
                    <p>上传目录不存在</p>
                    <button class="ap-btn ap-btn-primary" onclick="createUploadDir()"><i class="fas fa-folder-plus"></i> 创建 uploads 目录</button>
                </div>
                <?php elseif (empty($files)): ?>
                <div class="empty-uploads">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="12" y1="18" x2="12" y2="12"></line>
                                <line x1="9" y1="15" x2="15" y2="15"></line>
                    </svg>
                    <p>暂无上传文件</p>
                    <p class="sub">uploads 目录为空</p>
                </div>
                <?php else: ?>
                <div class="file-grid">
                    <?php foreach ($files as $file): ?>
                    <div class="file-card" data-path="<?php echo htmlspecialchars($file['path']); ?>">
                                <div class="file-thumb">
                            <span class="file-type-tag <?php echo $file['type']; ?>">
                                <?php echo $file['type'] === 'image' ? '图片' : ($file['type'] === 'video' ? '视频' : '文件'); ?>
                            </span>
                            <?php if ($file['type'] === 'image'): ?>
                                <img src="../<?php echo htmlspecialchars($file['url']); ?>" alt="<?php echo htmlspecialchars($file['name']); ?>" loading="lazy">
                            <?php elseif ($file['type'] === 'video'): ?>
                                <div class="video-icon">
                                                <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M8 5v14l11-7z"/>
                                                </svg>
                                                <span>视频</span>
                                </div>
                            <?php else: ?>
                                <div class="other-icon">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                        <polyline points="14 2 14 8 20 8"></polyline>
                                                </svg>
                                                <span><?php echo htmlspecialchars(strtoupper(pathinfo($file['name'], PATHINFO_EXTENSION))); ?></span>
                                </div>
                            <?php endif; ?>
                                </div>
                                <div class="file-info">
                            <div class="file-name" title="<?php echo htmlspecialchars($file['name']); ?>">
                                <?php echo htmlspecialchars($file['name']); ?>
                            </div>
                            <div class="file-meta">
                                <span><?php echo formatSize($file['size']); ?></span>
                                <span><?php echo date('m-d H:i', $file['time']); ?></span>
                            </div>
                            <div class="file-actions">
                                <a href="../<?php echo htmlspecialchars($file['url']); ?>" target="_blank" class="ap-btn ap-btn-xs ap-btn-primary"><i class="fas fa-eye"></i></a>
                                <button class="ap-btn ap-btn-xs ap-btn-danger" onclick="deleteFile('<?php echo htmlspecialchars(addslashes($file['path'])); ?>')"><i class="fas fa-trash"></i></button>
                            </div>
                                </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
        </div>
    </div>

    <?php if ($upload_dir_exists && $total_files > 0): ?>
    <div class="ap-card">
        <div class="bottom-bar">
                <div class="info">
                共 <strong><?php echo $total_files; ?></strong> 个文件，占用空间 <strong><?php echo formatSize($total_size); ?></strong>
                </div>
                <button class="ap-btn ap-btn-danger ap-btn-sm" onclick="clearAll()">
                <i class="fas fa-trash-alt"></i> 清空全部
                </button>
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
                <span class="text-gray-400 mx-1">&copy;</span>
                <span>2025 朋友圈</span>
                </p>
        </div>
    </footer>
</div>
<script>
const CSRF_TOKEN = '<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>';
let pendingDeleteFile = null;

function deleteFile(path) {
    pendingDeleteFile = path;
    document.getElementById('confirmMsg').textContent = '确认删除该文件？';
    document.getElementById('confirmDialog').classList.add('show');
}

function clearAll() {
    pendingDeleteFile = '__ALL__';
    document.getElementById('confirmMsg').textContent = '确认清空所有上传文件？此操作不可恢复！';
    document.getElementById('confirmDialog').classList.add('show');
}

function createUploadDir() {
    fetch('?ajax=1&action=create_dir&csrf_token=' + encodeURIComponent(CSRF_TOKEN))
    .then(r => r.json())
    .then(data => {
        showToast(data.msg, data.code === 1 ? 'success' : 'error');
        if (data.code === 1) setTimeout(() => location.reload(), 800);
    })
    .catch(() => showToast('网络错误', 'error'));
}

function closeConfirm() {
    document.getElementById('confirmDialog').classList.remove('show');
    pendingDeleteFile = null;
}

function doConfirmAction() {
    if (!pendingDeleteFile) return;

    if (pendingDeleteFile === '__ALL__') {
        closeConfirm();
        fetch('?ajax=1&action=clear_all&csrf_token=' + encodeURIComponent(CSRF_TOKEN))
        .then(r => r.json())
        .then(data => {
            showToast(data.msg, data.code === 1 ? 'success' : 'error');
            if (data.code === 1) setTimeout(() => location.reload(), 800);
        })
        .catch(() => showToast('网络错误', 'error'));
        return;
    }

    const path = pendingDeleteFile;
    closeConfirm();
    fetch('?ajax=1&action=delete&file=' + encodeURIComponent(path) + '&csrf_token=' + encodeURIComponent(CSRF_TOKEN))
    .then(r => r.json())
    .then(data => {
        if (data.code === 1) {
            const card = document.querySelector('.file-card[data-path="' + CSS.escape(path) + '"]');
            if (card) {
                card.style.transition = 'all 0.3s ease';
                card.style.opacity = '0';
                card.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    card.remove();
                    if (document.querySelectorAll('.file-card').length === 0) {
                        setTimeout(() => location.reload(), 300);
                    }
                }, 300);
            }
            showToast('删除成功', 'success');
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

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($toast_msg): ?>
    showToast('<?php echo htmlspecialchars($toast_msg, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo (strpos($toast_msg, '失败') !== false || strpos($toast_msg, '无效') !== false || strpos($toast_msg, '未选择') !== false) ? 'error' : 'success'; ?>');
    <?php endif; ?>
});
</script>
</body>
</html>
