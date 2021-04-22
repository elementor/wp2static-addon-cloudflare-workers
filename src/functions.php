<?php

/**
 * functions.php - Procedural part of WP2Static Cloudflare Workers Add-on.
 *
 * @package           WP2StaticCloudflareWorkers
 * @author            Leon Stafford <me@ljs.dev>
 * @license           The Unlicense
 * @link              https://unlicense.org
 */

declare(strict_types=1);

namespace WP2StaticCloudflareWorkers;

use WP_CLI;

use function current_user_can;
use function esc_html__;

/**
 * @return void
 */
function activate()
{
    // Do something related to activation.
}

/**
 * @return void
 */
function deactivate()
{
    // Do something related to deactivation.
}

/**
 * @return void
 */
function uninstall()
{
    // Remove custom database tables, WordPress options etc.
}

/**
 * @return void
 */
function printRequirementsNotice()
{
    // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
    error_log('WP2Static Cloudflare Workers Add-on requirements are not met. Please read the Installation instructions.');

    if (! current_user_can('activate_plugins')) {
        return;
    }

    printf(
        '<div class="notice notice-error"><p>%1$s</p></div>',
        esc_html__('WP2Static Cloudflare Workers Add-on activation failed!', 'wp2static-addon-cloudflare-workers'),
    );
}

/**
 * @return void
 */
function registerCliCommands()
{
    \WP_CLI::add_command(
        'wp2static cloudflare_workers',
        [ 'WP2StaticCloudflareWorkers\CLI', 'cloudflareWorkers' ]
    );

/**
 * Start!
 *
 * @return void
 */
function boot()
{
    new \WP2StaticCloudflareWorkers\Controller();
}
