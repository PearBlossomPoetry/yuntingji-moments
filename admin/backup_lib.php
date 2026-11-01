<?php
if (!defined('YTJ_BACKUP_LIB_LOADED')) {
    define('YTJ_BACKUP_LIB_LOADED', true);

    if (!isset($restore_embed_source)) {
        $__restore_embed_file = __DIR__ . '/restore_embed.php';
        if (file_exists($__restore_embed_file)) {
            require_once $__restore_embed_file;
        }
    }

    if (!function_exists('ytj_sql_escape')) {
        function ytj_sql_escape($conn, $value) {
            if ($value === null) return 'NULL';
            return "'" . mysqli_real_escape_string($conn, $value) . "'";
        }
    }

    if (!function_exists('ytj_get_table_create')) {
        function ytj_get_table_create($conn, $table) {
            $result = mysqli_query($conn, "SHOW CREATE TABLE `" . mysqli_real_escape_string($conn, $table) . "`");
            if ($result && $row = mysqli_fetch_assoc($result)) {
                return "\n-- 表结构 `" . $table . "`\n" .
                    "DROP TABLE IF EXISTS `" . $table . "`;\n" .
                    $row['Create Table'] . ";\n";
            }
            return '';
        }
    }

    if (!function_exists('ytj_get_table_inserts')) {
        function ytj_get_table_inserts($conn, $table) {
            $sql = "";
            $result = mysqli_query($conn, "SELECT * FROM `" . mysqli_real_escape_string($conn, $table) . "`");
            if (!$result) return $sql;

            $columns = [];
            foreach (mysqli_fetch_fields($result) as $field) {
                $columns[] = "`" . $field->name . "`";
            }

            $batch = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $values = [];
                foreach ($row as $val) {
                    $values[] = ytj_sql_escape($conn, $val);
                }
                $batch[] = "(" . implode(", ", $values) . ")";
            }

            if (!empty($batch)) {
                $sql .= "\n-- 数据 `" . $table . "`\n";
                $sql .= "INSERT INTO `" . $table . "` (" . implode(", ", $columns) . ") VALUES\n";
                $sql .= implode(",\n", $batch) . ";\n";
            }
            return $sql;
        }
    }

    if (!function_exists('ytj_add_dir_to_zip')) {
        function ytj_add_dir_to_zip($zip, $dir, $base_len, $prefix = '') {
            if (!is_dir($dir)) return;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                $real_path = $file->getRealPath();
                $relative = substr($real_path, $base_len);
                $relative = ltrim(str_replace('\\', '/', $relative), '/');
                $zip_path = $prefix ? $prefix . '/' . $relative : $relative;
                if ($file->isDir()) {
                    $zip->addEmptyDir($zip_path);
                } else {
                    $zip->addFile($real_path, $zip_path);
                }
            }
        }
    }

    if (!function_exists('ytj_rrmdir')) {
        function ytj_rrmdir($dir) {
            if (!is_dir($dir)) return;
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                is_dir($path) ? ytj_rrmdir($path) : unlink($path);
            }
            rmdir($dir);
        }
    }

    if (!function_exists('ytj_create_site_backup')) {
        function ytj_create_site_backup($conn, $settings, $dest_dir, $filename_base) {
            global $restore_embed_source;

            if (!is_dir($dest_dir)) {
                if (!@mkdir($dest_dir, 0755, true)) {
                    error_log('[自动备份] 无法创建输出目录: ' . $dest_dir);
                    return false;
                }
            }

            $tables = [];
            $res = mysqli_query($conn, "SHOW TABLES");
            if ($res) {
                while ($row = mysqli_fetch_row($res)) {
                    $tables[] = $row[0];
                }
            }
            if (empty($tables)) {
                error_log('[自动备份] 未读取到任何数据表');
                return false;
            }

            $tmp_dir = sys_get_temp_dir() . '/' . uniqid('backup_', true);
            if (!is_dir($tmp_dir)) mkdir($tmp_dir, 0755, true);

            $zip_file = rtrim($dest_dir, '/') . '/' . $filename_base . '.zip';

            $sql = "-- 全站数据备份\n";
            $sql .= "-- 备份时间：" . date('Y-m-d H:i:s') . "\n";
            $sql .= "-- 数据库：" . (defined('DB_NAME') ? DB_NAME : '') . "\n";
            $sql .= "SET NAMES utf8mb4;\n";
            $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n";
            foreach ($tables as $table) {
                $sql .= ytj_get_table_create($conn, $table);
                $sql .= ytj_get_table_inserts($conn, $table);
            }
            $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
            file_put_contents($tmp_dir . '/database.sql', $sql);

            $readme_text = "网站迁移说明\n";
            $readme_text .= "====================\n\n";
            $readme_text .= "1. 将本压缩包上传到新的服务器/虚拟主机并解压\n";
            $readme_text .= "2. 访问 restore.php选择本压缩包并上传\n";
            $readme_text .= "3. 填写新数据库信息（程序会自动创建数据库并导入 database.sql）\n";
            $readme_text .= "4. 恢复完成后 删除 restore.php 文件 访问网站首页即可 \n\n";
            $readme_text .= "注意：若新环境的域名或目录发生变化 请检查 settings 表中的相关 URL 配置\n";
            file_put_contents($tmp_dir . '/README.txt', $readme_text);

            $zip = new ZipArchive();
            if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                error_log('[自动备份] 创建压缩包失败');
                @ytj_rrmdir($tmp_dir);
                return false;
            }

            try {
                $zip->addFile($tmp_dir . '/database.sql', 'database.sql');
                $zip->addFile($tmp_dir . '/README.txt', 'README.txt');

                $uploads_dir = realpath(__DIR__ . '/../uploads');
                if ($uploads_dir && is_dir($uploads_dir)) {
                    ytj_add_dir_to_zip($zip, $uploads_dir, strlen($uploads_dir), 'uploads');
                }

                $root_dir = realpath(__DIR__ . '/..');

                $exclude_names = ['db.config.php', 'install.lock', 'a.zip', 'a_modified.zip', 'friendlink_modified.zip', 'restore.php'];
                $exclude_dirs  = ['backups', '.sources', 'skills', '.git'];
                if ($root_dir && is_dir($root_dir)) {
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($root_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::SELF_FIRST
                    );
                    foreach ($iterator as $file) {
                        $real_path = $file->getRealPath();
                        if ($real_path === false) continue;
                        $relative = substr($real_path, strlen($root_dir));
                        $relative = ltrim(str_replace('\\', '/', $relative), '/');
                        $basename = basename($relative);
                        if (in_array($basename, $exclude_names, true)) continue;
                        if (in_array($relative, $exclude_names, true)) continue;
                        if (strpos($relative, 'a_modified.zip') !== false) continue;
                        if (strpos($relative, 'friendlink_modified.zip') !== false) continue;
                        $top = explode('/', $relative)[0];
                        if (in_array($top, $exclude_dirs, true)) continue;
                        if ($file->isDir()) {
                            $zip->addEmptyDir($relative);
                        } else {
                            $zip->addFile($real_path, $relative);
                        }
                    }
                }

                $restore_file = __DIR__ . '/../restore.php';
                if (file_exists($restore_file)) {
                    $zip->addFile($restore_file, 'restore.php');
                } elseif (isset($restore_embed_source) && $restore_embed_source !== '') {
                    $temp_restore = $tmp_dir . '/restore.php';
                    file_put_contents($temp_restore, base64_decode($restore_embed_source));
                    $zip->addFile($temp_restore, 'restore.php');
                }

                $zip->close();
            } catch (Throwable $e) {
                $zip->close();
                @unlink($zip_file);
                @ytj_rrmdir($tmp_dir);
                error_log('[自动备份] 打包失败：' . $e->getMessage());
                return false;
            }

            @ytj_rrmdir($tmp_dir);

            if (!file_exists($zip_file) || filesize($zip_file) < 100) {
                @unlink($zip_file);
                error_log('[自动备份] 生成的备份包无效');
                return false;
            }

            return $zip_file;
        }
    }

    if (!function_exists('ytj_upload_backup_ftp')) {
        function ytj_upload_backup_ftp($file, $cfg) {
            if (!function_exists('ftp_connect')) {
                return [false, '服务器未启用 FTP 扩展(ftp)，无法使用 FTP 上传'];
            }
            $host = $cfg['host'] ?? '';
            $port = intval($cfg['port'] ?? 21);
            $user = $cfg['user'] ?? '';
            $pass = $cfg['pass'] ?? '';
            $remote_dir = rtrim($cfg['dir'] ?? '/', '/');
            if ($port < 1) $port = 21;

            $conn = @ftp_connect($host, $port, 15);
            if (!$conn) return [false, '无法连接 FTP 服务器'];
            if (!@ftp_login($conn, $user, $pass)) {
                ftp_close($conn);
                return [false, 'FTP 登录失败，请检查用户名/密码'];
            }
            @ftp_pasv($conn, true);

            $parts = array_filter(explode('/', $remote_dir), 'strlen');
            $cur = '';
            foreach ($parts as $p) {
                $cur .= '/' . $p;
                if (!@ftp_chdir($conn, $cur)) {
                    @ftp_mkdir($conn, $cur);
                    @ftp_chdir($conn, $cur);
                }
            }
            $remote_file = rtrim($remote_dir, '/') . '/' . basename($file);
            $ok = @ftp_put($conn, $remote_file, $file, FTP_BINARY);
            ftp_close($conn);
            return $ok ? [true, ''] : [false, 'FTP 上传失败（请确认远程目录可写）'];
        }
    }

    if (!function_exists('ytj_upload_backup_sftp')) {
        function ytj_upload_backup_sftp($file, $cfg) {
            if (!function_exists('ssh2_connect')) {
                return [false, '服务器未启用 SSH2 扩展(ssh2)，无法使用 SFTP 上传'];
            }
            $host = $cfg['host'] ?? '';
            $port = intval($cfg['port'] ?? 22);
            $user = $cfg['user'] ?? '';
            $pass = $cfg['pass'] ?? '';
            $remote_dir = rtrim($cfg['dir'] ?? '/', '/');
            if ($port < 1) $port = 22;

            $conn = @ssh2_connect($host, $port);
            if (!$conn) return [false, '无法连接 SFTP 服务器'];
            if (!@ssh2_auth_password($conn, $user, $pass)) {
                return [false, 'SFTP 登录失败，请检查用户名/密码'];
            }
            $sftp = @ssh2_sftp($conn);
            if ($sftp) {
                $parts = array_filter(explode('/', $remote_dir), 'strlen');
                $cur = '';
                foreach ($parts as $p) {
                    $cur .= '/' . $p;
                    @ssh2_sftp_mkdir($sftp, $cur, 0755, true);
                }
            }
            $remote_file = rtrim($remote_dir, '/') . '/' . basename($file);
            $ok = @ssh2_scp_send($conn, $file, $remote_file, 0644);
            return $ok ? [true, ''] : [false, 'SFTP 上传失败（请确认远程目录可写）'];
        }
    }

    if (!function_exists('ytj_send_email_with_attachment')) {
        function ytj_send_email_with_attachment($to, $subject, $body_html, $file) {
            if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return [false, '收件邮箱格式不正确'];
            }
            $settings = get_all_settings();
            $attach = !empty($file) && file_exists($file);
            $max_bytes = 25 * 1024 * 1024;
            if ($attach && filesize($file) > $max_bytes) {
                $attach = false;
                $size_mb = round(filesize($file) / 1024 / 1024, 1);
                $body_html .= '<p style="color:#e58a00;">提示：备份包大小为 ' . $size_mb . 'MB，已超过邮件附件上限(25MB) 未随信发送 请通过 FTP/SFTP 或服务器本地 /backups/auto/ 目录获取备份包</p>';
            }

            $smtp = null;
            if (!empty($settings['auto_backup_smtp_host']) && !empty($settings['auto_backup_smtp_user']) && !empty($settings['auto_backup_smtp_pass'])) {
                $smtp = ['host' => $settings['auto_backup_smtp_host'], 'port' => intval($settings['auto_backup_smtp_port'] ?: 465), 'user' => $settings['auto_backup_smtp_user'], 'pass' => $settings['auto_backup_smtp_pass'], 'secure' => $settings['auto_backup_smtp_secure'] ?? 'ssl', 'from' => $settings['auto_backup_smtp_from'] ?? ''];
            }

            if ($smtp) {
                return ytj_smtp_send_raw($to, $subject, $body_html, $attach ? $file : null, $smtp, $settings);
            }
            return [false, '未配置自动备份专用 SMTP 无法发送邮件 请在「自动备份 - 邮箱发送」中单独配置 SMTP'];
        }
    }

    if (!function_exists('ytj_smtp_send_attachment')) {
        function ytj_smtp_send_attachment($to, $subject, $body_html, $file, $settings) {
            $host = $settings['smtp_host'] ?? '';
            $port = intval($settings['smtp_port'] ?? 465);
            $user = $settings['smtp_user'] ?? '';
            $pass = $settings['smtp_pass'] ?? '';
            if (!$host || !$user || !$pass) return [false, 'SMTP 配置不完整'];

            $fromName = $settings['site_name'] ?? '云汀记';
            $isSsl = ($port == 465);
            $boundary = 'ytj' . md5(uniqid('', true));
            $fromName_enc = '=?utf-8?B?' . base64_encode($fromName) . '?=';
            $subject_enc = '=?utf-8?B?' . base64_encode($subject) . '?=';

            $header = "MIME-Version: 1.0\r\n";
            $header .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
            $header .= "From: {$fromName_enc} <{$user}>\r\n";
            $header .= "To: {$to}\r\n";
            $header .= "Subject: {$subject_enc}\r\n";
            $header .= "Date: " . date('r') . "\r\n";
            $header .= "\r\n";

            $body = "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=utf-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($body_html)) . "\r\n";

            if ($file) {
                $filename = basename($file);
                $fname_enc = '=?utf-8?B?' . base64_encode($filename) . '?=';
                $data = file_get_contents($file);
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Type: application/zip; name=\"{$fname_enc}\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n";
                $body .= "Content-Disposition: attachment; filename=\"{$fname_enc}\"\r\n\r\n";
                $body .= chunk_split(base64_encode($data)) . "\r\n";
            }
            $body .= "--{$boundary}--\r\n";

            $fullBody = $header . $body;

            $errno = 0; $errstr = '';
            $addr = $isSsl ? 'ssl://' . $host : $host;
            $socket = @fsockopen($addr, $port, $errno, $errstr, 15);
            if (!$socket) return [false, '无法连接 SMTP 服务器'];
            stream_set_timeout($socket, 30);

            $readResp = function ($expectPrefix) use ($socket) {
                $resp = fgets($socket, 512);
                return $resp !== false && strpos($resp, $expectPrefix) === 0;
            };

            if (!$readResp('220')) { fclose($socket); return [false, 'SMTP 握手失败']; }
            $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
            fwrite($socket, "EHLO {$domain}\r\n");
            while ($line = fgets($socket, 512)) { if (strpos($line, '250 ') === 0) break; }
            fwrite($socket, "AUTH LOGIN\r\n");
            if (!$readResp('334')) { fclose($socket); return [false, 'SMTP 认证失败']; }
            fwrite($socket, base64_encode($user) . "\r\n");
            if (!$readResp('334')) { fclose($socket); return [false, 'SMTP 认证失败']; }
            fwrite($socket, base64_encode($pass) . "\r\n");
            if (!$readResp('235')) { fclose($socket); return [false, 'SMTP 账号或密码错误']; }
            fwrite($socket, "MAIL FROM:<{$user}>\r\n");
            if (!$readResp('250')) { fclose($socket); return [false, 'SMTP MAIL FROM 失败']; }
            fwrite($socket, "RCPT TO:<{$to}>\r\n");
            if (!$readResp('250')) { fclose($socket); return [false, 'SMTP 收件人被拒绝']; }
            fwrite($socket, "DATA\r\n");
            if (!$readResp('354')) { fclose($socket); return [false, 'SMTP DATA 失败']; }
            fwrite($socket, $fullBody . "\r\n.\r\n");
            if (!$readResp('250')) { fclose($socket); return [false, 'SMTP 发送失败']; }
            fwrite($socket, "QUIT\r\n");
            fclose($socket);
            return [true, ''];
        }
    }

    if (!function_exists('ytj_smtp_send_raw')) {
        function ytj_smtp_send_raw($to, $subject, $body_html, $file, $smtp, $settings) {
            $host = $smtp['host'] ?? '';
            $port = intval($smtp['port'] ?: 465);
            $user = $smtp['user'] ?? '';
            $pass = $smtp['pass'] ?? '';
            if (!$host || !$user || !$pass) return [false, 'SMTP 配置不完整'];

            $fromName = $settings['auto_backup_email_from'] ?: ($settings['site_name'] ?? '云汀记');
            $secure = strtolower($smtp['secure'] ?? 'ssl');
            $isSsl = ($secure === 'ssl');
            $isTls = ($secure === 'tls');
            $boundary = 'ytj' . md5(uniqid('', true));
            $fromName_enc = '=?utf-8?B?' . base64_encode($fromName) . '?=';
            $subject_enc = '=?utf-8?B?' . base64_encode($subject) . '?=';

            $header = "MIME-Version: 1.0\r\n";
            $header .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
            $header .= "From: {$fromName_enc} <{$user}>\r\n";
            $header .= "To: {$to}\r\n";
            $header .= "Subject: {$subject_enc}\r\n";
            $header .= "Date: " . date('r') . "\r\n";
            $header .= "\r\n";

            $body = "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=utf-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($body_html)) . "\r\n";

            if ($file) {
                $filename = basename($file);
                $fname_enc = '=?utf-8?B?' . base64_encode($filename) . '?=';
                $data = file_get_contents($file);
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Type: application/zip; name=\"{$fname_enc}\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n";
                $body .= "Content-Disposition: attachment; filename=\"{$fname_enc}\"\r\n\r\n";
                $body .= chunk_split(base64_encode($data)) . "\r\n";
            }
            $body .= "--{$boundary}--\r\n";

            $fullBody = $header . $body;

            $errno = 0; $errstr = '';
            $addr = $isSsl ? 'ssl://' . $host : $host;
            $socket = @fsockopen($addr, $port, $errno, $errstr, 15);
            if (!$socket) return [false, '无法连接 SMTP 服务器'];
            stream_set_timeout($socket, 30);

            $readResp = function ($expectPrefix) use ($socket) {
                $resp = fgets($socket, 512);
                return $resp !== false && strpos($resp, $expectPrefix) === 0;
            };

            if (!$readResp('220')) { fclose($socket); return [false, 'SMTP 握手失败']; }
            $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
            fwrite($socket, "EHLO {$domain}\r\n");
            while ($line = fgets($socket, 512)) { if (strpos($line, '250 ') === 0) break; }
            if ($isTls) {
                fwrite($socket, "STARTTLS\r\n");
                if (!$readResp('220')) { fclose($socket); return [false, 'SMTP STARTTLS 失败']; }
                if (!function_exists('stream_socket_enable_crypto') || !@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    fclose($socket); return [false, 'SMTP TLS 加密启动失败'];
                }
                fwrite($socket, "EHLO {$domain}\r\n");
                while ($line = fgets($socket, 512)) { if (strpos($line, '250 ') === 0) break; }
            }
            fwrite($socket, "AUTH LOGIN\r\n");
            if (!$readResp('334')) { fclose($socket); return [false, 'SMTP 认证失败']; }
            fwrite($socket, base64_encode($user) . "\r\n");
            if (!$readResp('334')) { fclose($socket); return [false, 'SMTP 认证失败']; }
            fwrite($socket, base64_encode($pass) . "\r\n");
            if (!$readResp('235')) { fclose($socket); return [false, 'SMTP 账号或密码错误']; }
            fwrite($socket, "MAIL FROM:<{$user}>\r\n");
            if (!$readResp('250')) { fclose($socket); return [false, 'SMTP MAIL FROM 失败']; }
            fwrite($socket, "RCPT TO:<{$to}>\r\n");
            if (!$readResp('250')) { fclose($socket); return [false, 'SMTP 收件人被拒绝']; }
            fwrite($socket, "DATA\r\n");
            if (!$readResp('354')) { fclose($socket); return [false, 'SMTP DATA 失败']; }
            fwrite($socket, $fullBody . "\r\n.\r\n");
            if (!$readResp('250')) { fclose($socket); return [false, 'SMTP 发送失败']; }
            fwrite($socket, "QUIT\r\n");
            fclose($socket);
            return [true, ''];
        }
    }

    if (!function_exists('ytj_ensure_dir_protected')) {
        function ytj_ensure_dir_protected($dir) {
            if (!is_dir($dir)) return;
            $ht = $dir . '/.htaccess';
            if (file_exists($ht)) return;
            @file_put_contents($ht, "Order Deny,Allow\nDeny from all\n<Files ~ \"\\.(htaccess|htpasswd)$\">\nOrder Allow,Deny\nDeny from all\n</Files>\n");
        }
    }

    if (!function_exists('ytj_mail_send_attachment')) {
        function ytj_mail_send_attachment($to, $subject, $body_html, $file, $settings) {
            $fromName = $settings['site_name'] ?? '云汀记';
            $fromEmail = $settings['smtp_user'] ?? '';
            $boundary = 'ytj' . md5(uniqid('', true));
            $subject_enc = '=?utf-8?B?' . base64_encode($subject) . '?=';

            $headers = "MIME-Version: 1.0\r\n";
            if ($fromEmail) {
                $fromName_enc = '=?utf-8?B?' . base64_encode($fromName) . '?=';
                $headers .= "From: {$fromName_enc} <{$fromEmail}>\r\n";
            }
            $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

            $body = "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=utf-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($body_html)) . "\r\n";

            if ($file) {
                $filename = basename($file);
                $fname_enc = '=?utf-8?B?' . base64_encode($filename) . '?=';
                $data = file_get_contents($file);
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Type: application/zip; name=\"{$fname_enc}\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n";
                $body .= "Content-Disposition: attachment; filename=\"{$fname_enc}\"\r\n\r\n";
                $body .= chunk_split(base64_encode($data)) . "\r\n";
            }
            $body .= "--{$boundary}--\r\n";

            $ok = @mail($to, $subject_enc, $body, $headers);
            return $ok ? [true, ''] : [false, 'mail() 发送失败 请检查服务器邮件配置'];
        }
    }

    if (!function_exists('ytj_local_cleanup')) {
        function ytj_local_cleanup($dir, $keep) {
            $files = glob($dir . '/*.zip');
            if (!$files) return;
            usort($files, function ($a, $b) { return filemtime($a) - filemtime($b); });
            if (count($files) > $keep) {
                $del = array_slice($files, 0, count($files) - $keep);
                foreach ($del as $f) @unlink($f);
            }
        }
    }

    if (!function_exists('ytj_local_cleanup_days')) {
        function ytj_local_cleanup_days($dir, $days) {
            $files = glob($dir . '/*.zip');
            if (!$files) return;
            $threshold = time() - intval($days) * 86400;
            foreach ($files as $f) {
                if (@filemtime($f) < $threshold) @unlink($f);
            }
        }
    }

    if (!function_exists('ytj_auto_backup_record')) {
        function ytj_auto_backup_record($ok, $msg) {
            set_setting('auto_backup_last_time', (string)time());
            set_setting('auto_backup_last_status', $ok ? 'success' : 'fail');
            set_setting('auto_backup_last_msg', $msg);
        }
    }

    if (!function_exists('ytj_run_auto_backup')) {
        function ytj_run_auto_backup() {
            global $conn;
            if (!$conn) return ['ok' => false, 'msg' => '数据库未连接 无法执行自动备份'];

            $settings = get_all_settings();
            if (($settings['auto_backup_enabled'] ?? '0') !== '1') {
                return ['ok' => false, 'msg' => '自动备份未启用'];
            }

            $auto_dir = __DIR__ . '/../backups/auto';
            if (!is_dir($auto_dir)) @mkdir($auto_dir, 0755, true);
            ytj_ensure_dir_protected($auto_dir);

            $site_name = preg_replace('/[^\w\x{4e00}-\x{9fa5}_-]/u', '', $settings['site_name'] ?? 'backup');
            $filename_base = $site_name . '_auto_' . date('Ymd_His');

            $zip = ytj_create_site_backup($conn, $settings, $auto_dir, $filename_base);
            if (!$zip || !file_exists($zip)) {
                ytj_auto_backup_record(false, '生成备份包失败 请检查磁盘空间与 ZipArchive 支持');
                return ['ok' => false, 'msg' => '生成备份包失败 请检查磁盘空间与 ZipArchive 支持'];
            }

            $results = [];
            $local_enabled = ($settings['auto_backup_local_enabled'] ?? '1') === '1';

            if (($settings['auto_backup_ftp_enabled'] ?? '0') === '1') {
                $cfg = [
                    'host' => $settings['auto_backup_ftp_host'] ?? '',
                    'port' => $settings['auto_backup_ftp_port'] ?? '',
                    'user' => $settings['auto_backup_ftp_user'] ?? '',
                    'pass' => $settings['auto_backup_ftp_pass'] ?? '',
                    'dir'  => $settings['auto_backup_ftp_dir'] ?? '/',
                ];
                if (empty($cfg['host']) || empty($cfg['user'])) {
                    $results[] = 'FTP/SFTP 未配置服务器地址或用户名 已跳过';
                } elseif (!file_exists($zip)) {
                    $results[] = '无可用备份包用于远程上传';
                } else {
                    $is_sftp = ($settings['auto_backup_ftp_type'] ?? 'ftp') === 'sftp';
                    list($ok, $err) = $is_sftp
                        ? ytj_upload_backup_sftp($zip, $cfg)
                        : ytj_upload_backup_ftp($zip, $cfg);
                    $results[] = $ok
                        ? ('已通过 ' . ($is_sftp ? 'SFTP' : 'FTP') . ' 上传到远程服务器')
                        : (($is_sftp ? 'SFTP' : 'FTP') . ' 上传失败：' . $err);
                }
            }

            if (($settings['auto_backup_email_enabled'] ?? '0') === '1') {
                $to = trim($settings['auto_backup_email_to'] ?? '');
                if (empty($to)) {
                    $results[] = '未填写接收邮箱 已跳过邮件发送';
                } elseif (!file_exists($zip)) {
                    $results[] = '无可用备份包用于邮件发送';
                } else {
                    $subject = '【' . ($settings['site_name'] ?? '云汀记') . '】网站自动备份 ' . date('Y-m-d H:i');
                    $body = '你好，<br>你的网站「' . htmlspecialchars($settings['site_name'] ?? '云汀记') .
                        '」已完成一次自动备份 时间：' . date('Y-m-d H:i:s') . '。<br>';
                    if ($local_enabled) {
                        $body .= '备份包已保存在服务器本地目录 <code>/backups/auto/</code>';
                    }
                    list($ok, $err) = ytj_send_email_with_attachment($to, $subject, $body, $zip);
                    $results[] = $ok ? ('备份包已发送至邮箱 ' . htmlspecialchars($to)) : ('邮件发送失败：' . $err);
                }
            }

            if ($local_enabled) {
                $keep = intval($settings['auto_backup_keep'] ?? 30);
                if ($keep < 1) $keep = 30;
                ytj_local_cleanup($auto_dir, $keep);
                $keep_days = intval($settings['auto_backup_keep_days'] ?? 0);
                if ($keep_days > 0) ytj_local_cleanup_days($auto_dir, $keep_days);
                $results[] = '本地已保留备份包（/backups/auto/）';
            } else {
                @unlink($zip);
            }

            $msg = implode('；', $results);
            $all_ok = strpos($msg, '失败') === false && strpos($msg, '跳过') === false;
            ytj_auto_backup_record($all_ok, $msg);
            return ['ok' => $all_ok, 'msg' => $msg];
        }
    }

    if (!function_exists('ytj_maybe_auto_backup')) {
        function ytj_maybe_auto_backup() {
            $lock_file = sys_get_temp_dir() . '/ytj_autobackup.lock';
            $fp = @fopen($lock_file, 'c');
            if (!$fp) return;
            if (!flock($fp, LOCK_EX | LOCK_NB)) { fclose($fp); return; }

            try {
                $settings = get_all_settings();
                if (($settings['auto_backup_enabled'] ?? '0') !== '1') return;

                $freq = $settings['auto_backup_frequency'] ?? 'daily';
                $interval = ($freq === 'weekly') ? 7 * 86400 : (($freq === 'every3') ? 3 * 86400 : 86400);
                $last = intval($settings['auto_backup_last_time'] ?? 0);

                if ($last > 0 && (time() - $last) < $interval) return;

                ytj_run_auto_backup();
            } catch (Throwable $e) {
                error_log('[自动备份] 被动触发异常：' . $e->getMessage());
            } finally {
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }
    }
}
