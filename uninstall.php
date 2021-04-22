<?php

/**
 * Uninstall script
 *
 * @package           WP2StaticCloudflareWorkers
 * @author            Leon Stafford <me@ljs.dev>
 * @license           The Unlicense
 * @link              https://unlicense.org
 */

declare(strict_types=1);

// If uninstall not called from WordPress, then exit.
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$tableName = $wpdb->prefix . 'wp2static_addon_cloudflare_workers_options';

$wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %s', $tableName));
