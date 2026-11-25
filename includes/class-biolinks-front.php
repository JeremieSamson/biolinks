<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class BioLinks_Front
{
    public function __construct()
    {
        add_filter('template_include', [$this, 'load_template']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_enqueue_scripts', [$this, 'dequeue_other_assets'], 999);
        add_action('template_redirect', [$this, 'purge_third_party_output'], 9999);
        add_action('wp', [$this, 'maybe_hide_admin_bar']);
        add_action('wp_ajax_biolinks_click', [$this, 'handle_click']);
        add_action('wp_ajax_nopriv_biolinks_click', [$this, 'handle_click']);
        add_action('wp_ajax_biolinks_view', [$this, 'handle_view']);
        add_action('wp_ajax_nopriv_biolinks_view', [$this, 'handle_view']);
        add_filter('wp_sitemaps_posts_query_args', [$this, 'exclude_from_sitemap'], 10, 2);
        add_action('pre_get_posts', [$this, 'exclude_from_search']);
    }

    private function is_biolinks_page(): bool
    {
        $page_id = (int) BioLinks_DB::get_config('page_id');
        return $page_id > 0 && is_page($page_id);
    }

    public function load_template(string $template): string
    {
        if ($this->is_biolinks_page()) {
            $custom = BIOLINKS_PATH . 'templates/page.php';
            if (file_exists($custom)) {
                return $custom;
            }
        }
        return $template;
    }

    public function enqueue_assets(): void
    {
        if (!$this->is_biolinks_page()) {
            return;
        }

        $config = BioLinks_DB::get_all_config();
        $template = $config['template'] ?? 'dark';
        $allowed = ['dark', 'light', 'minimal', 'colorful', 'glass'];
        if (!in_array($template, $allowed, true)) {
            $template = 'dark';
        }

        $accent = sanitize_hex_color((string) ($config['accent_color'] ?? '')) ?: '#0a7286';
        $css = ':root { --accent: ' . $accent . '; }' . "\n"
            . $this->read_stylesheet(BIOLINKS_PATH . 'templates/' . $template . '.css')
            . $this->read_stylesheet(BIOLINKS_PATH . 'templates/common.css');

        wp_register_style('biolinks-template', false, [], BIOLINKS_VERSION);
        wp_enqueue_style('biolinks-template');
        wp_add_inline_style('biolinks-template', $css);

        wp_enqueue_script(
            'biolinks-front',
            BIOLINKS_URL . 'assets/front.js',
            [],
            BIOLINKS_VERSION,
            true
        );

        wp_localize_script('biolinks-front', 'biolinksData', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }

    private function read_stylesheet(string $path): string
    {
        if (!is_readable($path)) {
            return '';
        }
        $css = (string) file_get_contents($path);
        return preg_replace('/\s+/', ' ', $css) . "\n";
    }

    private function allowed_handles(string $type): array
    {
        $defaults = $type === 'style' ? ['biolinks-template'] : ['biolinks-front'];
        $handles = apply_filters('biolinks_allowed_' . $type . '_handles', $defaults);
        return is_array($handles) ? $handles : $defaults;
    }

    public function dequeue_other_assets(): void
    {
        if (!$this->is_biolinks_page()) {
            return;
        }

        global $wp_styles, $wp_scripts;

        if ($wp_styles instanceof WP_Styles) {
            foreach ($wp_styles->queue as $handle) {
                if (!in_array($handle, $this->allowed_handles('style'), true)) {
                    wp_dequeue_style($handle);
                }
            }
        }

        if ($wp_scripts instanceof WP_Scripts) {
            foreach ($wp_scripts->queue as $handle) {
                if (!in_array($handle, $this->allowed_handles('script'), true)) {
                    wp_dequeue_script($handle);
                }
            }
        }

        add_filter('print_styles_array', [$this, 'filter_style_handles']);
        add_filter('print_scripts_array', [$this, 'filter_script_handles']);
        add_filter('wp_resource_hints', '__return_empty_array', 9999);
    }

    public function filter_style_handles(array $handles): array
    {
        return array_values(array_intersect($handles, $this->allowed_handles('style')));
    }

    public function filter_script_handles(array $handles): array
    {
        return array_values(array_intersect($handles, $this->allowed_handles('script')));
    }

    public function purge_third_party_output(): void
    {
        if (!$this->is_biolinks_page()) {
            return;
        }

        $this->purge_hook('wp_head', [
            'wp_enqueue_scripts',
            'wp_resource_hints',
            'wp_print_styles',
            'wp_print_head_scripts',
            'wp_maybe_inline_styles',
        ]);

        $this->purge_hook('wp_footer', [
            'wp_print_footer_scripts',
        ]);
    }

    private function purge_hook(string $hook, array $core_callbacks): void
    {
        $keep = apply_filters('biolinks_keep_callbacks', $core_callbacks, $hook);
        if (!is_array($keep)) {
            return;
        }

        global $wp_filter;

        if (!isset($wp_filter[$hook]) || !$wp_filter[$hook] instanceof WP_Hook) {
            return;
        }

        $preserved = [];
        foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $ident => $callback) {
                $name = is_string($callback['function']) ? $callback['function'] : $ident;
                if (in_array($name, $keep, true)) {
                    $preserved[] = [$callback['function'], (int) $priority, (int) $callback['accepted_args']];
                }
            }
        }

        remove_all_actions($hook);

        foreach ($preserved as $callback) {
            add_action($hook, $callback[0], $callback[1], $callback[2]);
        }
    }

    public function maybe_hide_admin_bar(): void
    {
        if ($this->is_biolinks_page()) {
            add_filter('show_admin_bar', '__return_false');
        }
    }

    public function handle_click(): void
    {
        if (!$this->is_same_origin_request()) {
            wp_send_json_error('Invalid origin', 403);
        }

        $link_id = isset($_POST['link_id']) ? absint(wp_unslash($_POST['link_id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public analytics beacon sent from a cached page, where a nonce would expire.
        if ($link_id <= 0 || !BioLinks_DB::link_exists($link_id)) {
            wp_send_json_error('Invalid link ID', 400);
        }

        $user_agent = $this->user_agent();
        if ($this->is_bot($user_agent)) {
            wp_send_json_success();
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $rate_key = 'biolinks_rate_' . md5($ip . $link_id);
        if (get_transient($rate_key)) {
            wp_send_json_success();
        }
        set_transient($rate_key, 1, 60);

        BioLinks_DB::log_click($link_id, $user_agent, BioLinks_DB::detect_source((string) $user_agent, $this->posted_referrer()));

        wp_send_json_success();
    }

    public function handle_view(): void
    {
        if (!$this->is_same_origin_request()) {
            wp_send_json_error('Invalid origin', 403);
        }

        $user_agent = $this->user_agent();
        if ($this->is_bot($user_agent)) {
            wp_send_json_success();
        }

        BioLinks_DB::log_view(BioLinks_DB::detect_source((string) $user_agent, $this->posted_referrer()));

        wp_send_json_success();
    }

    private function user_agent(): ?string
    {
        return isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : null;
    }

    private function posted_referrer(): string
    {
        return isset($_POST['referrer']) ? esc_url_raw(wp_unslash($_POST['referrer'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public analytics beacon sent from a cached page, where a nonce would expire.
    }

    private function is_bot(?string $user_agent): bool
    {
        if ($user_agent === null || $user_agent === '') {
            return true;
        }
        return (bool) preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|embedly|headless|lighthouse|pingdom|uptime|curl|wget|python-requests/i', $user_agent);
    }

    private function is_same_origin_request(): bool
    {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_ORIGIN'])) : '';
        if ($origin === '') {
            return true;
        }
        $origin_host = strtolower((string) wp_parse_url($origin, PHP_URL_HOST));
        $home_host = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
        return $origin_host === $home_host;
    }

    public static function link_href(object $link, array $config): string
    {
        $url = (string) $link->link_url;
        if (($config['utm_enabled'] ?? '0') !== '1' || !preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        if ($host === strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST))) {
            return $url;
        }

        $query = (string) wp_parse_url($url, PHP_URL_QUERY);
        if (str_contains($query, 'utm_')) {
            return $url;
        }

        $params = array_filter([
            'utm_source' => sanitize_title((string) ($config['utm_source'] ?? 'biolinks')),
            'utm_medium' => sanitize_title((string) ($config['utm_medium'] ?? 'link_in_bio')),
            'utm_content' => sanitize_title((string) $link->link_name),
        ]);

        return add_query_arg(array_map('rawurlencode', $params), $url);
    }

    public function exclude_from_sitemap(array $args, string $post_type): array
    {
        if ($post_type !== 'page') {
            return $args;
        }
        $page_id = (int) BioLinks_DB::get_config('page_id');
        if ($page_id > 0) {
            $args['post__not_in'] = array_merge($args['post__not_in'] ?? [], [$page_id]);
        }
        return $args;
    }

    public function exclude_from_search(\WP_Query $query): void
    {
        if (!is_admin() && $query->is_main_query() && $query->is_search()) {
            $page_id = (int) BioLinks_DB::get_config('page_id');
            if ($page_id > 0) {
                $excluded = $query->get('post__not_in') ?: [];
                $excluded[] = $page_id;
                $query->set('post__not_in', $excluded);
            }
        }
    }
}
