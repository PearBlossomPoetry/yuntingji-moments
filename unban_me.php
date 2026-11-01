<?php
// 解封脚本，密钥见下方
// ?k=密钥 解开当前 IP，加 &ip= 解指定 IP，加 &all=1 清空

// 访问密钥
$SECRET = 'ytj-unban-2025';

header('Content-Type: text/html; charset=utf-8');

// 校验密钥
$k = $_GET['k'] ?? '';
if ($k !== $SECRET) {
    http_response_code(403);
    echo '<h2>访问被拒绝</h2><p>请提供正确的密钥参数：?k=解封密钥</p>';
    exit;
}

// 找配置文件
$candidates = [__DIR__ . '/db.config.php', __DIR__ . '/../db.config.php'];
$dbfile = null;
foreach ($candidates as $c) {
    if (file_exists($c)) { $dbfile = $c; break; }
}
if (!$dbfile) {
    echo '<p style="color:red">找不到 db.config.php。请把本文件放到程序根目录（与 index.php 同级）。</p>';
    exit;
}
require_once $dbfile;

if (!defined('DB_HOST') || !defined('DB_NAME')) {
    echo '<p style="color:red">db.config.php 未定义数据库常量，无法连接。</p>';
    exit;
}

$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    echo '<p style="color:red">数据库连接失败：' . htmlspecialchars(mysqli_connect_error()) . '</p>';
    exit;
}
mysqli_set_charset($conn, 'utf8mb4');

// 当前 IP
function visitor_ip() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    }
    return $ip;
}

$self_ip = visitor_ip();
$msg = [];

if (isset($_GET['all']) && $_GET['all'] === '1') {
    mysqli_query($conn, "DELETE FROM banned_ips");
    $msg[] = "已清空全部 IP 黑名单。";
} elseif (!empty($_GET['ip'])) {
    $esc = mysqli_real_escape_string($conn, $_GET['ip']);
    mysqli_query($conn, "DELETE FROM banned_ips WHERE ip = '$esc'");
    $msg[] = "已删除指定 IP：{$_GET['ip']}（影响行数：" . mysqli_affected_rows($conn) . "）";
} else {
    $esc = mysqli_real_escape_string($conn, $self_ip);
    mysqli_query($conn, "DELETE FROM banned_ips WHERE ip = '$esc'");
    $aff = mysqli_affected_rows($conn);
    $msg[] = "已尝试解封你的访问 IP：$self_ip（影响行数：$aff）";
    if ($aff === 0) {
        $msg[] = "提示：当前访问 IP 不在黑名单中。可能你的网络/代理 IP 与当时被封的不一致，"
               . "请改用 ?ip=实际被封IP 指定删除，或 ?all=1 清空全部。";
    }
}

// 剩余黑名单
$res = mysqli_query($conn, "SELECT * FROM banned_ips ORDER BY created_at DESC");
$rows = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
?>
<!doctype html>
<html lang="zh-CN">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>临时解封</title></head>
<body style="font-family:system-ui;padding:24px;max-width:760px;margin:0 auto;color:#222">
<h2>临时解封结果</h2>
<ul>
<?php foreach ($msg as $m): ?><li style="margin:6px 0"><?php echo htmlspecialchars($m); ?></li><?php endforeach; ?>
</ul>
<h3>当前剩余黑名单（共 <?php echo count($rows); ?> 条）</h3>
<?php if (empty($rows)): ?>
  <p>无（已全部解封，前台可正常访问）</p>
<?php else: ?>
  <table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:14px">
    <tr><th>ID</th><th>IP</th><th>原因</th><th>时间</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?php echo (int)$r['id']; ?></td>
      <td><?php echo htmlspecialchars($r['ip']); ?></td>
      <td><?php echo htmlspecialchars($r['reason'] ?? ''); ?></td>
      <td><?php echo htmlspecialchars($r['created_at'] ?? ''); ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>
<hr>
<p style="color:#c0392b"><b>操作完成后请立即删除本文件 unban_me.php 避免被他人利用！</b></p>
</body>
</html>
