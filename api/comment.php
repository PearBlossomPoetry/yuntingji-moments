<?php
require_once '../config.php';
if (!$conn) { json_response(0, '系统未安装'); }
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$post_id = intval($_POST['post_id'] ?? 0);
$nickname = trim($_POST['nickname'] ?? '');
$email = trim($_POST['email'] ?? '');
$website = trim($_POST['website'] ?? '');
$content = trim($_POST['content'] ?? '');
$parent_id = intval($_POST['parent_id'] ?? 0);
$ip = get_real_ip();

define('PAGE_COMMENT_OFFSET', 100000000);
define('ARTICLE_COMMENT_OFFSET', 200000000);

if (!$post_id) {
    json_response(0, '参数错误');
}

$is_article_comment = $post_id <= -ARTICLE_COMMENT_OFFSET;
$article_id = $is_article_comment ? (abs($post_id) - ARTICLE_COMMENT_OFFSET) : 0;

$is_page_comment = ($post_id <= -PAGE_COMMENT_OFFSET) && !$is_article_comment;
$page_id = $is_page_comment ? (abs($post_id) - PAGE_COMMENT_OFFSET) : 0;

if ($is_page_comment && $page_id > 0) {
    $page_check = mysqli_query($conn, "SELECT id, enable_comments FROM pages WHERE id = {$page_id} AND status = 1 LIMIT 1");
    if (!$page_check || mysqli_num_rows($page_check) === 0) {
        json_response(0, '页面不存在');
    }
    $page_row = mysqli_fetch_assoc($page_check);
    if (intval($page_row['enable_comments'] ?? 1) !== 1) {
        json_response(0, '该页面已关闭评论');
    }
}

if ($is_article_comment && $article_id > 0) {
    $article_check = mysqli_query($conn, "SELECT id, comment_enabled FROM articles WHERE id = {$article_id} AND status = 1 LIMIT 1");
    if (!$article_check || mysqli_num_rows($article_check) === 0) {
        json_response(0, '文章不存在');
    }
    $article_row = mysqli_fetch_assoc($article_check);
    if (intval($article_row['comment_enabled'] ?? 1) !== 1) {
        json_response(0, '该文章已关闭评论');
    }
}

if (!$nickname || !$email || !$content) {
    json_response(0, '昵称、邮箱和评论内容不能为空');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(0, '邮箱格式不正确');
}

if (is_ip_banned($ip)) {
    json_response(0, '您的IP已被禁止评论');
}

$ban_check = check_banned_words($content);
if ($ban_check['has']) {
    json_response(0, '评论包含违禁词「' . $ban_check['word'] . '」，请修改后重试');
}

$avatar = trim($_POST['avatar'] ?? '');
if (!$avatar && strpos($email, '@qq.com') !== false) {
    $qq = str_replace('@qq.com', '', $email);
    if (is_numeric($qq)) {
        $avatar = get_qq_avatar($qq);

        $qq_nickname = get_qq_nickname($qq);
        if ($qq_nickname && $qq_nickname !== 'error' && strpos($qq_nickname, '<') === false) {
            $nickname = $qq_nickname;
        }
    }
}

if ($website && !preg_match('/^https?:\/\//', $website)) {
    $website = 'http://' . $website;
}

$raw_nickname = $nickname;
$raw_content = $content;

$nickname = mysqli_real_escape_string($conn, $nickname);
$email = mysqli_real_escape_string($conn, $email);
$website = $website ? mysqli_real_escape_string($conn, $website) : '';
$avatar = $avatar ? mysqli_real_escape_string($conn, $avatar) : '';
$content = mysqli_real_escape_string($conn, $content);
$parent_id = intval($parent_id);

$settings = get_all_settings();
$need_approve = ($settings['comment_need_approve'] ?? '0') === '1';

$is_admin_reply = false;
if ($parent_id > 0) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $is_admin_reply = !empty($_SESSION['admin_id']);
}

if ($need_approve && !$is_admin_reply) {
    $comment_status = 0;
    $comment_approve_status = 0;
} else {
    $comment_status = 1;
    $comment_approve_status = 1;
}

$sql = "INSERT INTO comments (post_id, nickname, email, website, avatar, content, parent_id, ip, status, approve_status)
        VALUES ($post_id, '$nickname', '$email', '$website', '$avatar', '$content', $parent_id, '$ip', $comment_status, $comment_approve_status)";

$comment_id = 0;
if (mysqli_query($conn, $sql)) {
    $comment_id = mysqli_insert_id($conn);

    if ($comment_id > 0 && function_exists('send_comment_notify')) {
        if ($is_page_comment && $page_id > 0) {
            send_page_comment_notify($page_id, $raw_nickname, $raw_content, $parent_id, $comment_id);
        } elseif ($is_article_comment && $article_id > 0) {
            send_article_comment_notify($article_id, $raw_nickname, $raw_content, $parent_id, $comment_id);
        } else {
            send_comment_notify($post_id, $raw_nickname, $raw_content, $parent_id, $comment_id);
        }
    }

    if ($need_approve && !$is_admin_reply) {
        json_response(1, '评论已提交，等待审核');
    } else {
        json_response(1, '评论成功');
    }
} else {
    json_response(0, '评论失败：' . mysqli_error($conn));
}

function get_qq_nickname($qq) {
    $url = 'https://r.qzone.qq.com/fcg-bin/cgi_get_portrait.fcg?uins=' . $qq;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || !$response) {
        return '';
    }

    $response = iconv('GBK', 'UTF-8//IGNORE', $response);

    $response = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', '', $response);

    preg_match('/"([^"]+)"/', $response, $matches);
    if (isset($matches[1]) && $matches[1] && $matches[1] !== 'error') {
        return trim($matches[1]);
    }
    return '';
}

function send_page_comment_notify($page_id, $nickname, $content, $parent_id, $comment_id) {
    global $conn;
    if (!$conn) return;

    $settings = get_all_settings();
    if (($settings['email_notify_enabled'] ?? '0') !== '1') return;

    $site_name = $settings['site_name'] ?? '云汀记';
    $subject_tpl = $settings['email_subject'] ?? '【{site_name}】你收到一条新评论';
    $body_tpl = $settings['email_template'] ?? '';
    if (!$body_tpl) return;

    $page_result = mysqli_query($conn, "SELECT slug FROM pages WHERE id = " . intval($page_id) . " LIMIT 1");
    $page_slug = '';
    if ($page_result && $row = mysqli_fetch_assoc($page_result)) {
        $page_slug = $row['slug'];
    }

    if ($parent_id > 0) {
        $pid = intval($parent_id);
        $result = mysqli_query($conn, "SELECT email, nickname FROM comments WHERE id = {$pid} LIMIT 1");
        if (!$result || !($row = mysqli_fetch_assoc($result))) return;
        $to = $row['email'];
        $reply_to_name = $row['nickname'];
        if (!$to) return;
        $subject = str_replace(['{site_name}', '{nickname}'], [$site_name, $nickname], '【{site_name}】{nickname} 回复了你的评论');
    } else {
        $to = $settings['smtp_user'] ?? '';
        if (!$to) return;
        $reply_to_name = '管理员';
        $subject = str_replace(['{site_name}', '{nickname}'], [$site_name, $nickname], $subject_tpl);
    }

    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $post_link = $protocol . '://' . $host . '/' . ($page_slug ? $page_slug . '.html' : '');
    $time = date('Y-m-d H:i:s');

    $body = str_replace(
        ['{site_name}', '{nickname}', '{content}', '{post_link}', '{time}', '{commenter}'],
        [$site_name, $nickname, nl2br(htmlspecialchars($content)), $post_link, $time, $nickname],
        $body_tpl
    );

    send_smtp_email($to, $subject, $body);
}

function send_article_comment_notify($article_id, $nickname, $content, $parent_id, $comment_id) {
    global $conn;
    if (!$conn) return;

    $settings = get_all_settings();
    if (($settings['email_notify_enabled'] ?? '0') !== '1') return;

    $site_name = $settings['site_name'] ?? '云汀记';
    $subject_tpl = $settings['email_subject'] ?? '【{site_name}】你收到一条新评论';
    $body_tpl = $settings['email_template'] ?? '';
    if (!$body_tpl) return;

    $article_result = mysqli_query($conn, "SELECT slug, title FROM articles WHERE id = " . intval($article_id) . " LIMIT 1");
    $article_slug = '';
    if ($article_result && $row = mysqli_fetch_assoc($article_result)) {
        $article_slug = $row['slug'];
    }

    if ($parent_id > 0) {
        $pid = intval($parent_id);
        $result = mysqli_query($conn, "SELECT email, nickname FROM comments WHERE id = {$pid} LIMIT 1");
        if (!$result || !($row = mysqli_fetch_assoc($result))) return;
        $to = $row['email'];
        if (!$to) return;
        $subject = str_replace(['{site_name}', '{nickname}'], [$site_name, $nickname], '【{site_name}】{nickname} 回复了你的评论');
    } else {
        $to = $settings['smtp_user'] ?? '';
        if (!$to) return;
        $subject = str_replace(['{site_name}', '{nickname}'], [$site_name, $nickname], $subject_tpl);
    }

    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $post_link = $protocol . '://' . $host . '/' . urlencode($article_slug) . '.article.html';
    $time = date('Y-m-d H:i:s');

    $body = str_replace(
        ['{site_name}', '{nickname}', '{content}', '{post_link}', '{time}', '{commenter}'],
        [$site_name, $nickname, nl2br(htmlspecialchars($content)), $post_link, $time, $nickname],
        $body_tpl
    );

    send_smtp_email($to, $subject, $body);
}
