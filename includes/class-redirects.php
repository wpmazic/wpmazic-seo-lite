<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPMazic_Redirects {

    public function __construct() {
        add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 1 );
        add_action( 'post_updated', array( $this, 'maybe_add_slug_change_redirect' ), 10, 3 );
    }

    /**
     * Handle per-post redirects and rules from redirects table.
     */
    public function maybe_redirect() {
        if ( is_admin() || wp_doing_ajax() ) {
            return;
        }

        if ( is_singular() ) {
            $target = get_post_meta( get_queried_object_id(), '_wpmazic_redirect', true );
            if ( ! empty( $target ) ) {
                $this->do_redirect( $target, 301 );
            }
        }

        global $wpdb;

        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_sanitize_redirect( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
        $path        = wp_parse_url( $request_uri, PHP_URL_PATH );
        $path = '/' . ltrim( (string) $path, '/' );
        $path = untrailingslashit( $path );
        if ( '' === $path ) {
            $path = '/';
        }

        $table    = wpmazic_seo_get_table_name( 'redirects' );
        $redirect = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = %s AND source = %s LIMIT 1",
                'active',
                $path
            )
        );

        if ( ! $redirect ) {
            // Fallback: regex rules stored as source prefixed with "regex:".
            $regex_rules = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE status = %s AND source LIKE %s ORDER BY id DESC LIMIT 200",
                    'active',
                    'regex:%'
                )
            );

            if ( ! empty( $regex_rules ) ) {
                foreach ( $regex_rules as $rule ) {
                    $raw_pattern = trim( substr( (string) $rule->source, 6 ) );
                    if ( '' === $raw_pattern ) {
                        continue;
                    }

                    $pattern = '#' . str_replace( '#', '\#', (string) $raw_pattern ) . '#u';
                    $matched = @preg_match( $pattern, (string) $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                    if ( 1 === (int) $matched ) {
                        $redirect = $rule;
                        break;
                    }
                }
            }
        }

        $redirect_type = isset( $redirect->type ) ? (int) $redirect->type : 301;
        $is_gone_410   = ( 410 === $redirect_type );

        if ( ! $redirect || ( ! $is_gone_410 && empty( $redirect->target ) ) ) {
            return;
        }

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET hits = hits + 1 WHERE id = %d",
                (int) $redirect->id
            )
        );

        $status_code = in_array( (int) $redirect->type, array( 301, 302, 307, 308, 410 ), true ) ? (int) $redirect->type : 301;

        if ( 410 === $status_code ) {
            status_header( 410 );
            nocache_headers();
            header( 'X-Robots-Tag: noindex, nofollow', true );
            echo esc_html__( 'Gone', 'wpmazic-seo-lite' );
            exit;
        }

        $this->do_redirect( $redirect->target, $status_code );
    }

    /**
     * Automatically add redirect entries when a published URL slug changes.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post_after Updated post object.
     * @param WP_Post $post_before Previous post object.
     */
    public function maybe_add_slug_change_redirect( $post_id, $post_after, $post_before ) {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        if ( ! $post_after instanceof WP_Post || ! $post_before instanceof WP_Post ) {
            return;
        }

        if ( 'publish' !== $post_after->post_status || 'publish' !== $post_before->post_status ) {
            return;
        }

        if ( (string) $post_after->post_name === (string) $post_before->post_name ) {
            return;
        }

        $settings = wpmazic_seo_get_settings();
        if ( isset( $settings['enable_auto_slug_redirect'] ) && empty( $settings['enable_auto_slug_redirect'] ) ) {
            return;
        }

        $old_url = get_permalink( $post_before );
        $new_url = get_permalink( $post_after );

        if ( empty( $old_url ) || empty( $new_url ) ) {
            return;
        }

        $old_path = wp_parse_url( $old_url, PHP_URL_PATH );
        $old_path = '/' . ltrim( (string) $old_path, '/' );
        $old_path = untrailingslashit( $old_path );
        if ( '' === $old_path ) {
            $old_path = '/';
        }

        $new_path = wp_parse_url( $new_url, PHP_URL_PATH );
        $new_path = '/' . ltrim( (string) $new_path, '/' );
        $new_path = untrailingslashit( $new_path );
        if ( '' === $new_path ) {
            $new_path = '/';
        }

        if ( $old_path === $new_path ) {
            return;
        }

        global $wpdb;
        $table = wpmazic_seo_get_table_name( 'redirects' );

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE source = %s AND status = %s LIMIT 1",
                $old_path,
                'active'
            )
        );

        if ( $exists ) {
            return;
        }

        $wpdb->insert(
            $table,
            array(
                'source' => substr( (string) $old_path, 0, 500 ),
                'target' => substr( esc_url_raw( $new_url ), 0, 500 ),
                'type'   => 301,
                'status' => 'active',
            ),
            array( '%s', '%s', '%d', '%s' )
        );
    }

    /**
     * Redirect helper with loop prevention.
     *
     * @param string $target Target URL.
     * @param int    $status Status code.
     */
    private function do_redirect( $target, $status ) {
        $target = (string) esc_url_raw( $target );
        if ( '' === $target ) {
            return;
        }

        if ( 0 === strpos( (string) $target, '/' ) ) {
            $target = home_url( $target );
        }

        $target_path = wp_parse_url( $target, PHP_URL_PATH );
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_sanitize_redirect( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
        $request     = wp_parse_url( $request_uri, PHP_URL_PATH );

        if ( $target_path && $request && untrailingslashit( $target_path ) === untrailingslashit( $request ) ) {
            return;
        }

        wp_safe_redirect( $target, $status, 'WPMazic SEO' );
        exit;
    }
}
