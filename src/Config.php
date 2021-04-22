<?php

/**
 * Config.php
 *
 * @package           WP2StaticCloudflareWorkers
 * @author            Leon Stafford <me@ljs.dev>
 * @license           The Unlicense
 * @link              https://unlicense.org
 */

declare(strict_types=1);

namespace WP2StaticCloudflareWorkers;

/**
 * Immutable configuration.
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $container;

    /**
     * @param array<string, mixed> $container
     * @return void
     */
    public static function init(array $container)
    {
        if (isset(self::$container)) {
            return;
        }

        self::$container = $container;
    }

    public static function get(string $key): ?string
    {
        if (! isset(self::$container) || ! array_key_exists($key, self::$container)) {
            return null;
        }

        return self::$container[$key];
    }
}
