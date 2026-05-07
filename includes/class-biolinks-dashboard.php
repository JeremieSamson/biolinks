<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class BioLinks_Dashboard
{
    public function __construct()
    {
        add_action('wp_dashboard_setup', [$this, 'register_widget']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_widget(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'biolinks_dashboard_stats',
            __('BioLinks: click stats', 'biolinks'),
            [$this, 'render']
        );
    }

    public function enqueue_assets(string $hook): void
    {
        if ($hook !== 'index.php') {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_enqueue_script(
            'chartjs',
            BIOLINKS_URL . 'assets/vendor/chart.umd.min.js',
            [],
            '4.4.8',
            true
        );
        wp_enqueue_style(
            'biolinks-dashboard',
            BIOLINKS_URL . 'assets/dashboard.css',
            [],
            BIOLINKS_VERSION
        );
    }

    public function render(): void
    {
        $stats = [
            'today' => BioLinks_DB::get_total_clicks_today(),
            'week'  => BioLinks_DB::get_total_clicks_week(),
            'month' => BioLinks_DB::get_total_clicks_month(),
            'total' => BioLinks_DB::get_total_clicks(),
        ];

        $stats_url = admin_url('admin.php?page=biolinks&tab=stats');

        if ($stats['total'] === 0) {
            echo '<p>' . esc_html__('No clicks tracked yet. Once visitors start clicking your links, stats will appear here.', 'biolinks') . '</p>';
            echo '<p class="bl-dash-foot"><a href="' . esc_url($stats_url) . '">' . esc_html__('Open BioLinks', 'biolinks') . ' &rarr;</a></p>';
            return;
        }

        $clicks_per_day = BioLinks_DB::get_clicks_per_day(30);
        $clicks_per_link = BioLinks_DB::get_clicks_per_link(30);

        $by_date = [];
        foreach ($clicks_per_day as $row) {
            $by_date[$row->date] = (int) $row->clicks;
        }
        $labels = [];
        $values = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = wp_date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = $d;
            $values[] = $by_date[$d] ?? 0;
        }

        $top_links = [];
        foreach ($clicks_per_link as $row) {
            if ((int) $row->clicks > 0) {
                $top_links[] = $row;
            }
            if (count($top_links) >= 3) {
                break;
            }
        }
        ?>
        <div class="bl-dash">
            <div class="bl-dash-kpis">
                <div class="bl-dash-kpi"><span><?php echo (int) $stats['today']; ?></span><em><?php esc_html_e('Today', 'biolinks'); ?></em></div>
                <div class="bl-dash-kpi"><span><?php echo (int) $stats['week']; ?></span><em><?php esc_html_e('7 days', 'biolinks'); ?></em></div>
                <div class="bl-dash-kpi"><span><?php echo (int) $stats['month']; ?></span><em><?php esc_html_e('30 days', 'biolinks'); ?></em></div>
                <div class="bl-dash-kpi"><span><?php echo (int) $stats['total']; ?></span><em><?php esc_html_e('All time', 'biolinks'); ?></em></div>
            </div>

            <div class="bl-dash-chart">
                <canvas id="biolinks-dash-spark"></canvas>
            </div>

            <?php if (!empty($top_links)): ?>
                <h4 class="bl-dash-h4"><?php esc_html_e('Top links (30 days)', 'biolinks'); ?></h4>
                <ol class="bl-dash-top">
                    <?php foreach ($top_links as $row): ?>
                        <li>
                            <span class="bl-dash-top-name"><?php echo esc_html($row->link_name); ?></span>
                            <span class="bl-dash-top-count"><?php echo (int) $row->clicks; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

            <p class="bl-dash-foot">
                <a href="<?php echo esc_url($stats_url); ?>"><?php esc_html_e('View full stats', 'biolinks'); ?> &rarr;</a>
            </p>
        </div>
        <?php

        $payload = wp_json_encode([
            'labels' => $labels,
            'values' => array_map('intval', $values),
        ]);

        $js = "(function(){var c=document.getElementById('biolinks-dash-spark');if(!c)return;var d={$payload};new Chart(c,{type:'line',data:{labels:d.labels,datasets:[{data:d.values,borderColor:'#2271b1',backgroundColor:'rgba(34,113,177,0.12)',borderWidth:2,fill:true,tension:0.3,pointRadius:0,pointHoverRadius:4}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{displayColors:false,callbacks:{title:function(i){return i[0].label;},label:function(i){var n=i.parsed.y;return n+' click'+(n===1?'':'s');}}}},scales:{x:{display:false},y:{display:false,beginAtZero:true}}}});})();";

        wp_add_inline_script('chartjs', $js);
    }
}
