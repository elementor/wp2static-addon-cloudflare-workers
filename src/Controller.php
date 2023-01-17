<?php

/**
 * Controller.php
 *
 * @package           WP2StaticCloudflareWorkers
 * @author            Leon Stafford <me@ljs.dev>
 * @license           The Unlicense
 * @link              https://unlicense.org
 */

declare(strict_types=1);

namespace WP2StaticCloudflareWorkers;

use Latte;

/**
 * CloudflareWorkers Client Functions
 *
 */
class Controller
{
    public function __construct()
    {
        add_filter(
            'wp2static_add_menu_items',
            [ 'WP2StaticCloudflareWorkers\Controller', 'addSubmenuPage' ]
        );

        add_action(
            'admin_post_wp2static_cloudflare_workers_save_options',
            [ $this, 'saveOptionsFromUI' ],
            15,
            1
        );

        add_action(
            'wp2static_deploy',
            [ $this, 'deploy' ],
            15,
            1
        );

        add_action(
            'admin_menu',
            [ $this, 'addOptionsPage' ],
            15,
            1
        );

        add_action(
            'admin_enqueue_scripts',
            [ $this, 'wp2staticAdminScripts' ],
            0
        );

        do_action(
            'wp2static_register_addon',
            'wp2static-addon-cloudflare-workers',
            'deploy',
            'Cloudflare Workers Deployment',
            'https://wp2static.com/addons/cloudflare-workers/',
            'Deploys to Cloudflare Workers'
        );
    }

    /**
     *  Get all add-on options
     *
     *  @return array<mixed> All options
     */
    public static function getOptions(): array
    {
        global $wpdb;
        $options = [];

        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wp2static_addon_cloudflare_workers_options");

        foreach ($rows as $row) {
            $options[$row->name] = $row;
        }

        return $options;
    }

    /**
     * Seed options
     */
    // phpcs:ignore NeutronStandard.Functions.LongFunction.LongFunction
    public static function seedOptions(): void
    {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->prefix}wp2static_addon_cloudflare_workers_options " .
                    '(name, value, label, description) VALUES (%s, %s, %s, %s);',
                'apiToken',
                '',
                'API Token',
                'see https://dash.cloudflare.com/profile/api-tokens'
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->prefix}wp2static_addon_cloudflare_workers_options " .
                    '(name, value, label, description) VALUES (%s, %s, %s, %s);',
                'useBulkUpload',
                '1',
                'Bulk uploads',
                'Uploads files in batches'
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->prefix}wp2static_addon_cloudflare_workers_options " .
                    '(name, value, label, description) VALUES (%s, %s, %s, %s);',
                'namespaceID',
                '',
                'Namespace ID',
                'ie 3d61660f7f564f689b24fbb1f252c033'
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->prefix}wp2static_addon_cloudflare_workers_options " .
                    '(name, value, label, description) VALUES (%s, %s, %s, %s);',
                'accountID',
                '',
                'Account ID',
                'ie 13e736c51a7a73dabc0b83f75d3bedce'
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->prefix}wp2static_addon_cloudflare_workers_options " .
                    '(name, value, label, description) VALUES (%s, %s, %s, %s);',
                'batchSize',
                Deployer::BATCH_SIZE_DEFAULT,
                'Batch Size',
                'Number of files to include in each batch'
            )
        );
    }

    /**
     * Save options
     *
     * @param mixed $value option value to save
     */
    public static function saveOption( string $name, $value ): void
    {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'wp2static_addon_cloudflare_workers_options',
            [ 'value' => $value ],
            [ 'name' => $name ]
        );
    }

    public static function renderCloudflareWorkersPage(): void
    {
        self::createOptionsTable();
        self::seedOptions();

        $latte = new Latte\Engine();
        $latte->setTempDirectory(sys_get_temp_dir());

        $cloudflareWorkersPath =
            \WP2Static\SiteInfo::getPath('uploads') . 'wp2static-processed-site';

        $options = self::getOptions();

        $parameters = [
                'nonce_action' => wp_create_nonce('wp2static-cloudflare-workers-options'),
                'uploads_path' => \WP2Static\SiteInfo::getPath('uploads'),
                'admin_post_path' => esc_url(admin_url('admin-post.php')),
                'wp_referrer_path' => esc_url(admin_url('admin.php?page=wp2static-addon-cloudflare-workers')),
                'options' => $options,
                'decrypted_api_token' => $options['apiToken']->value ?
                    \WP2Static\CoreOptions::encrypt_decrypt('decrypt', $options['apiToken']->value) : '',
                'cloudflare_workers_url' => is_file($cloudflareWorkersPath) ?
                    \WP2Static\SiteInfo::getUrl('uploads') . 'wp2static-processed-site.cf' : '#',
        ];

        $latte->render(Config::get('pluginDir') . '/views/admin-page.latte', $parameters);
    }

    public function deploy( string $processedSitePath ): void
    {
        \WP2Static\WsLog::l('Cloudflare Workers add-on deploying');

        $cloudflareWorkersDeployer = new Deployer();
        $cloudflareWorkersDeployer->uploadFiles($processedSitePath);
    }

    public static function activateForSingleSite(): void
    {
        self::createOptionsTable();
        self::seedOptions();
    }

    public static function createOptionsTable(): void
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$wpdb->prefix}wp2static_addon_cloudflare_workers_options (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            value VARCHAR(255) NOT NULL,
            label VARCHAR(255) NULL,
            description VARCHAR(255) NULL,
            PRIMARY KEY  (id)
        ) $charsetCollate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // dbDelta doesn't handle unique indexes well.
        $indexes = $wpdb->query(
            "SHOW INDEX FROM {$wpdb->prefix}wp2static_addon_cloudflare_workers_options WHERE key_name = 'name'"
        );

        if ($indexes !== 0) {
            return;
        }

        $result = $wpdb->query(
            "CREATE UNIQUE INDEX name ON {$wpdb->prefix}wp2static_addon_cloudflare_workers_options (name)"
        );

        if ($result !== false) {
            return;
        }

        \WP2Static\WsLog::l(
            "Failed to create 'name' index on {$wpdb->prefix}wp2static_addon_cloudflare_workers_options."
        );
    }

    public static function deactivateForSingleSite(): void
    {
    }

    public static function deactivate( ?bool $networkWide = null ): void
    {
        if ($networkWide === true) {
            global $wpdb;

            $siteIDs = $wpdb->get_col(
                $wpdb->prepare(
                    'SELECT blog_id FROM %s WHERE siteID = %d;',
                    $wpdb->blogs,
                    $wpdb->siteid
                )
            );

            foreach ($siteIDs as $siteID) {
                switch_to_blog($siteID);
                self::deactivateForSingleSite();
            }

            restore_current_blog();
        } else {
            self::deactivateForSingleSite();
        }
    }

    public static function activate( ?bool $networkWide = null ): void
    {
        if ($networkWide === true) {
            global $wpdb;

            $siteIDs = $wpdb->get_col(
                $wpdb->prepare(
                    'SELECT blog_id FROM %s WHERE siteID = %d;',
                    $wpdb->blogs,
                    $wpdb->siteid
                )
            );

            foreach ($siteIDs as $siteID) {
                switch_to_blog($siteID);
                self::activateForSingleSite();
            }

            restore_current_blog();
        } else {
            self::activateForSingleSite();
        }
    }

    /**
     * Add WP2Static submenu
     *
     * @param array<mixed> $submenuPages array of submenu pages
     * @return array<mixed> array of submenu pages
     */
    public static function addSubmenuPage( array $submenuPages ): array
    {
        $submenuPages['cloudflare-workers'] =
            [ 'WP2StaticCloudflareWorkers\Controller', 'renderCloudflareWorkersPage' ];

        return $submenuPages;
    }

    public static function saveOptionsFromUI(): void
    {
        check_admin_referer('wp2static-cloudflare-workers-options');

        global $wpdb;

        $tableName = $wpdb->prefix . 'wp2static_addon_cloudflare_workers_options';

        $apiToken =
            $_POST['apiToken'] ?
            \WP2Static\CoreOptions::encrypt_decrypt(
                'encrypt',
                sanitize_text_field($_POST['apiToken'])
            ) : '';

        $wpdb->update(
            $tableName,
            [ 'value' => $apiToken ],
            [ 'name' => 'apiToken' ]
        );

        $wpdb->update(
            $tableName,
            [ 'value' => isset($_POST['useBulkUpload']) ? 1 : 0 ],
            [ 'name' => 'useBulkUpload' ]
        );

        $wpdb->update(
            $tableName,
            [ 'value' => sanitize_text_field($_POST['namespaceID']) ],
            [ 'name' => 'namespaceID' ]
        );

        $wpdb->update(
            $tableName,
            [ 'value' => sanitize_text_field($_POST['accountID']) ],
            [ 'name' => 'accountID' ]
        );

        $batchSize = sanitize_text_field($_POST['batchSize']);
        if (!$batchSize) {
            $batchSize = (string)Deployer::BATCH_SIZE_DEFAULT;
        }
        $batchSize = preg_replace('/\D/', '', $batchSize);
        $wpdb->update(
            $tableName,
            [ 'value' => $batchSize ],
            [ 'name' => 'batchSize' ]
        );

        wp_safe_redirect(admin_url('admin.php?page=wp2static-addon-cloudflare-workers'));
        exit;
    }

    /**
     * Get option value
     *
     * @return string option value
     */
    public static function getValue( string $name ): string
    {
        global $wpdb;

        $optionValue = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT value FROM {$wpdb->prefix}wp2static_addon_cloudflare_workers_options WHERE name = %s LIMIT 1",
                $name
            )
        );

        if (! is_string($optionValue)) {
            return '';
        }

        return $optionValue;
    }

    public function addOptionsPage(): void
    {
        add_submenu_page(
            '',
            'Cloudflare Workers Deployment Options',
            'Cloudflare Workers Deployment Options',
            'manage_options',
            'wp2static-addon-cloudflare-workers',
            [ $this, 'renderCloudflareWorkersPage' ]
        );
    }

    public static function wp2staticAdminScripts(): void
    {
        wp_register_script(
            'wp2static_addon_cloudflare_admin_scripts',
            plugins_url('../js/admin/batch-size-controller.js', __FILE__),
            [],
            Config::get('version'),
            false
        );
        wp_enqueue_script('wp2static_addon_cloudflare_admin_scripts');
    }
}
