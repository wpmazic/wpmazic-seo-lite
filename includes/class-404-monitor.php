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

        $url         = $this->get_current_request_path();
        $referer     = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
        $user_agent  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
        $ip_address  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

        $referer    = substr( $referer, 0, 500 );
        $user_agent = substr( $user_agent, 0, 500 );
        $ip_address = substr( $ip_address, 0, 45 );

        if ( '' === $url ) {
            return;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, hits FROM {$wpdb->prefix}wpmazic_404 WHERE url = %s LIMIT 1",
                $url
            )
        );

        if ( $row ) {
            $wpdb->update(
                $wpdb->prefix . 'wpmazic_404',
                array(
                    'hits'       => (int) $row->hits + 1,
                    'last_hit'   => current_time( 'mysql' ),
                    'referer'    => $referer,
                    'user_agent' => $user_agent,
                    'ip_address' => $ip_address,
                ),
                array(
                    'id' => (int) $row->id,
                ),
                array( '%d', '%s', '%s', '%s', '%s' ),
                array( '%d' )
            );
            return;
        }

        $wpdb->insert(
            $wpdb->prefix . 'wpmazic_404',
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
     * Normalize the current request URI into a stored path/query string.
     *
     * @return string
     */
    private function get_current_request_path() {
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        if ( '' === $request_uri ) {
            return '';
        }

        $request_url = esc_url_raw( home_url( $request_uri ) );
        if ( '' === $request_url ) {
            return '';
        }

        $path  = (string) wp_parse_url( $request_url, PHP_URL_PATH );
        $query = (string) wp_parse_url( $request_url, PHP_URL_QUERY );
        $url   = $path;

        if ( '' !== $query ) {
            $url .= '?' . $query;
        }

        return substr( $url, 0, 500 );
    }
}
