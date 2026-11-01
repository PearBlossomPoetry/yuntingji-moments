<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>未安装 - 云汀记朋友圈程序</title>
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
    text-align: center;
}
.section-title {
    font-size: 18px;
    font-weight: 700;
    color: #1c1c1e;
    margin-bottom: 14px;
    letter-spacing: -0.3px;
}
.intro-text {
    font-size: 14px;
    line-height: 1.8;
    color: #86868b;
    margin-bottom: 8px;
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
.footer-text {
    margin-top: 12px;
    font-size: 13px;
    color: #86868b;
}
</style>
</head>
<body>
<div class="hero">
    <div class="icon-wrap">
        <svg width="44" height="44" viewBox="0 0 24 24" fill="none">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
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
        <div class="section-title">系统提示</div>
        <div class="intro-text">
            检测到还未安装本系统<br>
            点击下方按钮开始安装向导
        </div>
        <a href="install/intro.php" class="btn-primary">前往安装</a>
        <div class="footer-text">安装过程仅需2分钟</div>
    </div>
</div>
</body>
</html>
