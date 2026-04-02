<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'wpmazic_404';

if ( isset( $_POST['wpmazic_delete_404_id'] ) && check_admin_referer( 'wpmazic_delete_404' ) ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        wpmazic_seo_lite_add_notice( 'error', __( 'Permission denied.', 'wpmazic-seo-lite' ) );
    } else {
        $delete_id = isset( $_POST['wpmazic_delete_404_id'] ) ? absint( wp_unslash( $_POST['wpmazic_delete_404_id'] ) ) : 0;
        if ( $delete_id ) {
            $wpdb->delete( $table, array( 'id' => $delete_id ), array( '%d' ) );
            wpmazic_seo_lite_add_notice( 'success', __( '404 entry deleted.', 'wpmazic-seo-lite' ) );
        }
    }
}

if ( isset( $_POST['wpmazic_clear_404'] ) && check_admin_referer( 'wpmazic_clear_404' ) ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        wpmazic_seo_lite_add_notice( 'error', __( 'Permission denied.', 'wpmazic-seo-lite' ) );
    } else {
        $wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        wpmazic_seo_lite_add_notice( 'success', __( '404 log cleared.', 'wpmazic-seo-lite' ) );
    }
}

$fetch_limit = 50;
$errors      = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$table} ORDER BY last_hit DESC LIMIT %d",
        $fetch_limit
    )
);
$total_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

wpmazic_seo_admin_shell_open(
    __( '404 Monitor', 'wpmazic-seo-lite' ),
    __( 'Track broken URLs, identify crawl waste, and quickly clean problematic links.', 'wpmazic-seo-lite' )
);
?>

<div class="wmz-card">
    <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-3">
        <h2 class="tw-mb-0"><?php esc_html_e( 'Tracked 404 URLs', 'wpmazic-seo-lite' ); ?></h2>
        <form method="post">
            <?php wp_nonce_field( 'wpmazic_clear_404' ); ?>
            <input type="hidden" name="wpmazic_clear_404" value="1">
            <button type="submit" class="button"><?php esc_html_e( 'Clear Log', 'wpmazic-seo-lite' ); ?></button>
        </form>
    </div>
    <p class="wmz-subtle tw-mt-3"><?php esc_html_e( 'Showing the latest 50 logged URLs. The full log remains stored until you clear it.', 'wpmazic-seo-lite' ); ?></p>
    <div class="wmz-table-wrap tw-mt-3">
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'URL', 'wpmazic-seo-lite' ); ?></th>
                    <th><?php esc_html_e( 'Referer', 'wpmazic-seo-lite' ); ?></th>
                    <th><?php esc_html_e( 'Hits', 'wpmazic-seo-lite' ); ?></th>
                    <th><?php esc_html_e( 'Last Hit', 'wpmazic-seo-lite' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'wpmazic-seo-lite' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $errors ) ) : ?>
                    <?php foreach ( $errors as $error ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( $error->url ); ?></code></td>
                            <td><?php echo esc_html( $error->referer ); ?></td>
                            <td><?php echo esc_html( (string) $error->hits ); ?></td>
                            <td><?php echo esc_html( (string) $error->last_hit ); ?></td>
                            <td>
                                <form method="post" class="tw-inline">
                                    <?php wp_nonce_field( 'wpmazic_delete_404' ); ?>
                                    <input type="hidden" name="wpmazic_delete_404_id" value="<?php echo esc_attr( $error->id ); ?>">
                                    <button type="submit" class="button button-small"><?php esc_html_e( 'Delete', 'wpmazic-seo-lite' ); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="5"><?php esc_html_e( 'No 404 errors recorded yet.', 'wpmazic-seo-lite' ); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php wpmazic_seo_admin_shell_close(); ?>
