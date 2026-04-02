<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPMazic_Migration {

    /**
     * Supported source plugins.
     *
     * @return array
     */
    public static function get_supported_sources() {
        return array(
            'auto'              => __( 'Auto Detect (Recommended)', 'wpmazic-seo-lite' ),
            'yoast'             => __( 'Yoast SEO', 'wpmazic-seo-lite' ),
            'rank_math'         => __( 'Rank Math SEO', 'wpmazic-seo-lite' ),
            'aioseo'            => __( 'All in One SEO (AIOSEO)', 'wpmazic-seo-lite' ),
            'seopress'          => __( 'SEOPress', 'wpmazic-seo-lite' ),
            'the_seo_framework' => __( 'The SEO Framework', 'wpmazic-seo-lite' ),
            'slim_seo'          => __( 'Slim SEO', 'wpmazic-seo-lite' ),
            'squirrly'          => __( 'Squirrly SEO', 'wpmazic-seo-lite' ),
            'wp_meta_seo'       => __( 'WP Meta SEO', 'wpmazic-seo-lite' ),
            'smartcrawl'        => __( 'SmartCrawl SEO', 'wpmazic-seo-lite' ),
            'premium_seo_pack'  => __( 'Premium SEO Pack', 'wpmazic-seo-lite' ),
        );
    }

    /**
     * Detect active SEO plugins.
     *
     * @return array
     */
    public static function get_detected_sources() {
        if ( ! function_exists( 'is_plugin_active' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $checks = array(
            'yoast' => array(
                'plugins' => array( 'wordpress-seo/wp-seo.php', 'wordpress-seo-premium/wp-seo-premium.php' ),
                'class'   => 'WPSEO_Options',
                'option'  => 'wpseo',
            ),
            'rank_math' => array(
                'plugins' => array( 'seo-by-rank-math/rank-math.php' ),
                'class'   => 'RankMath',
                'option'  => 'rank-math-options-general',
            ),
            'aioseo' => array(
                'plugins' => array( 'all-in-one-seo-pack/all_in_one_seo_pack.php', 'all-in-one-seo-pack-pro/all_in_one_seo_pack.php' ),
                'class'   => 'AIOSEO\\Plugin\\AIOSEO',
                'option'  => 'aioseo_options',
            ),
            'seopress' => array(
                'plugins' => array( 'wp-seopress/seopress.php', 'wp-seopress-pro/seopress-pro.php' ),
                'class'   => 'SEOPress\\Main',
                'option'  => 'seopress_titles_option_name',
            ),
            'the_seo_framework' => array(
                'plugins' => array( 'autodescription/autodescription.php' ),
                'class'   => 'The_SEO_Framework\\Load',
                'option'  => 'autodescription-settings',
            ),
            'slim_seo' => array(
                'plugins' => array( 'slim-seo/slim-seo.php' ),
                'class'   => 'SlimSEO\\Main',
                'option'  => 'slim_seo',
            ),
            'squirrly' => array(
                'plugins' => array( 'squirrly-seo/squirrly.php' ),
                'class'   => 'SQ_Classes_ObjController',
                'option'  => 'sq_options',
            ),
            'wp_meta_seo' => array(
                'plugins' => array( 'wp-meta-seo/wp-meta-seo.php' ),
                'class'   => 'WpmsMain',
                'option'  => 'wpms_settings',
            ),
            'smartcrawl' => array(
                'plugins' => array( 'smartcrawl-seo/wpmu-dev-seo.php' ),
                'class'   => 'Smartcrawl_Service',
                'option'  => 'wds_settings',
            ),
            'premium_seo_pack' => array(
                'plugins' => array( 'premium-seo-pack/plugin.php', 'premium-seo-pack/premium-seo-pack.php' ),
                'class'   => 'pspSeo',
                'option'  => 'psp_opts',
            ),
        );

        $detected = array();
        foreach ( $checks as $slug => $check ) {
            if ( self::is_source_detected( $check ) ) {
                $detected[] = $slug;
            }
        }

        return $detected;
    }

    /**
     * Run post-level metadata migration.
     *
     * @param array $args Migration options.
     * @return array
     */
    public static function run_metadata_import( $args = array() ) {
        $defaults = array(
            'source'                  => 'auto',
            'overwrite'               => false,
            'include_social'          => true,
            'include_robots'          => true,
            'include_advanced_robots' => true,
            'include_image_seo'       => true,
        );
        $args = wp_parse_args( $args, $defaults );

        $source           = sanitize_key( (string) $args['source'] );
        $source_slugs     = self::resolve_source_slugs( $source );
        $overwrite        = ! empty( $args['overwrite'] );
        $include_social   = ! empty( $args['include_social'] );
        $include_robots   = ! empty( $args['include_robots'] );
        $include_advanced = ! empty( $args['include_advanced_robots'] );
        $include_image_seo = ! empty( $args['include_image_seo'] );

        $post_types = get_post_types(
            array(
                'public' => true,
            ),
            'names'
        );
        if ( ! isset( $post_types['attachment'] ) ) {
            $post_types['attachment'] = 'attachment';
        }

        $post_ids = get_posts(
            array(
                'numberposts' => -1,
                'post_type'   => array_values( $post_types ),
                'post_status' => array( 'publish', 'future', 'private', 'inherit' ),
                'fields'      => 'ids',
            )
        );

        $stats = self::empty_stats();
        foreach ( $post_ids as $post_id ) {
            $stats['scanned']++;

            self::maybe_import_string(
                $post_id,
                '_wpmazic_title',
                self::pick_first_meta( $post_id, self::get_meta_keys_for_field( 'title', $source_slugs ) ),
                'sanitize_text_field',
                $overwrite,
                $stats,
                'title'
            );

            self::maybe_import_string(
                $post_id,
                '_wpmazic_description',
                self::pick_first_meta( $post_id, self::get_meta_keys_for_field( 'description', $source_slugs ) ),
                'sanitize_textarea_field',
                $overwrite,
                $stats,
                'description'
            );

            self::import_focus_keywords( $post_id, $source_slugs, $overwrite, $stats );

            self::maybe_import_string(
                $post_id,
                '_wpmazic_canonical',
                self::pick_first_meta( $post_id, self::get_meta_keys_for_field( 'canonical', $source_slugs ) ),
                'esc_url_raw',
                $overwrite,
                $stats,
                'canonical'
            );

            if ( $include_robots ) {
                self::maybe_import_flag(
                    $post_id,
                    '_wpmazic_noindex',
                    self::source_has_robot_directive( $post_id, 'noindex', $source_slugs ),
                    $overwrite,
                    $stats,
                    'noindex'
                );

                self::maybe_import_flag(
                    $post_id,
                    '_wpmazic_nofollow',
                    self::source_has_robot_directive( $post_id, 'nofollow', $source_slugs ),
                    $overwrite,
                    $stats,
                    'nofollow'
                );
            }

            if ( $include_advanced ) {
                self::maybe_import_flag(
                    $post_id,
                    '_wpmazic_noarchive',
                    self::source_has_robot_directive( $post_id, 'noarchive', $source_slugs ),
                    $overwrite,
                    $stats,
                    'noarchive'
                );

                self::maybe_import_flag(
                    $post_id,
                    '_wpmazic_nosnippet',
                    self::source_has_robot_directive( $post_id, 'nosnippet', $source_slugs ),
                    $overwrite,
                    $stats,
                    'nosnippet'
                );

                self::maybe_import_flag(
                    $post_id,
                    '_wpmazic_noimageindex',
                    self::source_has_robot_directive( $post_id, 'noimageindex', $source_slugs ),
                    $overwrite,
                    $stats,
                    'noimageindex'
                );
            }

            if ( $include_social ) {
                self::maybe_import_string(
                    $post_id,
                    '_wpmazic_og_title',
                    self::pick_first_meta( $post_id, self::get_meta_keys_for_field( 'og_title', $source_slugs ) ),
                    'sanitize_text_field',
                    $overwrite,
                    $stats,
                    'og_title'
                );

                self::maybe_import_string(
                    $post_id,
                    '_wpmazic_og_description',
                    self::pick_first_meta( $post_id, self::get_meta_keys_for_field( 'og_description', $source_slugs ) ),
                    'sanitize_textarea_field',
                    $overwrite,
                    $stats,
                    'og_description'
                );

                self::maybe_import_string(
                    $post_id,
                    '_wpmazic_og_image',
                    self::pick_first_meta( $post_id, self::get_meta_keys_for_field( 'og_image', $source_slugs ) ),
                    'esc_url_raw',
                    $overwrite,
                    $stats,
                    'og_image'
                );

                self::maybe_import_string(
                    $post_id,
                    '_wpmazic_twitter_title',
                    self::pick_first_meta( $post_id, self::get_meta_keys_for_field( 'twitter_title', $source_slugs ) ),
                    'sanitize_text_field',
                    $overwrite,
                    $stats,
                    'tw_title'
                );

                self::maybe_import_string(
                    $post_id,
                    '_wpmazic_twitter_description',
                    self::pick_first_meta( $post_id, self::get_meta_keys_for_field( 'twitter_description', $source_slugs ) ),
                    'sanitize_textarea_field',
                    $overwrite,
                    $stats,
                    'tw_description'
                );

                self::maybe_import_string(
                    $post_id,
                    '_wpmazic_twitter_image',
                    self::pick_first_meta( $post_id, self::get_meta_keys_for_field( 'twitter_image', $source_slugs ) ),
                    'esc_url_raw',
                    $overwrite,
                    $stats,
                    'tw_image'
                );
            }

            if ( $include_image_seo && 'attachment' === get_post_type( $post_id ) ) {
                self::import_attachment_image_seo( $post_id, $source_slugs, $overwrite, $stats );
            }
        }

        $stats['source']    = $source;
        $stats['overwrite'] = $overwrite ? 1 : 0;
        return $stats;
    }

    /**
     * Build a human-readable summary line.
     *
     * @param array $stats Import stats.
     * @return string
     */
    public static function build_summary_message( $stats ) {
        $robot_flags = ( isset( $stats['noindex'] ) ? (int) $stats['noindex'] : 0 )
            + ( isset( $stats['nofollow'] ) ? (int) $stats['nofollow'] : 0 )
            + ( isset( $stats['noarchive'] ) ? (int) $stats['noarchive'] : 0 )
            + ( isset( $stats['nosnippet'] ) ? (int) $stats['nosnippet'] : 0 )
            + ( isset( $stats['noimageindex'] ) ? (int) $stats['noimageindex'] : 0 );
        $social_fields = ( isset( $stats['og_title'] ) ? (int) $stats['og_title'] : 0 )
            + ( isset( $stats['og_description'] ) ? (int) $stats['og_description'] : 0 )
            + ( isset( $stats['og_image'] ) ? (int) $stats['og_image'] : 0 )
            + ( isset( $stats['tw_title'] ) ? (int) $stats['tw_title'] : 0 )
            + ( isset( $stats['tw_description'] ) ? (int) $stats['tw_description'] : 0 )
            + ( isset( $stats['tw_image'] ) ? (int) $stats['tw_image'] : 0 );
        $image_fields = ( isset( $stats['image_alt'] ) ? (int) $stats['image_alt'] : 0 )
            + ( isset( $stats['image_title'] ) ? (int) $stats['image_title'] : 0 )
            + ( isset( $stats['image_caption'] ) ? (int) $stats['image_caption'] : 0 )
            + ( isset( $stats['image_description'] ) ? (int) $stats['image_description'] : 0 )
            + ( isset( $stats['image_seo_title'] ) ? (int) $stats['image_seo_title'] : 0 )
            + ( isset( $stats['image_seo_description'] ) ? (int) $stats['image_seo_description'] : 0 )
            + ( isset( $stats['image_seo_keyword'] ) ? (int) $stats['image_seo_keyword'] : 0 );

        return sprintf(
            __( 'Migration complete. Scanned: %1$d. Imported -> Title: %2$d, Description: %3$d, Focus Keyword: %4$d, Canonical: %5$d, Robots Flags: %6$d, Social Fields: %7$d, Image SEO Fields: %8$d.', 'wpmazic-seo-lite' ),
            isset( $stats['scanned'] ) ? (int) $stats['scanned'] : 0,
            isset( $stats['title'] ) ? (int) $stats['title'] : 0,
            isset( $stats['description'] ) ? (int) $stats['description'] : 0,
            isset( $stats['keyword'] ) ? (int) $stats['keyword'] : 0,
            isset( $stats['canonical'] ) ? (int) $stats['canonical'] : 0,
            $robot_flags,
            $social_fields,
            $image_fields
        );
    }

    /**
     * Empty import stats structure.
     *
     * @return array
     */
    private static function empty_stats() {
        return array(
            'scanned'        => 0,
            'title'          => 0,
            'description'    => 0,
            'keyword'        => 0,
            'canonical'      => 0,
            'noindex'        => 0,
            'nofollow'       => 0,
            'noarchive'      => 0,
            'nosnippet'      => 0,
            'noimageindex'   => 0,
            'og_title'       => 0,
            'og_description' => 0,
            'og_image'       => 0,
            'tw_title'       => 0,
            'tw_description' => 0,
            'tw_image'       => 0,
            'image_alt'              => 0,
            'image_title'            => 0,
            'image_caption'          => 0,
            'image_description'      => 0,
            'image_seo_title'        => 0,
            'image_seo_description'  => 0,
            'image_seo_keyword'      => 0,
            'source'         => 'auto',
            'overwrite'      => 0,
        );
    }

    /**
     * Import the primary focus keyword.
     *
     * @param int   $post_id Post ID.
     * @param array $source_slugs Source slugs.
     * @param bool  $overwrite Overwrite mode.
     * @param array $stats Stats accumulator.
     */
    private static function import_focus_keywords( $post_id, $source_slugs, $overwrite, &$stats ) {
        $raw_focus = self::pick_first_meta( $post_id, self::get_meta_keys_for_field( 'focus_keyword', $source_slugs ) );
        if ( '' === trim( (string) $raw_focus ) ) {
            return;
        }

        $keywords = array_filter(
            array_map(
                static function ( $item ) {
                    return sanitize_text_field( trim( (string) $item ) );
                },
                preg_split( '/[,|]/', (string) $raw_focus )
            )
        );

        if ( empty( $keywords ) ) {
            return;
        }

        $existing_main = trim( (string) get_post_meta( $post_id, '_wpmazic_keyword', true ) );
        if ( $overwrite || '' === $existing_main ) {
            update_post_meta( $post_id, '_wpmazic_keyword', reset( $keywords ) );
            $stats['keyword']++;
        }
    }

    /**
     * Import image SEO data for attachments.
     *
     * @param int   $post_id Attachment ID.
     * @param array $source_slugs Source slugs.
     * @param bool  $overwrite Overwrite mode.
     * @param array $stats Stats accumulator.
     */
    private static function import_attachment_image_seo( $post_id, $source_slugs, $overwrite, &$stats ) {
        if ( ! wp_attachment_is_image( $post_id ) ) {
            return;
        }

        $attachment = get_post( $post_id );
        if ( ! $attachment instanceof WP_Post ) {
            return;
        }

        $image_alt = self::pick_first_meta( $post_id, self::get_meta_keys_for_field( 'image_alt', $source_slugs ) );
        if ( '' !== trim( (string) $image_alt ) ) {
            $existing_alt = trim( (string) get_post_meta( $post_id, '_wp_attachment_image_alt', true ) );
            if ( $overwrite || '' === $existing_alt ) {
                update_post_meta( $post_id, '_wp_attachment_image_alt', sanitize_text_field( $image_alt ) );
                $stats['image_alt']++;
            }
        }

        $updates = array();
        $counts  = array();

        $image_title = self::pick_first_meta( $post_id, self::get_meta_keys_for_field( 'image_title', $source_slugs ) );
        if ( '' !== trim( (string) $image_title ) ) {
            $current_title = trim( (string) $attachment->post_title );
            if ( $overwrite || '' === $current_title ) {
                $updates['post_title'] = sanitize_text_field( $image_title );
                $counts['image_title'] = 1;
            }
        }

        $image_caption = self::pick_first_meta( $post_id, self::get_meta_keys_for_field( 'image_caption', $source_slugs ) );
        if ( '' !== trim( (string) $image_caption ) ) {
            $current_caption = trim( (string) $attachment->post_excerpt );
            if ( $overwrite || '' === $current_caption ) {
                $updates['post_excerpt'] = sanitize_textarea_field( $image_caption );
                $counts['image_caption'] = 1;
            }
        }

        $image_description = self::pick_first_meta( $post_id, self::get_meta_keys_for_field( 'image_description', $source_slugs ) );
        if ( '' !== trim( (string) $image_description ) ) {
            $current_description = trim( (string) $attachment->post_content );
            if ( $overwrite || '' === $current_description ) {
                $updates['post_content'] = sanitize_textarea_field( $image_description );
                $counts['image_description'] = 1;
            }
        }

        if ( ! empty( $updates ) ) {
            $updates['ID'] = $post_id;
            $result        = wp_update_post( $updates, true );
            if ( ! is_wp_error( $result ) ) {
                foreach ( $counts as $stats_key => $count ) {
                    $stats[ $stats_key ] += (int) $count;
                }
            }
        }

        self::maybe_import_string(
            $post_id,
            '_wpmazic_title',
            self::pick_first_meta( $post_id, self::get_meta_keys_for_field( 'image_seo_title', $source_slugs ) ),
            'sanitize_text_field',
            $overwrite,
            $stats,
            'image_seo_title'
        );

        self::maybe_import_string(
            $post_id,
            '_wpmazic_description',
            self::pick_first_meta( $post_id, self::get_meta_keys_for_field( 'image_seo_description', $source_slugs ) ),
            'sanitize_textarea_field',
            $overwrite,
            $stats,
            'image_seo_description'
        );

        $raw_focus = self::pick_first_meta( $post_id, self::get_meta_keys_for_field( 'image_focus_keyword', $source_slugs ) );
        if ( '' !== trim( (string) $raw_focus ) ) {
            $keywords = array_filter(
                array_map(
                    static function ( $item ) {
                        return sanitize_text_field( trim( (string) $item ) );
                    },
                    preg_split( '/[,|]/', (string) $raw_focus )
                )
            );

            if ( ! empty( $keywords ) ) {
                $existing_keyword = trim( (string) get_post_meta( $post_id, '_wpmazic_keyword', true ) );
                if ( $overwrite || '' === $existing_keyword ) {
                    update_post_meta( $post_id, '_wpmazic_keyword', reset( $keywords ) );
                    $stats['image_seo_keyword']++;
                }
            }
        }
    }

    /**
     * Import scalar meta if source value exists.
     *
     * @param int      $post_id Post ID.
     * @param string   $target_key Target key.
     * @param string   $value Source value.
     * @param callable $sanitize_callback Sanitizer.
     * @param bool     $overwrite Overwrite mode.
     * @param array    $stats Stats accumulator.
     * @param string   $stats_key Stat key.
     */
    private static function maybe_import_string( $post_id, $target_key, $value, $sanitize_callback, $overwrite, &$stats, $stats_key ) {
        $value = trim( (string) $value );
        if ( '' === $value ) {
            return;
        }

        $existing = trim( (string) get_post_meta( $post_id, $target_key, true ) );
        if ( '' !== $existing && ! $overwrite ) {
            return;
        }

        $clean = is_callable( $sanitize_callback ) ? call_user_func( $sanitize_callback, $value ) : sanitize_text_field( $value );
        if ( '' === trim( (string) $clean ) ) {
            return;
        }

        update_post_meta( $post_id, $target_key, $clean );
        $stats[ $stats_key ]++;
    }

    /**
     * Import robots directive as boolean flag.
     *
     * @param int    $post_id Post ID.
     * @param string $target_key Target key.
     * @param bool   $enabled Source has directive.
     * @param bool   $overwrite Overwrite mode.
     * @param array  $stats Stats accumulator.
     * @param string $stats_key Stat key.
     */
    private static function maybe_import_flag( $post_id, $target_key, $enabled, $overwrite, &$stats, $stats_key ) {
        if ( ! $enabled ) {
            return;
        }

        $existing = '1' === (string) get_post_meta( $post_id, $target_key, true );
        if ( $existing && ! $overwrite ) {
            return;
        }

        update_post_meta( $post_id, $target_key, '1' );
        $stats[ $stats_key ]++;
    }

    /**
     * Check if any selected source marks the directive.
     *
     * @param int    $post_id Post ID.
     * @param string $directive Directive.
     * @param array  $source_slugs Sources.
     * @return bool
     */
    private static function source_has_robot_directive( $post_id, $directive, $source_slugs ) {
        if ( in_array( 'rank_math', $source_slugs, true ) ) {
            $rm_robots = self::normalize_robot_array( get_post_meta( $post_id, 'rank_math_robots', true ) );
            if ( in_array( $directive, $rm_robots, true ) ) {
                return true;
            }
        }

        if ( in_array( 'yoast', $source_slugs, true ) && in_array( $directive, array( 'noarchive', 'nosnippet', 'noimageindex' ), true ) ) {
            if ( self::source_has_yoast_advanced_directive( $post_id, $directive ) ) {
                return true;
            }
        }

        $robot_map = self::get_robot_meta_map();
        if ( empty( $robot_map[ $directive ] ) ) {
            return false;
        }

        foreach ( $source_slugs as $source_slug ) {
            if ( empty( $robot_map[ $directive ][ $source_slug ] ) ) {
                continue;
            }
            if ( self::has_truthy_meta( $post_id, $robot_map[ $directive ][ $source_slug ], $directive ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse Yoast advanced robots field.
     *
     * @param int    $post_id Post ID.
     * @param string $directive Directive.
     * @return bool
     */
    private static function source_has_yoast_advanced_directive( $post_id, $directive ) {
        $raw = self::extract_scalar( get_post_meta( $post_id, '_yoast_wpseo_meta-robots-adv', true ) );
        if ( '' === trim( (string) $raw ) ) {
            return false;
        }

        $list = array_filter(
            array_map(
                'trim',
                explode( ',', strtolower( (string) $raw ) )
            )
        );
        return in_array( strtolower( (string) $directive ), $list, true );
    }

    /**
     * Check if any provided meta key is set to a truthy directive value.
     *
     * @param int    $post_id Post ID.
     * @param array  $keys Meta keys.
     * @param string $directive Directive.
     * @return bool
     */
    private static function has_truthy_meta( $post_id, $keys, $directive ) {
        foreach ( $keys as $key ) {
            $value = get_post_meta( $post_id, $key, true );
            if ( is_array( $value ) ) {
                $flat = array_map(
                    static function ( $item ) {
                        return strtolower( trim( (string) self::extract_scalar( $item ) ) );
                    },
                    $value
                );
                if ( in_array( strtolower( (string) $directive ), $flat, true ) ) {
                    return true;
                }
            }

            if ( self::is_truthy_value( self::extract_scalar( $value ), $directive ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Pick first non-empty meta value from key list.
     *
     * @param int   $post_id Post ID.
     * @param array $keys Meta keys.
     * @return string
     */
    private static function pick_first_meta( $post_id, $keys ) {
        foreach ( $keys as $key ) {
            $value = self::extract_scalar( get_post_meta( $post_id, $key, true ) );
            if ( '' !== trim( (string) $value ) ) {
                return $value;
            }
        }
        return '';
    }

    /**
     * Extract scalar value from mixed metadata.
     *
     * @param mixed $value Raw value.
     * @return string
     */
    private static function extract_scalar( $value ) {
        if ( is_array( $value ) ) {
            if ( isset( $value['url'] ) && '' !== trim( (string) $value['url'] ) ) {
                return (string) $value['url'];
            }

            foreach ( $value as $item ) {
                if ( is_array( $item ) && isset( $item['url'] ) && '' !== trim( (string) $item['url'] ) ) {
                    return (string) $item['url'];
                }

                if ( ! is_array( $item ) && '' !== trim( (string) $item ) ) {
                    return (string) $item;
                }
            }

            return '';
        }
        return (string) $value;
    }

    /**
     * Resolve source selection to one or more source slugs.
     *
     * @param string $source Source selection.
     * @return array
     */
    private static function resolve_source_slugs( $source ) {
        $sources = self::get_supported_sources();
        $source  = isset( $sources[ $source ] ) ? $source : 'auto';
        if ( 'auto' !== $source ) {
            return array( $source );
        }

        $detected = self::get_detected_sources();
        if ( ! empty( $detected ) ) {
            return $detected;
        }

        $all = array_keys( $sources );
        return array_values(
            array_filter(
                $all,
                static function ( $slug ) {
                    return 'auto' !== $slug;
                }
            )
        );
    }

    /**
     * Return list of source keys for a field based on selected sources.
     *
     * @param string $field Field key.
     * @param array  $source_slugs Source slugs.
     * @return array
     */
    private static function get_meta_keys_for_field( $field, $source_slugs ) {
        $map  = self::get_source_meta_map();
        $keys = array();

        if ( empty( $map[ $field ] ) ) {
            return $keys;
        }

        foreach ( $source_slugs as $source_slug ) {
            if ( empty( $map[ $field ][ $source_slug ] ) ) {
                continue;
            }
            $keys = array_merge( $keys, $map[ $field ][ $source_slug ] );
        }

        return array_values( array_unique( $keys ) );
    }

    /**
     * Field-to-source meta key map.
     *
     * @return array
     */
    private static function get_source_meta_map() {
        return array_merge(
            self::get_core_meta_map(),
            self::get_social_meta_map(),
            self::get_image_meta_map()
        );
    }

    /**
     * Core metadata key mapping (title, description, keywords, canonical).
     *
     * @return array
     */
    private static function get_core_meta_map() {
        return array(
            'title' => array(
                'yoast' => array( '_yoast_wpseo_title' ),
                'rank_math' => array( 'rank_math_title' ),
                'aioseo' => array( '_aioseo_title', 'aioseo_title' ),
                'seopress' => array( '_seopress_titles_title' ),
                'the_seo_framework' => array( '_genesis_title', '_tsf_title', 'autodescription_title' ),
                'slim_seo' => array( 'slim_seo_title', '_slim_seo_title', 'slim_seo_meta_title' ),
                'squirrly' => array( '_sq_title', 'sq_title', 'squirrly_title' ),
                'wp_meta_seo' => array( '_metaseo_title', '_wp_meta_seo_title', 'metaseo_title' ),
                'smartcrawl' => array( '_wds_title' ),
                'premium_seo_pack' => array( 'psp_meta_title', '_psp_meta_title' ),
            ),
            'description' => array(
                'yoast' => array( '_yoast_wpseo_metadesc' ),
                'rank_math' => array( 'rank_math_description' ),
                'aioseo' => array( '_aioseo_description', 'aioseo_description', '_aioseo_desc' ),
                'seopress' => array( '_seopress_titles_desc' ),
                'the_seo_framework' => array( '_genesis_description', '_tsf_description', 'autodescription_description' ),
                'slim_seo' => array( 'slim_seo_description', '_slim_seo_description', 'slim_seo_meta_description' ),
                'squirrly' => array( '_sq_description', 'sq_description', 'squirrly_description' ),
                'wp_meta_seo' => array( '_metaseo_description', '_wp_meta_seo_description', 'metaseo_description' ),
                'smartcrawl' => array( '_wds_metadesc' ),
                'premium_seo_pack' => array( 'psp_meta_description', '_psp_meta_description' ),
            ),
            'focus_keyword' => array(
                'yoast' => array( '_yoast_wpseo_focuskw', '_yoast_wpseo_focuskeywords' ),
                'rank_math' => array( 'rank_math_focus_keyword' ),
                'aioseo' => array( '_aioseo_keywords', 'aioseo_keywords' ),
                'seopress' => array( '_seopress_analysis_target_kw', '_seopress_target_kw' ),
                'the_seo_framework' => array( '_genesis_keywords', '_tsf_focus_keywords' ),
                'slim_seo' => array( 'slim_seo_keywords', '_slim_seo_keywords' ),
                'squirrly' => array( '_sq_keywords', 'sq_keywords', 'squirrly_keyword' ),
                'wp_meta_seo' => array( '_metaseo_keywords', 'wp_meta_seo_keywords' ),
                'smartcrawl' => array( '_wds_focus_keywords' ),
                'premium_seo_pack' => array( 'psp_focus_keyword', '_psp_focus_keyword', 'psp_keywords' ),
            ),
            'canonical' => array(
                'yoast' => array( '_yoast_wpseo_canonical' ),
                'rank_math' => array( 'rank_math_canonical_url' ),
                'aioseo' => array( '_aioseo_canonical_url', 'aioseo_canonical_url' ),
                'seopress' => array( '_seopress_robots_canonical' ),
                'the_seo_framework' => array( '_genesis_canonical_uri', '_tsf_canonical' ),
                'slim_seo' => array( 'slim_seo_canonical', '_slim_seo_canonical' ),
                'squirrly' => array( '_sq_canonical', 'sq_canonical' ),
                'wp_meta_seo' => array( '_metaseo_canonical', '_wp_meta_seo_canonical' ),
                'smartcrawl' => array( '_wds_canonical' ),
                'premium_seo_pack' => array( 'psp_canonical', '_psp_canonical' ),
            ),
        );
    }

    /**
     * Social metadata key mapping.
     *
     * @return array
     */
    private static function get_social_meta_map() {
        return array(
            'og_title' => array(
                'yoast' => array( '_yoast_wpseo_opengraph-title' ),
                'rank_math' => array( 'rank_math_facebook_title' ),
                'aioseo' => array( '_aioseo_og_title', 'aioseo_og_title' ),
                'seopress' => array( '_seopress_social_fb_title' ),
                'the_seo_framework' => array( '_tsf_og_title', 'autodescription_og_title' ),
                'slim_seo' => array( 'slim_seo_og_title' ),
                'squirrly' => array( '_sq_facebook_title', 'sq_og_title' ),
                'wp_meta_seo' => array( '_metaseo_og_title' ),
                'smartcrawl' => array( '_wds_og_title' ),
                'premium_seo_pack' => array( 'psp_og_title', '_psp_og_title' ),
            ),
            'og_description' => array(
                'yoast' => array( '_yoast_wpseo_opengraph-description' ),
                'rank_math' => array( 'rank_math_facebook_description' ),
                'aioseo' => array( '_aioseo_og_description', 'aioseo_og_description' ),
                'seopress' => array( '_seopress_social_fb_desc' ),
                'the_seo_framework' => array( '_tsf_og_description' ),
                'slim_seo' => array( 'slim_seo_og_description' ),
                'squirrly' => array( '_sq_facebook_description', 'sq_og_description' ),
                'wp_meta_seo' => array( '_metaseo_og_description' ),
                'smartcrawl' => array( '_wds_og_description' ),
                'premium_seo_pack' => array( 'psp_og_description', '_psp_og_description' ),
            ),
            'og_image' => array(
                'yoast' => array( '_yoast_wpseo_opengraph-image' ),
                'rank_math' => array( 'rank_math_facebook_image' ),
                'aioseo' => array( '_aioseo_og_image', 'aioseo_og_image' ),
                'seopress' => array( '_seopress_social_fb_img' ),
                'the_seo_framework' => array( '_tsf_og_image' ),
                'slim_seo' => array( 'slim_seo_og_image' ),
                'squirrly' => array( '_sq_facebook_image', 'sq_og_image' ),
                'wp_meta_seo' => array( '_metaseo_og_image' ),
                'smartcrawl' => array( '_wds_og_image' ),
                'premium_seo_pack' => array( 'psp_og_image', '_psp_og_image' ),
            ),
            'twitter_title' => array(
                'yoast' => array( '_yoast_wpseo_twitter-title' ),
                'rank_math' => array( 'rank_math_twitter_title' ),
                'aioseo' => array( '_aioseo_twitter_title', 'aioseo_twitter_title' ),
                'seopress' => array( '_seopress_social_twitter_title' ),
                'the_seo_framework' => array( '_tsf_twitter_title' ),
                'slim_seo' => array( 'slim_seo_twitter_title' ),
                'squirrly' => array( '_sq_twitter_title', 'sq_twitter_title' ),
                'wp_meta_seo' => array( '_metaseo_twitter_title' ),
                'smartcrawl' => array( '_wds_twitter_title' ),
                'premium_seo_pack' => array( 'psp_twitter_title', '_psp_twitter_title' ),
            ),
            'twitter_description' => array(
                'yoast' => array( '_yoast_wpseo_twitter-description' ),
                'rank_math' => array( 'rank_math_twitter_description' ),
                'aioseo' => array( '_aioseo_twitter_description', 'aioseo_twitter_description' ),
                'seopress' => array( '_seopress_social_twitter_desc' ),
                'the_seo_framework' => array( '_tsf_twitter_description' ),
                'slim_seo' => array( 'slim_seo_twitter_description' ),
                'squirrly' => array( '_sq_twitter_description', 'sq_twitter_description' ),
                'wp_meta_seo' => array( '_metaseo_twitter_description' ),
                'smartcrawl' => array( '_wds_twitter_description' ),
                'premium_seo_pack' => array( 'psp_twitter_description', '_psp_twitter_description' ),
            ),
            'twitter_image' => array(
                'yoast' => array( '_yoast_wpseo_twitter-image' ),
                'rank_math' => array( 'rank_math_twitter_image' ),
                'aioseo' => array( '_aioseo_twitter_image', 'aioseo_twitter_image' ),
                'seopress' => array( '_seopress_social_twitter_img' ),
                'the_seo_framework' => array( '_tsf_twitter_image' ),
                'slim_seo' => array( 'slim_seo_twitter_image' ),
                'squirrly' => array( '_sq_twitter_image', 'sq_twitter_image' ),
                'wp_meta_seo' => array( '_metaseo_twitter_image' ),
                'smartcrawl' => array( '_wds_twitter_image' ),
                'premium_seo_pack' => array( 'psp_twitter_image', '_psp_twitter_image' ),
            ),
        );
    }

    /**
     * Image SEO metadata key mapping.
     *
     * @return array
     */
    private static function get_image_meta_map() {
        return array(
            'image_alt' => array(
                'yoast' => array( '_yoast_wpseo_opengraph-image-alt', '_yoast_wpseo_twitter-image-alt', '_yoast_wpseo_image_alt' ),
                'rank_math' => array( 'rank_math_image_alt', 'rank_math_media_alt', 'rank_math_alt' ),
                'aioseo' => array( '_aioseo_image_alt', 'aioseo_image_alt', '_aioseo_media_alt' ),
                'seopress' => array( '_seopress_image_alt', '_seopress_media_alt' ),
                'the_seo_framework' => array( '_tsf_image_alt' ),
                'slim_seo' => array( 'slim_seo_image_alt', '_slim_seo_image_alt' ),
                'squirrly' => array( '_sq_image_alt', 'sq_image_alt' ),
                'wp_meta_seo' => array( '_wp_meta_seo_image_alt', '_wpms_image_alt', 'wpms_image_alt' ),
                'smartcrawl' => array( '_wds_image_alt' ),
                'premium_seo_pack' => array( 'psp_image_alt', '_psp_image_alt' ),
            ),
            'image_title' => array(
                'yoast' => array( '_yoast_wpseo_image_title' ),
                'rank_math' => array( 'rank_math_image_title', 'rank_math_media_title' ),
                'aioseo' => array( '_aioseo_image_title', 'aioseo_image_title' ),
                'seopress' => array( '_seopress_image_title' ),
                'the_seo_framework' => array( '_tsf_image_title' ),
                'slim_seo' => array( 'slim_seo_image_title', '_slim_seo_image_title' ),
                'squirrly' => array( '_sq_image_title', 'sq_image_title' ),
                'wp_meta_seo' => array( '_wp_meta_seo_image_title', '_wpms_image_title' ),
                'smartcrawl' => array( '_wds_image_title' ),
                'premium_seo_pack' => array( 'psp_image_title', '_psp_image_title' ),
            ),
            'image_caption' => array(
                'yoast' => array( '_yoast_wpseo_image_caption' ),
                'rank_math' => array( 'rank_math_image_caption', 'rank_math_media_caption' ),
                'aioseo' => array( '_aioseo_image_caption', 'aioseo_image_caption' ),
                'seopress' => array( '_seopress_image_caption' ),
                'the_seo_framework' => array( '_tsf_image_caption' ),
                'slim_seo' => array( 'slim_seo_image_caption', '_slim_seo_image_caption' ),
                'squirrly' => array( '_sq_image_caption', 'sq_image_caption' ),
                'wp_meta_seo' => array( '_wp_meta_seo_image_caption', '_wpms_image_caption' ),
                'smartcrawl' => array( '_wds_image_caption' ),
                'premium_seo_pack' => array( 'psp_image_caption', '_psp_image_caption' ),
            ),
            'image_description' => array(
                'yoast' => array( '_yoast_wpseo_image_description' ),
                'rank_math' => array( 'rank_math_image_description', 'rank_math_media_description' ),
                'aioseo' => array( '_aioseo_image_description', 'aioseo_image_description' ),
                'seopress' => array( '_seopress_image_description' ),
                'the_seo_framework' => array( '_tsf_image_description' ),
                'slim_seo' => array( 'slim_seo_image_description', '_slim_seo_image_description' ),
                'squirrly' => array( '_sq_image_description', 'sq_image_description' ),
                'wp_meta_seo' => array( '_wp_meta_seo_image_description', '_wpms_image_description' ),
                'smartcrawl' => array( '_wds_image_description' ),
                'premium_seo_pack' => array( 'psp_image_description', '_psp_image_description' ),
            ),
            'image_seo_title' => array(
                'yoast' => array( '_yoast_wpseo_title', '_yoast_wpseo_image_seo_title' ),
                'rank_math' => array( 'rank_math_title', 'rank_math_image_seo_title' ),
                'aioseo' => array( '_aioseo_title', '_aioseo_image_seo_title' ),
                'seopress' => array( '_seopress_titles_title', '_seopress_image_seo_title' ),
                'the_seo_framework' => array( '_tsf_title', '_tsf_image_seo_title' ),
                'slim_seo' => array( 'slim_seo_title', 'slim_seo_image_seo_title' ),
                'squirrly' => array( '_sq_title', '_sq_image_seo_title' ),
                'wp_meta_seo' => array( '_wp_meta_seo_title', '_wp_meta_seo_image_seo_title' ),
                'smartcrawl' => array( '_wds_title', '_wds_image_seo_title' ),
                'premium_seo_pack' => array( 'psp_meta_title', 'psp_image_seo_title' ),
            ),
            'image_seo_description' => array(
                'yoast' => array( '_yoast_wpseo_metadesc', '_yoast_wpseo_image_seo_desc' ),
                'rank_math' => array( 'rank_math_description', 'rank_math_image_seo_description' ),
                'aioseo' => array( '_aioseo_description', '_aioseo_image_seo_description' ),
                'seopress' => array( '_seopress_titles_desc', '_seopress_image_seo_description' ),
                'the_seo_framework' => array( '_tsf_description', '_tsf_image_seo_description' ),
                'slim_seo' => array( 'slim_seo_description', 'slim_seo_image_seo_description' ),
                'squirrly' => array( '_sq_description', '_sq_image_seo_description' ),
                'wp_meta_seo' => array( '_wp_meta_seo_description', '_wp_meta_seo_image_seo_description' ),
                'smartcrawl' => array( '_wds_metadesc', '_wds_image_seo_description' ),
                'premium_seo_pack' => array( 'psp_meta_description', 'psp_image_seo_description' ),
            ),
            'image_focus_keyword' => array(
                'yoast' => array( '_yoast_wpseo_focuskw', '_yoast_wpseo_image_focuskw' ),
                'rank_math' => array( 'rank_math_focus_keyword', 'rank_math_image_focus_keyword' ),
                'aioseo' => array( '_aioseo_keywords', '_aioseo_image_keywords' ),
                'seopress' => array( '_seopress_analysis_target_kw', '_seopress_image_target_kw' ),
                'the_seo_framework' => array( '_tsf_focus_keywords', '_tsf_image_focus_keywords' ),
                'slim_seo' => array( 'slim_seo_keywords', 'slim_seo_image_keywords' ),
                'squirrly' => array( '_sq_keywords', '_sq_image_keywords' ),
                'wp_meta_seo' => array( '_wp_meta_seo_keywords', '_wp_meta_seo_image_keywords' ),
                'smartcrawl' => array( '_wds_focus_keywords', '_wds_image_focus_keywords' ),
                'premium_seo_pack' => array( 'psp_focus_keyword', 'psp_image_focus_keyword' ),
            ),
        );
    }

    /**
     * Robots directive map by source plugin.
     *
     * @return array
     */
    private static function get_robot_meta_map() {
        return array(
            'noindex' => array(
                'yoast' => array( '_yoast_wpseo_meta-robots-noindex' ),
                'aioseo' => array( '_aioseo_noindex' ),
                'seopress' => array( '_seopress_robots_index' ),
                'the_seo_framework' => array( '_genesis_noindex', '_tsf_noindex' ),
                'slim_seo' => array( 'slim_seo_noindex', '_slim_seo_noindex' ),
                'squirrly' => array( '_sq_noindex', 'sq_noindex' ),
                'wp_meta_seo' => array( '_metaseo_noindex' ),
                'smartcrawl' => array( '_wds_noindex' ),
                'premium_seo_pack' => array( 'psp_noindex', '_psp_noindex' ),
            ),
            'nofollow' => array(
                'yoast' => array( '_yoast_wpseo_meta-robots-nofollow' ),
                'aioseo' => array( '_aioseo_nofollow' ),
                'seopress' => array( '_seopress_robots_follow' ),
                'the_seo_framework' => array( '_genesis_nofollow', '_tsf_nofollow' ),
                'slim_seo' => array( 'slim_seo_nofollow', '_slim_seo_nofollow' ),
                'squirrly' => array( '_sq_nofollow', 'sq_nofollow' ),
                'wp_meta_seo' => array( '_metaseo_nofollow' ),
                'smartcrawl' => array( '_wds_nofollow' ),
                'premium_seo_pack' => array( 'psp_nofollow', '_psp_nofollow' ),
            ),
            'noarchive' => array(
                'aioseo' => array( '_aioseo_noarchive' ),
                'seopress' => array( '_seopress_robots_archive' ),
                'the_seo_framework' => array( '_tsf_noarchive' ),
                'slim_seo' => array( 'slim_seo_noarchive', '_slim_seo_noarchive' ),
                'squirrly' => array( '_sq_noarchive', 'sq_noarchive' ),
                'wp_meta_seo' => array( '_metaseo_noarchive' ),
                'smartcrawl' => array( '_wds_noarchive' ),
                'premium_seo_pack' => array( 'psp_noarchive', '_psp_noarchive' ),
            ),
            'nosnippet' => array(
                'aioseo' => array( '_aioseo_nosnippet' ),
                'seopress' => array( '_seopress_robots_snippet' ),
                'the_seo_framework' => array( '_tsf_nosnippet' ),
                'slim_seo' => array( 'slim_seo_nosnippet', '_slim_seo_nosnippet' ),
                'squirrly' => array( '_sq_nosnippet', 'sq_nosnippet' ),
                'wp_meta_seo' => array( '_metaseo_nosnippet' ),
                'smartcrawl' => array( '_wds_nosnippet' ),
                'premium_seo_pack' => array( 'psp_nosnippet', '_psp_nosnippet' ),
            ),
            'noimageindex' => array(
                'aioseo' => array( '_aioseo_noimageindex' ),
                'seopress' => array( '_seopress_robots_imageindex' ),
                'the_seo_framework' => array( '_tsf_noimageindex' ),
                'slim_seo' => array( 'slim_seo_noimageindex', '_slim_seo_noimageindex' ),
                'squirrly' => array( '_sq_noimageindex', 'sq_noimageindex' ),
                'wp_meta_seo' => array( '_metaseo_noimageindex' ),
                'smartcrawl' => array( '_wds_noimageindex' ),
                'premium_seo_pack' => array( 'psp_noimageindex', '_psp_noimageindex' ),
            ),
        );
    }

    /**
     * Normalize robots source values to array.
     *
     * @param mixed $value Raw value.
     * @return array
     */
    private static function normalize_robot_array( $value ) {
        if ( is_array( $value ) ) {
            $items = $value;
        } else {
            $items = explode( ',', (string) $value );
        }

        return array_values(
            array_unique(
                array_filter(
                    array_map(
                        static function ( $item ) {
                            return strtolower( trim( (string) $item ) );
                        },
                        $items
                    )
                )
            )
        );
    }

    /**
     * Check if a value should be treated as enabled directive.
     *
     * @param mixed  $value Value.
     * @param string $directive Directive.
     * @return bool
     */
    private static function is_truthy_value( $value, $directive ) {
        $value = strtolower( trim( (string) $value ) );
        if ( '' === $value ) {
            return false;
        }

        return in_array(
            $value,
            array(
                '1',
                'true',
                'yes',
                'on',
                strtolower( (string) $directive ),
            ),
            true
        );
    }

    /**
     * Check whether a given source is active/detectable.
     *
     * @param array $check Check definition.
     * @return bool
     */
    private static function is_source_detected( $check ) {
        if ( ! empty( $check['class'] ) && class_exists( $check['class'] ) ) {
            return true;
        }

        if ( ! empty( $check['plugins'] ) && is_array( $check['plugins'] ) ) {
            foreach ( $check['plugins'] as $plugin_file ) {
                $plugin_file = is_scalar( $plugin_file ) ? (string) $plugin_file : '';
                $plugin_file = trim( (string) $plugin_file );
                if ( '' === $plugin_file ) {
                    continue;
                }

                if ( function_exists( 'is_plugin_active' ) && is_plugin_active( $plugin_file ) ) {
                    return true;
                }
                if ( function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $plugin_file ) ) {
                    return true;
                }
            }
        }

        if ( ! empty( $check['option'] ) ) {
            $option = get_option( $check['option'], null );
            if ( null !== $option ) {
                return true;
            }
        }

        return false;
    }
}
