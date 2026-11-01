<?php
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
require_once '../config.php';
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$page_title = '邮箱提醒';
$page_id = 'email';

$settings = get_all_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $csrf_ok = false;
    if (!empty($_POST['csrf_token']) && !empty($_SESSION['csrf_token'])) {
        $csrf_ok = hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }
    if (!$csrf_ok) {
        $_SESSION['admin_toast'] = '安全令牌无效，请刷新页面重试';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    $keys = ['email_notify_enabled','smtp_host','smtp_port','smtp_user','smtp_pass','email_subject','email_template'];
    foreach ($keys as $k) {
        $val = ($k === 'email_notify_enabled') ? (($_POST[$k] ?? '0') === '1' ? '1' : '0') : ($_POST[$k] ?? '');
        $ke = mysqli_real_escape_string($conn, $k);
        $ve = mysqli_real_escape_string($conn, $val);
        mysqli_query($conn, "DELETE FROM settings WHERE setting_key='{$ke}'");
        mysqli_query($conn, "INSERT INTO settings (setting_key,setting_value) VALUES ('{$ke}','{$ve}')");
    }
    $_SESSION['admin_toast'] = '保存成功';
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$toast_msg = $_SESSION['admin_toast'] ?? '';
if ($toast_msg) {
    unset($_SESSION['admin_toast']);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title); ?> | <?php echo htmlspecialchars($settings['site_name'] ?? '云汀记'); ?></title>
    <script src="../assets/vendor/tailwind.js"></script>
    <link href="../assets/vendor/font-awesome/css/all.min.css" rel="stylesheet">
    <link href="../assets/vendor/fonts/inter.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter','sans-serif'] },
                    colors: {
                        primary: '#5b73e8',
                        'primary-light': '#eef2ff',
                        secondary: '#5b6b79',
                        success: '#2ca87f',
                        danger: '#dc2626',
                        warning: '#e58a00',
                        info: '#3ec9d6',
                        dark: '#1d2630',
                        body: '#f8f9fa'
                    }
                }
            }
        }
    </script>
    <style>
        * { -webkit-tap-highlight-color: transparent; }
        body { font-family: 'Inter', sans-serif; background: #f4f7fa; }
        .sidebar-scroll::-webkit-scrollbar { width: 4px; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 4px; }
        .nav-item {
            transition: all 0.2s ease;
            border-radius: 12px;
            margin: 2px 12px;
        }
        .nav-item:hover {
            background: rgba(91, 115, 232, 0.06);
            color: #5b73e8;
        }
        .nav-item.active {
            background: rgba(91, 115, 232, 0.1);
            color: #5b73e8;
        }
        .nav-item.active .nav-icon { color: #5b73e8; }
        .nav-icon {
            color: #9ca3af;
            transition: color 0.2s;
            width: 20px;
            text-align: center;
        }
        .nav-item:hover .nav-icon { color: #5b73e8; }
        .section-title {
            font-size: 11px;
            letter-spacing: 0.08em;
            color: #6b7280;
            font-weight: 700;
        }
        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.35s cubic-bezier(0.4,0,0.2,1);
        }
        .submenu.open { max-height: 600px; }
        .submenu-arrow {
            transition: transform 0.3s ease;
            font-size: 12px;
            color: #9ca3af;
        }
        .submenu-arrow.rotate {
            transform: rotate(90deg);
            color: #5b73e8;
        }
        .user-card {
            background: #f3f4f6;
            border-radius: 16px;
            transition: all 0.2s;
        }
        .user-card:hover { background: #e5e7eb; }

        .ap-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid #f0f0f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02), 0 4px 20px rgba(0,0,0,0.03);
            overflow: hidden;
        }
        .ap-card-header {
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #f8f8f8;
        }
        .ap-card-title {
            font-size: 17px;
            font-weight: 700;
            color: #1f2937;
            letter-spacing: -0.2px;
        }
        .ap-card-body { padding: 24px; }

        .ap-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            outline: none;
            text-decoration: none;
            line-height: 1;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .ap-btn:active { transform: scale(0.96); }
        .ap-btn-primary {
            background: linear-gradient(135deg, #5b73e8 0%, #4a62d7 100%);
            color: #fff;
            box-shadow: 0 3px 10px rgba(91,115,232,0.18);
        }
        .ap-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 14px rgba(91,115,232,0.25);
        }
        .ap-btn-sm { padding: 8px 16px; font-size: 13px; border-radius: 10px; }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; gap: 16px; }
        }

        .form-group {
            margin-bottom: 0;
        }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        .form-input {
            height: 40px;
            padding: 0 12px;
            border: 2px solid #f0f0f0;
            border-radius: 10px;
            font-size: 14px;
            color: #374151;
            outline: none;
            transition: all 0.2s;
            background: #fafafa;
            font-weight: 500;
            width: 100%;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        .form-input:focus {
            border-color: #5b73e8;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(91,115,232,0.08);
        }
        textarea.form-input {
            height: auto;
            min-height: 120px;
            padding: 12px;
            resize: vertical;
            font-family: 'Inter', monospace;
            font-size: 13px;
            line-height: 1.5;
        }
        select.form-input { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 32px; }
        .form-tips {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 6px;
            line-height: 1.4;
        }

        .section-divider {
            height: 1px;
            background: #f0f0f0;
            margin: 24px 0;
        }
        .section-title-inline {
            font-size: 13px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-title-inline::before {
            content: '';
            width: 4px;
            height: 16px;
            background: #5b73e8;
            border-radius: 2px;
        }

        .ap-toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            background: #1e293b;
            color: #fff;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 500;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            transform: translateX(120%);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4,0,0.2,1);
            pointer-events: none;
            max-width: min(90vw, 360px);
            word-break: break-word;
        }
        .ap-toast.show {
            transform: translateX(0);
            opacity: 1;
        }
        .ap-toast-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 11px;
        }
        .ap-toast-icon.success { background: #2ca87f; color: #fff; }
        .ap-toast-icon.error { background: #dc2626; color: #fff; }

        .app-footer {
            background: linear-gradient(to bottom, #ffffff, #f8fafc);
            border-top: 1px solid #e2e8f0;
        }
        .app-footer p {
            font-size: 13px;
            letter-spacing: 0.05em;
        }
        .app-footer a {
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .app-footer a:hover {
            opacity: 0.8;
            text-decoration: underline;
        }

        .fade-in { animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity:0; transform: translateY(8px); } to { opacity:1; transform: translateY(0); } }

        @media (max-width: 768px) {
            .ap-card-header { padding: 16px 20px; }
            .ap-card-body { padding: 20px; }
            .ap-btn { padding: 8px 14px; font-size: 13px; border-radius: 12px; }
            .section-divider { margin: 20px 0; }
        }
    </style>
</head>
<body class="text-gray-700 antialiased">

<aside id="sidebar" class="fixed left-0 top-0 h-full w-[280px] bg-white z-40 transition-transform duration-300 lg:translate-x-0 -translate-x-full shadow-xl lg:shadow-none border-r border-gray-100">
    <div class="h-16 flex items-center px-6 pt-2">
        <div class="flex items-center gap-2">
            <span class="font-extrabold text-2xl text-primary tracking-tight">云汀记</span>
            <span class="text-xs font-semibold text-primary -mt-2">朋友圈程序</span>
            <span class="ml-2 px-2 py-0.5 text-[10px] font-semibold bg-green-100 text-green-600 rounded-md">v1.5.0</span>
        </div>
    </div>
    <div class="px-4 mb-4">
        <div class="user-card flex items-center gap-3 px-4 py-3">
            <?php
            $avatar_raw = $settings['avatar'] ?? '';
            if ($avatar_raw && !preg_match('#^https?://#i', $avatar_raw) && $avatar_raw[0] !== '/') {
                $avatar_raw = '../' . $avatar_raw;
            }
            $avatar_src = $avatar_raw ?: 'https://ui-avatars.com/api/?name=' . urlencode($settings['site_name'] ?? '云汀记') . '&background=5b73e8&color=fff&size=128';
            ?>
            <img src="<?php echo htmlspecialchars($avatar_src); ?>" class="w-11 h-11 rounded-full" alt="User">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($settings['site_name'] ?? '云汀记'); ?></p>
                <p class="text-xs text-gray-500 mt-1"><a href="https://imoe.top" target="_blank" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-primary-light text-primary font-medium hover:bg-[#dbe4ff] transition-colors">官网 https://imoe.top</a></p>
            </div>
        </div>
    </div>
    <div class="h-[calc(100vh-160px)] overflow-y-auto sidebar-scroll pb-8">
        <?php
        $menu_groups = [
            '仪表盘' => [
                ['index', '后台首页', 'fa-home', false]
            ],
            '内容管理' => [
                ['posts', '动态管理', 'fa-stream', true],
                ['articles', '文章管理', 'fa-file-alt', true],
                ['pages', '独立页面', 'fa-file', true],
                ['tags', '标签管理', 'fa-tags', true]
            ],
            '互动交流' => [
                ['comments', '评论管理', 'fa-comments', true],
                ['page_comments', '留言管理', 'fa-comment-dots', true]
            ],
            '营销推广' => [
                ['ads', '广告管理', 'fa-bullhorn', false]
            ],
            '系统设置' => [
                ['settings', '前台设置', 'fa-cog', true],
                ['email', '邮箱提醒', 'fa-envelope', true],
                ['ban_manage', '违禁词与IP', 'fa-ban', true],
                ['uploads', '上传管理', 'fa-cloud-upload-alt', true],
                ['security', '安全管理', 'fa-shield-alt', true]
            ],
            '账户管理' => [
                ['profile', '个人设置', 'fa-user-cog', false],
                ['backup', '数据备份', 'fa-download', false],
                ['visitor_logs', '访客记录', 'fa-eye', false]
            ]
        ];
        foreach ($menu_groups as $group_name => $items):
            $has_active = false;
            foreach ($items as $item) { if ($item[0] === $page_id) $has_active = true; }
        ?>
        <div class="px-6 mt-2 mb-2">
            <span class="section-title font-bold uppercase"><?php echo $group_name; ?></span>
        </div>
        <ul class="space-y-0.5">
            <?php
            $first_item = $items[0];
            $group_has_sub = count($items) > 1;
            $group_id = $group_name . '_group';
            if ($group_has_sub):
            ?>
            <li>
                <div class="nav-item has-submenu <?php echo $has_active ? 'active' : ''; ?> flex items-center gap-3 px-4 py-3 text-sm font-medium cursor-pointer select-none <?php echo $has_active ? '' : 'text-gray-600'; ?>" onclick="toggleSubmenu('<?php echo $group_id; ?>', this)">
                    <i class="fas <?php echo $first_item[2]; ?> nav-icon"></i>
                    <span class="flex-1"><?php echo $group_name; ?></span>
                    <i class="fas fa-chevron-right submenu-arrow <?php echo $has_active ? 'rotate' : ''; ?>"></i>
                </div>
                <div id="<?php echo $group_id; ?>" class="submenu <?php echo $has_active ? 'open' : ''; ?>">
                    <ul class="py-1">
                        <?php foreach ($items as $item):
                            $id = $item[0];
                            $name = $item[1];
                            $is_active = ($id === $page_id);
                        ?>
                        <li>
                            <a href="<?php echo $id; ?>.php" class="nav-item <?php echo $is_active ? 'active' : ''; ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium <?php echo $is_active ? '' : 'text-gray-600'; ?>" data-page="<?php echo $id; ?>">
                                <span class="w-5 flex justify-center"><span class="w-1.5 h-1.5 rounded-full <?php echo $is_active ? 'bg-primary' : 'bg-gray-300'; ?>"></span></span>
                                <span><?php echo $name; ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </li>
            <?php else:
                $item = $items[0];
                $id = $item[0];
                $name = $item[1];
                $icon = $item[2];
                $is_active = ($id === $page_id);
            ?>
            <li>
                <a href="<?php echo $id; ?>.php" class="nav-item <?php echo $is_active ? 'active' : ''; ?> flex items-center gap-3 px-4 py-3 text-sm font-medium <?php echo $is_active ? '' : 'text-gray-600'; ?>" data-page="<?php echo $id; ?>">
                    <i class="fas <?php echo $icon; ?> nav-icon"></i>
                    <span class="flex-1"><?php echo $name; ?></span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
        <?php endforeach; ?>
        <?php
        $logout_active = ($page_id === 'logout');
        ?>
        <div class="px-6 mt-4 mb-2">
            <span class="section-title font-bold uppercase">退出</span>
        </div>
        <ul class="space-y-0.5">
            <li>
                <a href="logout.php" class="nav-item <?php echo $logout_active ? 'active' : ''; ?> flex items-center gap-3 px-4 py-3 text-sm font-medium <?php echo $logout_active ? '' : 'text-red-500'; ?>" data-page="logout">
                    <i class="fas fa-sign-out-alt nav-icon <?php echo $logout_active ? '' : 'text-red-400'; ?>"></i>
                    <span class="flex-1">退出登录</span>
                </a>
            </li>
        </ul>
    </div>
    <div class="absolute bottom-0 left-0 right-0 px-6 py-3 border-t border-gray-100 bg-white">
        <p class="text-[10px] text-gray-400 text-center">
            <a href="https://imoe.top" target="_blank" class="text-primary hover:underline font-medium">云汀记</a> &copy; 2025 朋友圈
        </p>
    </div>
</aside>

<div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-30 hidden lg:hidden" onclick="toggleSidebar()"></div>

<div class="lg:ml-[280px] min-h-screen flex flex-col">
    <header class="h-16 bg-white border-b border-gray-100 flex items-center justify-between px-4 lg:px-8 sticky top-0 z-20">
        <div class="flex items-center gap-4">
            <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 text-gray-600">
                <i class="fas fa-bars text-lg"></i>
            </button>
            <div class="flex items-center gap-3">
                <h1 class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
            </div>
        </div>
    </header>

    <main id="mainContent" class="flex-1 p-4 lg:p-8">
        <div id="contentArea">

<div class="fade-in">
    <div class="ap-card">
        <div class="ap-card-header">
            <div class="ap-card-title">邮箱提醒设置</div>
            <button type="submit" form="emailForm" class="ap-btn ap-btn-primary"><i class="fas fa-save"></i> 保存</button>
        </div>
        <div class="ap-card-body">
            <form id="emailForm" method="post" action="email.php">
                <?php echo csrf_token_field(); ?>
                <input type="hidden" name="save" value="1">

                <div class="section-title-inline">SMTP 设置</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">开启邮箱提醒</label>
                        <select name="email_notify_enabled" class="form-input">
                            <option value="1" <?php echo ($settings['email_notify_enabled']??'0')==='1'?'selected':''; ?>>开启</option>
                            <option value="0" <?php echo ($settings['email_notify_enabled']??'0')==='0'?'selected':''; ?>>关闭</option>
                        </select>
                        <div class="form-tips">开启后，收到新评论或回复时将发送邮件通知</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">SMTP服务器</label>
                        <input type="text" name="smtp_host" class="form-input" value="<?php echo htmlspecialchars($settings['smtp_host']??''); ?>" placeholder="smtp.qq.com">
                        <div class="form-tips">如 smtp.qq.com、smtp.163.com</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">SMTP端口</label>
                        <input type="number" name="smtp_port" class="form-input" value="<?php echo htmlspecialchars($settings['smtp_port']??'465'); ?>" placeholder="465">
                        <div class="form-tips">465（SSL）或 587（TLS）</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">发件邮箱</label>
                        <input type="email" name="smtp_user" class="form-input" value="<?php echo htmlspecialchars($settings['smtp_user']??''); ?>" placeholder="your@email.com">
                        <div class="form-tips">SMTP登录邮箱账号，也是接收通知的邮箱</div>
                    </div>
                </div>

                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label">邮箱密码 / 授权码</label>
                    <input type="password" name="smtp_pass" class="form-input" value="<?php echo htmlspecialchars($settings['smtp_pass']??''); ?>" placeholder="********">
                    <div class="form-tips">QQ邮箱请填写SMTP授权码（非登录密码）</div>
                </div>

                <div class="section-divider"></div>

                <div class="section-title-inline">邮件模板</div>
                <div class="form-group">
                    <label class="form-label">邮件标题模板</label>
                    <input type="text" name="email_subject" class="form-input" value="<?php echo htmlspecialchars(!empty($settings['email_subject'])?$settings['email_subject']:'【{site_name}】你收到一条新评论'); ?>">
                    <div class="form-tips">变量：{site_name} 网站名，{nickname} 评论者</div>
                </div>

                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label">邮件内容模板</label>
                    <div style="margin-bottom:8px;">
                        <button type="button" class="ap-btn ap-btn-sm" onclick="useTpl()" style="background:#f3f4f6;color:#4b5563;">
                            <i class="fas fa-magic"></i> 使用默认模板
                        </button>
                    </div>
                    <textarea name="email_template" id="tpl" class="form-input" rows="16"><?php echo htmlspecialchars($settings['email_template']??''); ?></textarea>
                    <div class="form-tips">变量：{site_name}、{nickname}、{content}、{post_link}、{time}</div>
                </div>

                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label">预览</label>
                    <iframe id="preview" style="width:100%;height:400px;border:2px solid #f0f0f0;border-radius:10px;background:#fff;"></iframe>
                </div>

            </form>
        </div>
    </div>
</div>

        </div>
    </main>

    <footer class="app-footer py-5 px-4 lg:px-8 mt-auto">
        <div class="flex items-center justify-center">
            <p class="text-gray-500 font-medium">
                <a href="https://imoe.top" target="_blank" class="text-primary font-bold">云汀记</a>
                <span class="text-gray-400 mx-1">&copy;</span>
                <span>2025 朋友圈</span>
            </p>
        </div>
    </footer>
</div>

<script>
const CSRF_TOKEN = '<?php echo csrf_token_value(); ?>';
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
}

function toggleSubmenu(id, el) {
    const submenu = document.getElementById(id);
    const arrow = el.querySelector('.submenu-arrow');
    const isOpen = submenu.classList.contains('open');
    if (isOpen) {
        submenu.classList.remove('open');
        arrow.classList.remove('rotate');
    } else {
        submenu.classList.add('open');
        arrow.classList.add('rotate');
    }
}

function showToast(msg, type) {
    type = type || 'success';
    const existing = document.querySelector('.ap-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'ap-toast';
    const iconClass = type === 'success' ? 'fa-check' : 'fa-exclamation';
    const iconBg = type === 'success' ? 'success' : 'error';
    toast.innerHTML = '<div class="ap-toast-icon ' + iconBg + '"><i class="fas ' + iconClass + '"></i></div><span>' + msg + '</span>';
    document.body.appendChild(toast);

    requestAnimationFrame(() => {
        toast.classList.add('show');
    });

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 400);
    }, 3000);
}

function useTpl() {
    var defaultTpl = `<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>新评论提醒</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f8; font-family:'Segoe UI','Helvetica Neue',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f6f8;">
<tr>
<td align="center" style="padding:40px 20px;">
<table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px; width:100%; background:#ffffff; border-radius:16px; box-shadow:0 2px 8px rgba(0,0,0,0.06);">

<tr>
<td style="background:#1a1a2e; padding:25px 40px; border-radius:12px 12px 0 0;">
<table width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td style="font-size:24px; font-weight:700; color:#ffffff; letter-spacing:-0.5px;">
Instant
</td>
</tr>
<tr>
<td style="font-size:14px; color:rgba(255,255,255,0.55); padding-top:6px;">
{site_name} · 新评论提醒
</td>
</tr>
</table>
</td>
</tr>

<tr>
<td style="padding:40px 40px 32px 40px;">
<p style="margin:0 0 8px 0; font-size:18px; font-weight:650; color:#1a1a2e; line-height:1.2;">
<span style="color:#1a1a2e; font-weight:500;">你好</span> {nickname}
</p>
<p style="margin:0 0 28px 0; font-size:15px; line-height:1.6; color:#6b7280;">
你收到了一条新评论 内容如下：
</p>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; margin:0 0 28px 0;">
<tr>
<td style="padding:24px 28px;">
<p style="margin:0 0 8px 0; font-size:13px; color:#6b7280; font-weight:500; letter-spacing:0.5px;">
评论内容
</p>
<p style="margin:0; font-size:16px; font-weight:500; color:#1a1a2e; line-height:1.6;">
{content}
</p>
</td>
</tr>
</table>

<table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 28px 0;">
<tr>
<td style="background:#5d6d9e; border-radius:10px; text-align:center;">
<a href="{post_link}" style="display:inline-block; padding:10px 32px; color:#ffffff; text-decoration:none; font-size:12px; font-weight:600; border-radius:10px;">查看详情</a>
</td>
</tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #f1f5f9; padding-top:20px;">
<tr>
<td style="padding:10px 0;">
<table width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td style="font-size:14px; color:#9ca3af;">评论时间</td>
<td align="right" style="font-size:14px; color:#1a1a2e; font-weight:600;">{time}</td>
</tr>
</table>
</td>
</tr>
<tr>
<td style="padding:10px 0; border-top:1px solid #f1f5f9;">
<table width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td style="font-size:14px; color:#9ca3af;">评论者</td>
<td align="right" style="font-size:14px; color:#1a1a2e; font-weight:600;">{commenter}</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>

<tr>
<td style="background:#f8fafc; padding:24px 40px; border-top:1px solid #f1f5f9; border-radius:0 0 16px 16px;">
<p style="margin:0; font-size:12px; color:#9ca3af; line-height:1.8;">
这是一封系统自动发送的邮件 请勿直接回复
</p>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>`;

    document.getElementById('tpl').value = defaultTpl;
    updatePreview();
}

function updatePreview() {
    var tpl = document.getElementById('tpl').value;
    var preview = document.getElementById('preview');

    if (!tpl.trim()) {
        preview.srcdoc = '';
        return;
    }

    var html = tpl
        .replace(/{site_name}/g, '<?php echo htmlspecialchars($settings['site_name'] ?? '云汀记', ENT_QUOTES, 'UTF-8'); ?>')
        .replace(/{nickname}/g, '访客')
        .replace(/{content}/g, '这是一条示例评论内容 用于预览邮件模板渲染效果 你可以在这里看到实际收到的邮件样式')
        .replace(/{post_link}/g, '#')
        .replace(/{time}/g, new Date().toLocaleString('zh-CN'));

    preview.srcdoc = html;
}

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($toast_msg): ?>
    showToast('<?php echo htmlspecialchars($toast_msg, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo (strpos($toast_msg, '失败') !== false || strpos($toast_msg, '无效') !== false || strpos($toast_msg, '未选择') !== false) ? 'error' : 'success'; ?>');
    <?php endif; ?>
    var tplArea = document.getElementById('tpl');
    if (tplArea) {
        tplArea.addEventListener('input', updatePreview);
        if (!tplArea.value.trim()) useTpl();
        else updatePreview();
    }
});
</script>
</body>
</html>
