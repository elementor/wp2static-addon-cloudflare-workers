<?php

namespace WP2StaticCloudFlareWorkers;

use WP_CLI;


/**
 * WP2StaticCloudFlareWorkers WP-CLI commands
 *
 * Registers WP-CLI commands for WP2StaticCloudFlareWorkers under main wp2static cmd
 *
 * Usage: wp wp2static options set s3Bucket mybucketname
 */
class CLI {

    /**
     * S3 commands
     *
     * @param string[] $args CLI args
     * @param string[] $assoc_args CLI args
     */
    public function cloudflare_workers(
        array $args,
        array $assoc_args
    ) : void {
        $action = isset( $args[0] ) ? $args[0] : null;

        if ( empty( $action ) ) {
            WP_CLI::error( 'Missing required argument: <options>' );
        }

        if ( $action === 'options' ) {
            WP_CLI::line( 'TBC setting options for CF Workers addon' );
        }
    }
}

