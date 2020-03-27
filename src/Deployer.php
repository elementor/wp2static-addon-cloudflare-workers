<?php

namespace WP2StaticCloudflareWorkers;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use GuzzleHttp\Client;

class Deployer {

    public function upload_files( string $processed_site_path ) : void {
        if ( Controller::getValue( 'useBulkUpload' ) ) {
            $this->bulk_upload_files( $processed_site_path );
        }

        $this->singlular_upload_files( $processed_site_path );
    }

    public function singlular_upload_files( string $processed_site_path ) : void {
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

                $mime_type = MimeTypes::GuessMimeType( $filename );

                // TODO: try / catch before recording as success/adding to DeployCache

                // put file contents to path key
                $res = $client->request(
                    'PUT',
                    "accounts/$account_id/storage/kv/namespaces/$namespace_id/values/$key",
                    [
                        'headers' => $headers,
                        'body' => file_get_contents( $filename ),
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

                if ( $res->getBody() ) {
                    $result = json_decode( (string) $res->getBody() );

                    if ( $result->success ) {
                        \WP2Static\DeployCache::addFile( $filename );
                    }
                } else {
                    $err = 'Failed to deploy file: ' . $filename;
                    \WP2Static\WsLog::l( $err );

                    if ( $result->error ) {
                        \WP2Static\WsLog::l( $result->error );
                    }
                }
            }
        }
    }

    // TODO: see if efficient implementation is possible
    public function bulk_upload_files( string $processed_site_path ) : void {
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
            'Content-Type' => 'application/json',
        ];


        // iterate each file in ProcessedSite
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $processed_site_path,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );


        // per batch write is < 100MB or < 10,000 files
        $batch_file_count = 0;
        $batch_file_size = 0;
        $batch_number = 0;
        $batches = [];


        $counter = 0;
        $debug_limit = 5;

        foreach ( $iterator as $filename => $file_object ) {
            // $counter++;
            // if ( $counter > $debug_limit ) {
            //     continue;
            // }

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

                // $key = urlencode( str_replace( $processed_site_path, '', $filename ) );
                $key = str_replace( $processed_site_path, '', $filename );
                $mime_type = MimeTypes::GuessMimeType( $filename );

                $put_object = new \stdClass();
                $put_object->kv_key = $key;
                $put_object->content_type = $mime_type;
                $put_object->filename = $filename;

                $batches[ $batch_number ][] = $put_object;

            }

            foreach ( $batches as $batch ) {

                $bulk_key_values = [];

                foreach ( $batch as $put_object ) {
                    // error_log( print_r( $put_object, true ) );

                    // put file contents to path key
                    $bulk_key_values[] = [
                        'key' => $put_object->kv_key,
                        'value' => base64_encode( file_get_contents( $put_object->filename ) ),
                        'base64' => true,
                    ];

                    // put content type to path_ct key
                    $bulk_key_values[] = [
                        'key' => $put_object->kv_key . "_ct",
                        'value' => $put_object->content_type,
                    ];

                    // // TODO: try / catch before recording as success/adding to DeployCache
                    // // put file contents to path key
                    // $res = $client->request(
                    //     'PUT',
                    //     "accounts/$account_id/storage/kv/namespaces/$namespace_id/values/$key",
                    //     [
                    //         'headers' => $headers,
                    //         'body' => 'some stuff (updated)',
                    //     ],
                    // );

                    // // put content type to path_ct key
                    // $res = $client->request(
                    //     'PUT',
                    //     "accounts/$account_id/storage/kv/namespaces/$namespace_id/values/${key}_ct",
                    //     [
                    //         'headers' => $headers,
                    //         'body' => $mime_type,
                    //     ],
                    // );

                    // if ( $result['@metadata']['statusCode'] === 200 ) {
                    //     \WP2Static\DeployCache::addFile( $filename );
                    // }
                }

                // $bulk_key_values = [
                //     [
                //         'key' => '000test',
                //         'value' => 'some content',
                //     ],
                //     [
                //         'key' => '000test_ct',
                //         'value' => 'some content type',
                //     ],
                // ];

                // error_log( print_r( $bulk_key_values, true ) );

                // error_log( json_encode( $bulk_key_values ) );

                $res = $client->request(
                    'PUT',
                    "accounts/$account_id/storage/kv/namespaces/$namespace_id/bulk",
                    [
                        'headers' => $headers,
                        'json' => $bulk_key_values,
                    ],
                );
            }
        }
    }
}

