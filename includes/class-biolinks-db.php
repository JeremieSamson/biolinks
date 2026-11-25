<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class BioLinks_DB
{
    public const SCHEMA_VERSION = '2';

    public const SOURCES = [
        'instagram',
        'facebook',
        'tiktok',
        'linkedin',
        'twitter',
        'threads',
        'snapchat',
        'pinterest',
        'youtube',
        'whatsapp',
        'telegram',
        'search',
        'website',
        'direct',
    ];

    public static function table_config(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'biolinks_config';
    }

    public static function table_links(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'biolinks_links';
    }

    public static function table_logs(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'biolinks_logs';
    }

    public static function table_views(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'biolinks_views';
    }

    public static function maybe_upgrade(): void
    {
        if (get_option('biolinks_schema_version') === self::SCHEMA_VERSION) {
            return;
        }
        self::activate();
    }

    public static function activate(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $config = self::table_config();
        $links = self::table_links();
        $logs = self::table_logs();
        $views = self::table_views();
        $previous_schema = (string) get_option('biolinks_schema_version', '');

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE $config (
            option_name varchar(100) NOT NULL,
            option_value longtext NOT NULL,
            PRIMARY KEY  (option_name)
        ) $charset;");

        dbDelta("CREATE TABLE $links (
            id int(11) NOT NULL AUTO_INCREMENT,
            link_name varchar(255) NOT NULL,
            link_url varchar(500) NOT NULL,
            icon varchar(50) DEFAULT NULL,
            position int(11) NOT NULL DEFAULT 0,
            click_count int(11) DEFAULT 0,
            link_type varchar(20) NOT NULL DEFAULT 'link',
            featured tinyint(1) NOT NULL DEFAULT 0,
            starts_at datetime DEFAULT NULL,
            ends_at datetime DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset;");

        dbDelta("CREATE TABLE $logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            link_id int(11) NOT NULL,
            clicked_at datetime NOT NULL,
            user_agent varchar(500) DEFAULT NULL,
            source varchar(20) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_link_date (link_id, clicked_at)
        ) $charset;");

        dbDelta("CREATE TABLE $views (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            viewed_at datetime NOT NULL,
            source varchar(20) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_date (viewed_at)
        ) $charset;");

        $defaults = [
            'photo_url' => '',
            'title' => get_bloginfo('name'),
            'bio' => '',
            'slug' => 'links',
            'page_id' => '0',
            'template' => 'dark',
            'accent_color' => '#0a7286',
            'socials' => '{}',
            'show_credit' => '0',
            'activated_at' => (string) time(),
            'utm_enabled' => '0',
            'utm_source' => 'biolinks',
            'utm_medium' => 'link_in_bio',
            'newsletter_enabled' => '0',
            'newsletter_action' => '',
            'newsletter_field' => 'EMAIL',
            'newsletter_hidden' => '',
            'newsletter_title' => '',
            'newsletter_placeholder' => '',
            'newsletter_button' => '',
        ];

        foreach ($defaults as $key => $value) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $config WHERE option_name = %s",
                $key
            ));
            if ((int) $exists === 0) {
                $wpdb->insert($config, [
                    'option_name' => $key,
                    'option_value' => $value,
                ]);
            }
        }

        if ($previous_schema === '' || version_compare($previous_schema, '2', '<')) {
            self::backfill_log_sources();
        }

        update_option('biolinks_schema_version', self::SCHEMA_VERSION);

        self::ensure_page();
    }

    private static function backfill_log_sources(): void
    {
        global $wpdb;
        $logs = self::table_logs();
        $patterns = [
            'instagram' => ['Instagram'],
            'facebook' => ['FBAN', 'FBAV', 'FB_IAB', 'FBIOS', 'FB4A'],
            'tiktok' => ['musical_ly', 'BytedanceWebview', 'TikTok', 'trill_'],
            'linkedin' => ['LinkedInApp'],
            'snapchat' => ['Snapchat'],
            'pinterest' => ['Pinterest'],
            'twitter' => ['Twitter'],
            'telegram' => ['Telegram'],
            'whatsapp' => ['WhatsApp'],
        ];

        foreach ($patterns as $source => $needles) {
            foreach ($needles as $needle) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE $logs SET source = %s WHERE source IS NULL AND user_agent LIKE %s",
                    $source,
                    '%' . $wpdb->esc_like($needle) . '%'
                ));
            }
        }

        $wpdb->query("UPDATE $logs SET source = 'direct' WHERE source IS NULL");
    }

    public static function detect_source(string $user_agent, string $referrer): string
    {
        $ua_patterns = [
            'instagram' => '/Instagram/i',
            'facebook' => '/FBAN|FBAV|FB_IAB|FBIOS|FB4A/',
            'tiktok' => '/musical_ly|BytedanceWebview|TikTok|trill_/i',
            'linkedin' => '/LinkedInApp/i',
            'threads' => '/Barcelona/',
            'snapchat' => '/Snapchat/i',
            'pinterest' => '/Pinterest/i',
            'twitter' => '/Twitter/i',
            'telegram' => '/Telegram/i',
            'whatsapp' => '/WhatsApp/i',
        ];
        foreach ($ua_patterns as $source => $pattern) {
            if (preg_match($pattern, $user_agent)) {
                return $source;
            }
        }

        $host = strtolower((string) wp_parse_url($referrer, PHP_URL_HOST));
        if ($host === '') {
            return 'direct';
        }

        $host_patterns = [
            'instagram' => '/(^|\.)instagram\.com$/',
            'facebook' => '/(^|\.)(facebook\.com|fb\.me|messenger\.com)$/',
            'tiktok' => '/(^|\.)tiktok\.com$/',
            'linkedin' => '/(^|\.)(linkedin\.com|lnkd\.in)$/',
            'twitter' => '/(^|\.)(twitter\.com|x\.com|t\.co)$/',
            'threads' => '/(^|\.)threads\.(net|com)$/',
            'snapchat' => '/(^|\.)snapchat\.com$/',
            'pinterest' => '/(^|\.)(pinterest\.[a-z.]+|pin\.it)$/',
            'youtube' => '/(^|\.)(youtube\.com|youtu\.be)$/',
            'whatsapp' => '/(^|\.)whatsapp\.(com|net)$/',
            'telegram' => '/(^|\.)(t\.me|telegram\.org)$/',
            'search' => '/(^|\.)(google\.[a-z.]+|bing\.com|duckduckgo\.com|search\.yahoo\.com|yandex\.[a-z]+|ecosia\.org|qwant\.com|search\.brave\.com)$/',
        ];
        foreach ($host_patterns as $source => $pattern) {
            if (preg_match($pattern, $host)) {
                return $source;
            }
        }

        $own_host = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
        if ($host === $own_host) {
            return 'direct';
        }

        return 'website';
    }

    public static function ensure_page(): void
    {
        $page_id = (int) self::get_config('page_id');
        $slug = self::get_config('slug') ?: 'links';

        if ($page_id > 0 && get_post_status($page_id) !== false) {
            return;
        }

        $existing = get_page_by_path($slug);
        if ($existing) {
            self::set_config('page_id', (string) $existing->ID);
            return;
        }

        $new_id = wp_insert_post([
            'post_title' => 'BioLinks',
            'post_name' => $slug,
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => '',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        ]);

        if ($new_id && !is_wp_error($new_id)) {
            self::set_config('page_id', (string) $new_id);
        }
    }

    // --- Config CRUD ---

    public static function get_config(string $key): string
    {
        global $wpdb;
        $table = self::table_config();
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM $table WHERE option_name = %s",
            $key
        ));
        return $value ?? '';
    }

    public static function set_config(string $key, string $value): void
    {
        global $wpdb;
        $table = self::table_config();
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE option_name = %s",
            $key
        ));
        if ((int) $exists > 0) {
            $wpdb->update($table, ['option_value' => $value], ['option_name' => $key]);
        } else {
            $wpdb->insert($table, ['option_name' => $key, 'option_value' => $value]);
        }
    }

    public static function get_all_config(): array
    {
        global $wpdb;
        $table = self::table_config();
        $rows = $wpdb->get_results("SELECT option_name, option_value FROM $table");
        $config = [];
        foreach ($rows as $row) {
            $config[$row->option_name] = $row->option_value;
        }
        return $config;
    }

    // --- Links CRUD ---

    public static function get_all_links(): array
    {
        global $wpdb;
        $table = self::table_links();
        return $wpdb->get_results("SELECT * FROM $table ORDER BY position ASC");
    }

    public static function get_link(int $id): ?object
    {
        global $wpdb;
        $table = self::table_links();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }

    public static function get_public_links(): array
    {
        global $wpdb;
        $table = self::table_links();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE ends_at IS NULL OR ends_at > %s ORDER BY position ASC",
            current_time('mysql')
        ));
    }

    public static function insert_link(array $data): int
    {
        global $wpdb;
        $wpdb->insert(self::table_links(), self::link_columns($data));
        return (int) $wpdb->insert_id;
    }

    public static function update_link(int $id, array $data): void
    {
        global $wpdb;
        $wpdb->update(self::table_links(), self::link_columns($data), ['id' => $id]);
    }

    private static function link_columns(array $data): array
    {
        return [
            'link_name' => (string) ($data['link_name'] ?? ''),
            'link_url' => (string) ($data['link_url'] ?? ''),
            'position' => (int) ($data['position'] ?? 0),
            'icon' => $data['icon'] ?? null,
            'link_type' => ($data['link_type'] ?? 'link') === 'header' ? 'header' : 'link',
            'featured' => !empty($data['featured']) ? 1 : 0,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ];
    }

    public static function delete_link(int $id): void
    {
        global $wpdb;
        $wpdb->delete(self::table_links(), ['id' => $id]);
        $wpdb->delete(self::table_logs(), ['link_id' => $id]);
    }

    public static function update_positions(array $ordered_ids): void
    {
        global $wpdb;
        $table = self::table_links();
        foreach ($ordered_ids as $position => $id) {
            $wpdb->update($table, ['position' => $position], ['id' => (int) $id]);
        }
    }

    public static function get_next_position(): int
    {
        global $wpdb;
        $table = self::table_links();
        $max = $wpdb->get_var("SELECT MAX(position) FROM $table");
        return $max !== null ? (int) $max + 1 : 0;
    }

    // --- Click tracking ---

    public static function log_click(int $link_id, ?string $user_agent, string $source): void
    {
        global $wpdb;
        $links = self::table_links();
        $logs = self::table_logs();

        $wpdb->query($wpdb->prepare(
            "UPDATE $links SET click_count = click_count + 1 WHERE id = %d",
            $link_id
        ));

        $wpdb->insert($logs, [
            'link_id' => $link_id,
            'clicked_at' => current_time('mysql'),
            'user_agent' => $user_agent ? mb_substr($user_agent, 0, 500) : null,
            'source' => $source,
        ]);
    }

    public static function log_view(string $source): void
    {
        global $wpdb;
        $wpdb->insert(self::table_views(), [
            'viewed_at' => current_time('mysql'),
            'source' => $source,
        ]);
    }

    public static function link_exists(int $link_id): bool
    {
        global $wpdb;
        $table = self::table_links();
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE id = %d AND link_type = 'link'",
            $link_id
        )) > 0;
    }

    // --- Stats ---

    private static function since(int $days): string
    {
        return wp_date('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
    }

    private static function today_start(): string
    {
        return wp_date('Y-m-d 00:00:00');
    }

    public static function get_daily_series(int $days = 30): array
    {
        global $wpdb;
        $logs = self::table_logs();
        $views = self::table_views();
        $since = wp_date('Y-m-d 00:00:00', time() - ($days - 1) * DAY_IN_SECONDS);

        $clicks = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(clicked_at) AS day, COUNT(*) AS total FROM $logs WHERE clicked_at >= %s GROUP BY DATE(clicked_at)",
            $since
        ));
        $page_views = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(viewed_at) AS day, COUNT(*) AS total FROM $views WHERE viewed_at >= %s GROUP BY DATE(viewed_at)",
            $since
        ));

        $clicks_map = [];
        foreach ($clicks as $row) {
            $clicks_map[$row->day] = (int) $row->total;
        }
        $views_map = [];
        foreach ($page_views as $row) {
            $views_map[$row->day] = (int) $row->total;
        }

        $series = ['labels' => [], 'clicks' => [], 'views' => []];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = wp_date('Y-m-d', time() - $i * DAY_IN_SECONDS);
            $series['labels'][] = $day;
            $series['clicks'][] = $clicks_map[$day] ?? 0;
            $series['views'][] = $views_map[$day] ?? 0;
        }

        return $series;
    }

    public static function get_clicks_per_link(int $days = 30): array
    {
        global $wpdb;
        $links = self::table_links();
        $logs = self::table_logs();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT t.id, t.link_name, COUNT(l.id) as clicks
             FROM $links t
             LEFT JOIN $logs l ON l.link_id = t.id AND l.clicked_at >= %s
             WHERE t.link_type = 'link'
             GROUP BY t.id, t.link_name
             ORDER BY clicks DESC",
            self::since($days)
        ));
    }

    public static function get_sources(int $days = 30): array
    {
        global $wpdb;
        $logs = self::table_logs();
        $views = self::table_views();
        $since = self::since($days);

        $view_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT COALESCE(source, 'direct') AS source, COUNT(*) AS total FROM $views WHERE viewed_at >= %s GROUP BY COALESCE(source, 'direct')",
            $since
        ));
        $click_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT COALESCE(source, 'direct') AS source, COUNT(*) AS total FROM $logs WHERE clicked_at >= %s GROUP BY COALESCE(source, 'direct')",
            $since
        ));

        $sources = [];
        foreach ($view_rows as $row) {
            $sources[$row->source] = ['views' => (int) $row->total, 'clicks' => 0];
        }
        foreach ($click_rows as $row) {
            $sources[$row->source] = [
                'views' => $sources[$row->source]['views'] ?? 0,
                'clicks' => (int) $row->total,
            ];
        }

        uasort($sources, static fn(array $a, array $b): int => ($b['views'] <=> $a['views']) ?: ($b['clicks'] <=> $a['clicks']));

        return $sources;
    }

    public static function get_total_clicks_today(): int
    {
        global $wpdb;
        $table = self::table_logs();
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE clicked_at >= %s",
            self::today_start()
        ));
    }

    public static function get_total_clicks_week(): int
    {
        return self::count_since(self::table_logs(), 'clicked_at', self::since(7));
    }

    public static function get_total_clicks_month(): int
    {
        return self::count_since(self::table_logs(), 'clicked_at', self::since(30));
    }

    public static function get_views_since_days(int $days): int
    {
        return self::count_since(self::table_views(), 'viewed_at', self::since($days));
    }

    public static function get_total_views(): int
    {
        global $wpdb;
        $table = self::table_views();
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    private static function count_since(string $table, string $column, string $since): int
    {
        global $wpdb;
        $column = esc_sql($column);
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE $column >= %s",
            $since
        ));
    }

    public static function get_total_clicks(): int
    {
        global $wpdb;
        $table = self::table_links();
        return (int) $wpdb->get_var("SELECT COALESCE(SUM(click_count), 0) FROM $table");
    }

    // --- Import depuis Click Tracker v2 ---

    public static function can_import_from_click_tracker(): bool
    {
        global $wpdb;
        $old_table = $wpdb->prefix . 'click_tracker';
        return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $old_table)) === $old_table;
    }

    public static function import_from_click_tracker(): int
    {
        global $wpdb;
        $old_table = $wpdb->prefix . 'click_tracker';
        $new_table = self::table_links();

        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $new_table");
        if ($count > 0) {
            return 0;
        }

        $old_links = $wpdb->get_results("SELECT link_name, link_url, icon, position, click_count FROM $old_table ORDER BY position ASC");

        $imported = 0;
        foreach ($old_links as $link) {
            $wpdb->insert($new_table, [
                'link_name' => $link->link_name,
                'link_url' => $link->link_url,
                'icon' => $link->icon,
                'position' => (int) $link->position,
                'click_count' => (int) $link->click_count,
            ]);
            $imported++;
        }

        return $imported;
    }
}
