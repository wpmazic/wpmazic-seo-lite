<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPMazic_Breadcrumbs {

    public function __construct() {
        add_shortcode( 'wpmazic_breadcrumbs', array( $this, 'render_shortcode' ) );
        add_filter( 'wpmazic_breadcrumb_items', array( $this, 'get_items' ) );
    }

    /**
     * Render shortcode output.
     *
     * @return string
     */
    public function render_shortcode() {
        $items = $this->get_items( array() );
        if ( empty( $items ) ) {
            return '';
        }

        $settings  = wpmazic_seo_get_settings();
        $separator = ! empty( $settings['breadcrumb_separator'] ) ? $settings['breadcrumb_separator'] : '/';
        $parts     = array();

        foreach ( $items as $index => $item ) {
            if ( count( $items ) - 1 === $index ) {
                $parts[] = '<span class="wpmazic-breadcrumb-current" aria-current="page">' . esc_html( $item['label'] ) . '</span>';
                continue;
            }
            $parts[] = '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>';
        }

        return '<nav class="wpmazic-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'wpmazic-seo-lite' ) . '">' . implode( ' <span class="sep">' . esc_html( $separator ) . '</span> ', $parts ) . '</nav>';
    }

    /**
     * Build breadcrumb items.
     *
     * @param array $items Existing items.
     * @return array
     */
    public function get_items( $items ) {
        $settings  = wpmazic_seo_get_settings();
        $home_text = ! empty( $settings['breadcrumb_home_text'] ) ? $settings['breadcrumb_home_text'] : __( 'Home', 'wpmazic-seo-lite' );

        $crumbs   = array();
        $crumbs[] = array(
            'label' => $home_text,
            'url'   => home_url( '/' ),
        );

        if ( is_front_page() ) {
            return $crumbs;
        }

        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            $post    = get_post( $post_id );

            if ( ! $post ) {
                return $crumbs;
            }

            if ( 'post' === $post->post_type ) {
                $cats = get_the_category( $post_id );
                if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) {
                    $primary = $cats[0];
                    $crumbs[] = array(
                        'label' => $primary->name,
                        'url'   => get_category_link( $primary->term_id ),
                    );
                }
            } elseif ( 'page' !== $post->post_type ) {
                $archive = get_post_type_archive_link( $post->post_type );
                if ( $archive ) {
                    $obj      = get_post_type_object( $post->post_type );
                    $crumbs[] = array(
                        'label' => $obj ? $obj->labels->name : ucfirst( $post->post_type ),
                        'url'   => $archive,
                    );
                }
            }

            $ancestors = get_post_ancestors( $post_id );
            if ( ! empty( $ancestors ) ) {
                $ancestors = array_reverse( $ancestors );
                foreach ( $ancestors as $ancestor ) {
                    $crumbs[] = array(
                        'label' => get_the_title( $ancestor ),
                        'url'   => get_permalink( $ancestor ),
                    );
                }
            }

            $label      = get_post_meta( $post_id, '_wpmazic_breadcrumb_title', true );
            $crumbs[] = array(
                'label' => ! empty( $label ) ? $label : get_the_title( $post_id ),
                'url'   => get_permalink( $post_id ),
            );

            return $crumbs;
        }

        if ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();
            if ( $term && ! is_wp_error( $term ) ) {
                $crumbs[] = array(
                    'label' => $term->name,
                    'url'   => get_term_link( $term ),
                );
            }
            return $crumbs;
        }

        if ( is_search() ) {
            $crumbs[] = array(
                'label' => sprintf( __( 'Search: %s', 'wpmazic-seo-lite' ), get_search_query() ),
                'url'   => get_search_link(),
            );
            return $crumbs;
        }

        if ( is_author() ) {
            $author_id = get_queried_object_id();
            $crumbs[]  = array(
                'label' => get_the_author_meta( 'display_name', $author_id ),
                'url'   => get_author_posts_url( $author_id ),
            );
            return $crumbs;
        }

        if ( is_404() ) {
            $crumbs[] = array(
                'label' => __( '404 Not Found', 'wpmazic-seo-lite' ),
                'url'   => '',
            );
        }

        return $crumbs;
    }
}

if ( ! function_exists( 'wpmazic_breadcrumbs' ) ) {
    /**
     * Echo breadcrumb HTML.
     */
    function wpmazic_breadcrumbs() {
        echo do_shortcode( '[wpmazic_breadcrumbs]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
