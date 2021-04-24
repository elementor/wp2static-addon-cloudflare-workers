<?php

/**
 * WP2Static Cloudflare Workers Add-on
 *
 * @package           WP2StaticCloudflareWorkers
 * @author            Leon Stafford <me@ljs.dev>
 * @license           The Unlicense https://unlicense.org
 * @link              https://github.com/leonstafford/wp2static-addon-cloudflare-workers
 *
 * Plugin Name:       WP2Static Add-on: Cloudflare Workers Deployment
 * Plugin URI:        https://wp2static.com
 * Description:       Cloudflare Workers deployment add-on for WP2Static.
 * Version:           1.0-dev
 * Author:            Leon Stafford
 * Author URI:        https://ljs.dev
 * License:           Unlicense
 * License URI:       http://unlicense.org
 * Text Domain:       wp2static-addon-cloudflare-workers
 */

declare(strict_types=1);

namespace WP2StaticCloudflareWorkers;

use function add_action;
use function current_user_can;
use function deactivate_plugins;
use function esc_html;
use function esc_html__;
use function plugin_basename;
use function register_activation_hook;
use function register_deactivation_hook;
use function register_uninstall_hook;

if (! defined('ABSPATH')) {
    die;
}

// Load autoloader.
if (! class_exists(Config::class) && is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Prevent double activation.
if (Config::get('version') !== null) {
    add_action(
        'admin_notices',
        static function (): void {
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
            error_log(
                'WP2Static Cloudflare Workers Add-on double activation. ' .
                'Please remove all but one copies. ' . __FILE__
            );

            if (! current_user_can('activate_plugins')) {
                return;
            }

            printf(
                '<div class="notice notice-warning"><p>%1$s<br>%2$s&nbsp;<code>%3$s</code></p></div>',
                esc_html__(
                    'WP2Static Cloudflare Workers Add-on already installed! Please deactivate all but one copies.',
                    'wp2static-addon-cloudflare-workers'
                ),
                esc_html__('Current plugin path:', 'wp2static-addon-cloudflare-workers'),
                esc_html(__FILE__)
            );
        },
        0,
        0
    );
    return;
}

// Define constant values.
Config::init(
    [
        'version' => '1.0.0-dev',
        'filePath' => __FILE__,
        'pluginDir' => plugin_dir_path(__FILE__),
        'baseName' => plugin_basename(__FILE__),
        'slug' => 'wp2static-addon-cloudflare-workers',
    ]
);

// Check requirements.
if (
    (new Requirements())
        ->php('7.4')
        ->wp('5.3')
        ->multisite(false)
        ->met()
) {
    // Hook plugin activation functions.
    register_activation_hook(__FILE__, __NAMESPACE__ . '\\activate');
    register_deactivation_hook(__FILE__, __NAMESPACE__ . '\\deactivate');
    register_uninstall_hook(__FILE__, __NAMESPACE__ . '\\uninstall');
    add_action('plugins_loaded', __NAMESPACE__ . '\\boot', 10, 0);

    // Support WP-CLI.
    if (defined('WP_CLI') && \WP_CLI === true) {
        registerCliCommands();
    }
} else {
    // Suppress "Plugin activated." notice.
    unset($_GET['activate']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    add_action('admin_notices', __NAMESPACE__ . '\\printRequirementsNotice', 0, 0);

    require_once \ABSPATH . 'wp-admin/includes/plugin.php';
    deactivate_plugins([(string)Config::get('baseName')], true);
}
