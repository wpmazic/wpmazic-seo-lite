<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPMazic_Image_SEO {

    public function __construct() {
        add_filter( 'wp_get_attachment_image_attributes', array( $this, 'ensure_image_attributes' ), 20, 3 );
        add_action( 'add_attachment', array( $this, 'set_default_alt_on_upload' ) );
    }

    /**
     * Ensure attachment output contains useful alt/title attributes.
     *
     * @param array        $attr       Image attributes.
     * @param WP_Post      $attachment Attachment object.
     * @param string|array $size       Requested image size.
     * @return array
     */
    public function ensure_image_attributes( $attr, $attachment, $size ) {
        $current_alt = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
        $alt         = trim( (string) $current_alt );
        $title       = trim( (string) $attachment->post_title );

        if ( '' === $alt ) {
            $alt = '' !== $title ? $title : get_bloginfo( 'name' );
            $attr['alt'] = $alt;
        }

        if ( empty( $attr['title'] ) && '' !== $title ) {
            $attr['title'] = $title;
        }

        // Let WordPress core handle loading/fetchpriority heuristics.
        // Avoid invalid combinations like loading="lazy" + fetchpriority="high".
        $fetchpriority = isset( $attr['fetchpriority'] ) ? strtolower( trim( (string) $attr['fetchpriority'] ) ) : '';
        $loading       = isset( $attr['loading'] ) ? strtolower( trim( (string) $attr['loading'] ) ) : '';

        if ( 'high' === $fetchpriority && 'lazy' === $loading ) {
            unset( $attr['loading'] );
        }

        return $attr;
    }

    /**
     * On upload, create default attachment alt text if empty.
     *
     * @param int $attachment_id Attachment ID.
     */
    public function set_default_alt_on_upload( $attachment_id ) {
        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            return;
        }

        $alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
        if ( ! empty( $alt ) ) {
            return;
        }

        $attachment = get_post( $attachment_id );
        if ( ! $attachment ) {
            return;
        }

        $fallback = trim( (string) $attachment->post_title );
        if ( '' === $fallback ) {
            $fallback = get_bloginfo( 'name' );
        }

        update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $fallback ) );
    }
}
