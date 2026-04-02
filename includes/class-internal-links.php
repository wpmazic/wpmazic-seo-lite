<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPMazic_Internal_Links {

    public function __construct() {
        add_action( 'save_post', array( $this, 'track_links_for_post' ), 20, 3 );
    }

    /**
     * Parse post content and store internal links.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post Post object.
     * @param bool    $update Update flag.
     */
    public function track_links_for_post( $post_id, $post, $update ) {
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        if ( ! $post instanceof WP_Post ) {
            return;
        }

        if ( 'publish' !== $post->post_status ) {
            return;
        }

        if ( ! post_type_supports( $post->post_type, 'editor' ) ) {
            return;
        }

        $content = (string) $post->post_content;
        if ( '' === trim( (string) $content ) ) {
            $this->clear_post_links( $post_id );
            return;
        }

        $links = $this->extract_links( $content );
        $this->store_links( $post_id, $links );
    }

    /**
     * Extract internal links and anchors from HTML.
     *
     * @param string $content HTML content.
     * @return array
     */
    private function extract_links( $content ) {
        $results = array();
        $home    = wp_parse_url( home_url(), PHP_URL_HOST );

        if ( empty( $home ) ) {
            return $results;
        }

        if ( ! preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER ) ) {
            return $results;
        }

        foreach ( $matches as $match ) {
            $href   = isset( $match[1] ) ? trim( (string) $match[1] ) : '';
            $anchor = isset( $match[2] ) ? wp_strip_all_tags( $match[2] ) : '';

            if ( '' === $href || '#' === $href || 0 === strpos( (string) $href, 'mailto:' ) || 0 === strpos( (string) $href, 'tel:' ) ) {
                continue;
            }

            if ( 0 === strpos( (string) $href, '/' ) ) {
                $href = home_url( $href );
            }

            $parsed = wp_parse_url( $href );
            if ( empty( $parsed['host'] ) || strtolower( (string) $parsed['host'] ) !== strtolower( (string) $home ) ) {
                continue;
            }

            $clean_url      = esc_url_raw( $href );
            $target_post_id = url_to_postid( $clean_url );

            $results[] = array(
                'url'            => $clean_url,
                'anchor_text'    => sanitize_text_field( $anchor ),
                'target_post_id' => $target_post_id ? (int) $target_post_id : 0,
            );
        }

        return $results;
    }

    /**
     * Replace stored links for a post.
     *
     * @param int   $post_id Post ID.
     * @param array $links Links list.
     */
    private function store_links( $post_id, $links ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wpmazic_links';
        $this->clear_post_links( $post_id );

        if ( empty( $links ) ) {
            return;
        }

        foreach ( $links as $link ) {
            $wpdb->insert(
                $table,
                array(
                    'post_id'        => (int) $post_id,
                    'target_post_id' => (int) $link['target_post_id'],
                    'anchor_text'    => substr( (string) $link['anchor_text'], 0, 500 ),
                    'url'            => substr( (string) $link['url'], 0, 500 ),
                    'type'           => 'internal',
                ),
                array( '%d', '%d', '%s', '%s', '%s' )
            );
        }
    }

    /**
     * Delete all stored links for a post.
     *
     * @param int $post_id Post ID.
     */
    private function clear_post_links( $post_id ) {
        global $wpdb;

        $wpdb->delete(
            $wpdb->prefix . 'wpmazic_links',
            array(
                'post_id' => (int) $post_id,
            ),
            array( '%d' )
        );
    }
}
