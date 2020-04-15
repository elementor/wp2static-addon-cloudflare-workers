<?php

/**
 * Plugin Name:       WP2Static Add-on: Cloudflare Workers Deployment
 * Plugin URI:        https://wp2static.com
 * Description:       Cloudflare Workers deployment add-on for WP2Static.
 * Version:           1.0-alpha-002
 * Author:            Leon Stafford
 * Author URI:        https://ljs.dev
 * License:           Unlicense
 * License URI:       http://unlicense.org
 * Text Domain:       wp2static-addon-cloudflare-workers
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'WP2STATIC_CLOUDFLARE_WORKERS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP2STATIC_CLOUDFLARE_WORKERS_VERSION', '1.0-alpha-002' );

require WP2STATIC_CLOUDFLARE_WORKERS_PATH . 'vendor/autoload.php';

function run_wp2static_addon_cloudflare_workers() {
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

run_wp2static_addon_cloudflare_workers();

