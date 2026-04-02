<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPMazic_IndexNow {

    /**
     * @var string
     */
    private $key = '';

    public function __construct() {
        $settings  = wpmazic_seo_get_settings();
        $this->key = ! empty( $settings['indexnow_api_key'] ) ? preg_replace( '/[^a-zA-Z0-9]/', '', (string) $settings['indexnow_api_key'] ) : '';

        add_action( 'init', array( $this, 'register_rewrite' ) );
        add_action( 'template_redirect', array( $this, 'serve_key_file' ) );
        add_action( 'transition_post_status', array( $this, 'submit_on_publish' ), 10, 3 );
    }

    /**
     * Register query var for key endpoint.
     */
    public function register_rewrite() {
        add_rewrite_rule( '^indexnow-key/([A-Za-z0-9]+)\.txt$', 'index.php?wpmazic_indexnow_key=$matches[1]', 'top' );
        add_rewrite_tag( '%wpmazic_indexnow_key%', '([A-Za-z0-9]+)' );
    }

    /**
     * Respond with plaintext key.
     */
    public function serve_key_file() {
        $requested = get_query_var( 'wpmazic_indexnow_key' );
        if ( empty( $requested ) || empty( $this->key ) ) {
            return;
        }

        if ( $requested !== $this->key ) {
            return;
        }

        nocache_headers();
        header( 'Content-Type: text/plain; charset=utf-8' );
        echo esc_html( $this->key );
        exit;
    }

    /**
     * Submit newly published URLs to IndexNow.
     *
     * @param string  $new_status New status.
     * @param string  $old_status Old status.
     * @param WP_Post $post       Post object.
     */
    public function submit_on_publish( $new_status, $old_status, $post ) {
        if ( 'publish' !== $new_status ) {
            return;
        }
        if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
            return;
        }
        if ( 'auto-draft' === $post->post_status ) {
            return;
        }

        $url = get_permalink( $post );
        if ( empty( $url ) ) {
            return;
        }

        $this->submit_url( $url );
    }

    /**
     * Submit a single URL to IndexNow endpoint.
     *
     * @param string $url URL to submit.
     */
    public function submit_url( $url ) {
        if ( empty( $this->key ) ) {
            return;
        }

        $url = esc_url_raw( $url );
        if ( empty( $url ) ) {
            return;
        }

        $key_location = home_url( '/indexnow-key/' . $this->key . '.txt' );
        $payload      = array(
            'host'        => wp_parse_url( home_url(), PHP_URL_HOST ),
            'key'         => $this->key,
            'keyLocation' => $key_location,
            'urlList'     => array( $url ),
        );

        $response = wp_remote_post(
            'https://api.indexnow.org/indexnow',
            array(
                'timeout' => 8,
                'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
                'body'    => wp_json_encode( $payload ),
            )
        );

        $status   = 'error';
        $body     = '';
        $code     = 0;

        if ( is_wp_error( $response ) ) {
            $body = $response->get_error_message();
        } else {
            $code = (int) wp_remote_retrieve_response_code( $response );
            $body = (string) wp_remote_retrieve_body( $response );
            $status = ( $code >= 200 && $code < 300 ) ? 'success' : 'error';
        }

        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'wpmazic_indexnow',
            array(
                'url'      => $url,
                'status'   => $status,
                'response' => sprintf( 'HTTP %d: %s', $code, substr( sanitize_textarea_field( $body ), 0, 3000 ) ),
            ),
            array( '%s', '%s', '%s' )
        );
    }
}
