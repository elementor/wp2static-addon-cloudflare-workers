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
            if ( empty( $arg ) ) {
                WP_CLI::error( 'Missing required argument: <get|set|list>' );
            }

            $option_name = isset( $args[2] ) ? $args[2] : null;

            if ( $arg === 'get' ) {
                if ( empty( $option_name ) ) {
                    WP_CLI::error( 'Missing required argument: <option-name>' );
                    return;
                }

                WP_CLI::line( Controller::getOptionValue( $option_name ) );
            }

            if ( $arg === 'set' ) {
                if ( empty( $option_name ) ) {
                    WP_CLI::error( 'Missing required argument: <option-name>' );
                    return;
                }

                if ( empty( $value ) ) {
                    WP_CLI::error( 'Missing required argument: <value>' );
                    return;
                }

                Controller::saveOption( $option_name, $value );
            }

            if ( $arg === 'list' ) {
                $options = Controller::getOptions();

                WP_CLI\Utils\format_items(
                    'table',
                    $options,
                    [ 'name', 'value' ]
                );
            }
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

            if ( $arg === 'delete' ) {
                if ( ! isset( $assoc_args['force'] ) ) {
                    $this->multilinePrint(
                        "no --force given. Please type 'yes' to confirm
                        deletion of all keys in namespace"
                    );

                    $userval = trim( (string) fgets( STDIN ) );

                    if ( $userval !== 'yes' ) {
                        WP_CLI::error( 'Failed to delete namespace keys' );
                    }
                }

                $client = new CloudflareWorkers();

                $success = $client->delete_keys();

                if ( ! $success ) {
                    WP_CLI::error( 'Failed to delete keys (maybe there weren\'t any?' );
                }

                WP_CLI::success( 'Deleted all keys in namespace' );
            }
        }

        if ( $action === 'get_value' ) {
            WP_CLI::line( 'TBC get value for a key' );
        }
    }

    /**
     * Print multilines of input text via WP-CLI
     */
    public function multilinePrint( string $string ) : void {
        $msg = trim( str_replace( [ "\r", "\n" ], '', $string ) );

        $msg = preg_replace( '!\s+!', ' ', $msg );

        WP_CLI::line( PHP_EOL . $msg . PHP_EOL );
    }
}

