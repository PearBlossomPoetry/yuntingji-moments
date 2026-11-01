<?php
if (file_exists(__DIR__ . '/../install.lock')) {
    header('Location: ../');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>云汀记 - 朋友圈程序</title>
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
    padding: 48px 24px 40px;
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
.section-title {
    font-size: 18px;
    font-weight: 700;
    color: #1c1c1e;
    margin-bottom: 14px;
    margin-top: 28px;
    letter-spacing: -0.3px;
}
.section-title:first-child {
    margin-top: 0;
}
.intro-text {
    font-size: 14px;
    line-height: 1.8;
    color: #86868b;
}
.feature-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-top: 4px;
}
.feature-item {
    background: #f9f9fb;
    border-radius: 14px;
    padding: 16px;
    border: 1px solid #f2f2f7;
}
.feature-item .title {
    font-size: 14px;
    font-weight: 600;
    color: #1c1c1e;
    margin-bottom: 4px;
}
.feature-item .desc {
    font-size: 12px;
    color: #86868b;
    line-height: 1.5;
}
.env-list {
    list-style: none;
    margin-top: 4px;
}
.env-list li {
    font-size: 14px;
    color: #86868b;
    padding: 10px 0;
    border-bottom: 1px solid #f2f2f7;
    position: relative;
    padding-left: 16px;
}
.env-list li:last-child {
    border-bottom: none;
}
.env-list li::before {
    content: '';
    position: absolute;
    left: 0;
    top: 17px;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #d2d2d7;
}
.btn-primary {
    display: block;
    width: 100%;
    padding: 16px 0;
    background: #000;
    color: #fff;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    transition: transform 0.1s;
    margin-top: 32px;
}
.btn-primary:active {
    transform: scale(0.97);
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
        <div class="section-title">系统简介</div>
        <div class="intro-text">
            云汀记，一个精致的仿微信朋友圈系统程序。采用仿微信朋友圈界面设计，支持发布动态、图片、视频、音乐，拥有完善的评论互动和广告管理功能，适合个人博客、产品展示、内容社区等多种场景。
        </div>

        <div class="section-title">核心功能</div>
        <div class="feature-grid">
            <div class="feature-item">
                <div class="title">多媒体动态</div>
                <div class="desc">支持图文、视频、音乐多种内容形式，无限级评论回复</div>
            </div>
            <div class="feature-item">
                <div class="title">互动系统</div>
                <div class="desc">点赞、评论、回复功能完善，AJAX无刷新操作体验流畅</div>
            </div>
            <div class="feature-item">
                <div class="title">广告管理</div>
                <div class="desc">卡片式与大图式两种广告样式，支持一键嵌入</div>
            </div>
            <div class="feature-item">
                <div class="title">高度自定义</div>
                <div class="desc">支持自定义修改前台所有东西，打造独特风格</div>
            </div>
            <div class="feature-item">
                <div class="title">邮件提醒</div>
                <div class="desc">新评论邮件通知，精美HTML邮件模板，支持自定义</div>
            </div>
            <div class="feature-item">
                <div class="title">安全可靠</div>
                <div class="desc">密码哈希存储、CSRF防护、SQL注入防护，守护数据安全</div>
            </div>
        </div>

        <div class="section-title">环境要求</div>
        <ul class="env-list">
            <li>PHP 7.4 或更高版本</li>
            <li>MySQL 5.7 或更高版本（推荐 MariaDB）</li>
            <li>支持 mysqli 扩展</li>
            <li>目录可写权限（用于生成配置文件和上传）</li>
        </ul>

        <a href="index.php" class="btn-primary">下一步：开始安装</a>
    </div>
</div>
</body>
</html>
