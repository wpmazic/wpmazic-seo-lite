<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPMazic_RSS_SEO {

    public function __construct() {
        add_filter( 'the_content_feed', array( $this, 'inject_rss_footer' ) );
        add_filter( 'the_excerpt_rss', array( $this, 'inject_rss_excerpt_footer' ) );
    }

    /**
     * Inject attribution content into RSS full content.
     *
     * @param string $content Feed content.
     * @return string
     */
    public function inject_rss_footer( $content ) {
        if ( ! is_feed() ) {
            return $content;
        }

        $settings = wpmazic_seo_get_settings();
        $before   = isset( $settings['rss_before_content'] ) ? trim( (string) $settings['rss_before_content'] ) : '';
        $after    = isset( $settings['rss_after_content'] ) ? trim( (string) $settings['rss_after_content'] ) : '';

        if ( '' === $before && '' === $after ) {
            return $content;
        }

        $post_id = get_the_ID();
        $parts   = array(
            '%post_title%' => get_the_title( $post_id ),
            '%post_link%'  => get_permalink( $post_id ),
            '%site_name%'  => get_bloginfo( 'name' ),
            '%site_link%'  => home_url( '/' ),
        );

        $before_rendered = $this->render_snippet( $before, $parts );
        $after_rendered  = $this->render_snippet( $after, $parts );

        return $before_rendered . $content . $after_rendered;
    }

    /**
     * Apply same logic for excerpt feeds.
     *
     * @param string $excerpt Excerpt.
     * @return string
     */
    public function inject_rss_excerpt_footer( $excerpt ) {
        return $this->inject_rss_footer( $excerpt );
    }

    /**
     * Replace placeholders and sanitize snippet HTML.
     *
     * @param string $snippet Snippet template.
     * @param array  $parts   Placeholder map.
     * @return string
     */
    private function render_snippet( $snippet, $parts ) {
        if ( '' === trim( (string) $snippet ) ) {
            return '';
        }

        $snippet = strtr( (string) $snippet, $parts );
        return wp_kses_post( $snippet );
    }
}
