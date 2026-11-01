<?php header('Content-Type: text/css'); ?>
<?php require_once __DIR__ . '/../../config.php'; $settings = get_all_settings(); $nickname_color = $settings['nickname_color'] ?? '#5d6d9e'; ?>
<?php
$font_url = $settings['font_url'] ?? '';
if (!empty($font_url)) {
    if (!preg_match('#^(https?:)?//|^/#i', $font_url)) {
        $font_url = '/' . ltrim($font_url, '/');
    }
    $font_path = parse_url($font_url, PHP_URL_PATH) ?: $font_url;
    $ext = strtolower(pathinfo($font_path, PATHINFO_EXTENSION));
    $font_fmt = ['ttf' => 'truetype', 'otf' => 'opentype', 'woff' => 'woff', 'woff2' => 'woff2'][$ext] ?? 'truetype';
    echo "@font-face{font-family:'CF';src:url('" . htmlspecialchars($font_url) . "') format('" . $font_fmt . "');}body{font-family:'CF',sans-serif!important;}\n";
}
?>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    -webkit-tap-highlight-color: transparent;
}
body {
    font-family: -apple-system,BlinkMacSystemFont,'PingFang SC','Helvetica Neue',Arial,sans-serif;
    background: #ededed;
    -webkit-font-smoothing: antialiased;
    font-size: <?php echo htmlspecialchars($settings['global_font_size'] ?? '13'); ?>px;
}
.container {
    max-width: 100%;
    background: #fff;
    min-height: 100vh;
    margin: 0 auto;
}
@media (min-width:768px) {
    .container {
        max-width: 520px;
        box-shadow: 0 0 20px rgba(0,0,0,.06);
    }
}
@media (min-width:1024px) {
    .container {
        max-width: 560px;
        box-shadow: 0 0 24px rgba(0,0,0,.08);
    }
}
.card-banner {
    position: relative;
    border-radius: 0;
    overflow: hidden;
    height: 220px;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.card-banner.has-img {
    height: auto;
    min-height: 220px;
    background-size: cover;
    background-position: center;
}
.card-banner.has-img::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(to bottom, rgba(0,0,0,0.35) 0%, rgba(0,0,0,0.65) 100%);
    z-index: 1;
}
.card-banner .content {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    padding: 40px 32px;
    color: #fff;
}
.card-banner h3 {
    font-size: <?php echo htmlspecialchars(intval($settings['global_font_size'] ?? 13) + 6); ?>px;
    font-weight: <?php echo htmlspecialchars(intval($settings['global_font_weight'] ?? 500) - 50); ?>;
    margin-bottom: 14px;
    text-shadow: 0 2px 8px rgba(0,0,0,0.35);
    line-height: 1.3;
}
.card-banner .meta {
    font-size: <?php echo htmlspecialchars(intval($settings['global_font_size'] ?? 13) + 1); ?>px;
    opacity: 0.8;
    text-shadow: 0 1px 4px rgba(0,0,0,0.3);
}
.top-menu-btn {
    position: fixed;
    top: 6px;
    right: 10px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 1001;
    padding: 0 16px;
    transition: all .2s;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(8px) saturate(1.2);
    -webkit-backdrop-filter: blur(8px) saturate(1.2);
    border-radius: 12px;
    border: 1.5px solid rgba(255,255,255,0.9);
}
.top-menu-btn.light {
    border-color: rgba(0,0,0,0.7);
}
.top-menu-btn.dark {
    border-color: rgba(255,255,255,0.9);
}
.top-menu-btn:active {
    opacity: .7;
    transform: scale(0.96);
    transition: all .1s;
}
.hamburger {
    display: flex;
    flex-direction: column;
    gap: 3.5px;
    width: 16px;
    pointer-events: none;
}
.menu-custom-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
}
.menu-custom-icon i,.menu-custom-icon svg {
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    line-height: 1;
    margin: 0;
    padding: 0;
}
.top-menu-btn.light .menu-custom-icon i,.top-menu-btn.light .menu-custom-icon svg {
    color: #000;
}
.top-menu-btn.dark .menu-custom-icon i,.top-menu-btn.dark .menu-custom-icon svg {
    color: rgba(255,255,255,0.9);
}
.hamburger span {
    display: block;
    height: 1.8px;
    border-radius: 1px;
    background: #fff;
    transition: background .3s;
    width: 100%;
}
.hamburger span:nth-child(2) {
    width: 70%;
    margin-left: auto;
}
.top-menu-btn.light .hamburger span {
    background: #000;
}
.top-menu-btn.dark .hamburger span {
    background: rgba(255,255,255,0.9);
}
.menu-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,.15);
    z-index: 1001;
    opacity: 0;
    visibility: hidden;
    transition: all .2s;
    backdrop-filter: blur(2px);
    -webkit-backdrop-filter: blur(2px);
}
.menu-overlay.show {
    opacity: 1;
    visibility: visible;
}
.top-menu-panel {
    position: fixed;
    top: 50px;
    right: 12px;
    background: rgba(255,255,255,0.65);
    backdrop-filter: blur(28px) saturate(1.8);
    -webkit-backdrop-filter: blur(28px) saturate(1.8);
    border-radius: 16px;
    padding: 6px;
    z-index: 1002;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px) scale(0.95);
    transform-origin: top right;
    transition: all .25s cubic-bezier(.32,.72,.33,1);
    box-shadow: 0 8px 32px rgba(0,0,0,.1),inset 0 1px 0 rgba(255,255,255,.6);
    min-width: 140px;
    border: 1px solid rgba(255,255,255,.4);
}
.top-menu-panel.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}
.top-menu-item {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 10px;
    cursor: pointer;
    transition: all .15s;
    text-decoration: none;
    color: #333;
    font-size: 14px;
    text-align: center;
}
.top-menu-item:active {
    background: rgba(0,0,0,.06);
}
.top-menu-item:hover {
    background: rgba(0,0,0,.04);
}
.top-menu-item.active {
    background: rgba(93,109,158,0.1);
    color: <?php echo htmlspecialchars($nickname_color); ?>;
    font-weight: 500;
}
.top-menu-divider {
    height: 1px;
    background: rgba(0,0,0,.06);
    margin: 4px 8px;
}
.page-content {
    padding: 12px 24px 0;
    background: #fff;
}
.page-body {
    font-size: <?php echo htmlspecialchars($settings['global_font_size'] ?? '13'); ?>px;
    color: #333;
    line-height: 1.8;
    max-width: 800px;
    margin: 0 auto;
    padding: 0 8px;
}
.page-body p {
    margin-bottom: 12px;
}
.page-body img {
    max-width: 100%;
    border-radius: 12px;
    margin: 8px 0;
}
.page-body a {
    color: <?php echo htmlspecialchars($nickname_color); ?>;
    text-decoration: none !important;
}
.page-body a:hover {
    text-decoration: none !important;
}
.page-body a.sc-file,.page-body a.sc-file:hover,.page-body .sc-github a,.page-body .sc-github a:hover {
    text-decoration: none !important;
}
.page-body h1,.page-body h2,.page-body h3,.page-body h4,.page-body h5,.page-body h6 {
    letter-spacing: 0;
}
.page-body {
    --text-primary: #1c1917;
    --text-secondary: #57534e;
    --text-muted: #a8a29e;
    --accent: #c2410c;
    --border: #e7e5e4;
}

.page-body h1 {
    font-size: clamp(1.5rem, 2.6vw, 2rem);
    font-weight: 800;
    line-height: 1.2;
    letter-spacing: -0.02em;
    color: var(--text-primary);
    position: relative;
    display: block;
    margin: 1em 0 0.4em;
}
.page-body h1 .eyebrow {
    display: block;
    font-size: 0.5rem;
    font-weight: 600;
    letter-spacing: 0.25em;
    text-transform: uppercase;
    color: var(--accent);
    margin-bottom: 10px;
}
.page-body h1::after {
    content: '';
    display: block;
    width: 32px;
    height: 2px;
    background: var(--text-primary);
    margin-top: 10px;
    transition: width 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}
.page-body h1:hover::after {
    width: 64px;
}

.page-body h2 {
    font-size: clamp(1.4rem, 2.5vw, 1.9rem);
    font-weight: 700;
    line-height: 1.2;
    letter-spacing: -0.01em;
    color: var(--text-primary);
    position: relative;
    display: inline-block;
    isolation: isolate;
    margin: 1em 0 0.5em;
}
.page-body h2::after {
    content: '';
    position: absolute;
    bottom: 2px;
    left: 0;
    width: 100%;
    height: 8px;
    background: #fde68a;
    z-index: -1;
    opacity: 0.5;
    transition: all 0.3s ease;
}
.page-body h2:hover::after {
    height: 12px;
    opacity: 0.7;
    background: #fcd34d;
}

.page-body h3 {
    font-size: clamp(1.1rem, 1.8vw, 1.35rem);
    font-weight: 700;
    line-height: 1.3;
    color: var(--text-primary);
    padding-left: 22px;
    position: relative;
    transition: padding-left 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    display: block;
    margin: 1em 0 0.5em;
}
.page-body h3::before,
.page-body h3::after {
    content: "";
    position: absolute;
    left: 0;
    top: 0.12em;
    bottom: 0.12em;
    width: 3px;
    border-radius: 2px;
    background: var(--border);
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.page-body h3::after {
    left: 7px;
    opacity: 0.5;
}
.page-body h3:hover {
    padding-left: 26px;
}
.page-body h3:hover::before,
.page-body h3:hover::after {
    background: var(--accent);
}
.page-body h3:hover::after {
    opacity: 0.2;
}

.page-body h4 {
    font-size: 0.92rem;
    font-weight: 600;
    letter-spacing: 0.05em;
    color: var(--text-primary);
    display: inline-block;
    position: relative;
    padding-top: 12px;
    margin: 1.1em 0 0.4em;
}
.page-body h4::before {
    content: "";
    position: absolute;
    top: -1px;
    left: 0;
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: var(--border);
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    z-index: 1;
}
.page-body h4::after {
    content: "";
    position: absolute;
    top: 1px;
    left: 0;
    width: 24px;
    height: 1px;
    background: var(--border);
    transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}
.page-body h4:hover::before {
    background: var(--accent);
    transform: scale(1.6);
}
.page-body h4:hover::after {
    width: 100%;
    background: var(--accent);
}

.page-body h5 {
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: var(--text-primary);
    display: inline-flex;
    align-items: center;
    gap: 12px;
    position: relative;
    margin: 0.8em 0 0.4em;
}
.page-body h5::before,
.page-body h5::after {
    content: "";
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: var(--border);
    transition: all 0.45s cubic-bezier(0.16, 1, 0.3, 1);
}
.page-body h5:hover::before,
.page-body h5:hover::after {
    background: var(--accent);
    width: 20px;
    border-radius: 2px;
    height: 2px;
}

.page-body h6 {
    font-family: 'Inter', sans-serif;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--text-primary);
    display: inline-flex;
    align-items: center;
    gap: 10px;
    margin: 0.8em 0 0.4em;
    transition: color 0.3s;
}
.page-body h6::before,
.page-body h6::after {
    content: "";
    display: block;
    width: 16px;
    height: 2px;
    background: var(--border);
    transition: background 0.3s;
}
.page-body h6:hover {
    color: var(--accent);
}
.page-body h6:hover::before,
.page-body h6:hover::after {
    background: var(--accent);
}
.page-body ul,.page-body ol {
    margin: 8px 0 8px 20px;
}
.page-body li {
    margin: 4px 0;
}
.page-body blockquote {
    border-left: 3px solid #5d6d9e;
    background: #f0f3fa;
    border-radius: 0 10px 10px 0;
    padding: 12px 16px 2px 24px;
    margin: 10px 0;
    color: #4a5568;
    font-size: 14px;
    line-height: 1.7;
    position: relative;
}
.page-body blockquote::before {
    content: '"';
    position: absolute;
    left: 8px;
    top: 4px;
    font-size: 22px;
    color: #5d6d9e;
    opacity: 0.25;
    line-height: 1;
    font-family: Georgia,serif;
}
.page-body code {
    font-family: inherit;
    font-size: 0.92em;
    background: #eff1f5;
    color: #d6336c;
    padding: 0.18em 0.55em;
    border-radius: 6px;
    border: none;
}
.page-body .mac-code-content code,.page-body .mac-code-block code {
    background: none!important;
    padding: 0!important;
    border-radius: 0!important;
    color: inherit!important;
    font-family: inherit!important;
    font-size: inherit!important;
    line-height: inherit!important;
    vertical-align: baseline!important;
}
.page-body pre {
    background: #f8f8f8;
    padding: 12px;
    border-radius: 6px;
    overflow-x: auto;
    margin: 10px 0;
}
.page-body pre code {
    font-family: inherit !important;
    font-size: inherit !important;
    background: none !important;
    padding: 0 !important;
    border-radius: 0 !important;
    border: none !important;
    color: inherit !important;
    line-height: inherit !important;
    vertical-align: baseline !important;
}
.page-body table {
    width: 100%;
    border-collapse: collapse;
    margin: 10px 0;
    font-size: 13px;
}
.page-body th,.page-body td {
    border: 1px solid #e0e0e0;
    padding: 8px;
    text-align: center;
}
.page-body th {
    font-weight: 550;
    background: #f5f5f5;
}
.page-body .task-list {
    list-style: none;
    margin-left: 0;
    padding-left: 0;
}
.page-body .task-item {
    list-style: none;
    margin-left: 0;
    padding-left: 0;
}
.page-body .task-row {
    display: flex;
    gap: 10px;
    margin: 6px 0;
    align-items: flex-start;
}
.page-body .task-check {
    width: 17px;
    height: 17px;
    border: 1.5px solid #999;
    border-radius: 3px;
    margin-top: 2px;
    flex-shrink: 0;
    text-align: center;
    font-size: 12px;
    line-height: 17px;
    color: #fff;
    background: transparent;
}
.page-body .task-item.done .task-check {
    background: #23965b;
    border-color: #23965b;
}
.page-body .task-item.done .txt {
    text-decoration: line-through;
    color: #999;
}
.page-body .task-item .txt {
    color: #333;
}
.page-not-found {
    text-align: center;
    padding: 60px 20px;
}
.page-not-found h2 {
    font-size: 18px;
    color: #999;
    margin-bottom: 12px;
}
.page-not-found p {
    font-size: 14px;
    color: #ccc;
    margin-bottom: 20px;
}
.back-link {
    display: inline-block;
    padding: 8px 20px;
    background: <?php echo htmlspecialchars($nickname_color); ?>;
    color: #fff;
    text-decoration: none;
    border-radius: 8px;
    font-size: 14px;
}
.back-link:active {
    opacity: .85;
}
.layui-toast {
    position: fixed;
    left: 50%;
    top: 50%;
    transform: translate(-50%,-50%) scale(.9);
    padding: 12px 24px;
    background: rgba(0,0,0,.7);
    color: #fff;
    border-radius: 6px;
    font-size: 14px;
    opacity: 0;
    transition: all .25s;
    z-index: 99999;
    pointer-events: none;
    white-space: nowrap;
    letter-spacing: .5px;
}
.layui-toast.show {
    opacity: 1;
    transform: translate(-50%,-50%) scale(1);
}
.pg-comments-section {
    --primary: <?php echo htmlspecialchars($nickname_color); ?>;
    --primary-light: #eef2ff;
    --text: #1e293b;
    --text-secondary: #64748b;
    --text-muted: #94a3b8;
    --border: #e2e8f0;
    --bg: #ffffff;
    --radius: 14px;
    --radius-sm: 8px;
    margin: 0 -24px;
    padding: 28px 16px 16px;
    background: var(--bg);
}
.pg-top-header {
    text-align: center;
    margin-bottom: 12px;
}
.pg-top-header h2 {
    font-size: 22px;
    font-weight: 800;
    background: linear-gradient(135deg, var(--primary), #a5b4fc);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    letter-spacing: -0.5px;
    margin-bottom: 8px;
}
.pg-top-count {
    display: block;
    font-size: 13px;
    color: var(--text-muted);
    text-align: center;
}
.pg-comment-form {
    background: var(--bg);
    border-radius: var(--radius);
    border: 1.5px solid var(--border);
    padding: 16px;
    margin-bottom: 24px;
    transition: all 0.3s ease;
}
.pg-comment-form:focus-within {
    border-color: var(--primary);
    box-shadow: 0 4px 20px rgba(99,102,241,0.1);
}
.pg-form-row {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
}
.pg-form-row-email {
    position: relative;
    margin-bottom: 12px;
    display: block;
}
.pg-form-row-email .pg-form-input {
    width: 100%;
    padding-right: 46px;
}
.pg-form-avatar-preview {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #f1f5f9;
    overflow: hidden;
    border: 1px solid var(--border);
}
.pg-form-avatar-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.pg-form-input {
    flex: 1;
    height: 32px;
    padding: 0 10px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-family: inherit;
    font-size: 12px;
    color: var(--text);
    background: var(--bg);
    outline: none;
    transition: all 0.2s ease;
    min-width: 0;
}
.pg-form-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.08);
}
.pg-form-input::placeholder {
    color: #c4c9d4;
}
.pg-form-textarea {
    width: 100%;
    min-height: 64px;
    padding: 8px 10px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-family: inherit;
    font-size: 13px;
    color: var(--text);
    background: var(--bg);
    outline: none;
    resize: vertical;
    line-height: 1.5;
    transition: all 0.2s ease;
}
.pg-form-textarea:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.08);
}
.pg-form-textarea::placeholder {
    color: #c4c9d4;
}
.pg-form-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 8px;
}
.pg-form-hint {
    font-size: 11px;
    color: var(--text-muted);
}
.pg-btn-send {
    height: 28px;
    padding: 0 14px;
    border-radius: var(--radius-sm);
    border: none;
    background: var(--primary);
    color: #fff;
    font-family: inherit;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 2px 6px rgba(99,102,241,0.25);
}
.pg-btn-send:hover {
    filter: brightness(1.1);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(99,102,241,0.3);
}
.pg-sticker-btn {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    background: rgba(0,0,0,0.04);
    border: none;
    transition: all 0.15s;
    flex-shrink: 0;
    margin-right: 4px;
}
.pg-sticker-btn:hover {
    background: rgba(0,0,0,0.08);
}
.pg-sticker-btn:active {
    transform: scale(0.92);
}
.pg-sticker-btn svg {
    width: 16px;
    height: 16px;
    fill: <?php echo htmlspecialchars($nickname_color); ?>;
}
.pg-sticker-panel {
    display: none;
    margin: 4px 6px 8px;
    background: #f8f9fc;
    border-radius: 12px;
    padding: 10px;
    border: 1px solid var(--border);
}
.pg-sticker-panel.show {
    display: block;
}
.pg-sticker-tabs {
    display: flex;
    gap: 8px;
    padding: 0 4px 6px;
    border-bottom: 1px solid rgba(0,0,0,.06);
    margin-bottom: 6px;
}
.pg-sticker-tab {
    font-size: 11px;
    color: #999;
    padding: 3px 10px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s;
}
.pg-sticker-tab.active {
    background: rgba(93,109,158,0.1);
    color: var(--primary);
    font-weight: 500;
}
.pg-sticker-tab:hover {
    background: rgba(0,0,0,.04);
}
.pg-sticker-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 3px;
    overflow-y: auto;
    max-height: 118px;
    padding: 2px;
}
.pg-sticker-grid-item {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s;
    height: 36px;
}
.pg-sticker-grid-item:hover {
    background: rgba(0,0,0,.06);
    transform: scale(1.15);
}
.pg-sticker-grid-item img {
    width: 24px;
    height: 24px;
    object-fit: contain;
    pointer-events: none;
}
.heo-sticker {
    display: inline-block;
    width: 16px;
    height: 16px;
    object-fit: contain;
    vertical-align: -4px;
    margin: 0 1px;
    pointer-events: none;
}
.pg-notify-btn {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    background: rgba(0,0,0,0.04);
    border: none;
    transition: all 0.15s;
    flex-shrink: 0;
    margin-right: 4px;
}
.pg-notify-btn:hover {
    background: rgba(0,0,0,0.08);
}
.pg-notify-btn:active {
    transform: scale(0.92);
}
.pg-notify-btn.on {
    background: rgba(99,102,241,0.12);
}
.pg-notify-btn svg {
    width: 14px;
    height: 14px;
    fill: #94a3b8;
}
.pg-notify-btn.on svg {
    fill: var(--primary);
}
.pg-comment-item {
    display: flex;
    gap: 10px;
    padding: 16px 0;
    animation: pgFadeIn 0.4s ease;
}
.pg-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    flex-shrink: 0;
    overflow: hidden;
    border: 2px solid white;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    background: linear-gradient(135deg, var(--primary), #a5b4fc);
}
.pg-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.pg-avatar-sm {
    width: 24px;
    height: 24px;
    border-width: 1px;
}
.pg-comment-body {
    flex: 1;
    min-width: 0;
}
.pg-comment-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 2px;
}
.pg-comment-author {
    font-size: 13px;
    font-weight: 550;
    color: var(--text);
}
.pg-comment-time {
    font-size: 11px;
    color: var(--text-muted);
}
.pg-comment-content {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    border-top-left-radius: 3px;
    padding: 10px 14px;
    font-size: 13px;
    color: #334155;
    line-height: 1.6;
    word-break: break-word;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    transition: all 0.2s ease;
}
.pg-comment-item:hover .pg-comment-content {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
.pg-comment-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 6px;
}
.pg-reply-btn {
    display: inline-flex;
    align-items: center;
    padding: 5px 14px;
    border-radius: 20px;
    border: 1px solid var(--border);
    background: var(--bg);
    color: var(--text-secondary);
    font-family: inherit;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}
.pg-reply-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-light);
}
.pg-replies-section {
    margin-top: 8px;
    margin-bottom: 0px;
}
.pg-replies-section:has(.pg-reply-list:not(.hidden)) {
    margin-bottom: 12px;
}
.pg-toggle-replies {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: var(--primary);
    font-family: inherit;
    font-size: 12px;
    font-weight: 550;
    cursor: pointer;
    transition: all 0.15s ease;
    margin-bottom: 10px;
}
.pg-toggle-replies:hover {
    background: var(--primary-light);
}
.pg-toggle-replies svg {
    width: 13px;
    height: 13px;
    transition: transform 0.2s ease;
}
.pg-toggle-replies.collapsed svg {
    transform: rotate(-90deg);
}
.pg-reply-list {
    display: flex;
    flex-direction: column;
    background: rgba(241, 245, 249, 0.6);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid rgba(226, 232, 240, 0.6);
}
.pg-reply-list.hidden {
    display: none;
}
.pg-reply-item {
    display: flex;
    gap: 10px;
    padding: 12px 14px;
    background: rgba(255, 255, 255, 0.5);
    transition: background 0.2s ease;
}
.pg-reply-item:hover {
    background: rgba(255, 255, 255, 0.85);
}
.pg-reply-item ~ .pg-reply-item {
    border-top: 1px solid #e2e8f0;
}
.pg-reply-bubble {
    flex: 1;
    min-width: 0;
}
.pg-reply-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
    flex-wrap: wrap;
}
.pg-reply-author {
    font-size: 13px;
    font-weight: 550;
    color: var(--text);
}
.pg-reply-to {
    font-size: 12px;
    font-weight: 500;
    color: var(--primary);
}
.pg-reply-label {
    font-size: 12px;
    color: var(--text-muted);
}
.pg-reply-time {
    font-size: 11px;
    color: #cbd5e1;
    margin-left: auto;
}
.pg-reply-text {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.6;
    word-break: break-word;
}
.pg-reply-actions {
    display: flex;
    align-items: center;
    gap: 1px;
    margin-top: 6px;
}
.pg-reply-actions .pg-reply-btn {
    padding: 2px 8px;
    font-size: 10px;
}
.pg-nested-replies {
    margin-top: 0;
    padding-left: 0;
    border-left: none;
}
.pg-nested-replies .pg-reply-item {
    padding: 12px 14px;
    background: rgba(255, 255, 255, 0.5);
    border-top: 1px solid #e2e8f0;
}
.pg-nested-replies .pg-reply-item:hover {
    background: rgba(255, 255, 255, 0.85);
}
.pg-reply-list > .pg-reply-input-area {
    margin: 8px 14px;
}
.pg-nested-replies + .pg-reply-input-area {
    margin: 8px 14px;
}
.pg-comment-body > .pg-reply-input-area {
    margin-left: 0;
    margin-right: 14px;
    margin-top: 12px;
}
.pg-reply-input-area .pg-comment-form {
    padding: 10px;
    margin-bottom: 0;
    border: 1.5px dashe var(--border);
    border-radius: var(--radius);
}
.pg-comment-list {
    display: flex;
    flex-direction: column;
}
.pg-comment-divider {
    width: 60px;
    height: 3px;
    background: linear-gradient(135deg, var(--primary), #a5b4fc);
    border-radius: 2px;
    margin: 4px auto;
}
.pg-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
}
.pg-page-btn {
    min-width: 36px;
    height: 36px;
    padding: 0 12px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: var(--bg);
    color: var(--text-secondary);
    font-family: inherit;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
}
.pg-page-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-light);
}
.pg-page-btn.active {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
    box-shadow: 0 2px 8px rgba(99,102,241,0.25);
}
.pg-page-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    border-color: var(--border);
    color: var(--text-muted);
    background: var(--bg);
}
.pg-page-end-hint {
    text-align: center;
    padding: 20px 10px 10px 10px;
    color: var(--text-muted);
    font-size: 12px;
    letter-spacing: 1px;
}
.pg-empty-state {
    text-align: center;
    padding: 24px 20px;
}
.pg-empty-icon {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
}
.pg-empty-icon svg {
    width: 24px;
    height: 24px;
    color: #cbd5e1;
}
.pg-empty-state h3 {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 4px;
}
.pg-empty-state p {
    font-size: 13px;
    color: var(--text-muted);
}
@keyframes pgFadeIn {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
@keyframes pgShake {
    0%,100% {
        transform:translateX(0)
    }
    20% {
        transform:translateX(-4px)
    }
    40% {
        transform:translateX(4px)
    }
    60% {
        transform:translateX(-3px)
    }
    80% {
        transform:translateX(3px)
    }
}
