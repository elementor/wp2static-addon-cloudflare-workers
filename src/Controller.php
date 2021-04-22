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

/**
 * CloudflareWorkers Client Functions
 *
 */
class Controller
{
    public function run(): void
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

        do_action(
            'wp2static_register_addon',
            'wp2static-addon-cloudflare-workers',
            'deploy',
            'Cloudflare Workers Deployment',
            'https://wp2static.com/addons/cloudflare-workers/',
            'Deploys to Cloudflare Workers'
        );

        if (!defined('WP_CLI')) {
            return;
        }

        \WP_CLI::add_command(
            'wp2static cloudflare_workers',
            [ 'WP2StaticCloudflareWorkers\CLI', 'cloudflareWorkers' ]
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

        $tableName = $wpdb->prefix . 'wp2static_addon_cloudflare_workers_options';

        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %s', $tableName));

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

        $tableName = $wpdb->prefix . 'wp2static_addon_cloudflare_workers_options';

        $wpdb->query(
            $wpdb->prepare(
                'INSERT INTO %s (name, value, label, description) VALUES (%s, %s, %s, %s);',
                $tableName,
                'apiToken',
                '',
                'API Token',
                'see https://dash.cloudflare.com/profile/api-tokens'
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                'INSERT INTO %s (name, value, label, description) VALUES (%s, %s, %s, %s);',
                $tableName,
                'useBulkUpload',
                '1',
                'Bulk uploads',
                'Uploads files in batches'
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                'INSERT INTO %s (name, value, label, description) VALUES (%s, %s, %s, %s);',
                $tableName,
                'namespaceID',
                '',
                'Namespace ID',
                'ie 3d61660f7f564f689b24fbb1f252c033'
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                'INSERT INTO %s (name, value, label, description) VALUES (%s, %s, %s, %s);',
                $tableName,
                'accountID',
                '',
                'Account ID',
                'ie 13e736c51a7a73dabc0b83f75d3bedce'
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

        $tableName = $wpdb->prefix . 'wp2static_addon_cloudflare_workers_options';

        $wpdb->update(
            $tableName,
            [ 'value' => $value ],
            [ 'name' => $name ]
        );
    }

    public static function renderCloudflareWorkersPage(): void
    {
        $view = [];
        $view['nonce_action'] = 'wp2static-cloudflare-workers-options';
        $view['uploads_path'] = \WP2Static\SiteInfo::getPath('uploads');
        $cloudflareWorkersPath =
            \WP2Static\SiteInfo::getPath('uploads') . 'wp2static-processed-site.s3';

        $view['options'] = self::getOptions();

        $view['cloudflare_workers_url'] =
            is_file($cloudflareWorkersPath) ?
                \WP2Static\SiteInfo::getUrl('uploads') . 'wp2static-processed-site.s3' : '#';

        require_once __DIR__ . '/../views/cloudflare-workers-page.php';
    }

    public function deploy( string $processedSitePath ): void
    {
        \WP2Static\WsLog::l('Cloudflare Workers add-on deploying');

        $cloudflareWorkersDeployer = new Deployer();
        $cloudflareWorkersDeployer->upload_files($processedSitePath);
    }

    public static function activateForSingleSite(): void
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'wp2static_addon_cloudflare_workers_options';

        $charsetCollate = $wpdb->get_charsetCollate();

        $sql = "CREATE TABLE $tableName (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            value VARCHAR(255) NOT NULL,
            label VARCHAR(255) NULL,
            description VARCHAR(255) NULL,
            PRIMARY KEY  (id)
        ) $charsetCollate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        $options = self::getOptions();

        if (isset($options['namespaceID'])) {
            return;
        }

        self::seedOptions();
    }

    public static function deactivateForSingleSite(): void
    {
    }

    public static function deactivate( ?bool $networkWide = null ): void
    {
        if ($networkWide) {
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
        if ($networkWide) {
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
                'SELECT value FROM %s WHERE name = %s LIMIT 1',
                $wpdb->prefix . 'wp2static_addon_cloudflare_workers_options',
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
}
