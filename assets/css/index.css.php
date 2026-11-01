<?php header('Content-Type: text/css'); ?>
<?php require_once __DIR__ . '/../../config.php'; $settings = get_all_settings(); $nickname_color = $settings['nickname_color'] ?? '#5d6d9e'; $ad_nickname_color = $settings['ad_nickname_color'] ?? '#5d6d9e'; $avatar_shape = $settings['avatar_shape'] ?? 'square'; $is_circle = ($avatar_shape === 'circle'); ?>
<?php
$divider_width = !empty($settings['divider_width']) ? trim($settings['divider_width']) : '0.5px';
$divider_color = !empty($settings['divider_color']) ? $settings['divider_color'] : '#f0f0f0';
$line_text     = !empty($settings['line_text'])     ? $settings['line_text']     : '- - -';
$line_color    = !empty($settings['line_color'])    ? $settings['line_color']    : '#e0e0e0';
$card_base     = max(10, intval($settings['global_font_size'] ?? 13) - 2);
?>
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
.cover-section {
    position: relative;
    width: 100%;
    height: <?php echo intval($settings['cover_height'] ?? 220); ?>px;
    background: #f5f5f5;
}
.cover-image {
    width: 100%;
    height: <?php echo htmlspecialchars($settings['cover_height'] ?? '220'); ?>px;
    object-fit: cover;
    display: block;
}
.user-info {
    position: absolute;
    bottom: -20px;
    right: 16px;
    display: flex;
    align-items: flex-end;
    gap: 4px;
    z-index: 10;
}
.user-name {
    font-size: <?php echo htmlspecialchars($settings['nickname_font_size'] ?? '15'); ?>px;
    color: #fff;
    font-weight: 550;
    text-shadow: 0 1px 3px rgba(0,0,0,.3);
    margin-bottom: 28px;
}
.user-avatar {
    width: <?php echo htmlspecialchars(!empty($settings['avatar_width']) ? $settings['avatar_width'] : '52'); ?>px;
    height: <?php echo htmlspecialchars(!empty($settings['avatar_height']) ? $settings['avatar_height'] : '52'); ?>px;
    border-radius: <?php echo $is_circle ? '50%' : '6px'; ?>;
    border: none;
    object-fit: cover;
    box-shadow: 0 0 0 2px #fff,0 2px 8px rgba(0,0,0,.15);
}
.user-sign {
    position: absolute;
    right: 16px;
    bottom: -42px;
    font-size: <?php echo htmlspecialchars($settings['sign_font_size'] ?? '11'); ?>px;
    color: #666;
    white-space: nowrap;
}
.moments-content {
    padding-top: 52px;
    background: #fff;
}
.moment-item {
    display: flex;
    padding: 16px;
    background: #fff;
    padding-bottom: 11px;
    position: relative;
    border-bottom: none;
}
.moment-item::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: 1px;
    background: <?php echo htmlspecialchars($divider_color); ?>;
    transform: scaleY(<?php echo floatval($divider_width); ?>);
    transform-origin: 0 100%;
}
.moment-item.article-moment {
    padding-bottom: 9px;
}
.ad-item {
    padding: 16px;
    padding-bottom: 10px;
    background: #fff;
    position: relative;
    border-bottom: none;
}
.ad-item::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: 1px;
    background: <?php echo htmlspecialchars($divider_color); ?>;
    transform: scaleY(<?php echo floatval($divider_width); ?>);
    transform-origin: 0 100%;
}
.moment-avatar {
    width: <?php echo htmlspecialchars(!empty($settings['post_avatar_width']) ? $settings['post_avatar_width'] : '40'); ?>px;
    height: <?php echo htmlspecialchars(!empty($settings['post_avatar_height']) ? $settings['post_avatar_height'] : '40'); ?>px;
    border-radius: 4px;
    margin-right: 12px;
    object-fit: cover;
    flex-shrink: 0;
}
.moment-body {
    flex: 1;
    min-width: 0;
    position: relative;
    overflow: visible;
}
.name-row {
    line-height: 17px;
    font-size: 0;
}
.moment-nickname {
    font-size: <?php echo htmlspecialchars($settings['global_font_size'] ?? '13'); ?>px;
    color: <?php echo htmlspecialchars($nickname_color); ?>;
    font-weight: 550;
    line-height: 17px;
    display: inline;
    vertical-align: middle;
}
.tag-group {
    display: inline;
    vertical-align: middle;
    line-height: 17px;
}
.tag {
    display: inline-block;
    font-size: <?php echo htmlspecialchars(intval($settings['global_font_size'] ?? 13) - 2); ?>px;
    padding: 0 10px;
    border-radius: 4px;
    line-height: 17px;
    vertical-align: middle;
    margin-left: 4px;
}
.top-tag {
    display: inline-block;
    font-size: <?php echo htmlspecialchars(intval($settings['global_font_size'] ?? 13) - 2); ?>px;
    padding: 0 10px;
    border-radius: 4px;
    background: #fef0f0;
    color: #f56c6c;
    line-height: 17px;
    vertical-align: middle;
    margin-left: 4px;
}
.moment-text {
    font-size: <?php echo htmlspecialchars(intval($settings['global_font_size'] ?? 13) - 1); ?>px;
    color: #333;
    line-height: 1.55;
    margin: 6px 0 0 0;
    word-wrap: break-word;
}
.moment-text:empty {
    display: none;
    margin: 0;
}
.moment-text+.moment-images,.moment-text+.video-grid,.moment-text+.music-wrap {
    margin-top: 8px;
}
.moment-text+.ad-card,.moment-text+.ad-image-card {
    margin-top: 10px;
}
.moment-text a,.ad-text a {
    text-decoration: none;
}
.moment-text code {
    font-family: inherit;
    font-size: 0.92em;
    background: #eff1f5;
    color: #d6336c;
    padding: 0.18em 0.55em;
    border-radius: 6px;
    border: none;
}
.moment-text .task-list {
    list-style: none;
    margin-left: 0;
    padding-left: 0;
}
.moment-text .task-item {
    list-style: none;
    margin-left: 0;
    padding-left: 0;
}
.moment-text .task-row {
    display: flex;
    gap: 10px;
    margin: 6px 0;
    align-items: flex-start;
}
.moment-text .task-check {
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
.moment-text .task-item.done .task-check {
    background: #23965b;
    border-color: #23965b;
}
.moment-text .task-item.done .txt {
    text-decoration: line-through;
    color: #999;
}
.moment-text .task-item .txt {
    color: #333;
}
.moment-images {
    display: grid;
    gap: 4px;
    margin: 10px 0 8px 0;
    grid-auto-rows: 1fr;
}
.moment-images.grid-1 {
    max-width: 200px;
    grid-template-columns: 1fr;
}
.moment-images.grid-2 {
    max-width: 280px;
    grid-template-columns: repeat(2,1fr);
}
.moment-images.grid-3,.moment-images.grid-4,.moment-images.grid-5,.moment-images.grid-6,.moment-images.grid-7,.moment-images.grid-8,.moment-images.grid-9 {
    max-width: 280px;
    grid-template-columns: repeat(3,1fr);
}
.moment-images img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 2px;
    background: #f0f0f0;
    cursor: pointer;
    display: block;
    aspect-ratio: 1/1;
}
@media (max-width: 767px) {
    .moment-images.grid-2,
  .moment-images.grid-3,
  .moment-images.grid-4,
  .moment-images.grid-5,
  .moment-images.grid-6,
  .moment-images.grid-7,
  .moment-images.grid-8,
  .moment-images.grid-9 {
        max-width: 100%;
    }
}

.moment-video {
    position: relative;
    background: #000;
    border-radius: 4px;
    overflow: hidden;
    margin: 0;
    cursor: pointer;
}
.moment-video.grid-1 {
    width: 180px;
    aspect-ratio: 16/9;
}
.moment-video.grid-2 {
    width: 100%;
    aspect-ratio: 16/9;
}
.moment-video iframe,.moment-video video {
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
    object-fit: cover;
    background: #000;
}
.video-play-btn {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%,-50%);
    width: 40px;
    height: 40px;
    background: rgba(0,0,0,.5);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.video-play-btn svg {
    width: 18px;
    height: 18px;
    fill: #fff;
    margin-left: 2px;
}
.video-duration {
    position: absolute;
    bottom: 4px;
    right: 4px;
    font-size: 10px;
    color: #fff;
    background: rgba(0,0,0,.6);
    padding: 1px 5px;
    border-radius: 2px;
}
.video-grid {
    display: grid;
    gap: 4px;
    margin: 10px 0 8px 0;
    max-width: 280px;
    grid-template-columns: repeat(2,1fr);
}
.video-grid.grid-1 {
    max-width: 180px;
    grid-template-columns: 1fr;
}
@media (max-width: 767px) {
    .video-grid {
        max-width: 100%;
    }
    .video-grid.grid-1 {
        max-width: 180px;
    }
    .moment-video.grid-1 {
        width: 180px;
    }
}

.moment-location {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    color: <?php echo htmlspecialchars($nickname_color); ?>;
    margin-left: 8px;
    white-space: nowrap;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
}
.location-icon {
    width: 12px;
    height: 12px;
    fill: <?php echo htmlspecialchars($nickname_color); ?>;
}
.moment-meta,.moment-meta-only {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 6px;
    padding-bottom: 0;
}
.moment-time {
    font-size: <?php echo htmlspecialchars(intval($settings['global_font_size'] ?? 13) - 2); ?>px;
    color: #b2b2b2;
    display: flex;
    align-items: center;
    height: 20px;
}
.menu-btn-wrap {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: flex-end;
}
.menu-btn {
    width: 28px;
    height: 20px;
    border-radius: 2px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    background: #e8e8e8;
    transition: background .15s;
    flex-shrink: 0;
}
.menu-btn:active {
    background: #d0d0d0;
}
.menu-btn::before {
    content: '';
    width: 16px;
    height: 12px;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 12'%3E%3Ccircle cx='4' cy='6' r='1.5' fill='%23576b95'/%3E%3Ccircle cx='8' cy='6' r='1.5' fill='%23576b95'/%3E%3Ccircle cx='12' cy='6' r='1.5' fill='%23576b95'/%3E%3C/svg%3E") center/contain no-repeat;
}
.action-bar {
    display: flex;
    align-items: center;
    height: 31px;
    margin-right: 4px;
    opacity: 0;
    visibility: hidden;
    transform: scaleX(0.9);
    transform-origin: right center;
    transition: all .2s;
    z-index: 99999;
    overflow: visible;
    border-radius: 4px;
    position: absolute;
    right: 32px;
    top: 50%;
    margin-top: -15.5px;
}
.action-bar.show {
    opacity: 1;
    visibility: visible;
    transform: scaleX(1);
    pointer-events: auto;
}
.action-bar-item {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 0 13px;
    height: 100%;
    color: #fff;
    font-size: <?php echo htmlspecialchars(intval($settings['global_font_size'] ?? 13) - 2); ?>px;
    cursor: pointer;
    background: #333;
    border-right: 1px solid rgba(255,255,255,.12);
    transition: background .15s;
    white-space: nowrap;
}
.action-bar-item:first-child {
    border-radius: 4px 0 0 4px;
}
.action-bar-item:last-child {
    border-right: none;
    border-radius: 0 4px 4px 0;
}
.action-bar-item:active {
    background: #444;
}
.comment-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,.4);
    z-index: 9998;
    opacity: 0;
    visibility: hidden;
    transition: opacity .2s;
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
}
.comment-modal-overlay.show {
    opacity: 1;
    visibility: visible;
}
.comment-modal {
    position: fixed;
    left: 16px;
    right: 16px;
    bottom: 16px;
    background: rgba(255,255,255,0.85);
    backdrop-filter: blur(28px) saturate(1.8);
    -webkit-backdrop-filter: blur(28px) saturate(1.8);
    border-radius: 24px;
    padding: 20px;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transform: translateY(20px);
    transition: all .35s cubic-bezier(.32,.72,.33,1);
    box-shadow: 0 -8px 40px rgba(0,0,0,.1),inset 0 1px 0 rgba(255,255,255,.6);
    max-width: 500px;
    margin: 0 auto;
    border: 1px solid rgba(255,255,255,.5);
}
.comment-modal.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}
.comment-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.comment-modal-title {
    font-size: 15px;
    color: #333;
    font-weight: 550;
}
.comment-modal-close {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ccc;
    font-size: 18px;
    cursor: pointer;
}
.comment-modal-close:active {
    color: #999;
}
.comment-modal-form {
    padding: 0;
}
.comment-modal-row {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
}
.comment-modal-input {
    flex: 1;
    height: 40px;
    padding: 0 12px;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    background: #fafafa;
    font-size: 14px;
    -webkit-appearance: none;
    outline: none;
    transition: all .15s;
    color: #333;
    font-family: inherit;
    width: 0;
}
.comment-modal-input:focus {
    background: #fff;
    border-color: <?php echo htmlspecialchars($nickname_color); ?>;
}
.comment-modal-input::placeholder {
    color: #ccc;
}
.comment-modal-textarea {
    width: 100%;
    min-height: 72px;
    padding: 10px 12px;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    background: #fafafa;
    font-size: 14px;
    resize: none;
    margin-bottom: 10px;
    -webkit-appearance: none;
    outline: none;
    transition: all .15s;
    color: #333;
    font-family: inherit;
    box-sizing: border-box;
}
.comment-modal-textarea:focus {
    background: #fff;
    border-color: <?php echo htmlspecialchars($nickname_color); ?>;
}
.comment-modal-textarea::placeholder {
    color: #ccc;
}
.comment-modal-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.comment-modal-tip {
    font-size: 11px;
    color: #ccc;
}
.comment-modal-btn {
    padding: 8px 20px;
    background: <?php echo htmlspecialchars($nickname_color); ?>;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
}
.comment-modal-btn:active {
    opacity: .85;
}
.comment-modal-btn.disabled {
    opacity: .5;
    cursor: not-allowed;
    pointer-events: none;
}
.notify-btn {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    background: rgba(0,0,0,0.04);
    border: none;
    transition: all .15s;
    margin-right: 2px;
    flex-shrink: 0;
}
.notify-btn:hover {
    background: rgba(0,0,0,0.08);
}
.notify-btn:active {
    transform: scale(0.92);
}
.notify-btn.on {
    background: rgba(93,109,158,0.15);
}
.notify-btn svg {
    width: 20px;
    height: 20px;
    fill: #999;
}
.notify-btn.on svg {
    fill: <?php echo htmlspecialchars($nickname_color); ?>;
}
.reply-hint {
    display: none;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: <?php echo htmlspecialchars($nickname_color); ?>;
    background: rgba(93,109,158,0.06);
    padding: 6px 10px;
    border-radius: 8px;
    margin-bottom: 8px;
}
.reply-hint.show {
    display: flex;
}
.interaction-area {
    margin-top: 2px;
}
.ad-item>.interaction-area {
    margin-left: 52px;
    margin-right: 0;
}
.likes {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 6px 10px;
    background: #f7f7f7;
    border-radius: 4px;
    margin-bottom: 3px;
}
.likes:empty {
    display: none;
}
.like-icon {
    width: 13px;
    height: 13px;
    fill: <?php echo htmlspecialchars($nickname_color); ?>;
    color: <?php echo htmlspecialchars($nickname_color); ?>;
    flex-shrink: 0;
}
.like-names {
    font-size: <?php echo htmlspecialchars(intval($settings['global_font_size'] ?? 13) - 2); ?>px;
    color: <?php echo htmlspecialchars($nickname_color); ?>;
    line-height: 1.4;
}
.comments {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 6px 10px;
    background: #f7f7f7;
    border-radius: 4px;
    margin-top: 3px;
}
.comments:empty {
    display: none;
}
.comment-item {
    display: block;
    font-size: <?php echo htmlspecialchars(intval($settings['global_font_size'] ?? 13) - 1); ?>px;
    color: #333;
    line-height: 1.4;
    cursor: pointer;
    padding: 1px 0;
}
.comment-item .comment-avatar-link {
    display: inline-flex;
    align-items: center;
    vertical-align: middle;
    text-decoration: none;
    cursor: pointer;
}
.comment-item .author-name {
    display: inline;
    vertical-align: middle;
    color: <?php echo htmlspecialchars($nickname_color); ?>;
    font-weight: 500;
    white-space: nowrap;
}
.comment-item:active {
    background: rgba(0,0,0,.04);
}
.comment-avatar-circle {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    background: #e0e0e0;
}
.comment-avatar-link {
    display: inline-flex;
    align-self: center;
    flex-shrink: 0;
    text-decoration: none;
    cursor: pointer;
}
.comment-text {
    display: inline;
    font-size: 13px;
    line-height: 1.3;
}
.comment-text {
    display: inline;
    font-size: <?php echo htmlspecialchars(intval($settings['global_font_size'] ?? 13) - 1); ?>px;
    line-height: 1.3;
}
.comment-text .author-name {
    color: <?php echo htmlspecialchars($nickname_color); ?>;
    font-weight: 500;
    white-space: nowrap;
    display: inline;
    vertical-align: baseline;
}
.reply-item {
    display: block;
    font-size: <?php echo htmlspecialchars(intval($settings['global_font_size'] ?? 13) - 1); ?>px;
    color: #333;
    cursor: pointer;
    padding: 0;
}
.reply-item .reply-avatar {
    width: 15px;
    height: 15px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    background: #e0e0e0;
    display: inline-block;
    vertical-align: middle;
    margin-right: 4px;
}
.reply-item .reply-to-avatar-inline {
    width: 13px;
    height: 13px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    background: #e0e0e0;
    display: inline-block;
    vertical-align: middle;
    margin-right: 4px;
}
.reply-item .reply-author-link,.reply-item .reply-to-avatar-link,.reply-item .reply-avatar-link {
    display: inline-flex;
    align-items: center;
    vertical-align: middle;
    text-decoration: none;
    cursor: pointer;
}
.reply-item .reply-label {
    display: inline;
    vertical-align: middle;
    color: #999;
    font-size: <?php echo htmlspecialchars(intval($settings['global_font_size'] ?? 13) - 1); ?>px;
    white-space: nowrap;
    margin: 0 4px;
}
.reply-item .reply-author {
    display: inline;
    vertical-align: middle;
    color: <?php echo htmlspecialchars($nickname_color); ?>;
    font-weight: 500;
    white-space: nowrap;
    font-size: <?php echo htmlspecialchars(intval($settings['global_font_size'] ?? 13) - 1); ?>px;
}
.reply-item .reply-to-name {
    display: inline;
    vertical-align: middle;
    color: <?php echo htmlspecialchars($nickname_color); ?>;
    font-weight: 500;
    white-space: nowrap;
    font-size: <?php echo htmlspecialchars(intval($settings['global_font_size'] ?? 13) - 1); ?>px;
    margin-right: 1px;
}
.reply-item:active {
    background: rgba(0,0,0,.04);
}
.reply-item .reply-author {
    color: <?php echo htmlspecialchars($nickname_color); ?>;
    font-weight: 500;
    white-space: nowrap;
    font-size: <?php echo htmlspecialchars(intval($settings['global_font_size'] ?? 13) - 1); ?>px;
}
.reply-item .reply-to-name {
    color: <?php echo htmlspecialchars($nickname_color); ?>;
    font-weight: 500;
    white-space: nowrap;
    font-size: <?php echo htmlspecialchars(intval($settings['global_font_size'] ?? 13) - 1); ?>px;
}
.reply-item .reply-label {
    color: #999;
    font-size: <?php echo htmlspecialchars(intval($settings['global_font_size'] ?? 13) - 1); ?>px;
    white-space: nowrap;
    margin: 0 5px;
}
.reply-author-link,.reply-to-name-link,.reply-to-avatar-link {
    text-decoration: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    vertical-align: middle;
}
.reply-author-link:hover .reply-author,.reply-to-name-link:hover .reply-to-name {
    text-decoration: none;
}
.reply-content {
    display: inline;
    cursor: pointer;
    vertical-align: middle;
}
.reply-avatar-link {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
    text-decoration: none;
    cursor: pointer;
    vertical-align: middle;
    margin-right: 0px;
}
.ad-header {
    display: flex;
    align-items: center;
    margin-bottom: 6px;
}
.ad-avatar-default {
    width: <?php echo htmlspecialchars(!empty($settings['post_avatar_width']) ? $settings['post_avatar_width'] : '40'); ?>px;
    height: <?php echo htmlspecialchars(!empty($settings['post_avatar_height']) ? $settings['post_avatar_height'] : '40'); ?>px;
    border-radius: <?php echo $is_circle ? '50%' : '4px'; ?>;
    margin-right: 12px;
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: #999;
    flex-shrink: 0;
}
.ad-avatar-img {
    width: <?php echo htmlspecialchars(!empty($settings['post_avatar_width']) ? $settings['post_avatar_width'] : '40'); ?>px;
    height: <?php echo htmlspecialchars(!empty($settings['post_avatar_height']) ? $settings['post_avatar_height'] : '40'); ?>px;
    border-radius: <?php echo $is_circle ? '50%' : '4px'; ?>;
    margin-right: 12px;
    object-fit: cover;
    flex-shrink: 0;
}
.ad-info {
    flex: 1;
}
.ad-title {
    font-size: <?php echo htmlspecialchars($settings['global_font_size'] ?? '13'); ?>px;
    color: <?php echo htmlspecialchars($ad_nickname_color); ?>;
    font-weight: 550;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
}
.ad-subtitle {
    font-size: <?php echo htmlspecialchars(intval($settings['global_font_size'] ?? 13) - 2); ?>px;
    color: #999;
    margin-top: 1px;
    line-height: 1.3;
}
.ad-tag {
    font-size: 11px;
    color: #999;
    background: #f0f0f0;
    padding: 2px 8px;
    border-radius: 2px;
    align-self: center;
}
.ad-text {
    font-size: <?php echo htmlspecialchars($settings['global_font_size'] ?? '13'); ?>px;
    color: #333;
    line-height: 1.6;
    margin: 4px 0 6px 0;
    padding-left: 52px;
}
.ad-card {
    border: .5px solid #e0e0e0;
    border-radius: 4px;
    overflow: hidden;
    display: flex;
    background: #fafafa;
    margin: 10px 0 8px 52px;
    cursor: pointer;
}
.ad-card-image {
    width: 80px;
    height: 80px;
    background: #f5f5f5;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    color: #ccc;
}
.ad-card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.ad-card-content {
    padding: 10px;
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.ad-card-title {
    font-size: <?php echo htmlspecialchars(intval($settings['global_font_size'] ?? 13) - 1); ?>px;
    color: #333;
    font-weight: 500;
}
.ad-card-desc {
    font-size: <?php echo htmlspecialchars(intval($settings['global_font_size'] ?? 13) - 2); ?>px;
    color: #999;
    margin-top: 4px;
}
.ad-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 6px;
    padding-left: 52px;
    position: relative;
}
.ad-image-card {
    margin: 10px 0 8px 52px;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    cursor: pointer;
}
.ad-image-main {
    width: 100%;
    height: auto;
    display: block;
    background: #f5f5f5;
}
.ad-image-footer {
    padding: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #fff;
}
.ad-brand {
    display: flex;
    align-items: center;
    gap: 8px;
}
.ad-brand-logo {
    width: 24px;
    height: 24px;
    border-radius: 4px;
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 9px;
    color: #ccc;
    flex-shrink: 0;
    object-fit: cover;
}
.ad-brand-name {
    font-size: <?php echo htmlspecialchars($settings['global_font_size'] ?? '13'); ?>px;
    color: #333;
    font-weight: 500;
}
.ad-btn {
    padding: 6px 16px;
    background: #07C160;
    color: #fff;
    font-size: <?php echo htmlspecialchars(intval($settings['global_font_size'] ?? 13) - 1); ?>px;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}
.ad-btn:active {
    opacity: .9;
}
.music-wrap {
    margin: 10px 0 8px 0;
    width: 100%;
}
.mc {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border-radius: 12px;
    background: rgba(250,250,252,.8);
    border: 1px solid rgba(0,0,0,.04);
    box-shadow: 0 2px 8px rgba(0,0,0,.04);
    position: relative;
    width: 100%;
}
.mc:active {
    transform: scale(.98);
}
.mcv {
    width: 52px;
    height: 52px;
    flex-shrink: 0;
    border-radius: 10px;
    overflow: hidden;
    position: relative;
}
.mcv img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.mi {
    flex: 1;
    min-width: 0;
}
.mn {
    font-size: <?php echo htmlspecialchars($settings['global_font_size'] ?? '13'); ?>px;
    color: #222;
    font-weight: 550;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.3;
}
.ma {
    font-size: <?php echo htmlspecialchars(intval($settings['global_font_size'] ?? 13) - 2); ?>px;
    color: #999;
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.3;
}
.mp {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: <?php echo htmlspecialchars($nickname_color); ?>;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(0,0,0,.2);
    transition: transform .15s;
}
.mp:active {
    transform: scale(.9);
}
.mp::before {
    content: '';
    width: 0;
    height: 0;
    border-style: solid;
    border-width: 5px 0 5px 8px;
    border-color: transparent transparent transparent #fff;
    margin-left: 2px;
}
.mp.on::before {
    border: none;
    width: 8px;
    height: 8px;
    background: linear-gradient(to right,#fff 2px,transparent 2px,transparent 5px,#fff 5px);
    margin-left: 0;
}
.article-new-card {
    display: flex;
    border-radius: 16px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
    position: relative;
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    margin: 10px 0 10px 0;
}
.article-new-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 32px rgba(0,0,0,0.14);
}
.article-new-card.card-magazine {
    min-height: 150px;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    background-size: cover;
    background-position: center;
    margin: 10px 0 10px 0;
}
.article-new-card.card-magazine::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(180deg, rgba(0,0,0,0.25) 0%, rgba(0,0,0,0.55) 100%);
    z-index: 1;
}
.article-new-card.card-magazine .article-card-thumb {
    display: none;
}
.article-new-card.card-magazine .article-card-body {
    position: relative;
    z-index: 2;
    flex: 0;
    padding: 14px 20px;
    text-align: center;
    width: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 6px;
    background: transparent;
    min-height: auto;
}
.article-new-card.card-magazine .article-card-title {
    font-size: <?php echo $card_base + 6; ?>px;
    font-weight: 500;
    color: #fff;
    text-shadow: 0 2px 8px rgba(0,0,0,0.3);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 0;
}
.article-new-card.card-magazine .article-card-date {
    font-size: <?php echo $card_base - 2; ?>px;
    color: rgba(255,255,255,0.8);
    text-shadow: 0 1px 4px rgba(0,0,0,0.3);
}
.article-new-card.card-minimal {
    background: #fff;
    border: 1px solid #e8e8e8;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    flex-direction: column;
}
.article-new-card.card-minimal .article-card-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    padding: 16px 20px;
    text-align: left;
    min-height: auto;
    gap: 6px;
}
.article-new-card.card-minimal .article-card-title {
    color: #222;
    font-size: <?php echo $card_base + 4; ?>px;
    font-weight: 500;
    margin-bottom: 0;
}
.article-new-card.card-minimal .article-card-summary {
    font-size: <?php echo $card_base; ?>px;
    color: #888;
    line-height: 1.6;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.article-new-card.card-minimal .article-card-date {
    font-size: <?php echo $card_base - 2; ?>px;
    color: #bbb;
}
.moment-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 8px;
    padding-bottom: 2px;
}
.read-full-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #576b95;
    font-size: 12px;
    cursor: pointer;
    padding: 4px 10px;
    background: #f0f4fa;
    border-radius: 6px;
    transition: all 0.15s;
    text-decoration: none;
}
.read-full-btn:hover {
    background: #e8eef7;
}
@media (max-width: 480px) {
    .article-new-card.card-magazine {
        min-height: 130px;
        border-radius: 14px;
    }
    .article-new-card.card-magazine .article-card-title {
        font-size: <?php echo $card_base + 4; ?>px;
    }
    .article-new-card.card-magazine .article-card-date {
        font-size: <?php echo $card_base - 2; ?>px;
    }
    .article-new-card.card-minimal .article-card-body {
        padding: 14px 16px;
    }
    .article-new-card.card-minimal .article-card-title {
        font-size: <?php echo $card_base + 3; ?>px;
    }
}
.image-viewer {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,.92);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    touch-action: none;
}
.image-viewer.show {
    display: flex;
}
.viewer-img {
    max-width: 95%;
    max-height: 78vh;
    object-fit: contain;
    transition: transform .2s;
}
.viewer-bottom-bar {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: center;
    gap: 6px;
    z-index: 10;
    background: rgba(142,142,147,0.4);
    backdrop-filter: blur(24px) saturate(1.5);
    -webkit-backdrop-filter: blur(24px) saturate(1.5);
    border-radius: 14px;
    padding: 8px 14px;
    pointer-events: none;
    min-width: 210px;
    justify-content: space-between;
}
.viewer-bottom-bar>* {
    pointer-events: auto;
}
.viewer-counter {
    color: #fff;
    font-size: 13px;
    font-weight: 500;
    letter-spacing: .3px;
    min-width: 32px;
    text-align: center;
    order: 0;
}
.viewer-btn {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #fff;
    font-size: 16px;
    font-weight: 400;
    background: rgba(255,255,255,0.12);
    border: none;
    outline: none;
    -webkit-tap-highlight-color: transparent;
    transition: all .2s;
    -webkit-user-select: none;
    user-select: none;
}
.viewer-btn:hover {
    background: rgba(255,255,255,.22);
}
.viewer-btn:active {
    background: rgba(255,255,255,.32);
    transform: scale(.92);
}
.viewer-close svg {
    width: 16px;
    height: 16px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2.2;
    stroke-linecap: round;
}
.video-player-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,.95);
    z-index: 9999;
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding-bottom: 70px;
    box-sizing: border-box;
}
.video-player-overlay.show {
    display: flex;
}
.video-player-overlay video,.video-player-overlay iframe {
    max-width: 100vw;
    max-height: calc(100vh - 70px);
    width: 100%;
    height: 100%;
    object-fit: contain;
    margin-bottom: 0;
    border: 0;
}
.video-player-bar {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: center;
    gap: 6px;
    z-index: 10;
    background: rgba(142,142,147,0.4);
    backdrop-filter: blur(24px) saturate(1.5);
    -webkit-backdrop-filter: blur(24px) saturate(1.5);
    border-radius: 14px;
    padding: 8px 14px;
    pointer-events: none;
    min-width: 210px;
    justify-content: space-between;
}
.video-player-bar>* {
    pointer-events: auto;
}
.video-player-bar .vp-btn {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #fff;
    font-size: 14px;
    font-weight: 400;
    background: rgba(255,255,255,0.12);
    border: none;
    outline: none;
    -webkit-tap-highlight-color: transparent;
    transition: all .2s;
}
.video-player-bar .vp-btn:hover {
    background: rgba(255,255,255,.22);
}
.video-player-bar .vp-btn:active {
    background: rgba(255,255,255,.32);
    transform: scale(.92);
}
.vp-counter {
    color: #fff;
    font-size: 13px;
    font-weight: 500;
    letter-spacing: .3px;
    min-width: 32px;
    text-align: center;
}
.load-more {
    text-align: center;
    padding: 20px;
}
.load-more-line {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #ccc;
    font-size: <?php echo htmlspecialchars($settings['global_font_size'] ?? '13'); ?>px;
}
.load-more-line::before,.load-more-line::after {
    content: '<?php echo htmlspecialchars($line_text); ?>';
    color: <?php echo htmlspecialchars($line_color); ?>;
    font-size: <?php echo htmlspecialchars($settings['global_font_size'] ?? '13'); ?>px;
    letter-spacing: 2px;
}
.load-more-btn {
    background: none;
    border: none;
    color: #576b95;
    font-size: <?php echo htmlspecialchars($settings['global_font_size'] ?? '13'); ?>px;
    cursor: pointer;
    padding: 4px 8px;
}
.end-tip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #ccc;
    font-size: <?php echo htmlspecialchars($settings['global_font_size'] ?? '13'); ?>px;
}
.end-tip::before,.end-tip::after {
    content: '<?php echo htmlspecialchars($line_text); ?>';
    color: <?php echo htmlspecialchars($line_color); ?>;
    font-size: <?php echo htmlspecialchars($settings['global_font_size'] ?? '13'); ?>px;
    letter-spacing: 2px;
}
.loading-spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid #576b95;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin .6s linear infinite;
    vertical-align: middle;
    margin-right: 4px;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
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
.interaction-disabled {
    text-align: center;
    color: #999;
    font-size: 12px;
    padding: 5px 10px;
    background: #f7f7f7;
    border-radius: 4px;
    margin-top: 5px;
    line-height: 1.4;
}
.expand-btn {
    color: <?php echo htmlspecialchars($nickname_color); ?>;
    cursor: pointer;
    margin-left: 4px;
    font-size: <?php echo htmlspecialchars($settings['global_font_size'] ?? '13'); ?>px;
}
.expand-btn:hover {
    opacity: .8;
}
.expand-dots {
    color: #999;
}
.sticker-btn {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    background: rgba(0,0,0,0.04);
    border: none;
    transition: all .15s;
    margin-right: 6px;
    flex-shrink: 0;
}
.sticker-btn:hover {
    background: rgba(0,0,0,0.08);
}
.sticker-btn:active {
    transform: scale(0.92);
}
.sticker-btn svg {
    width: 20px;
    height: 20px;
    fill: <?php echo htmlspecialchars($nickname_color); ?>;
}
.sticker-panel {
    position: fixed;
    left: 32px;
    right: 32px;
    bottom: 110px;
    background: rgba(255,255,255,0.85);
    backdrop-filter: blur(28px) saturate(1.8);
    -webkit-backdrop-filter: blur(28px) saturate(1.8);
    border-radius: 24px;
    padding: 20px;
    z-index: 10000;
    opacity: 0;
    visibility: hidden;
    transform: translateY(10px);
    transition: all .3s cubic-bezier(.32,.72,.33,1);
    box-shadow: 0 -8px 40px rgba(0,0,0,.1),inset 0 1px 0 rgba(255,255,255,.6);
    max-width: 500px;
    margin: 0 auto;
    border: 1px solid rgba(105,105,105,.15);
    max-height: 260px;
    display: flex;
    flex-direction: column;
}
.sticker-panel.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}
.sticker-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 4px 8px;
    border-bottom: 1px solid rgba(0,0,0,.06);
    margin-bottom: 8px;
}
.sticker-panel-title {
    font-size: 13px;
    font-weight: 550;
    color: #333;
}
.sticker-panel-close {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #999;
    font-size: 16px;
    cursor: pointer;
    border-radius: 6px;
}
.sticker-panel-close:hover {
    background: rgba(0,0,0,.06);
}
.sticker-tabs {
    display: flex;
    gap: 8px;
    padding: 0 4px 8px;
    border-bottom: 1px solid rgba(0,0,0,.06);
    margin-bottom: 8px;
}
.sticker-tab {
    font-size: 12px;
    color: #999;
    padding: 4px 12px;
    border-radius: 6px;
    cursor: pointer;
    transition: all .15s;
}
.sticker-tab.active {
    background: rgba(93,109,158,0.1);
    color: <?php echo htmlspecialchars($nickname_color); ?>;
    font-weight: 500;
}
.sticker-tab:hover {
    background: rgba(0,0,0,.04);
}
.sticker-grid {
    display: grid;
    grid-template-columns: repeat(5,1fr);
    gap: 4px;
    overflow-y: auto;
    padding: 2px;
    max-height: 180px;
}
.sticker-grid-item {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2px;
    border-radius: 8px;
    cursor: pointer;
    transition: all .15s;
    height: 40px;
}
.sticker-grid-item:hover {
    background: rgba(0,0,0,.06);
    transform: scale(1.15);
}
.sticker-grid-item img {
    width: 28px;
    height: 28px;
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
    border: 1px solid rgba(255,255,255,0.9);
}
.top-menu-btn.light {
    border-color: rgba(0,0,0,0.6);
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
    background: rgba(0,0,0,.03);
}
.top-menu-item.active {
    background: rgba(93,109,158,0.1);
    color: <?php echo htmlspecialchars($nickname_color); ?>;
    font-weight: 500;
}
.top-menu-item svg {
    width: 18px;
    height: 18px;
    fill: currentColor;
    flex-shrink: 0;
}
.top-menu-divider {
    height: 1px;
    background: rgba(0,0,0,.06);
    margin: 4px 8px;
}
.comment-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 4px;
    margin-bottom: 0;
    border-bottom: 1px solid rgba(0,0,0,.06);
}
.comment-count-text {
    font-size: 12px;
    color: #999;
}
.comment-toggle-btn {
    font-size: 12px;
    color: #576b95;
    cursor: pointer;
    transition: opacity .15s;
}
.comment-toggle-btn:hover {
    opacity: .7;
}
.comment-list-wrap {
    display: flex;
    flex-direction: column;
    padding-top: 0;
    gap: 3.5px;
}
.comment-preview {
    display: flex;
    flex-direction: column;
    gap: 3.5px;
}
.comment-rest {
    display: flex;
    flex-direction: column;
    gap: 3.5px;
    padding-top: 0;
    animation: commentFadeIn .25s ease-out;
}
@keyframes commentFadeIn {
    from {
        opacity: 0;
        transform: translateY(-4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.moment-text .sc-bilibili {
    position: relative;
    width: 100%;
    max-width: 100%;
    padding-top: 56.25%;
    margin: 10px 0 8px;
    border-radius: 14px;
    overflow: hidden;
    background: #000;
    box-shadow: 0 2px 8px rgba(0,0,0,.1);
}
.moment-text .sc-bilibili + .sc-bilibili {
    margin-top: 8px;
}
.moment-text .sc-bilibili iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
    transform: scale(1.03);
}
@media(max-width:640px) {
    .moment-text .sc-bilibili {
        border-radius: 10px;
    }
}
