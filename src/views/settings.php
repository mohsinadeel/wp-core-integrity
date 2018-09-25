<div class="wrap" id="wp-core-integrity">
    <h3>WordPress Core Integrity: Settings </h3>
    <form method="post" action="options.php">
        <?php settings_fields('wp_core_integrity_core_group'); ?>
        <?php do_settings_fields('wp_core_integrity', 'wp_core_integrity_core_group'); ?>

        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="include_wp_content">Include "wp_include" to scan</label></th>
                <td><input name="include_wp_content" id="include_wp_content" type="checkbox" value="1"
                           class="code" <?php echo checked(1,
                        get_option('include_wp_content'), false) ?>" />
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="check_newly_added_files">Search for malicious files addition in the
                        core</label></th>
                <td><input name="check_newly_added_files" id="check_newly_added_files" type="checkbox" value="1"
                           class="code" <?php echo checked(1,
                        get_option('check_newly_added_files'), false) ?>" />
                </td>
            </tr>
        </table>

        <?php submit_button('Save Settings'); ?>
    </form>
</div>