<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class BioLinks_Admin
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_biolinks_reorder', [$this, 'handle_reorder']);
        add_action('wp_ajax_biolinks_stats', [$this, 'handle_stats']);
        add_action('wp_ajax_biolinks_import', [$this, 'handle_import']);
    }

    public function add_menu(): void
    {
        add_menu_page(
            'BioLinks',
            'BioLinks',
            'manage_options',
            'biolinks',
            [$this, 'render_page'],
            'dashicons-admin-links'
        );
    }

    public function enqueue_assets(string $hook): void
    {
        if ($hook !== 'toplevel_page_biolinks') {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js',
            [],
            '4',
            true
        );
        wp_enqueue_script(
            'sortablejs',
            'https://cdn.jsdelivr.net/npm/sortablejs@1/Sortable.min.js',
            [],
            '1',
            true
        );
        wp_enqueue_style(
            'biolinks-admin',
            BIOLINKS_URL . 'assets/admin.css',
            ['wp-color-picker'],
            BIOLINKS_VERSION
        );
        wp_enqueue_script(
            'biolinks-admin',
            BIOLINKS_URL . 'assets/admin.js',
            ['chartjs', 'sortablejs', 'wp-color-picker', 'jquery'],
            BIOLINKS_VERSION,
            true
        );
        wp_localize_script('biolinks-admin', 'biolinksAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('biolinks_admin_nonce'),
        ]);
    }

    public function render_page(): void
    {
        $this->handle_form_submit();

        $config = BioLinks_DB::get_all_config();
        $links = BioLinks_DB::get_all_links();
        $editing = null;
        if (isset($_GET['edit'])) {
            $editing = BioLinks_DB::get_link((int) $_GET['edit']);
        }

        $tab = $_GET['tab'] ?? 'page';
        $page_id = (int) ($config['page_id'] ?? 0);
        $page_url = $page_id > 0 ? get_permalink($page_id) : '';
        $socials = json_decode($config['socials'] ?? '{}', true) ?: [];
        $can_import = BioLinks_DB::can_import_from_click_tracker();

        $stats = [
            'today' => BioLinks_DB::get_total_clicks_today(),
            'week' => BioLinks_DB::get_total_clicks_week(),
            'month' => BioLinks_DB::get_total_clicks_month(),
            'total' => BioLinks_DB::get_total_clicks(),
        ];
        $clicks_per_day = BioLinks_DB::get_clicks_per_day(30);
        $clicks_per_link = BioLinks_DB::get_clicks_per_link(30);

        ?>
        <div class="wrap biolinks-admin">
            <h1>BioLinks <?php if ($page_url): ?><a href="<?php echo esc_url($page_url); ?>" target="_blank" class="page-title-action">Voir la page</a><?php endif; ?></h1>

            <?php if (isset($_GET['imported'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo (int) $_GET['imported']; ?> lien(s) importé(s) depuis Click Tracker.</p>
                </div>
            <?php endif; ?>

            <nav class="nav-tab-wrapper">
                <a href="?page=biolinks&tab=page" class="nav-tab <?php echo $tab === 'page' ? 'nav-tab-active' : ''; ?>">Ma page</a>
                <a href="?page=biolinks&tab=appearance" class="nav-tab <?php echo $tab === 'appearance' ? 'nav-tab-active' : ''; ?>">Apparence</a>
                <a href="?page=biolinks&tab=stats" class="nav-tab <?php echo $tab === 'stats' ? 'nav-tab-active' : ''; ?>">Statistiques</a>
            </nav>

            <?php if ($tab === 'page'): ?>
                <?php $this->render_tab_page($config, $links, $editing, $socials, $can_import); ?>
            <?php elseif ($tab === 'appearance'): ?>
                <?php $this->render_tab_appearance($config); ?>
            <?php elseif ($tab === 'stats'): ?>
                <?php $this->render_tab_stats($stats, $clicks_per_day, $clicks_per_link); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_tab_page(array $config, array $links, ?object $editing, array $socials, bool $can_import): void
    {
        $clicks_per_link = BioLinks_DB::get_clicks_per_link(30);
        $clicks_30d_map = [];
        foreach ($clicks_per_link as $cl) {
            $clicks_30d_map[$cl->id] = (int) $cl->clicks;
        }
        ?>
        <!-- Import banner -->
        <?php if ($can_import && empty($links)): ?>
        <div class="bl-import-banner">
            <p><strong>Click Tracker détecté !</strong> Importer les liens existants ?</p>
            <button type="button" class="button button-primary" id="bl-import-btn">Importer les liens</button>
        </div>
        <?php endif; ?>

        <!-- Profile section -->
        <div class="bl-section">
            <h2>Profil</h2>
            <form method="post">
                <?php wp_nonce_field('biolinks_save_profile', 'bl_profile_nonce'); ?>
                <input type="hidden" name="bl_action" value="save_profile">
                <table class="form-table">
                    <tr>
                        <th><label>Photo de profil</label></th>
                        <td>
                            <div class="bl-photo-upload">
                                <img src="<?php echo esc_url($config['photo_url'] ?? ''); ?>" alt="" class="bl-photo-preview" id="bl-photo-preview" style="<?php echo empty($config['photo_url']) ? 'display:none;' : ''; ?>">
                                <input type="hidden" name="photo_url" id="bl-photo-url" value="<?php echo esc_attr($config['photo_url'] ?? ''); ?>">
                                <button type="button" class="button" id="bl-photo-upload-btn">Choisir une image</button>
                                <button type="button" class="button bl-photo-remove" id="bl-photo-remove-btn" style="<?php echo empty($config['photo_url']) ? 'display:none;' : ''; ?>">Supprimer</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="bl-title">Titre</label></th>
                        <td><input type="text" name="title" id="bl-title" class="regular-text" value="<?php echo esc_attr($config['title'] ?? ''); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="bl-bio">Bio</label></th>
                        <td>
                            <textarea name="bio" id="bl-bio" class="regular-text" rows="2" maxlength="160"><?php echo esc_textarea($config['bio'] ?? ''); ?></textarea>
                            <p class="description">Max 160 caractères. <span id="bl-bio-count"><?php echo mb_strlen($config['bio'] ?? ''); ?></span>/160</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="bl-slug">Slug de la page</label></th>
                        <td>
                            <code><?php echo esc_html(home_url('/')); ?></code><input type="text" name="slug" id="bl-slug" value="<?php echo esc_attr($config['slug'] ?? 'links'); ?>" class="small-text" style="width:150px">
                        </td>
                    </tr>
                </table>
                <?php submit_button('Enregistrer le profil'); ?>
            </form>
        </div>

        <!-- Social networks section -->
        <div class="bl-section">
            <h2>Réseaux sociaux</h2>
            <form method="post">
                <?php wp_nonce_field('biolinks_save_socials', 'bl_socials_nonce'); ?>
                <input type="hidden" name="bl_action" value="save_socials">
                <table class="form-table">
                    <?php foreach (BIOLINKS_SOCIAL_KEYS as $key): ?>
                    <tr>
                        <th><label for="bl-social-<?php echo esc_attr($key); ?>"><?php echo esc_html(BIOLINKS_SOCIAL_LABELS[$key]); ?></label></th>
                        <td><input type="url" name="socials[<?php echo esc_attr($key); ?>]" id="bl-social-<?php echo esc_attr($key); ?>" class="regular-text" value="<?php echo esc_attr($socials[$key] ?? ''); ?>" placeholder="https://"></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php submit_button('Enregistrer les réseaux'); ?>
            </form>
        </div>

        <!-- Links section -->
        <div class="bl-section">
            <h2><?php echo $editing ? 'Modifier le lien' : 'Ajouter un lien'; ?></h2>
            <form method="post" id="bl-link-form">
                <?php wp_nonce_field('biolinks_save_link', 'bl_link_nonce'); ?>
                <input type="hidden" name="bl_action" value="save_link">
                <input type="hidden" name="link_id" id="bl-link-id" value="<?php echo $editing ? (int) $editing->id : 0; ?>">
                <table class="form-table">
                    <tr>
                        <th><label for="bl-link-name">Nom du lien</label></th>
                        <td><input type="text" name="link_name" id="bl-link-name" class="regular-text" required value="<?php echo $editing ? esc_attr($editing->link_name) : ''; ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="bl-link-url">URL</label></th>
                        <td><input type="url" name="link_url" id="bl-link-url" class="regular-text" required value="<?php echo $editing ? esc_attr($editing->link_url) : ''; ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="bl-link-icon">Icône</label></th>
                        <td>
                            <select name="link_icon" id="bl-link-icon">
                                <?php foreach (BIOLINKS_GENERIC_LABELS as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($editing ? ($editing->icon ?? '') : '', $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="bl-link-position">Position</label></th>
                        <td><input type="number" name="link_position" id="bl-link-position" value="<?php echo $editing ? (int) $editing->position : BioLinks_DB::get_next_position(); ?>" min="0"></td>
                    </tr>
                </table>
                <?php submit_button($editing ? 'Mettre à jour' : 'Ajouter', 'primary', 'submit'); ?>
                <?php if ($editing): ?>
                    <a href="?page=biolinks&tab=page" class="button">Annuler</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Links table -->
        <div class="bl-section">
            <h2>Liens</h2>
            <table class="widefat bl-links-table">
                <thead>
                    <tr>
                        <th class="bl-col-drag"></th>
                        <th>Icône</th>
                        <th>Nom</th>
                        <th>URL</th>
                        <th>Clics (30j)</th>
                        <th>Clics (total)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="bl-sortable-links">
                    <?php foreach ($links as $link): ?>
                        <tr data-id="<?php echo (int) $link->id; ?>">
                            <td class="bl-col-drag"><span class="bl-drag-handle dashicons dashicons-menu"></span></td>
                            <td><?php echo esc_html($link->icon ?? '--'); ?></td>
                            <td><strong><?php echo esc_html($link->link_name); ?></strong></td>
                            <td><a href="<?php echo esc_url($link->link_url); ?>" target="_blank"><?php echo esc_html(mb_strimwidth($link->link_url, 0, 50, '...')); ?></a></td>
                            <td><?php echo $clicks_30d_map[$link->id] ?? 0; ?></td>
                            <td><?php echo (int) $link->click_count; ?></td>
                            <td>
                                <a href="?page=biolinks&tab=page&edit=<?php echo (int) $link->id; ?>" class="button button-small">Modifier</a>
                                <form method="post" style="display:inline">
                                    <?php wp_nonce_field('biolinks_delete_' . $link->id, 'bl_delete_nonce'); ?>
                                    <input type="hidden" name="bl_action" value="delete_link">
                                    <input type="hidden" name="link_id" value="<?php echo (int) $link->id; ?>">
                                    <button type="submit" class="button button-small button-link-delete" onclick="return confirm('Supprimer ce lien ?');">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_tab_appearance(array $config): void
    {
        $current_template = $config['template'] ?? 'dark';
        $accent_color = $config['accent_color'] ?? '#0a7286';

        $templates = [
            'dark' => ['name' => 'Sombre', 'desc' => 'Dégradé sombre, boutons transparents'],
            'light' => ['name' => 'Clair', 'desc' => 'Fond blanc, bordures grises, clean'],
            'minimal' => ['name' => 'Minimal', 'desc' => 'Couleur unie, boutons outline'],
            'colorful' => ['name' => 'Coloré', 'desc' => 'Fond vif, boutons blancs'],
            'glass' => ['name' => 'Glass', 'desc' => 'Glassmorphism, flou et transparence'],
        ];
        ?>
        <form method="post">
            <?php wp_nonce_field('biolinks_save_appearance', 'bl_appearance_nonce'); ?>
            <input type="hidden" name="bl_action" value="save_appearance">

            <div class="bl-section">
                <h2>Template</h2>
                <div class="bl-templates-grid">
                    <?php foreach ($templates as $key => $tpl): ?>
                    <label class="bl-template-card <?php echo $current_template === $key ? 'bl-template-active' : ''; ?>">
                        <input type="radio" name="template" value="<?php echo esc_attr($key); ?>" <?php checked($current_template, $key); ?>>
                        <div class="bl-template-preview bl-template-preview-<?php echo esc_attr($key); ?>">
                            <div class="bl-preview-circle"></div>
                            <div class="bl-preview-line bl-preview-line-long"></div>
                            <div class="bl-preview-line"></div>
                            <div class="bl-preview-btn"></div>
                            <div class="bl-preview-btn"></div>
                            <div class="bl-preview-btn"></div>
                        </div>
                        <span class="bl-template-name"><?php echo esc_html($tpl['name']); ?></span>
                        <span class="bl-template-desc"><?php echo esc_html($tpl['desc']); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bl-section">
                <h2>Couleur d'accent</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="bl-accent-color">Couleur</label></th>
                        <td><input type="text" name="accent_color" id="bl-accent-color" value="<?php echo esc_attr($accent_color); ?>" class="bl-color-picker" data-default-color="#0a7286"></td>
                    </tr>
                </table>
            </div>

            <?php submit_button('Enregistrer l\'apparence'); ?>
        </form>
        <?php
    }

    private function render_tab_stats(array $stats, array $clicks_per_day, array $clicks_per_link): void
    {
        ?>
        <div class="bl-stats-cards">
            <div class="bl-stat-card">
                <span class="bl-stat-value"><?php echo (int) $stats['today']; ?></span>
                <span class="bl-stat-label">Aujourd'hui</span>
            </div>
            <div class="bl-stat-card">
                <span class="bl-stat-value"><?php echo (int) $stats['week']; ?></span>
                <span class="bl-stat-label">7 derniers jours</span>
            </div>
            <div class="bl-stat-card">
                <span class="bl-stat-value"><?php echo (int) $stats['month']; ?></span>
                <span class="bl-stat-label">30 derniers jours</span>
            </div>
            <div class="bl-stat-card">
                <span class="bl-stat-value"><?php echo (int) $stats['total']; ?></span>
                <span class="bl-stat-label">Total</span>
            </div>
        </div>

        <div class="bl-charts">
            <div class="bl-chart-container">
                <div class="bl-chart-header">
                    <h2>Clics par jour</h2>
                    <select id="bl-period-select">
                        <option value="7">7 jours</option>
                        <option value="30" selected>30 jours</option>
                        <option value="90">90 jours</option>
                    </select>
                </div>
                <div class="bl-chart-wrap"><canvas id="bl-chart-daily"></canvas></div>
            </div>
            <div class="bl-chart-container">
                <h2>Clics par lien</h2>
                <div class="bl-chart-wrap"><canvas id="bl-chart-links"></canvas></div>
            </div>
        </div>

        <script>
            var blChartData = {
                daily: {
                    labels: <?php echo wp_json_encode(array_map(fn($r) => $r->date, $clicks_per_day)); ?>,
                    values: <?php echo wp_json_encode(array_map(fn($r) => (int) $r->clicks, $clicks_per_day)); ?>
                },
                links: {
                    labels: <?php echo wp_json_encode(array_map(fn($r) => $r->link_name, $clicks_per_link)); ?>,
                    values: <?php echo wp_json_encode(array_map(fn($r) => (int) $r->clicks, $clicks_per_link)); ?>
                }
            };
        </script>
        <?php
    }

    private function handle_form_submit(): void
    {
        if (!isset($_POST['bl_action'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé');
        }

        $action = $_POST['bl_action'];

        if ($action === 'save_profile') {
            if (!wp_verify_nonce($_POST['bl_profile_nonce'] ?? '', 'biolinks_save_profile')) {
                wp_die('Nonce invalide');
            }

            BioLinks_DB::set_config('photo_url', esc_url_raw($_POST['photo_url'] ?? ''));
            BioLinks_DB::set_config('title', sanitize_text_field($_POST['title'] ?? ''));
            BioLinks_DB::set_config('bio', sanitize_text_field($_POST['bio'] ?? ''));

            $new_slug = sanitize_title($_POST['slug'] ?? 'links');
            $old_slug = BioLinks_DB::get_config('slug');
            BioLinks_DB::set_config('slug', $new_slug);

            if ($new_slug !== $old_slug) {
                $page_id = (int) BioLinks_DB::get_config('page_id');
                if ($page_id > 0) {
                    wp_update_post(['ID' => $page_id, 'post_name' => $new_slug]);
                }
            }

            BioLinks_DB::ensure_page();

            wp_safe_redirect(admin_url('admin.php?page=biolinks&tab=page'));
            exit;
        }

        if ($action === 'save_socials') {
            if (!wp_verify_nonce($_POST['bl_socials_nonce'] ?? '', 'biolinks_save_socials')) {
                wp_die('Nonce invalide');
            }

            $socials = [];
            foreach (BIOLINKS_SOCIAL_KEYS as $key) {
                $url = esc_url_raw($_POST['socials'][$key] ?? '');
                if (!empty($url)) {
                    $socials[$key] = $url;
                }
            }
            BioLinks_DB::set_config('socials', wp_json_encode($socials));

            wp_safe_redirect(admin_url('admin.php?page=biolinks&tab=page'));
            exit;
        }

        if ($action === 'save_link') {
            if (!wp_verify_nonce($_POST['bl_link_nonce'] ?? '', 'biolinks_save_link')) {
                wp_die('Nonce invalide');
            }

            $id = (int) ($_POST['link_id'] ?? 0);
            $name = sanitize_text_field($_POST['link_name'] ?? '');
            $url = esc_url_raw($_POST['link_url'] ?? '');
            $position = (int) ($_POST['link_position'] ?? 0);
            $icon = sanitize_text_field($_POST['link_icon'] ?? '');
            if ($icon !== '' && !array_key_exists($icon, BIOLINKS_GENERIC_LABELS)) {
                $icon = '';
            }
            $icon = $icon !== '' ? $icon : null;

            if ($id > 0) {
                BioLinks_DB::update_link($id, $name, $url, $position, $icon);
            } else {
                BioLinks_DB::insert_link($name, $url, $position, $icon);
            }

            wp_safe_redirect(admin_url('admin.php?page=biolinks&tab=page'));
            exit;
        }

        if ($action === 'delete_link') {
            $id = (int) ($_POST['link_id'] ?? 0);
            if (!wp_verify_nonce($_POST['bl_delete_nonce'] ?? '', 'biolinks_delete_' . $id)) {
                wp_die('Nonce invalide');
            }

            BioLinks_DB::delete_link($id);

            wp_safe_redirect(admin_url('admin.php?page=biolinks&tab=page'));
            exit;
        }

        if ($action === 'save_appearance') {
            if (!wp_verify_nonce($_POST['bl_appearance_nonce'] ?? '', 'biolinks_save_appearance')) {
                wp_die('Nonce invalide');
            }

            $template = sanitize_text_field($_POST['template'] ?? 'dark');
            $allowed = ['dark', 'light', 'minimal', 'colorful', 'glass'];
            if (!in_array($template, $allowed, true)) {
                $template = 'dark';
            }
            BioLinks_DB::set_config('template', $template);

            $color = sanitize_hex_color($_POST['accent_color'] ?? '#0a7286');
            BioLinks_DB::set_config('accent_color', $color ?: '#0a7286');

            wp_safe_redirect(admin_url('admin.php?page=biolinks&tab=appearance'));
            exit;
        }
    }

    public function handle_reorder(): void
    {
        check_ajax_referer('biolinks_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Accès refusé', 403);
        }

        $order = isset($_POST['order']) ? array_map('intval', $_POST['order']) : [];
        if (empty($order)) {
            wp_send_json_error('Pas de données', 400);
        }

        BioLinks_DB::update_positions($order);
        wp_send_json_success();
    }

    public function handle_stats(): void
    {
        check_ajax_referer('biolinks_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Accès refusé', 403);
        }

        $days = isset($_POST['days']) ? (int) $_POST['days'] : 30;
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        $daily = BioLinks_DB::get_clicks_per_day($days);
        $per_link = BioLinks_DB::get_clicks_per_link($days);

        wp_send_json_success([
            'daily' => [
                'labels' => array_map(fn($r) => $r->date, $daily),
                'values' => array_map(fn($r) => (int) $r->clicks, $daily),
            ],
            'links' => [
                'labels' => array_map(fn($r) => $r->link_name, $per_link),
                'values' => array_map(fn($r) => (int) $r->clicks, $per_link),
            ],
        ]);
    }

    public function handle_import(): void
    {
        check_ajax_referer('biolinks_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Accès refusé', 403);
        }

        $count = BioLinks_DB::import_from_click_tracker();
        wp_send_json_success(['imported' => $count]);
    }
}
