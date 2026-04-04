<?php
declare(strict_types=1);

/**
 * Plugin Name: BioLinks
 * Plugin URI: https://github.com/JeremieSamson/biolinks
 * Description: Self-hosted link in bio page with 5 templates, click tracking, and analytics.
 * Version: 1.1.0
 * Author: Jérémie Samson
 * Author URI: https://nomadesurrails.fr
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: biolinks
 * Domain Path: /languages
 * Requires at least: 5.9
 * Tested up to: 6.8
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BIOLINKS_VERSION', '1.1.0');
define('BIOLINKS_PATH', plugin_dir_path(__FILE__));
define('BIOLINKS_URL', plugin_dir_url(__FILE__));

require_once BIOLINKS_PATH . 'includes/icons.php';
require_once BIOLINKS_PATH . 'includes/class-biolinks-db.php';
require_once BIOLINKS_PATH . 'includes/class-biolinks-admin.php';
require_once BIOLINKS_PATH . 'includes/class-biolinks-front.php';

register_activation_hook(__FILE__, [BioLinks_DB::class, 'activate']);

add_action('plugins_loaded', static function (): void {
    load_plugin_textdomain('biolinks', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

if (is_admin()) {
    new BioLinks_Admin();
}

new BioLinks_Front();
