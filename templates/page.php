<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$biolinks_config    = BioLinks_DB::get_all_config();
$biolinks_links     = BioLinks_DB::get_all_links();
$biolinks_photo_url = $biolinks_config['photo_url'] ?? '';
$biolinks_title     = $biolinks_config['title'] ?? get_bloginfo('name');
$biolinks_bio       = $biolinks_config['bio'] ?? '';
$biolinks_socials   = json_decode($biolinks_config['socials'] ?? '{}', true) ?: [];
$biolinks_credit    = ($biolinks_config['show_credit'] ?? '0') === '1';

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
    <link rel="icon" href="<?php echo esc_url(get_site_icon_url()); ?>">
    <?php wp_head(); ?>
</head>
<body>
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
                    $biolinks_icon_key = $biolinks_link->icon ?: 'globe';
                    $biolinks_icon_svg = BIOLINKS_GENERIC_ICONS[$biolinks_icon_key] ?? BIOLINKS_GENERIC_ICONS['globe'];
                ?>
                <a
                    href="<?php echo esc_url($biolinks_link->link_url); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="biolinks-link"
                    data-id="<?php echo (int) $biolinks_link->id; ?>"
                >
                    <span class="biolinks-link-icon"><?php echo wp_kses($biolinks_icon_svg, $biolinks_allowed_svg); ?></span>
                    <span class="biolinks-link-name"><?php echo esc_html($biolinks_link->link_name); ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($biolinks_credit): ?>
        <span class="biolinks-footer"><?php
            printf(
                /* translators: %s: BioLinks plugin name */
                esc_html__('Powered by %s', 'biolinks'),
                'BioLinks'
            );
        ?></span>
        <?php endif; ?>
    </div>

    <?php wp_footer(); ?>
</body>
</html>
