<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$config = BioLinks_DB::get_all_config();
$links = BioLinks_DB::get_all_links();
$nonce = wp_create_nonce('biolinks_nonce');
$ajax_url = admin_url('admin-ajax.php');

$template = $config['template'] ?? 'dark';
$allowed_templates = ['dark', 'light', 'minimal', 'colorful', 'glass'];
if (!in_array($template, $allowed_templates, true)) {
    $template = 'dark';
}
$accent = $config['accent_color'] ?? '#0a7286';
$photo_url = $config['photo_url'] ?? '';
$title = $config['title'] ?? get_bloginfo('name');
$bio = $config['bio'] ?? '';
$socials = json_decode($config['socials'] ?? '{}', true) ?: [];

$ga_id = BioLinks_Front::detect_ga_id();

$template_css = BIOLINKS_URL . 'templates/' . $template . '.css?v=' . BIOLINKS_VERSION;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($title); ?></title>
    <link rel="icon" href="<?php echo esc_url(get_site_icon_url()); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($template_css); ?>">
    <style>:root { --accent: <?php echo esc_attr($accent); ?>; }</style>
    <?php if ($ga_id): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($ga_id); ?>"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','<?php echo esc_js($ga_id); ?>');</script>
    <?php endif; ?>
</head>
<body>
    <div class="biolinks">
        <div class="biolinks-header">
            <?php if ($photo_url): ?>
            <img
                src="<?php echo esc_url($photo_url); ?>"
                alt="<?php echo esc_attr($title); ?>"
                class="biolinks-avatar"
                width="100"
                height="100"
            >
            <?php endif; ?>
            <h1 class="biolinks-title"><?php echo esc_html($title); ?></h1>
            <?php if ($bio): ?>
            <p class="biolinks-bio"><?php echo esc_html($bio); ?></p>
            <?php endif; ?>
        </div>

        <?php
        $active_socials = array_filter($socials, fn($url) => !empty($url));
        if (!empty($active_socials)):
        ?>
        <div class="biolinks-socials">
            <?php foreach ($active_socials as $key => $url): ?>
                <?php if (isset(BIOLINKS_SOCIAL_ICONS[$key])): ?>
                <a
                    href="<?php echo esc_url($url); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="biolinks-social"
                    title="<?php echo esc_attr(BIOLINKS_SOCIAL_LABELS[$key] ?? $key); ?>"
                ><?php echo BIOLINKS_SOCIAL_ICONS[$key]; ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="biolinks-links">
            <?php foreach ($links as $link): ?>
                <?php
                    $icon_key = $link->icon ?: 'globe';
                    $icon_svg = BIOLINKS_GENERIC_ICONS[$icon_key] ?? BIOLINKS_GENERIC_ICONS['globe'];
                ?>
                <a
                    href="<?php echo esc_url($link->link_url); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="biolinks-link"
                    data-id="<?php echo (int) $link->id; ?>"
                >
                    <span class="biolinks-link-icon"><?php echo $icon_svg; ?></span>
                    <span class="biolinks-link-name"><?php echo esc_html($link->link_name); ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <a href="https://nomadesurrails.fr/biolinks" class="biolinks-footer" rel="dofollow">Propulsé par BioLinks</a>
    </div>

    <script>
        var biolinksData = {
            ajax_url: <?php echo wp_json_encode($ajax_url); ?>,
            nonce: <?php echo wp_json_encode($nonce); ?>
        };
    </script>
    <script src="<?php echo esc_url(BIOLINKS_URL . 'assets/front.js?v=' . BIOLINKS_VERSION); ?>"></script>
</body>
</html>
