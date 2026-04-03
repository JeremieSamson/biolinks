<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class BioLinks_DB
{
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

    public static function activate(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $config = self::table_config();
        $links = self::table_links();
        $logs = self::table_logs();

        $wpdb->query("CREATE TABLE IF NOT EXISTS $config (
            option_name varchar(100) NOT NULL,
            option_value longtext NOT NULL,
            PRIMARY KEY (option_name)
        ) $charset");

        $wpdb->query("CREATE TABLE IF NOT EXISTS $links (
            id int(11) NOT NULL AUTO_INCREMENT,
            link_name varchar(255) NOT NULL,
            link_url varchar(500) NOT NULL,
            icon varchar(50) DEFAULT NULL,
            position int(11) NOT NULL DEFAULT 0,
            click_count int(11) DEFAULT 0,
            PRIMARY KEY (id)
        ) $charset");

        $wpdb->query("CREATE TABLE IF NOT EXISTS $logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            link_id int(11) NOT NULL,
            clicked_at datetime NOT NULL,
            user_agent varchar(500) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_link_date (link_id, clicked_at)
        ) $charset");

        $defaults = [
            'photo_url' => '',
            'title' => get_bloginfo('name'),
            'bio' => '',
            'slug' => 'links',
            'page_id' => '0',
            'template' => 'dark',
            'accent_color' => '#0a7286',
            'socials' => '{}',
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

        self::ensure_page();
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

    public static function insert_link(string $name, string $url, int $position, ?string $icon = null): int
    {
        global $wpdb;
        $wpdb->insert(self::table_links(), [
            'link_name' => $name,
            'link_url' => $url,
            'position' => $position,
            'icon' => $icon,
        ]);
        return (int) $wpdb->insert_id;
    }

    public static function update_link(int $id, string $name, string $url, int $position, ?string $icon = null): void
    {
        global $wpdb;
        $wpdb->update(self::table_links(), [
            'link_name' => $name,
            'link_url' => $url,
            'position' => $position,
            'icon' => $icon,
        ], ['id' => $id]);
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

    public static function log_click(int $link_id, ?string $user_agent): void
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
        ]);
    }

    // --- Stats ---

    public static function get_clicks_per_day(int $days = 30): array
    {
        global $wpdb;
        $table = self::table_logs();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(clicked_at) as date, COUNT(*) as clicks
             FROM $table
             WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY DATE(clicked_at)
             ORDER BY date ASC",
            $days
        ));
    }

    public static function get_clicks_per_link(int $days = 30): array
    {
        global $wpdb;
        $links = self::table_links();
        $logs = self::table_logs();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT t.id, t.link_name, COUNT(l.id) as clicks
             FROM $links t
             LEFT JOIN $logs l ON l.link_id = t.id AND l.clicked_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY t.id, t.link_name
             ORDER BY clicks DESC",
            $days
        ));
    }

    public static function get_total_clicks_today(): int
    {
        global $wpdb;
        $table = self::table_logs();
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE DATE(clicked_at) = CURDATE()"
        );
    }

    public static function get_total_clicks_week(): int
    {
        global $wpdb;
        $table = self::table_logs();
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
    }

    public static function get_total_clicks_month(): int
    {
        global $wpdb;
        $table = self::table_logs();
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
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
        return $wpdb->get_var("SHOW TABLES LIKE '$old_table'") === $old_table;
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
