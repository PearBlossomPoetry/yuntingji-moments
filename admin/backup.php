<?php
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

@set_time_limit(0);
@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '512M');
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');

require_once '../config.php';
require_once __DIR__ . '/restore_embed.php';
require_once __DIR__ . '/backup_lib.php';
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
$page_title = '数据备份';
$page_id = 'backup';
$settings = get_all_settings();

function sql_escape($conn, $value)
{
    if ($value === null) return 'NULL';
    return "'" . mysqli_real_escape_string($conn, $value) . "'";
}

function get_table_create($conn, $table)
{
    $result = mysqli_query($conn, "SHOW CREATE TABLE `" . mysqli_real_escape_string($conn, $table) . "`");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return "\n-- 表结构 `" . $table . "`\n" .
               "DROP TABLE IF EXISTS `" . $table . "`;\n" .
               $row['Create Table'] . ";\n";
    }
    return '';
}

function get_table_inserts($conn, $table)
{
    $sql = "";
    $result = mysqli_query($conn, "SELECT * FROM `" . mysqli_real_escape_string($conn, $table) . "`");
    if (!$result) return $sql;

    $columns = [];
    foreach (mysqli_fetch_fields($result) as $field) {
        $columns[] = "`" . $field->name . "`";
    }

    $batch = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $values = [];
        foreach ($row as $val) {
            $values[] = sql_escape($conn, $val);
        }
        $batch[] = "(" . implode(", ", $values) . ")";
    }

    if (!empty($batch)) {
        $sql .= "\n-- 数据 `" . $table . "`\n";
        $sql .= "INSERT INTO `" . $table . "` (" . implode(", ", $columns) . ") VALUES\n";
        $sql .= implode(",\n", $batch) . ";\n";
    }
    return $sql;
}

function add_dir_to_zip($zip, $dir, $base_len, $prefix = '')
{
    if (!is_dir($dir)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $file) {
        $real_path = $file->getRealPath();
        $relative = substr($real_path, $base_len);
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $zip_path = $prefix ? $prefix . '/' . $relative : $relative;
        if ($file->isDir()) {
            $zip->addEmptyDir($zip_path);
        } else {
            $zip->addFile($real_path, $zip_path);
        }
    }
}

function rrmdir($dir)
{
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? rrmdir($path) : unlink($path);
    }
    rmdir($dir);
}

$backups_dir = __DIR__ . '/../backups';
if (!is_dir($backups_dir)) @mkdir($backups_dir, 0755, true);
ytj_ensure_dir_protected($backups_dir);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['download'])) {
    $download_file = basename($_GET['download']);
    $download_path = $backups_dir . '/' . $download_file;
    if (preg_match('/^[^\/\\\\]+\\.zip$/i', $download_file) && file_exists($download_path)) {
        while (ob_get_level()) @ob_end_clean();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $download_file . '"');
        header('Content-Length: ' . filesize($download_path));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        $handle = fopen($download_path, 'rb');
        if ($handle) {
            while (!feof($handle)) {
                echo fread($handle, 8192);
                flush();
            }
            fclose($handle);
        }
        exit;
    }
}

$toast_msg = $_SESSION['admin_toast'] ?? '';
if ($toast_msg) unset($_SESSION['admin_toast']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_backup'])) {
    if (!csrf_validate()) {
        $_SESSION['admin_toast'] = '安全令牌无效 请刷新页面重试';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $tables = [];
    $res = mysqli_query($conn, "SHOW TABLES");
    if ($res) {
        while ($row = mysqli_fetch_row($res)) {
            $tables[] = $row[0];
        }
    }

    if (empty($tables)) {
        $_SESSION['admin_toast'] = '未读取到任何数据表，请检查数据库连接';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $date_str = date('Ymd_His');
    $site_name = preg_replace('/[^a-zA-Z0-9\x{4e00}-\x{9fa5}_-]/u', '', $settings['site_name'] ?? 'backup');
    $backup_name = $site_name . '_backup_' . $date_str;

    $tmp_dir = sys_get_temp_dir() . '/' . uniqid('backup_', true);
    if (!is_dir($tmp_dir)) mkdir($tmp_dir, 0755, true);

    $zip_file = $backups_dir . '/' . $backup_name . '.zip';

    $sql_file = $tmp_dir . '/database.sql';
    $sql = "-- 全站数据备份\n";
    $sql .= "-- 备份时间：" . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- 数据库：" . DB_NAME . "\n";
    $sql .= "SET NAMES utf8mb4;\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n";

    foreach ($tables as $table) {
        $sql .= get_table_create($conn, $table);
        $sql .= get_table_inserts($conn, $table);
    }

    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    file_put_contents($sql_file, $sql);

    $readme = $tmp_dir . '/README.txt';
    $readme_text = "网站迁移说明\n";
    $readme_text .= "====================\n\n";
    $readme_text .= "1. 将本压缩包上传到新的服务器/虚拟主机并解压。\n";
    $readme_text .= "2. 访问 restore.php，选择本压缩包并上传。\n";
    $readme_text .= "3. 填写新数据库信息（程序会自动创建数据库并导入 database.sql）。\n";
    $readme_text .= "4. 恢复完成后，删除 restore.php 文件，访问网站首页即可。\n\n";
    $readme_text .= "注意：若新环境的域名或目录发生变化，请检查 settings 表中的相关 URL 配置。\n";
    file_put_contents($readme, $readme_text);

    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        $_SESSION['admin_toast'] = '创建压缩包失败，请检查服务器是否支持 ZipArchive';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    try {
        $zip->addFile($sql_file, 'database.sql');
        $zip->addFile($readme, 'README.txt');

        $uploads_dir = realpath(__DIR__ . '/../uploads');
        if ($uploads_dir && is_dir($uploads_dir)) {
            add_dir_to_zip($zip, $uploads_dir, strlen($uploads_dir), 'uploads');
        }

        $root_dir = realpath(__DIR__ . '/..');
        $exclude = ['db.config.php', 'install.lock', 'a.zip', 'a_modified.zip', 'friendlink_modified.zip', 'restore.php'];
        if ($root_dir && is_dir($root_dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                $real_path = $file->getRealPath();
                if ($real_path === false) continue;
                $relative = substr($real_path, strlen($root_dir));
                $relative = ltrim(str_replace('\\', '/', $relative), '/');
                $basename = basename($relative);
                if (in_array($basename, $exclude)) continue;
                if (in_array($relative, $exclude)) continue;
                if (strpos($relative, 'a_modified.zip') !== false) continue;
                if (strpos($relative, 'friendlink_modified.zip') !== false) continue;
                if (strpos($relative, '.sources/') === 0) continue;
                if (strpos($relative, 'skills/') === 0) continue;
                if ($file->isDir()) {
                    $zip->addEmptyDir($relative);
                } else {
                    $zip->addFile($real_path, $relative);
                }
            }
        }

        $restore_file = __DIR__ . '/../restore.php';
        if (file_exists($restore_file)) {
            $zip->addFile($restore_file, 'restore.php');
        } elseif (isset($restore_embed_source) && $restore_embed_source !== '') {
            $temp_restore = $tmp_dir . '/restore.php';
            file_put_contents($temp_restore, base64_decode($restore_embed_source));
            $zip->addFile($temp_restore, 'restore.php');
        }

        $zip->close();
    } catch (Throwable $e) {
        $zip->close();
        @unlink($zip_file);
        @rrmdir($tmp_dir);
        $_SESSION['admin_toast'] = '打包失败：' . $e->getMessage();
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    if (!file_exists($zip_file) || filesize($zip_file) < 100) {
        @unlink($zip_file);
        @rrmdir($tmp_dir);
        $_SESSION['admin_toast'] = '生成的备份包无效 请检查服务器磁盘空间或 PHP 配置';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    @rrmdir($tmp_dir);

    $backup_files = glob($backups_dir . '/*.zip');
    if ($backup_files && count($backup_files) > 10) {
        usort($backup_files, function ($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        $to_delete = array_slice($backup_files, 0, count($backup_files) - 10);
        foreach ($to_delete as $old) @unlink($old);
    }

    header('Location: backup.php?download=' . urlencode($backup_name . '.zip'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_auto_backup'])) {
    if (!csrf_validate()) {
        $_SESSION['admin_toast'] = '安全令牌无效 请刷新页面重试';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    $bool_keys = ['auto_backup_enabled', 'auto_backup_local_enabled', 'auto_backup_ftp_enabled', 'auto_backup_email_enabled'];
    foreach ($bool_keys as $k) {
        set_setting($k, ($_POST[$k] ?? '0') === '1' ? '1' : '0');
    }
    set_setting('auto_backup_frequency', in_array($_POST['auto_backup_frequency'] ?? 'daily', ['daily', 'every3', 'weekly']) ? $_POST['auto_backup_frequency'] : 'daily');
    set_setting('auto_backup_keep', (string) max(1, intval($_POST['auto_backup_keep'] ?? 30)));
    set_setting('auto_backup_keep_days', (string) max(0, intval($_POST['auto_backup_keep_days'] ?? 0)));
    set_setting('auto_backup_ftp_type', ($_POST['auto_backup_ftp_type'] ?? 'ftp') === 'sftp' ? 'sftp' : 'ftp');
    set_setting('auto_backup_smtp_secure', ($_POST['auto_backup_smtp_secure'] ?? 'ssl') === 'tls' ? 'tls' : 'ssl');

    foreach (['auto_backup_ftp_host', 'auto_backup_ftp_port', 'auto_backup_ftp_user', 'auto_backup_ftp_dir', 'auto_backup_email_to', 'auto_backup_smtp_host', 'auto_backup_smtp_port', 'auto_backup_smtp_user', 'auto_backup_email_from'] as $k) {
        set_setting($k, trim((string) ($_POST[$k] ?? '')));
    }

    foreach (['auto_backup_ftp_pass', 'auto_backup_smtp_pass'] as $k) {
        if (($_POST[$k] ?? '') !== '') {
            set_setting($k, trim((string) $_POST[$k]));
        }
    }

    if (empty(get_setting('auto_backup_cron_key'))) {
        set_setting('auto_backup_cron_key', bin2hex(random_bytes(16)));
    }
    $_SESSION['admin_toast'] = '自动备份设置已保存';
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_auto_now'])) {
    if (!csrf_validate()) {
        $_SESSION['admin_toast'] = '安全令牌无效 请刷新页面重试';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    $r = ytj_run_auto_backup();
    $_SESSION['admin_toast'] = ($r['ok'] ? '自动备份执行成功：' : '自动备份执行失败：') . $r['msg'];
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

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

        .info-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 14px;
            color: #475569;
            line-height: 1.6;
        }
        .info-item .info-num {
            color: #1e293b;
            font-size: 14px;
            width: 22px;
            text-align: left;
            flex-shrink: 0;
            font-weight: 600;
        }
        .info-item strong { color: #1e293b; }
        .info-item code {
            background: #f1f5f9;
            padding: 1px 5px;
            border-radius: 4px;
            font-size: 12px;
            color: #334155;
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
        .ap-toast.show { transform: translateX(0); opacity: 1; }
        .ap-toast-icon {
            width: 24px; height: 24px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            flex-shrink: 0; font-size: 11px;
        }
        .ap-toast-icon.success { background: #2ca87f; color: #fff; }
        .ap-toast-icon.error { background: #dc2626; color: #fff; }

        .fade-in { animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity:0; transform: translateY(8px); } to { opacity:1; transform: translateY(0); } }

        @media (max-width: 768px) {
            .ap-toast {
                bottom: calc(20px + env(safe-area-inset-bottom, 0px));
                right: 12px; padding: 10px 14px; font-size: 12px;
                max-width: calc(100vw - 24px);
            }
            .ap-toast-icon { width: 22px; height: 22px; font-size: 11px; }
            .ap-card-header { padding: 16px 20px; }
            .ap-card-body { padding: 20px; }
            .ap-btn { padding: 8px 14px; font-size: 13px; border-radius: 12px; }
        }
        select {
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            appearance: none !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: right 12px center !important;
            background-size: 12px !important;
            padding-right: 32px !important;
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
        <div class="fade-in">
            <div class="ap-card">
                <div class="ap-card-header">
                    <div class="ap-card-title">一键全站备份</div>
                </div>
                <div class="ap-card-body">
                    <div class="info-list mb-6">
                        <div class="info-item">
                            <span class="info-num">1.</span>
                            <span><strong>网站数据</strong>：包含动态、文章、独立页面、评论、留言、标签、管理员、系统设置等所有数据表</span>
                        </div>
                        <div class="info-item">
                            <span class="info-num">2.</span>
                            <span><strong>上传文件</strong>：包含 <code>uploads/</code> 目录下的所有图片、头像、封面等文件</span>
                        </div>
                        <div class="info-item">
                            <span class="info-num">3.</span>
                            <span><strong>网站程序</strong>：备份包内已包含所有程序文件 在新服务器上传解压后访问 <code>restore.php</code> 即可恢复</span>
                        </div>
                        <div class="info-item">
                            <span class="info-num">4.</span>
                            <span><strong>迁移说明</strong>：包内含有 <code>README.txt</code> 和 <code>restore.php</code> 按提示填写数据库即可自动恢复</span>
                        </div>
                    </div>
                    <form method="post" action="">
                        <?php echo csrf_token_field(); ?>
                        <button type="submit" name="do_backup" class="ap-btn ap-btn-primary">
                            <i class="fas fa-download"></i>
                            立即备份并下载
                        </button>
                    </form>
                </div>
            </div>

            <div class="ap-card" style="margin-top:24px;">
                <div class="ap-card-header">
                    <div class="ap-card-title">自动备份设置</div>
                </div>
                <div class="ap-card-body" style="padding-top:16px;">
                    <?php
                    $last_ts = intval($settings['auto_backup_last_time'] ?? 0);
                    $last_status = $settings['auto_backup_last_status'] ?? '';
                    $last_msg = $settings['auto_backup_last_msg'] ?? '';
                    $cron_key = get_setting('auto_backup_cron_key', '');
                    $cron_rel = $cron_key !== '' ? ('admin/auto_backup.php?cron=1&token=' . $cron_key) : '';
                    $cron_full = $cron_rel !== '' ? ('https://' . ($_SERVER['HTTP_HOST'] ?? '你的域名') . '/' . $cron_rel) : '';
                    ?>
                    <form method="post" action="">
                        <?php echo csrf_token_field(); ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:0 0 10px;border-bottom:1px solid #f1f5f9;">
                            <div>
                                <div style="font-weight:600;color:#1f2937;font-size:14px;">启用自动备份</div>
                                <div style="font-size:12px;color:#94a3b8;margin-top:2px;">开启后系统按设定频率自动生成备份包 并通过下方方式投递</div>
                            </div>
                            <input type="checkbox" name="auto_backup_enabled" value="1" <?php echo ($settings['auto_backup_enabled']??'0')==='1'?'checked':''; ?> style="width:18px;height:18px;">
                        </div>

                        <div style="padding:14px 0;border-bottom:1px solid #f1f5f9;">
                            <div style="font-weight:600;color:#1f2937;font-size:14px;margin-bottom:10px;">备份频率</div>
                            <label style="display:inline-flex;align-items:center;margin-right:20px;font-size:14px;color:#475569;cursor:pointer;gap:6px;">
                                <input type="radio" name="auto_backup_frequency" value="daily" <?php echo ($settings['auto_backup_frequency']??'daily')==='daily'?'checked':''; ?>>每天一次
                            </label>
                            <label style="display:inline-flex;align-items:center;margin-right:20px;font-size:14px;color:#475569;cursor:pointer;gap:6px;">
                                <input type="radio" name="auto_backup_frequency" value="every3" <?php echo ($settings['auto_backup_frequency']??'daily')==='every3'?'checked':''; ?>> 每3天一次
                            </label>
                            <label style="display:inline-flex;align-items:center;font-size:14px;color:#475569;cursor:pointer;gap:6px;">
                                <input type="radio" name="auto_backup_frequency" value="weekly" <?php echo ($settings['auto_backup_frequency']??'daily')==='weekly'?'checked':''; ?>>每周一次
                            </label>
                        </div>

                        <div style="padding:14px 0;border-bottom:1px solid #f1f5f9;">
                            <div style="display:flex;align-items:center;justify-content:space-between;">
                                <div style="font-weight:600;color:#1f2937;font-size:14px;">方案A：FTP / SFTP 远程上传</div>
                                <input type="checkbox" name="auto_backup_ftp_enabled" value="1" <?php echo ($settings['auto_backup_ftp_enabled']??'0')==='1'?'checked':''; ?> style="width:18px;height:18px;">
                            </div>
                            <div style="font-size:12px;color:#94a3b8;margin:8px 0 12px;line-height:1.6;">到时间自动生成备份包并通过 FTP/SFTP 上传到你的另一台服务器 / NAS / 对象存储 迁移时从远程下载备份包用 restore.php 恢复即可</div>
                            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;">
                                <div>
                                    <div style="font-size:12px;color:#64748b;margin-bottom:4px;">传输方式</div>
                                    <select name="auto_backup_ftp_type" style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:10px;font-size:14px;">
                                        <option value="ftp" <?php echo ($settings['auto_backup_ftp_type']??'ftp')==='ftp'?'selected':''; ?>>FTP</option>
                                        <option value="sftp" <?php echo ($settings['auto_backup_ftp_type']??'ftp')==='sftp'?'selected':''; ?>>SFTP (SSH)</option>
                                    </select>
                                </div>
                                <div>
                                    <div style="font-size:12px;color:#64748b;margin-bottom:4px;">服务器地址</div>
                                    <input type="text" name="auto_backup_ftp_host" value="<?php echo htmlspecialchars($settings['auto_backup_ftp_host']??''); ?>" placeholder="1.2.3.4或ftp.imoe.top" style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:10px;font-size:14px;">
                                </div>
                                <div>
                                    <div style="font-size:12px;color:#64748b;margin-bottom:4px;">端口</div>
                                    <input type="text" name="auto_backup_ftp_port" value="<?php echo htmlspecialchars($settings['auto_backup_ftp_port']??''); ?>" placeholder="FTP=21或SFTP=22" style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:10px;font-size:14px;">
                                </div>
                                <div>
                                    <div style="font-size:12px;color:#64748b;margin-bottom:4px;">用户名</div>
                                    <input type="text" name="auto_backup_ftp_user" value="<?php echo htmlspecialchars($settings['auto_backup_ftp_user']??''); ?>" style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:10px;font-size:14px;">
                                </div>
                                <div>
                                    <div style="font-size:12px;color:#64748b;margin-bottom:4px;">密码</div>
                                    <input type="password" name="auto_backup_ftp_pass" value="" placeholder="<?php echo !empty($settings['auto_backup_ftp_pass']) ? '已设置 留空则不修改' : '登录密码'; ?>" style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:10px;font-size:14px;">
                                </div>
                                <div>
                                    <div style="font-size:12px;color:#64748b;margin-bottom:4px;">上传目录</div>
                                    <input type="text" name="auto_backup_ftp_dir" value="<?php echo htmlspecialchars($settings['auto_backup_ftp_dir']??'/'); ?>" placeholder="/home/backup/" style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:10px;font-size:14px;">
                                </div>
                            </div>
                        </div>

                        <div style="padding:14px 0;border-bottom:1px solid #f1f5f9;">
                            <div style="display:flex;align-items:center;justify-content:space-between;">
                                <div style="font-weight:600;color:#1f2937;font-size:14px;">方案B：本地多版本保留</div>
                                <input type="checkbox" name="auto_backup_local_enabled" value="1" <?php echo ($settings['auto_backup_local_enabled']??'1')==='1'?'checked':''; ?> style="width:18px;height:18px;">
                            </div>
                            <div style="font-size:12px;color:#94a3b8;margin:8px 0 12px;line-height:1.6;">备份包保存在服务器 <code style="background:#f1f5f9;padding:1px 5px;border-radius:4px;">/backups/auto/</code> 目录（已禁止 Web 直接访问）自动保留最近 N 个版本或按天数清理 适合搭配后台/手动下载</div>
                            <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end;">
                                <div>
                                    <div style="font-size:12px;color:#64748b;margin-bottom:4px;">保留数量（个）</div>
                                    <input type="number" name="auto_backup_keep" value="<?php echo htmlspecialchars($settings['auto_backup_keep']??'3'); ?>" style="width:130px;padding:9px 12px;border:1px solid #e5e7eb;border-radius:10px;font-size:14px;">
                                </div>
                                <div>
                                    <div style="font-size:12px;color:#64748b;margin-bottom:4px;">保留天数（天 0=不限）</div>
                                    <input type="number" name="auto_backup_keep_days" value="<?php echo htmlspecialchars($settings['auto_backup_keep_days']??'0'); ?>" style="width:130px;padding:9px 12px;border:1px solid #e5e7eb;border-radius:10px;font-size:14px;">
                                </div>
                            </div>
                        </div>

                        <div style="padding:14px 0;border-bottom:1px solid #f1f5f9;">
                            <div style="display:flex;align-items:center;justify-content:space-between;">
                                <div style="font-weight:600;color:#1f2937;font-size:14px;">方案C：邮箱发送备份文件</div>
                                <input type="checkbox" name="auto_backup_email_enabled" value="1" <?php echo ($settings['auto_backup_email_enabled']??'0')==='1'?'checked':''; ?> style="width:18px;height:18px;">
                            </div>
                            <div style="font-size:12px;color:#94a3b8;margin:8px 0 12px;line-height:1.6;">使用下方<strong>独立的 SMTP 配置</strong>发送备份包（与「邮箱提醒」的 SMTP 互不影响）未填写则无法发送（附件超过 25MB 改为发送通知）</div>
                            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;">
                                <div>
                                    <div style="font-size:12px;color:#64748b;margin-bottom:4px;">加密方式</div>
                                    <select name="auto_backup_smtp_secure" style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:10px;font-size:14px;">
                                        <option value="ssl" <?php echo ($settings['auto_backup_smtp_secure']??'ssl')==='ssl'?'selected':''; ?>>SSL（端口465）</option>
                                        <option value="tls" <?php echo ($settings['auto_backup_smtp_secure']??'ssl')==='tls'?'selected':''; ?>>TLS或STARTTLS（端口587）</option>
                                    </select>
                                </div>
                                <div>
                                    <div style="font-size:12px;color:#64748b;margin-bottom:4px;">SMTP 服务器</div>
                                    <input type="text" name="auto_backup_smtp_host" value="<?php echo htmlspecialchars($settings['auto_backup_smtp_host']??''); ?>" placeholder="smtp.qq.com" style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:10px;font-size:14px;">
                                </div>
                                <div>
                                    <div style="font-size:12px;color:#64748b;margin-bottom:4px;">端口</div>
                                    <input type="text" name="auto_backup_smtp_port" value="<?php echo htmlspecialchars($settings['auto_backup_smtp_port']??''); ?>" placeholder="465或587" style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:10px;font-size:14px;">
                                </div>
                                <div>
                                    <div style="font-size:12px;color:#64748b;margin-bottom:4px;">SMTP 用户名</div>
                                    <input type="text" name="auto_backup_smtp_user" value="<?php echo htmlspecialchars($settings['auto_backup_smtp_user']??''); ?>" placeholder="1239399954@qq.com" style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:10px;font-size:14px;">
                                </div>
                                <div>
                                    <div style="font-size:12px;color:#64748b;margin-bottom:4px;">SMTP 密码</div>
                                    <input type="password" name="auto_backup_smtp_pass" value="" placeholder="<?php echo !empty($settings['auto_backup_smtp_pass']) ? '已设置 留空则不修改' : 'SMTP 密码'; ?>" style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:10px;font-size:14px;">
                                </div>
                                <div>
                                    <div style="font-size:12px;color:#64748b;margin-bottom:4px;">发件人显示名</div>
                                    <input type="text" name="auto_backup_email_from" value="<?php echo htmlspecialchars($settings['auto_backup_email_from']??''); ?>" placeholder="云汀记自动备份" style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:10px;font-size:14px;">
                                </div>
                            </div>
                            <div style="margin-top:12px;">
                                <div style="font-size:12px;color:#64748b;margin-bottom:4px;">接收邮箱</div>
                                <input type="email" name="auto_backup_email_to" value="<?php echo htmlspecialchars($settings['auto_backup_email_to']??''); ?>" placeholder="1239399954@qq.com" style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:10px;font-size:14px;">
                            </div>
                        </div>

                        <div style="display:flex;gap:12px;margin-top:18px;flex-wrap:wrap;">
                            <button type="submit" name="save_auto_backup" class="ap-btn ap-btn-primary"><i class="fas fa-save"></i> 保存设置</button>
                            <button type="submit" name="run_auto_now" class="ap-btn" style="background:#eef2ff;color:#5b73e8;"><i class="fas fa-play"></i> 立即执行一次</button>
                        </div>
                    </form>

                    <div style="margin-top:20px;padding-top:16px;border-top:1px solid #f1f5f9;">
                        <div style="font-weight:600;color:#1f2937;font-size:14px;margin-bottom:8px;">上次自动备份</div>
                        <?php if ($last_ts > 0): ?>
                        <div style="font-size:13px;color:#475569;line-height:1.9;">
                            时间：<?php echo date('Y-m-d H:i:s', $last_ts); ?>（<?php echo time_ago(date('Y-m-d H:i:s', $last_ts)); ?>）<br>
                            状态：<span style="color:<?php echo $last_status==='success'?'#2ca87f':'#dc2626'; ?>;font-weight:600;"><?php echo $last_status==='success'?'成功':'失败'; ?></span><br>
                            详情：<?php echo htmlspecialchars($last_msg); ?>
                        </div>
                        <?php else: ?>
                        <div style="font-size:13px;color:#94a3b8;">尚未执行过自动备份 保存设置后点击「立即执行一次」可测试</div>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top:16px;padding-top:16px;border-top:1px solid #f1f5f9;">
                        <div style="font-weight:600;color:#1f2937;font-size:14px;margin-bottom:8px;">服务器定时任务（Cron推荐）</div>
                        <div style="font-size:12px;color:#94a3b8;line-height:1.8;">
                            如需精确按时备份 请在服务器添加定时任务访问下方地址 例如每天 3 点：<br>
                            <code style="display:inline-block;background:#f1f5f9;padding:6px 10px;border-radius:6px;margin-top:6px;font-size:12px;color:#334155;word-break:break-all;"><?php echo htmlspecialchars($cron_full ?: '（请先保存设置以生成调用地址）'); ?></code><br>
                            <?php
                            $ytj_freq = $settings['auto_backup_frequency'] ?? 'daily';
                            $ytj_cron_expr = $ytj_freq === 'weekly' ? '0 3 * * 0' : ($ytj_freq === 'every3' ? '0 3 */3 * *' : '0 3 * * *');
                            $ytj_freq_label = $ytj_freq === 'weekly' ? '每周日' : ($ytj_freq === 'every3' ? '每3天' : '每天');
                            ?>
                            <span style="display:inline-block;margin-top:6px;">示例命令（<?php echo $ytj_freq_label; ?> 3 点）：</span><br>
                            <code style="display:inline-block;background:#f1f5f9;padding:6px 10px;border-radius:6px;margin-top:4px;font-size:12px;color:#334155;word-break:break-all;"><?php echo $ytj_cron_expr; ?> curl -s "<?php echo htmlspecialchars($cron_full ?: 'https://你的域名/admin/auto_backup.php?cron=1&token=KEY'); ?>"</code><br>
                            <span style="display:inline-block;margin-top:8px;color:#64748b;">未配置 Cron 时 系统会在你访问后台首页时自动判断是否到期并执行（请保持每日/每周至少访问一次后台）</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<?php if ($toast_msg): ?>
<div id="toast" class="ap-toast <?php echo (strpos($toast_msg, '失败') !== false || strpos($toast_msg, '错误') !== false) ? '' : 'success'; ?>">
    <span class="ap-toast-icon <?php echo (strpos($toast_msg, '失败') !== false || strpos($toast_msg, '错误') !== false) ? 'error' : 'success'; ?>">
        <i class="fas <?php echo (strpos($toast_msg, '失败') !== false || strpos($toast_msg, '错误') !== false) ? 'fa-exclamation' : 'fa-check'; ?>"></i>
    </span>
    <span><?php echo htmlspecialchars($toast_msg); ?></span>
</div>
<script>
    setTimeout(() => {
        const t = document.getElementById('toast');
        if (t) t.classList.add('show');
    }, 100);
    setTimeout(() => {
        const t = document.getElementById('toast');
        if (t) { t.classList.remove('show'); }
    }, 3000);
</script>
<?php endif; ?>
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
        if (submenu.classList.contains('open')) {
            submenu.classList.remove('open');
            arrow.classList.remove('rotate');
        } else {
            submenu.classList.add('open');
            arrow.classList.add('rotate');
        }
    }
</script>
</body>
</html>
