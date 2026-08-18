<?php
/**
 * Plugin Name: WP Performance
 * Description: WP Performance Optimizer - cache & performance plugin.
 * Version: 2.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Ante Laca
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpp
 * Domain Path: /languages
 */

declare(strict_types=1);

use WPP\Foundation\Plugin;

if (! defined('ABSPATH')) {
    exit;
}

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/inc/fragment-helpers.php';

define('WPP_VERSION', '2.0.0');
define('WPP_FILE', __FILE__);
define('WPP_DIR', plugin_dir_path(__FILE__));
define('WPP_URL', plugin_dir_url(__FILE__));
define('WPP_SLUG', 'wp-performance');
define('WPP_CACHE_DIR', trailingslashit(WP_CONTENT_DIR) . 'cache/wpp-cache/');
define('WPP_CACHE_URL', trailingslashit(WP_CONTENT_URL) . 'cache/wpp-cache/');

register_activation_hook(__FILE__, [Plugin::class, 'onActivation']);
register_deactivation_hook(__FILE__, [Plugin::class, 'onDeactivation']);

add_action('plugins_loaded', static function (): void {
    Plugin::boot();

    if (defined('WP_CLI') && WP_CLI) {
        $cli = new WPP\Cli\CliCommands();
        WP_CLI::add_command('wpp flush', [$cli, 'flush']);
        WP_CLI::add_command('wpp flush-blocks', [$cli, 'flushBlocks']);
        WP_CLI::add_command('wpp enable', [$cli, 'enable']);
        WP_CLI::add_command('wpp disable', [$cli, 'disable']);
        WP_CLI::add_command('wpp cleanup', [$cli, 'cleanup']);
    }
});
