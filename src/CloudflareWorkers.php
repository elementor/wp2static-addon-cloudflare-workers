<?php

/**
 * CloudflareWorkers.php
 *
 * @package           WP2StaticCloudflareWorkers
 * @author            Leon Stafford <me@ljs.dev>
 * @license           The Unlicense
 * @link              https://unlicense.org
 */

declare(strict_types=1);

namespace WP2StaticCloudflareWorkers;

use WP2StaticGuzzleHttp\Client;

/**
 * CloudflareWorkers Client Functions
 */
class CloudflareWorkers
{

    public string $accountID;
    public string $namespaceID;
    public string $apiToken;
    public Client $client;
    /** @var array<string> $headers Client headers */
    public array $headers;
    /** @var array<string> $keyNames List of key names */
    public array $keyNames;

    public const MAX_KEYS_DELETE = 10000;

    public function __construct()
    {
        $this->accountID = Controller::getValue('accountID');
        $this->namespaceID = Controller::getValue('namespaceID');
        $this->apiToken = \WP2Static\CoreOptions::encrypt_decrypt(
            'decrypt',
            Controller::getValue('apiToken')
        );

        if ($this->accountID === '' || $this->namespaceID === '' || $this->apiToken === '') {
            $err = 'Unable to connect to Cloudflare API without ' .
            'API Token, Account ID & Namespace ID set';
            \WP2Static\WsLog::l($err);
        }

        $this->client = new Client([ 'base_uri' => 'https://api.cloudflare.com/client/v4/' ]);
        $this->headers = [ 'Authorization' => 'Bearer ' . $this->apiToken ];
        $this->keyNames = [];
    }

    public function getPageOfKeys( string $cursor ): void
    {
        $res = $this->client->request(
            'GET',
            "accounts/$this->accountID/storage/kv/namespaces/$this->namespaceID/keys",
            [
                'headers' => $this->headers,
                'query' => [
                    'limit' => 1000,
                    'cursor' => $cursor,

                ],
            ],
        );

        $result = json_decode((string)$res->getBody());

        if ($result === null) {
            return;
        }

        if ($result->errors) {
            \WP2Static\WsLog::l('Failed to retrieve whole list of KV keys');
            \WP2Static\WsLog::l(join(PHP_EOL, $result->errors));
            exit(1);
        }

        if ($result->messages) {
            \WP2Static\WsLog::l('Messages from CF API');
            \WP2Static\WsLog::l(join(PHP_EOL, (array)$result->errors));
        }

        if ($result->success !== true) {
            return;
        }

        foreach ($result->result as $key) {
            $this->keyNames[] = $key->name;
        }

        // phpcs:ignore Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps
        $cursor = $result->result_info->cursor;

        if ($cursor === '') {
            return;
        }

        $this->getPageOfKeys($cursor);
    }

    /**
     * Get all key names for namespace
     *
     * @return array<string> Array of strings
     */
    public function listKeys(): array
    {
        \WP2Static\WsLog::l('Starting to retrieving list of KV keys');
        $this->getPageOfKeys('');

        \WP2Static\WsLog::l('Completed retrieving list of KV keys');
        return $this->keyNames;
    }

    public function deleteKeys(): bool
    {
        \WP2Static\WsLog::l('Starting to delete all KV keys in namespace');
        $this->getPageOfKeys('');

        $totalKeys = count($this->keyNames);
        \WP2Static\WsLog::l("Attempting to delete $totalKeys keys");

        if ($totalKeys < 1) {
            return false;
        }

        // Note: API allows bulk deletion up to 10,000 at a time
        $batches = ceil($totalKeys / self::MAX_KEYS_DELETE);

        for ($batch = 0; $batch < $batches; $batch += 1) {
            \WP2Static\WsLog::l('Deleting batch ' . ( $batch + 1 ) . " of $batches");

            $keysToDelete = array_slice(
                $this->keyNames,
                $batch * self::MAX_KEYS_DELETE,
                self::MAX_KEYS_DELETE
            );

            \WP2Static\WsLog::l(count($keysToDelete) . ' keys in batch');

            $res = $this->client->request(
                'DELETE',
                "accounts/$this->accountID/storage/kv/namespaces/" .
                "$this->namespaceID/bulk",
                [
                    'headers' => $this->headers,
                    'json' => $keysToDelete,
                ],
            );

            $result = json_decode((string)$res->getBody());

            if (! $result->success) {
                \WP2Static\WsLog::l('Failed during deleting all KV keys in namespace');
                return false;
            }
        }

        \WP2Static\WsLog::l('Completed deleting all KV keys in namespace');
        return true;
    }
}
