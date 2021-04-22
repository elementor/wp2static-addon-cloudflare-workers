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

if (! defined('ABSPATH')) {
    die;
}

// TODO: defines into a Config, like @szepeviktor does in:
// szepeviktor/small-project/blob/346d4c3008a2fc67f47df19a5b54c9546a27f135/plugin-name.php#L75
define('WP2STATIC_CLOUDFLARE_WORKERS_PATH', plugin_dir_path(__FILE__));
define('WP2STATIC_CLOUDFLARE_WORKERS_VERSION', '1.0-dev');

if (
    file_exists(WP2STATIC_CLOUDFLARE_WORKERS_PATH . 'vendor/autoload.php')
) {
    require WP2STATIC_CLOUDFLARE_WORKERS_PATH . 'vendor/autoload.php';
}

// TODO: don't run global fn, rather, do req's check and load/fail:
// szepeviktor/small-project/blob/346d4c3008a2fc67f47df19a5b54c9546a27f135/plugin-name.php#L92
function runWp2staticAddonCloudflareWorkers()
{
    $controller = new WP2StaticCloudflareWorkers\Controller();
    $controller->run();
}

register_activation_hook(
    __FILE__,
    [ 'WP2StaticCloudflareWorkers\Controller', 'activate' ]
);

register_deactivation_hook(
    __FILE__,
    [ 'WP2StaticCloudflareWorkers\Controller', 'deactivate' ]
);

runWp2staticAddonCloudflareWorkers();
