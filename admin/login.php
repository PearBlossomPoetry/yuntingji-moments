<?php
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (!file_exists(__DIR__ . '/../install.lock')) {
    header('Location: ../install/');
    exit;
}
require_once '../config.php';
session_start();

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$loginKey = 'login_attempts_' . md5($ip);
$maxAttempts = 5;
$lockoutTime = 900;

if (!isset($_SESSION[$loginKey])) {
    $_SESSION[$loginKey] = ['count' => 0, 'time' => time()];
}

$isLocked = false;
if ($_SESSION[$loginKey]['count'] >= $maxAttempts) {
    if (time() - $_SESSION[$loginKey]['time'] < $lockoutTime) {
        $isLocked = true;
    } else {
        $_SESSION[$loginKey] = ['count' => 0, 'time' => time()];
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLocked) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = '请求无效，请刷新页面重试';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $conn->prepare("SELECT id, username, password FROM admin WHERE username = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($admin = $result->fetch_assoc()) {
                if (password_verify($password, $admin['password'])) {
                    $_SESSION[$loginKey] = ['count' => 0, 'time' => time()];
                    session_regenerate_id(true);

                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];

                    header('Location: index.php');
                    exit;
                } else {
                    $error = '账号或密码错误';
                    $_SESSION[$loginKey]['count']++;
                    $_SESSION[$loginKey]['time'] = time();
                }
            } else {
                $error = '账号或密码错误';
                $_SESSION[$loginKey]['count']++;
                $_SESSION[$loginKey]['time'] = time();
            }
            $stmt->close();
        } else {
            $error = '系统错误，请稍后重试';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>登录 - 云汀记 · 朋友圈程序</title>
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
    padding: 48px 24px 60px;
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
.panel {
    background: #fff;
    border-radius: 24px;
    padding: 36px 24px 40px;
    margin: -24px 16px 24px 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    width: auto;
}
.content-wrap {
    width: 100%;
    max-width: 360px;
}
.step-title {
    font-size: 18px;
    font-weight: 700;
    color: #1c1c1e;
    margin-bottom: 24px;
    text-align: center;
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
.form-group {
    margin-bottom: 20px;
    text-align: left;
    position: relative;
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
    padding: 14px 44px 14px 16px;
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
.form-input:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.password-toggle {
    position: absolute;
    right: 14px;
    bottom: 14px;
    width: 20px;
    height: 20px;
    cursor: pointer;
    background: none;
    border: none;
    padding: 0;
    color: #c7c7cc;
    display: flex;
    align-items: center;
    justify-content: center;
}
.password-toggle:active {
    color: #1c1c1e;
}
.password-toggle svg {
    width: 18px;
    height: 18px;
    display: block;
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
.btn-primary:disabled {
    background: #c7c7cc;
    cursor: not-allowed;
    transform: none;
}
.copyright {
    text-align: center;
    font-size: 12px;
    color: #c7c7cc;
    margin-top: 24px;
}
.copyright a {
    color: #c7c7cc;
    text-decoration: none;
    transition: color 0.2s;
}
.copyright a:active {
    color: #1c1c1e;
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
    <div class="brand">后台登录</div>
</div>

<div class="panel">
    <div class="content-wrap">

        <?php if ($isLocked): ?>
        <div class="alert">
            <strong style="color:#1c1c1e;">登录已锁定</strong><br>
            失败次数过多，请15分钟后再试
        </div>
        <?php elseif ($error): ?>
        <div class="alert">
            <strong style="color:#1c1c1e;">登录失败</strong><br>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="form-group">
                <label class="form-label">管理员账号</label>
                <input type="text" name="username" class="form-input" placeholder="请输入账号" required <?php echo $isLocked ? 'disabled' : ''; ?>>
            </div>

            <div class="form-group">
                <label class="form-label">管理员密码</label>
                <input type="password" name="password" id="password" class="form-input" placeholder="请输入密码" required <?php echo $isLocked ? 'disabled' : ''; ?>>
                <button type="button" class="password-toggle" onclick="togglePassword()" <?php echo $isLocked ? 'disabled' : ''; ?>>
                    <svg id="eyeOpen" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg id="eyeClosed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                </button>
            </div>

            <button type="submit" class="btn btn-primary" <?php echo $isLocked ? 'disabled' : ''; ?>>登 录</button>
        </form>

        <p class="copyright">2025 © <a href="https://imoe.top" target="_blank">云汀记</a></p>
    </div>
</div>

<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const eyeOpen = document.getElementById('eyeOpen');
    const eyeClosed = document.getElementById('eyeClosed');

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeOpen.style.display = 'none';
        eyeClosed.style.display = 'block';
    } else {
        passwordInput.type = 'password';
        eyeOpen.style.display = 'block';
        eyeClosed.style.display = 'none';
    }
}
</script>
</body>
</html>
