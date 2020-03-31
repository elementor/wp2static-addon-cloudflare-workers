<?php

namespace WP2StaticCloudflareWorkers;

use WP_CLI;

/**
 * WP2StaticCloudflareWorkers WP-CLI commands
 *
 * Registers WP-CLI commands for WP2StaticCloudflareWorkers under main wp2static cmd
 *
 * Usage: wp wp2static options set s3Bucket mybucketname
 */
class CLI {

    /**
     * Cloudflare Workers add-on commands
     *
     * @param string[] $args CLI args
     * @param string[] $assoc_args CLI args
     */
    public function cloudflare_workers(
        array $args,
        array $assoc_args
    ) : void {
        $action = isset( $args[0] ) ? $args[0] : null;
        $arg = isset( $args[1] ) ? $args[1] : null;

        if ( empty( $action ) ) {
            WP_CLI::error( 'Missing required argument: <options>' );
        }

        if ( $action === 'options' ) {
            WP_CLI::line( 'TBC setting options for CF Workers addon' );
        }

        if ( $action === 'keys' ) {
            if ( $arg === 'list' ) {
                $client = new CloudflareWorkers();

                $key_names = $client->list_keys();

                foreach ( $key_names as $name ) {
                    WP_CLI::line( $name );
                }
            }

            if ( $arg === 'count' ) {
                $client = new CloudflareWorkers();

                $key_names = $client->list_keys();

                WP_CLI::line( (string) count( $key_names ) );
            }
        }

        if ( $action === 'get_value' ) {
            WP_CLI::line( 'TBC get value for a key' );
        }
    }
}

