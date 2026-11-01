<?php
@set_time_limit(0);
@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '512M');
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/backup_lib.php';

header('Content-Type: application/json; charset=utf-8');

$is_cron = isset($_GET['cron']) && $_GET['cron'] === '1';
if (!$is_cron) {
    echo json_encode(['ok' => false, 'msg' => '仅允许通过 cron 定时任务调用 请在地址后附加 ?cron=1&token=你的密钥']);
    exit;
}

$token = (string) ($_GET['token'] ?? '');
$cfg_token = get_setting('auto_backup_cron_key', '');
if ($cfg_token === '' || !function_exists('hash_equals') || !hash_equals($cfg_token, $token)) {
    echo json_encode(['ok' => false, 'msg' => 'token 无效或未配置 请到「数据备份」页面保存设置以生成调用地址']);
    exit;
}

$r = ytj_run_auto_backup();
echo json_encode($r);
exit;
