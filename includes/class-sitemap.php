<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPMazic_Sitemap {

    public function __construct() {
        add_action( 'init', array( $this, 'add_rewrite' ) );
        add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'handle_request' ) );
    }

    public function add_rewrite() {
        add_rewrite_rule( '^sitemap\.xml$', 'index.php?wpmazic_sitemap=1', 'top' );
        add_rewrite_tag( '%wpmazic_sitemap%', '([^&]+)' );
    }

    /**
     * Register custom query vars.
     *
     * @param array $vars Existing vars.
     * @return array
     */
    public function register_query_vars( $vars ) {
        $vars[] = 'wpmazic_sitemap';
        return $vars;
    }

    /**
     * Return sitemap post types after include/exclude and noindex rules.
     *
     * @param array $settings Plugin settings.
     * @return array
     */
    private function get_sitemap_post_types( $settings ) {
        $post_types = array_values(
            get_post_types(
                array(
                    'public' => true,
                ),
                'names'
            )
        );

        $filter_enabled = ! empty( $settings['sitemap_post_types_filter_enabled'] );
        $mode           = isset( $settings['sitemap_post_types_filter_mode'] ) && 'include' === sanitize_key( (string) $settings['sitemap_post_types_filter_mode'] ) ? 'include' : 'exclude';
        $selected       = $this->normalize_slug_list(
            isset( $settings['sitemap_post_types_selected'] ) ? $settings['sitemap_post_types_selected'] : array(),
            $post_types
        );

        if ( $filter_enabled ) {
            if ( 'include' === $mode ) {
                $post_types = ! empty( $selected )
                    ? array_values( array_intersect( $post_types, $selected ) )
                    : array();
            } elseif ( ! empty( $selected ) ) {
                $post_types = array_values( array_diff( $post_types, $selected ) );
            }
        } else {
            // Backward compatibility with previous include/exclude fields.
            $include = $this->normalize_slug_list(
                isset( $settings['sitemap_post_types_include'] ) ? $settings['sitemap_post_types_include'] : array(),
                $post_types
            );
            $exclude = $this->normalize_slug_list(
                isset( $settings['sitemap_post_types_exclude'] ) ? $settings['sitemap_post_types_exclude'] : array(),
                $post_types
            );

            if ( ! empty( $include ) ) {
                $post_types = array_values( array_intersect( $post_types, $include ) );
            }

            if ( ! empty( $exclude ) ) {
                $post_types = array_values( array_diff( $post_types, $exclude ) );
            }
        }

        if ( ! empty( $settings['noindex_attachments'] ) ) {
            $post_types = array_values( array_diff( $post_types, array( 'attachment' ) ) );
        }

        return array_values( array_unique( $post_types ) );
    }

    /**
     * Return sitemap taxonomies after include/exclude and noindex rules.
     *
     * @param array $settings Plugin settings.
     * @return array
     */
    private function get_sitemap_taxonomies( $settings ) {
        $taxonomies = array_values(
            get_taxonomies(
                array(
                    'public' => true,
                ),
                'names'
            )
        );

        $filter_enabled = ! empty( $settings['sitemap_taxonomies_filter_enabled'] );
        $mode           = isset( $settings['sitemap_taxonomies_filter_mode'] ) && 'include' === sanitize_key( (string) $settings['sitemap_taxonomies_filter_mode'] ) ? 'include' : 'exclude';
        $selected       = $this->normalize_slug_list(
            isset( $settings['sitemap_taxonomies_selected'] ) ? $settings['sitemap_taxonomies_selected'] : array(),
            $taxonomies
        );

        if ( $filter_enabled ) {
            if ( 'include' === $mode ) {
                $taxonomies = ! empty( $selected )
                    ? array_values( array_intersect( $taxonomies, $selected ) )
                    : array();
            } elseif ( ! empty( $selected ) ) {
                $taxonomies = array_values( array_diff( $taxonomies, $selected ) );
            }
        } else {
            // Backward compatibility with previous include/exclude fields.
            $include = $this->normalize_slug_list(
                isset( $settings['sitemap_taxonomies_include'] ) ? $settings['sitemap_taxonomies_include'] : array(),
                $taxonomies
            );
            $exclude = $this->normalize_slug_list(
                isset( $settings['sitemap_taxonomies_exclude'] ) ? $settings['sitemap_taxonomies_exclude'] : array(),
                $taxonomies
            );

            if ( ! empty( $include ) ) {
                $taxonomies = array_values( array_intersect( $taxonomies, $include ) );
            }

            if ( ! empty( $exclude ) ) {
                $taxonomies = array_values( array_diff( $taxonomies, $exclude ) );
            }
        }

        if ( ! empty( $settings['noindex_categories'] ) ) {
            $taxonomies = array_values( array_diff( $taxonomies, array( 'category' ) ) );
        }

        if ( ! empty( $settings['noindex_tags'] ) ) {
            $taxonomies = array_values( array_diff( $taxonomies, array( 'post_tag' ) ) );
        }

        return array_values( array_unique( $taxonomies ) );
    }

    /**
     * Sanitize submitted slug arrays and keep only allowed values.
     *
     * @param mixed $slugs Submitted values.
     * @param array $allowed Allowed slugs.
     * @return array
     */
    private function normalize_slug_list( $slugs, $allowed ) {
        if ( ! is_array( $slugs ) ) {
            return array();
        }

        $clean = array();
        foreach ( $slugs as $slug ) {
            if ( ! is_scalar( $slug ) ) {
                continue;
            }

            $slug = sanitize_key( (string) $slug );
            if ( '' === $slug || ! in_array( $slug, $allowed, true ) ) {
                continue;
            }

            $clean[] = $slug;
        }

        return array_values( array_unique( $clean ) );
    }

    public function handle_request() {
        if ( ! get_query_var( 'wpmazic_sitemap' ) ) {
            return;
        }

        $settings = wpmazic_seo_get_settings();
        if ( isset( $settings['enable_sitemap'] ) && ! (int) $settings['enable_sitemap'] ) {
            status_header( 404 );
            return;
        }

        nocache_headers();
        header( 'Content-Type: application/xml; charset=utf-8' );

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Homepage.
        echo '  <url>' . "\n";
        echo '    <loc>' . esc_url( home_url( '/' ) ) . '</loc>' . "\n";
        echo '    <lastmod>' . esc_html( gmdate( 'c' ) ) . '</lastmod>' . "\n";
        echo '    <changefreq>daily</changefreq>' . "\n";
        echo '    <priority>1.0</priority>' . "\n";
        echo '  </url>' . "\n";

        // Public post types with include/exclude controls.
        $post_types = $this->get_sitemap_post_types( $settings );
        if ( ! empty( $post_types ) ) {
            $posts = get_posts(
                array(
                    'numberposts' => -1,
                    'post_status' => 'publish',
                    'post_type'   => $post_types,
                    'fields'      => 'ids',
                )
            );

            foreach ( $posts as $post_id ) {
                if ( get_post_meta( $post_id, '_wpmazic_noindex', true ) ) {
                    continue;
                }

                echo '  <url>' . "\n";
                echo '    <loc>' . esc_url( get_permalink( $post_id ) ) . '</loc>' . "\n";
                echo '    <lastmod>' . esc_html( get_the_modified_date( 'c', $post_id ) ) . '</lastmod>' . "\n";
                echo '    <changefreq>weekly</changefreq>' . "\n";
                echo '    <priority>0.8</priority>' . "\n";
                echo '  </url>' . "\n";
            }
        }

        // Public taxonomy archives with include/exclude controls.
        $taxonomies = $this->get_sitemap_taxonomies( $settings );
        foreach ( $taxonomies as $taxonomy ) {

            $terms = get_terms(
                array(
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => true,
                )
            );

            if ( is_wp_error( $terms ) ) {
                continue;
            }

            foreach ( $terms as $term ) {
                $term_link = get_term_link( $term );
                if ( is_wp_error( $term_link ) ) {
                    continue;
                }

                echo '  <url>' . "\n";
                echo '    <loc>' . esc_url( $term_link ) . '</loc>' . "\n";
                echo '    <changefreq>weekly</changefreq>' . "\n";
                echo '    <priority>0.6</priority>' . "\n";
                echo '  </url>' . "\n";
            }
        }

        echo '</urlset>';
        exit;
    }
}
