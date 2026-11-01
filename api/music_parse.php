<?php
require_once '../config.php';
if (!$conn) { json_response(0, '系统未安装'); }
$settings = get_all_settings();
header('Content-Type: application/json; charset=utf-8');

function json_out($code, $msg = '', $data = []) {
    global $parseMode;
    echo json_encode(['code' => $code, 'msg' => $msg, 'data' => $data, 'mode' => $parseMode ?? 'official'], JSON_UNESCAPED_UNICODE);
    exit;
}

function is_safe_url($url) {
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return false;

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }

    return true;
}

function http_get($url, $headers = [], $timeout = 12) {
    if (!is_safe_url($url)) {
        return ['body' => '', 'code' => 0, 'url' => $url];
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    $hdr = ['Accept: */*', 'Accept-Language: zh-CN,zh;q=0.9'];
    if ($headers) $hdr = array_merge($hdr, $headers);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $hdr);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return ['body' => $resp, 'code' => $code, 'url' => $finalUrl];
}

function http_post_json($url, $json, $headers = [], $timeout = 12) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    $hdr = ['Content-Type: application/json', 'Accept: */*'];
    if ($headers) $hdr = array_merge($hdr, $headers);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $hdr);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['body' => $resp, 'code' => $code];
}

$parseMode  = $settings['music_parse_mode'] ?? 'official';
$customApi  = trim($settings['music_api_url'] ?? '');
$defaultPlatform = $settings['music_platform'] ?? 'netease';

$url = trim($_POST['url'] ?? $_GET['url'] ?? '');
$platform = trim($_POST['platform'] ?? $_GET['platform'] ?? $defaultPlatform);

if (!$url) {
    json_out(0, '链接不能为空');
}

if (!preg_match('#^https?://#', $url)) {
    if (preg_match('#(https?://[^\s（）()\x{4e00}-\x{9fa5}]+)#u', $url, $m)) {
        $url = rtrim($m[1], ',.;');
    }
}

if ($platform === $defaultPlatform || empty($platform)) {
    if (preg_match('/163cn\.|music\.163\.com|y\.music\.163/', $url)) $platform = 'netease';
    elseif (preg_match('/y\.qq\.com|i\.y\.qq\.com|c6\.y\.qq\.com|qqmusic/', $url)) $platform = 'qq';
    elseif (preg_match('/kugou\.com|kugou\.cn/', $url)) $platform = 'kugou';
}

function extract_netease_id($url) {
    $songId = '';
    if (strpos($url, '163cn.tv') !== false || strpos($url, '163cn.link') !== false) {
        $r = http_get($url, ['Referer: https://music.163.com/']);
        if ($r['url'] && preg_match('/id[=/](\d+)/', $r['url'], $m)) $songId = $m[1];
        if (!$songId && $r['body']) {
            if (preg_match('/song[?\/]id[=:]*(\d+)/', $r['body'], $m)) $songId = $m[1];
            elseif (preg_match('/"id"[:"\s]*(\d+)/', $r['body'], $m)) $songId = $m[1];
        }
    }
    if (!$songId && preg_match('/music\.163\.com.*[?&]id=(\d+)/', $url, $m)) $songId = $m[1];
    if (!$songId && preg_match('/y\.music\.163\.com.*[?&]id=(\d+)/', $url, $m)) $songId = $m[1];
    if (!$songId && preg_match('/id[=:](\d+)/', $url, $m)) $songId = $m[1];
    return $songId ?: null;
}

function extract_qq_id($url) {
    $songmid = '';
    if (preg_match('/url\.cn|t\.cn/', $url)) {
        $r = http_get($url);
        $url = $r['url'] ?: $url;
        if ($r['body'] && preg_match('/songmid[=:]["\']*([A-Za-z0-9]+)/', $r['body'], $m)) $songmid = $m[1];
    }
    if (!$songmid && preg_match('/songmid=([A-Za-z0-9]+)/', $url, $m)) $songmid = $m[1];
    if (!$songmid && preg_match('/songDetail\/([A-Za-z0-9]+)/', $url, $m)) $songmid = $m[1];
    if (!$songmid && preg_match('/playsong.*songmid=([A-Za-z0-9]+)/', $url, $m)) $songmid = $m[1];
    return $songmid ?: null;
}

function extract_kugou_id($url) {
    $hash = '';
    if (preg_match('/(t1|t)\.kugou\.com/', $url)) {
        $r = http_get($url, ['Referer: https://www.kugou.com/']);
        $url = $r['url'] ?: $url;
        if ($r['body']) {
            if (preg_match('/hash[=:]["\']*([A-Fa-f0-9]{32})/', $r['body'], $m)) $hash = $m[1];
            elseif (preg_match('/"hash"\s*:\s*"([A-Fa-f0-9]{32})/', $r['body'], $m)) $hash = $m[1];
        }
    }
    if (!$hash && preg_match('/hash=([A-Fa-f0-9]{32})/', $url, $m)) $hash = $m[1];
    if (!$hash && preg_match('/#hash=([A-Fa-f0-9]{32})/', $url, $m)) $hash = $m[1];
    if (!$hash && preg_match('/([A-Fa-f0-9]{32})/', $url, $m)) $hash = $m[1];
    return $hash ?: null;
}

$songId = '';
switch ($platform) {
    case 'qq':    $songId = extract_qq_id($url); break;
    case 'kugou': $songId = extract_kugou_id($url); break;
    case 'netease':
    default:      $songId = extract_netease_id($url); break;
}

if ($parseMode === 'custom' && $customApi) {
    if (!$songId) {
        json_out(0, '无法从链接中提取歌曲ID，请检查链接是否正确');
    }

    $mediaMap = ['qq' => 'tencent', 'netease' => 'netease'];
    $media = $mediaMap[$platform] ?? 'netease';

    $tryBugpk = function($id, $med) use ($customApi) {
        $apiUrl = $customApi . (strpos($customApi, '?') !== false ? '&' : '?') . http_build_query([
            'id' => $id, 'media' => $med, 'type' => 'song'
        ]);
        $r = http_get($apiUrl, [], 15);
        if (!$r['body'] || $r['code'] !== 200) return null;
        $data = json_decode($r['body'], true);
        if ($data === null) return null;
        $inner = isset($data['code']) ? ($data['data'] ?? $data) : $data;
        $u = $inner['url'] ?? '';
        if (!$u || strpos($u, '版权') !== false) return null;
        return [
            'name'   => $inner['name'] ?? '',
            'artist' => $inner['author'] ?? ($inner['artist'] ?? ''),
            'cover'  => $inner['cover'] ?? ($inner['pic'] ?? ''),
            'url'    => $u,
            'song_id'=> $inner['song_id'] ?? $id
        ];
    };

    $result = $tryBugpk($songId, $media);
    if ($result) json_out(1, '解析成功', $result);

    $songName = '';
    $songArtist = '';
    if ($platform === 'netease') {
        $infoUrl = 'https://music.163.com/api/song/detail?ids=[' . $songId . ']';
        $r = http_get($infoUrl, ['Referer: https://music.163.com/']);
        if ($r['body']) {
            $d = json_decode($r['body'], true);
            if (isset($d['songs'][0])) {
                $songName = $d['songs'][0]['name'] ?? '';
                if (isset($d['songs'][0]['artists'])) {
                    $songArtist = implode(' ', array_column($d['songs'][0]['artists'], 'name'));
                }
            }
        }
    } else if ($platform === 'qq') {
        $infoUrl = 'https://c.y.qq.com/v8/fcg-bin/fcg_play_single_song.fcg?songmid=' . $songId . '&format=json';
        $r = http_get($infoUrl, ['Referer: https://y.qq.com/']);
        if ($r['body']) {
            $d = json_decode($r['body'], true);
            if (isset($d['data'][0])) {
                $songName = $d['data'][0]['name'] ?? '';
                if (isset($d['data'][0]['singer'])) {
                    $songArtist = implode(' ', array_column($d['data'][0]['singer'], 'name'));
                }
            }
        }
    }

    if (!$songName) {
        json_out(0, '该歌曲版权受限，且无法获取歌曲信息进行音源切换');
    }

    $keyword = $songName . ' ' . $songArtist;

    $altIds = [];
    if ($platform === 'netease') {
        $sUrl = 'https://music.163.com/api/search/get?s=' . urlencode($keyword) . '&type=1&offset=0&limit=8';
        $r = http_get($sUrl, ['Referer: https://music.163.com/']);
        if ($r['body']) {
            $d = json_decode($r['body'], true);
            if (isset($d['result']['songs'])) {
                foreach ($d['result']['songs'] as $s) {
                    if ($s['id'] != $songId) $altIds[] = $s['id'];
                }
            }
        }
    } else if ($platform === 'qq') {
        $sUrl = 'https://c.y.qq.com/soso/fcgi-bin/client_search_cp?p=1&n=8&w=' . urlencode($keyword) . '&format=json';
        $r = http_get($sUrl, ['Referer: https://y.qq.com/']);
        if ($r['body']) {
            $d = json_decode($r['body'], true);
            if (isset($d['data']['song']['list'])) {
                foreach ($d['data']['song']['list'] as $s) {
                    if (($s['songmid'] ?? '') != $songId) $altIds[] = $s['songmid'];
                }
            }
        }
    }

    foreach ($altIds as $altId) {
        $result = $tryBugpk($altId, $media);
        if ($result) json_out(1, '解析成功（音源切换）', $result);
    }

    $otherMedia = ($media === 'netease') ? 'tencent' : 'netease';
    $crossIds = [];
    if ($otherMedia === 'netease') {
        $sUrl = 'https://music.163.com/api/search/get?s=' . urlencode($keyword) . '&type=1&offset=0&limit=5';
        $r = http_get($sUrl, ['Referer: https://music.163.com/']);
        if ($r['body']) {
            $d = json_decode($r['body'], true);
            if (isset($d['result']['songs'])) {
                foreach ($d['result']['songs'] as $s) $crossIds[] = $s['id'];
            }
        }
    } else {
        $sUrl = 'https://c.y.qq.com/soso/fcgi-bin/client_search_cp?p=1&n=5&w=' . urlencode($keyword) . '&format=json';
        $r = http_get($sUrl, ['Referer: https://y.qq.com/']);
        if ($r['body']) {
            $d = json_decode($r['body'], true);
            if (isset($d['data']['song']['list'])) {
                foreach ($d['data']['song']['list'] as $s) $crossIds[] = $s['songmid'];
            }
        }
    }

    foreach ($crossIds as $crossId) {
        $result = $tryBugpk($crossId, $otherMedia);
        if ($result) json_out(1, '解析成功（跨平台音源）', $result);
    }

    json_out(0, '该歌曲所有音源均受限，无法解析完整播放地址');
}

switch ($platform) {
    case 'qq':
        $result = parse_qq($url);
        break;
    case 'kugou':
        $result = parse_kugou($url);
        break;
    case 'netease':
    default:
        $result = parse_netease($url);
        break;
}

if ($result) {
    json_out(1, '解析成功', $result);
} else {
    json_out(0, '解析失败，请检查链接是否正确');
}

function parse_netease($url) {
    $songId = extract_netease_id($url);
    if (!$songId) return null;

    $apiUrl = 'https://music.163.com/api/song/detail?ids=[' . $songId . ']';
    $r = http_get($apiUrl, ['Referer: https://music.163.com/', 'Origin: https://music.163.com']);
    if (!$r['body'] || $r['code'] !== 200) return null;

    $data = json_decode($r['body'], true);
    if (!isset($data['songs'][0])) return null;

    $song = $data['songs'][0];
    $name = $song['name'] ?? '';
    $artist = '';
    if (isset($song['artists']) && is_array($song['artists'])) {
        $artist = implode('/', array_column($song['artists'], 'name'));
    }
    $cover = $song['album']['picUrl'] ?? '';
    if ($cover) $cover .= '?param=300x300';

    $musicUrl = '';
    $outerUrl = 'https://music.163.com/song/media/outer/url?id=' . $songId . '.mp3';
    $r2 = http_get($outerUrl, ['Referer: https://music.163.com/']);
    if ($r2['code'] === 200 && $r2['url'] && strpos($r2['url'], '404') === false) {
        $musicUrl = $r2['url'];
    } elseif ($r2['code'] === 302 || $r2['code'] === 301) {
        $musicUrl = 'https://music.163.com/song/media/outer/url?id=' . $songId . '.mp3';
    }

    if (!$musicUrl) {
        $musicUrl = 'https://music.163.com/song/media/outer/url?id=' . $songId . '.mp3';
    }

    return [
        'name' => $name,
        'artist' => $artist,
        'cover' => $cover,
        'url' => $musicUrl,
        'song_id' => $songId
    ];
}

function parse_qq($url) {
    $songmid = extract_qq_id($url);
    if (!$songmid) return null;

    $guid = mt_rand(1000000000, 9999999999);

    $payload = json_encode([
        'comm' => ['uin' => '', 'format' => 'json', 'ct' => 24, 'cv' => 0],
        'req_0' => [
            'module' => 'vkey.GetVkeyServer',
            'method' => 'CgiGetVkey',
            'param' => [
                'guid' => (string)$guid,
                'songmid' => [$songmid],
                'songtype' => [0],
                'uin' => '',
                'loginflag' => 1,
                'platform' => '20'
            ]
        ]
    ]);
    $r = http_post_json('https://u.y.qq.com/cgi-bin/musicu.fcg', $payload, [
        'Referer: https://y.qq.com/',
        'Origin: https://y.qq.com'
    ]);
    $musicUrl = '';
    if ($r['body']) {
        $d = json_decode($r['body'], true);
        $info = $d['req_0']['data']['midurlinfo'][0] ?? [];
        $purl = $info['purl'] ?? '';
        $sip = $d['req_0']['data']['sip'][0] ?? 'https://dl.stream.qqmusic.qq.com/';
        if ($purl) $musicUrl = $sip . $purl;
    }

    $name = ''; $artist = ''; $cover = '';
    $infoUrl = 'https://c.y.qq.com/v8/fcg-bin/fcg_play_single_song.fcg?songmid=' . $songmid . '&format=json';
    $r2 = http_get($infoUrl, ['Referer: https://y.qq.com/']);
    if ($r2['body']) {
        $body = $r2['body'];
        if (preg_match('/^\s*callback\((.+)\)\s*$/', $body, $m)) $body = $m[1];
        $d2 = json_decode($body, true);
        $songInfo = $d2['data'][0] ?? ($d2['songlist'][0] ?? null);
        if ($songInfo) {
            $name = $songInfo['name'] ?? ($songInfo['songname'] ?? '');
            if (isset($songInfo['singer']) && is_array($songInfo['singer'])) {
                $artist = implode('/', array_column($songInfo['singer'], 'name'));
            }
            $albumMid = $songInfo['albummid'] ?? ($songInfo['album']['mid'] ?? '');
            if ($albumMid) {
                $cover = 'https://y.gtimg.cn/music/photo_new/T002R300x300M000' . $albumMid . '.jpg';
            }
        }
    }

    if (!$name && $r['body']) {
        $d = json_decode($r['body'], true);
        $songInfo = $d['req_0']['data']['songinfo'][0] ?? null;
        if ($songInfo) {
            $name = $songInfo['title'] ?? $songInfo['songname'] ?? '';
            $singer = $songInfo['singer'] ?? [];
            if (is_array($singer)) {
                $names = [];
                foreach ($singer as $s) $names[] = $s['name'] ?? '';
                $artist = implode('/', array_filter($names));
            }
        }
    }

    if (!$musicUrl) {
        $musicUrl = 'https://ws.stream.qqmusic.qq.com/C400' . $songmid . '.m4a?fromtag=0&guid=' . $guid;
    }

    return [
        'name' => $name ?: '未知歌曲',
        'artist' => $artist,
        'cover' => $cover,
        'url' => $musicUrl,
        'song_id' => $songmid
    ];
}

function parse_kugou($url) {
    $hash = extract_kugou_id($url);
    if (!$hash) return null;

    $dfid = '2ZfU6e0WfIqr80' . mt_rand(100, 999);
    $mid = md5($dfid);

    $apiUrl = 'https://wwwapi.kugou.com/yy/index.php?r=play/getdata&hash=' . $hash . '&dfid=' . urlencode($dfid) . '&mid=' . $mid . '&appid=1014&clientver=1000&clienttime=0&srcappid=2919&userid=0';
    $r = http_get($apiUrl, [
        'Referer: https://www.kugou.com/song/',
        'Cookie: kg_mid=' . $mid
    ]);
    if (!$r['body']) return null;
    $d = json_decode($r['body'], true);
    $info = $d['data'] ?? null;
    if (!$info) return null;

    $name = $info['audio_name'] ?? ($info['song_name'] ?? '未知歌曲');
    $author = $info['author_name'] ?? ($info['singer_name'] ?? '');
    $cover = $info['img'] ?? '';
    $musicUrl = $info['play_url'] ?? ($info['url'] ?? '');

    if (!$musicUrl) {
        $mApiUrl = 'https://m.kugou.com/app/i/getSongInfo.php?cmd=playInfo&hash=' . $hash;
        $r2 = http_get($mApiUrl, ['Referer: https://m.kugou.com/']);
        if ($r2['body']) {
            $d2 = json_decode($r2['body'], true);
            $musicUrl = $d2['url'] ?? '';
            if (!$name || $name === '未知歌曲') $name = $d2['songName'] ?? $name;
            if (!$author) $author = $d2['singerId'] ?? '';
            if (!$cover) $cover = $d2['imgUrl'] ?? '';
        }
    }

    if (!$musicUrl) return null;

    return [
        'name' => $name,
        'artist' => $author,
        'cover' => $cover,
        'url' => $musicUrl,
        'song_id' => $hash
    ];
}
