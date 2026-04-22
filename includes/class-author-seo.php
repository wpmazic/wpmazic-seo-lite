<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPMazic_Author_SEO {

    public function __construct() {
        add_action( 'show_user_profile', array( $this, 'render_profile_fields' ) );
        add_action( 'edit_user_profile', array( $this, 'render_profile_fields' ) );
        add_action( 'personal_options_update', array( $this, 'save_profile_fields' ) );
        add_action( 'edit_user_profile_update', array( $this, 'save_profile_fields' ) );
    }

    /**
     * Render custom author SEO fields on user profile page.
     *
     * @param WP_User $user User object.
     */
    public function render_profile_fields( $user ) {
        if ( ! $user instanceof WP_User ) {
            return;
        }
        ?>
        <h2><?php esc_html_e( 'WPMazic Author SEO', 'wpmazic-seo-lite' ); ?></h2>
        <?php wp_nonce_field( 'wpmazic_author_seo_profile', 'wpmazic_author_seo_nonce' ); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="wpmazic_author_job_title"><?php esc_html_e( 'Job Title', 'wpmazic-seo-lite' ); ?></label></th>
                <td>
                    <input type="text" class="regular-text" name="wpmazic_author_job_title" id="wpmazic_author_job_title" value="<?php echo esc_attr( get_user_meta( $user->ID, 'wpmazic_author_job_title', true ) ); ?>">
                </td>
            </tr>
            <tr>
                <th><label for="wpmazic_author_expertise"><?php esc_html_e( 'Expertise', 'wpmazic-seo-lite' ); ?></label></th>
                <td>
                    <input type="text" class="regular-text" name="wpmazic_author_expertise" id="wpmazic_author_expertise" value="<?php echo esc_attr( get_user_meta( $user->ID, 'wpmazic_author_expertise', true ) ); ?>">
                    <p class="description"><?php esc_html_e( 'Example: Technical SEO, E-commerce SEO, Local SEO', 'wpmazic-seo-lite' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="wpmazic_author_sameas"><?php esc_html_e( 'Author Profile URLs', 'wpmazic-seo-lite' ); ?></label></th>
                <td>
                    <textarea class="large-text" rows="4" name="wpmazic_author_sameas" id="wpmazic_author_sameas"><?php echo esc_textarea( get_user_meta( $user->ID, 'wpmazic_author_sameas', true ) ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'One URL per line. Used for schema sameAs links (LinkedIn, X, etc).', 'wpmazic-seo-lite' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save author SEO fields.
     *
     * @param int $user_id User ID.
     */
    public function save_profile_fields( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }

        $nonce = isset( $_POST['wpmazic_author_seo_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wpmazic_author_seo_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'wpmazic_author_seo_profile' ) ) {
            return;
        }

        $job_title = isset( $_POST['wpmazic_author_job_title'] ) ? sanitize_text_field( wp_unslash( $_POST['wpmazic_author_job_title'] ) ) : '';
        $expertise = isset( $_POST['wpmazic_author_expertise'] ) ? sanitize_text_field( wp_unslash( $_POST['wpmazic_author_expertise'] ) ) : '';
        $sameas    = '';

        if ( isset( $_POST['wpmazic_author_sameas'] ) ) {
            $sameas_lines = preg_split( '/\r\n|\r|\n/', (string) wp_unslash( $_POST['wpmazic_author_sameas'] ) );
            if ( is_array( $sameas_lines ) ) {
                $clean_sameas = array();
                foreach ( $sameas_lines as $line ) {
                    $line = esc_url_raw( trim( (string) $line ) );
                    if ( '' !== $line ) {
                        $clean_sameas[] = $line;
                    }
                }

                if ( ! empty( $clean_sameas ) ) {
                    $sameas = implode( "\n", array_values( array_unique( $clean_sameas ) ) );
                }
            }
        }

        update_user_meta( $user_id, 'wpmazic_author_job_title', $job_title );
        update_user_meta( $user_id, 'wpmazic_author_expertise', $expertise );
        update_user_meta( $user_id, 'wpmazic_author_sameas', $sameas );
    }
}
