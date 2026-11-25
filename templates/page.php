<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$biolinks_config    = BioLinks_DB::get_all_config();
$biolinks_links     = BioLinks_DB::get_public_links();
$biolinks_photo_url = $biolinks_config['photo_url'] ?? '';
$biolinks_title     = $biolinks_config['title'] ?? get_bloginfo('name');
$biolinks_bio       = $biolinks_config['bio'] ?? '';
$biolinks_socials   = json_decode($biolinks_config['socials'] ?? '{}', true) ?: [];
$biolinks_credit    = ($biolinks_config['show_credit'] ?? '0') === '1';
$biolinks_template  = in_array($biolinks_config['template'] ?? 'dark', ['dark', 'light', 'minimal', 'colorful', 'glass'], true) ? $biolinks_config['template'] : 'dark';
$biolinks_page_url  = (string) get_permalink();
$biolinks_desc      = $biolinks_bio !== '' ? $biolinks_bio : (string) get_bloginfo('description');
$biolinks_now       = current_time('mysql');
$biolinks_newsletter_action = (string) ($biolinks_config['newsletter_action'] ?? '');
$biolinks_newsletter_field  = (string) ($biolinks_config['newsletter_field'] ?? '');
$biolinks_newsletter = ($biolinks_config['newsletter_enabled'] ?? '0') === '1'
    && preg_match('#^https://#i', $biolinks_newsletter_action)
    && $biolinks_newsletter_field !== '';
$biolinks_newsletter_hidden = [];
foreach (preg_split('/\r\n|\r|\n/', (string) ($biolinks_config['newsletter_hidden'] ?? '')) as $biolinks_line) {
    $biolinks_pair = explode('=', $biolinks_line, 2);
    if (count($biolinks_pair) === 2 && trim($biolinks_pair[0]) !== '') {
        $biolinks_newsletter_hidden[trim($biolinks_pair[0])] = trim($biolinks_pair[1]);
    }
}
$biolinks_timestamp = static fn(?string $biolinks_local): int => $biolinks_local ? (int) strtotime(get_gmt_from_date($biolinks_local) . ' UTC') : 0;

$biolinks_allowed_svg = [
    'svg'     => ['xmlns' => [], 'width' => [], 'height' => [], 'viewBox' => [], 'viewbox' => [], 'fill' => [], 'stroke' => [], 'stroke-width' => [], 'stroke-linecap' => [], 'stroke-linejoin' => []],
    'path'    => ['d' => [], 'fill' => []],
    'circle'  => ['cx' => [], 'cy' => [], 'r' => [], 'fill' => []],
    'rect'    => ['x' => [], 'y' => [], 'width' => [], 'height' => [], 'rx' => [], 'ry' => [], 'fill' => []],
    'line'    => ['x1' => [], 'y1' => [], 'x2' => [], 'y2' => []],
    'polygon' => ['points' => []],
    'polyline' => ['points' => []],
];
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($biolinks_title); ?></title>
    <?php if ($biolinks_desc !== ''): ?>
    <meta name="description" content="<?php echo esc_attr($biolinks_desc); ?>">
    <?php endif; ?>
    <link rel="canonical" href="<?php echo esc_url($biolinks_page_url); ?>">
    <meta property="og:type" content="profile">
    <meta property="og:title" content="<?php echo esc_attr($biolinks_title); ?>">
    <?php if ($biolinks_desc !== ''): ?>
    <meta property="og:description" content="<?php echo esc_attr($biolinks_desc); ?>">
    <?php endif; ?>
    <meta property="og:url" content="<?php echo esc_url($biolinks_page_url); ?>">
    <meta property="og:site_name" content="<?php echo esc_attr(get_bloginfo('name')); ?>">
    <meta property="og:locale" content="<?php echo esc_attr(str_replace('-', '_', get_bloginfo('language'))); ?>">
    <?php if ($biolinks_photo_url): ?>
    <meta property="og:image" content="<?php echo esc_url($biolinks_photo_url); ?>">
    <meta property="og:image:alt" content="<?php echo esc_attr($biolinks_title); ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?php echo esc_attr($biolinks_title); ?>">
    <?php if ($biolinks_desc !== ''): ?>
    <meta name="twitter:description" content="<?php echo esc_attr($biolinks_desc); ?>">
    <?php endif; ?>
    <?php if ($biolinks_photo_url): ?>
    <meta name="twitter:image" content="<?php echo esc_url($biolinks_photo_url); ?>">
    <?php endif; ?>
    <link rel="icon" href="<?php echo esc_url(get_site_icon_url()); ?>">
    <?php wp_head(); ?>
</head>
<body class="biolinks-template-<?php echo esc_attr($biolinks_template); ?>">
    <div class="biolinks">
        <div class="biolinks-header">
            <?php if ($biolinks_photo_url): ?>
            <img
                src="<?php echo esc_url($biolinks_photo_url); ?>"
                alt="<?php echo esc_attr($biolinks_title); ?>"
                class="biolinks-avatar"
                width="100"
                height="100"
            >
            <?php endif; ?>
            <h1 class="biolinks-title"><?php echo esc_html($biolinks_title); ?></h1>
            <?php if ($biolinks_bio): ?>
            <p class="biolinks-bio"><?php echo esc_html($biolinks_bio); ?></p>
            <?php endif; ?>
        </div>

        <?php
        $biolinks_active_socials = array_filter($biolinks_socials, fn($biolinks_url) => !empty($biolinks_url));
        if (!empty($biolinks_active_socials)):
        ?>
        <div class="biolinks-socials">
            <?php foreach ($biolinks_active_socials as $biolinks_key => $biolinks_url): ?>
                <?php if (isset(BIOLINKS_SOCIAL_ICONS[$biolinks_key])): ?>
                <a
                    href="<?php echo esc_url($biolinks_url); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="biolinks-social"
                    title="<?php echo esc_attr(BIOLINKS_SOCIAL_LABELS[$biolinks_key] ?? $biolinks_key); ?>"
                ><?php echo wp_kses(BIOLINKS_SOCIAL_ICONS[$biolinks_key], $biolinks_allowed_svg); ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="biolinks-links">
            <?php foreach ($biolinks_links as $biolinks_link): ?>
                <?php
                    $biolinks_starts = $biolinks_timestamp($biolinks_link->starts_at ?? null);
                    $biolinks_ends = $biolinks_timestamp($biolinks_link->ends_at ?? null);
                    $biolinks_upcoming = !empty($biolinks_link->starts_at) && $biolinks_link->starts_at > $biolinks_now;
                    $biolinks_schedule_attrs = array_filter([
                        'data-start' => $biolinks_starts ? (string) $biolinks_starts : '',
                        'data-end' => $biolinks_ends ? (string) $biolinks_ends : '',
                    ]);
                ?>
                <?php if (($biolinks_link->link_type ?? 'link') === 'header'): ?>
                <h2 class="biolinks-section-title"<?php foreach ($biolinks_schedule_attrs as $biolinks_attr => $biolinks_attr_value): ?> <?php echo esc_attr($biolinks_attr); ?>="<?php echo esc_attr($biolinks_attr_value); ?>"<?php endforeach; ?><?php if ($biolinks_upcoming): ?> hidden<?php endif; ?>><?php echo esc_html($biolinks_link->link_name); ?></h2>
                <?php else: ?>
                <?php
                    $biolinks_icon_key = $biolinks_link->icon ?: 'globe';
                    $biolinks_icon_svg = BIOLINKS_GENERIC_ICONS[$biolinks_icon_key] ?? BIOLINKS_GENERIC_ICONS['globe'];
                    $biolinks_classes = 'biolinks-link' . (!empty($biolinks_link->featured) ? ' biolinks-link--featured' : '');
                ?>
                <a
                    href="<?php echo esc_url(BioLinks_Front::link_href($biolinks_link, $biolinks_config)); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="<?php echo esc_attr($biolinks_classes); ?>"
                    data-id="<?php echo (int) $biolinks_link->id; ?>"<?php foreach ($biolinks_schedule_attrs as $biolinks_attr => $biolinks_attr_value): ?> <?php echo esc_attr($biolinks_attr); ?>="<?php echo esc_attr($biolinks_attr_value); ?>"<?php endforeach; ?><?php if ($biolinks_upcoming): ?> hidden<?php endif; ?>
                >
                    <span class="biolinks-link-icon"><?php echo wp_kses($biolinks_icon_svg, $biolinks_allowed_svg); ?></span>
                    <span class="biolinks-link-name"><?php echo esc_html($biolinks_link->link_name); ?></span>
                </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php if ($biolinks_newsletter): ?>
        <form class="biolinks-newsletter" method="post" action="<?php echo esc_url($biolinks_newsletter_action); ?>" target="_blank">
            <?php if (!empty($biolinks_config['newsletter_title'])): ?>
            <p class="biolinks-newsletter-title"><?php echo esc_html($biolinks_config['newsletter_title']); ?></p>
            <?php endif; ?>
            <div class="biolinks-newsletter-row">
                <label class="screen-reader-text" for="biolinks-newsletter-email"><?php esc_html_e('Email address', 'biolinks'); ?></label>
                <input
                    type="email"
                    id="biolinks-newsletter-email"
                    name="<?php echo esc_attr($biolinks_newsletter_field); ?>"
                    class="biolinks-newsletter-input"
                    placeholder="<?php echo esc_attr(($biolinks_config['newsletter_placeholder'] ?? '') !== '' ? $biolinks_config['newsletter_placeholder'] : __('Your email address', 'biolinks')); ?>"
                    autocomplete="email"
                    required
                >
                <button type="submit" class="biolinks-newsletter-button"><?php echo esc_html(($biolinks_config['newsletter_button'] ?? '') !== '' ? $biolinks_config['newsletter_button'] : __('Subscribe', 'biolinks')); ?></button>
            </div>
            <?php foreach ($biolinks_newsletter_hidden as $biolinks_name => $biolinks_value): ?>
            <input type="hidden" name="<?php echo esc_attr($biolinks_name); ?>" value="<?php echo esc_attr($biolinks_value); ?>">
            <?php endforeach; ?>
        </form>
        <?php endif; ?>

        <?php if ($biolinks_credit): ?>
        <span class="biolinks-footer"><?php
            echo wp_kses(
                sprintf(
                    /* translators: %s: BioLinks plugin name wrapped in a link */
                    __('Powered by %s', 'biolinks'),
                    '<a href="' . esc_url('https://symfolidity.com/biolinks') . '" target="_blank" rel="noopener">BioLinks</a>'
                ),
                ['a' => ['href' => true, 'target' => true, 'rel' => true]]
            );
        ?></span>
        <?php endif; ?>
    </div>

    <?php wp_footer(); ?>
</body>
</html>
