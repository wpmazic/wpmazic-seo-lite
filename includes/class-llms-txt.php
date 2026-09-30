<?php
/**
 * llms.txt — serves an LLM-friendly content index endpoint.
 *
 * @package WPMazic_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers and renders the /llms.txt content index endpoint.
 */
class WPMazic_LLMS_Txt {

    public function __construct() {
        add_action( 'template_redirect', array( $this, 'serve_llms_txt' ) );
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
        echo esc_html( wp_strip_all_tags( $output ) );
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
