<?php
error_reporting(0);
ini_set('display_errors', 0);

if (file_exists(__DIR__ . '/../install.lock')) {
    header('Location: ../index.php');
    exit;
}

session_start();
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        $_SESSION['db_host'] = $_POST['db_host'] ?? 'localhost';
        $_SESSION['db_user'] = $_POST['db_user'] ?? '';
        $_SESSION['db_pass'] = $_POST['db_pass'] ?? '';
        $_SESSION['db_name'] = $_POST['db_name'] ?? '';

        $test = false;
        $db_err = '';
        try {
            $test = @mysqli_connect($_SESSION['db_host'], $_SESSION['db_user'], $_SESSION['db_pass']);
        } catch (Throwable $e) {
            $test = false;
            $db_err = $e->getMessage();
        }
        if (!$test) {
            $error = '数据库连接失败 请检查数据库地址 用户名和密码是否正确填写 ' . ($db_err ? ' (' . $db_err . ')' : '');
        } else {
            $db_name_escaped = str_replace('`', '``', $_SESSION['db_name']);
            mysqli_query($test, "CREATE DATABASE IF NOT EXISTS `{$db_name_escaped}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $db_error = mysqli_error($test);

            if (!mysqli_select_db($test, $_SESSION['db_name'])) {
                $error = '无法选择数据库：' . $db_error;
            } else {
                $sql_file = __DIR__ . '/install.sql';
                if (!file_exists($sql_file)) {
                    $error = 'install.sql 文件不存在';
                } else {
                    $sql = file_get_contents($sql_file);
                    if ($sql === false) {
                        $error = '无法读取 install.sql 文件';
                    } else {
                        $sql_err = '';
                        try {
                            if (mysqli_multi_query($test, $sql)) {
                                do {
                                    if ($result = mysqli_store_result($test)) mysqli_free_result($result);
                                } while (mysqli_more_results($test) && mysqli_next_result($test));
                            }
                            $sql_err = mysqli_error($test);
                            while (mysqli_more_results($test)) { @mysqli_next_result($test); }
                        } catch (Throwable $e) {
                            $sql_err = $e->getMessage();
                        }

                        if ($sql_err) {
                            $error = 'SQL导入失败：' . $sql_err;
                        } else {
                            $config_content = "<?php\n";
                            $config_content .= "/**\n * 数据库配置文件（由安装程序自动生成）\n */\n";
                            $config_content .= "define('DB_HOST', '" . addslashes($_SESSION['db_host']) . "');\n";
                            $config_content .= "define('DB_USER', '" . addslashes($_SESSION['db_user']) . "');\n";
                            $config_content .= "define('DB_PASS', '" . addslashes($_SESSION['db_pass']) . "');\n";
                            $config_content .= "define('DB_NAME', '" . addslashes($_SESSION['db_name']) . "');\n";

                            $config_file = __DIR__ . '/../db.config.php';
                            if (file_put_contents($config_file, $config_content) === false) {
                                $error = '无法写入配置文件 db.config.php 请检查目录权限';
                            } else {
                                mysqli_close($test);
                                header('Location: ?step=2');
                                exit;
                            }
                        }
                    }
                }
            }
            if ($test) mysqli_close($test);
        }
    } elseif ($step === 2) {
        if (!file_exists(__DIR__ . '/../db.config.php')) {
            $error = '配置文件不存在 请返回步骤1重新安装';
        } else {
            require_once __DIR__ . '/../db.config.php';
            $conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if (!$conn) {
                $error = '数据库连接失败 请返回步骤1检查数据库配置是否正确';
            } else {
                mysqli_set_charset($conn, 'utf8mb4');

                if (isset($_POST['clear_old_db']) && $_POST['clear_old_db'] === '1') {
                    $tables = ['posts', 'comments', 'likes', 'ads', 'admin', 'settings', 'music', 'tags', 'post_tags', 'banned_words', 'banned_ips', 'articles', 'pages', 'visitor_logs'];
                    foreach ($tables as $table) {
                        mysqli_query($conn, "DROP TABLE IF EXISTS `$table`");
                    }
                    $sql_file = __DIR__ . '/install.sql';
                    if (file_exists($sql_file)) {
                        $sql = file_get_contents($sql_file);
                        if ($sql !== false) {
                            try {
                                if (mysqli_multi_query($conn, $sql)) {
                                    do {
                                        if ($result = mysqli_store_result($conn)) mysqli_free_result($result);
                                    } while (mysqli_more_results($conn) && mysqli_next_result($conn));
                                }
                                while (mysqli_more_results($conn)) { mysqli_next_result($conn); }
                            } catch (Throwable $e) {
                                $error = 'SQL导入失败：' . $e->getMessage();
                            }
                        }
                    }
                }

                $username = mysqli_real_escape_string($conn, $_POST['username'] ?? 'admin');
                $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);

                mysqli_query($conn, "TRUNCATE TABLE admin");
                mysqli_query($conn, "INSERT INTO admin (username, password) VALUES ('$username', '$password')");

                if (isset($_POST['clear_old_db']) && $_POST['clear_old_db'] === '1') {
                    mysqli_query($conn, "UPDATE settings SET setting_value = '云汀记' WHERE setting_key = 'nickname'");
                    mysqli_query($conn, "UPDATE settings SET setting_value = '欢迎使用云汀记系统' WHERE setting_key = 'signature'");
                    mysqli_query($conn, "UPDATE settings SET setting_value = 'http://q.qlogo.cn/headimg_dl?dst_uin=1239399954&spec=640&img_type=jpg' WHERE setting_key = 'avatar'");
                    mysqli_query($conn, "UPDATE settings SET setting_value = 'assets/image/bj.png' WHERE setting_key = 'cover_image'");
                    mysqli_query($conn, "UPDATE settings SET setting_value = '12' WHERE setting_key = 'page_size'");
                }

                file_put_contents(__DIR__ . '/../install.lock', 'installed at ' . date('Y-m-d H:i:s'));
                session_destroy();

                header('Location: ?step=3');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>安装向导 - 云汀记 · 朋友圈程序</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
html, body {
    min-height: 100%;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    background: #000;
    -webkit-tap-highlight-color: transparent;
}
body {
    display: flex;
    flex-direction: column;
}
.hero {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 24px 60px;
    position: relative;
}
.hero::before {
    content: '';
    position: absolute;
    width: 320px;
    height: 320px;
    background: radial-gradient(circle, rgba(255,255,255,0.025) 0%, transparent 70%);
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}
.icon-wrap {
    position: relative;
    z-index: 1;
    margin-bottom: 24px;
    opacity: 0.85;
    color: #fff;
}
.icon-wrap svg {
    width: 44px;
    height: 44px;
    display: block;
}
.brand {
    position: relative;
    z-index: 1;
    font-size: 34px;
    font-weight: 700;
    color: #fff;
    letter-spacing: -0.6px;
}
.subtitle {
    position: relative;
    z-index: 1;
    margin-top: 4px;
    font-size: 14px;
    color: rgba(255,255,255,0.35);
    font-weight: 400;
}
.version {
    position: relative;
    z-index: 1;
    margin-top: 12px;
    font-size: 13px;
    color: rgba(255,255,255,0.3);
    font-weight: 500;
    letter-spacing: 0.5px;
}
.official-site {
    position: relative;
    z-index: 1;
    margin-top: 6px;
}
.official-site a {
    font-size: 12px;
    color: rgba(255,255,255,0.25);
    text-decoration: none;
    background: none;
    transition: color 0.2s;
}
.official-site a:active {
    color: rgba(255,255,255,0.5);
}
.panel {
    background: #fff;
    border-radius: 24px;
    padding: 32px 24px 40px;
    margin: -24px 16px 24px 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    width: auto;
}
.content-wrap {
    width: 100%;
    max-width: 420px;
}
.step-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 32px;
    gap: 0;
}
.step-dot {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    color: #d2d2d7;
    background: #f2f2f7;
    transition: all 0.3s;
    flex-shrink: 0;
}
.step-dot.active {
    background: #000;
    color: #fff;
}
.step-line {
    width: 40px;
    height: 2px;
    background: #f2f2f7;
    margin: 0 8px;
    position: relative;
}
.step-line::after {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 0;
    background: #000;
    transition: width 0.4s;
}
.step-line.filled::after {
    width: 100%;
}
.form-group {
    margin-bottom: 20px;
    text-align: left;
}
.form-label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #1c1c1e;
    margin-bottom: 8px;
}
.form-input {
    width: 100%;
    padding: 14px 16px;
    border: 1px solid #e5e5ea;
    border-radius: 12px;
    font-size: 15px;
    color: #1c1c1e;
    background: #fafafa;
    outline: none;
    transition: border-color 0.2s;
    font-family: inherit;
}
.form-input:focus {
    border-color: #000;
    background: #fff;
}
.form-input::placeholder {
    color: #c7c7cc;
}
.btn {
    display: block;
    width: 100%;
    padding: 16px 0;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    transition: transform 0.1s;
    font-family: inherit;
}
.btn:active {
    transform: scale(0.97);
}
.btn-primary {
    background: #000;
    color: #fff;
}
.btn-outline {
    background: #fff;
    color: #1c1c1e;
    border: 1px solid #e5e5ea;
}
.btn-outline:active {
    background: #f5f5f7;
}
.btn-group {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}
.alert {
    padding: 14px 16px;
    border-radius: 12px;
    font-size: 14px;
    line-height: 1.5;
    margin-bottom: 20px;
    text-align: left;
    background: #fafafa;
    color: #666;
    border: 1px solid #e5e5ea;
}
.text-center {
    text-align: center;
}
.mb-16 {
    margin-bottom: 16px;
}
.mt-24 {
    margin-top: 24px;
}
.mb-12 {
    margin-bottom: 12px;
}
.gap-12 {
    gap: 12px;
}
.success-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: #000;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    margin: 0 auto 24px;
}
.step-title {
    font-size: 18px;
    font-weight: 700;
    color: #1c1c1e;
    margin-bottom: 24px;
    text-align: center;
}
.choice-box {
    margin-bottom: 20px;
}
.form-box {
    display: none;
}
.form-box.active {
    display: block;
}
</style>
</head>
<body>
<div class="hero">
    <div class="icon-wrap">
        <svg width="44" height="44" viewBox="0 0 48 48" fill="none">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M24 44c11.046 0 20-8.954 20-20S35.046 4 24 4S4 12.954 4 24s8.954 20 20 20"/>
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M31 7v17"/>
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="m16.636 6.636l14.142 14.142"/>
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M7 17h17"/>
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M20.364 17.636L6.222 31.778"/>
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M17 25v17"/>
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="m17.636 27.636l14.142 14.142"/>
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M24 31h18"/>
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M42.364 16.636L28.222 30.778"/>
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M24 31a7 7 0 1 0 0-14a7 7 0 0 0 0 14"/>
        </svg>
    </div>
    <div class="brand">云汀记</div>
    <div class="subtitle">朋友圈程序</div>
    <div class="version">v1.5.0</div>
    <div class="official-site">
        <a href="https://imoe.top" target="_blank">官网：https://imoe.top</a>
    </div>
</div>

<div class="panel">
    <div class="content-wrap">
        <div class="step-indicator">
            <div class="step-dot <?php echo $step >= 1 ? 'active' : ''; ?>">1</div>
            <div class="step-line <?php echo $step >= 2 ? 'filled' : ''; ?>"></div>
            <div class="step-dot <?php echo $step >= 2 ? 'active' : ''; ?>">2</div>
            <div class="step-line <?php echo $step >= 3 ? 'filled' : ''; ?>"></div>
            <div class="step-dot <?php echo $step >= 3 ? 'active' : ''; ?>">3</div>
        </div>

        <?php if ($step === 1): ?>
            <div class="step-title">数据库配置</div>

            <?php if ($error): ?>
                <div class="alert"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST" action="?step=1">
                <div class="form-group">
                    <label class="form-label">数据库地址</label>
                    <input type="text" name="db_host" value="localhost" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">数据库用户名</label>
                    <input type="text" name="db_user" placeholder="auth：1239399954" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">数据库密码</label>
                    <input type="password" name="db_pass" placeholder="auth：1239399954" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">数据库名称</label>
                    <input type="text" name="db_name" placeholder="auth：1239399954" class="form-input" required>
                </div>
                <button type="submit" class="btn btn-primary mt-24">下一步</button>
            </form>

        <?php elseif ($step === 2): ?>
            <div class="step-title">设置管理员</div>

            <?php if ($error): ?>
                <div class="alert"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php
            $has_old_db = false;
            if (file_exists(__DIR__ . '/../db.config.php')) {
                require_once __DIR__ . '/../db.config.php';
                $check_conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                if ($check_conn) {
                    $check_result = mysqli_query($check_conn, "SHOW TABLES LIKE 'posts'");
                    if (!$check_result || mysqli_num_rows($check_result) === 0) {
                        $check_result = mysqli_query($check_conn, "SHOW TABLES LIKE 'articles'");
                    }
                    if (!$check_result || mysqli_num_rows($check_result) === 0) {
                        $check_result = mysqli_query($check_conn, "SHOW TABLES LIKE 'pages'");
                    }
                    if ($check_result && mysqli_num_rows($check_result) > 0) {
                        $has_old_db = true;
                    }
                    mysqli_close($check_conn);
                }
            }
            ?>
            <?php if ($has_old_db): ?>
                <div id="choiceArea">
                    <div class="alert">
                        <strong style="color:#1c1c1e;">检测到旧数据库</strong><br>
                        该数据库中已存在系统数据表，你可以选择清空旧数据重新安装，或保留旧数据继续安装
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline" onclick="showForm('clear')">清空旧数据</button>
                        <button type="button" class="btn btn-outline" onclick="showForm('keep')">保留旧数据</button>
                    </div>
                </div>

                <form method="POST" action="?step=2" id="clearDbForm" class="form-box">
                    <input type="hidden" name="clear_old_db" value="1">
                    <div class="alert mb-12">
                        <strong>警告：此操作将删除所有旧数据（动态、评论、广告、设置等）且不可恢复！</strong>
                    </div>
                    <div class="form-group">
                        <label class="form-label">管理员账号</label>
                        <input type="text" name="username" placeholder="auth：1239399954" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">管理员密码</label>
                        <input type="password" name="password" placeholder="QQ群：1134371175" class="form-input" required>
                    </div>
                    <div class="btn-group gap-12">
                        <button type="button" class="btn btn-outline" onclick="backToChoice()">返回</button>
                        <button type="submit" class="btn btn-primary">确认清空并安装</button>
                    </div>
                </form>

                <form method="POST" action="?step=2" id="keepDbForm" class="form-box">
                    <div class="alert mb-12">将保留所有旧数据，仅更新管理员账号</div>
                    <div class="form-group">
                        <label class="form-label">管理员账号</label>
                        <input type="text" name="username" placeholder="auth：1239399954" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">管理员密码</label>
                        <input type="password" name="password" placeholder="QQ群：1134371175" class="form-input" required>
                    </div>
                    <div class="btn-group gap-12">
                        <button type="button" class="btn btn-outline" onclick="backToChoice()">返回</button>
                        <button type="submit" class="btn btn-primary">保留数据并安装</button>
                    </div>
                </form>
            <?php else: ?>
                <form method="POST" action="?step=2">
                    <div class="form-group">
                        <label class="form-label">管理员账号</label>
                        <input type="text" name="username" placeholder="auth：1239399954" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">管理员密码</label>
                        <input type="password" name="password" placeholder="QQ群：1134371175" class="form-input" required>
                    </div>
                    <button type="submit" class="btn btn-primary mt-24">完成安装</button>
                </form>
            <?php endif; ?>
        <?php elseif ($step === 3): ?>
            <div class="text-center">
                <div class="success-icon">✓</div>
                <h3 class="mb-16" style="font-size:20px;font-weight:700;color:#1c1c1e;">安装成功</h3>
                <p style="color:#86868b;font-size:14px;">你已安装完成，请登录后台进行管理</p>
                <div class="mt-24">
                    <a href="../admin/login.php" class="btn btn-primary" style="display:inline-block;width:auto;padding:12px 40px;">进入后台</a>
                </div>
                <p style="color:#c7c7cc;font-size:12px;margin-top:16px;">建议删除 install 目录以保证安全</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function showForm(type) {
    document.getElementById('choiceArea').style.display = 'none';
    document.getElementById('clearDbForm').classList.remove('active');
    document.getElementById('keepDbForm').classList.remove('active');

    if (type === 'clear') {
        document.getElementById('clearDbForm').classList.add('active');
    } else {
        document.getElementById('keepDbForm').classList.add('active');
    }
}

function backToChoice() {
    document.getElementById('choiceArea').style.display = 'block';
    document.getElementById('clearDbForm').classList.remove('active');
    document.getElementById('keepDbForm').classList.remove('active');
}
</script>
</body>
</html>
