<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'wpmazic_redirects';

$redirect_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

if ( isset( $_POST['wpmazic_add_redirect'] ) && check_admin_referer( 'wpmazic_add_redirect' ) ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        wpmazic_seo_lite_add_notice( 'error', __( 'Permission denied.', 'wpmazic-seo-lite' ) );
    } else {
        $source_raw = isset( $_POST['source'] ) ? wp_unslash( $_POST['source'] ) : '';
        $target     = isset( $_POST['target'] ) ? wp_unslash( $_POST['target'] ) : '';
        $type       = 301;
        $source     = '/' . ltrim( trim( sanitize_text_field( $source_raw ) ), '/' );

        $target = esc_url_raw( trim( (string) $target ) );

        if ( '' !== trim( (string) $source ) && '' !== $target ) {
            $exists = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE source = %s LIMIT 1",
                    $source
                )
            );

            if ( $exists ) {
                wpmazic_seo_lite_add_notice( 'warning', __( 'A redirect for this source URL already exists.', 'wpmazic-seo-lite' ) );
            } else {
                $wpdb->insert(
                    $table,
                    array(
                        'source' => substr( (string) $source, 0, 500 ),
                        'target' => substr( (string) $target, 0, 500 ),
                        'type'   => $type,
                        'status' => 'active',
                    ),
                    array( '%s', '%s', '%d', '%s' )
                );
                $redirect_count++;
                wpmazic_seo_lite_add_notice( 'success', __( 'Redirect added.', 'wpmazic-seo-lite' ) );
            }
        }
    }
}

if ( isset( $_POST['wpmazic_delete_redirect_id'] ) && check_admin_referer( 'wpmazic_delete_redirect' ) ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        wpmazic_seo_lite_add_notice( 'error', __( 'Permission denied.', 'wpmazic-seo-lite' ) );
    } else {
        $delete_id = isset( $_POST['wpmazic_delete_redirect_id'] ) ? absint( wp_unslash( $_POST['wpmazic_delete_redirect_id'] ) ) : 0;
        if ( $delete_id ) {
            $wpdb->delete( $table, array( 'id' => $delete_id ), array( '%d' ) );
            wpmazic_seo_lite_add_notice( 'success', __( 'Redirect deleted.', 'wpmazic-seo-lite' ) );
        }
    }
}

$fetch_limit = 300;
$redirects   = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d",
        $fetch_limit
    )
);

wpmazic_seo_admin_shell_open(
    __( 'Redirects', 'wpmazic-seo-lite' ),
    __( 'Manage 301 redirects to preserve link equity and avoid dead pages.', 'wpmazic-seo-lite' )
);
?>

<div class="wmz-card">
    <h2><?php esc_html_e( 'Add Redirect Rule', 'wpmazic-seo-lite' ); ?></h2>
    <form method="post">
        <?php wp_nonce_field( 'wpmazic_add_redirect' ); ?>
        <input type="hidden" name="wpmazic_add_redirect" value="1">
        <div class="wmz-form-grid">
            <div class="wmz-field">
                <label for="redirect_source"><?php esc_html_e( 'Source URL', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" id="redirect_source" type="text" name="source" placeholder="/old-page" required>
                <p class="wmz-help"><?php esc_html_e( 'Enter a relative path starting with /. Example: /old-page', 'wpmazic-seo-lite' ); ?></p>
            </div>
            <div class="wmz-field">
                <label for="redirect_target"><?php esc_html_e( 'Target URL', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" id="redirect_target" type="url" name="target" placeholder="https://example.com/new-page" required>
                <p class="wmz-help"><?php esc_html_e( 'Uses permanent 301 redirects for canonical URL changes and retired content.', 'wpmazic-seo-lite' ); ?></p>
            </div>
        </div>
        <div class="wmz-actions">
            <?php submit_button( __( 'Add Redirect', 'wpmazic-seo-lite' ), 'primary', 'submit', false ); ?>
        </div>
    </form>
</div>

<div class="wmz-card">
    <h2><?php esc_html_e( 'Active Redirect Rules', 'wpmazic-seo-lite' ); ?></h2>
    <div class="wmz-table-wrap">
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Source', 'wpmazic-seo-lite' ); ?></th>
                    <th><?php esc_html_e( 'Target', 'wpmazic-seo-lite' ); ?></th>
                    <th><?php esc_html_e( 'Type', 'wpmazic-seo-lite' ); ?></th>
                    <th><?php esc_html_e( 'Hits', 'wpmazic-seo-lite' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'wpmazic-seo-lite' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $redirects ) ) : ?>
                    <?php foreach ( $redirects as $redirect ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( $redirect->source ); ?></code></td>
                            <td><a href="<?php echo esc_url( $redirect->target ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $redirect->target ); ?></a></td>
                            <td><?php echo esc_html( (string) $redirect->type ); ?></td>
                            <td><?php echo esc_html( (string) $redirect->hits ); ?></td>
                            <td>
                                <form method="post" class="tw-inline">
                                    <?php wp_nonce_field( 'wpmazic_delete_redirect' ); ?>
                                    <input type="hidden" name="wpmazic_delete_redirect_id" value="<?php echo esc_attr( $redirect->id ); ?>">
                                    <button type="submit" class="button button-small"><?php esc_html_e( 'Delete', 'wpmazic-seo-lite' ); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="5"><?php esc_html_e( 'No redirects found.', 'wpmazic-seo-lite' ); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php wpmazic_seo_admin_shell_close(); ?>
