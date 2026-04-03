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
        add_action('wp_ajax_biolinks_click', [$this, 'handle_click']);
        add_action('wp_ajax_nopriv_biolinks_click', [$this, 'handle_click']);
        add_filter('wp_sitemaps_posts_query_args', [$this, 'exclude_from_sitemap'], 10, 2);
        add_action('pre_get_posts', [$this, 'exclude_from_search']);
    }

    public function load_template(string $template): string
    {
        $page_id = (int) BioLinks_DB::get_config('page_id');
        if ($page_id > 0 && is_page($page_id)) {
            $custom = BIOLINKS_PATH . 'templates/page.php';
            if (file_exists($custom)) {
                return $custom;
            }
        }
        return $template;
    }

    public function handle_click(): void
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'biolinks_nonce')) {
            wp_send_json_error('Invalid nonce', 403);
        }

        $link_id = isset($_POST['link_id']) ? (int) $_POST['link_id'] : 0;
        if ($link_id <= 0) {
            wp_send_json_error('Invalid link ID', 400);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $rate_key = 'bl_rate_' . md5($ip . $link_id);
        if (get_transient($rate_key)) {
            wp_send_json_success();
        }
        set_transient($rate_key, 1, 60);

        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : null;

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
