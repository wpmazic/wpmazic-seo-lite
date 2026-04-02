<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPMazic_Monitor_404 {

    public function __construct() {
        add_action( 'template_redirect', array( $this, 'log_404' ), 999 );
    }

    /**
     * Log 404 requests in plugin table.
     */
    public function log_404() {
        if ( ! is_404() || is_admin() || wp_doing_ajax() ) {
            return;
        }

        global $wpdb;

        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        $referer     = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
        $user_agent  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
        $ip_address  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

        $url        = substr( $request_uri, 0, 500 );
        $referer    = substr( $referer, 0, 500 );
        $user_agent = substr( $user_agent, 0, 500 );
        $ip_address = substr( $ip_address, 0, 45 );

        if ( '' === $url ) {
            return;
        }

        $table = $wpdb->prefix . 'wpmazic_404';
        $row   = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE url = %s LIMIT 1",
                $url
            )
        );

        if ( $row ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table}
                     SET hits = hits + 1, last_hit = %s, referer = %s, user_agent = %s, ip_address = %s
                     WHERE id = %d",
                    current_time( 'mysql' ),
                    $referer,
                    $user_agent,
                    $ip_address,
                    (int) $row->id
                )
            );
            $this->enforce_lite_limit( $table );
            return;
        }

        $wpdb->insert(
            $table,
            array(
                'url'        => $url,
                'referer'    => $referer,
                'user_agent' => $user_agent,
                'ip_address' => $ip_address,
                'hits'       => 1,
                'last_hit'   => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%d', '%s' )
        );

    }

    /**
     * Legacy compatibility stub.
     *
     * @param string $table Table name.
     */
    private function enforce_lite_limit( $table ) {
        unset( $table );
    }
}
