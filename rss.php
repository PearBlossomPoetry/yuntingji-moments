<?php
header('Content-Type: application/rss+xml; charset=utf-8');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

require_once 'config.php';

$settings = get_all_settings();
$site_name = htmlspecialchars($settings['site_name'] ?? '云汀记');
$site_desc = htmlspecialchars($settings['site_description'] ?? '');
$site_url = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'imoe.top');
$avatar = $settings['avatar'] ?? '';
$nickname = $settings['nickname'] ?? '作者';

$cache_dir = __DIR__ . '/cache';
$cache_file = $cache_dir . '/rss.xml';
$cache_time = 600;

if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}

if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
    readfile($cache_file);
    exit;
}

ob_start();

$items = [];

$r = mysqli_query($conn, "SELECT * FROM posts WHERE status = 1 ORDER BY published_at DESC LIMIT 30");
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) {
        $row['type'] = 'post';
        $row['link'] = $site_url . '/#' . $row['id'];
        $row['sort_time'] = $row['published_at'];
        $items[] = $row;
    }
}

$ar = mysqli_query($conn, "SELECT * FROM articles WHERE status = 1 ORDER BY published_at DESC LIMIT 30");
if ($ar) {
    while ($row = mysqli_fetch_assoc($ar)) {
        $row['type'] = 'article';
        $row['link'] = $site_url . '/' . urlencode($row['slug'] ?? $row['id']) . '.html';
        $row['sort_time'] = $row['published_at'];
        $items[] = $row;
    }
}

usort($items, function($a, $b) {
    return strtotime($b['sort_time']) - strtotime($a['sort_time']);
});
$items = array_slice($items, 0, 30);

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <channel>
    <title><?php echo $site_name; ?></title>
    <link><?php echo $site_url; ?></link>
    <description><?php echo $site_desc; ?></description>
    <language>zh-CN</language>
    <lastBuildDate><?php echo date('r'); ?></lastBuildDate>
    <atom:link href="<?php echo $site_url; ?>/feed" rel="self" type="application/rss+xml" />
    <image>
      <url><?php echo htmlspecialchars($avatar); ?></url>
      <title><?php echo $site_name; ?></title>
      <link><?php echo $site_url; ?></link>
    </image>
<?php foreach ($items as $item):
    $title = '';
    $desc = '';
    $content = '';

    if ($item['type'] === 'post') {
        $title = ($item['nickname'] ?: $nickname) . ' · ' . date('m月d日 H:i', strtotime($item['published_at']));
        $content = $item['content'] ?? '';

        $images = json_decode($item['images'] ?? '[]', true) ?: [];
        if (!empty($images)) {
            foreach ($images as $img) {
                $content .= '<p><img src="' . htmlspecialchars($img) . '" alt="图片" /></p>';
            }
        }

        $videos = json_decode($item['videos'] ?? '[]', true) ?: [];
        if (!empty($videos)) {
            foreach ($videos as $video) {
                $content .= '<p><video src="' . htmlspecialchars($video) . '" controls></video></p>';
            }
        }

        $mr = mysqli_query($conn, "SELECT * FROM music WHERE post_id = " . intval($item['id']) . " ORDER BY id ASC");
        if ($mr) {
            while ($m = mysqli_fetch_assoc($mr)) {
                $content .= '<p>🎵 ' . htmlspecialchars($m['name'] ?? '') . ' - ' . htmlspecialchars($m['artist'] ?? '') . '</p>';
            }
        }

        $desc = strip_tags($content);
        if (mb_strlen($desc) > 200) {
            $desc = mb_substr($desc, 0, 200) . '...';
        }

    } else {
        $title = $item['title'] ?? '无标题';
        $summary = trim($item['summary'] ?? '');
        if ($summary === '') {
            $plain = strip_tags($item['content'] ?? '');
            $plain = str_replace(["\n", "\r", ' ', '&nbsp;'], '', $plain);
            $summary = mb_substr($plain, 0, 200);
            if (mb_strlen($plain) > 200) $summary .= '...';
        }
        $desc = $summary;
        $content = $item['content'] ?? '';
        if ($item['cover_image']) {
            $content = '<p><img src="' . htmlspecialchars($item['cover_image']) . '" alt="封面" /></p>' . $content;
        }
    }

    if (function_exists('parse_shortcodes')) {
        $parsed = parse_shortcodes($content, $item);
        $content = $parsed['html'] ?? $content;
    }
    if (function_exists('parse_content')) {
        $content = parse_content($content);
    }
    if (function_exists('parse_stickers')) {
        $content = parse_stickers($content);
    }

    $content = str_replace(']]>', ']]]]><![CDATA[>', $content);
?>
    <item>
      <title><?php echo htmlspecialchars($title); ?></title>
      <link><?php echo htmlspecialchars($item['link']); ?></link>
      <guid isPermaLink="true"><?php echo htmlspecialchars($item['link']); ?></guid>
      <pubDate><?php echo date('r', strtotime($item['sort_time'])); ?></pubDate>
      <dc:creator><?php echo htmlspecialchars($item['nickname'] ?? $nickname); ?></dc:creator>
      <description><![CDATA[<?php echo $desc; ?>]]></description>
      <content:encoded><![CDATA[<?php echo $content; ?>]]></content:encoded>
    </item>
<?php endforeach; ?>
  </channel>
</rss>
<?php
file_put_contents($cache_file, ob_get_contents());
ob_end_flush();
?>
