<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPMazic_Search_Ping {

    /**
     * Option name used for latest ping summary.
     */
    const LAST_PING_OPTION = 'wpmazic_last_search_ping';

    public function __construct() {
        add_action( 'transition_post_status', array( $this, 'ping_on_publish_or_update' ), 20, 3 );
    }

    /**
     * Ping engines when a public URL is published/updated.
     *
     * @param string  $new_status New status.
     * @param string  $old_status Old status.
     * @param WP_Post $post       Post object.
     */
    public function ping_on_publish_or_update( $new_status, $old_status, $post ) {
        if ( ! $post instanceof WP_Post ) {
            return;
        }

        if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
            return;
        }

        if ( 'publish' !== $new_status ) {
            return;
        }

        if ( ! in_array( $post->post_type, get_post_types( array( 'public' => true ), 'names' ), true ) ) {
            return;
        }

        $settings = wpmazic_seo_get_settings();
        if ( isset( $settings['enable_auto_search_ping'] ) && empty( $settings['enable_auto_search_ping'] ) ) {
            return;
        }

        $sitemap_url = home_url( '/sitemap.xml' );
        $endpoints   = array(
            'bing'   => 'https://www.bing.com/ping?sitemap=' . rawurlencode( $sitemap_url ),
            'google' => 'https://www.google.com/ping?sitemap=' . rawurlencode( $sitemap_url ),
        );

        $summary = array(
            'date'      => gmdate( 'c' ),
            'post_id'   => (int) $post->ID,
            'post_type' => (string) $post->post_type,
            'post_url'  => get_permalink( $post ),
            'results'   => array(),
        );

        foreach ( $endpoints as $engine => $endpoint ) {
            $result = $this->ping_endpoint( $endpoint );
            $summary['results'][ $engine ] = $result;
        }

        update_option( self::LAST_PING_OPTION, $summary, false );
    }

    /**
     * Perform an HTTP GET ping.
     *
     * @param string $endpoint URL.
     * @return array
     */
    private function ping_endpoint( $endpoint ) {
        $response = wp_remote_get(
            $endpoint,
            array(
                'timeout' => 8,
            )
        );

        if ( is_wp_error( $response ) ) {
            return array(
                'ok'      => false,
                'code'    => 0,
                'message' => $response->get_error_message(),
            );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        return array(
            'ok'      => $code >= 200 && $code < 300,
            'code'    => $code,
            'message' => '',
        );
    }
}

