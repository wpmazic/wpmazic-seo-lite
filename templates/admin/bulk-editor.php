<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( isset( $_POST['wpmazic_bulk_save'] ) && check_admin_referer( 'wpmazic_bulk_save' ) ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        wpmazic_seo_lite_add_notice( 'error', __( 'Permission denied.', 'wpmazic-seo-lite' ) );
    } else {
    // SECURITY: Validate and sanitize input
    $items_raw = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? $_POST['items'] : array();
    $items = array();
    
    if ( is_array( $items_raw ) ) {
        // Sanitize each item in the array
        foreach ( $items_raw as $post_id => $item ) {
            $post_id = absint( $post_id );
            if ( $post_id && is_array( $item ) ) {
                $items[ $post_id ] = array(
                    'title'       => isset( $item['title'] ) ? sanitize_text_field( wp_unslash( $item['title'] ) ) : '',
                    'description' => isset( $item['description'] ) ? sanitize_textarea_field( wp_unslash( $item['description'] ) ) : '',
                    'keyword'     => isset( $item['keyword'] ) ? sanitize_text_field( wp_unslash( $item['keyword'] ) ) : '',
                );
            }
        }
    }
    
    $saved = 0;

    foreach ( $items as $post_id => $item ) {
        $post_id = absint( $post_id );
        if ( ! $post_id || ! get_post( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
            continue;
        }

        $title       = isset( $item['title'] ) ? (string) $item['title'] : '';
        $description = isset( $item['description'] ) ? (string) $item['description'] : '';
        $keyword     = isset( $item['keyword'] ) ? (string) $item['keyword'] : '';

        update_post_meta( $post_id, '_wpmazic_title', $title );
        update_post_meta( $post_id, '_wpmazic_description', $description );
        update_post_meta( $post_id, '_wpmazic_keyword', $keyword );
        $saved++;
    }

    wpmazic_seo_lite_add_notice( 'success', sprintf( __( '%d posts updated.', 'wpmazic-seo-lite' ), (int) $saved ) );
    }
}

$post_types = get_post_types( array( 'public' => true ), 'names' );
$posts      = get_posts(
    array(
        'numberposts' => 50,
        'post_type'   => array_values( $post_types ),
        'post_status' => 'publish',
    )
);

wpmazic_seo_admin_shell_open(
    __( 'Bulk Editor', 'wpmazic-seo-lite' ),
    __( 'Quickly edit SEO titles, descriptions, and focus keywords for multiple posts.', 'wpmazic-seo-lite' )
);
?>

<div class="wmz-card">
    <h2><?php esc_html_e( 'Bulk SEO Editor', 'wpmazic-seo-lite' ); ?></h2>
    <form method="post">
        <?php wp_nonce_field( 'wpmazic_bulk_save' ); ?>
        <input type="hidden" name="wpmazic_bulk_save" value="1">
        <div class="wmz-table-wrap">
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th class="wmz-col-post"><?php esc_html_e( 'Post', 'wpmazic-seo-lite' ); ?></th>
                        <th class="wmz-col-title"><?php esc_html_e( 'SEO Title', 'wpmazic-seo-lite' ); ?></th>
                        <th class="wmz-col-description"><?php esc_html_e( 'Meta Description', 'wpmazic-seo-lite' ); ?></th>
                        <th class="wmz-col-keyword"><?php esc_html_e( 'Focus Keyword', 'wpmazic-seo-lite' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $posts as $post ) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( $post->post_title ); ?></a>
                            </td>
                            <td>
                                <input type="text" class="wmz-input" name="items[<?php echo esc_attr( $post->ID ); ?>][title]" value="<?php echo esc_attr( get_post_meta( $post->ID, '_wpmazic_title', true ) ); ?>">
                            </td>
                            <td>
                                <textarea class="wmz-textarea" rows="2" name="items[<?php echo esc_attr( $post->ID ); ?>][description]"><?php echo esc_textarea( get_post_meta( $post->ID, '_wpmazic_description', true ) ); ?></textarea>
                            </td>
                            <td>
                                <input type="text" class="wmz-input" name="items[<?php echo esc_attr( $post->ID ); ?>][keyword]" value="<?php echo esc_attr( get_post_meta( $post->ID, '_wpmazic_keyword', true ) ); ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="wmz-actions">
            <?php submit_button( __( 'Save All Changes', 'wpmazic-seo-lite' ), 'primary', 'submit', false ); ?>
        </div>
    </form>
</div>

<?php wpmazic_seo_admin_shell_close(); ?>
