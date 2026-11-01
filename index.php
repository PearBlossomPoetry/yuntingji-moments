<?php
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (!file_exists(__DIR__ . '/install.lock')) {
    require_once __DIR__ . '/not_installed.php';
    exit;
}
require_once 'config.php';
$settings = get_all_settings();
$avatar = $settings['avatar'] ?? '';
$nickname = $settings['nickname'] ?? '';
$nickname_color = $settings['nickname_color'] ?? '#5d6d9e';
$ad_nickname_color = $settings['ad_nickname_color'] ?? '#5d6d9e';
$signature = $settings['signature'] ?? '';
$cover_image = $settings['cover_image'] ?? '';
$page_size = intval($settings['page_size'] ?? 12);
$user_ip = get_real_ip();

$posts = [];
$r = mysqli_query($conn, "SELECT * FROM posts WHERE status = 1 ORDER BY is_top DESC, published_at DESC LIMIT $page_size");
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) {
        $row['item_type'] = 'post';
        $row['sort_time'] = $row['published_at'];
        $row['tags'] = [];
        $tr = mysqli_query($conn, "SELECT t.* FROM tags t INNER JOIN post_tags pt ON t.id = pt.tag_id WHERE pt.post_id = " . $row['id']);
        if ($tr) while ($t = mysqli_fetch_assoc($tr)) $row['tags'][] = $t;
        $row['music'] = [];
        $mr = mysqli_query($conn, "SELECT * FROM music WHERE post_id = " . $row['id'] . " ORDER BY id ASC");
        if ($mr) while ($m = mysqli_fetch_assoc($mr)) $row['music'][] = $m;
        $row['likes'] = [];
        $row['like_count'] = 0;
        $row['is_liked'] = false;
        $lr = mysqli_query($conn, "SELECT nickname, ip FROM likes WHERE post_id = " . $row['id']);
        if ($lr) {
            while ($l = mysqli_fetch_assoc($lr)) {
                $row['likes'][] = $l['nickname'] ?: '访客';
                if ($l['ip'] === $user_ip) $row['is_liked'] = true;
            }
            $row['like_count'] = mysqli_num_rows($lr);
        }
        $row['comments'] = get_comment_tree($conn, $row['id']);
        $row['images'] = json_decode($row['images'] ?? '[]', true) ?: [];
        $row['videos'] = json_decode($row['videos'] ?? '[]', true) ?: [];
        $row['video_covers'] = json_decode($row['video_covers'] ?? '[]', true) ?: [];
        $posts[] = $row;
    }
}
$ads = [];
$ar = mysqli_query($conn, "SELECT * FROM ads WHERE status = 1 ORDER BY is_top DESC, published_at DESC LIMIT $page_size");
if ($ar) {
    while ($a = mysqli_fetch_assoc($ar)) {
        $a['item_type'] = 'ad';
        $a['display_nickname_color'] = !empty($a['nickname_color']) ? $a['nickname_color'] : '#5d6d9e';
        $a['sort_time'] = $a['published_at'] ?: $a['created_at'];
        $a['likes'] = [];
        $a['like_count'] = 0;
        $a['is_liked'] = false;
        $alr = mysqli_query($conn, "SELECT nickname, ip FROM likes WHERE post_id = -" . intval($a['id']));
        if ($alr) {
            while ($l = mysqli_fetch_assoc($alr)) {
                $a['likes'][] = $l['nickname'] ?: '访客';
                if ($l['ip'] === $user_ip) $a['is_liked'] = true;
            }
            $a['like_count'] = mysqli_num_rows($alr);
        }
        $a['comments'] = get_comment_tree($conn, -intval($a['id']));
        $ads[] = $a;
    }
}

$articles = [];
$ar = mysqli_query($conn, "SELECT * FROM articles WHERE status = 1 ORDER BY is_top DESC, published_at DESC LIMIT $page_size");
if ($ar) {
    while ($row = mysqli_fetch_assoc($ar)) {
        $row['item_type'] = 'article';
        $row['sort_time'] = $row['published_at'];
        $articles[] = $row;
    }
}

$gradients = [
    'linear-gradient(135deg, #2b32b2 0%, #1488cc 100%)',
    'linear-gradient(135deg, #cb2d3e 0%, #ef473a 100%)',
    'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)',
    'linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%)',
    'linear-gradient(135deg, #8e2de2 0%, #4a00e0 100%)',
    'linear-gradient(135deg, #00b4db 0%, #0083b0 100%)',
    'linear-gradient(135deg, #f857a6 0%, #ff5858 100%)',
    'linear-gradient(135deg, #56ab2f 0%, #a8e063 100%)',
    'linear-gradient(135deg, #3a1c71 0%, #d76d77 50%, #ffaf7b 100%)',
    'linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)',
];

$all_items = array_merge($posts, $ads, $articles);
usort($all_items, function($a, $b) {
    $aTop = intval($a['is_top'] ?? 0);
    $bTop = intval($b['is_top'] ?? 0);
    if ($aTop !== $bTop) return $bTop - $aTop;
    return strtotime($b['sort_time']) - strtotime($a['sort_time']);
});
$all_items = array_slice($all_items, 0, $page_size);

$sticker_names = ['3d眼镜','EDG','LPL','beluga','不好意思','不服吗','亲亲','伞兵','倚墙笑','值得肯定','偷偷看','傻笑','再见','出家人','击剑','加班','勉强笑','危险','发红包','吃手','吃瓜','吐血','吵架','呦吼','呲牙笑','哈士奇','哈士奇失去意识','哈士奇失望','哭泣','唱歌','喜欢','嘿哈','大笑','失去信号','失望','头秃','奋斗','好奇','好的','害羞','小丑','小偷','尬笑','尴尬','应援','开心','引起不适','微笑','思考','恶心','恶魔','恶魔恐惧','惊吓','惊吓白眼','惊讶','惬意','感动','愤怒','我看好你','手机相机','打咩','打牌','托腮','扶额','抠鼻','抬眼镜','拜托','捂嘴笑','捂脸','擦汗','放鞭炮','敬礼','整理发型','斗鸡眼','智慧的眼神','月饼','有没有搞错','正确','没招','波吉','泪奔','流汗微笑','流鼻涕','深思','滑稽','滑稽吃瓜','滑稽喝水','滑稽奶茶','滑稽柠檬','滑稽狂汗','滑稽被子','烦恼','熊熊','熊猫','熊猫唱歌','熊猫喜欢','熊猫失望','熊猫意外','熬夜','爆炸','牛年进宝','狂热','狗头','狗头围脖','狗头失望','狗头意外','狗头胖次','狗头花','狗头草','猪头','猪头意外','生病','电话','疑问','疼痛','痛哭','看穿一切','眩晕','睡觉','禁言','笑哭','纠结','绿帽','缺牙笑','翻白眼','老虎意外','耍酷','胡子','菜狗','菜狗花','蒙面滑稽','虎年进宝','被打','裂开','警告','读书','财神红包','超爱','这是啥','送福','送花','错误','阴险','难以置信','面具','饥渴','鬼脸','黑线','鼓掌'];

$sticker_names_kawa = ['kawa-1','kawa-2','kawa-3','kawa-4','kawa-5','kawa-6','kawa-7','kawa-8','kawa-9','kawa-10','kawa-11','kawa-12','kawa-13','kawa-14','kawa-15','kawa-16','kawa-17','kawa-18','kawa-19','kawa-20','kawa-21','kawa-22','kawa-23','kawa-24','kawa-25','kawa-26','kawa-27','kawa-28','kawa-29','kawa-30','kawa-31','kawa-32'];

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

$total_posts = 0;
$tp = mysqli_query($conn, "SELECT COUNT(*) as c FROM posts WHERE status = 1");
$ta = mysqli_query($conn, "SELECT COUNT(*) as c FROM articles WHERE status = 1");
$td = mysqli_query($conn, "SELECT COUNT(*) as c FROM ads WHERE status = 1");
if ($tp) $total_posts += intval(mysqli_fetch_assoc($tp)['c']);
if ($ta) $total_posts += intval(mysqli_fetch_assoc($ta)['c']);
if ($td) $total_posts += intval(mysqli_fetch_assoc($td)['c']);

$all_pages = [];
$pr = mysqli_query($conn, "SELECT * FROM pages WHERE status = 1 ORDER BY sort ASC, id ASC");
if ($pr) {
    while ($row = mysqli_fetch_assoc($pr)) {
        $all_pages[] = $row;
    }
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title><?php echo htmlspecialchars($settings['site_name'] ?? '云汀记'); ?></title>
<?php if (!empty($settings['site_keywords'])): ?><meta name="keywords" content="<?php echo htmlspecialchars($settings['site_keywords']); ?>"><?php endif; ?>
<?php if (!empty($settings['site_description'])): ?><meta name="description" content="<?php echo htmlspecialchars($settings['site_description']); ?>"><?php endif; ?>
<meta name="generator" content="云汀记">
<?php
$favicon_url = !empty($settings['favicon']) ? $settings['favicon'] : ($settings['avatar'] ?? '');
if ($favicon_url):
?>
<link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($favicon_url); ?>">
<?php endif; ?>
    <link rel="stylesheet" href="assets/css/index.css.php">
    <link rel="stylesheet" href="assets/css/shortcode.css">
    <link rel="stylesheet" href="assets/fontawesome/all.min.css">
    <link rel="stylesheet" href="assets/highlight/googlecode.css">
    <link rel="alternate" type="application/rss+xml" title="<?php echo htmlspecialchars($settings['site_name'] ?? '云汀记'); ?>" href="./feed" />
<script src="assets/highlight/code-highlight.js" defer></script>
<script>var _INIT={hasMore:<?php echo ($total_posts>$page_size)?'true':'false'; ?>,commentLimit:<?php echo intval($settings['comment_display_limit'] ?? 5); ?>};</script>
</head>
<body>
<div class="container">
    <div class="top-menu-btn dark" id="topMenuBtn" onclick="toggleTopMenu(event)">
        <?php if (!empty($settings['menu_icon_html'])): ?>
            <div class="menu-custom-icon"><?php echo $settings['menu_icon_html']; ?></div>
        <?php else: ?>
            <div class="hamburger"><span></span><span></span><span></span></div>
        <?php endif; ?>
    </div>
    <div class="menu-overlay" id="menuOverlay" onclick="closeTopMenu()"></div>
    <div class="top-menu-panel" id="topMenuPanel">
        <a href="./" class="top-menu-item active" onclick="closeTopMenu()">首页</a>
        <div class="top-menu-divider"></div>
        <?php foreach ($all_pages as $p):
            $menu_title = $p['title'];
            if ($menu_title === '关于') $menu_title = '关于';
            if ($menu_title === '友链') $menu_title = '友链';
        ?>
        <a href="./<?php echo urlencode($p['slug']); ?>.html" class="top-menu-item" onclick="closeTopMenu()"><?php echo htmlspecialchars($menu_title); ?></a>
        <?php endforeach; ?>
    </div>
    <div class="cover-section">
        <img src="<?php echo htmlspecialchars($cover_image); ?>" alt="cover" class="cover-image">
        <div class="user-info"><span class="user-name"><?php echo htmlspecialchars($nickname); ?></span><img src="<?php echo htmlspecialchars($avatar); ?>" alt="avatar" class="user-avatar"></div>
        <div class="user-sign"><?php echo htmlspecialchars($signature); ?></div>
    </div>
    <div class="moments-content" id="momentsList">
        <?php foreach ($all_items as $item): ?>
        <?php if ($item['item_type'] === 'ad'): ?>
        <div class="ad-item" data-id="<?php echo $item['id']; ?>" data-type="ad">
            <div class="ad-header">
                <?php if ($item['avatar']): ?><img src="<?php echo htmlspecialchars($item['avatar']); ?>" class="ad-avatar-img" alt=""><?php else: ?><div class="ad-avatar-default">广告</div><?php endif; ?>
                <div class="ad-info"><div class="ad-title" style="color:<?php echo htmlspecialchars($item['display_nickname_color']); ?>;"><?php echo htmlspecialchars($item['title']); ?><?php if ($item['is_top']): ?> <span class="top-tag">置顶</span><?php endif; ?><span class="ad-tag" style="margin-left:auto;">广告</span></div><div class="ad-subtitle"><?php echo htmlspecialchars($item['subtitle']); ?></div></div>
            </div>
            <?php if ($item['content']): ?><div class="ad-text"><?php echo $item['content']; ?></div><?php endif; ?>
            <?php if ($item['style'] == 1): ?><div class="ad-card" data-link="<?php echo htmlspecialchars($item['link'] ?: '#'); ?>" onclick="openAdLink(this)"><div class="ad-card-image"><?php if ($item['image']): ?><img src="<?php echo htmlspecialchars($item['image']); ?>" alt="" onclick="event.stopPropagation()"><?php else: ?>广告<?php endif; ?></div><div class="ad-card-content"><div class="ad-card-title"><?php echo htmlspecialchars($item['card_title'] ?: ''); ?></div><div class="ad-card-desc"><?php echo htmlspecialchars($item['card_desc'] ?: ''); ?></div></div></div>
            <?php else: ?><div class="ad-image-card" data-link="<?php echo htmlspecialchars($item['link'] ?: '#'); ?>" onclick="openAdLink(this)"><img src="<?php echo htmlspecialchars($item['image'] ?: ''); ?>" class="ad-image-main" alt="" onclick="event.stopPropagation()"><div class="ad-image-footer"><div class="ad-brand"><?php if ($item['brand_logo']): ?><img src="<?php echo htmlspecialchars($item['brand_logo']); ?>" class="ad-brand-logo" alt="" onclick="event.stopPropagation()"><?php else: ?><div class="ad-brand-logo" style="display:flex;align-items:center;justify-content:center;font-size:9px;color:#999;">广告</div><?php endif; ?><span class="ad-brand-name"><?php echo htmlspecialchars($item['brand_name'] ?: ''); ?></span></div><a href="<?php echo htmlspecialchars($item['link'] ?: '#'); ?>" class="ad-btn" target="_blank" style="background:<?php echo $item['button_color']; ?>;text-decoration:none;" onclick="event.stopPropagation()"><?php echo htmlspecialchars($item['button_text']); ?></a></div></div><?php endif; ?>
            <div class="ad-actions"><span class="moment-time"><?php echo time_ago($item['sort_time']); ?></span><div class="menu-btn-wrap"><div class="action-bar" id="action-bar-ad-<?php echo $item['id']; ?>"><?php if (($item['like_enabled'] ?? 1)): ?><div class="action-bar-item" onclick="doLike(-<?php echo $item['id']; ?>, this)"><svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="<?php echo $item['is_liked'] ? '0 0 24 24' : '0 0 32 32'; ?>" style="width:13px;height:13px;fill:currentColor;display:block;align-self:center;"><path fill="currentColor" d="<?php echo $item['is_liked'] ? 'M12 18c0 1 .25 1.92.67 2.74l-.67.61l-1.45-1.32C5.4 15.36 2 12.27 2 8.5C2 5.41 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.08C13.09 3.81 14.76 3 16.5 3C19.58 3 22 5.41 22 8.5c0 1.43-.5 2.76-1.38 4.11c-.79-.38-1.68-.61-2.62-.61c-3.31 0-6 2.69-6 6m9.54-2.12l-1.42-1.41L18 16.59l-2.12-2.12l-1.41 1.41L16.59 18l-2.12 2.12l1.41 1.42L18 19.41l2.12 2.13l1.42-1.42L19.41 18z' : 'M15.53 7.122c-2.979-4.166-9.174-4.162-12.146.009a7.46 7.46 0 0 0 .609 9.409l11.275 12.14a1 1 0 0 0 1.464.001L28 16.583a7.48 7.48 0 0 0 .584-9.485c-3.01-4.155-9.216-4.114-12.171.081l-.417.593z'; ?>"></path></svg><span class="like-text"><?php echo $item['is_liked'] ? '取消' : '赞'; ?></span></div><?php endif; ?><?php if (($item['comment_enabled'] ?? 1)): ?><div class="action-bar-item" onclick="openCommentModal(-<?php echo $item['id']; ?>)"><svg viewBox="0 0 24 24" style="width:13px;height:13px;fill:currentColor;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>评论</div><?php endif; ?></div><div class="menu-btn" data-toggle="ad-<?php echo $item['id']; ?>"></div></div></div>
            <?php if (!empty($item['likes']) || !empty($item['comments']) || !($item['like_enabled'] ?? 1) || !($item['comment_enabled'] ?? 1)): ?>
            <div class="interaction-area" id="interact-ad-<?php echo $item['id']; ?>">
                <?php if (!empty($item['likes'])): ?><div class="likes"><svg class="like-icon" viewBox="0 0 32 32" style="width:13px;height:13px;display:block;align-self:center;color:<?php echo htmlspecialchars($nickname_color); ?>"><path fill="currentColor" d="M15.53 7.122c-2.979-4.166-9.174-4.162-12.146.009a7.46 7.46 0 0 0 .609 9.409l11.275 12.14a1 1 0 0 0 1.464.001L28 16.583a7.48 7.48 0 0 0 .584-9.485c-3.01-4.155-9.216-4.114-12.171.081l-.417.593z"/></svg><span class="like-names"><?php $display_ad_likes = array_slice($item['likes'], 0, 5); echo implode('、', $display_ad_likes); ?><?php if ($item['like_count'] > 5): ?>、访客+<?php echo ($item['like_count'] - 5); ?><?php endif; ?></span></div><?php endif; ?>
                <?php if (!empty($item['comments'])): ?><div class="comments" id="comments-ad-<?php echo $item['id']; ?>"><?php echo render_comment_tree($item['comments'], -$item['id']); ?></div><?php endif; ?>
                <?php if (!($item['like_enabled'] ?? 1) || !($item['comment_enabled'] ?? 1)): ?>
                <div class="interaction-disabled">
                    <?php if (!($item['like_enabled'] ?? 1) && !($item['comment_enabled'] ?? 1)): ?>已关闭点赞与评论<?php elseif (!($item['like_enabled'] ?? 1)): ?>已关闭点赞<?php elseif (!($item['comment_enabled'] ?? 1)): ?>已关闭评论<?php endif; ?>
                </div>
                <?php endif; ?>
            </div><?php endif; ?>
        </div>
        <?php elseif ($item['item_type'] === 'article'): ?>
        <?php
        $bg_mode = intval($item['bg_mode'] ?? 0);
        $bg_color = $item['bg_color'] ?? '';
        $cover = $item['cover_image'] ?? '';
        $title = $item['title'] ?? '';
        $slug = $item['slug'] ?? '';
        $published = $item['published_at'] ?? '';
        $date_str = $published ? date('Y年m月d日', strtotime($published)) : '';

        $card_colors = [
            '#5a7a9a', '#6a7a9a', '#5a8a8a', '#7a6a9a',
            '#8a7a6a', '#6a8a7a', '#6a6a8a', '#7a7a6a',
            '#8a6a7a', '#6a8a6a',
        ];
        $ci = hexdec(substr(md5($slug), 0, 8)) % count($card_colors);
        $is_magazine = ($bg_mode === 1);
        $card_bg = ($bg_color !== '') ? $bg_color : '#000000';

        $summary_raw = trim($item['summary'] ?? '');
        if ($summary_raw === '') {
            $plain = strip_tags($item['content'] ?? '');
            $plain = str_replace(["\n", "\r", ' ', '&nbsp;'], '', $plain);
            $summary_raw = mb_substr($plain, 0, 40);
            if (mb_strlen($plain) > 40) $summary_raw .= '...';
        }

        $magazine_style = '';
        if ($is_magazine) {
            if ($cover) {
                $magazine_style = 'background-image:url(' . htmlspecialchars($cover) . ');background-size:cover;background-position:center;';
            } elseif ($bg_color !== '') {
                $magazine_style = 'background:' . htmlspecialchars($bg_color) . ';';
            } else {
                $magazine_style = 'background:' . htmlspecialchars($card_bg) . ';';
            }
        }
        ?>
        <div class="moment-item article-moment" data-id="<?php echo $item['id']; ?>" data-type="article">
            <img src="<?php echo htmlspecialchars($avatar); ?>" alt="avatar" class="moment-avatar">
            <div class="moment-body">
                <div class="name-row">
                    <span class="moment-nickname" style="color:<?php echo htmlspecialchars($nickname_color); ?>"><?php echo htmlspecialchars($nickname); ?></span>
                    <?php if ($item['is_top']): ?><span class="top-tag">置顶</span><?php endif; ?>
                </div>
                <div class="article-new-card <?php echo $is_magazine ? 'card-magazine' : 'card-minimal'; ?>" style="<?php echo $is_magazine ? $magazine_style : ''; ?>" onclick="location.href='<?php echo urlencode($slug); ?>.html'">
                    <div class="article-card-body">
                        <div class="article-card-title"><?php echo htmlspecialchars($title); ?></div>
                        <?php if (!$is_magazine): ?><div class="article-card-summary"><?php echo htmlspecialchars($summary_raw); ?></div><?php endif; ?>
                        <div class="article-card-date"><?php echo $date_str; ?></div>
                    </div>
                </div>
                <div class="moment-footer">
                    <span class="moment-time"><?php echo time_ago($item['published_at']); ?></span>
                    <a href="<?php echo urlencode($slug); ?>.html" class="read-full-btn">→ 查看全文</a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="moment-item" data-id="<?php echo $item['id']; ?>" data-type="post">
            <img src="<?php echo htmlspecialchars($item['avatar'] ?: $avatar); ?>" alt="avatar" class="moment-avatar">
            <div class="moment-body">
                <div class="name-row">
                    <span class="moment-nickname" style="color:<?php echo htmlspecialchars($item['nickname_color'] ?: $nickname_color); ?>"><?php echo htmlspecialchars($item['nickname'] ?: $nickname); ?></span>
                    <div class="tag-group">
                        <?php foreach ($item['tags'] as $tag): ?><span class="tag" style="background:<?php echo $tag['bg_color']; ?>;color:<?php echo $tag['color']; ?>"><?php echo htmlspecialchars($tag['name']); ?></span><?php endforeach; ?>
                        <?php if ($item['is_top']): ?><span class="top-tag">置顶</span><?php endif; ?>
                    </div>
                </div>
                <?php
                $parsed = $item['content'] ? parse_shortcodes($item['content'], $item) : ['html' => '', 'has_images' => false, 'has_music' => false, 'has_videos' => false];
                ?>
                <?php if ($parsed['html']): ?><div class="moment-text"><?php echo parse_content($parsed['html']); ?></div><?php endif; ?>
                <?php
                $rest_imgs = [];
                if (!empty($item['images'])) {
                    $used_idx = $parsed['used_image_indices'] ?? [];
                    foreach ($item['images'] as $k => $img) {
                        if (!in_array($k, $used_idx)) $rest_imgs[] = $img;
                    }
                }
                if (!empty($rest_imgs) && empty($parsed['has_all_images'])):
                ?>
                <div class="moment-images grid-<?php echo min(count($rest_imgs), 9); ?>"><?php foreach ($rest_imgs as $img): ?><img src="<?php echo htmlspecialchars($img); ?>" alt="" onclick='openViewer(<?php echo json_encode($img, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_APOS); ?>, <?php echo json_encode($rest_imgs, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_APOS); ?>)'><?php endforeach; ?></div><?php endif; ?>
                <?php if (!empty($item['videos']) && !$parsed['has_videos']): ?><div class="video-grid grid-<?php echo min(count($item['videos']), 2); ?>"><?php $vi=0; foreach ($item['videos'] as $video): $vcover = $item['video_covers'][$vi] ?? ''; $vi++; ?><div class="moment-video grid-<?php echo min(count($item['videos']), 2); ?>" onclick='playVideo(<?php echo json_encode($video, JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_APOS); ?>, <?php echo json_encode($item['videos'], JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_APOS); ?>, <?php echo $vi-1; ?>)'><video src="<?php echo htmlspecialchars($video); ?>" poster="<?php echo htmlspecialchars($vcover); ?>" preload="metadata" style="width:100%;height:100%;object-fit:cover;background:#000;" muted playsinline onloadedmetadata="this.parentElement.querySelector('.video-duration').textContent=formatTime(this.duration)"></video><div class="video-play-btn"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div><span class="video-duration">00:00</span></div><?php endforeach; ?></div><?php endif; ?>
                <?php if (!empty($item['music']) && !$parsed['has_music']): foreach ($item['music'] as $mi => $m): ?><div class="music-wrap"><div class="mc" id="mc-<?php echo $item['id']; ?>-<?php echo $mi; ?>" data-murl="<?php echo htmlspecialchars($m['url'] ?? ''); ?>" data-mid="<?php echo $item['id']; ?>-<?php echo $mi; ?>" data-mname="<?php echo htmlspecialchars($m['name'] ?? ''); ?>" data-martist="<?php echo htmlspecialchars($m['artist'] ?? ''); ?>" data-mcover="<?php echo htmlspecialchars($m['cover'] ?? ''); ?>" onclick="playMcCard(this)"><div class="mcv"><img src="<?php echo htmlspecialchars($m['cover'] ?? ''); ?>" alt="cover" crossorigin="anonymous"></div><div class="mi"><div class="mn"><?php echo htmlspecialchars($m['name'] ?? ''); ?></div><div class="ma"><?php echo htmlspecialchars($m['artist'] ?? ''); ?></div></div><div class="mp" id="mp-<?php echo $item['id']; ?>-<?php echo $mi; ?>"></div></div></div><?php endforeach; endif; ?>
                <div class="moment-meta<?php echo empty($item['location_name']) ? '-only' : ''; ?>"><div class="moment-time"><?php echo time_ago($item['published_at']); ?><?php if ($item['location_name']): ?><span class="moment-location"><svg class="location-icon" viewBox="0 0 12 12"><path d="M6 0C3.8 0 2 1.8 2 4c0 3 4 8 4 8s4-5 4-8c0-2.2-1.8-4-4-4zm0 5.5c-.8 0-1.5-.7-1.5-1.5S5.2 2.5 6 2.5s1.5.7 1.5 1.5S6.8 5.5 6 5.5z"/></svg><?php echo htmlspecialchars($item['location_name']); ?></span><?php endif; ?></div><div class="menu-btn-wrap"><div class="action-bar" id="action-bar-<?php echo $item['id']; ?>"><?php if (($item['like_enabled'] ?? 1)): ?><div class="action-bar-item" onclick="doLike(<?php echo $item['id']; ?>, this)"><svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="<?php echo $item['is_liked'] ? '0 0 24 24' : '0 0 32 32'; ?>" style="width:13px;height:13px;fill:currentColor;display:block;align-self:center;"><path fill="currentColor" d="<?php echo $item['is_liked'] ? 'M12 18c0 1 .25 1.92.67 2.74l-.67.61l-1.45-1.32C5.4 15.36 2 12.27 2 8.5C2 5.41 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.08C13.09 3.81 14.76 3 16.5 3C19.58 3 22 5.41 22 8.5c0 1.43-.5 2.76-1.38 4.11c-.79-.38-1.68-.61-2.62-.61c-3.31 0-6 2.69-6 6m9.54-2.12l-1.42-1.41L18 16.59l-2.12-2.12l-1.41 1.41L16.59 18l-2.12 2.12l1.41 1.42L18 19.41l2.12 2.13l1.42-1.42L19.41 18z' : 'M15.53 7.122c-2.979-4.166-9.174-4.162-12.146.009a7.46 7.46 0 0 0 .609 9.409l11.275 12.14a1 1 0 0 0 1.464.001L28 16.583a7.48 7.48 0 0 0 .584-9.485c-3.01-4.155-9.216-4.114-12.171.081l-.417.593z'; ?>"></path></svg><span class="like-text"><?php echo $item['is_liked'] ? '取消' : '赞'; ?></span></div><?php endif; ?><?php if (($item['comment_enabled'] ?? 1)): ?><div class="action-bar-item" onclick="openCommentModal(<?php echo $item['id']; ?>)"><svg viewBox="0 0 24 24" style="width:13px;height:13px;fill:currentColor;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>评论</div><?php endif; ?></div><div class="menu-btn" data-toggle="<?php echo $item['id']; ?>"></div></div></div>
                <?php if (!empty($item['likes']) || !empty($item['comments']) || !($item['like_enabled'] ?? 1) || !($item['comment_enabled'] ?? 1)): ?>
                <div class="interaction-area" id="interact-<?php echo $item['id']; ?>">
                    <?php if (!empty($item['likes'])): ?><div class="likes"><svg class="like-icon" viewBox="0 0 32 32" style="width:13px;height:13px;display:block;align-self:center;color:<?php echo htmlspecialchars($nickname_color); ?>"><path fill="currentColor" d="M15.53 7.122c-2.979-4.166-9.174-4.162-12.146.009a7.46 7.46 0 0 0 .609 9.409l11.275 12.14a1 1 0 0 0 1.464.001L28 16.583a7.48 7.48 0 0 0 .584-9.485c-3.01-4.155-9.216-4.114-12.171.081l-.417.593z"/></svg><span class="like-names" id="likes-<?php echo $item['id']; ?>"><?php
                        $display_likes = array_slice($item['likes'], 0, 5);
                        $like_str = implode('、', $display_likes);
                        if ($item['like_count'] > 5) {
                            $like_str .= '、访客+' . ($item['like_count'] - 5);
                        }
                        echo $like_str;
                    ?></span></div><?php endif; ?>
                    <?php if (!empty($item['comments'])): ?><div class="comments" id="comments-<?php echo $item['id']; ?>"><?php echo render_comment_tree($item['comments'], $item['id']); ?></div><?php endif; ?>
                    <?php if (!($item['like_enabled'] ?? 1) || !($item['comment_enabled'] ?? 1)): ?>
                    <div class="interaction-disabled">
                        <?php if (!($item['like_enabled'] ?? 1) && !($item['comment_enabled'] ?? 1)): ?>已关闭点赞与评论<?php elseif (!($item['like_enabled'] ?? 1)): ?>已关闭点赞<?php elseif (!($item['comment_enabled'] ?? 1)): ?>已关闭评论<?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    <div id="loadMoreSection"><?php if ($total_posts > $page_size): ?><div class="load-more"><span class="load-more-line"><button class="load-more-btn" id="loadMoreBtn" onclick="loadMore()"><?php echo htmlspecialchars($settings['load_more_text'] ?? '加载更多'); ?></button></span></div><?php else: ?><div class="load-more"><span class="end-tip"><?php echo htmlspecialchars($settings['end_tip_text'] ?? '没有更多啦'); ?></span></div><?php endif; ?></div>
    </div>
</div>
<div class="comment-modal-overlay" id="commentOverlay" onclick="closeCommentModal()"></div>
<div class="comment-modal" id="commentModal">
    <div class="comment-modal-header">
        <span class="comment-modal-title" id="modalTitle">发表评论</span>
        <span class="comment-modal-close" onclick="closeCommentModal()">&times;</span>
    </div>
    <div class="reply-hint" id="replyHint"><span>回复 <span id="replyToName"></span></span></div>
    <div class="comment-modal-form">
        <div class="comment-modal-row" style="position:relative;align-items:center;"><input type="email" class="comment-modal-input" id="modalEmail" placeholder="输入邮箱" oninput="modalFetchAvatar()" style="padding-right:52px;"><img src="https://cravatar.cn/avatar/00000000000000000000000000000000?s=100&d=mp" id="modalPreviewAvatar" style="width:32px;height:32px;border-radius:50%;object-fit:cover;background:#f0f0f0;position:absolute;right:8px;top:50%;transform:translateY(-50%);opacity:1;transition:opacity .3s;flex-shrink:0;border:1px solid rgba(0,0,0,.06);"></div>
        <div class="comment-modal-row"><input type="text" class="comment-modal-input" id="modalNick" placeholder="昵称"><input type="text" class="comment-modal-input" id="modalSite" placeholder="网址（选填）"></div>
        <textarea class="comment-modal-textarea" id="modalContent" placeholder="说点什么..."></textarea>
        <div class="comment-modal-footer">
            <span class="comment-modal-tip">填写邮箱自动获取头像</span>
            <div style="display:flex;align-items:center;gap:6px;">
                <button class="notify-btn" id="notifyBtn" onclick="toggleCommentNotify()" title="邮件提醒" type="button">
                    <svg id="notifyIconOff" viewBox="0 0 24 24" style="display:block;"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/><line x1="3" y1="3" x2="21" y2="21" stroke="currentColor" stroke-width="1.5" fill="none"/></svg>
                    <svg id="notifyIconOn" viewBox="0 0 24 24" style="display:none;"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                </button>
                <button class="sticker-btn" id="stickerBtn" onclick="toggleStickerPanel()" title="表情" type="button">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-3.5-9c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm7 0c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/></svg>
                </button>
                <button class="comment-modal-btn" onclick="submitModalComment()">发送</button>
            </div>
        </div>
    </div>
</div>
<div class="sticker-panel" id="stickerPanel">
    <div class="sticker-panel-header">
        <span class="sticker-panel-title">表情</span>
        <span class="sticker-panel-close" onclick="toggleStickerPanel()">&times;</span>
    </div>
    <div class="sticker-tabs" id="stickerTabs">
        <span class="sticker-tab active" data-tab="kawa" onclick="switchStickerTab('kawa')">三小只</span>
        <span class="sticker-tab" data-tab="heo" onclick="switchStickerTab('heo')">Heo</span>
    </div>
    <div class="sticker-grid" id="stickerGrid"></div>
</div>

<div class="image-viewer" id="imageViewer" onclick="closeViewer(event)"><img class="viewer-img" id="viewerImg" src="" alt=""><iframe style="display:none;" id="viewerIframe" sandbox="allow-scripts"></iframe><div class="viewer-bottom-bar"><button class="viewer-btn" id="viewerPrev" onclick="viewerPrev(event)">&#8249;</button><span class="viewer-counter" id="viewerCounter">1 / 1</span><button class="viewer-btn" id="viewerNext" onclick="viewerNext(event)">&#8250;</button><button class="viewer-btn viewer-close" id="viewerClose" onclick="closeViewerForce()"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div></div>
<div class="video-player-overlay" id="videoPlayer" onclick="closeVideo(event)"><video id="videoEl" src="" controls playsinline></video><div class="video-player-bar"><button class="vp-btn" id="videoPrev" onclick="videoPrev(event)">&#8249;</button><span class="vp-counter" id="videoCounter">1 / 1</span><button class="vp-btn" id="videoNext" onclick="videoNext(event)">&#8250;</button><button class="vp-btn" onclick="closeVideoForce()">&#10005;</button></div></div>
<audio id="globalAudio" style="display:none;"></audio>

<div class="layui-toast" id="layuiToast"></div>
</div>
<script src="assets/js/index.js.php" defer></script>
</body>
</html>
