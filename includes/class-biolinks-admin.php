<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class BioLinks_Admin
{
    private const SUPPORT_MIN_CLICKS = 25;

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('load-toplevel_page_biolinks', [$this, 'handle_form_submit']);
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
            BIOLINKS_URL . 'assets/vendor/chart.umd.min.js',
            [],
            '4.4.8',
            true
        );
        wp_enqueue_script(
            'sortablejs',
            BIOLINKS_URL . 'assets/vendor/Sortable.min.js',
            [],
            '1.15.7',
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
            ['chartjs', 'sortablejs', 'wp-color-picker', 'jquery', 'wp-i18n'],
            BIOLINKS_VERSION,
            true
        );
        wp_set_script_translations('biolinks-admin', 'biolinks', BIOLINKS_PATH . 'languages');
        wp_localize_script('biolinks-admin', 'biolinksAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('biolinks_admin_nonce'),
        ]);
    }

    public function render_page(): void
    {
        $config = BioLinks_DB::get_all_config();
        if (empty($config['activated_at'])) {
            $now = (string) time();
            BioLinks_DB::set_config('activated_at', $now);
            $config['activated_at'] = $now;
        }
        $links = BioLinks_DB::get_all_links();
        $editing = null;
        if (isset($_GET['edit'])) {
            $editing = BioLinks_DB::get_link(absint(wp_unslash($_GET['edit'])));
        }

        $tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'page';
        $page_id = (int) ($config['page_id'] ?? 0);
        $page_url = $page_id > 0 ? get_permalink($page_id) : '';
        $socials = json_decode($config['socials'] ?? '{}', true) ?: [];
        $can_import = BioLinks_DB::can_import_from_click_tracker();

        $stats = [
            'views' => BioLinks_DB::get_views_since_days(30),
            'month' => BioLinks_DB::get_total_clicks_month(),
            'week' => BioLinks_DB::get_total_clicks_week(),
            'total' => BioLinks_DB::get_total_clicks(),
            'has_views' => BioLinks_DB::get_total_views() > 0,
        ];
        $clicks_per_link = BioLinks_DB::get_clicks_per_link(30);
        $sources = BioLinks_DB::get_sources(30);

        wp_add_inline_script(
            'biolinks-admin',
            'var blChartData = ' . wp_json_encode($this->chart_payload(30)) . ';',
            'before'
        );

        ?>
        <div class="wrap biolinks-admin">
            <h1>BioLinks <?php if ($page_url): ?><a href="<?php echo esc_url($page_url); ?>" target="_blank" class="page-title-action"><?php esc_html_e('View page', 'biolinks'); ?></a><?php endif; ?></h1>

            <?php if ($this->should_show_support_banner($config, $links)) {
                $this->render_support_banner($config);
            } ?>

            <?php if (isset($_GET['imported'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php
                        printf(
                            /* translators: %d: number of imported links */
                            esc_html__('%d link(s) imported from Click Tracker.', 'biolinks'),
                            absint(wp_unslash($_GET['imported']))
                        );
                    ?></p>
                </div>
            <?php endif; ?>

            <nav class="nav-tab-wrapper">
                <a href="?page=biolinks&tab=page" class="nav-tab <?php echo $tab === 'page' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('My page', 'biolinks'); ?></a>
                <a href="?page=biolinks&tab=appearance" class="nav-tab <?php echo $tab === 'appearance' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Appearance', 'biolinks'); ?></a>
                <a href="?page=biolinks&tab=stats" class="nav-tab <?php echo $tab === 'stats' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Statistics', 'biolinks'); ?></a>
            </nav>

            <?php if ($tab === 'page'): ?>
                <?php $this->render_tab_page($config, $links, $editing, $socials, $can_import); ?>
            <?php elseif ($tab === 'appearance'): ?>
                <?php $this->render_tab_appearance($config); ?>
            <?php elseif ($tab === 'stats'): ?>
                <?php $this->render_tab_stats($stats, $clicks_per_link, $sources); ?>
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
            <p><strong><?php esc_html_e('Click Tracker detected!', 'biolinks'); ?></strong> <?php esc_html_e('Import existing links?', 'biolinks'); ?></p>
            <button type="button" class="button button-primary" id="bl-import-btn"><?php esc_html_e('Import links', 'biolinks'); ?></button>
        </div>
        <?php endif; ?>

        <!-- Profile section -->
        <div class="bl-section">
            <h2><?php esc_html_e('Profile', 'biolinks'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('biolinks_save_profile', 'bl_profile_nonce'); ?>
                <input type="hidden" name="bl_action" value="save_profile">
                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e('Profile photo', 'biolinks'); ?></label></th>
                        <td>
                            <div class="bl-photo-upload">
                                <img src="<?php echo esc_url($config['photo_url'] ?? ''); ?>" alt="" class="bl-photo-preview" id="bl-photo-preview" style="<?php echo empty($config['photo_url']) ? 'display:none;' : ''; ?>">
                                <input type="hidden" name="photo_url" id="bl-photo-url" value="<?php echo esc_attr($config['photo_url'] ?? ''); ?>">
                                <button type="button" class="button" id="bl-photo-upload-btn"><?php esc_html_e('Choose an image', 'biolinks'); ?></button>
                                <button type="button" class="button bl-photo-remove" id="bl-photo-remove-btn" style="<?php echo empty($config['photo_url']) ? 'display:none;' : ''; ?>"><?php esc_html_e('Remove', 'biolinks'); ?></button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="bl-title"><?php esc_html_e('Title', 'biolinks'); ?></label></th>
                        <td><input type="text" name="title" id="bl-title" class="regular-text" value="<?php echo esc_attr($config['title'] ?? ''); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="bl-bio"><?php esc_html_e('Bio', 'biolinks'); ?></label></th>
                        <td>
                            <textarea name="bio" id="bl-bio" class="regular-text" rows="2" maxlength="160"><?php echo esc_textarea($config['bio'] ?? ''); ?></textarea>
                            <p class="description"><?php
                                echo wp_kses(
                                    sprintf(
                                        /* translators: %s: character count HTML element */
                                        __('Max 160 characters. %s/160', 'biolinks'),
                                        '<span id="bl-bio-count">' . esc_html((string) mb_strlen($config['bio'] ?? '')) . '</span>'
                                    ),
                                    ['span' => ['id' => []]]
                                );
                            ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="bl-slug"><?php esc_html_e('Page slug', 'biolinks'); ?></label></th>
                        <td>
                            <code><?php echo esc_html(home_url('/')); ?></code><input type="text" name="slug" id="bl-slug" value="<?php echo esc_attr($config['slug'] ?? 'links'); ?>" class="small-text" style="width:150px">
                        </td>
                    </tr>
                </table>
                <?php submit_button(esc_html__('Save profile', 'biolinks')); ?>
            </form>
        </div>

        <!-- Social networks section -->
        <div class="bl-section">
            <h2><?php esc_html_e('Social networks', 'biolinks'); ?></h2>
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
                <?php submit_button(esc_html__('Save social networks', 'biolinks')); ?>
            </form>
        </div>

        <!-- Links section -->
        <div class="bl-section">
            <h2><?php echo $editing ? esc_html__('Edit link', 'biolinks') : esc_html__('Add a link', 'biolinks'); ?></h2>
            <form method="post" id="bl-link-form">
                <?php wp_nonce_field('biolinks_save_link', 'bl_link_nonce'); ?>
                <input type="hidden" name="bl_action" value="save_link">
                <input type="hidden" name="link_id" id="bl-link-id" value="<?php echo $editing ? (int) $editing->id : 0; ?>">
                <?php $editing_type = $editing ? ($editing->link_type ?? 'link') : 'link'; ?>
                <table class="form-table">
                    <tr>
                        <th><label for="bl-link-type"><?php esc_html_e('Type', 'biolinks'); ?></label></th>
                        <td>
                            <select name="link_type" id="bl-link-type">
                                <option value="link" <?php selected($editing_type, 'link'); ?>><?php esc_html_e('Link', 'biolinks'); ?></option>
                                <option value="header" <?php selected($editing_type, 'header'); ?>><?php esc_html_e('Section title', 'biolinks'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('A section title groups the links placed below it.', 'biolinks'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="bl-link-name"><?php esc_html_e('Link name', 'biolinks'); ?></label></th>
                        <td><input type="text" name="link_name" id="bl-link-name" class="regular-text" required value="<?php echo $editing ? esc_attr($editing->link_name) : ''; ?>"></td>
                    </tr>
                    <tr class="bl-link-only">
                        <th><label for="bl-link-url"><?php esc_html_e('URL', 'biolinks'); ?></label></th>
                        <td><input type="url" name="link_url" id="bl-link-url" class="regular-text" required value="<?php echo $editing ? esc_attr($editing->link_url) : ''; ?>"></td>
                    </tr>
                    <tr class="bl-link-only">
                        <th><label for="bl-link-icon"><?php esc_html_e('Icon', 'biolinks'); ?></label></th>
                        <td>
                            <select name="link_icon" id="bl-link-icon">
                                <?php foreach (biolinks_generic_labels() as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($editing ? ($editing->icon ?? '') : '', $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr class="bl-link-only">
                        <th><?php esc_html_e('Highlight', 'biolinks'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="link_featured" id="bl-link-featured" value="1" <?php checked($editing && !empty($editing->featured)); ?>>
                                <?php esc_html_e('Make this link stand out with the accent color', 'biolinks'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="bl-link-starts"><?php esc_html_e('Schedule', 'biolinks'); ?></label></th>
                        <td>
                            <label for="bl-link-starts"><?php esc_html_e('Show from', 'biolinks'); ?></label>
                            <input type="datetime-local" name="link_starts_at" id="bl-link-starts" value="<?php echo esc_attr($this->datetime_input_value($editing->starts_at ?? null)); ?>">
                            <label for="bl-link-ends"><?php esc_html_e('until', 'biolinks'); ?></label>
                            <input type="datetime-local" name="link_ends_at" id="bl-link-ends" value="<?php echo esc_attr($this->datetime_input_value($editing->ends_at ?? null)); ?>">
                            <p class="description"><?php
                                printf(
                                    /* translators: %s: site timezone, e.g. Europe/Paris */
                                    esc_html__('Optional. Leave empty to always show it. Times use the site timezone (%s).', 'biolinks'),
                                    esc_html(wp_timezone_string())
                                );
                            ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="bl-link-position"><?php esc_html_e('Position', 'biolinks'); ?></label></th>
                        <td><input type="number" name="link_position" id="bl-link-position" value="<?php echo esc_attr((string) ($editing ? $editing->position : BioLinks_DB::get_next_position())); ?>" min="0"></td>
                    </tr>
                </table>
                <?php submit_button($editing ? esc_html__('Update', 'biolinks') : esc_html__('Add', 'biolinks'), 'primary', 'submit'); ?>
                <?php if ($editing): ?>
                    <a href="?page=biolinks&tab=page" class="button"><?php esc_html_e('Cancel', 'biolinks'); ?></a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Links table -->
        <div class="bl-section">
            <h2><?php esc_html_e('Links', 'biolinks'); ?></h2>
            <table class="widefat bl-links-table">
                <thead>
                    <tr>
                        <th class="bl-col-drag"></th>
                        <th><?php esc_html_e('Icon', 'biolinks'); ?></th>
                        <th><?php esc_html_e('Name', 'biolinks'); ?></th>
                        <th><?php esc_html_e('URL', 'biolinks'); ?></th>
                        <th><?php esc_html_e('Clicks (30d)', 'biolinks'); ?></th>
                        <th><?php esc_html_e('Clicks (total)', 'biolinks'); ?></th>
                        <th><?php esc_html_e('Actions', 'biolinks'); ?></th>
                    </tr>
                </thead>
                <tbody id="bl-sortable-links">
                    <?php foreach ($links as $link): ?>
                        <tr data-id="<?php echo (int) $link->id; ?>">
                            <td class="bl-col-drag"><span class="bl-drag-handle dashicons dashicons-menu"></span></td>
                            <?php $is_header = ($link->link_type ?? 'link') === 'header'; ?>
                            <td><?php echo esc_html($is_header ? '--' : ($link->icon ?? '--')); ?></td>
                            <td>
                                <strong><?php echo esc_html($link->link_name); ?></strong>
                                <?php foreach ($this->link_badges($link) as $badge): ?>
                                    <span class="bl-badge bl-badge-<?php echo esc_attr($badge['type']); ?>"><?php echo esc_html($badge['label']); ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td><?php if (!$is_header): ?><a href="<?php echo esc_url($link->link_url); ?>" target="_blank"><?php echo esc_html(mb_strimwidth($link->link_url, 0, 50, '...')); ?></a><?php else: ?>--<?php endif; ?></td>
                            <td><?php echo esc_html($is_header ? '--' : (string) ($clicks_30d_map[$link->id] ?? 0)); ?></td>
                            <td><?php echo esc_html($is_header ? '--' : (string) $link->click_count); ?></td>
                            <td>
                                <a href="?page=biolinks&tab=page&edit=<?php echo (int) $link->id; ?>" class="button button-small"><?php esc_html_e('Edit', 'biolinks'); ?></a>
                                <form method="post" style="display:inline">
                                    <?php wp_nonce_field('biolinks_delete_' . $link->id, 'bl_delete_nonce'); ?>
                                    <input type="hidden" name="bl_action" value="delete_link">
                                    <input type="hidden" name="link_id" value="<?php echo (int) $link->id; ?>">
                                    <button type="submit" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js(__('Delete this link?', 'biolinks')); ?>');"><?php esc_html_e('Delete', 'biolinks'); ?></button>
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
        $show_credit = ($config['show_credit'] ?? '0') === '1';

        $templates = [
            'dark' => ['name' => __('Dark', 'biolinks'), 'desc' => __('Dark gradient, transparent buttons', 'biolinks')],
            'light' => ['name' => __('Light', 'biolinks'), 'desc' => __('White background, gray borders, clean', 'biolinks')],
            'minimal' => ['name' => __('Minimal', 'biolinks'), 'desc' => __('Solid color, outline buttons', 'biolinks')],
            'colorful' => ['name' => __('Colorful', 'biolinks'), 'desc' => __('Vibrant background, white buttons', 'biolinks')],
            'glass' => ['name' => __('Glass', 'biolinks'), 'desc' => __('Glassmorphism, blur and transparency', 'biolinks')],
        ];
        ?>
        <form method="post">
            <?php wp_nonce_field('biolinks_save_appearance', 'bl_appearance_nonce'); ?>
            <input type="hidden" name="bl_action" value="save_appearance">

            <div class="bl-section">
                <h2><?php esc_html_e('Template', 'biolinks'); ?></h2>
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
                <h2><?php esc_html_e('Accent color', 'biolinks'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="bl-accent-color"><?php esc_html_e('Color', 'biolinks'); ?></label></th>
                        <td><input type="text" name="accent_color" id="bl-accent-color" value="<?php echo esc_attr($accent_color); ?>" class="bl-color-picker" data-default-color="#0a7286"></td>
                    </tr>
                </table>
            </div>

            <div class="bl-section">
                <h2><?php esc_html_e('Campaign tracking', 'biolinks'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="bl-utm-enabled"><?php esc_html_e('UTM parameters', 'biolinks'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="utm_enabled" id="bl-utm-enabled" value="1" <?php checked(($config['utm_enabled'] ?? '0') === '1'); ?>>
                                <?php esc_html_e('Add utm_source, utm_medium and utm_content to outgoing links', 'biolinks'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Lets the analytics of the destination site (Google Analytics, Matomo, Plausible...) attribute the visit to your link in bio. Links to your own site and links that already carry UTM parameters are left untouched. utm_content is the link name.', 'biolinks'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="bl-utm-source">utm_source</label></th>
                        <td><input type="text" name="utm_source" id="bl-utm-source" class="regular-text" value="<?php echo esc_attr($config['utm_source'] ?? 'biolinks'); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="bl-utm-medium">utm_medium</label></th>
                        <td><input type="text" name="utm_medium" id="bl-utm-medium" class="regular-text" value="<?php echo esc_attr($config['utm_medium'] ?? 'link_in_bio'); ?>"></td>
                    </tr>
                </table>
            </div>

            <div class="bl-section">
                <h2><?php esc_html_e('Newsletter form', 'biolinks'); ?></h2>
                <p class="description"><?php esc_html_e('Shows an email field below your links. The form posts straight to your email provider (Mailchimp, Brevo, MailerLite, Kit...): BioLinks never stores or sends the address itself. Copy the form action URL and the email field name from the embed code your provider gives you.', 'biolinks'); ?></p>
                <table class="form-table">
                    <tr>
                        <th><label for="bl-newsletter-enabled"><?php esc_html_e('Display', 'biolinks'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="newsletter_enabled" id="bl-newsletter-enabled" value="1" <?php checked(($config['newsletter_enabled'] ?? '0') === '1'); ?>>
                                <?php esc_html_e('Show the newsletter form', 'biolinks'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="bl-newsletter-title"><?php esc_html_e('Heading', 'biolinks'); ?></label></th>
                        <td><input type="text" name="newsletter_title" id="bl-newsletter-title" class="regular-text" value="<?php echo esc_attr($config['newsletter_title'] ?? ''); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="bl-newsletter-action"><?php esc_html_e('Form action URL', 'biolinks'); ?></label></th>
                        <td><input type="url" name="newsletter_action" id="bl-newsletter-action" class="large-text" value="<?php echo esc_attr($config['newsletter_action'] ?? ''); ?>" placeholder="https://"></td>
                    </tr>
                    <tr>
                        <th><label for="bl-newsletter-field"><?php esc_html_e('Email field name', 'biolinks'); ?></label></th>
                        <td><input type="text" name="newsletter_field" id="bl-newsletter-field" class="regular-text" value="<?php echo esc_attr($config['newsletter_field'] ?? 'EMAIL'); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="bl-newsletter-hidden"><?php esc_html_e('Hidden fields', 'biolinks'); ?></label></th>
                        <td>
                            <textarea name="newsletter_hidden" id="bl-newsletter-hidden" class="large-text code" rows="3"><?php echo esc_textarea($config['newsletter_hidden'] ?? ''); ?></textarea>
                            <p class="description"><?php esc_html_e('Optional. One name=value per line, for the hidden inputs of your provider embed code.', 'biolinks'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="bl-newsletter-placeholder"><?php esc_html_e('Placeholder', 'biolinks'); ?></label></th>
                        <td><input type="text" name="newsletter_placeholder" id="bl-newsletter-placeholder" class="regular-text" value="<?php echo esc_attr($config['newsletter_placeholder'] ?? ''); ?>" placeholder="<?php esc_attr_e('Your email address', 'biolinks'); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="bl-newsletter-button"><?php esc_html_e('Button label', 'biolinks'); ?></label></th>
                        <td><input type="text" name="newsletter_button" id="bl-newsletter-button" class="regular-text" value="<?php echo esc_attr($config['newsletter_button'] ?? ''); ?>" placeholder="<?php esc_attr_e('Subscribe', 'biolinks'); ?>"></td>
                    </tr>
                </table>
            </div>

            <div class="bl-section">
                <h2><?php esc_html_e('Footer', 'biolinks'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="bl-show-credit"><?php esc_html_e('Footer credit', 'biolinks'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="show_credit" id="bl-show-credit" value="1" <?php checked($show_credit); ?>>
                                <?php esc_html_e('Display a "Powered by BioLinks" link at the bottom of the page.', 'biolinks'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Disabled by default. Opt-in only.', 'biolinks'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <?php submit_button(esc_html__('Save appearance', 'biolinks')); ?>
        </form>
        <?php
    }

    private function render_tab_stats(array $stats, array $clicks_per_link, array $sources): void
    {
        $ctr = $stats['views'] > 0 ? $this->format_rate($stats['month'], $stats['views']) : '--';
        ?>
        <?php if (!$stats['has_views']): ?>
        <div class="notice notice-info inline"><p><?php esc_html_e('Page views and traffic sources are recorded since BioLinks 1.2.0. They will fill in as visitors open your page.', 'biolinks'); ?></p></div>
        <?php endif; ?>

        <div class="bl-stats-cards">
            <div class="bl-stat-card">
                <span class="bl-stat-value"><?php echo (int) $stats['views']; ?></span>
                <span class="bl-stat-label"><?php esc_html_e('Page views (30 days)', 'biolinks'); ?></span>
            </div>
            <div class="bl-stat-card">
                <span class="bl-stat-value"><?php echo (int) $stats['month']; ?></span>
                <span class="bl-stat-label"><?php esc_html_e('Clicks (30 days)', 'biolinks'); ?></span>
            </div>
            <div class="bl-stat-card">
                <span class="bl-stat-value"><?php echo esc_html($ctr); ?></span>
                <span class="bl-stat-label"><?php esc_html_e('Click-through rate', 'biolinks'); ?></span>
            </div>
            <div class="bl-stat-card">
                <span class="bl-stat-value"><?php echo (int) $stats['total']; ?></span>
                <span class="bl-stat-label"><?php esc_html_e('Clicks (all time)', 'biolinks'); ?></span>
            </div>
        </div>

        <div class="bl-charts">
            <div class="bl-chart-container">
                <div class="bl-chart-header">
                    <h2><?php esc_html_e('Views and clicks per day', 'biolinks'); ?></h2>
                    <select id="bl-period-select">
                        <option value="7"><?php
                            /* translators: %d: number of days */
                            printf(esc_html__('%d days', 'biolinks'), 7);
                        ?></option>
                        <option value="30" selected><?php
                            /* translators: %d: number of days */
                            printf(esc_html__('%d days', 'biolinks'), 30);
                        ?></option>
                        <option value="90"><?php
                            /* translators: %d: number of days */
                            printf(esc_html__('%d days', 'biolinks'), 90);
                        ?></option>
                    </select>
                </div>
                <div class="bl-chart-wrap"><canvas id="bl-chart-daily"></canvas></div>
            </div>
            <div class="bl-chart-container">
                <h2><?php esc_html_e('Clicks per link', 'biolinks'); ?></h2>
                <div class="bl-chart-wrap"><canvas id="bl-chart-links"></canvas></div>
            </div>
        </div>

        <div class="bl-charts">
            <div class="bl-chart-container">
                <h2><?php esc_html_e('Links (30 days)', 'biolinks'); ?></h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Link', 'biolinks'); ?></th>
                            <th><?php esc_html_e('Clicks', 'biolinks'); ?></th>
                            <th><?php esc_html_e('Click-through rate', 'biolinks'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clicks_per_link as $row): ?>
                        <tr>
                            <td><?php echo esc_html($row->link_name); ?></td>
                            <td><?php echo (int) $row->clicks; ?></td>
                            <td><?php echo esc_html($stats['views'] > 0 ? $this->format_rate((int) $row->clicks, $stats['views']) : '--'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="bl-chart-container">
                <h2><?php esc_html_e('Traffic sources (30 days)', 'biolinks'); ?></h2>
                <?php if (empty($sources)): ?>
                    <p><?php esc_html_e('No data yet.', 'biolinks'); ?></p>
                <?php else: ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Source', 'biolinks'); ?></th>
                            <th><?php esc_html_e('Views', 'biolinks'); ?></th>
                            <th><?php esc_html_e('Clicks', 'biolinks'); ?></th>
                            <th><?php esc_html_e('Click-through rate', 'biolinks'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sources as $source => $row): ?>
                        <tr>
                            <td><?php echo esc_html($this->source_label((string) $source)); ?></td>
                            <td><?php echo (int) $row['views']; ?></td>
                            <td><?php echo (int) $row['clicks']; ?></td>
                            <td><?php echo esc_html($row['views'] > 0 ? $this->format_rate($row['clicks'], $row['views']) : '--'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="description"><?php esc_html_e('Detected from the in-app browser of each social network, or from the referring site. Nothing leaves your server.', 'biolinks'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function format_rate(int $part, int $total): string
    {
        return number_format_i18n($part / $total * 100, 1) . ' %';
    }

    private function source_label(string $source): string
    {
        $labels = [
            'search' => __('Search engines', 'biolinks'),
            'website' => __('Other websites', 'biolinks'),
            'direct' => __('Direct or unknown', 'biolinks'),
            'threads' => 'Threads',
            'telegram' => 'Telegram',
        ];
        return $labels[$source] ?? (BIOLINKS_SOCIAL_LABELS[$source] ?? ucfirst($source));
    }

    private function chart_payload(int $days): array
    {
        $series = BioLinks_DB::get_daily_series($days);
        $per_link = BioLinks_DB::get_clicks_per_link($days);

        return [
            'daily' => $series,
            'links' => [
                'labels' => array_map(static fn($r) => $r->link_name, $per_link),
                'values' => array_map(static fn($r) => (int) $r->clicks, $per_link),
            ],
        ];
    }

    private function datetime_input_value(?string $value): string
    {
        return $value ? str_replace(' ', 'T', substr($value, 0, 16)) : '';
    }

    private function parse_datetime_input(string $key): ?string
    {
        $raw = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})$/', $raw, $m)) {
            return null;
        }
        return $m[1] . ' ' . $m[2] . ':00';
    }

    private function link_badges(object $link): array
    {
        $badges = [];
        if (($link->link_type ?? 'link') === 'header') {
            $badges[] = ['type' => 'header', 'label' => __('Section title', 'biolinks')];
        }
        if (!empty($link->featured)) {
            $badges[] = ['type' => 'featured', 'label' => __('Highlighted', 'biolinks')];
        }
        $now = current_time('mysql');
        if (!empty($link->ends_at) && $link->ends_at <= $now) {
            $badges[] = ['type' => 'ended', 'label' => __('Ended', 'biolinks')];
        } elseif (!empty($link->starts_at) && $link->starts_at > $now) {
            $badges[] = ['type' => 'scheduled', 'label' => sprintf(
                /* translators: %s: date and time the link becomes visible */
                __('From %s', 'biolinks'),
                mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $link->starts_at)
            )];
        } elseif (!empty($link->ends_at)) {
            $badges[] = ['type' => 'scheduled', 'label' => sprintf(
                /* translators: %s: date and time the link stops being visible */
                __('Until %s', 'biolinks'),
                mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $link->ends_at)
            )];
        }
        return $badges;
    }

    private function should_show_support_banner(array $config, array $links): bool
    {
        if (($config['support_dismissed'] ?? '0') === '1') {
            return false;
        }
        if ((int) ($config['support_snooze_until'] ?? 0) > time()) {
            return false;
        }
        $activated = (int) ($config['activated_at'] ?? 0);
        if ($activated === 0 || (time() - $activated) < 7 * DAY_IN_SECONDS) {
            return false;
        }
        if ((int) ($config['page_id'] ?? 0) <= 0) {
            return false;
        }
        if (count($links) < 1) {
            return false;
        }
        return BioLinks_DB::get_total_clicks() >= self::SUPPORT_MIN_CLICKS;
    }

    private function render_support_banner(array $config): void
    {
        $credit_active = ($config['show_credit'] ?? '0') === '1';
        $review_url = 'https://wordpress.org/support/plugin/biolinks/reviews/?rate=5#new-post';
        ?>
        <div class="bl-support-banner">
            <div class="bl-support-text">
                <strong><?php esc_html_e('Enjoying BioLinks?', 'biolinks'); ?></strong>
                <span><?php esc_html_e('Here are two ways to support this free plugin, built on my own time.', 'biolinks'); ?></span>
            </div>
            <div class="bl-support-actions">
                <a href="<?php echo esc_url($review_url); ?>" target="_blank" rel="noopener" class="button button-primary"><?php esc_html_e('Leave a review', 'biolinks'); ?></a>
                <?php if (!$credit_active): ?>
                <form method="post" style="display:inline">
                    <?php wp_nonce_field('biolinks_support', 'bl_support_nonce'); ?>
                    <input type="hidden" name="bl_action" value="enable_credit">
                    <button type="submit" class="button"><?php esc_html_e('Enable footer credit', 'biolinks'); ?></button>
                </form>
                <?php endif; ?>
            </div>
            <div class="bl-support-dismiss">
                <form method="post" style="display:inline">
                    <?php wp_nonce_field('biolinks_support', 'bl_support_nonce'); ?>
                    <input type="hidden" name="bl_action" value="dismiss_support">
                    <button type="submit" class="button-link"><?php esc_html_e('I already left a review', 'biolinks'); ?></button>
                </form>
                <form method="post" style="display:inline">
                    <?php wp_nonce_field('biolinks_support', 'bl_support_nonce'); ?>
                    <input type="hidden" name="bl_action" value="snooze_support">
                    <button type="submit" class="button-link"><?php esc_html_e('Maybe later', 'biolinks'); ?></button>
                </form>
            </div>
        </div>
        <?php
    }

    public function handle_form_submit(): void
    {
        if (!isset($_POST['bl_action'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied', 'biolinks'));
        }

        $action = sanitize_text_field(wp_unslash($_POST['bl_action']));

        if ($action === 'save_profile') {
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bl_profile_nonce'] ?? '')), 'biolinks_save_profile')) {
                wp_die(esc_html__('Invalid nonce', 'biolinks'));
            }

            BioLinks_DB::set_config('photo_url', esc_url_raw(wp_unslash($_POST['photo_url'] ?? '')));
            BioLinks_DB::set_config('title', sanitize_text_field(wp_unslash($_POST['title'] ?? '')));
            BioLinks_DB::set_config('bio', sanitize_text_field(wp_unslash($_POST['bio'] ?? '')));

            $new_slug = sanitize_title(wp_unslash($_POST['slug'] ?? 'links'));
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
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bl_socials_nonce'] ?? '')), 'biolinks_save_socials')) {
                wp_die(esc_html__('Invalid nonce', 'biolinks'));
            }

            $socials = [];
            foreach (BIOLINKS_SOCIAL_KEYS as $key) {
                $url = esc_url_raw(wp_unslash($_POST['socials'][$key] ?? ''));
                if (!empty($url)) {
                    $socials[$key] = $url;
                }
            }
            BioLinks_DB::set_config('socials', wp_json_encode($socials));

            wp_safe_redirect(admin_url('admin.php?page=biolinks&tab=page'));
            exit;
        }

        if ($action === 'save_link') {
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bl_link_nonce'] ?? '')), 'biolinks_save_link')) {
                wp_die(esc_html__('Invalid nonce', 'biolinks'));
            }

            $id = absint(wp_unslash($_POST['link_id'] ?? 0));
            $name = sanitize_text_field(wp_unslash($_POST['link_name'] ?? ''));
            $url = esc_url_raw(wp_unslash($_POST['link_url'] ?? ''));
            $position = absint(wp_unslash($_POST['link_position'] ?? 0));
            $icon = sanitize_text_field(wp_unslash($_POST['link_icon'] ?? ''));
            if ($icon !== '' && !in_array($icon, BIOLINKS_GENERIC_LABELS_KEYS, true)) {
                $icon = '';
            }
            $icon = $icon !== '' ? $icon : null;
            $type = sanitize_key(wp_unslash($_POST['link_type'] ?? 'link')) === 'header' ? 'header' : 'link';
            $starts_at = $this->parse_datetime_input('link_starts_at');
            $ends_at = $this->parse_datetime_input('link_ends_at');
            if ($starts_at !== null && $ends_at !== null && $ends_at <= $starts_at) {
                $ends_at = null;
            }

            $data = [
                'link_name' => $name,
                'link_url' => $type === 'header' ? '' : $url,
                'position' => (int) $position,
                'icon' => $type === 'header' ? null : $icon,
                'link_type' => $type,
                'featured' => $type === 'link' && isset($_POST['link_featured']),
                'starts_at' => $starts_at,
                'ends_at' => $ends_at,
            ];

            if ($id > 0) {
                BioLinks_DB::update_link($id, $data);
            } else {
                BioLinks_DB::insert_link($data);
            }

            wp_safe_redirect(admin_url('admin.php?page=biolinks&tab=page'));
            exit;
        }

        if ($action === 'delete_link') {
            $id = absint(wp_unslash($_POST['link_id'] ?? 0));
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bl_delete_nonce'] ?? '')), 'biolinks_delete_' . $id)) {
                wp_die(esc_html__('Invalid nonce', 'biolinks'));
            }

            BioLinks_DB::delete_link((int) $id);

            wp_safe_redirect(admin_url('admin.php?page=biolinks&tab=page'));
            exit;
        }

        if ($action === 'save_appearance') {
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bl_appearance_nonce'] ?? '')), 'biolinks_save_appearance')) {
                wp_die(esc_html__('Invalid nonce', 'biolinks'));
            }

            $template = sanitize_text_field(wp_unslash($_POST['template'] ?? 'dark'));
            $allowed = ['dark', 'light', 'minimal', 'colorful', 'glass'];
            if (!in_array($template, $allowed, true)) {
                $template = 'dark';
            }
            BioLinks_DB::set_config('template', $template);

            $color = sanitize_hex_color(wp_unslash($_POST['accent_color'] ?? '#0a7286'));
            BioLinks_DB::set_config('accent_color', $color ?: '#0a7286');

            $show_credit = isset($_POST['show_credit']) ? '1' : '0';
            BioLinks_DB::set_config('show_credit', $show_credit);

            BioLinks_DB::set_config('utm_enabled', isset($_POST['utm_enabled']) ? '1' : '0');
            BioLinks_DB::set_config('utm_source', sanitize_title(wp_unslash($_POST['utm_source'] ?? '')) ?: 'biolinks');
            BioLinks_DB::set_config('utm_medium', sanitize_title(wp_unslash($_POST['utm_medium'] ?? '')) ?: 'link_in_bio');

            BioLinks_DB::set_config('newsletter_enabled', isset($_POST['newsletter_enabled']) ? '1' : '0');
            BioLinks_DB::set_config('newsletter_title', sanitize_text_field(wp_unslash($_POST['newsletter_title'] ?? '')));
            BioLinks_DB::set_config('newsletter_action', esc_url_raw(wp_unslash($_POST['newsletter_action'] ?? ''), ['https']));
            BioLinks_DB::set_config('newsletter_field', preg_replace('/[^A-Za-z0-9_\-\[\]]/', '', sanitize_text_field(wp_unslash($_POST['newsletter_field'] ?? ''))) ?: 'EMAIL');
            BioLinks_DB::set_config('newsletter_hidden', sanitize_textarea_field(wp_unslash($_POST['newsletter_hidden'] ?? '')));
            BioLinks_DB::set_config('newsletter_placeholder', sanitize_text_field(wp_unslash($_POST['newsletter_placeholder'] ?? '')));
            BioLinks_DB::set_config('newsletter_button', sanitize_text_field(wp_unslash($_POST['newsletter_button'] ?? '')));

            wp_safe_redirect(admin_url('admin.php?page=biolinks&tab=appearance'));
            exit;
        }

        if ($action === 'enable_credit') {
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bl_support_nonce'] ?? '')), 'biolinks_support')) {
                wp_die(esc_html__('Invalid nonce', 'biolinks'));
            }
            BioLinks_DB::set_config('show_credit', '1');
            BioLinks_DB::set_config('support_dismissed', '1');
            wp_safe_redirect(admin_url('admin.php?page=biolinks&tab=appearance'));
            exit;
        }

        if ($action === 'dismiss_support') {
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bl_support_nonce'] ?? '')), 'biolinks_support')) {
                wp_die(esc_html__('Invalid nonce', 'biolinks'));
            }
            BioLinks_DB::set_config('support_dismissed', '1');
            wp_safe_redirect(admin_url('admin.php?page=biolinks'));
            exit;
        }

        if ($action === 'snooze_support') {
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bl_support_nonce'] ?? '')), 'biolinks_support')) {
                wp_die(esc_html__('Invalid nonce', 'biolinks'));
            }
            BioLinks_DB::set_config('support_snooze_until', (string) (time() + 14 * DAY_IN_SECONDS));
            wp_safe_redirect(admin_url('admin.php?page=biolinks'));
            exit;
        }
    }

    public function handle_reorder(): void
    {
        check_ajax_referer('biolinks_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Access denied', 'biolinks'), 403);
        }

        $order = isset($_POST['order']) ? array_map('absint', wp_unslash($_POST['order'])) : [];
        if (empty($order)) {
            wp_send_json_error(esc_html__('No data', 'biolinks'), 400);
        }

        BioLinks_DB::update_positions($order);
        wp_send_json_success();
    }

    public function handle_stats(): void
    {
        check_ajax_referer('biolinks_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Access denied', 'biolinks'), 403);
        }

        $days = isset($_POST['days']) ? absint(wp_unslash($_POST['days'])) : 30;
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        wp_send_json_success($this->chart_payload($days));
    }

    public function handle_import(): void
    {
        check_ajax_referer('biolinks_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Access denied', 'biolinks'), 403);
        }

        $count = BioLinks_DB::import_from_click_tracker();
        wp_send_json_success(['imported' => $count]);
    }
}
