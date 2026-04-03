<?php
declare(strict_types=1);

/**
 * Plugin Name: BioLinks
 * Description: Page "link in bio" personnalisable avec templates, tracking des clics et statistiques.
 * Version: 1.0
 * Author: Jérémie Samson
 * Text Domain: biolinks
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BIOLINKS_VERSION', '1.0');
define('BIOLINKS_PATH', plugin_dir_path(__FILE__));
define('BIOLINKS_URL', plugin_dir_url(__FILE__));

require_once BIOLINKS_PATH . 'includes/icons.php';
require_once BIOLINKS_PATH . 'includes/class-biolinks-db.php';
require_once BIOLINKS_PATH . 'includes/class-biolinks-admin.php';
require_once BIOLINKS_PATH . 'includes/class-biolinks-front.php';

register_activation_hook(__FILE__, [BioLinks_DB::class, 'activate']);

if (is_admin()) {
    new BioLinks_Admin();
}

new BioLinks_Front();
