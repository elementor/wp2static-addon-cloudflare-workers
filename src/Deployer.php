<?php

namespace WP2StaticCloudflareWorkers;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use GuzzleHttp\Client;

class Deployer {

    public function upload_files( string $processed_site_path ) : void {
        if ( ! is_dir( $processed_site_path ) ) {
            return;
        }

        $account_id = Controller::getValue( 'accountID' );
        $namespace_id = Controller::getValue( 'namespaceID' );
        $api_token = \WP2StaticCloudflareWorkers\Controller::encrypt_decrypt(
            'decrypt',
            Controller::getValue( 'apiToken' )
        );

        if ( ! $account_id || ! $namespace_id || ! $api_token ) {
            $err = 'Unable to deploy without API Token & Namespace ID set';
            \WP2Static\WsLog::l( $err );
        }

        $client = new Client( [ 'base_uri' => 'https://api.cloudflare.com/client/v4/' ] );

        $headers = [
            'Authorization' => 'Bearer ' . $api_token,
        ];


        // iterate each file in ProcessedSite
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $processed_site_path,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        foreach ( $iterator as $filename => $file_object ) {
            $base_name = basename( $filename );
            if ( $base_name != '.' && $base_name != '..' ) {
                $real_filepath = realpath( $filename );

                if ( \WP2Static\DeployCache::fileisCached( $filename ) ) {
                    continue;
                }

                if ( ! $real_filepath ) {
                    $err = 'Trying to deploy unknown file: ' . $filename;
                    \WP2Static\WsLog::l( $err );
                    continue;
                }

                // Standardise all paths to use / (Windows support)
                $filename = str_replace( '\\', '/', $filename );

                if ( ! is_string( $filename ) ) {
                    continue;
                }

                $key = urlencode( str_replace( $processed_site_path, '', $filename ) );

                error_log($key);

                $mime_type = MimeTypes::GuessMimeType( $filename );

                // TODO: try / catch before recording as success/adding to DeployCache

                // put file contents to path key
                $res = $client->request(
                    'PUT',
                    "accounts/$account_id/storage/kv/namespaces/$namespace_id/values/$key",
                    [
                        'headers' => $headers,
                        'body' => 'some stuff (updated)',
                    ],
                );

                // put content type to path_ct key
                $res = $client->request(
                    'PUT',
                    "accounts/$account_id/storage/kv/namespaces/$namespace_id/values/${key}_ct",
                    [
                        'headers' => $headers,
                        'body' => $mime_type,
                    ],
                );

                // if ( $result['@metadata']['statusCode'] === 200 ) {
                //     \WP2Static\DeployCache::addFile( $filename );
                // }
            }
        }
    }
}

