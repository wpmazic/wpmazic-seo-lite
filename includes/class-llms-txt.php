<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPMazic_LLMS_Txt {

    public function __construct() {
        add_action( 'init', array( $this, 'register_rewrite' ) );
        add_action( 'template_redirect', array( $this, 'serve_llms_txt' ) );
    }

    /**
     * Register rewrite rule and query var.
     */
    public function register_rewrite() {
        add_rewrite_rule( '^llms\.txt$', 'index.php?wpmazic_llms_txt=1', 'top' );
        add_rewrite_tag( '%wpmazic_llms_txt%', '([0-1])' );
    }

    /**
     * Output llms.txt response.
     */
    public function serve_llms_txt() {
        if ( ! get_query_var( 'wpmazic_llms_txt' ) ) {
            return;
        }

        $settings = wpmazic_seo_get_settings();
        if ( isset( $settings['enable_llms_txt'] ) && ! (int) $settings['enable_llms_txt'] ) {
            status_header( 404 );
            exit;
        }

        $default = $this->build_default_content();
        $saved   = get_option( 'wpmazic_llms_txt', '' );
        $output  = '' !== trim( (string) $saved ) ? (string) $saved : $default;

        nocache_headers();
        header( 'Content-Type: text/plain; charset=utf-8' );
        echo wp_strip_all_tags( $output );
        exit;
    }

    /**
     * Build basic llms.txt template.
     *
     * @return string
     */
    private function build_default_content() {
        $lines = array(
            '# ' . get_bloginfo( 'name' ),
            '# AI access guidance for this website',
            '',
            'Site: ' . home_url( '/' ),
            'Sitemap: ' . home_url( '/sitemap.xml' ),
            'Contact: ' . home_url( '/contact' ),
            '',
            'Preferred Attribution: Please cite source URLs when referencing this content.',
        );

        return implode( "\n", $lines );
    }
}
