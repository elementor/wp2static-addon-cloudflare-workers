<h2>Cloudflare Workers Deployment Options</h2>

<form
    name="wp2static-cloudflare-workers-save-options"
    method="POST"
    action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">

    <?php wp_nonce_field( $view['nonce_action'] ); ?>
    <input name="action" type="hidden" value="wp2static_cloudflare_workers_save_options" />

<table class="widefat striped">
    <tbody>

        <tr>
            <td style="width:50%;">
                <label
                    for="<?php echo $view['options']['accountID']->name; ?>"
                ><?php echo $view['options']['accountID']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['accountID']->name; ?>"
                    name="<?php echo $view['options']['accountID']->name; ?>"
                    type="text"
                    value="<?php echo $view['options']['accountID']->value !== '' ? $view['options']['accountID']->value : ''; ?>"
                />
            </td>
        </tr>

        <tr>
            <td style="width:50%;">
                <label
                    for="<?php echo $view['options']['namespaceID']->name; ?>"
                ><?php echo $view['options']['namespaceID']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['namespaceID']->name; ?>"
                    name="<?php echo $view['options']['namespaceID']->name; ?>"
                    type="text"
                    value="<?php echo $view['options']['namespaceID']->value !== '' ? $view['options']['namespaceID']->value : ''; ?>"
                />
            </td>
        </tr>

        <tr>
            <td style="width:50%;">
                <label
                    for="<?php echo $view['options']['apiToken']->name; ?>"
                ><?php echo $view['options']['apiToken']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['apiToken']->name; ?>"
                    name="<?php echo $view['options']['apiToken']->name; ?>"
                    type="password"
                    value="<?php echo $view['options']['apiToken']->value !== '' ?
                        \WP2Static\CoreOptions::encrypt_decrypt('decrypt', $view['options']['apiToken']->value) :
                        ''; ?>"
                />
            </td>
        </tr>

        <tr>
            <td style="width:50%;">
                <label
                    for="<?php echo $view['options']['useBulkUpload']->name; ?>"
                /><?php echo $view['options']['useBulkUpload']->label; ?></label>
            </td>
            <td>
                <input
                    type="checkbox"
                    id="<?php echo $view['options']['useBulkUpload']->name; ?>"
                    name="<?php echo $view['options']['useBulkUpload']->name; ?>"
                    value="1"
                    <?php echo (int) $view['options']['useBulkUpload']->value === 1 ? 'checked' : ''; ?>
                />
            </td>
        </tr>

    </tbody>
</table>

<br>

    <button class="button btn-primary">Save Cloudflare Workers Options</button>
</form>

