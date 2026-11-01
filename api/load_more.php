<?php
require_once '../config.php';
if (!$conn) { json_response(0, '系统未安装'); }
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$page = intval($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$settings = get_all_settings();
$page_size = intval($settings['page_size'] ?? 12);
$offset = ($page - 1) * $page_size;
$user_ip = get_real_ip();

$avatar = $settings['avatar'] ?? '';
$nickname = $settings['nickname'] ?? '';
$nickname_color = $settings['nickname_color'] ?? '#5d6d9e';
$ad_nickname_color = $settings['ad_nickname_color'] ?? '#5d6d9e';

$posts = [];
$sql = "SELECT * FROM posts WHERE status = 1 ORDER BY is_top DESC, published_at DESC LIMIT 1000";
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $row['item_type'] = 'post';
        $row['sort_time'] = $row['published_at'];

        $row['tags'] = [];
        $tag_result = mysqli_query($conn, "SELECT t.* FROM tags t INNER JOIN post_tags pt ON t.id = pt.tag_id WHERE pt.post_id = " . $row['id']);
        if ($tag_result) {
            while ($tag = mysqli_fetch_assoc($tag_result)) {
                $row['tags'][] = $tag;
            }
        }

        $row['music'] = [];
        $music_result = mysqli_query($conn, "SELECT * FROM music WHERE post_id = " . $row['id'] . " ORDER BY id ASC");
        if ($music_result) {
            while ($m = mysqli_fetch_assoc($music_result)) {
                $row['music'][] = $m;
            }
        }

        $row['likes'] = [];
        $row['like_count'] = 0;
        $row['is_liked'] = false;
        $like_result = mysqli_query($conn, "SELECT nickname, ip FROM likes WHERE post_id = " . $row['id']);
        if ($like_result) {
            while ($like = mysqli_fetch_assoc($like_result)) {
                $row['likes'][] = $like['nickname'] ?: '访客';
                if ($like['ip'] === $user_ip) $row['is_liked'] = true;
            }
            $row['like_count'] = mysqli_num_rows($like_result);
        }

        $row['comments'] = get_comment_tree($conn, $row['id']);

        $row['images'] = json_decode($row['images'] ?? '[]', true) ?: [];
        $row['videos'] = json_decode($row['videos'] ?? '[]', true) ?: [];
        $row['video_covers'] = json_decode($row['video_covers'] ?? '[]', true) ?: [];
        $row['published_ago'] = time_ago($row['published_at']);

        $row['like_enabled'] = isset($row['like_enabled']) ? intval($row['like_enabled']) : 1;
        $row['comment_enabled'] = isset($row['comment_enabled']) ? intval($row['comment_enabled']) : 1;

        $posts[] = $row;
    }
}

$articles = [];
$ar_result = mysqli_query($conn, "SELECT * FROM articles WHERE status = 1 ORDER BY published_at DESC LIMIT 1000");
if ($ar_result) {
    while ($ar = mysqli_fetch_assoc($ar_result)) {
        $ar['item_type'] = 'article';
        $ar['sort_time'] = $ar['published_at'];
        $ar['is_top'] = 0;
        $ar['published_ago'] = time_ago($ar['published_at']);
        $articles[] = $ar;
    }
}

$ads = [];
$ad_result = mysqli_query($conn, "SELECT * FROM ads WHERE status = 1 ORDER BY sort ASC, created_at DESC LIMIT 1000");
if ($ad_result) {
    while ($ad = mysqli_fetch_assoc($ad_result)) {
        $ad['item_type'] = 'ad';
        $ad['sort_time'] = $ad['created_at'];
        $ad['display_nickname_color'] = !empty($ad['nickname_color']) ? $ad['nickname_color'] : '#5d6d9e';
        $ad['is_top'] = 0;

        $ad['likes'] = [];
        $ad['like_count'] = 0;
        $ad['is_liked'] = false;
        $alr = mysqli_query($conn, "SELECT nickname, ip FROM likes WHERE post_id = -" . intval($ad['id']));
        if ($alr) {
            while ($l = mysqli_fetch_assoc($alr)) {
                $ad['likes'][] = $l['nickname'] ?: '访客';
                if ($l['ip'] === $user_ip) $ad['is_liked'] = true;
            }
            $ad['like_count'] = mysqli_num_rows($alr);
        }

        $ad['comments'] = get_comment_tree($conn, -intval($ad['id']));
        $ad['created_ago'] = time_ago($ad['created_at']);
        $ad['like_enabled'] = isset($ad['like_enabled']) ? intval($ad['like_enabled']) : 1;
        $ad['comment_enabled'] = isset($ad['comment_enabled']) ? intval($ad['comment_enabled']) : 1;

        $ads[] = $ad;
    }
}

$all_items = array_merge($posts, $articles, $ads);
usort($all_items, function($a, $b) {
    $aTop = intval($a['is_top'] ?? 0);
    $bTop = intval($b['is_top'] ?? 0);
    if ($aTop !== $bTop) return $bTop - $aTop;
    return strtotime($b['sort_time']) - strtotime($a['sort_time']);
});

$all_items = array_slice($all_items, $offset, $page_size);

$total_posts = 0;
$tr = mysqli_query($conn, "SELECT COUNT(*) as c FROM posts WHERE status = 1");
if ($tr) $total_posts = intval(mysqli_fetch_assoc($tr)['c']);
$total_articles = 0;
$tar = mysqli_query($conn, "SELECT COUNT(*) as c FROM articles WHERE status = 1");
if ($tar) $total_articles = intval(mysqli_fetch_assoc($tar)['c']);
$total_ads = 0;
$tad = mysqli_query($conn, "SELECT COUNT(*) as c FROM ads WHERE status = 1");
if ($tad) $total_ads = intval(mysqli_fetch_assoc($tad)['c']);
$total_all = $total_posts + $total_articles + $total_ads;

$rendered_items = [];
foreach ($all_items as $item) {
    if ($item['item_type'] === 'ad') {
        $html = render_ad_html($item, $settings, $ad_nickname_color, $user_ip);
    } elseif ($item['item_type'] === 'article') {
        $html = render_article_html($item, $settings, $avatar, $nickname, $nickname_color);
    } else {
        $html = render_post_html($item, $settings, $avatar, $nickname, $nickname_color, $user_ip);
    }
    $rendered_items[] = [
        'html' => $html,
        'type' => $item['item_type'],
        'id' => $item['id']
    ];
}

json_response(1, 'ok', [
    'items' => $rendered_items,
    'total_posts' => $total_all,
    'has_more' => ($offset + $page_size) < $total_all
]);

function render_post_html($item, $settings, $avatar, $nickname, $nickname_color, $user_ip) {
    $item_avatar = htmlspecialchars($item['avatar'] ?: $avatar);
    $item_nickname = htmlspecialchars($item['nickname'] ?: $nickname);
    $item_nickname_color = htmlspecialchars($item['nickname_color'] ?: $nickname_color);
    $item_id = $item['id'];

    $html = '<div class="moment-item" data-id="' . $item_id . '" data-type="post">';
    $html .= '<img src="' . $item_avatar . '" alt="avatar" class="moment-avatar">';
    $html .= '<div class="moment-body">';

    $html .= '<div class="name-row">';
    $html .= '<span class="moment-nickname" style="color:' . $item_nickname_color . ';">' . $item_nickname . '</span>';
    $html .= '<div class="tag-group">';
    foreach ($item['tags'] as $tag) {
        $html .= '<span class="tag" style="background:' . $tag['bg_color'] . ';color:' . $tag['color'] . ';">' . htmlspecialchars($tag['name']) . '</span>';
    }
    if ($item['is_top']) {
        $html .= '<span class="top-tag">置顶</span>';
    }
    $html .= '</div></div>';

    $parsed = $item['content'] ? parse_shortcodes($item['content'], $item) : ['html' => '', 'has_images' => false, 'has_music' => false, 'has_videos' => false];
    if ($parsed['html']) {
        $html .= '<div class="moment-text">' . $parsed['html'] . '</div>';
    }

    $rest_imgs = [];
    if (!empty($item['images'])) {
        $used_idx = $parsed['used_image_indices'] ?? [];
        foreach ($item['images'] as $k => $img) {
            if (!in_array($k, $used_idx)) $rest_imgs[] = $img;
        }
    }
    if (!empty($rest_imgs) && empty($parsed['has_all_images'])) {
        $grid_class = 'grid-' . min(count($rest_imgs), 9);
        $html .= '<div class="moment-images ' . $grid_class . '">';
        foreach ($rest_imgs as $img) {
            $html .= '<img src="' . htmlspecialchars($img) . '" alt="" onclick=\'openViewer(' . json_encode($img, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_APOS) . ', ' . json_encode($rest_imgs, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_APOS) . ')\'>';
        }
        $html .= '</div>';
    }

    if (!empty($item['videos']) && !$parsed['has_videos']) {
        $grid_class = 'grid-' . min(count($item['videos']), 2);
        $html .= '<div class="video-grid ' . $grid_class . '">';
        $vi = 0;
        foreach ($item['videos'] as $video) {
            $vcover = $item['video_covers'][$vi] ?? '';
            $vi++;
            $html .= '<div class="moment-video ' . $grid_class . '" onclick=\'playVideo(' . json_encode($video, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_APOS) . ', ' . json_encode($item['videos'], JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_APOS) . ', ' . ($vi - 1) . ')\'>';
            $html .= '<video src="' . htmlspecialchars($video) . '" poster="' . htmlspecialchars($vcover) . '" preload="metadata" style="width:100%;height:100%;object-fit:cover;background:#000;" muted playsinline onloadedmetadata="this.parentElement.querySelector(\'.video-duration\').textContent=formatTime(this.duration)"></video>';
            $html .= '<div class="video-play-btn"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div>';
            $html .= '<span class="video-duration">00:00</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }

    if (!empty($item['music']) && !$parsed['has_music']) {
        foreach ($item['music'] as $mi => $m) {
            $html .= '<div class="music-wrap"><div class="mc" id="mc-' . $item_id . '-' . $mi . '" data-murl="' . htmlspecialchars($m['url'] ?? '') . '" data-mid="' . $item_id . '-' . $mi . '" data-mname="' . htmlspecialchars($m['name'] ?? '') . '" data-martist="' . htmlspecialchars($m['artist'] ?? '') . '" data-mcover="' . htmlspecialchars($m['cover'] ?? '') . '" onclick="playMcCard(this)"><div class="mcv"><img src="' . htmlspecialchars($m['cover'] ?? '') . '" alt="cover" crossorigin="anonymous"></div><div class="mi"><div class="mn">' . htmlspecialchars($m['name'] ?? '') . '</div><div class="ma">' . htmlspecialchars($m['artist'] ?? '') . '</div></div><div class="mp" id="mp-' . $item_id . '-' . $mi . '"></div></div></div>';
        }
    }

    $meta_class = empty($item['location_name']) ? 'moment-meta-only' : 'moment-meta';
    $html .= '<div class="' . $meta_class . '">';
    $html .= '<div class="moment-time">' . time_ago($item['published_at']);
    if ($item['location_name']) {
        $html .= '<span class="moment-location"><svg class="location-icon" viewBox="0 0 12 12"><path d="M6 0C3.8 0 2 1.8 2 4c0 3 4 8 4 8s4-5 4-8c0-2.2-1.8-4-4-4zm0 5.5c-.8 0-1.5-.7-1.5-1.5S5.2 2.5 6 2.5s1.5.7 1.5 1.5S6.8 5.5 6 5.5z"/></svg>' . htmlspecialchars($item['location_name']) . '</span>';
    }
    $html .= '</div>';

    $html .= '<div class="menu-btn-wrap">';
    $html .= '<div class="action-bar" id="action-bar-' . $item_id . '">';
    if (($item['like_enabled'] ?? 1)) {
        $heart_path = $item['is_liked']
            ? 'M11.19 6.53A4.84 4.84 0 0 0 7.5 4.82c-2.6 0-4.69 2.03-4.69 4.53 0 2.1 1.24 3.87 2.98 5.43L12 20.38l3.23-3.1M12 4.06l.75.74M16.5 4.82a4.84 4.84 0 0 1 3.69 1.71c.88 1.03 1.31 2.32 1.19 3.65M16.19 9.53l-2.5 2.5-2.5-2.5'
            : 'M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z';
        $like_text = $item['is_liked'] ? '取消' : '赞';
        $html .= '<div class="action-bar-item" onclick="doLike(' . $item_id . ', this)"><svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor;"><path d="' . $heart_path . '"></path></svg><span class="like-text">' . $like_text . '</span></div>';
    }
    if (($item['comment_enabled'] ?? 1)) {
        $html .= '<div class="action-bar-item" onclick="openCommentModal(' . $item_id . ')"><svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>评论</div>';
    }
    $html .= '</div>';
    $html .= '<div class="menu-btn" data-toggle="' . $item_id . '"></div>';
    $html .= '</div></div>';

    if (!empty($item['likes']) || !empty($item['comments']) || !($item['like_enabled'] ?? 1) || !($item['comment_enabled'] ?? 1)) {
        $html .= '<div class="interaction-area" id="interact-' . $item_id . '">';
        if (!empty($item['likes'])) {
            $display_likes = array_slice($item['likes'], 0, 8);
            $like_str = implode('、', $display_likes);
            if ($item['like_count'] > 8) {
                $like_str .= '、访客×' . ($item['like_count'] - 8);
            }
            $html .= '<div class="likes"><svg class="like-icon" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg><span class="like-names" id="likes-' . $item_id . '">' . $like_str . '</span></div>';
        }
        if (!empty($item['comments'])) {
            $html .= '<div class="comments" id="comments-' . $item_id . '">' . render_comment_tree($item['comments'], $item_id) . '</div>';
        }
        if (!($item['like_enabled'] ?? 1) || !($item['comment_enabled'] ?? 1)) {
            $html .= '<div class="interaction-disabled">';
            if (!($item['like_enabled'] ?? 1)) $html .= '<span>已关闭点赞</span>';
            if (!($item['comment_enabled'] ?? 1)) $html .= '<span>已关闭评论</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }

    $html .= '</div></div>';
    return $html;
}

function render_article_html($item, $settings, $avatar, $nickname, $nickname_color) {
    $item_id = $item['id'];
    $title = $item['title'] ?? '';
    $slug = $item['slug'] ?? '';
    $cover = $item['cover_image'] ?? '';
    $published = $item['published_at'] ?? '';
    $date_str = $published ? date('Y年m月d日', strtotime($published)) : '';

    $card_colors = [
        '#5a7a9a', '#6a7a9a', '#5a8a8a', '#7a6a9a',
        '#8a7a6a', '#6a8a7a', '#6a6a8a', '#7a7a6a',
        '#8a6a7a', '#6a8a6a',
    ];
    $ci = hexdec(substr(md5($slug), 0, 8)) % count($card_colors);

    $bg_mode = intval($item['bg_mode'] ?? 0);
    $bg_color_val = $item['bg_color'] ?? '';
    $is_magazine = ($bg_mode === 1);
    $card_bg = ($bg_color_val !== '') ? $bg_color_val : '#000000';

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
        } elseif ($bg_color_val !== '') {
            $magazine_style = 'background:' . htmlspecialchars($bg_color_val) . ';';
        } else {
            $magazine_style = 'background:' . htmlspecialchars($card_bg) . ';';
        }
    }

    $html = '<div class="moment-item article-moment" data-id="' . $item_id . '" data-type="article">';
    $html .= '<img src="' . htmlspecialchars($avatar) . '" alt="avatar" class="moment-avatar">';
    $html .= '<div class="moment-body">';
    $html .= '<div class="name-row">';
    $html .= '<span class="moment-nickname" style="color:' . htmlspecialchars($nickname_color) . '">' . htmlspecialchars($nickname) . '</span>';
    if ($item['is_top']) $html .= ' <span class="top-tag">置顶</span>';
    $html .= '</div>';
    $html .= '<div class="article-new-card ' . ($is_magazine ? 'card-magazine' : 'card-minimal') . '" style="' . ($is_magazine ? $magazine_style : '') . '" onclick="location.href=\'' . urlencode($slug) . '.article.html\'">';
    $html .= '<div class="article-card-body">';
    $html .= '<div class="article-card-title">' . htmlspecialchars($title) . '</div>';
    if (!$is_magazine) $html .= '<div class="article-card-summary">' . htmlspecialchars($summary_raw) . '</div>';
    $html .= '<div class="article-card-date">' . $date_str . '</div>';
    $html .= '</div></div>';
    $html .= '<div class="moment-footer">';
    $html .= '<span class="moment-time">' . time_ago($item['published_at']) . '</span>';
    $html .= '<a href="' . urlencode($slug) . '.article.html" class="read-full-btn">→ 查看全文</a>';
    $html .= '</div></div></div>';
    return $html;
}

function render_ad_html($item, $settings, $ad_nickname_color, $user_ip) {
    $item_id = $item['id'];
    $display_color = htmlspecialchars($item['display_nickname_color']);

    $html = '<div class="ad-item" data-id="' . $item_id . '" data-type="ad">';

    $html .= '<div class="ad-header">';
    if ($item['avatar']) {
        $html .= '<img src="' . htmlspecialchars($item['avatar']) . '" class="ad-avatar-img" alt="">';
    } else {
        $html .= '<div class="ad-avatar-default">广告</div>';
    }
    $html .= '<div class="ad-info"><div class="ad-title" style="color:' . $display_color . ';">' . htmlspecialchars($item['title']) . '</div><div class="ad-subtitle">' . htmlspecialchars($item['subtitle']) . '</div></div>';
    $html .= '<span class="ad-tag">广告</span>';
    $html .= '</div>';

    if ($item['content']) {
        $html .= '<div class="ad-text">' . htmlspecialchars($item['content']) . '</div>';
    }

    if ($item['style'] == 1) {
        $html .= '<div class="ad-card" data-link="' . htmlspecialchars($item['link'] ?: '#') . '" onclick="openAdLink(this)"><div class="ad-card-image">';
        if ($item['image']) {
            $html .= '<img src="' . htmlspecialchars($item['image']) . '" alt="" onclick="event.stopPropagation()">';
        } else {
            $html .= '广告';
        }
        $html .= '</div><div class="ad-card-content"><div class="ad-card-title">' . htmlspecialchars($item['card_title'] ?: '') . '</div><div class="ad-card-desc">' . htmlspecialchars($item['card_desc'] ?: '') . '</div></div></div>';
    } else {
        $html .= '<div class="ad-image-card" data-link="' . htmlspecialchars($item['link'] ?: '#') . '" onclick="openAdLink(this)"><img src="' . htmlspecialchars($item['image'] ?: '') . '" class="ad-image-main" alt="" onclick="event.stopPropagation()"><div class="ad-image-footer"><div class="ad-brand">';
        if ($item['brand_logo']) {
            $html .= '<img src="' . htmlspecialchars($item['brand_logo']) . '" class="ad-brand-logo" alt="" onclick="event.stopPropagation()">';
        } else {
            $html .= '<div class="ad-brand-logo" style="display:flex;align-items:center;justify-content:center;font-size:9px;color:#999;">广告</div>';
        }
        $html .= '<span class="ad-brand-name">' . htmlspecialchars($item['brand_name'] ?: '') . '</span></div><a href="' . htmlspecialchars($item['link'] ?: '#') . '" class="ad-btn" target="_blank" style="background:' . htmlspecialchars($item['button_color'] ?: '#07C160') . ';text-decoration:none;" onclick="event.stopPropagation()">' . htmlspecialchars($item['button_text']) . '</a></div></div>';
    }

    $html .= '<div class="ad-actions"><span class="moment-time">' . time_ago($item['created_at']) . '</span><div class="menu-btn-wrap"><div class="action-bar" id="action-bar-ad-' . $item_id . '">';
    if (($item['like_enabled'] ?? 1)) {
        $like_text = $item['is_liked'] ? '取消' : '赞';
        $html .= '<div class="action-bar-item" onclick="doLike(-' . $item_id . ', this)"><svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor;"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg><span class="like-text">' . $like_text . '</span></div>';
    }
    if (($item['comment_enabled'] ?? 1)) {
        $html .= '<div class="action-bar-item" onclick="openCommentModal(-' . $item_id . ')"><svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>评论</div>';
    }
    $html .= '</div><div class="menu-btn" data-toggle="ad-' . $item_id . '"></div></div></div>';

    if (!empty($item['likes']) || !empty($item['comments']) || !($item['like_enabled'] ?? 1) || !($item['comment_enabled'] ?? 1)) {
        $html .= '<div class="interaction-area" id="interact-ad-' . $item_id . '">';
        if (!empty($item['likes'])) {
            $like_str = implode('、', $item['likes']);
            if ($item['like_count'] > count($item['likes'])) {
                $like_str .= '、访客+' . ($item['like_count'] - count($item['likes']));
            }
            $html .= '<div class="likes"><svg class="like-icon" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg><span class="like-names">' . $like_str . '</span></div>';
        }
        if (!empty($item['comments'])) {
            $html .= '<div class="comments" id="comments-ad-' . $item_id . '">' . render_comment_tree($item['comments'], -$item_id) . '</div>';
        }
        if (!($item['like_enabled'] ?? 1) || !($item['comment_enabled'] ?? 1)) {
            $html .= '<div class="interaction-disabled">';
            if (!($item['like_enabled'] ?? 1)) $html .= '<span>已关闭点赞</span>';
            if (!($item['comment_enabled'] ?? 1)) $html .= '<span>已关闭评论</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}
