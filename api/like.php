<?php
require_once '../config.php';
if (!$conn) { json_response(0, '系统未安装'); }
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$post_id = intval($_POST['post_id'] ?? 0);
$action = $_POST['action'] ?? 'like';
$ip = get_real_ip();

if (!$post_id) {
    json_response(0, '参数错误');
}

$check = mysqli_query($conn, "SELECT id FROM likes WHERE post_id = $post_id AND ip = '" . mysqli_real_escape_string($conn, $ip) . "'");
$has_liked = mysqli_fetch_assoc($check) ? true : false;

if ($action === 'cancel') {
    if ($has_liked) {
        mysqli_query($conn, "DELETE FROM likes WHERE post_id = $post_id AND ip = '" . mysqli_real_escape_string($conn, $ip) . "'");
        json_response(1, '已取消点赞');
    } else {
        json_response(0, '您还没有赞过');
    }
} else {
    if ($has_liked) {
        json_response(0, '您已经赞过了');
    } else {
        $nickname = '访客';
        mysqli_query($conn, "INSERT INTO likes (post_id, ip, nickname) VALUES ($post_id, '" . mysqli_real_escape_string($conn, $ip) . "', '$nickname')");
        json_response(1, '点赞成功');
    }
}
