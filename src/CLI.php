<?php

/**
 * CLI.php
 *
 * @package           WP2StaticCloudflareWorkers
 * @author            Leon Stafford <me@ljs.dev>
 * @license           The Unlicense
 * @link              https://unlicense.org
 */

declare(strict_types=1);

namespace WP2StaticCloudflareWorkers;

use WP_CLI;

/**
 * WP2StaticCloudflareWorkers WP-CLI commands
 *
 * Registers WP-CLI commands for WP2StaticCloudflareWorkers under main wp2static cmd
 *
 * Usage: wp wp2static options set s3Bucket mybucketname
 */
class CLI
{

    /**
     * Cloudflare Workers add-on commands
     *
     * @param array<string> $args CLI args
     * @param array<string> $assocArgs CLI args
     */
    // phpcs:ignore NeutronStandard.Functions.LongFunction.LongFunction
    public function cloudflareWorkers(
        array $args,
        array $assocArgs
    ): void {
        $action = $args[0] ?? null;
        $arg = $args[1] ?? null;

        if ($action === null) {
            WP_CLI::error('Missing required argument: <options>');
        }

        if ($action === 'options') {
            if ($arg === null) {
                WP_CLI::error('Missing required argument: <get|set|list>');
            }

            $optionName = $args[2] ?? null;

            if ($arg === 'get') {
                if ($optionName === null) {
                    WP_CLI::error('Missing required argument: <option-name>');
                    return;
                }

                // decrypt apiToken
                $optionValue = $optionName === 'apiToken' ? \WP2Static\CoreOptions::encrypt_decrypt(
                    'decrypt',
                    Controller::getValue($optionName)
                ) : Controller::getValue($optionName);

                WP_CLI::line($optionValue);
            }

            if ($arg === 'set') {
                if ($optionName === null) {
                    WP_CLI::error('Missing required argument: <option-name>');
                    return;
                }

                $optionValue = $args[3] ?? null;

                if ($optionValue === null) {
                    $optionValue = '';
                }

                // decrypt apiToken
                if ($optionName === 'apiToken') {
                    $optionValue = \WP2Static\CoreOptions::encrypt_decrypt(
                        'encrypt',
                        $optionValue
                    );
                }

                Controller::saveOption($optionName, $optionValue);
            }

            if ($arg === 'list') {
                $options = Controller::getOptions();

                // decrypt apiToken
                $options['apiToken']->value = \WP2Static\CoreOptions::encrypt_decrypt(
                    'decrypt',
                    $options['apiToken']->value
                );

                WP_CLI\Utils\format_items(
                    'table',
                    $options,
                    [ 'name', 'value' ]
                );
            }
        }

        if ($action === 'keys') {
            if ($arg === 'list') {
                $client = new CloudflareWorkers();

                $keyNames = $client->listKeys();

                foreach ($keyNames as $name) {
                    WP_CLI::line($name);
                }
            }

            if ($arg === 'count') {
                $client = new CloudflareWorkers();

                $keyNames = $client->listKeys();

                WP_CLI::line((string)count($keyNames));
            }

            if ($arg === 'delete') {
                if (! isset($assocArgs['force'])) {
                    $this->multilinePrint(
                        "no --force given. Please type 'yes' to confirm
                        deletion of all keys in namespace"
                    );

                    $userval = trim((string)fgets(STDIN));

                    if ($userval !== 'yes') {
                        WP_CLI::error('Failed to delete namespace keys');
                    }
                }

                $client = new CloudflareWorkers();

                $success = $client->deleteKeys();

                if (! $success) {
                    WP_CLI::error('Failed to delete keys (maybe there weren\'t any?');
                }

                WP_CLI::success('Deleted all keys in namespace');
            }
        }

        if ($action !== 'get_value') {
            return;
        }

        WP_CLI::line('TBC get value for a key');
    }

    /**
     * Print multilines of input text via WP-CLI
     */
    public function multilinePrint( string $string ): void
    {
        $msg = trim(str_replace([ "\r", "\n" ], '', $string));

        $msg = preg_replace('!\s+!', ' ', $msg);

        WP_CLI::line(PHP_EOL . $msg . PHP_EOL);
    }
}
