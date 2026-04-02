<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPMazic_Image_Sitemap {

    public function __construct() {
        add_action( 'init', array( $this, 'add_rewrite' ) );
        add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'handle_request' ) );
    }

    /**
     * Register rewrite endpoint.
     */
    public function add_rewrite() {
        add_rewrite_rule( '^image-sitemap\.xml$', 'index.php?wpmazic_image_sitemap=1', 'top' );
        add_rewrite_tag( '%wpmazic_image_sitemap%', '([0-1])' );
    }

    /**
     * Register query vars.
     *
     * @param array $vars Existing query vars.
     * @return array
     */
    public function register_query_vars( $vars ) {
        $vars[] = 'wpmazic_image_sitemap';
        return $vars;
    }

    /**
     * Render image sitemap XML.
     */
    public function handle_request() {
        if ( ! get_query_var( 'wpmazic_image_sitemap' ) ) {
            return;
        }

        $settings = wpmazic_seo_get_settings();
        $enabled  = isset( $settings['enable_image_sitemap'] ) ? (int) $settings['enable_image_sitemap'] : 1;
        if ( ! $enabled ) {
            status_header( 404 );
            exit;
        }

        $image_ids = get_posts(
            array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'post_mime_type' => 'image',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        );

        nocache_headers();
        header( 'Content-Type: application/xml; charset=utf-8' );

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        foreach ( $image_ids as $image_id ) {
            $image_url = wp_get_attachment_url( $image_id );
            if ( empty( $image_url ) ) {
                continue;
            }

            $parent_id = (int) wp_get_post_parent_id( $image_id );
            $loc       = $parent_id > 0 ? get_permalink( $parent_id ) : home_url( '/' );
            if ( empty( $loc ) ) {
                $loc = home_url( '/' );
            }

            $title = get_the_title( $image_id );
            if ( '' === trim( (string) $title ) ) {
                $title = __( 'Image', 'wpmazic-seo-lite' );
            }

            $caption = wp_get_attachment_caption( $image_id );

            echo '  <url>' . "\n";
            echo '    <loc>' . esc_url( $loc ) . '</loc>' . "\n";
            echo '    <image:image>' . "\n";
            echo '      <image:loc>' . esc_url( $image_url ) . '</image:loc>' . "\n";
            echo '      <image:title>' . esc_html( wp_strip_all_tags( (string) $title ) ) . '</image:title>' . "\n";
            if ( '' !== trim( (string) $caption ) ) {
                echo '      <image:caption>' . esc_html( wp_strip_all_tags( (string) $caption ) ) . '</image:caption>' . "\n";
            }
            echo '    </image:image>' . "\n";
            echo '  </url>' . "\n";
        }

        echo '</urlset>';
        exit;
    }
}
