<?php
// 云汀记 全局配置与公共函数

// Markdown 解析器
require_once __DIR__ . '/assets/Parsedown.php';

// 时区
date_default_timezone_set('Asia/Shanghai');

// 未安装时先用空函数占位，装好后由下面的实现覆盖
if (!file_exists(__DIR__ . '/install.lock')) {
    if (!function_exists('get_setting')) {
        function get_setting($key, $default = '') { return $default; }
    }
    if (!function_exists('get_all_settings')) {
        function get_all_settings() { return []; }
    }
    if (!function_exists('json_response')) {
        function json_response($code, $msg = '', $data = []) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['code' => $code, 'msg' => $msg, 'data' => $data]);
            exit;
        }
    }
    if (!function_exists('get_real_ip')) {
        function get_real_ip() {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $ip = trim($ips[0]);
            }
            return $ip;
        }
    }
    if (!function_exists('get_qq_avatar')) {
        function get_qq_avatar($qq) {
            return 'https://q2.qlogo.cn/headimg_dl?dst_uin=' . $qq . '&spec=100';
        }
    }
    if (!function_exists('time_ago')) {
        function time_ago($datetime) {
            $time = strtotime($datetime);
            $now = time();
            $diff = $now - $time;
            if ($diff < 60) return '刚刚';
            if ($diff < 3600) return floor($diff / 60) . '分钟前';
            if ($diff < 86400) return floor($diff / 3600) . '小时前';
            if ($diff < 2592000) return floor($diff / 86400) . '天前';
            if ($diff < 31536000) return floor($diff / 2592000) . '个月前';
            return date('Y-m-d', $time);
        }
    }

    $conn = null;
    return;
}

// 安全头
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// 数据库配置
require_once __DIR__ . '/db.config.php';

// CSRF
if (!function_exists('csrf_token_field')) {
    function csrf_token_field() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
    }
}
if (!function_exists('csrf_validate')) {
    function csrf_validate() {
        if (empty($_SESSION['csrf_token']) || empty($_POST['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }
}
if (!function_exists('csrf_validate_or_die')) {
    function csrf_validate_or_die($is_ajax = false) {
        if (!csrf_validate()) {
            if ($is_ajax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['code' => 0, 'msg' => '安全令牌无效，请刷新页面重试']);
                exit;
            }
            die('安全令牌无效，请刷新页面重试');
        }
    }
}
if (!function_exists('csrf_token_value')) {
    function csrf_token_value() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
    }
}

// 连接数据库
$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die('数据库连接失败: ' . mysqli_connect_error());
}
mysqli_set_charset($conn, 'utf8mb4');

// 读取单个配置
function get_setting($key, $default = '') {
    global $conn;
    if (!$conn) return $default;
    $result = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key = '" . mysqli_real_escape_string($conn, $key) . "'");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row['setting_value'];
    }
    return $default;
}

// 读取全部配置
function get_all_settings() {
    global $conn;
    $settings = [];
    if (!$conn) return $settings;
    $result = mysqli_query($conn, "SELECT * FROM settings");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $settings;
}

if (!function_exists('json_response')) {
    function json_response($code, $msg = '', $data = []) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code' => $code, 'msg' => $msg, 'data' => $data]);
        exit;
    }
}

if (!defined('TRUSTED_PROXIES')) {
    define('TRUSTED_PROXIES', ['217.114.47.100']);
}

// 真实 IP
function get_real_ip() {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $trusted = defined('TRUSTED_PROXIES') && is_array(TRUSTED_PROXIES) ? TRUSTED_PROXIES : [];
    $trust_proxy = in_array($remote, $trusted, true)
        || !filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    if ($trust_proxy) {
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $real = trim($_SERVER['HTTP_X_REAL_IP']);
            if (filter_var($real, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $real;
            }
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            foreach ($ips as $cand) {
                $cand = trim($cand);
                if (filter_var($cand, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $cand;
                }
            }
        }
    }
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '127.0.0.1';
}

// QQ 头像
function get_qq_avatar($qq) {
    return 'https://q2.qlogo.cn/headimg_dl?dst_uin=' . $qq . '&spec=100';
}

// 表情列表
function get_sticker_names() {
    return ['3d眼镜','EDG','LPL','beluga','不好意思','不服吗','亲亲','伞兵','倚墙笑','值得肯定','偷偷看','傻笑','再见','出家人','击剑','加班','勉强笑','危险','发红包','吃手','吃瓜','吐血','吵架','呦吼','呲牙笑','哈士奇','哈士奇失去意识','哈士奇失望','哭泣','唱歌','喜欢','嘿哈','大笑','失去信号','失望','头秃','奋斗','好奇','好的','害羞','小丑','小偷','尬笑','尴尬','应援','开心','引起不适','微笑','思考','恶心','恶魔','恶魔恐惧','惊吓','惊吓白眼','惊讶','惬意','感动','愤怒','我看好你','手机相机','打咩','打牌','托腮','扶额','抠鼻','抬眼镜','拜托','捂嘴笑','捂脸','擦汗','放鞭炮','敬礼','整理发型','斗鸡眼','智慧的眼神','月饼','有没有搞错','正确','没招','波吉','泪奔','流汗微笑','流鼻涕','深思','滑稽','滑稽吃瓜','滑稽喝水','滑稽奶茶','滑稽柠檬','滑稽狂汗','滑稽被子','烦恼','熊熊','熊猫','熊猫唱歌','熊猫喜欢','熊猫失望','熊猫意外','熬夜','爆炸','牛年进宝','狂热','狗头','狗头围脖','狗头失望','狗头意外','狗头胖次','狗头花','狗头草','猪头','猪头意外','生病','电话','疑问','疼痛','痛哭','看穿一切','眩晕','睡觉','禁言','笑哭','纠结','绿帽','缺牙笑','翻白眼','老虎意外','耍酷','胡子','菜狗','菜狗花','蒙面滑稽','虎年进宝','被打','裂开','警告','读书','财神红包','超爱','这是啥','送福','送花','错误','阴险','难以置信','面具','饥渴','鬼脸','黑线','鼓掌'];
}
function get_sticker_names_kawa() {
    return ['kawa-1','kawa-2','kawa-3','kawa-4','kawa-5','kawa-6','kawa-7','kawa-8','kawa-9','kawa-10','kawa-11','kawa-12','kawa-13','kawa-14','kawa-15','kawa-16','kawa-17','kawa-18','kawa-19','kawa-20','kawa-21','kawa-22','kawa-23','kawa-24','kawa-25','kawa-26','kawa-27','kawa-28','kawa-29','kawa-30','kawa-31','kawa-32'];
}

// 时间格式化
function time_ago($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    if ($diff < 60) return '刚刚';
    if ($diff < 3600) return floor($diff / 60) . '分钟前';
    if ($diff < 86400) return floor($diff / 3600) . '小时前';
    if ($diff < 2592000) return floor($diff / 86400) . '天前';
    if ($diff < 31536000) return floor($diff / 2592000) . '个月前';
    return date('Y-m-d', $time);
}

// IP 黑名单
function is_ip_banned($ip = null) {
    global $conn;
    if (!$conn) return false;
    if (!$ip) $ip = get_real_ip();
    $esc_ip = mysqli_real_escape_string($conn, $ip);
    $result = mysqli_query($conn, "SELECT id FROM banned_ips WHERE ip = '$esc_ip' LIMIT 1");
    return $result && mysqli_num_rows($result) > 0;
}

$GLOBALS['sticker_names'] = ['3d眼镜','EDG','LPL','beluga','不好意思','不服吗','亲亲','伞兵','倚墙笑','值得肯定','偷偷看','傻笑','再见','出家人','击剑','加班','勉强笑','危险','发红包','吃手','吃瓜','吐血','吵架','呦吼','呲牙笑','哈士奇','哈士奇失去意识','哈士奇失望','哭泣','唱歌','喜欢','嘿哈','大笑','失去信号','失望','头秃','奋斗','好奇','好的','害羞','小丑','小偷','尬笑','尴尬','应援','开心','引起不适','微笑','思考','恶心','恶魔','恶魔恐惧','惊吓','惊吓白眼','惊讶','惬意','感动','愤怒','我看好你','手机相机','打咩','打牌','托腮','扶额','抠鼻','抬眼镜','拜托','捂嘴笑','捂脸','擦汗','放鞭炮','敬礼','整理发型','斗鸡眼','智慧的眼神','月饼','有没有搞错','正确','没招','波吉','泪奔','流汗微笑','流鼻涕','深思','滑稽','滑稽吃瓜','滑稽喝水','滑稽奶茶','滑稽柠檬','滑稽狂汗','滑稽被子','烦恼','熊熊','熊猫','熊猫唱歌','熊猫喜欢','熊猫失望','熊猫意外','熬夜','爆炸','牛年进宝','狂热','狗头','狗头围脖','狗头失望','狗头意外','狗头胖次','狗头花','狗头草','猪头','猪头意外','生病','电话','疑问','疼痛','痛哭','看穿一切','眩晕','睡觉','禁言','笑哭','纠结','绿帽','缺牙笑','翻白眼','老虎意外','耍酷','胡子','菜狗','菜狗花','蒙面滑稽','虎年进宝','被打','裂开','警告','读书','财神红包','超爱','这是啥','送福','送花','错误','阴险','难以置信','面具','饥渴','鬼脸','黑线','鼓掌'];
$GLOBALS['sticker_names_kawa'] = ['kawa-1','kawa-2','kawa-3','kawa-4','kawa-5','kawa-6','kawa-7','kawa-8','kawa-9','kawa-10','kawa-11','kawa-12','kawa-13','kawa-14','kawa-15','kawa-16','kawa-17','kawa-18','kawa-19','kawa-20','kawa-21','kawa-22','kawa-23','kawa-24','kawa-25','kawa-26','kawa-27','kawa-28','kawa-29','kawa-30','kawa-31','kawa-32'];

// 表情解析
if (!function_exists('parse_stickers')) {
function parse_stickers($text) {
    $sticker_names = $GLOBALS['sticker_names'];
    $sticker_names_kawa = $GLOBALS['sticker_names_kawa'];
    $nameToFile = function($name) {
        $raw = preg_replace_callback('/[\x{0080}-\x{FFFF}]/u', function($ch) {
            return '#U' . dechex(mb_ord($ch[0]));
        }, $name);
        return str_replace('#', '%23', $raw);
    };
    $text = preg_replace_callback('/:heo-([^:]+?):/', function($m) use ($sticker_names, $nameToFile) {
        if (in_array($m[1], $sticker_names)) {
            $filename = $nameToFile($m[1]);
            return '<img src="assets/stickers2/' . $filename . '.png" class="heo-sticker" alt="' . htmlspecialchars($m[1]) . '">';
        }
        return $m[0];
    }, $text);
    $text = preg_replace_callback('/:kawa-([^:]+?):/', function($m) use ($sticker_names_kawa, $nameToFile) {
        $full_name = 'kawa-' . $m[1];
        if (in_array($full_name, $sticker_names_kawa)) {
            $filename = $nameToFile($full_name);
            return '<img src="assets/stickers/' . $filename . '.png" class="heo-sticker" alt="' . htmlspecialchars($full_name) . '">';
        }
        return $m[0];
    }, $text);
    return $text;
}
}

// 违禁词
function check_banned_words($content) {
    global $conn;
    if (!$conn) return ['has' => false, 'word' => ''];
    $result = mysqli_query($conn, "SELECT word FROM banned_words");
    if (!$result) return ['has' => false, 'word' => ''];
    while ($row = mysqli_fetch_assoc($result)) {
        $word = $row['word'];

        $found = function_exists('mb_stripos') ? mb_stripos($content, $word) !== false : stripos($content, $word) !== false;
        if ($found) {
            return ['has' => true, 'word' => $word];
        }
    }
    return ['has' => false, 'word' => ''];
}

// PHP 7 兼容
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return strpos($haystack, $needle) === 0;
    }
}

// 发信
function send_smtp_email($to, $subject, $body) {
    global $conn;
    if (!$conn) return false;

    $settings = get_all_settings();
    if (($settings['email_notify_enabled'] ?? '0') !== '1') return false;

    $host = $settings['smtp_host'] ?? '';
    $port = intval($settings['smtp_port'] ?? 465);
    $user = $settings['smtp_user'] ?? '';
    $pass = $settings['smtp_pass'] ?? '';
    if (!$host || !$user || !$pass) return false;

    $fromName = $settings['site_name'] ?? '云汀记';
    $isSsl = ($port == 465);

    $boundary = md5(time() . rand());
    $subject_enc = '=?utf-8?B?' . base64_encode($subject) . '?=';
    $fromName_enc = '=?utf-8?B?' . base64_encode($fromName) . '?=';

    $header = "MIME-Version: 1.0\r\n";
    $header .= "Content-Type: text/html; charset=utf-8\r\n";
    $header .= "From: {$fromName_enc} <{$user}>\r\n";
    $header .= "To: {$to}\r\n";
    $header .= "Subject: {$subject_enc}\r\n";
    $header .= "Date: " . date('r') . "\r\n";
    $header .= "\r\n";

    $fullBody = $header . $body;

    $errno = 0; $errstr = '';
    $addr = $isSsl ? 'ssl://' . $host : $host;
    $socket = @fsockopen($addr, $port, $errno, $errstr, 15);
    if (!$socket) return false;

    stream_set_timeout($socket, 15);

    $readResp = function($expectPrefix) use ($socket) {
        $resp = fgets($socket, 512);
        return $resp !== false && strpos($resp, $expectPrefix) === 0;
    };

    if (!$readResp('220')) { fclose($socket); return false; }

    $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
    fwrite($socket, "EHLO {$domain}\r\n");
    while ($line = fgets($socket, 512)) {
        if (strpos($line, '250 ') === 0) break;
    }

    fwrite($socket, "AUTH LOGIN\r\n");
    if (!$readResp('334')) { fclose($socket); return false; }

    fwrite($socket, base64_encode($user) . "\r\n");
    if (!$readResp('334')) { fclose($socket); return false; }

    fwrite($socket, base64_encode($pass) . "\r\n");
    if (!$readResp('235')) { fclose($socket); return false; }

    fwrite($socket, "MAIL FROM:<{$user}>\r\n");
    if (!$readResp('250')) { fclose($socket); return false; }

    fwrite($socket, "RCPT TO:<{$to}>\r\n");
    if (!$readResp('250')) { fclose($socket); return false; }

    fwrite($socket, "DATA\r\n");
    if (!$readResp('354')) { fclose($socket); return false; }

    fwrite($socket, $fullBody . "\r\n.\r\n");
    if (!$readResp('250')) { fclose($socket); return false; }

    fwrite($socket, "QUIT\r\n");
    fclose($socket);
    return true;
}

// 评论通知
function send_comment_notify($post_id, $nickname, $content, $parent_id, $comment_id) {
    global $conn;
    if (!$conn) return;

    $settings = get_all_settings();
    if (($settings['email_notify_enabled'] ?? '0') !== '1') return;

    $site_name = $settings['site_name'] ?? '云汀记';
    $subject_tpl = $settings['email_subject'] ?? '【{site_name}】你收到一条新评论';
    $body_tpl = $settings['email_template'] ?? '';
    if (!$body_tpl) return;

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
    $post_link = $protocol . '://' . $host . '/';
    $time = date('Y-m-d H:i:s');

    $body = str_replace(
        ['{site_name}', '{nickname}', '{content}', '{post_link}', '{time}', '{commenter}'],
        [$site_name, $nickname, nl2br(htmlspecialchars($content)), $post_link, $time, $nickname],
        $body_tpl
    );

    send_smtp_email($to, $subject, $body);
}

// 保存配置
function set_setting($key, $value) {
    global $conn;
    if (!$conn) return false;
    $key_esc = mysqli_real_escape_string($conn, $key);
    $val_esc = mysqli_real_escape_string($conn, $value);
    $sql = "INSERT INTO settings (setting_key, setting_value) VALUES ('{$key_esc}', '{$val_esc}') ON DUPLICATE KEY UPDATE setting_value = '{$val_esc}'";
    return mysqli_query($conn, $sql);
}

// 评论列表
function get_comment_tree($conn, $post_id) {
    $all = [];
    $r = mysqli_query($conn, "SELECT * FROM comments WHERE post_id = " . intval($post_id) . " AND status = 1 AND approve_status = 1 ORDER BY created_at DESC");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $all[] = $row;

    $id_map = [];
    foreach ($all as $c) {
        $id_map[intval($c['id'])] = $c;
    }

    $comments = [];
    foreach ($all as $c) {
        $pid = intval($c['parent_id']);
        if ($pid > 0 && isset($id_map[$pid])) {
            $c['parent_nickname'] = $id_map[$pid]['nickname'];
            $c['parent_avatar'] = $id_map[$pid]['avatar'];
            $c['parent_website'] = $id_map[$pid]['website'];
        } else {
            $c['parent_nickname'] = '';
            $c['parent_avatar'] = '';
            $c['parent_website'] = '';
        }
        $comments[] = $c;
    }

    return $comments;
}

function count_all_comments($comments) {
    return count($comments);
}

// 单条评论
function render_comment_item($c, $post_id) {
    $html = '';
    $is_reply = !empty($c['parent_nickname']);
    $website = !empty($c['website']) ? trim($c['website']) : '';
    $parent_website = !empty($c['parent_website']) ? trim($c['parent_website']) : '';

    if ($is_reply) {
        $html .= '<div class="reply-item" onclick="replyComment(' . $post_id . ', \'' . addslashes(htmlspecialchars($c['nickname'])) . '\', ' . $c['id'] . ')">';

        if ($website) {
            $html .= '<a href="' . htmlspecialchars($website) . '" target="_blank" class="reply-avatar-link" onclick="event.stopPropagation()">';
            $html .= '<img src="' . htmlspecialchars($c['avatar'] ?: '') . '" class="reply-avatar" onerror="this.style.background=\'#e0e0e0\';this.src=\'\'">';
            $html .= '</a>';
        } else {
            $html .= '<span class="reply-avatar-link" style="cursor:default;">';
            $html .= '<img src="' . htmlspecialchars($c['avatar'] ?: '') . '" class="reply-avatar" onerror="this.style.background=\'#e0e0e0\';this.src=\'\'">';
            $html .= '</span>';
        }

        if ($website) {
            $html .= '<a href="' . htmlspecialchars($website) . '" target="_blank" class="reply-author-link" onclick="event.stopPropagation()"><span class="reply-author">' . htmlspecialchars($c['nickname']) . '</span></a>';
        } else {
            $html .= '<span class="reply-author">' . htmlspecialchars($c['nickname']) . '</span>';
        }

        $html .= '<span class="reply-label">回复</span>';
        $parent_name = $c['parent_nickname'] ?? '';
        $parent_avatar = $c['parent_avatar'] ?? '';

        if ($parent_avatar) {
            if ($parent_website) {
                $html .= '<a href="' . htmlspecialchars($parent_website) . '" target="_blank" class="reply-to-avatar-link" onclick="event.stopPropagation()"><img src="' . htmlspecialchars($parent_avatar) . '" class="reply-to-avatar-inline" onerror="this.style.display=\'none\'"></a>';
            } else {
                $html .= '<span class="reply-to-avatar-link" style="cursor:default;"><img src="' . htmlspecialchars($parent_avatar) . '" class="reply-to-avatar-inline" onerror="this.style.display=\'none\'"></span>';
            }
        }

        if ($parent_website) {
            $html .= '<a href="' . htmlspecialchars($parent_website) . '" target="_blank" class="reply-to-name-link" onclick="event.stopPropagation()"><span class="reply-to-name">' . htmlspecialchars($parent_name) . '</span></a>';
        } else {
            $html .= '<span class="reply-to-name">' . htmlspecialchars($parent_name) . '</span>';
        }

        $html .= ': <span class="reply-content">' . parse_stickers(nl2br(htmlspecialchars($c['content']))) . '</span>';
        $html .= '</div>';
    } else {
        $html .= '<div class="reply-item" onclick="replyComment(' . $post_id . ', \'' . addslashes(htmlspecialchars($c['nickname'])) . '\', ' . $c['id'] . ')">';

        if ($website) {
            $html .= '<a href="' . htmlspecialchars($website) . '" target="_blank" class="reply-avatar-link" onclick="event.stopPropagation()">';
            $html .= '<img src="' . htmlspecialchars($c['avatar'] ?: '') . '" class="reply-avatar" onerror="this.style.background=\'#e0e0e0\';this.src=\'\'">';
            $html .= '</a>';
        } else {
            $html .= '<span class="reply-avatar-link" style="cursor:default;">';
            $html .= '<img src="' . htmlspecialchars($c['avatar'] ?: '') . '" class="reply-avatar" onerror="this.style.background=\'#e0e0e0\';this.src=\'\'">';
            $html .= '</span>';
        }

        if ($website) {
            $html .= '<a href="' . htmlspecialchars($website) . '" target="_blank" class="reply-author-link" onclick="event.stopPropagation()"><span class="reply-author">' . htmlspecialchars($c['nickname']) . '</span></a>';
        } else {
            $html .= '<span class="reply-author">' . htmlspecialchars($c['nickname']) . '</span>';
        }

        $html .= ': <span class="reply-content">' . parse_stickers(nl2br(htmlspecialchars($c['content']))) . '</span>';
        $html .= '</div>';
    }
    return $html;
}

function render_comment_tree($comments, $post_id, $level = 0, $limit = null) {
    if ($level !== 0) {
        return '';
    }

    if ($limit === null) {
        $settings = get_all_settings();
        $limit = intval($settings['comment_display_limit'] ?? 5);
    }
    $total_limit = max(1, intval($limit));
    $total_count = count_all_comments($comments);

    $preview_items = array_slice($comments, 0, $total_limit);
    $fold_items = array_slice($comments, $total_limit);
    $has_fold = count($fold_items) > 0;

    $preview_html = '';
    foreach ($preview_items as $c) {
        $preview_html .= render_comment_item($c, $post_id);
    }

    $fold_html = '';
    foreach ($fold_items as $c) {
        $fold_html .= render_comment_item($c, $post_id);
    }

    $html = '';
    $html .= '<div class="comment-header-bar">';
    $html .= '<span class="comment-count-text">共 ' . $total_count . ' 条评论</span>';
    if ($has_fold) {
        $html .= '<span class="comment-toggle-btn" onclick="toggleCommentFold(' . $post_id . ', this)">查看全部</span>';
    }
    $html .= '</div>';

    $html .= '<div class="comment-list-wrap">';
    $html .= '<div class="comment-preview">' . $preview_html . '</div>';
    if ($has_fold) {
        $html .= '<div class="comment-rest" id="comment-rest-' . $post_id . '" style="display:none;">' . $fold_html . '</div>';
    }
    $html .= '</div>';

    return $html;
}

function decode_allowed_html($content) {
    $allowed_tags = [
        'p','br','b','i','strong','em','u','s','strike','a','span','img','div','hr',
        'h1','h2','h3','h4','h5','h6','blockquote','code','pre','small','sub','sup',
        'ul','ol','li','table','tr','td','th','thead','tbody','font','center'
    ];
    $allowed_attrs = 'style|href|src|alt|title|target|class|id|width|height|color|size|align';

    $content = preg_replace_callback(
        '/&lt;\/?(\w+)(?:\s+(' . $allowed_attrs . ')=&quot;[^&]*&quot;)*\s*\/?&gt;/',
        function($m) { return html_entity_decode($m[0]); },
        $content
    );

    foreach ($allowed_tags as $tag) {
        $content = str_replace('&lt;' . $tag . '&gt;', '<' . $tag . '>', $content);
        $content = str_replace('&lt;/' . $tag . '&gt;', '</' . $tag . '>', $content);
        $content = str_replace('&lt;' . $tag . ' /&gt;', '<' . $tag . ' />', $content);
    }

    $content = preg_replace_callback(
        '/&lt;(\/?(?:' . implode('|', $allowed_tags) . '))((?:\s+(?:' . $allowed_attrs . ')=[\'"][^\'"]*[\'"])*\s*\/?)&gt;/',
        function($m) {
            return '<' . $m[1] . str_replace('&quot;', '"', $m[2]) . '>';
        },
        $content
    );
    return $content;
}

function render_bilibili_embed($url) {
    $url = trim($url);
    $bvid = '';
    $aid = '';

    $bilibili_domains = ['b23.tv', 'bilibili.com', 'www.bilibili.com', 'player.bilibili.com', 'm.bilibili.com'];
    if (preg_match('/b23\.tv\/([A-Za-z0-9]+)/', $url, $m)) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

        $redirect_hosts = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) use (&$redirect_hosts) {
            $len = strlen($header);
            if (stripos($header, 'Location:') === 0) {
                $location = trim(substr($header, 9));
                $host = parse_url($location, PHP_URL_HOST);
                if ($host) $redirect_hosts[] = strtolower($host);
            }
            return $len;
        });
        curl_exec($ch);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        $final_host = parse_url($finalUrl, PHP_URL_HOST);
        $is_bilibili = false;
        if ($final_host) {
            $final_host_lc = strtolower($final_host);
            foreach ($bilibili_domains as $bd) {
                if ($final_host_lc === $bd || str_ends_with($final_host_lc, '.' . $bd)) {
                    $is_bilibili = true;
                    break;
                }
            }
        }
        if ($finalUrl && $is_bilibili) {
            $url = $finalUrl;
        }
    }

    if (preg_match('/BV([A-Za-z0-9]{10})/', $url, $m)) {
        $bvid = 'BV' . $m[1];
    }

    elseif (preg_match('/av(\d+)/i', $url, $m)) {
        $aid = $m[1];
    }

    elseif (preg_match('/[?&]aid=(\d+)/', $url, $m)) {
        $aid = $m[1];
    }

    elseif (preg_match('/[?&]bvid=(BV[A-Za-z0-9]{10})/', $url, $m)) {
        $bvid = $m[1];
    }

    if (!$bvid && !$aid) {
        return '<p><a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener">' . htmlspecialchars($url) . '</a></p>';
    }

    $params = 'high_quality=1&autoplay=0&danmaku=1';
    if ($bvid) {
        $embedUrl = 'https://player.bilibili.com/player.html?bvid=' . $bvid . '&' . $params;
    } else {
        $embedUrl = 'https://player.bilibili.com/player.html?aid=' . $aid . '&' . $params;
    }

    return '<div class="sc-bilibili"><iframe src="' . $embedUrl . '" scrolling="no" border="0" frameborder="0" framespacing="0" allowfullscreen="true" sandbox="allow-top-navigation allow-same-origin allow-forms allow-scripts allow-popups"></iframe></div>';
}

// 短代码
if (!function_exists('parse_shortcodes')) {
function parse_shortcodes($content, $item) {
    if (!$content) return ['html' => '', 'has_images' => false, 'has_music' => false, 'has_videos' => false];
    $music = $item['music'] ?? [];
    $item_id = $item['id'];
    $replacements = [];
    $idx = 0;
    $has_images = false;
    $has_all_images = false;
    $used_image_indices = [];
    $has_music_flag = false;
    $has_videos = false;

    $content = preg_replace_callback('/\{\{images\}\}/', function($m) use (&$replacements, &$idx, $item, &$has_images) {
        $has_images = true;
        $has_all_images = true;
        $images = $item['images'] ?? [];
        if (empty($images)) { $replacements[$idx] = ''; }
        else {
            $grid_class = 'grid-' . min(count($images), 9);
            $html = '<div class="moment-images ' . $grid_class . '">';
            foreach ($images as $img) { $html .= '<img src="' . htmlspecialchars($img) . '" alt="" onclick=\'openViewer(' . json_encode($img, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_APOS) . ', ' . json_encode($images, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_APOS) . ')\'>'; }
            $html .= '</div>';
            $replacements[$idx] = $html;
        }
        $tag = '%%SC' . $idx . '%%'; $idx++; return $tag;
    }, $content);

    $content = preg_replace_callback('/\{\{music\}\}/', function($m) use (&$replacements, &$idx, $music, $item_id, &$has_music_flag) {
        $has_music_flag = true;
        if (empty($music)) { $replacements[$idx] = ''; }
        else {
            $html = '';
            foreach ($music as $mi => $mu) {
                $html .= '<div class="music-wrap"><div class="mc" id="mc-' . $item_id . '-' . $mi . '" data-murl="' . htmlspecialchars($mu['url'] ?? '') . '" data-mid="' . $item_id . '-' . $mi . '" data-mname="' . htmlspecialchars($mu['name'] ?? '') . '" data-martist="' . htmlspecialchars($mu['artist'] ?? '') . '" data-mcover="' . htmlspecialchars($mu['cover'] ?? '') . '" onclick="playMcCard(this)"><div class="mcv"><img src="' . htmlspecialchars($mu['cover'] ?? '') . '" alt="cover" crossorigin="anonymous"></div><div class="mi"><div class="mn">' . htmlspecialchars($mu['name'] ?? '') . '</div><div class="ma">' . htmlspecialchars($mu['artist'] ?? '') . '</div></div><div class="mp" id="mp-' . $item_id . '-' . $mi . '"></div></div></div>';
            }
            $replacements[$idx] = $html;
        }
        $tag = '%%SC' . $idx . '%%'; $idx++; return $tag;
    }, $content);

    $content = preg_replace_callback('/\{\{videos\}\}/', function($m) use (&$replacements, &$idx, $item, &$has_videos) {
        $has_videos = true;
        $videos = $item['videos'] ?? [];
        $video_covers = $item['video_covers'] ?? [];
        if (empty($videos)) { $replacements[$idx] = ''; }
        else {
            $grid_class = 'grid-' . min(count($videos), 3);
            $html = '<div class="video-grid ' . $grid_class . '">'; $vi = 0;
            foreach ($videos as $video) { $vcover = $video_covers[$vi] ?? ''; $vi++;
                $html .= '<div class="moment-video ' . $grid_class . '" onclick="playVideo(\'' . htmlspecialchars($video) . '\',[' . htmlspecialchars(json_encode($videos)) . '],' . $vi . ')"><video src="' . htmlspecialchars($video) . '" poster="' . htmlspecialchars($vcover) . '" preload="metadata" style="width:100%;height:100%;object-fit:cover;background:#000;" muted playsinline onloadedmetadata="this.parentElement.querySelector(\'.video-duration\').textContent=formatTime(this.duration)"></video><div class="video-play-btn"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div><span class="video-duration">00:00</span></div>';
            }
            $html .= '</div>';
            $replacements[$idx] = $html;
        }
        $tag = '%%SC' . $idx . '%%'; $idx++; return $tag;
    }, $content);

    $content = preg_replace_callback('/\[image\s+id=(\d+)\]/', function($m) use (&$replacements, &$idx, $item, &$has_images, &$used_image_indices) {
        $i = intval($m[1]) - 1;
        $images = $item['images'] ?? [];
        if (isset($images[$i])) {
            $has_images = true;
            $used_image_indices[] = $i;
            $replacements[$idx] = '<div class="moment-images grid-1"><img src="' . htmlspecialchars($images[$i]) . '" alt="" onclick=\'openViewer(' . json_encode($images[$i], JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_APOS) . ', ' . json_encode($images, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_APOS) . ')\'></div>';
        } else { $replacements[$idx] = ''; }
        $tag = '%%SC' . $idx . '%%'; $idx++; return $tag;
    }, $content);

    $content = preg_replace_callback('/\[video\s+id=(\d+)\]/', function($m) use (&$replacements, &$idx, $item, &$has_videos) {
        $i = intval($m[1]) - 1;
        $videos = $item['videos'] ?? [];
        $video_covers = $item['video_covers'] ?? [];
        if (isset($videos[$i])) {
            $has_videos = true;
            $vcover = $video_covers[$i] ?? '';
            $replacements[$idx] = '<div class="video-grid grid-1"><div class="moment-video grid-1" onclick="playVideo(\'' . htmlspecialchars($videos[$i]) . '\',[' . htmlspecialchars(json_encode($videos[$i])) . '],0)"><video src="' . htmlspecialchars($videos[$i]) . '" poster="' . htmlspecialchars($vcover) . '" preload="metadata" style="width:100%;height:100%;object-fit:cover;background:#000;" muted playsinline onloadedmetadata="this.parentElement.querySelector(\'.video-duration\').textContent=formatTime(this.duration)"></video><div class="video-play-btn"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div><span class="video-duration">00:00</span></div></div>';
        } else { $replacements[$idx] = ''; }
        $tag = '%%SC' . $idx . '%%'; $idx++; return $tag;
    }, $content);

    $content = preg_replace_callback('/\[music\s+id=(\d+)\]/', function($m) use (&$replacements, &$idx, $music, $item_id, &$has_music_flag) {
        $i = intval($m[1]) - 1;
        if (isset($music[$i])) {
            $mu = $music[$i];
            $replacements[$idx] = '<div class="music-wrap"><div class="mc" id="mc-' . $item_id . '-' . $i . '" data-murl="' . htmlspecialchars($mu['url'] ?? '') . '" data-mid="' . $item_id . '-' . $i . '" data-mname="' . htmlspecialchars($mu['name'] ?? '') . '" data-martist="' . htmlspecialchars($mu['artist'] ?? '') . '" data-mcover="' . htmlspecialchars($mu['cover'] ?? '') . '" onclick="playMcCard(this)"><div class="mcv"><img src="' . htmlspecialchars($mu['cover'] ?? '') . '" alt="cover" crossorigin="anonymous"></div><div class="mi"><div class="mn">' . htmlspecialchars($mu['name'] ?? '') . '</div><div class="ma">' . htmlspecialchars($mu['artist'] ?? '') . '</div></div><div class="mp" id="mp-' . $item_id . '-' . $i . '"></div></div></div>';
        } else { $replacements[$idx] = ''; }
        $tag = '%%SC' . $idx . '%%'; $idx++; return $tag;
    }, $content);

    $content = preg_replace_callback('/\[video\s+url=([^\s\]]+)(?:\s+cover=([^\]]+))?\]/', function($m) use (&$replacements, &$idx) {
        $url = trim($m[1]);
        $cover = isset($m[2]) ? trim($m[2]) : '';
        $replacements[$idx] = '<div class="video-grid grid-1"><div class="moment-video grid-1" onclick="playVideo(\'' . htmlspecialchars($url) . '\',[' . htmlspecialchars(json_encode($url)) . '],0)"><video src="' . htmlspecialchars($url) . '" poster="' . htmlspecialchars($cover) . '" preload="metadata" style="width:100%;height:100%;object-fit:cover;background:#000;" muted playsinline onloadedmetadata="this.parentElement.querySelector(\'.video-duration\').textContent=formatTime(this.duration)"></video><div class="video-play-btn"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div><span class="video-duration">00:00</span></div></div>';
        $tag = '%%SC' . $idx . '%%'; $idx++; return $tag;
    }, $content);

    $content = preg_replace_callback('/\[html\](.*?)\[\/html\]/s', function($m) use (&$replacements, &$idx) {
        $replacements[$idx] = $m[1];
        $tag = '%%SC' . $idx . '%%'; $idx++;
        return $tag;
    }, $content);

    $content = preg_replace_callback('/\[bilibili\](.*?)\[\/bilibili\]/s', function($m) use (&$replacements, &$idx) {
        $replacements[$idx] = render_bilibili_embed($m[1]);
        $tag = '%%SC' . $idx . '%%'; $idx++;
        return $tag;
    }, $content);

    $content = preg_replace_callback('/\[link\]\s*\[(<svg\b[\s\S]*?<\/svg>|[^\]]*)\]\s*\[([^\]]+)\]\s*\[([^\]]*)\]\s*\[([^\]]+)\]\s*\[\/link\]/i', function($m) use (&$replacements, &$idx) {
        $icon_raw = trim($m[1]);
        $title = trim($m[2]);
        $desc = trim($m[3]);
        $url = trim($m[4]);

        $icon_empty = ($icon_raw === '' || $icon_raw === '图片链接');

        if (preg_match('/^<svg\b/i', $icon_raw)) {
            $icon_html = '<div class="sc-linkcard-icon">' . $icon_raw . '</div>';
        } elseif (!$icon_empty) {
            $icon_html = '<div class="sc-linkcard-icon"><img src="' . htmlspecialchars($icon_raw, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"></div>';
        } else {
            $url_for_host = $url;
            if (!preg_match('/^https?:\/\//i', $url_for_host)) {
                $url_for_host = 'https://' . $url_for_host;
            }
            $host = parse_url($url_for_host, PHP_URL_HOST);
            $favicon = $host ? 'https://favicon.splitbee.io/?url=' . urlencode($host) : '';
            $icon_html = '<div class="sc-linkcard-icon"><img src="' . htmlspecialchars($favicon, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" onerror="this.style.display=\'none\'"></div>';
        }

        $html = '<div class="sc-linkcard-wrap"><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="sc-linkcard" target="_blank" rel="noopener noreferrer">'
              . $icon_html
              . '<div class="sc-linkcard-body">'
              . '<div class="sc-linkcard-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>'
              . ($desc ? '<div class="sc-linkcard-desc">' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</div>' : '')
              . '</div>'
              . '<span class="sc-linkcard-arrow">→</span>'
              . '</a></div>';
        $replacements[$idx] = $html;
        $tag = '%%SC' . $idx . '%%'; $idx++;
        return $tag;
    }, $content);

    $content = nl2br(htmlspecialchars($content));
    $content = decode_allowed_html($content);

    foreach ($replacements as $i => $html) {
        $content = str_replace('%%SC' . $i . '%%', $html, $content);
    }
    return [
        'html' => parse_stickers($content),
        'has_images' => $has_images,
        'has_all_images' => $has_all_images,
        'used_image_indices' => $used_image_indices,
        'has_music' => $has_music_flag,
        'has_videos' => $has_videos
    ];
}
}

// 首图
function extract_first_image_from_content($content) {
    if (empty($content)) return '';

    if (preg_match('/!\[.*?\]\s*\(\s*(https?:\/\/[^\s\)]+)\s*\)/', $content, $m)) {
        return $m[1];
    }

    if (preg_match('/<img[^>]+src=["\'](https?:\/\/[^"\']+)["\']/', $content, $m)) {
        return $m[1];
    }
    return '';
}

// GitHub 卡片
function render_github_card_inline($user, $repo) {
    $cache_file = sys_get_temp_dir() . '/sc_gh_' . md5($user . '_' . $repo) . '.json';
    $data = null;
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 3600) {
        $data = json_decode(file_get_contents($cache_file), true);
    }
    if (!$data) {
        $ctx = stream_context_create([
            'http' => ['timeout' => 2, 'header' => "User-Agent: YunTingJi-Shortcode\r\nAccept: application/vnd.github.v3+json\r\n", 'follow_location' => 0],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ]);
        $res = @file_get_contents('https://api.github.com/repos/' . rawurlencode($user) . '/' . rawurlencode($repo), false, $ctx);
        if ($res) {
            $decoded = json_decode($res, true);
            if ($decoded && isset($decoded['full_name'])) {
                $data = $decoded;
                @file_put_contents($cache_file, $res);
            }
        }
    }
    $stars = isset($data['stargazers_count']) ? number_format($data['stargazers_count']) : '0';
    $forks = isset($data['forks_count']) ? number_format($data['forks_count']) : '0';
    $desc = isset($data['description']) ? htmlspecialchars((string)$data['description'], ENT_QUOTES, 'UTF-8') : 'No description provided.';
    $homepage = isset($data['homepage']) && $data['homepage'] ? htmlspecialchars((string)$data['homepage'], ENT_QUOTES, 'UTF-8') : '';
    $full_name = htmlspecialchars($user . '/' . $repo, ENT_QUOTES, 'UTF-8');
    $repo_url = 'https://github.com/' . rawurlencode($user) . '/' . rawurlencode($repo);
    $updated = isset($data['updated_at']) ? date('n-j-Y', strtotime($data['updated_at'])) : date('n-j-Y');
    $branch = isset($data['default_branch']) ? htmlspecialchars($data['default_branch']) : 'master';
    $language = isset($data['language']) && $data['language'] ? htmlspecialchars($data['language']) : '-';

    $html = '<div class="sc-github">';

    $html .= '<div class="sc-github-header">';
    $html .= '<svg class="sc-github-logo" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>';
    $html .= '<div class="sc-github-name">' . $full_name . '</div>';
    $html .= '<div class="sc-github-stats">';
    $html .= '<span class="sc-github-stars"><svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>' . $stars . '</span>';
    $html .= '<span class="sc-github-forks"><svg viewBox="0 0 24 24"><circle cx="12" cy="18" r="3"/><circle cx="6" cy="6" r="3"/><circle cx="18" cy="6" r="3"/><path d="M6 9v3a3 3 0 0 0 3 3h6a3 3 0 0 0 3-3V9"/><path d="M12 15V9"/></svg>' . $forks . '</span>';
    $html .= '</div></div>';

    if ($desc) {
        $html .= '<div class="sc-github-body">';
        $html .= '<p class="sc-github-desc">' . $desc;
        if ($homepage) {
            $html .= ' <a class="sc-github-link" href="' . $homepage . '" target="_blank" rel="noopener noreferrer">Read More &rarr;</a>';
        }
        $html .= '</p>';
        $html .= '</div>';
    }

    $html .= '<div class="sc-github-footer">';
    $html .= '<span>' . $branch . ' &middot; ' . $language . ' &middot; ' . $updated . '</span>';
    $html .= '<a class="sc-github-download" href="' . $repo_url . '/archive/refs/heads/' . $branch . '.zip" target="_blank" rel="noopener noreferrer"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>zip</a>';
    $html .= '</div></div>';
    return $html;
}

// 正文解析
function parse_content($content) {
    if (empty($content)) return '';

    $Parsedown = new Parsedown();

    $content = preg_replace('/""\n?(.*?)\n?""/s', "```\n$1\n```", $content);

    $content = str_replace('![', "\x00IMG\x00", $content);

    $content = preg_replace_callback(
        '/\[([^\]]*)\]\s*\n?\s*\(([^)]+)\)/',
        function($matches) {
            $alt = $matches[1];
            $url = trim($matches[2]);

            if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $url)) {
                return '![' . $alt . '](' . $url . ')';
            }

            return $matches[0];
        },
        $content
    );

    $content = str_replace("\x00IMG\x00", '![', $content);

    $content = preg_replace('/!\[([^\]]*)\]\s*\n\s*\(\s*([^\s\)]+)\s*\)/', '![$1]($2)', $content);

    $content = preg_replace('/!\[([^\]]*)\]\s*\(\s*([^\s\)]+)\s*\)/', '![$1]($2)', $content);

    $shortcode_placeholders = array();
    $shortcode_counter = 0;

    $content = preg_replace_callback(
        '/\[tabs\s*(?:selected="(\d+)")?\s*\](.*?)\[\/tabs\]/s',
        function($m) use (&$shortcode_placeholders, &$shortcode_counter, $Parsedown) {
            $ph = '%%SC' . ($shortcode_counter++) . '%%';
            $selected = intval(($m[1] ?? '') ?: '1');
            $inner = $m[2];
            preg_match_all('/\[tab\s+name="([^"]*)"\](.*?)\[\/tab\]/s', $inner, $tabMatches, PREG_SET_ORDER);
            if (empty($tabMatches)) {
                $shortcode_placeholders[$ph] = '';
                return $ph;
            }
            $tabs_id = 'tabs_' . mt_rand(10000, 99999);
            $nav = ''; $panes = ''; $i = 0;
            foreach ($tabMatches as $t) {
                $i++;
                $act = ($i === $selected);
                $nav .= '<button type="button" class="sc-tab-trigger' . ($act ? ' active' : '') . '" data-tabs="' . $tabs_id . '" data-index="' . ($i-1) . '">' . htmlspecialchars($t[1]) . '</button>';

                $tab_html = parse_content(trim($t[2]));
                $panes .= '<div class="sc-tab-pane' . ($act ? ' active' : '') . '" data-tabs="' . $tabs_id . '" data-index="' . ($i-1) . '">' . $tab_html . '</div>';
            }
            $shortcode_placeholders[$ph] = '<div class="sc-tabs" id="' . $tabs_id . '"><div class="sc-tabs-nav">' . $nav . '</div><div class="sc-tabs-content">' . $panes . '</div></div>';
            return $ph;
        },
        $content
    );

    $content = preg_replace_callback(
        '/\[hint\s*(?:type="([^"]*)")?\s*(?:title="([^"]*)")?\s*(warn)?\s*\](.*?)\[\/hint\]/s',
        function($m) use (&$shortcode_placeholders, &$shortcode_counter) {
            $ph = '%%SC' . ($shortcode_counter++) . '%%';
            $type = trim($m[1] ?? '');
            $title = trim($m[2] ?? '');
            $warnFlag = trim($m[3] ?? '');
            $body = nl2br(htmlspecialchars(trim($m[4])));
            $hasTitle = $title !== '';
            if ($type === 'danger' || $type === 'error') {
                $cssType = 'error';
                $iconHtml = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>';
            } elseif ($warnFlag === 'warn' || $type === 'warn' || $type === 'warning') {
                $cssType = 'warning';
                $iconHtml = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>';
            } else {
                $cssType = 'info';
                $iconHtml = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>';
            }
            $out = '<div class="sc-hint sc-hint-' . $cssType . '">';
            $out .= '<div class="sc-hint-icon">' . $iconHtml . '</div>';
            $out .= '<div class="sc-hint-body">';
            if ($hasTitle) $out .= '<div class="sc-hint-title">' . htmlspecialchars($title) . '</div>';
            $out .= '<div class="sc-hint-content">' . $body . '</div>';
            $out .= '</div></div>';
            $shortcode_placeholders[$ph] = $out;
            return $ph;
        },
        $content
    );

    $content = preg_replace_callback(
        '/\[(?:btn|button)\s+url="([^"]*)"\s*\](.*?)\[\/(?:btn|button)\]/s',
        function($m) use (&$shortcode_placeholders, &$shortcode_counter) {
            $ph = '%%SC' . ($shortcode_counter++) . '%%';
            $url = htmlspecialchars($m[1]);
            $text = htmlspecialchars(trim($m[2]));
            $shortcode_placeholders[$ph] = '<a href="' . $url . '" class="sc-btn sc-btn-primary">' . $text . '</a>';
            return $ph;
        },
        $content
    );

    $content = preg_replace_callback(
        '/\[file\b([^\]]*)\](.*?)\[\/file\]/s',
        function($m) use (&$shortcode_placeholders, &$shortcode_counter) {
            $ph = '%%SC' . ($shortcode_counter++) . '%%';
            $attr = trim($m[1]);
            $body = trim($m[2]);
            $params = [];
            preg_match_all('/(\w+)="([^"]*)"/', $attr, $pm, PREG_SET_ORDER);
            foreach ($pm as $p) $params[$p[1]] = $p[2];
            $url = htmlspecialchars($params['url'] ?? '#', ENT_QUOTES, 'UTF-8');
            $name = htmlspecialchars($body ?: ($params['name'] ?? '未知文件'), ENT_QUOTES, 'UTF-8');
            $shortcode_placeholders[$ph] = '<a href="' . $url . '" class="sc-file" target="_blank" rel="noopener noreferrer">'
                 . '<svg class="sc-file-icon" viewBox="0 0 24 24" fill="#555"><path d="M18 22a2 2 0 0 0 2-2V8l-6-6H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2zM13 4l5 5h-5zM7 8h3v2H7zm0 4h10v2H7zm0 4h10v2H7z"/></svg>'
                 . '<span class="sc-file-name">' . $name . '</span></a>';
            return $ph;
        },
        $content
    );

    $content = preg_replace_callback(
        '/\[github\s+([^\]]*)\/\]/',
        function($m) use (&$shortcode_placeholders, &$shortcode_counter) {
            $ph = '%%SC' . ($shortcode_counter++) . '%%';
            $params = [];
            preg_match_all('/(\w+)="([^"]*)"/', $m[1], $pm, PREG_SET_ORDER);
            foreach ($pm as $p) $params[$p[1]] = $p[2];
            $repo_full = $params['repo'] ?? '';
            if (strpos($repo_full, '/') !== false) {
                list($user, $repo) = explode('/', $repo_full, 2);
            } else {
                $user = $params['user'] ?? '';
                $repo = $repo_full;
            }
            if (!$user || !$repo) { $shortcode_placeholders[$ph] = ''; return $ph; }
            $shortcode_placeholders[$ph] = render_github_card_inline($user, $repo);
            return $ph;
        },
        $content
    );

    $content = preg_replace_callback(
        '/\[bilibili\](.*?)\[\/bilibili\]/s',
        function($m) use (&$shortcode_placeholders, &$shortcode_counter) {
            $ph = '%%SC' . ($shortcode_counter++) . '%%';
            $shortcode_placeholders[$ph] = render_bilibili_embed($m[1]);
            return $ph;
        },
        $content
    );

    $content = preg_replace_callback(
        '/\[link\]\s*\[(<svg\b[\s\S]*?<\/svg>|[^\]]*)\]\s*\[([^\]]+)\]\s*\[([^\]]*)\]\s*\[([^\]]+)\]\s*\[\/link\]/i',
        function($m) use (&$shortcode_placeholders, &$shortcode_counter) {
            $ph = '%%SC' . ($shortcode_counter++) . '%%';
            $icon_raw = trim($m[1]);
            $title = trim($m[2]);
            $desc = trim($m[3]);
            $url = trim($m[4]);

            $icon_empty = ($icon_raw === '' || $icon_raw === '图片链接');

            if (preg_match('/^<svg\b/i', $icon_raw)) {
                $icon_html = '<div class="sc-linkcard-icon">' . $icon_raw . '</div>';
            } elseif (!$icon_empty) {
                $icon_html = '<div class="sc-linkcard-icon"><img src="' . htmlspecialchars($icon_raw, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"></div>';
            } else {
                $url_for_host = $url;
                if (!preg_match('/^https?:\/\//i', $url_for_host)) {
                    $url_for_host = 'https://' . $url_for_host;
                }
                $host = parse_url($url_for_host, PHP_URL_HOST);
                $favicon = $host ? 'https://favicon.splitbee.io/?url=' . urlencode($host) : '';
                $icon_html = '<div class="sc-linkcard-icon"><img src="' . htmlspecialchars($favicon, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" onerror="this.style.display=\'none\'"></div>';
            }

        $out = '<div class="sc-linkcard-wrap"><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="sc-linkcard" target="_blank" rel="noopener noreferrer">'
             . $icon_html
             . '<div class="sc-linkcard-body">'
             . '<div class="sc-linkcard-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>'
             . ($desc ? '<div class="sc-linkcard-desc">' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</div>' : '')
                 . '</div>'
                 . '<span class="sc-linkcard-arrow">→</span>'
                 . '</a></div>';

            $block_ph = '<div class="sc-linkcard-placeholder" data-sc="' . $shortcode_counter . '"></div>';
            $shortcode_placeholders[$block_ph] = $out;
            $shortcode_counter++;
            return $block_ph;
        },
        $content
    );

    $content = preg_replace_callback(
        '/\[ul-square\](.*?)\[\/ul-square\]/s',
        function($m) use (&$shortcode_placeholders, &$shortcode_counter) {
            $inner = trim($m[1]);
            $pd = new Parsedown();
            $list_html = $pd->text($inner);

            $list_html = preg_replace('/<ul>/', '<ul class="ul-square">', $list_html, 1);
            $block_ph = '<div class="sc-ul-square-placeholder" data-sc="' . $shortcode_counter . '"></div>';
            $shortcode_placeholders[$block_ph] = $list_html;
            $shortcode_counter++;
            return $block_ph;
        },
        $content
    );

    $content = preg_replace_callback(
        '/\[ol-gray-bg\](.*?)\[\/ol-gray-bg\]/s',
        function($m) use (&$shortcode_placeholders, &$shortcode_counter) {
            $inner = trim($m[1]);
            $pd = new Parsedown();
            $list_html = $pd->text($inner);
            $list_html = preg_replace('/<ol>/', '<ol class="ol-gray-bg">', $list_html, 1);
            $block_ph = '<div class="sc-ol-gray-bg-placeholder" data-sc="' . $shortcode_counter . '"></div>';
            $shortcode_placeholders[$block_ph] = $list_html;
            $shortcode_counter++;
            return $block_ph;
        },
        $content
    );

    $content = preg_replace_callback(
        '/\[tag\s+color="([^"]*)"\](.*?)\[\/tag\]/s',
        function($m) use (&$shortcode_placeholders, &$shortcode_counter) {
            $color = trim($m[1]);
            $text  = trim($m[2]);

            if (!preg_match('/^(#[0-9a-fA-F]{3,8}|[a-zA-Z]+)$/', $color)) {
                return $m[0];
            }
            $out = '<span class="sc-ctag" style="background:' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
            $inline_ph = '<span class="sc-ctag-placeholder" data-sc="' . $shortcode_counter . '"></span>';
            $shortcode_placeholders[$inline_ph] = $out;
            $shortcode_counter++;
            return $inline_ph;
        },
        $content
    );

    $content = preg_replace_callback(
        '/\[graybox\](.*?)\[\/graybox\]/s',
        function($m) use (&$shortcode_placeholders, &$shortcode_counter) {
            $inner = trim($m[1]);
            $pd = new Parsedown();
            $inner_html = $pd->text($inner);
            $out = '<div class="sc-graybox">' . $inner_html . '</div>';
            $block_ph = '<div class="sc-graybox-placeholder" data-sc="' . $shortcode_counter . '"></div>';
            $shortcode_placeholders[$block_ph] = $out;
            $shortcode_counter++;
            return $block_ph;
        },
        $content
    );

    $content = preg_replace_callback(
        '/\\[mark\\](.*?)\\[\\/mark\\]/s',
        function($m) use (&$shortcode_placeholders, &$shortcode_counter) {
            $text = trim($m[1]);
            $out = '<mark class="sc-mark">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</mark>';
            $inline_ph = '<span class="sc-mark-placeholder" data-sc="' . $shortcode_counter . '"></span>';
            $shortcode_placeholders[$inline_ph] = $out;
            $shortcode_counter++;
            return $inline_ph;
        },
        $content
    );

    $content = preg_replace_callback(
        '/\\[figure\\s+([^\\]]*)\\]/',
        function($m) use (&$shortcode_placeholders, &$shortcode_counter) {
            $attr = trim($m[1]);
            $params = [];
            preg_match_all('/(\\w+)="([^"]*)"/', $attr, $pm, PREG_SET_ORDER);
            foreach ($pm as $p) $params[$p[1]] = $p[2];
            $src = htmlspecialchars($params['src'] ?? '', ENT_QUOTES, 'UTF-8');
            $caption = htmlspecialchars($params['caption'] ?? '', ENT_QUOTES, 'UTF-8');
            if (!$src) return '';
            $out = '<figure class="sc-figure">'
                 . '<img src="' . $src . '" alt="' . $caption . '" loading="lazy">'
                 . ($caption ? '<figcaption>' . $caption . '</figcaption>' : '')
                 . '</figure>';
            $block_ph = '<div class="sc-figure-placeholder" data-sc="' . $shortcode_counter . '"></div>';
            $shortcode_placeholders[$block_ph] = $out;
            $shortcode_counter++;
            return $block_ph;
        },
        $content
    );

    $content = preg_replace_callback(
        '/\\[timeline\\](.*?)\\[\\/timeline\\]/s',
        function($m) use (&$shortcode_placeholders, &$shortcode_counter) {
            $inner = trim($m[1]);
            $pd = new Parsedown();
            $inner_html = $pd->text($inner);
            $inner_html = preg_replace('/<ul>/', '<ul class="sc-timeline-list">', $inner_html, 1);
            $out = '<div class="sc-timeline">' . $inner_html . '</div>';
            $block_ph = '<div class="sc-timeline-placeholder" data-sc="' . $shortcode_counter . '"></div>';
            $shortcode_placeholders[$block_ph] = $out;
            $shortcode_counter++;
            return $block_ph;
        },
        $content
    );

    $Parsedown = new Parsedown();
    $html = $Parsedown->text($content);

    $html = str_replace('@@@@@', '<div style="letter-spacing:8px;text-align:center;color:#bbb;margin:20px 0;">&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;</div>', $html);
    $html = str_replace('@@@@', '<hr style="border:0;height:8px;background:repeating-linear-gradient(45deg,transparent,transparent 4px,#ddd 4px,#ddd 8px);margin:20px 0;">', $html);
    $html = str_replace('@@@', '<hr style="border:0;border-top:1px dashed #dddddd;margin:30px 0;">', $html);

    $html = preg_replace_callback(
        '/<details>(.*?)<\/details>/s',
        function($m) use ($Parsedown) {
            $inner = $m[1];
            preg_match('/<summary>(.*?)<\/summary>/', $inner, $sm);
            $title = htmlspecialchars(trim($sm[1] ?? '点击展开'), ENT_QUOTES, 'UTF-8');
            $body = preg_replace('/<summary>.*?<\/summary>/s', '', $inner, 1);
            $body = trim($body);

            $body = parse_content($body);
            return '<div class="sc-collapse" style="border:1px solid #e0e0e0;border-radius:10px;margin:18px 0;overflow:hidden;background:#fafafa;">'
                 . '<button class="sc-collapse-header" onclick="var w=this.nextElementSibling,a=this.querySelector(\'.arrow\');if(w.style.display===\'none\'||!w.style.display){w.style.display=\'block\';a.style.transform=\'rotate(180deg)\'}else{w.style.display=\'none\';a.style.transform=\'rotate(0deg)\'}" style="width:100%;padding:10px 16px;text-align:left;border:none;background:#fafafa;cursor:pointer;font-size:14px;display:flex;justify-content:space-between;align-items:center;margin:0;-webkit-tap-highlight-color:transparent;outline:none;user-select:none;-webkit-touch-callout:none;border-bottom:none;"><span style="font-weight:500;color:#222;">' . $title . '</span><span class="arrow" style="transition:transform .3s;font-size:12px;color:#888;">&#9660;</span></button>'
                 . '<div class="sc-collapse-body" style="display:none;border-top:1px solid #e0e0e0;">' . $body . '</div>'
                 . '</div>';
        },
        $html
    );

    $html = preg_replace(
        '/<kbd>([^<]+)<\/kbd>/',
        '<kbd style="display:inline-block;padding:3px 8px;font-family:inherit;font-size:0.85em;line-height:1.4;color:#333;background:linear-gradient(to bottom,#f7f7f7,#eaeaea);border:1px solid #bbb;border-radius:5px;box-shadow:0 1px 2px rgba(0,0,0,0.15),inset 0 1px 0 rgba(255,255,255,0.8);white-space:nowrap;margin:0 1px;vertical-align:middle;-webkit-tap-highlight-color:transparent;outline:none;">$1</kbd>',
        $html
    );

    $html = preg_replace_callback(
        '/<li>\s*\[([ xX])\]\s*(.*?)<\/li>/',
        function($m) {
            $checked = strtolower($m[1]) === 'x';
            $text = trim($m[2]);
            $doneClass = $checked ? ' done' : '';
            $checkContent = $checked ? '&#10003;' : '';
            $textClass = $checked ? ' class="txt" style="text-decoration:line-through;color:#999;"' : ' class="txt" style="color:#333;"';
            return '<li class="task-item' . $doneClass . '" style="list-style:none;margin-left:0;padding-left:0;">'
                 . '<div class="task-row" style="display:flex;gap:10px;margin:6px 0;align-items:flex-start;">'
                 . '<div class="task-check" style="width:17px;height:17px;border:1.5px solid ' . ($checked ? '#23965b' : '#999') . ';border-radius:3px;margin-top:2px;flex-shrink:0;text-align:center;font-size:12px;line-height:17px;color:#fff;background:' . ($checked ? '#23965b' : 'transparent') . ';">' . $checkContent . '</div>'
                 . '<div' . $textClass . '>' . $text . '</div>'
                 . '</div></li>';
        },
        $html
    );

    $html = preg_replace(
        '/<ul>(\s*<li class="task-item)/',
        '<ul class="task-list" style="list-style:none;margin-left:0;padding-left:0;">$1',
        $html
    );

    foreach ($shortcode_placeholders as $ph => $sc_html) {
        $html = str_replace($ph, $sc_html, $html);
    }

    $html = preg_replace('/<p>\s*(<div class="sc-hint)/', '$1', $html);
    $html = preg_replace('/(<\/div>)\s*<\/p>/', '$1', $html);
    $html = preg_replace('/<p>\s*(<div class="sc-tabs)/', '$1', $html);
    $html = preg_replace('/(<\/div>)\s*<\/p>/s', '$1', $html);
    $html = preg_replace('/<p>\s*(<a href="[^"]*" class="sc-btn)/', '$1', $html);
    $html = preg_replace('/(<\/a>)\s*<\/p>/', '$1', $html);

    $html = preg_replace('/<p>\s*(<a href="[^"]*" class="sc-file)/', '$1', $html);
    $html = preg_replace('/(<\/a>)\s*<\/p>/', '$1', $html);
    $html = preg_replace('/<p>\s*(<div class="sc-github)/', '$1', $html);
    $html = preg_replace('/(<\/div>\s*<\/div>)\s*<\/p>/s', '$1', $html);

    $html = preg_replace('/<p>\s*(<div class="sc-bilibili)/', '$1', $html);
    $html = preg_replace('/(<\/div>)\s*<\/p>/s', '$1', $html);

    $html = preg_replace('/<p>\s*(<div class="sc-linkcard-wrap)/', '$1', $html);
    $html = preg_replace('/(<\/div>\s*<\/a><\/div>)\s*<\/p>/s', '$1', $html);

    $html = preg_replace('/<ul>/', '<ul class="list3">', $html, 1);

    $html = preg_replace('/<ol>/', '<ol class="ol1">', $html, 1);

    $html = preg_replace('/<p>\s*(<div class="sc-graybox)/', '$1', $html);
    $html = preg_replace('/<p>\s*(<figure class="sc-figure)/', '$1', $html);
    $html = preg_replace('/<p>\s*(<div class="sc-timeline)/', '$1', $html);
    $html = preg_replace('/(<\\/figure>)\s*<\\/p>/', '$1', $html);

    return $html;
}

// 访客记录与拦截
if (!function_exists('ytj_ensure_visitor_table')) {
    function ytj_ensure_visitor_table() {
        global $conn;
        if (!$conn) return;
        static $done = false;
        if ($done) return;
        $done = true;
        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `visitor_logs` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `ip` varchar(45) NOT NULL DEFAULT '',
            `carrier` varchar(50) DEFAULT '' COMMENT '运营商',
            `country` varchar(50) DEFAULT '',
            `region` varchar(50) DEFAULT '' COMMENT '省份',
            `city` varchar(50) DEFAULT '' COMMENT '城市',
            `isp` varchar(100) DEFAULT '',
            `browser` varchar(50) DEFAULT '' COMMENT '浏览器',
            `os` varchar(50) DEFAULT '' COMMENT '操作系统',
            `device` varchar(20) DEFAULT '' COMMENT '设备',
            `model` varchar(100) DEFAULT '' COMMENT '手机型号',
            `referer` varchar(500) DEFAULT '' COMMENT '来源页',
            `url` varchar(500) DEFAULT '' COMMENT '访问地址',
            `page_type` varchar(30) DEFAULT '' COMMENT '操作类型',
            `target` varchar(100) DEFAULT '' COMMENT '操作对象',
            `user_agent` varchar(500) DEFAULT '',
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `ip` (`ip`),
            KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='访客访问记录'");

        $chk = @mysqli_query($conn, "SHOW COLUMNS FROM `visitor_logs` LIKE 'model'");
        if (!$chk || mysqli_num_rows($chk) === 0) {
            @mysqli_query($conn, "ALTER TABLE `visitor_logs` ADD COLUMN `model` varchar(100) DEFAULT '' COMMENT '手机型号' AFTER `device`");
        }
    }
}

if (!function_exists('ytj_geo_lookup')) {
    function ytj_geo_lookup($ip) {
        $default = ['carrier' => '未知', 'country' => '', 'region' => '', 'city' => '', 'isp' => ''];
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return $default;
        if (in_array($ip, ['127.0.0.1', '::1'], true)) {
            return array_merge($default, ['country' => '本机', 'region' => '本地', 'city' => '本地']);
        }
        $dir = __DIR__ . '/cache';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $cf = $dir . '/geo_' . md5($ip) . '.json';
        if (file_exists($cf) && (time() - filemtime($cf) < 86400)) {
            $c = @json_decode(@file_get_contents($cf), true);
            if ($c) return $c;
        }
        $result = $default;
        $ctx = @stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
        $resp = @file_get_contents('http://ip-api.com/json/' . urlencode($ip) . '?fields=status,message,country,regionName,city,isp,org,as', false, $ctx);
        if ($resp) {
            $d = @json_decode($resp, true);
            if (!empty($d) && ($d['status'] ?? '') === 'success') {
                $isp_raw = trim($d['isp'] ?? '');
                $org_raw = trim($d['org'] ?? '');

                if ($org_raw !== '' && strcasecmp($org_raw, $isp_raw) !== 0
                    && stripos($isp_raw, $org_raw) === false && stripos($org_raw, $isp_raw) === false) {
                    $isp = trim($isp_raw . ' ' . $org_raw);
                } else {
                    $isp = $isp_raw !== '' ? $isp_raw : $org_raw;
                }
                $carrier = '未知';
                if (stripos($isp, '电信') !== false || stripos($isp, 'chinanet') !== false) $carrier = '中国电信';
                elseif (stripos($isp, '联通') !== false || stripos($isp, 'unicom') !== false) $carrier = '中国联通';
                elseif (stripos($isp, '移动') !== false || stripos($isp, 'mobile') !== false) $carrier = '中国移动';
                elseif ($isp !== '') $carrier = $isp;
                $result = [
                    'carrier' => $carrier,
                    'country' => $d['country'] ?? '',
                    'region'  => $d['regionName'] ?? '',
                    'city'    => $d['city'] ?? '',
                    'isp'     => $isp
                ];
            }
        }
        @file_put_contents($cf, json_encode($result));
        return $result;
    }
}

if (!function_exists('ytj_parse_ua')) {
    function ytj_parse_ua($ua) {
        $ua = (string)$ua;
        $browser = '未知'; $os = '未知'; $device = 'PC';
        if (preg_match('/Edg\//', $ua)) $browser = 'Edge';
        elseif (preg_match('/OPR\/|Opera/', $ua)) $browser = 'Opera';
        elseif (preg_match('/Firefox\//', $ua)) $browser = 'Firefox';
        elseif (preg_match('/Chrome\//', $ua)) $browser = 'Chrome';
        elseif (preg_match('/Safari\//', $ua)) $browser = 'Safari';
        if (preg_match('/Windows NT 10.0/', $ua)) $os = 'Windows 11';
        elseif (preg_match('/Windows NT 6.3/', $ua)) $os = 'Windows 8.1';
        elseif (preg_match('/Windows NT 6.1/', $ua)) $os = 'Windows 7';
        elseif (preg_match('/Windows NT/', $ua)) $os = 'Windows';
        elseif (preg_match('/Mac OS X (\d+)[_.](\d+)/', $ua, $m)) {
            $macmap = array(10=>'macOS',11=>'macOS Big Sur',12=>'macOS Monterey',13=>'macOS Ventura',14=>'macOS Sonoma',15=>'macOS Sequoia');
            $os = $macmap[(int)$m[1]] ?? 'macOS';
        }
        elseif (preg_match('/iPhone|iPad|iPod/', $ua)) {
            if (preg_match('/OS (\d+)/', $ua, $m)) $os = 'iOS ' . $m[1];
            else $os = 'iOS';
        }
        elseif (preg_match('/Mac OS X|Macintosh/', $ua)) $os = 'macOS';
        elseif (preg_match('/Android (\d+(?:\.\d+)?)/', $ua, $m)) $os = 'Android ' . $m[1];
        elseif (preg_match('/Android/', $ua)) $os = 'Android';

        elseif (preg_match('/Linux/', $ua)) $os = 'Linux';
        if (preg_match('/Mobile|Android.*Mobile|iPhone|iPod/', $ua)) $device = '手机';
        elseif (preg_match('/iPad|Android(?!.*Mobile)|Tablet/', $ua)) $device = '平板';
        $model = '';
        if (preg_match('/Android[^;]*;([^;]+?)Build/i', $ua, $m)) {
            $model = trim($m[1]);
        } elseif (preg_match('/iPhone(\d+),(\d+)/', $ua, $m)) {
            $imap = array('15,2'=>'iPhone 14 Pro','15,3'=>'iPhone 14 Pro Max','14,2'=>'iPhone 13 Pro','14,3'=>'iPhone 13 Pro Max','13,2'=>'iPhone 12','13,3'=>'iPhone 12 Pro','13,4'=>'iPhone 12 Pro Max','12,1'=>'iPhone 11','12,3'=>'iPhone 11 Pro','12,5'=>'iPhone 11 Pro Max','15,4'=>'iPhone 15','15,5'=>'iPhone 15 Plus','16,1'=>'iPhone 15 Pro','16,2'=>'iPhone 15 Pro Max','14,4'=>'iPhone 13 mini','14,5'=>'iPhone 13','10,3'=>'iPhone X','10,2'=>'iPhone 8 Plus','10,1'=>'iPhone 8','11,2'=>'iPhone XS','11,4'=>'iPhone XS Max','11,8'=>'iPhone XR','9,1'=>'iPhone 7','9,3'=>'iPhone 7 Plus');
            $model = $imap[$m[1].','.$m[2]] ?? ('iPhone '.$m[1].','.$m[2]);
        } elseif (preg_match('/iPad(\d+),(\d+)/', $ua, $m)) {
            $model = 'iPad '.$m[1].','.$m[2];
        } elseif (preg_match('/Macintosh/i', $ua)) {
            $model = 'Mac';
        }
        return [$browser, $os, $device, $model];
    }
}

if (!function_exists('ytj_log_visitor')) {
    function ytj_log_visitor($page_type, $target = '') {
        global $conn;
        if (!$conn) return;
        ytj_ensure_visitor_table();
        $ip = get_real_ip();

        $dedup_minutes = defined('VISITOR_DEDUP_MINUTES') ? (int)VISITOR_DEDUP_MINUTES : 10;
        $ip_esc = mysqli_real_escape_string($conn, $ip);
        $recent = mysqli_query($conn, "SELECT 1 FROM visitor_logs WHERE ip='$ip_esc' AND created_at >= DATE_SUB(NOW(), INTERVAL $dedup_minutes MINUTE) LIMIT 1");
        if ($recent && mysqli_fetch_row($recent)) {
            return;
        }
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        list($browser, $os, $device, $model) = ytj_parse_ua($ua);
        $geo = ytj_geo_lookup($ip);
        $referer = isset($_SERVER['HTTP_REFERER']) ? mb_substr($_SERVER['HTTP_REFERER'], 0, 500) : '';
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $url = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
        $url = mb_substr($url, 0, 500);
        $esc = function($v) use ($conn) { return "'" . mysqli_real_escape_string($conn, (string)$v) . "'"; };
        $sql = "INSERT INTO visitor_logs (ip, carrier, country, region, city, isp, browser, os, device, model, referer, url, page_type, target, user_agent, created_at) VALUES ("
            . $esc($ip) . ',' . $esc($geo['carrier']) . ',' . $esc($geo['country']) . ',' . $esc($geo['region']) . ',' . $esc($geo['city']) . ','
            . $esc($geo['isp']) . ',' . $esc($browser) . ',' . $esc($os) . ',' . $esc($device) . ',' . $esc($model) . ',' . $esc($referer) . ',' . $esc($url) . ','
            . $esc($page_type) . ',' . $esc($target) . ',' . $esc(mb_substr($ua, 0, 500)) . ', NOW())';
        @mysqli_query($conn, $sql);
    }
}

if (!function_exists('ytj_frontend_init')) {
    function ytj_frontend_init() {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';

        if (strpos($script, '/admin/') !== false || strpos($script, '/install/') !== false) return;
        $ip = get_real_ip();

        if (is_ip_banned($ip)) {
            header('HTTP/1.1 403 Forbidden');
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>禁止访问</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:system-ui}
body{height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#f7f8fa;color:#333}
.code{font-size:120px;font-weight:bold;color:#222;opacity:.15}
.title{font-size:26px;margin:-30px 0 12px}
.desc{color:#666;margin-bottom:30px}
.btn{padding:10px 26px;background:#222;color:#fff;border:none;border-radius:6px;text-decoration:none}
.btn:hover{background:#444}
</style>
</head>
<body>
<div class="code">555</div>
<h1 class="title">禁止访问</h1>
<p class="desc">你的IP已记录并被拉黑</p>
</body>
</html>';
            exit;
        }

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['admin_logged_in'])) return;
        $bn = basename($script);
        if ($bn === 'index.php') {
            ytj_log_visitor('浏览首页');
        } elseif ($bn === 'article.php') {
            ytj_log_visitor('浏览文章', '文章 #' . ($_GET['id'] ?? ''));
        } elseif ($bn === 'page.php') {
            ytj_log_visitor('浏览页面', ($_GET['slug'] ?? ''));
        }
    }
}

// 前台拦截
ytj_frontend_init();
