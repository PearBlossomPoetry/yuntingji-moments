# 云汀记 · 朋友圈程序

单用户的 PHP 朋友圈式站点。动态、文章、单页混排成一条时间流，带评论、点赞、标签、广告位和完整后台。

- 当前版本：**1.5.0**
- 技术栈：PHP + MySQL，无框架依赖
- 适配：移动端优先，PC 端自适应

## 预览

<p align="center">
  <img src="assets/github/home.png" width="32%">
  <img src="assets/github/home1.png" width="32%">
  <img src="assets/github/home2.png" width="32%">
</p>
<p align="center">
  <img src="assets/github/home3.png" width="48%">
  <img src="assets/github/home4.png" width="48%">
</p>

## 环境要求

| 项目 | 要求 |
| --- | --- |
| PHP | 7.4 及以上（8.x 可用） |
| PHP 扩展 | mysqli、mbstring、fileinfo、curl、zip、json |
| 数据库 | MySQL 5.7+ / MariaDB 10.3+，utf8mb4 |
| Web 服务器 | Apache 需开启 mod_rewrite 且 `AllowOverride All`；Nginx 见下方规则 |
| 目录权限 | 站点根目录可写（安装时生成 db.config.php、install.lock），`uploads/`、`cache/` 可写 |

## 安装

```bash
git clone https://github.com/PearBlossomPoetry/yuntingji-moments.git
```

1. 上传全部文件到网站根目录（也支持子目录部署），保证根目录可写。
2. 访问站点域名，未安装时会显示引导页，点「前往安装」。
3. 按向导三步完成：
   - 填写数据库地址、用户名、密码、库名（库不存在会自动创建）
   - 选择「清空旧数据」或「保留数据」，同时设置后台管理员账号和密码
   - 安装完成，跳转登录
4. 装完后建议删除 `install/` 目录（`install.lock` 已能拦截重复安装）。
5. 后台入口：`https://你的域名/admin/`

## 目录结构

```
├── index.php          首页信息流
├── article.php        文章详情（伪静态 /slug.html）
├── page.php           独立页面
├── rss.php            RSS 输出（/feed）
├── config.php         全局配置、数据库连接、内容解析、评论渲染
├── not_installed.php  未安装引导页
├── upgrade.php        数据结构升级（需登录后台）
├── unban_me.php       IP 解封工具
├── admin/             后台（文章、动态、页面、评论、标签、广告、设置、备份等）
├── api/               前台接口（评论、点赞、加载更多、上传、音乐解析）
├── assets/            Parsedown、样式、脚本、表情包、背景图、字体、README 截图
├── install/           安装向导，装完可删
├── uploads/           用户上传文件
├── cache/             RSS 等临时缓存
└── .htaccess          Apache 伪静态与安全规则
```

## 功能

**前台**
- 动态流：文字 / 图片 / 视频 / 音乐，支持置顶，文章与广告按时间混排
- 点赞（按 IP 去重）、多级评论、表情包、违禁词过滤
- 标签筛选、手动或滚动加载更多、PJAX 无刷新跳转
- 文章支持 Markdown 与短代码排版，自动生成 RSS

**后台**
- 动态、文章、页面、评论、标签、广告位管理
- 上传文件管理、访客日志、IP 黑名单
- 站点设置：头像、昵称、签名、封面、分页数量、字体字号、配色等
- SMTP 邮件通知：新评论提醒、自动备份发送到邮箱
- 数据库备份、恢复、定时自动备份

## 内容短代码

正文支持 Markdown，另可用以下短代码排版：

| 短代码 | 说明 |
| --- | --- |
| `[hint type="info" title="标题"]内容[/hint]` | 提示块，type 可为 info / warn / danger |
| `[tabs selected="0"][tab name="一"]内容[/tab][/tabs]` | 标签页 |
| `[graybox]内容[/graybox]` | 灰色区块 |
| `[tag color="#5b73e8"]文字[/tag]` | 彩色标签 |
| `[btn url="链接"]按钮文字[/btn]` | 按钮 |
| `[link][图标][标题][描述][链接][/link]` | 链接卡片 |
| `[file url="链接" name="文件名"]说明[/file]` | 文件下载卡片 |
| `[github owner/repo/]` | GitHub 仓库卡片 |
| `[bilibili]BV号或视频链接[/bilibili]` | 嵌入 B 站视频 |
| `[image id=1]` `[video id=1]` `[music id=1]` | 引用已上传的媒体 |
| `[html]任意 HTML[/html]` | 直接嵌入 HTML |
| `:kawa-1:` `:heo-xx:` | 表情包 |

## 伪静态

Apache 已随包提供 `.htaccess`，无需额外配置。Nginx 可用：

```nginx
rewrite ^/([a-zA-Z0-9_-]+)\.html$ /article.php?slug=$1 last;
location / { try_files $uri $uri/ /index.php?$query_string; }

location ~* \.(env|ini|log|sql|bak|zip|gz|rar)$ { deny all; }
location ~ /\. { deny all; }
```

## 常见问题

**点「前往安装」报 403**
旧版 `.htaccess` 里有整目录拦截规则，本版已移除。若仍报 403，检查主机是否有安全插件拦截 `install` 目录，或 Apache 未开启 `AllowOverride`。

**提示数据库连接失败**
检查根目录 `db.config.php` 中的地址、账号、密码、库名，并确认服务器装了 mysqli 扩展。

**上传图片或视频失败**
`uploads/` 需要写权限；同时受 PHP 配置限制（`upload_max_filesize`、`post_max_size`），程序内限制为图片 20 MB、视频 100 MB。

**首页能打开但文章链接 404**
伪静态没生效。Apache 确认 mod_rewrite 与 AllowOverride，Nginx 加上面的规则。

**忘了后台密码**
生成新密码哈希：`php -r "echo password_hash('新密码', PASSWORD_DEFAULT);"`
然后更新数据库：`UPDATE admin SET password = '哈希值' WHERE username = '账号';`

## 安全建议

- 删除 `install/` 目录
- 修改 `unban_me.php` 里的访问密钥
- 后台目录 `admin/` 可改名，登录后注意不要在公共设备保存会话
- 数据库文件（`.sql`）与备份包已被 `.htaccess` 拦截，Nginx 需按上文补充
