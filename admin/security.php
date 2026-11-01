<?php
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict strict-origin-w-origin-when-cross-origin');
require_once '../config.php';
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
$settings = get_all_settings();
$page_title = '安全管理';
$page_id = 'security';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_username'])) {
    if (!csrf_validate()) {
        $_SESSION['admin_toast'] = '安全令牌无效，请刷新页面重试';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    $new_username = $_POST['new_username'] ?? '';
    $password = $_POST['username_password'] ?? '';

    if (empty($new_username) || empty($password)) {
        $_SESSION['admin_toast'] = '请填写所有字段';
    } elseif (strlen($new_username) < 3) {
        $_SESSION['admin_toast'] = '用户名至少3位';
    } else {
        $aid = intval($_SESSION['admin_id']);
        $r = mysqli_query($conn, "SELECT * FROM admin WHERE id = $aid");
        if ($admin = mysqli_fetch_assoc($r)) {
            if (password_verify($password, $admin['password'])) {
                $esc_user = mysqli_real_escape_string($conn, $new_username);
                $check = mysqli_query($conn, "SELECT id FROM admin WHERE username = '$esc_user' AND id != $aid");
                if (mysqli_num_rows($check) > 0) {
                    $_SESSION['admin_toast'] = '该用户名已被使用';
                } else {
                    mysqli_query($conn, "UPDATE admin SET username = '$esc_user' WHERE id = $aid");
                    $_SESSION['admin_toast'] = '账号修改成功';
                }
            } else {
                $_SESSION['admin_toast'] = '密码不正确';
            }
        } else {
            $_SESSION['admin_toast'] = '用户不存在';
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!csrf_validate()) {
        $_SESSION['admin_toast'] = '安全令牌无效，请刷新页面重试';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($old) || empty($new) || empty($confirm)) {
        $_SESSION['admin_toast'] = '请填写所有字段';
    } elseif ($new !== $confirm) {
        $_SESSION['admin_toast'] = '两次密码不一致';
    } elseif (strlen($new) < 6) {
        $_SESSION['admin_toast'] = '密码至少6位';
    } else {
        $aid = intval($_SESSION['admin_id']);
        $r = mysqli_query($conn, "SELECT * FROM admin WHERE id = $aid");
        if ($admin = mysqli_fetch_assoc($r)) {
            if (password_verify($old, $admin['password'])) {
                $h = password_hash($new, PASSWORD_DEFAULT);
                mysqli_query($conn, "UPDATE admin SET password = '$h' WHERE id = $aid");
                $_SESSION['admin_toast'] = '密码修改成功';
            } else {
                $_SESSION['admin_toast'] = '旧密码不正确';
            }
        } else {
            $_SESSION['admin_toast'] = '用户不存在';
        }
    }
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
           padding: 18px 24px;
           display: flex;
           align-items: center;
           justify-content: space-between;
           border-bottom: 1px solid #f8f8f8;
       }
       .ap-card-body {
           padding: 24px;
       }
       .ap-card-title {
           font-size: 16px;
           font-weight: 700;
           color: #1f2937;
           letter-spacing: -0.2px;
       }

       .ap-btn {
           display: inline-flex;
           align-items: center;
           justify-content: center;
           gap: 6px;
           padding: 9px 20px;
           border-radius: 12px;
           font-size: 13px;
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

       .ap-form-group { margin-bottom: 16px; }
       .ap-form-label {
           display: block;
           font-size: 13px;
           font-weight: 500;
           color: #4b5563;
           margin-bottom: 6px;
       }
       .ap-form-input {
           width: 100%;
           height: 42px;
           padding: 0 14px;
           border: 2px solid #f0f0f0;
           border-radius: 12px;
           font-size: 14px;
           color: #374151;
           outline: none;
           transition: all 0.2s;
           background: #fafafa;
           font-weight: 500;
           box-sizing: border-box;
       }
       .ap-form-input:focus {
           border-color: #5b73e8;
           background: #fff;
           box-shadow: 0 0 0 4px rgba(91,115,232,0.08);
       }
       .ap-form-input::placeholder {
           color: #c1c5cb;
           font-weight: 400;
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

       .tip-card{
           background:#fff;
           border-radius:10px;
           overflow:hidden;
           box-shadow:0 2px 10px rgba(0,0,0,0.06);
           margin-bottom:16px;
       }
       .tip-card .top{
           padding:14px 24px;
           background:#f8f9ff;
           font-size:14px;
           font-weight:700;
           color:#5b73e8;
           display:flex;
           align-items:center;
           gap:8px;
       }
       .tip-card .line{height:1px;background:#eee}
       .tip-card .content{
           padding:14px 24px;
           font-size:13px;
           color:#4b5563;
           line-height:1.6;
       }
       .tip-card .content code {
           background: rgba(91,115,232,0.08);
           padding: 1px 5px;
           border-radius: 4px;
           font-family: 'SF Mono', monospace;
           font-size: 12px;
           color: #5b73e8;
           font-weight: 600;
           word-break: break-all;
       }
       .tip-card .content strong { color: #1f2937; }

       @media (max-width: 768px) {
           .ap-toast {
                    bottom: calc(20px + env(safe-area-inset-bottom, 0px));
                    right: 12px;
                    padding: 10px 14px;
                    font-size: 12px;
                    max-width: calc(100vw - 24px);
           }
           .ap-toast-icon { width: 22px; height: 22px; font-size: 11px; }
           .ap-card-header { padding: 16px 20px; }
           .ap-card-body { padding: 20px; }
           .ap-btn { padding: 8px 16px; font-size: 13px; border-radius: 12px; }
           .ap-form-group { margin-bottom: 14px; }
           .ap-form-label { font-size: 13px; }
           .ap-form-input { height: 40px; font-size: 14px; padding: 0 12px; }
           .tip-card .top { padding: 12px 20px; }
           .tip-card .content { padding: 12px 20px; }
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
                                    <a href="<?php echo $id; $id; ?>.php" class="nav-item <?php echo $is_active ? 'active' : ''; ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium <?php echo $is_active ? '' : 'text-gray-600'; ?>" data-page="<?php echo $id; ?>">
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

<div class="fade-in" style="max-width:900px;">
    <div class="tip-card">
       <div class="top">
           <i class="fas fa-lightbulb"></i>
           找回提示
       </div>
       <div class="line"></div>
       <div class="content">
           <div><strong>忘记密码：</strong>进入数据库 <code>admin</code> 表 找到 <code>password</code> 字段 清空或替换为已知哈希值即可重置</div>
           <div style="margin-top:6px"><strong>忘记用户名：</strong>进入数据库 <code>admin</code> 表 <code>username</code> 字段即为当前登录账号</div>
       </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
       <div class="ap-card mb-4">
           <div class="ap-card-header">
                    <div class="ap-card-title">修改账号</div>
           </div>
           <div class="ap-card-body">
                    <form method="POST" action="">
                   <?php echo csrf_token_field(); ?>
                   <div class="ap-form-group">
                       <label class="ap-form-label">新用户名</label>
                       <input type="text" name="new_username" class="ap-form-input" required placeholder="请输入新用户名">
                   </div>
                   <div class="ap-form-group">
                       <label class="ap-form-label">当前密码（验证身份）</label>
                       <input type="password" name="username_password" class="ap-form-input" required placeholder="请输入当前密码">
                   </div>
                   <button type="submit" name="change_username" class="ap-btn ap-btn-primary"><i class="fas fa-user-edit"></i> 修改账号</button>
                    </form>
           </div>
       </div>

       <div class="ap-card mb-4">
           <div class="ap-card-header">
                    <div class="ap-card-title">修改密码</div>
           </div>
           <div class="ap-card-body">
                    <form method="POST" action="">
                   <?php echo csrf_token_field(); ?>
                   <div class="ap-form-group">
                       <label class="ap-form-label">旧密码</label>
                       <input type="password" name="old_password" class="ap-form-input" required placeholder="请输入旧密码">
                   </div>
                   <div class="ap-form-group">
                       <label class="ap-form-label">新密码</label>
                       <input type="password" name="new_password" class="ap-form-input" required placeholder="请输入新密码（至少6位）">
                   </div>
                   <div class="ap-form-group">
                       <label class="ap-form-label">确认密码</label>
                       <input type="password" name="confirm_password" class="ap-form-input" required placeholder="请再次输入新密码">
                   </div>
                   <button type="submit" name="change_password" class="ap-btn ap-btn-primary"><i class="fas fa-key"></i> 修改密码</button>
                    </form>
           </div>
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

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($toast_msg): ?>
    showToast('<?php echo htmlspecialchars($toast_msg, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo (strpos($toast_msg, '失败') !== false || strpos($toast_msg, '错误') !== false || strpos($toast_msg, '不正确') !== false || strpos($toast_msg, '已被') !== false) ? 'error' : 'success'; ?>');
    <?php endif; ?>
});
</script>
</body>
</html>
