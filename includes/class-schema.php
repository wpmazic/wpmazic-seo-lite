<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPMazic_Schema {

    public function __construct() {
        add_action( 'wp_head', array( $this, 'output_schema' ), 99 );
    }

    /**
     * Output structured data JSON-LD.
     */
    public function output_schema() {
        $settings = wpmazic_seo_get_settings();
        if ( isset( $settings['enable_schema'] ) && ! (int) $settings['enable_schema'] ) {
            return;
        }

        $graph = array();

        if ( is_front_page() || is_home() ) {
            $graph[] = $this->build_website_schema();
            $graph[] = $this->build_organization_schema();

            $local_business = $this->build_local_business_schema();
            if ( ! empty( $local_business ) ) {
                $graph[] = $local_business;
            }
        }

        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            $schema = $this->build_singular_schema();
            if ( ! empty( $schema ) ) {
                $graph[] = $schema;
            }

            $faq_schema = $this->build_faq_schema( $post_id );
            if ( ! empty( $faq_schema ) ) {
                $is_primary_faq = isset( $schema['@type'] ) && 'FAQPage' === $schema['@type'];
                if ( ! $is_primary_faq ) {
                    $graph[] = $faq_schema;
                }
            }
        }

        $breadcrumbs = apply_filters( 'wpmazic_breadcrumb_items', array() );
        if ( is_array( $breadcrumbs ) && count( $breadcrumbs ) > 1 ) {
            $graph[] = $this->build_breadcrumb_schema( $breadcrumbs );
        }

        if ( empty( $graph ) ) {
            return;
        }

        $payload = array(
            '@context' => 'https://schema.org',
            '@graph'   => $graph,
        );

        echo '<script type="application/ld+json">' . wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }

    /**
     * Build schema for singular pages.
     *
     * @return array
     */
    private function build_singular_schema() {
        $post_id = get_queried_object_id();
        $post    = get_post( $post_id );
        if ( ! $post ) {
            return array();
        }

        $schema_type = get_post_meta( $post_id, '_wpmazic_schema_type', true );
        if ( empty( $schema_type ) || 'default' === $schema_type ) {
            if ( 'product' === $post->post_type ) {
                $schema_type = 'Product';
            } elseif ( in_array( $post->post_type, array( 'job_listing', 'job', 'jobs' ), true ) ) {
                $schema_type = 'JobPosting';
            } elseif ( in_array( $post->post_type, array( 'course', 'courses', 'sfwd-courses' ), true ) ) {
                $schema_type = 'Course';
            } elseif ( $this->extract_first_video_url( $post->post_content ) ) {
                $schema_type = 'VideoObject';
            } else {
                $schema_type = 'Article';
            }
        }
        if ( 'none' === strtolower( (string) $schema_type ) ) {
            return array();
        }

        if ( 'FAQ' === $schema_type ) {
            $faq_schema = $this->build_faq_schema( $post_id );
            if ( ! empty( $faq_schema ) ) {
                return $faq_schema;
            }
            $schema_type = 'Article';
        }

        $description = get_post_meta( $post_id, '_wpmazic_description', true );
        if ( empty( $description ) ) {
            $description = wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 35 );
        }

        $schema = array(
            '@type'            => $schema_type,
            '@id'              => trailingslashit( get_permalink( $post_id ) ) . '#schema',
            'url'              => get_permalink( $post_id ),
            'headline'         => get_the_title( $post_id ),
            'description'      => $description,
            'datePublished'    => get_the_date( 'c', $post_id ),
            'dateModified'     => get_the_modified_date( 'c', $post_id ),
            'mainEntityOfPage' => get_permalink( $post_id ),
            'author'           => $this->build_author_schema( (int) $post->post_author ),
            'publisher'        => array(
                '@type' => 'Organization',
                'name'  => get_bloginfo( 'name' ),
            ),
        );

        if ( 'Product' === $schema_type && function_exists( 'wc_get_product' ) ) {
            $product = wc_get_product( $post_id );
            if ( $product ) {
                $price = $product->get_price();
                if ( '' !== (string) $price ) {
                    $schema['offers'] = array(
                        '@type'         => 'Offer',
                        'priceCurrency' => get_woocommerce_currency(),
                        'price'         => $price,
                        'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                        'url'           => get_permalink( $post_id ),
                    );
                }
            }
        }

        if ( 'LocalBusiness' === $schema_type ) {
            $local = $this->build_local_business_schema();
            if ( ! empty( $local ) ) {
                $schema = array_merge( $schema, $local );
            }
        }

        if ( 'Review' === $schema_type ) {
            $schema['reviewBody'] = $description;
            $schema['itemReviewed'] = array(
                '@type' => 'Thing',
                'name'  => get_the_title( $post_id ),
            );
        }

        if ( 'VideoObject' === $schema_type ) {
            $video_url = $this->extract_first_video_url( $post->post_content );
            if ( ! empty( $video_url ) ) {
                $schema['embedUrl'] = $video_url;
                $schema['contentUrl'] = $video_url;
            }
            $schema['name'] = get_the_title( $post_id );
            if ( has_post_thumbnail( $post_id ) ) {
                $thumbnail = wp_get_attachment_image_url( get_post_thumbnail_id( $post_id ), 'full' );
                if ( $thumbnail ) {
                    $schema['thumbnailUrl'] = $thumbnail;
                }
            }
        }

        if ( 'Course' === $schema_type ) {
            $schema['provider'] = array(
                '@type' => 'Organization',
                'name'  => get_bloginfo( 'name' ),
                'url'   => home_url( '/' ),
            );
        }

        if ( 'JobPosting' === $schema_type ) {
            $schema['datePosted'] = get_the_date( 'c', $post_id );
            $schema['hiringOrganization'] = array(
                '@type' => 'Organization',
                'name'  => get_bloginfo( 'name' ),
                'sameAs' => home_url( '/' ),
            );
        }

        if ( has_post_thumbnail( $post_id ) ) {
            $image = wp_get_attachment_image_url( get_post_thumbnail_id( $post_id ), 'full' );
            if ( $image ) {
                $schema['image'] = array( $image );
            }
        }

        return $schema;
    }

    /**
     * Try to find first video URL in content.
     *
     * @param string $content Raw content.
     * @return string
     */
    private function extract_first_video_url( $content ) {
        $content = (string) $content;
        if ( '' === trim( (string) $content ) ) {
            return '';
        }

        if ( preg_match( '/https?:\/\/[^\s"\']+(youtube\.com|youtu\.be|vimeo\.com)[^\s"\']*/i', (string) $content, $matches ) ) {
            return esc_url_raw( $matches[0] );
        }

        if ( preg_match( '/https?:\/\/[^\s"\']+\.(mp4|webm|mov)(\?[^\s"\']*)?/i', (string) $content, $matches ) ) {
            return esc_url_raw( $matches[0] );
        }

        return '';
    }

    /**
     * Build enhanced Person schema for post author.
     *
     * @param int $user_id User ID.
     * @return array
     */
    private function build_author_schema( $user_id ) {
        $user_id = (int) $user_id;

        $person = array(
            '@type' => 'Person',
            'name'  => get_the_author_meta( 'display_name', $user_id ),
            'url'   => get_author_posts_url( $user_id ),
        );

        $description = get_the_author_meta( 'description', $user_id );
        if ( '' !== trim( (string) $description ) ) {
            $person['description'] = wp_strip_all_tags( (string) $description );
        }

        $job_title = get_user_meta( $user_id, 'wpmazic_author_job_title', true );
        if ( '' !== trim( (string) $job_title ) ) {
            $person['jobTitle'] = sanitize_text_field( $job_title );
        }

        $expertise = get_user_meta( $user_id, 'wpmazic_author_expertise', true );
        if ( '' !== trim( (string) $expertise ) ) {
            $person['knowsAbout'] = array_map(
                'sanitize_text_field',
                array_filter( array_map( 'trim', explode( ',', (string) $expertise ) ) )
            );
        }

        $sameas_raw = get_user_meta( $user_id, 'wpmazic_author_sameas', true );
        if ( '' !== trim( (string) $sameas_raw ) ) {
            $sameas = array();
            $lines  = preg_split( '/\r\n|\r|\n/', (string) $sameas_raw );
            foreach ( $lines as $line ) {
                $line = esc_url_raw( trim( (string) $line ) );
                if ( '' !== $line ) {
                    $sameas[] = $line;
                }
            }
            if ( ! empty( $sameas ) ) {
                $person['sameAs'] = array_values( array_unique( $sameas ) );
            }
        }

        return $person;
    }

    /**
     * Build FAQPage schema from per-post FAQ items.
     *
     * @param int $post_id Post ID.
     * @return array
     */
    private function build_faq_schema( $post_id ) {
        $post_id = (int) $post_id;
        if ( $post_id <= 0 ) {
            return array();
        }

        $raw_items = get_post_meta( $post_id, '_wpmazic_faq_items', true );
        $raw_items = maybe_unserialize( $raw_items );
        if ( ! is_array( $raw_items ) ) {
            return array();
        }
        $main_entity = array();
        foreach ( $raw_items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $question = isset( $item['question'] ) ? sanitize_text_field( $item['question'] ) : '';
            $answer   = isset( $item['answer'] ) ? sanitize_textarea_field( $item['answer'] ) : '';

            if ( '' === trim( (string) $question ) || '' === trim( (string) $answer ) ) {
                continue;
            }

            $main_entity[] = array(
                '@type'          => 'Question',
                'name'           => $question,
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => $answer,
                ),
            );
        }

        if ( empty( $main_entity ) ) {
            return array();
        }

        return array(
            '@type'      => 'FAQPage',
            '@id'        => trailingslashit( get_permalink( $post_id ) ) . '#faq',
            'url'        => get_permalink( $post_id ),
            'mainEntity' => $main_entity,
        );
    }

    /**
     * Website schema.
     *
     * @return array
     */
    private function build_website_schema() {
        return array(
            '@type'       => 'WebSite',
            '@id'         => trailingslashit( home_url( '/' ) ) . '#website',
            'url'         => home_url( '/' ),
            'name'        => get_bloginfo( 'name' ),
            'description' => get_bloginfo( 'description' ),
            'potentialAction' => array(
                '@type'       => 'SearchAction',
                'target'      => home_url( '/?s={search_term_string}' ),
                'query-input' => 'required name=search_term_string',
            ),
        );
    }

    /**
     * Organization schema.
     *
     * @return array
     */
    private function build_organization_schema() {
        $settings = wpmazic_seo_get_settings();
        $social   = array();

        foreach ( array( 'social_facebook', 'social_twitter', 'social_instagram', 'social_linkedin' ) as $field ) {
            if ( ! empty( $settings[ $field ] ) ) {
                $social[] = esc_url_raw( $settings[ $field ] );
            }
        }

        $org = array(
            '@type' => 'Organization',
            '@id'   => trailingslashit( home_url( '/' ) ) . '#organization',
            'name'  => get_bloginfo( 'name' ),
            'url'   => home_url( '/' ),
        );

        if ( ! empty( $social ) ) {
            $org['sameAs'] = $social;
        }

        return $org;
    }

    /**
     * Local business schema from settings.
     *
     * @return array
     */
    private function build_local_business_schema() {
        $settings = wpmazic_seo_get_settings();

        if ( empty( $settings['business_name'] ) ) {
            return array();
        }

        $schema = array(
            '@type' => ! empty( $settings['business_type'] ) ? sanitize_text_field( $settings['business_type'] ) : 'LocalBusiness',
            '@id'   => trailingslashit( home_url( '/' ) ) . '#localbusiness',
            'name'  => sanitize_text_field( $settings['business_name'] ),
            'url'   => home_url( '/' ),
        );

        if ( ! empty( $settings['business_phone'] ) ) {
            $schema['telephone'] = sanitize_text_field( $settings['business_phone'] );
        }

        $address_parts = array(
            'streetAddress'   => ! empty( $settings['business_address'] ) ? sanitize_text_field( $settings['business_address'] ) : '',
            'addressLocality' => ! empty( $settings['business_city'] ) ? sanitize_text_field( $settings['business_city'] ) : '',
            'addressRegion'   => ! empty( $settings['business_state'] ) ? sanitize_text_field( $settings['business_state'] ) : '',
            'postalCode'      => ! empty( $settings['business_zip'] ) ? sanitize_text_field( $settings['business_zip'] ) : '',
            'addressCountry'  => ! empty( $settings['business_country'] ) ? sanitize_text_field( $settings['business_country'] ) : '',
        );

        $has_address = false;
        foreach ( $address_parts as $value ) {
            if ( '' !== $value ) {
                $has_address = true;
                break;
            }
        }

        if ( $has_address ) {
            $schema['address'] = array_merge(
                array(
                    '@type' => 'PostalAddress',
                ),
                $address_parts
            );
        }

        $lat = isset( $settings['business_lat'] ) ? (float) $settings['business_lat'] : 0;
        $lng = isset( $settings['business_lng'] ) ? (float) $settings['business_lng'] : 0;
        if ( 0.0 !== $lat || 0.0 !== $lng ) {
            $schema['geo'] = array(
                '@type'     => 'GeoCoordinates',
                'latitude'  => $lat,
                'longitude' => $lng,
            );
        }

        return $schema;
    }

    /**
     * BreadcrumbList schema.
     *
     * @param array $items Breadcrumb items.
     * @return array
     */
    private function build_breadcrumb_schema( $items ) {
        $list = array();

        foreach ( $items as $index => $item ) {
            $list[] = array(
                '@type'    => 'ListItem',
                'position' => $index + 1,
                'name'     => isset( $item['label'] ) ? $item['label'] : '',
                'item'     => isset( $item['url'] ) ? $item['url'] : '',
            );
        }

        return array(
            '@type'           => 'BreadcrumbList',
            '@id'             => trailingslashit( home_url( '/' ) ) . '#breadcrumb',
            'itemListElement' => $list,
        );
    }
}
