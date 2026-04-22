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
        $referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
        $referer = substr( $referer, 0, 500 );

        if ( '' === $url ) {
            return;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT id, hits FROM ' . wpmazic_seo_get_table_name( '404' ) . ' WHERE url = %s LIMIT 1',
                $url
            )
        );

        if ( $row ) {
            $wpdb->update(
                wpmazic_seo_get_table_name( '404' ),
                array(
                    'hits'       => (int) $row->hits + 1,
                    'last_hit'   => current_time( 'mysql' ),
                    'referer'    => $referer,
                ),
                array(
                    'id' => (int) $row->id,
                ),
                array( '%d', '%s', '%s' ),
                array( '%d' )
            );
            return;
        }

        $wpdb->insert(
            wpmazic_seo_get_table_name( '404' ),
            array(
                'url'        => $url,
                'referer'    => $referer,
                'hits'       => 1,
                'last_hit'   => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%d', '%s' )
        );

    }

    /**
     * Normalize the current request URI into a stored path/query string.
     *
     * @return string
     */
    private function get_current_request_path() {
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_sanitize_redirect( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
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
