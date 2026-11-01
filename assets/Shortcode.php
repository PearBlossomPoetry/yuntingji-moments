<?php
class Shortcode {
    public static function parse($html, $raw_content = '') {
        if (empty($html)) return $html;

        $source = !empty($raw_content) ? $raw_content : $html;

        $html = self::parse_github($html, $source);
        $html = self::parse_linkcard($html, $source);
        $html = self::parse_hide($html, $source);
        $html = self::parse_collapse($html, $source);
        $html = self::parse_tabs($html, $source);
        $html = self::parse_file($html, $source);
        $html = self::parse_button($html, $source);
        $html = self::parse_hint($html, $source);
        $html = self::parse_tag($html, $source);
        $html = self::parse_warn_icon($html, $source);

        return $html;
    }

    private static function extract_shortcode($content, $pattern) {
        if (preg_match($pattern, $content, $m)) {
            return $m;
        }
        return null;
    }

    private static function parse_params($param_str) {
        $params = [];
        preg_match_all('/(\w+)=(["\'])(.*?)\2/', $param_str, $m, PREG_SET_ORDER);
        foreach ($m as $match) {
            $params[$match[1]] = $match[3];
        }
        return $params;
    }

    private static function replace_shortcode_in_html($html, $source, $tag_pattern, $callback) {
        preg_match_all($tag_pattern, $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $offset_shift = 0;
        foreach ($matches as $match) {
            $full_match = $match[0][0];
            $pos = strpos($html, $full_match);
            if ($pos === false) {
                continue;
            }

            $replacement = $callback($full_match);
            if ($replacement !== null) {
                $html = substr_replace($html, $replacement, $pos, strlen($full_match));
            }
        }
        return $html;
    }

    private static function replace_in_html($html, $raw_content, $regex, $render_callback) {
        preg_match_all($regex, $raw_content, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $full = $m[0];
            $pos = strpos($html, $full);
            if ($pos !== false) {
                $replacement = $render_callback($m);
                $html = substr_replace($html, $replacement, $pos, strlen($full));
            }
        }
        return $html;
    }

    private static function parse_button($html, $raw) {
        foreach (['button', 'btn'] as $tag) {
            $html = self::replace_in_html(
                $html, $raw,
                '/\[' . $tag . '([^\]]*)\](.*?)\[\/' . $tag . '\]/s',
                function($m) {
                    $params = self::parse_params($m[1]);
                    $text = trim($m[2]);
                    $url = htmlspecialchars($params['url'] ?? '#', ENT_QUOTES, 'UTF-8');
                    $type = $params['type'] ?? 'default';
                    $types = ['default','primary','success','warning','error'];
                    if (!in_array($type, $types)) $type = 'default';
                    return '<a href="' . $url . '" class="sc-btn sc-btn-' . $type . '">' . $text . '</a>';
                }
            );
        }
        return $html;
    }

    private static function parse_hint($html, $raw) {
        foreach (['hint', 'tip'] as $tag) {
            $html = self::replace_in_html(
                $html, $raw,
                '/\[' . $tag . '([^\]]*)\](.*?)\[\/' . $tag . '\]/s',
                function($m) {
                    $params = self::parse_params($m[1]);
                    $type = $params['type'] ?? 'info';
                    $title = $params['title'] ?? '';
                    $content = trim($m[2]);

                    $type_map = [
                        'info' => 'info', 'warning' => 'warning',
                        'error' => 'error', 'danger' => 'error', 'success' => 'success'
                    ];
                    $css_type = $type_map[$type] ?? 'info';

                    $icons = [
                        'info' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>',
                        'warning' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>',
                        'error' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>',
                        'success' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>',
                    ];
                    $icon = $icons[$css_type] ?? $icons['info'];

                    $out = '<div class="sc-hint sc-hint-' . $css_type . '">';
                    $out .= '<div class="sc-hint-icon">' . $icon . '</div>';
                    $out .= '<div class="sc-hint-body">';
                    if ($title) {
                        $out .= '<div class="sc-hint-title">' . $title . '</div>';
                    }
                    $out .= '<div class="sc-hint-content">' . $content . '</div>';
                    $out .= '</div></div>';
                    return $out;
                }
            );
        }
        return $html;
    }

    private static function parse_tabs($html, $raw) {
        preg_match_all('/\[tabs\](.*?)\[\/tabs\]/s', $raw, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $full = $m[0];
            $inner = $m[1];

            preg_match_all('/\[tab\s+([^\]]*)\](.*?)\[\/tab\]/s', $inner, $tabs, PREG_SET_ORDER);
            if (empty($tabs)) continue;

            $tabs_id = 'tabs_' . mt_rand(10000, 99999);

            $out = '<div class="sc-tabs" id="' . $tabs_id . '">';
            $out .= '<div class="sc-tabs-nav">';
            foreach ($tabs as $i => $tab) {
                $t_params = self::parse_params($tab[1]);
                $title = $t_params['title'] ?? ('Tab ' . ($i + 1));
                $active = $i === 0 ? ' active' : '';
                $out .= '<button type="button" class="sc-tab-trigger' . $active . '" data-tabs="' . $tabs_id . '" data-index="' . $i . '">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</button>';
            }
            $out .= '</div><div class="sc-tabs-content">';
            foreach ($tabs as $i => $tab) {
                $active = $i === 0 ? ' active' : '';
                $inlineStyle = $i === 0 ? ' style="display:block;"' : '';
                $out .= '<div class="sc-tab-pane' . $active . '"' . $inlineStyle . ' data-tabs="' . $tabs_id . '" data-index="' . $i . '">' . trim($tab[2]) . '</div>';
            }
            $out .= '</div></div>';

            $pos = strpos($html, $full);
            if ($pos !== false) {
                $html = substr_replace($html, $out, $pos, strlen($full));
            }
        }
        return $html;
    }

    private static function parse_file($html, $raw) {
        preg_match_all('/\[file\s+([^\]]*)\/\]/', $raw, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $params = self::parse_params($m[1]);
            $name = $params['name'] ?? '未知文件';
            $url = $params['url'] ?? '#';
            $out = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="sc-file" target="_blank" rel="noopener noreferrer">'
                . '<svg class="sc-file-icon" viewBox="0 0 24 24" fill="#555"><path d="M18 22a2 2 0 0 0 2-2V8l-6-6H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2zM13 4l5 5h-5zM7 8h3v2H7zm0 4h10v2H7zm0 4h10v2H7z"/></svg>'
                . '<span class="sc-file-name">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span>'
                . '</a>';
            $pos = strpos($html, $m[0]);
            if ($pos !== false) $html = substr_replace($html, $out, $pos, strlen($m[0]));
        }

        preg_match_all('/\[file\s+([^\]]*)\](.*?)\[\/file\]/s', $raw, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $params = self::parse_params($m[1]);
            $name = trim($m[2]);
            $url = $params['url'] ?? '#';
            $out = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="sc-file" target="_blank" rel="noopener noreferrer">'
                . '<svg class="sc-file-icon" viewBox="0 0 24 24" fill="#555"><path d="M18 22a2 2 0 0 0 2-2V8l-6-6H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2zM13 4l5 5h-5zM7 8h3v2H7zm0 4h10v2H7zm0 4h10v2H7z"/></svg>'
                . '<span class="sc-file-name">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span>'
                . '</a>';
            $pos = strpos($html, $m[0]);
            if ($pos !== false) $html = substr_replace($html, $out, $pos, strlen($m[0]));
        }
        return $html;
    }

    private static function parse_tag($html, $raw) {
        foreach (['tag', 'label'] as $tag) {
            preg_match_all('/\[' . $tag . '([^\]]*)\](.*?)\[\/' . $tag . '\]/s', $raw, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                $params = self::parse_params($m[1]);
                $color = $params['color'] ?? 'default';
                $text = trim($m[2]);
                $colors = ['default','primary','success','warning','danger','info','pink','purple','orange','cyan','magenta','volcano','geekblue','lime','gold'];
                if (!in_array($color, $colors)) $color = 'default';
                $out = '<span class="sc-tag sc-tag-' . $color . '">' . $text . '</span>';
                $pos = strpos($html, $m[0]);
                if ($pos !== false) $html = substr_replace($html, $out, $pos, strlen($m[0]));
            }
        }
        return $html;
    }

    private static function parse_warn_icon($html, $raw) {
        preg_match_all('/\[!\s*\/\]/', $raw, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $out = '<span class="sc-warn-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg></span>';
            $pos = strpos($html, $m[0]);
            if ($pos !== false) $html = substr_replace($html, $out, $pos, strlen($m[0]));
        }
        return $html;
    }

    private static function parse_hide($html, $raw) {
        preg_match_all('/\[hide\](.*?)\[\/hide\]/s', $raw, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $inner = $m[1];
            $out = '<div class="sc-hide"><div class="sc-hide-mask">'
                . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>'
                . '<span>此处内容需要评论回复后方可阅读</span>'
                . '</div><div class="sc-hide-content" style="display:none;">'
                . $inner . '</div></div>';
            $pos = strpos($html, $m[0]);
            if ($pos !== false) $html = substr_replace($html, $out, $pos, strlen($m[0]));
        }
        return $html;
    }

    private static function parse_collapse($html, $raw) {
        preg_match_all('/\[collapse([^\]]*)\](.*?)\[\/collapse\]/s', $raw, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $params = self::parse_params($m[1]);
            $title = $params['title'] ?? '点击展开';
            $inner = $m[2];
            $id = 'sc_collapse_' . mt_rand(1000, 9999);
            $out = '<div class="sc-collapse" id="' . $id . '">'
                . '<button type="button" class="sc-collapse-header" onclick="this.closest(\'.sc-collapse\').classList.toggle(\'open\')">'
                . '<span class="sc-collapse-title">' . $title . '</span>'
                . '<svg class="sc-collapse-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>'
                . '</button>'
                . '<div class="sc-collapse-body">' . $inner . '</div>'
                . '</div>';
            $pos = strpos($html, $m[0]);
            if ($pos !== false) $html = substr_replace($html, $out, $pos, strlen($m[0]));
        }
        return $html;
    }

    private static function parse_github($html, $raw) {
        preg_match_all('/\[github\s+([^\]]*)\/\]/', $raw, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $params = self::parse_params($m[1]);
            $user = $params['user'] ?? '';
            $repo = $params['repo'] ?? '';

            if (!$user && strpos($repo, '/') !== false) {
                list($user, $repo) = explode('/', $repo, 2);
            }
            if (!$user || !$repo) continue;

            $out = self::render_github_card($user, $repo);
            $pos = strpos($html, $m[0]);
            if ($pos !== false) $html = substr_replace($html, $out, $pos, strlen($m[0]));
        }
        return $html;
    }

    private static function render_github_card($user, $repo) {
        $cache_file = sys_get_temp_dir() . '/sc_gh_' . md5($user . '_' . $repo) . '.json';
        $data = null;

        if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 3600) {
            $data = json_decode(file_get_contents($cache_file), true);
        }

        if (!$data) {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 2,
                    'header' => 'User-Agent: YunTingJi-Shortcode\r\nAccept: application/vnd.github.v3+json\r\n',
                    'follow_location' => 0,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);
            $res = @file_get_contents('https://api.github.com/repos/' . rawurlencode($user) . '/' . rawurlencode($repo), false, $ctx);
            if ($res) {
                $decoded = json_decode($res, true);
                if ($decoded && isset($decoded['full_name'])) {
                    $data = $decoded;
                    @file_put_contents($cache_file, $res);
                }
            }
        }

        $stars = isset($data['stargazers_count']) ? number_format($data['stargazers_count']) : '0';
        $forks = isset($data['forks_count']) ? number_format($data['forks_count']) : '0';
        $desc = isset($data['description']) ? htmlspecialchars((string)$data['description'], ENT_QUOTES, 'UTF-8') : 'No description provided.';
        $homepage = isset($data['homepage']) && $data['homepage'] ? htmlspecialchars((string)$data['homepage'], ENT_QUOTES, 'UTF-8') : '';
        $full_name = htmlspecialchars($user . '/' . $repo, ENT_QUOTES, 'UTF-8');
        $repo_url = 'https://github.com/' . rawurlencode($user) . '/' . rawurlencode($repo);
        $updated = isset($data['updated_at']) ? date('n-j-Y', strtotime($data['updated_at'])) : date('n-j-Y');
        $branch = isset($data['default_branch']) ? htmlspecialchars($data['default_branch']) : 'master';
        $language = isset($data['language']) ? htmlspecialchars((string)$data['language']) : 'PHP';

        $html = '<div class="sc-github">';
        $html .= '<div class="sc-github-header">';
        $html .= '<div class="sc-github-header-left">';
        $html .= '<svg class="sc-github-logo" viewBox="0 0 24 24"><path d="M5 2h4v2H7v2H5zm0 10H3V6h2zm2 2H5v-2h2zm2 2v-2H7v2H3v-2H1v2h2v2h4v4h2v-4h2v-2zm0 0v2H7v-2zm6-12v2H9V4zm4 2h-2V4h-2V2h4zm0 6V6h2v6zm-2 2v-2h2v2zm-2 2v-2h2v2zm0 2h-2v-2h2zm0 0h2v4h-2z"/></svg>';
        $html .= '<div class="sc-github-name">' . $full_name . '</div></div>';
        $html .= '<div class="sc-github-stats">';
        $html .= '<div class="sc-github-stat-box"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>' . $stars . '</div>';
        $html .= '<div class="sc-github-stat-box"><svg viewBox="0 0 24 24"><path d="M7 2v2h10V2h2v2h1v2h-1v2h-2V6H7v2H5V4h1V2zm2 8v2h6v-2h2v2h1v2h-1v2h-2v-2H9v2H7v-2H6v-2h1v-2h2zm-4 6v2h2v2h2v-2h6v2h2v-2h2v-2h-2v-2H7v2H5z"/></svg>' . $forks . '</div>';
        $html .= '</div></div>';
        $html .= '<div class="sc-github-body">';
        $html .= '<p class="sc-github-desc">' . $desc . '</p>';
        if ($homepage) {
            $html .= '<a class="sc-github-link" href="' . $homepage . '" target="_blank" rel="noopener noreferrer">&mdash; Read More</a>';
            $html .= '<a class="sc-github-homepage" href="' . $homepage . '" target="_blank" rel="noopener noreferrer">' . $homepage . '</a>';
        }
        $html .= '</div>';
        $html .= '<div class="sc-github-footer">';
        $html .= '<div class="sc-github-meta">Latest commit to the <strong>' . $branch . '</strong> branch<br>on ' . $updated . '</div>';
        $html .= '<a class="sc-github-download" href="' . $repo_url . '/archive/refs/heads/' . $branch . '.zip" target="_blank" rel="noopener noreferrer">Download as zip</a>';
        $html .= '</div></div>';
        return $html;
    }

    private static function parse_linkcard($html, $raw) {
        $pattern = '/\[link\]\s*\[(<svg\b[\s\S]*?<\/svg>|[^\]]*)\]\s*\[([^\]]+)\]\s*\[([^\]]*)\]\s*\[([^\]]+)\]\s*\[\/link\]/i';
        preg_match_all($pattern, $raw, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $icon_raw = trim($m[1]);
            $title = trim($m[2]);
            $desc = trim($m[3]);
            $url = trim($m[4]);

            $icon_empty = ($icon_raw === '' || $icon_raw === '图片链接');

            if (preg_match('/^<svg\b/i', $icon_raw)) {
                $icon_html = '<div class="sc-linkcard-icon">' . $icon_raw . '</div>';
            } elseif (!$icon_empty) {
                $icon_html = '<div class="sc-linkcard-icon"><img src="' . htmlspecialchars($icon_raw, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"></div>';
            } else {
                $url_for_host = $url;
                if (!preg_match('/^https?:\/\//i', $url_for_host)) {
                    $url_for_host = 'https://' . $url_for_host;
                }
                $host = parse_url($url_for_host, PHP_URL_HOST);
                $favicon = $host ? 'https://favicon.splitbee.io/?url=' . urlencode($host) : '';
                $icon_html = '<div class="sc-linkcard-icon"><img src="' . htmlspecialchars($favicon, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" onerror="this.style.display=\'none\'"></div>';
            }

            $out = '<div class="sc-linkcard-wrap"><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="sc-linkcard" target="_blank" rel="noopener noreferrer">'
                . $icon_html
                . '<div class="sc-linkcard-body">'
                . '<div class="sc-linkcard-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>'
                . ($desc ? '<div class="sc-linkcard-desc">' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</div>' : '')
                . '</div>'
                . '<span class="sc-linkcard-arrow">→</span>'
                . '</a></div>';

            $pos = strpos($html, $m[0]);
            if ($pos !== false) $html = substr_replace($html, $out, $pos, strlen($m[0]));
        }
        return $html;
    }
}
