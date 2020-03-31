<?php

namespace WP2StaticCloudflareWorkers;

use GuzzleHttp\Client;

/**
 * CloudflareWorkers
 *
 * @property string $account_id Account ID
 * @property string $namespace_id Namespace ID
 * @property string $api_token API Token
 * @property GuzzleHttp\Client $client Guzzle Client
 * @property array $headers Client headers
 * @property array $key_names List of key names
 */
class CloudflareWorkers {

    public $account_id;
    public $namespace_id;
    public $api_token;
    public $client;
    public $headers;
    public $key_names;

    public function __construct() {
        $this->account_id = Controller::getValue( 'accountID' );
        $this->namespace_id = Controller::getValue( 'namespaceID' );
        $this->api_token = \WP2StaticCloudflareWorkers\Controller::encrypt_decrypt(
            'decrypt',
            Controller::getValue( 'apiToken' )
        );

        if ( ! $this->account_id || ! $this->namespace_id || ! $this->api_token ) {
            $err = 'Unable to connect to Cloudflare API without ' .
            'API Token, Account ID & Namespace ID set';
            \WP2Static\WsLog::l( $err );
        }

        $this->client = new Client( [ 'base_uri' => 'https://api.cloudflare.com/client/v4/' ] );
        $this->headers = [ 'Authorization' => 'Bearer ' . $this->api_token ];
        $this->key_names = [];
    }


    public function get_page_of_keys( string $cursor ) : void {
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

        $result = json_decode( (string) $res->getBody() );

        if ( $result ) {
            if ( $result->errors ) {
                error_log( 'failed to retrieve whole list of KV keys' );
                \WP2Static\WsLog::l( join( PHP_EOL, $result->errors ) );
                exit( 1 );
            }

            if ( $result->messages ) {
                error_log( print_r( $result->messages, true ) );
            }

            if ( $result->success === true ) {
                foreach ( $result->result as $key ) {
                    $this->key_names[] = $key->name;
                }

                $cursor = $result->result_info->cursor;

                if ( $cursor !== '' ) {
                    $this->get_page_of_keys( $cursor );
                }
            }
        }
    }

    /**
     * Get all key names for namespace
     *
     * @return string[] Array of strings
     */
    public function list_keys() : array {
        $this->get_page_of_keys( '' );

        return $this->key_names;
    }
}

