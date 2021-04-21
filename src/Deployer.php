<?php

declare(strict_types=1);

namespace WP2StaticCloudflareWorkers;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use GuzzleHttp\Client;

class Deployer
{

    public function upload_files( string $processed_site_path ): void
    {
        if (Controller::getValue('useBulkUpload')) {
            $this->bulk_upload_files($processed_site_path);
            return;
        }

        $this->singlular_upload_files($processed_site_path);
    }

    public function singlular_upload_files( string $processed_site_path ): void
    {
        if (! is_dir($processed_site_path)) {
            return;
        }

        $account_id = Controller::getValue('accountID');
        $namespace_id = Controller::getValue('namespaceID');
        $api_token = \WP2Static\CoreOptions::encrypt_decrypt(
            'decrypt',
            Controller::getValue('apiToken')
        );

        if (! $account_id || ! $namespace_id || ! $api_token) {
            $err = 'Unable to connect to Cloudflare API without ' .
            'API Token, Account ID & Namespace ID set';
            \WP2Static\WsLog::l($err);
            return;
        }

        $deploy_count = 0;
        $error_count = 0;
        $cache_hits = 0;

        $client = new Client([ 'base_uri' => 'https://api.cloudflare.com/client/v4/' ]);

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

        foreach ($iterator as $filename => $file_object) {
            $base_name = basename($filename);

            if ($base_name === '.' || $base_name === '..') {
                continue;
            }

            $real_filepath = realpath($filename);

            $key = str_replace($processed_site_path, '', $filename);

            error_log('checking deploy cache for ' . $key);
            if (\WP2Static\DeployCache::fileisCached($key)) {
                $cache_hits++;
                continue;
            }

            if (! $real_filepath) {
                $err = 'Trying to deploy unknown file: ' . $filename;
                \WP2Static\WsLog::l($err);
                continue;
            }

            // Standardise all paths to use / (Windows support)
            $filename = str_replace('\\', '/', $filename);

            if (! is_string($filename)) {
                continue;
            }

            // NOTE: urlencode needed on singlular transfers but not bulk
            $key = urlencode($key);

            $mime_type = MimeTypes::GuessMimeType($filename);

            // TODO: try / catch before recording as success/adding to DeployCache

            // put file contents to path key
            $res = $client->request(
                'PUT',
                "accounts/$account_id/storage/kv/namespaces/$namespace_id/values/$key",
                [
                    'headers' => $headers,
                    'body' => file_get_contents($filename),
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

            $result = json_decode((string)$res->getBody());

            if (!$result) {
                continue;
            }

            if ($result->success) {
                $deploy_count++;
                \WP2Static\DeployCache::addFile(
                    str_replace($processed_site_path, '', $filename)
                );
            } else {
                $error_count++;
                $err = 'Failed to deploy file: ' . $filename;
                \WP2Static\WsLog::l($err);

                if ($result->error) {
                    \WP2Static\WsLog::l($result->error);
                }
            }
        }

        \WP2Static\WsLog::l(
            "Deployment complete. $deploy_count deployed, " .
            "$cache_hits skipped (cached), $error_count errors."
        );

        $args = [
            'deploy_count' => $deploy_count,
            'error_count' => $error_count,
            'cache_hits' => $cache_hits,
        ];

        do_action('wp2static_cloudflare_workers_deployment_complete', $args);
    }

    public function bulk_upload_files( string $processed_site_path ): void
    {
        if (! is_dir($processed_site_path)) {
            return;
        }

        $account_id = Controller::getValue('accountID');
        $namespace_id = Controller::getValue('namespaceID');
        $api_token = \WP2Static\CoreOptions::encrypt_decrypt(
            'decrypt',
            Controller::getValue('apiToken')
        );

        if (! $account_id || ! $namespace_id || ! $api_token) {
            $err = 'Unable to connect to Cloudflare API without ' .
            'API Token, Account ID & Namespace ID set';
            \WP2Static\WsLog::l($err);
            return;
        }

        $deploy_count = 0;
        $error_count = 0;
        $cache_hits = 0;

        $client = new Client([ 'base_uri' => 'https://api.cloudflare.com/client/v4/' ]);

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
        $batch_file_size = 0;
        $batch_number = 0;
        $batches = [];

        $files_in_batch = 0;
        // TODO: add select menu for user-overriding batch size or be clever and auto-retry
        // with smaller batch sizes on errors
        $file_limit = 10000;
        $paths_in_batch = [];

        foreach ($iterator as $filename => $file_object) {
            if ($files_in_batch === $file_limit) {
                $batch_number++;
                $files_in_batch = 0;
            }

            $base_name = basename($filename);
            $real_filepath = realpath($filename);

            $key = str_replace($processed_site_path, '', $filename);

            if (\WP2Static\DeployCache::fileisCached($key)) {
                $cache_hits++;
                continue;
            }

            if (! $real_filepath) {
                $err = 'Trying to deploy unknown file: ' . $filename;
                \WP2Static\WsLog::l($err);
                continue;
            }

            // Standardise all paths to use / (Windows support)
            $filename = str_replace('\\', '/', $filename);

            if (! is_string($filename)) {
                continue;
            }

            $key = str_replace('/index.html', '/', $key);
            $mime_type = MimeTypes::GuessMimeType($filename);

            $put_object = new \stdClass();
            $put_object->kv_key = $key;
            $put_object->content_type = $mime_type;
            $put_object->filename = $filename;

            $batches[$batch_number][] = $put_object;

            $files_in_batch++;
        }

        $total_batches = count($batches);

        foreach ($batches as $index => $batch) {
            \WP2Static\WsLog::l('Uploading batch ' . ( $index + 1 ) . " of $total_batches");

            $bulk_key_values = [];

            $batch_size = count($batch);

            foreach ($batch as $put_object) {
                $paths_in_batch[] = $put_object->kv_key;

                // put file contents to path key
                $bulk_key_values[] = [
                    'key' => $put_object->kv_key,
                    'value' => base64_encode(
                        (string)file_get_contents($put_object->filename)
                    ),
                    'base64' => true,
                ];

                // put content type to path_ct key
                $bulk_key_values[] = [
                    'key' => $put_object->kv_key . '_ct',
                    'value' => $put_object->content_type,
                ];
            }

            try {
                $res = $client->request(
                    'PUT',
                    "accounts/$account_id/storage/kv/namespaces/$namespace_id/bulk",
                    [
                        'headers' => $headers,
                        'json' => $bulk_key_values,
                    ],
                );
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                if ($e->hasResponse()) {
                    $response = $e->getResponse();

                    if ($response) {
                        \WP2Static\WsLog::l(
                            'Error response from Cloudflare API: ' .
                            $response->getStatusCode() . ' ' .
                            $response->getReasonPhrase()
                        );
                    }
                }

                $error_count += $batch_size;

                // skip current batch
                continue;
            }

            $result = json_decode((string)$res->getBody());

            if (!$result) {
                continue;
            }

            if (!$result->success) {
                continue;
            }

            $deploy_count += $batch_size;

            foreach ($batch as $put_object) {
                // TODO: optimize with DeployCache::addBulkFiles()
                \WP2Static\DeployCache::addFile(
                    str_replace($processed_site_path, '', $put_object->filename)
                );
            }
        }

        \WP2Static\WsLog::l(
            "Deployment complete. $deploy_count deployed, " .
            "$cache_hits skipped (cached), $error_count errors."
        );

        $args = [
            'deploy_count' => $deploy_count,
            'error_count' => $error_count,
            'cache_hits' => $cache_hits,
        ];

        do_action('wp2static_cloudflare_workers_deployment_complete', $args);
    }
}
