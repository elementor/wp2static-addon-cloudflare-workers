<?php

/**
 * Deployer.php
 *
 * @package           WP2StaticCloudflareWorkers
 * @author            Leon Stafford <me@ljs.dev>
 * @license           The Unlicense
 * @link              https://unlicense.org
 */

declare(strict_types=1);

namespace WP2StaticCloudflareWorkers;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use GuzzleHttp\Client;
use WP2Static\CoreOptions;

/**
 * Deploys to Cloudflare Workers
 */
class Deployer
{

    public function uploadFiles( string $processedSitePath ): void
    {
        if (Controller::getValue('useBulkUpload') === '1') {
            $this->bulkUploadFiles($processedSitePath);
            return;
        }

        $this->singularUploadFiles($processedSitePath);
    }

    // phpcs:ignore NeutronStandard.Functions.LongFunction.LongFunction
    public function singularUploadFiles( string $processedSitePath ): void
    {
        if (! is_dir($processedSitePath)) {
            return;
        }

        $accountID = Controller::getValue('accountID');
        $namespaceID = Controller::getValue('namespaceID');
        $apiToken = CoreOptions::encrypt_decrypt(
            'decrypt',
            Controller::getValue('apiToken')
        );

        // TODO: check decrypted apiToken can ever actually be empty string
        if ($accountID === '' || $namespaceID === '' || $apiToken === '') {
            $err = 'Unable to connect to Cloudflare API without ' .
            'API Token, Account ID & Namespace ID set';
            \WP2Static\WsLog::l($err);
            return;
        }

        $deployCount = 0;
        $errorCount = 0;
        $cacheHits = 0;

        $client = new Client([ 'base_uri' => 'https://api.cloudflare.com/client/v4/' ]);

        $headers = [
            'Authorization' => 'Bearer ' . $apiToken,
        ];

        // iterate each file in ProcessedSite
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $processedSitePath,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
        foreach ($iterator as $filename => $unusedVar) {
            $realFilepath = realpath($filename);

            $key = str_replace($processedSitePath, '', $filename);

            if (\WP2Static\DeployCache::fileisCached($key)) {
                $cacheHits += 1;
                continue;
            }

            if (! is_string($realFilepath)) {
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
            // TODO: check using raw encoding
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
            $key = urlencode($key);

            $mimeType = MimeTypes::guessMimeType($filename);

            // TODO: try / catch before recording as success/adding to DeployCache

            // put file contents to path key
            $res = $client->request(
                'PUT',
                "accounts/$accountID/storage/kv/namespaces/$namespaceID/values/$key",
                [
                    'headers' => $headers,
                    'body' => file_get_contents($filename),
                ],
            );

            // put content type to path_ct key
            $res = $client->request(
                'PUT',
                "accounts/$accountID/storage/kv/namespaces/$namespaceID/values/${key}_ct",
                [
                    'headers' => $headers,
                    'body' => $mimeType,
                ],
            );

            $result = json_decode((string)$res->getBody());

            if ($result === null) {
                continue;
            }

            if ($result->success) {
                $deployCount += 1;
                \WP2Static\DeployCache::addFile(
                    str_replace($processedSitePath, '', $filename)
                );
            } else {
                $errorCount += 1;
                $err = 'Failed to deploy file: ' . $filename;
                \WP2Static\WsLog::l($err);

                if ($result->error) {
                    \WP2Static\WsLog::l($result->error);
                }
            }
        }

        \WP2Static\WsLog::l(
            "Deployment complete. $deployCount deployed, " .
            "$cacheHits skipped (cached), $errorCount errors."
        );

        $args = [
            'deployCount' => $deployCount,
            'errorCount' => $errorCount,
            'cacheHits' => $cacheHits,
        ];

        do_action('wp2static_cloudflare_workers_deployment_complete', $args);
    }

    // phpcs:ignore NeutronStandard.Functions.LongFunction.LongFunction
    public function bulkUploadFiles( string $processedSitePath ): void
    {
        if (! is_dir($processedSitePath)) {
            return;
        }

        $accountID = Controller::getValue('accountID');
        $namespaceID = Controller::getValue('namespaceID');
        $apiToken = CoreOptions::encrypt_decrypt(
            'decrypt',
            Controller::getValue('apiToken')
        );

        // TODO: check decrypted apiToken can ever actually be empty string
        if ($accountID === '' || $namespaceID === '' || $apiToken === '') {
            $err = 'Unable to connect to Cloudflare API without ' .
            'API Token, Account ID & Namespace ID set';
            \WP2Static\WsLog::l($err);
            return;
        }

        $deployCount = 0;
        $errorCount = 0;
        $cacheHits = 0;

        $client = new Client([ 'base_uri' => 'https://api.cloudflare.com/client/v4/' ]);

        $headers = [
            'Authorization' => 'Bearer ' . $apiToken,
            'Content-Type' => 'application/json',
        ];

        // iterate each file in ProcessedSite
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $processedSitePath,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        // per batch write is < 100MB or < 10,000 files
        // TODO: implement batch file size checking
        // $batchFileSize = 0;
        $batchNumber = 0;
        $batches = [];

        $filesInBatch = 0;
        // TODO: add select menu for user-overriding batch size or be clever and auto-retry
        // with smaller batch sizes on errors
        $fileLimit = 10000;
        $pathsInBatch = [];

        // TODO: Q: will iterator_to_array() allow rm'ing unused var?
        // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
        foreach ($iterator as $filename => $unusedVar) {
            if ($filesInBatch === $fileLimit) {
                $batchNumber += 1;
                $filesInBatch = 0;
            }

            $realFilepath = realpath($filename);

            $key = str_replace($processedSitePath, '', $filename);

            if (\WP2Static\DeployCache::fileisCached($key)) {
                $cacheHits += 1;
                continue;
            }

            if (! is_string($realFilepath)) {
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
            $mimeType = MimeTypes::guessMimeType($filename);

            $putObject = new \stdClass();
            $putObject->kvKey = $key;
            $putObject->contentType = $mimeType;
            $putObject->filename = $filename;

            $batches[$batchNumber][] = $putObject;

            $filesInBatch += 1;
        }

        $totalBatches = count($batches);

        foreach ($batches as $index => $batch) {
            \WP2Static\WsLog::l('Uploading batch ' . ( $index + 1 ) . " of $totalBatches");

            $bulkKeyValues = [];

            $batchSize = count($batch);

            foreach ($batch as $putObject) {
                $pathsInBatch[] = $putObject->kvKey;

                // put file contents to path key
                $bulkKeyValues[] = [
                    'key' => $putObject->kvKey,
                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
                    'value' => base64_encode(
                        (string)file_get_contents($putObject->filename)
                    ),
                    'base64' => true,
                ];

                // put content type to path_ct key
                $bulkKeyValues[] = [
                    'key' => $putObject->kvKey . '_ct',
                    'value' => $putObject->contentType,
                ];
            }

            try {
                $res = $client->request(
                    'PUT',
                    "accounts/$accountID/storage/kv/namespaces/$namespaceID/bulk",
                    [
                        'headers' => $headers,
                        'json' => $bulkKeyValues,
                    ],
                );
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                if ($e->hasResponse()) {
                    $response = $e->getResponse();

                    if ($response !== null) {
                        \WP2Static\WsLog::l(
                            'Error response from Cloudflare API: ' .
                            $response->getStatusCode() . ' ' .
                            $response->getReasonPhrase()
                        );
                    }
                }

                $errorCount += $batchSize;

                // skip current batch
                continue;
            }

            $result = json_decode((string)$res->getBody());

            if ($result === null) {
                continue;
            }

            if (!$result->success) {
                continue;
            }

            $deployCount += $batchSize;

            foreach ($batch as $putObject) {
                // TODO: optimize with DeployCache::addBulkFiles()
                \WP2Static\DeployCache::addFile(
                    str_replace($processedSitePath, '', $putObject->filename)
                );
            }
        }

        \WP2Static\WsLog::l(
            "Deployment complete. $deployCount deployed, " .
            "$cacheHits skipped (cached), $errorCount errors."
        );

        $args = [
            'deployCount' => $deployCount,
            'errorCount' => $errorCount,
            'cacheHits' => $cacheHits,
        ];

        do_action('wp2static_cloudflare_workers_deployment_complete', $args);
    }
}
