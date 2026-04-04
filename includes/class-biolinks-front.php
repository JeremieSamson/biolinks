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
        add_action('wp_head', [$this, 'output_inline_styles']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_ga_script']);
        add_action('wp', [$this, 'maybe_hide_admin_bar']);
        add_action('wp_ajax_biolinks_click', [$this, 'handle_click']);
        add_action('wp_ajax_nopriv_biolinks_click', [$this, 'handle_click']);
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

        wp_enqueue_style(
            'biolinks-template',
            BIOLINKS_URL . 'templates/' . $template . '.css',
            [],
            BIOLINKS_VERSION
        );

        wp_enqueue_script(
            'biolinks-front',
            BIOLINKS_URL . 'assets/front.js',
            [],
            BIOLINKS_VERSION,
            true
        );

        wp_localize_script('biolinks-front', 'biolinksData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('biolinks_nonce'),
        ]);
    }

    public function dequeue_other_assets(): void
    {
        if (!$this->is_biolinks_page()) {
            return;
        }

        $keep_styles = ['biolinks-template'];
        $keep_scripts = ['biolinks-front', 'biolinks-gtag', 'jquery'];

        global $wp_styles, $wp_scripts;

        if ($wp_styles instanceof WP_Styles) {
            foreach ($wp_styles->queue as $handle) {
                if (!in_array($handle, $keep_styles, true)) {
                    wp_dequeue_style($handle);
                }
            }
        }

        if ($wp_scripts instanceof WP_Scripts) {
            foreach ($wp_scripts->queue as $handle) {
                if (!in_array($handle, $keep_scripts, true)) {
                    wp_dequeue_script($handle);
                }
            }
        }
    }

    public function maybe_hide_admin_bar(): void
    {
        if ($this->is_biolinks_page()) {
            add_filter('show_admin_bar', '__return_false');
        }
    }

    public function output_inline_styles(): void
    {
        if (!$this->is_biolinks_page()) {
            return;
        }

        $accent = BioLinks_DB::get_config('accent_color') ?: '#0a7286';
        echo '<style>:root { --accent: ' . esc_attr($accent) . '; }</style>' . "\n";
    }

    public function enqueue_ga_script(): void
    {
        if (!$this->is_biolinks_page()) {
            return;
        }

        $ga_id = self::detect_ga_id();
        if (!$ga_id) {
            return;
        }

        wp_enqueue_script(
            'biolinks-gtag',
            'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode($ga_id),
            [],
            null,
            false
        );

        wp_add_inline_script(
            'biolinks-gtag',
            'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag("js",new Date());gtag("config",' . wp_json_encode($ga_id) . ');'
        );
    }

    public function handle_click(): void
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'biolinks_nonce')) {
            wp_send_json_error('Invalid nonce', 403);
        }

        $link_id = isset($_POST['link_id']) ? (int) $_POST['link_id'] : 0;
        if ($link_id <= 0) {
            wp_send_json_error('Invalid link ID', 400);
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $rate_key = 'bl_rate_' . md5($ip . $link_id);
        if (get_transient($rate_key)) {
            wp_send_json_success();
        }
        set_transient($rate_key, 1, 60);

        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : null;

        BioLinks_DB::log_click($link_id, $user_agent);

        wp_send_json_success();
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

    public static function detect_ga_id(): string
    {
        $seopress = get_option('seopress_google_analytics_option_name');
        if (is_array($seopress) && !empty($seopress['seopress_google_analytics_ga4'])) {
            return $seopress['seopress_google_analytics_ga4'];
        }

        $wpseo = get_option('wpseo');
        if (is_array($wpseo) && !empty($wpseo['ga_measurement_id'])) {
            return $wpseo['ga_measurement_id'];
        }

        $mi = get_option('monsterinsights_site_profile');
        if (is_array($mi) && !empty($mi['ua'])) {
            return $mi['ua'];
        }

        return '';
    }
}
