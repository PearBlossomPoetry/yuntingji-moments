<?php
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (!file_exists(__DIR__ . '/install.lock')) {
    require_once __DIR__ . '/not_installed.php';
    exit;
}
require_once 'config.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if (empty($slug)) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($uri, PHP_URL_PATH) ?? '';
    $filename = basename($path);
    if (preg_match('/^([a-zA-Z0-9_-]+)\.html$/', $filename, $m)) {
        $slug = $m[1];
    }
}
if (empty($slug)) {
    header('Location: ./');
    exit;
}

$esc_slug = mysqli_real_escape_string($conn, $slug);
$page_result = mysqli_query($conn, "SELECT * FROM pages WHERE slug = '$esc_slug' AND status = 1 LIMIT 1");
$page = ($page_result && mysqli_num_rows($page_result) > 0) ? mysqli_fetch_assoc($page_result) : null;
if (!$page) {
    http_response_code(404);
}

$settings = get_all_settings();
$avatar = $settings['avatar'] ?? '';
$nickname = $settings['nickname'] ?? '';
$nickname_color = $settings['nickname_color'] ?? '#5d6d9e';
$signature = $settings['signature'] ?? '';
$cover_image = $settings['cover_image'] ?? '';
$page_template = $page['template'] ?? 'custom';

$sticker_names = ['3d眼镜','EDG','LPL','beluga','不好意思','不服吗','亲亲','伞兵','倚墙笑','值得肯定','偷偷看','傻笑','再见','出家人','击剑','加班','勉强笑','危险','发红包','吃手','吃瓜','吐血','吵架','呦吼','呲牙笑','哈士奇','哈士奇失去意识','哈士奇失望','哭泣','唱歌','喜欢','嘿哈','大笑','失去信号','失望','头秃','奋斗','好奇','好的','害羞','小丑','小偷','尬笑','尴尬','应援','开心','引起不适','微笑','思考','恶心','恶魔','恶魔恐惧','惊吓','惊吓白眼','惊讶','惬意','感动','愤怒','我看好你','手机相机','打咩','打牌','托腮','扶额','抠鼻','抬眼镜','拜托','捂嘴笑','捂脸','擦汗','放鞭炮','敬礼','整理发型','斗鸡眼','智慧的眼神','月饼','有没有搞错','正确','没招','波吉','泪奔','流汗微笑','流鼻涕','深思','滑稽','滑稽吃瓜','滑稽喝水','滑稽奶茶','滑稽柠檬','滑稽狂汗','滑稽被子','烦恼','熊熊','熊猫','熊猫唱歌','熊猫喜欢','熊猫失望','熊猫意外','熬夜','爆炸','牛年进宝','狂热','狗头','狗头围脖','狗头失望','狗头意外','狗头胖次','狗头花','狗头草','猪头','猪头意外','生病','电话','疑问','疼痛','痛哭','看穿一切','眩晕','睡觉','禁言','笑哭','纠结','绿帽','缺牙笑','翻白眼','老虎意外','耍酷','胡子','菜狗','菜狗花','蒙面滑稽','虎年进宝','被打','裂开','警告','读书','财神红包','超爱','这是啥','送福','送花','错误','阴险','难以置信','面具','饥渴','鬼脸','黑线','鼓掌'];

$sticker_names_kawa = ['kawa-1','kawa-2','kawa-3','kawa-4','kawa-5','kawa-6','kawa-7','kawa-8','kawa-9','kawa-10','kawa-11','kawa-12','kawa-13','kawa-14','kawa-15','kawa-16','kawa-17','kawa-18','kawa-19','kawa-20','kawa-21','kawa-22','kawa-23','kawa-24','kawa-25','kawa-26','kawa-27','kawa-28','kawa-29','kawa-30','kawa-31','kawa-32'];

define('PAGE_COMMENT_OFFSET', 100000000);
$page_id = $page ? intval($page['id']) : 0;
$page_post_id = $page_id > 0 ? -(PAGE_COMMENT_OFFSET + $page_id) : 0;
$comments_enabled = $page ? (intval($page['enable_comments'] ?? 1) === 1) : false;

$comment_page = isset($_GET['cp']) ? max(1, intval($_GET['cp'])) : 1;
$page_comments_global = intval(get_setting('page_comments_per_page', '10'));
if ($page_comments_global < 1) $page_comments_global = 10;
$comments_per_page = isset($page['comments_per_page']) && intval($page['comments_per_page']) > 0 ? intval($page['comments_per_page']) : $page_comments_global;
$comment_offset = ($comment_page - 1) * $comments_per_page;

$page_comments = [];
$total_comments_all = 0;
$total_pages = 1;
$has_more = false;

if ($page_id > 0 && $comments_enabled) {
    $cr = mysqli_query($conn, "SELECT COUNT(*) as c FROM comments WHERE post_id = " . intval($page_post_id) . " AND status = 1 AND parent_id = 0");
    if ($cr && $row = mysqli_fetch_assoc($cr)) {
        $total_comments_all = intval($row['c']);
    }
    $total_pages = max(1, ceil($total_comments_all / $comments_per_page));
    $has_more = $comment_page < $total_pages;
    $page_comments = get_page_comment_tree_paged($conn, $page_post_id, $comment_offset, $comments_per_page);
}

$all_pages = [];
$pr = mysqli_query($conn, "SELECT * FROM pages WHERE status = 1 ORDER BY sort ASC, id ASC");
if ($pr) {
    while ($row = mysqli_fetch_assoc($pr)) {
        $all_pages[] = $row;
    }
}

if (!function_exists('parse_stickers')) {
function parse_stickers($text) {
    global $sticker_names, $sticker_names_kawa;
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
        if (in_array('kawa-' . $m[1], $sticker_names_kawa)) {
            return '<img src="assets/stickers/' . urlencode('kawa-' . $m[1]) . '.png" class="heo-sticker" alt="kawa-' . htmlspecialchars($m[1]) . '">';
        }
        return $m[0];
    }, $text);
    return $text;
}
}

function get_page_comment_tree_paged($conn, $post_id, $offset, $limit) {
    $main = [];
    $r = mysqli_query($conn, "SELECT * FROM comments WHERE post_id = " . intval($post_id) . " AND status = 1 AND parent_id = 0 ORDER BY created_at DESC LIMIT $offset, $limit");
    if ($r) {
        while ($row = mysqli_fetch_assoc($r)) {
            $main[] = $row;
        }
    }
    if (empty($main)) {
        return [];
    }
    $main_ids = array_map(function($c) { return intval($c['id']); }, $main);
    $all_comment_ids = $main_ids;
    $to_process = $main_ids;
    while (!empty($to_process)) {
        $ids_str = implode(',', $to_process);
        $to_process = [];
        $child_r = mysqli_query($conn, "SELECT id FROM comments WHERE post_id = " . intval($post_id) . " AND status = 1 AND parent_id IN ($ids_str)");
        if ($child_r) {
            while ($child_row = mysqli_fetch_assoc($child_r)) {
                $cid = intval($child_row['id']);
                if (!in_array($cid, $all_comment_ids)) {
                    $all_comment_ids[] = $cid;
                    $to_process[] = $cid;
                }
            }
        }
    }
    $all_replies = [];
    if (!empty($all_comment_ids)) {
        $all_ids_str = implode(',', $all_comment_ids);
        $rr = mysqli_query($conn, "SELECT * FROM comments WHERE post_id = " . intval($post_id) . " AND status = 1 AND id IN ($all_ids_str) ORDER BY created_at ASC");
        if ($rr) {
            while ($rp = mysqli_fetch_assoc($rr)) {
                $all_replies[] = $rp;
            }
        }
    }
    $comment_map = [];
    $child_map = [];
    foreach ($all_replies as $rp) {
        $comment_map[intval($rp['id'])] = $rp;
        $pid = intval($rp['parent_id']);
        if (!isset($child_map[$pid])) {
            $child_map[$pid] = [];
        }
        $child_map[$pid][] = $rp;
    }
    function build_reply_tree($parent_id, $child_map, $comment_map) {
        $replies = [];
        if (isset($child_map[$parent_id])) {
            foreach ($child_map[$parent_id] as $child) {
                $parent_id_of_child = intval($child['parent_id']);
                if (isset($comment_map[$parent_id_of_child])) {
                    $child['parent_avatar'] = $comment_map[$parent_id_of_child]['avatar'] ?? null;
                    $child['parent_nickname'] = $comment_map[$parent_id_of_child]['nickname'] ?? null;
                    $child['parent_website'] = $comment_map[$parent_id_of_child]['website'] ?? null;
                }
                $child['replies'] = build_reply_tree(intval($child['id']), $child_map, $comment_map);
                $replies[] = $child;
            }
        }
        return $replies;
    }
    $result = [];
    foreach ($main as $m) {
        $m['replies'] = build_reply_tree(intval($m['id']), $child_map, $comment_map);
        $result[] = $m;
    }
    return $result;
}

function render_page_reply_tree($replies, $parent_comment, $level = 0) {
    $html = '';
    foreach ($replies as $r) {
        $rAvatar = $r['avatar'] ?: 'https://cravatar.cn/avatar/00000000000000000000000000000000?s=100&d=mp';
        $html .= '<div class="pg-reply-item" data-id="' . $r['id'] . '">';
        $html .= '<div class="pg-avatar pg-avatar-sm"><img src="' . htmlspecialchars($rAvatar) . '" alt="' . htmlspecialchars($r['nickname']) . '" onerror="this.style.display=\'none\'"></div>';
        $html .= '<div class="pg-reply-bubble">';
        $html .= '<div class="pg-reply-meta">';
        if ($r['website']) {
            $html .= '<a href="' . htmlspecialchars($r['website']) . '" target="_blank" style="text-decoration:none;"><span class="pg-reply-author">' . htmlspecialchars($r['nickname']) . '</span></a>';
        } else {
            $html .= '<span class="pg-reply-author">' . htmlspecialchars($r['nickname']) . '</span>';
        }
        $html .= '<span class="pg-reply-label">回复</span>';
        $reply_to_name = isset($r['parent_nickname']) ? $r['parent_nickname'] : $parent_comment['nickname'];
        $html .= '<span class="pg-reply-to">' . htmlspecialchars($reply_to_name) . '</span>';
        $html .= '<span class="pg-reply-time">' . time_ago($r['created_at']) . '</span>';
        $html .= '</div>';
        $html .= '<div class="pg-reply-text">' . nl2br(parse_stickers(htmlspecialchars($r['content']))) . '</div>';
        $html .= '<div class="pg-reply-actions">';
        $html .= '<button class="pg-reply-btn" onclick="pgShowReply(' . $r['id'] . ', \'' . addslashes(htmlspecialchars($r['nickname'])) . '\')">回复</button>';
        $html .= '</div>';
        $html .= '</div></div>';
        $html .= '<div class="pg-reply-input-area" id="replyBox-' . $r['id'] . '" style="display:none;"></div>';
        if (!empty($r['replies'])) {
            $html .= render_page_reply_tree($r['replies'], $r, $level + 1);
        }
    }
    return $html;
}

function build_friendlink_card($name, $url, $desc) {
    $html = '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener noreferrer" class="friend-card">';
    $html .= '<div class="site-name">' . htmlspecialchars($name) . '</div>';
    if ($desc !== '') {
        $html .= '<div class="site-desc">' . htmlspecialchars($desc) . '</div>';
    }
    $html .= '</a>';
    return $html;
}

function build_friendlink_grid($cards) {
    $random_idx = [];
    $random_cards = [];
    foreach ($cards as $i => $c) {
        if (!empty($c['random'])) {
            $random_idx[] = $i;
            $random_cards[] = $c;
        }
    }
    if (count($random_idx) > 1) {
        shuffle($random_cards);
        foreach ($random_idx as $k => $idx) {
            $cards[$idx] = $random_cards[$k];
        }
    }
    $html = '<div class="friend-grid">';
    foreach ($cards as $c) {
        $html .= build_friendlink_card($c['name'], $c['url'], $c['desc']);
    }
    $html .= '</div>';
    return $html;
}

function parse_friendlink_line($line) {
    $line = trim($line);
    if (preg_match('/^(@)?=([^\s=]+)=(https?:\/\/[^\s=]+)(?:=(.*))?$/', $line, $m)) {
        return [
            'name' => trim($m[2]),
            'url'  => trim($m[3]),
            'desc' => isset($m[4]) ? trim($m[4]) : '',
            'random' => !empty($m[1])
        ];
    }
    return null;
}

function render_friendlink_page($page_html, $legacy_json) {
    $friend_grids = [];
    $content = preg_replace_callback(
        '/(?:^[ \t]*@?=[^\s=]+=https?:\/\/[^\n]*\n?)+/m',
        function($m) use (&$friend_grids) {
            $cards = [];
            foreach (explode("\n", trim($m[0])) as $line) {
                $card = parse_friendlink_line($line);
                if ($card) $cards[] = $card;
            }
            if (empty($cards)) return $m[0];
            $ph = '%%FLINKGRID' . count($friend_grids) . '%%';
            $friend_grids[$ph] = build_friendlink_grid($cards);
            return "\n\n" . $ph . "\n\n";
        },
        $page_html
    );

    $html = parse_content($content);

    if (!empty($friend_grids)) {
        foreach ($friend_grids as $ph => $grid_html) {
            $html = str_replace('<p>' . $ph . '</p>', $grid_html, $html);
            $html = str_replace($ph, $grid_html, $html);
        }
        return $html;
    }

    $legacy_cards = [];
    $decoded = json_decode($legacy_json, true);
    if (is_array($decoded)) {
        foreach ($decoded as $fl) {
            if (!is_array($fl)) continue;
            $fl_hidden = $fl['hidden'] ?? false;
            if ($fl_hidden === true || $fl_hidden === '1' || $fl_hidden === 1) continue;
            $fl_name = trim($fl['name'] ?? '');
            $fl_url  = trim($fl['url'] ?? '');
            if ($fl_name === '' || $fl_url === '') continue;
            $legacy_cards[] = ['name' => $fl_name, 'url' => $fl_url, 'desc' => trim($fl['desc'] ?? '')];
        }
    }
    $empty_tip = '<div class="friend-empty"><p>暂无友情链接</p></div>';
    $legacy_grid = !empty($legacy_cards) ? build_friendlink_grid($legacy_cards) : $empty_tip;

    if (strpos($page_html, '{{friendlinks}}') !== false) {
        $html = str_replace('<p>{{friendlinks}}</p>', '{{friendlinks}}', $html);
        return str_replace('{{friendlinks}}', $legacy_grid, $html);
    }
    if (!empty($legacy_cards)) {
        return $html . $legacy_grid;
    }
    if (trim($page_html) === '') {
        return $empty_tip;
    }
    return $html;
}

if (!function_exists('pg_comment_pagination')) {
function pg_comment_pagination($current_page, $total_pages, $slug) {
    if ($total_pages <= 1) {
        return '';
    }
    $html = '<div class="pg-pagination">';
    if ($current_page > 1) {
        $html .= '<a href="./' . urlencode($slug) . '.html?cp=' . ($current_page - 1) . '" class="pg-page-btn">上一页</a>';
    } else {
        $html .= '<button class="pg-page-btn" disabled>上一页</button>';
    }
    for ($i = 1; $i <= $total_pages; $i++) {
        if ($i == $current_page) {
            $html .= '<span class="pg-page-btn active">' . $i . '</span>';
        } else {
            $html .= '<a href="./' . urlencode($slug) . '.html?cp=' . $i . '" class="pg-page-btn">' . $i . '</a>';
        }
    }
    if ($current_page < $total_pages) {
        $html .= '<a href="./' . urlencode($slug) . '.html?cp=' . ($current_page + 1) . '" class="pg-page-btn">下一页</a>';
    } else {
        $html .= '<button class="pg-page-btn" disabled>下一页</button>';
    }
    $html .= '</div>';
    return $html;
}
}

if (!function_exists('count_all_replies')) {
function count_all_replies($replies) {
    $count = 0;
    foreach ($replies as $r) {
        $count++;
        if (!empty($r['replies'])) {
            $count += count_all_replies($r['replies']);
        }
    }
    return $count;
}
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title><?php echo $page ? htmlspecialchars($page['title']) : '页面不存在'; ?> - <?php echo htmlspecialchars($settings['site_name'] ?? '云汀记'); ?></title>
<?php if (!empty($settings['site_keywords'])): ?><meta name="keywords" content="<?php echo htmlspecialchars($settings['site_keywords']); ?>"><?php endif; ?>
<?php
$seo_desc = $settings['site_description'] ?? '';
if ($page && empty($seo_desc)) {
    $seo_desc = mb_substr(strip_tags($page['content']), 0, 150, 'UTF-8');
}
if (!empty($seo_desc)): ?><meta name="description" content="<?php echo htmlspecialchars($seo_desc); ?>"><?php endif; ?>
<meta name="generator" content="云汀记">
<?php
$favicon_url = !empty($settings['favicon']) ? $settings['favicon'] : ($settings['avatar'] ?? '');
if ($favicon_url):
?>
<link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($favicon_url); ?>">
<?php endif; ?>
<link rel="stylesheet" href="assets/css/page.css.php">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap">
<link rel="stylesheet" href="assets/fontawesome/all.min.css">
<link rel="stylesheet" href="assets/highlight/googlecode.css">
<link rel="stylesheet" href="assets/css/shortcode.css">
<script src="assets/highlight/code-highlight.js" defer></script>
<script>var _PAGE={postId:<?php echo $page_post_id; ?>,slug:'<?php echo addslashes($slug); ?>'};</script>
</head>
<body>
<div class="container">
    <div class="top-menu-btn dark" id="topMenuBtn" onclick="toggleTopMenu(event)">
        <?php if (!empty($settings['menu_icon_html'])): ?><div class="menu-custom-icon"><?php echo $settings['menu_icon_html']; ?></div><?php else: ?><div class="hamburger"><span></span><span></span><span></span></div><?php endif; ?>
    </div>
    <div class="menu-overlay" id="menuOverlay" onclick="closeTopMenu()"></div>
    <div class="top-menu-panel" id="topMenuPanel">
        <a href="./" class="top-menu-item" onclick="closeTopMenu()">首页</a>
        <div class="top-menu-divider"></div>
        <?php foreach ($all_pages as $p):
            $menu_title = $p['title'];
            if ($menu_title === '关于') $menu_title = '关于';
            if ($menu_title === '友链') $menu_title = '友链';
        ?><a href="./<?php echo urlencode($p['slug']); ?>.html" class="top-menu-item<?php echo $page && $page['slug'] === $p['slug'] ? ' active' : ''; ?>" onclick="closeTopMenu()"><?php echo htmlspecialchars($menu_title); ?></a><?php endforeach; ?>
    </div>
    <div class="cover-section">
        <img src="<?php echo htmlspecialchars($cover_image); ?>" alt="cover" class="cover-image">
        <div class="user-info"><span class="user-name"><?php echo htmlspecialchars($nickname); ?></span><img src="<?php echo htmlspecialchars($avatar); ?>" alt="avatar" class="user-avatar"></div>
        <div class="user-sign"><?php echo htmlspecialchars($signature); ?></div>
    </div>
    <div class="page-content">
        <?php if ($page && $page_template === 'friendlink'): ?>
        <div class="page-body friendlink-body">
            <?php echo render_friendlink_page($page['page_content'] ?? '', $page['content'] ?? ''); ?>
        </div>
        <?php elseif ($page): ?>
        <div class="page-body"><?php echo parse_content($page['content']); ?></div>
        <?php else: ?>
        <div class="page-not-found"><h2>内容不存在</h2><p>你访问的内容不存在或已被隐藏</p><a href="./" class="back-link">返回首页</a></div>
        <?php endif; ?>
        <?php if ($page && $comments_enabled): ?>
        <div class="pg-comments-section">
            <div style="width:60px;height:3px;background:linear-gradient(135deg,var(--primary),#a5b4fc);border-radius:2px;margin:0 auto 20px;"></div>
            <div class="pg-top-header">
                <h2>添加新评论</h2>
                <span class="pg-top-count"><?php echo $total_comments_all; ?> 条评论</span>
            </div>
            <div class="pg-comment-form" id="pgForm">
                <div class="pg-form-row-email">
                    <input type="email" class="pg-form-input" id="pgEmail" placeholder="输入邮箱" oninput="pgFetchAvatar()">
                    <div class="pg-form-avatar-preview" id="pgAvatarPreview">
                        <img src="https://cravatar.cn/avatar/00000000000000000000000000000000?s=100&d=mp" alt="" style="width:100%;height:100%;object-fit:cover;">
                    </div>
                </div>
                <div class="pg-form-row">
                    <input type="text" class="pg-form-input" id="pgName" placeholder="昵称">
                    <input type="url" class="pg-form-input" id="pgUrl" placeholder="网址（选填）">
                </div>
                <div class="pg-form-row">
                    <textarea class="pg-form-textarea" id="pgContent" placeholder="说点什么..."></textarea>
                </div>
                <div class="pg-sticker-panel" id="pgStickerPanel">
                    <div class="pg-sticker-tabs">
                        <span class="pg-sticker-tab active" data-tab="kawa" onclick="pgSwitchStickerTab('kawa')">三小只</span>
                        <span class="pg-sticker-tab" data-tab="heo" onclick="pgSwitchStickerTab('heo')">Heo</span>
                    </div>
                    <div class="pg-sticker-grid" id="pgStickerGrid"></div>
                </div>
                <div class="pg-form-footer">
                    <span class="pg-form-hint" id="pgHint">填写邮箱自动获取头像</span>
                    <div style="display:flex;gap:4px;align-items:center;">
                        <button class="pg-notify-btn" id="pgNotifyBtn" onclick="pgToggleNotify()" title="邮件提醒">
                            <svg id="pgNotifyOff" viewBox="0 0 24 24" style="display:block;"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/><line x1="3" y1="3" x2="21" y2="21" stroke="currentColor" stroke-width="1.5" fill="none"/></svg>
                            <svg id="pgNotifyOn" viewBox="0 0 24 24" style="display:none;"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                        </button>
                        <button class="pg-sticker-btn" id="pgStickerBtn" onclick="pgToggleStickerPanel()" title="表情">
                            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-3.5-9c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm7 0c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/></svg>
                        </button>
                        <button class="pg-btn-send" onclick="pgSubmit()">发送</button>
                    </div>
                </div>
            </div>

            <div class="pg-comment-list" id="pgList">
                <?php if (empty($page_comments)): ?>
                <div class="pg-empty-state">
                    <div class="pg-empty-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
                    </div>
                    <h3>还没有评论</h3>
                    <p>成为第一个评论的人吧~</p>
                </div>
                <?php else: foreach ($page_comments as $c):
                    $avatar = $c['avatar'] ?: 'https://cravatar.cn/avatar/00000000000000000000000000000000?s=100&d=mp';
                    $replyCount = count_all_replies($c['replies'] ?? []);
                ?>
                <div class="pg-comment-item" data-id="<?php echo $c['id']; ?>">
                    <div class="pg-avatar"><img src="<?php echo htmlspecialchars($avatar); ?>" alt="<?php echo htmlspecialchars($c['nickname']); ?>" onerror="this.style.display='none'"></div>
                    <div class="pg-comment-body">
                        <div class="pg-comment-meta">
                            <?php if ($c['website']): ?><a href="<?php echo htmlspecialchars($c['website']); ?>" target="_blank" style="text-decoration:none;"><span class="pg-comment-author"><?php echo htmlspecialchars($c['nickname']); ?></span></a>
                            <?php else: ?><span class="pg-comment-author"><?php echo htmlspecialchars($c['nickname']); ?></span><?php endif; ?>
                            <span class="pg-comment-time"><?php echo time_ago($c['created_at']); ?></span>
                        </div>
                        <div class="pg-comment-content"><?php echo nl2br(parse_stickers(htmlspecialchars($c['content']))); ?></div>
                        <div class="pg-comment-actions">
                            <button class="pg-reply-btn" onclick="pgShowReply(<?php echo $c['id']; ?>, '<?php echo addslashes(htmlspecialchars($c['nickname'])); ?>')">回复</button>
                        </div>
                        <div class="pg-reply-input-area" id="replyBox-<?php echo $c['id']; ?>" style="display:none;"></div>
                        <?php if ($replyCount > 0): ?>
                        <div class="pg-replies-section">
                            <button class="pg-toggle-replies" id="toggleBtn-<?php echo $c['id']; ?>" onclick="pgToggleReplies(<?php echo $c['id']; ?>)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                                <?php echo $replyCount; ?> 条回复
                            </button>
                            <div class="pg-reply-list" id="replies-<?php echo $c['id']; ?>">
                                <?php echo render_page_reply_tree($c['replies'], $c); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="pg-comment-divider"></div>
                <?php endforeach; endif; ?>
            </div>

            <?php echo pg_comment_pagination($comment_page, $total_pages, $slug); ?>
            <?php if (!$has_more && $total_comments_all > 0): ?>
            <div class="pg-page-end-hint">- - - 没有更多啦 - - -</div>
            <?php endif; ?>
        </div>
        <?php elseif ($page): ?>
        <div class="pg-comments-section">
            <div style="width:60px;height:3px;background:linear-gradient(135deg,var(--primary),#a5b4fc);border-radius:2px;margin:0 auto 20px;"></div>
            <div class="pg-empty-state">
                <div class="pg-empty-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                </div>
                <h3>评论已关闭</h3>
                <p>管理员已关闭此页面的评论功能</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="layui-toast" id="layuiToast"></div>
    </div>

<script src="assets/js/page.js.php" defer></script>
</div>
</body>
</html>
