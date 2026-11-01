<?php
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    json_response(0, '请先登录');
}
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

define('UPLOAD_BASE_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_BASE_URL', 'uploads/');

$ALLOWED_CONFIG = [

    'image' => [
        'ext'   => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'mime'  => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        'max_size' => 20 * 1024 * 1024,
    ],

    'video' => [
        'ext'   => ['mp4', 'webm', 'mov'],
        'mime'  => ['video/mp4', 'video/webm', 'video/quicktime', 'application/octet-stream'],
        'max_size' => 100 * 1024 * 1024,
    ],
];

$DANGEROUS_EXTS = ['php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'exe', 'sh', 'bat', 'cmd', 'jsp', 'asp', 'aspx', 'dll', 'py', 'rb', 'pl'];

function generate_safe_filename($original_name) {
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    if (!$ext) $ext = 'jpg';

    $random = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 4);
    $filename = uniqid() . '_' . $random;
    return $filename . '.' . $ext;
}

function get_upload_path() {
    $subdir = date('Y') . '/' . date('m') . '/';
    $abs_dir = UPLOAD_BASE_DIR . $subdir;

    if (!is_dir($abs_dir)) {
        mkdir($abs_dir, 0755, true);
    }
    return [$subdir, $abs_dir];
}

function validate_file($file, $type) {
    global $ALLOWED_CONFIG, $DANGEROUS_EXTS;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_msgs = [
            UPLOAD_ERR_INI_SIZE   => '文件大小超过服务器限制',
            UPLOAD_ERR_FORM_SIZE  => '文件大小超过表单限制',
            UPLOAD_ERR_PARTIAL    => '文件上传不完整',
            UPLOAD_ERR_NO_FILE    => '没有文件被上传',
            UPLOAD_ERR_NO_TMP_DIR => '服务器临时目录丢失',
            UPLOAD_ERR_CANT_WRITE => '文件写入失败',
            UPLOAD_ERR_EXTENSION  => '上传被扩展阻止',
        ];
        return [false, $error_msgs[$file['error']] ?? '上传错误（代码：' . $file['error'] . '）'];
    }

    $original_name = $file['name'];
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    if (in_array($ext, $DANGEROUS_EXTS)) {
        return [false, '不允许上传此类型文件'];
    }

    $config = $ALLOWED_CONFIG[$type] ?? null;
    if (!$config) {
        return [false, '未知的文件类型分类'];
    }
    if (!in_array($ext, $config['ext'])) {
        return [false, '不支持的文件格式，允许：' . implode(', ', $config['ext'])];
    }

    if ($file['size'] > $config['max_size']) {
        $max_mb = $config['max_size'] / 1024 / 1024;
        return [false, '文件大小超过限制，最大允许 ' . $max_mb . 'MB'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $real_mime = $finfo->file($file['tmp_name']);
    $allowed_mimes = $config['mime'];

    if ($type === 'video' && $ext === 'mov') {
        $allowed_mimes[] = 'video/quicktime';
        $allowed_mimes[] = 'application/octet-stream';
    }

    if (!in_array($real_mime, $allowed_mimes)) {
        return [false, '文件类型验证失败，检测到 MIME: ' . $real_mime];
    }

    if ($type !== 'image') {
        $content = file_get_contents($file['tmp_name']);
        if (preg_match('/<\?php|<\?=|<\?\s/i', substr($content, 0, 4096))) {
            return [false, '文件内容包含非法代码'];
        }
    }

    return [true, ''];
}

function handle_upload($file, $type) {
    list($ok, $msg) = validate_file($file, $type);
    if (!$ok) {
        return [false, $msg];
    }

    list($subdir, $abs_dir) = get_upload_path();

    $filename = generate_safe_filename($file['name']);
    $abs_path = $abs_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $abs_path)) {
        return [false, '文件保存失败'];
    }

    $url = UPLOAD_BASE_URL . $subdir . $filename;
    return [true, $url];
}

$upload_type = '';
$files = [];
$is_multiple = false;

if (isset($_FILES['image']) && (!empty($_FILES['image']['name']) || !empty($_FILES['image']['tmp_name']))) {
    $upload_type = 'image';
    $files = [$_FILES['image']];
}

elseif (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
    $upload_type = 'image';
    $is_multiple = true;

    $file_count = count($_FILES['images']['name']);
    for ($i = 0; $i < $file_count; $i++) {
        $files[] = [
            'name'     => $_FILES['images']['name'][$i],
            'type'     => $_FILES['images']['type'][$i],
            'tmp_name' => $_FILES['images']['tmp_name'][$i],
            'error'    => $_FILES['images']['error'][$i],
            'size'     => $_FILES['images']['size'][$i],
        ];
    }
}

elseif (isset($_FILES['video']) && !empty($_FILES['video']['name'])) {
    $upload_type = 'video';
    $files = [$_FILES['video']];
}

elseif (isset($_FILES['live_image']) && !empty($_FILES['live_image']['name'])) {
    $upload_type = 'image';
    $files = [$_FILES['live_image']];
}

elseif (isset($_FILES['live_video']) && !empty($_FILES['live_video']['name'])) {
    $upload_type = 'video';
    $files = [$_FILES['live_video']];
}
else {
    json_response(0, '未检测到上传文件');
}

$success_urls = [];
$errors = [];

foreach ($files as $index => $file) {
    if (empty($file['name']) && empty($file['tmp_name'])) {
        continue;
    }
    list($ok, $result) = handle_upload($file, $upload_type);
    if ($ok) {
        $success_urls[] = $result;
    } else {
        $errors[] = '文件[' . ($index + 1) . ']: ' . $result;
    }
}

if (empty($success_urls)) {
    json_response(0, implode('; ', $errors));
}

if ($is_multiple) {
    json_response(1, '', ['urls' => $success_urls]);
} else {
    json_response(1, '', ['url' => $success_urls[0]]);
}
