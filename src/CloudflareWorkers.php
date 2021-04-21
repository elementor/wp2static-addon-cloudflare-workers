<?php

declare(strict_types=1);

namespace WP2StaticCloudflareWorkers;

use GuzzleHttp\Client;

/**
 * CloudflareWorkers
 *
 * @property string $account_id Account ID
 * @property string $namespace_id Namespace ID
 * @property string $api_token API Token
 * @property \WP2StaticCloudflareWorkers\GuzzleHttp\Client $client Guzzle Client
 * @property array $headers Client headers
 * @property array $key_names List of key names
 */
class CloudflareWorkers
{

    public $account_id;
    public $namespace_id;
    public $api_token;
    public $client;
    public $headers;
    public $key_names;

    const MAX_KEYS_DELETE = 10000;

    public function __construct()
    {
        $this->account_id = Controller::getValue('accountID');
        $this->namespace_id = Controller::getValue('namespaceID');
        $this->api_token = \WP2Static\CoreOptions::encrypt_decrypt(
            'decrypt',
            Controller::getValue('apiToken')
        );

        if (! $this->account_id || ! $this->namespace_id || ! $this->api_token) {
            $err = 'Unable to connect to Cloudflare API without ' .
            'API Token, Account ID & Namespace ID set';
            \WP2Static\WsLog::l($err);
        }

        $this->client = new Client([ 'base_uri' => 'https://api.cloudflare.com/client/v4/' ]);
        $this->headers = [ 'Authorization' => 'Bearer ' . $this->api_token ];
        $this->key_names = [];
    }

    public function get_page_of_keys( string $cursor ): void
    {
        $res = $this->client->request(
            'GET',
            "accounts/$this->account_id/storage/kv/namespaces/$this->namespace_id/keys",
            [
                'headers' => $this->headers,
                'query' => [
                    'limit' => 1000,
                    'cursor' => $cursor,

                ],
            ],
        );

        $result = json_decode((string)$res->getBody());

        if (!$result) {
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
            $this->key_names[] = $key->name;
        }

        $cursor = $result->result_info->cursor;

        if ($cursor === '') {
            return;
        }

        $this->get_page_of_keys($cursor);
    }

    /**
     * Get all key names for namespace
     *
     * @return array<string> Array of strings
     */
    public function list_keys(): array
    {
        \WP2Static\WsLog::l('Starting to retrieving list of KV keys');
        $this->get_page_of_keys('');

        \WP2Static\WsLog::l('Completed retrieving list of KV keys');
        return $this->key_names;
    }

    public function delete_keys(): bool
    {
        \WP2Static\WsLog::l('Starting to delete all KV keys in namespace');
        $this->get_page_of_keys('');

        $total_keys = count($this->key_names);
        \WP2Static\WsLog::l("Attempting to delete $total_keys keys");

        if (! $total_keys) {
            return false;
        }

        // Note: API allows bulk deletion up to 10,000 at a time
        $batches = ceil($total_keys / self::MAX_KEYS_DELETE);

        for ($batch = 0; $batch < $batches; $batch++) {
            \WP2Static\WsLog::l('Deleting batch ' . ( $batch + 1 ) . " of $batches");

            $keys_to_delete = array_slice(
                $this->key_names,
                $batch * self::MAX_KEYS_DELETE,
                self::MAX_KEYS_DELETE
            );

            \WP2Static\WsLog::l(count($keys_to_delete) . ' keys in batch');

            $res = $this->client->request(
                'DELETE',
                "accounts/$this->account_id/storage/kv/namespaces/" .
                "$this->namespace_id/bulk",
                [
                    'headers' => $this->headers,
                    'json' => $keys_to_delete,
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
