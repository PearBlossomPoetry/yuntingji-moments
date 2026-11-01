<?php
require_once '../config.php';
if (!$conn) { json_response(0, '系统未安装'); }
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$post_id = intval($_GET['post_id'] ?? 0);
if (!$post_id) json_response(0, '参数错误');

$likes = [];
$like_count = 0;
$result = mysqli_query($conn, "SELECT nickname, ip FROM likes WHERE post_id = $post_id");
if ($result) {
    $like_count = mysqli_num_rows($result);
    $count = 0;
    while ($row = mysqli_fetch_assoc($result)) {
        if ($count < 8) {
            $likes[] = $row['nickname'] ?: '访客';
        }
        $count++;
    }
}

$all_comments = [];
$result = mysqli_query($conn, "SELECT * FROM comments WHERE post_id = $post_id AND status = 1 AND approve_status = 1 ORDER BY created_at DESC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $all_comments[] = $row;
    }
}

$comment_map = [];
foreach ($all_comments as $c) {
    $comment_map[intval($c['id'])] = $c;
}

$comments = [];
foreach ($all_comments as $c) {
    $pid = intval($c['parent_id']);
    if ($pid > 0 && isset($comment_map[$pid])) {
        $c['parent_nickname'] = $comment_map[$pid]['nickname'];
        $c['parent_avatar'] = $comment_map[$pid]['avatar'];
        $c['parent_website'] = $comment_map[$pid]['website'];
    } else {
        $c['parent_nickname'] = '';
        $c['parent_avatar'] = '';
        $c['parent_website'] = '';
    }
    $comments[] = $c;
}

define('PAGE_COMMENT_OFFSET', 100000000);
$like_enabled = true;
$comment_enabled = true;
if ($post_id <= -PAGE_COMMENT_OFFSET) {
    $page_id = abs($post_id) - PAGE_COMMENT_OFFSET;
    $post_check = mysqli_query($conn, "SELECT 1 as like_enabled, enable_comments as comment_enabled FROM pages WHERE id = $page_id LIMIT 1");
} elseif ($post_id < 0) {
    $ad_id = abs($post_id);
    $post_check = mysqli_query($conn, "SELECT like_enabled, comment_enabled FROM ads WHERE id = $ad_id LIMIT 1");
} else {
    $post_check = mysqli_query($conn, "SELECT like_enabled, comment_enabled FROM posts WHERE id = $post_id LIMIT 1");
}
if ($post_check && $row = mysqli_fetch_assoc($post_check)) {
    $like_enabled = intval($row['like_enabled'] ?? 1) === 1;
    $comment_enabled = intval($row['comment_enabled'] ?? 1) === 1;
}

json_response(1, 'ok', [
    'likes' => $likes,
    'like_count' => $like_count,
    'comments' => $comments,
    'like_enabled' => $like_enabled,
    'comment_enabled' => $comment_enabled
]);
