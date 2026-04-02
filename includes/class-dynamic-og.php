<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPMazic_Dynamic_OG {

    public function __construct() {
        add_action( 'init', array( $this, 'add_rewrite' ) );
        add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'handle_request' ) );
    }

    /**
     * Register rewrite for dynamic SVG endpoint.
     */
    public function add_rewrite() {
        add_rewrite_rule( '^wpmazic-og/([0-9]+)\.svg$', 'index.php?wpmazic_dynamic_og=1&wpmazic_dynamic_og_post=$matches[1]', 'top' );
        add_rewrite_tag( '%wpmazic_dynamic_og%', '([0-1])' );
        add_rewrite_tag( '%wpmazic_dynamic_og_post%', '([0-9]+)' );
    }

    /**
     * Register query vars.
     *
     * @param array $vars Existing vars.
     * @return array
     */
    public function register_query_vars( $vars ) {
        $vars[] = 'wpmazic_dynamic_og';
        $vars[] = 'wpmazic_dynamic_og_post';
        return $vars;
    }

    /**
     * Serve dynamic OG SVG image.
     */
    public function handle_request() {
        if ( ! get_query_var( 'wpmazic_dynamic_og' ) ) {
            return;
        }

        $settings = wpmazic_seo_get_settings();
        if ( isset( $settings['enable_dynamic_og_image'] ) && empty( $settings['enable_dynamic_og_image'] ) ) {
            status_header( 404 );
            exit;
        }

        $post_id = absint( get_query_var( 'wpmazic_dynamic_og_post' ) );
        if ( $post_id <= 0 ) {
            status_header( 404 );
            exit;
        }

        $post = get_post( $post_id );
        if ( ! $post || 'publish' !== $post->post_status ) {
            status_header( 404 );
            exit;
        }

        $title = get_post_meta( $post_id, '_wpmazic_title', true );
        if ( '' === trim( (string) $title ) ) {
            $title = get_the_title( $post_id );
        }
        if ( '' === trim( (string) $title ) ) {
            $title = get_bloginfo( 'name' );
        }

        $title = wp_strip_all_tags( (string) $title );
        $title = $this->truncate_text( $title, 110 );

        $site_name = wp_strip_all_tags( (string) get_bloginfo( 'name' ) );
        $subtitle  = $this->truncate_text( $site_name, 45 );

        nocache_headers();
        header( 'Content-Type: image/svg+xml; charset=utf-8' );

        $svg  = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630" role="img" aria-label="' . esc_attr__( 'WPMazic dynamic social image', 'wpmazic-seo-lite' ) . '">';
        $svg .= '<defs><linearGradient id="wmzGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#0f172a"/><stop offset="100%" stop-color="#0ea5e9"/></linearGradient></defs>';
        $svg .= '<rect width="1200" height="630" fill="url(#wmzGrad)"/>';
        $svg .= '<rect x="40" y="40" width="1120" height="550" rx="22" fill="rgba(255,255,255,0.08)"/>';
        $svg .= '<text x="88" y="190" fill="#bae6fd" font-family="Segoe UI, Arial, sans-serif" font-size="28" font-weight="700">WPMazic SEO</text>';
        $svg .= '<foreignObject x="88" y="220" width="1024" height="270">';
        $svg .= '<div xmlns="http://www.w3.org/1999/xhtml" style="color:#ffffff;font-family:Segoe UI,Arial,sans-serif;font-size:62px;font-weight:700;line-height:1.12;">' . esc_html( $title ) . '</div>';
        $svg .= '</foreignObject>';
        $svg .= '<text x="88" y="548" fill="#e2e8f0" font-family="Segoe UI, Arial, sans-serif" font-size="26">' . esc_html( $subtitle ) . '</text>';
        $svg .= '</svg>';

        echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    /**
     * Build image URL for a post.
     *
     * @param int $post_id Post ID.
     * @return string
     */
    public static function get_image_url( $post_id ) {
        $post_id = absint( $post_id );
        if ( $post_id <= 0 ) {
            return '';
        }
        return home_url( '/wpmazic-og/' . $post_id . '.svg' );
    }

    /**
     * Trim long text.
     *
     * @param string $text Text.
     * @param int    $max_len Max.
     * @return string
     */
    private function truncate_text( $text, $max_len ) {
        $text = trim( (string) $text );
        if ( '' === $text ) {
            return '';
        }

        if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
            if ( mb_strlen( $text ) > $max_len ) {
                return mb_substr( (string) $text, 0, $max_len - 1 ) . '…';
            }
            return $text;
        }

        if ( strlen( $text ) > $max_len ) {
            return substr( (string) $text, 0, $max_len - 1 ) . '...';
        }
        return $text;
    }
}

