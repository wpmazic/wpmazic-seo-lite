<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = get_option( 'wpmazic_settings', array() );

wpmazic_seo_admin_shell_open(
    __( 'Local SEO', 'wpmazic-seo-lite' ),
    __( 'Define your business identity and location signals for LocalBusiness schema output.', 'wpmazic-seo-lite' )
);
?>

<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Local SEO settings saved.', 'wpmazic-seo-lite' ); ?></p></div>
<?php endif; ?>

<form method="post" action="options.php">
    <?php settings_fields( 'wpmazic_settings' ); ?>
    <div class="wmz-card">
        <h2><?php esc_html_e( 'Business Profile', 'wpmazic-seo-lite' ); ?></h2>
        <div class="wmz-form-grid-3">
            <div class="wmz-field">
                <label for="business_name"><?php esc_html_e( 'Business Name', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="text" id="business_name" name="wpmazic_settings[business_name]" value="<?php echo esc_attr( isset( $settings['business_name'] ) ? $settings['business_name'] : '' ); ?>">
            </div>
            <div class="wmz-field">
                <label for="business_type"><?php esc_html_e( 'Business Type', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="text" id="business_type" name="wpmazic_settings[business_type]" value="<?php echo esc_attr( isset( $settings['business_type'] ) ? $settings['business_type'] : 'LocalBusiness' ); ?>">
            </div>
            <div class="wmz-field">
                <label for="business_phone"><?php esc_html_e( 'Phone', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="text" id="business_phone" name="wpmazic_settings[business_phone]" value="<?php echo esc_attr( isset( $settings['business_phone'] ) ? $settings['business_phone'] : '' ); ?>">
            </div>
            <div class="wmz-field">
                <label for="business_address"><?php esc_html_e( 'Street Address', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="text" id="business_address" name="wpmazic_settings[business_address]" value="<?php echo esc_attr( isset( $settings['business_address'] ) ? $settings['business_address'] : '' ); ?>">
            </div>
            <div class="wmz-field">
                <label for="business_city"><?php esc_html_e( 'City', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="text" id="business_city" name="wpmazic_settings[business_city]" value="<?php echo esc_attr( isset( $settings['business_city'] ) ? $settings['business_city'] : '' ); ?>">
            </div>
            <div class="wmz-field">
                <label for="business_state"><?php esc_html_e( 'State', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="text" id="business_state" name="wpmazic_settings[business_state]" value="<?php echo esc_attr( isset( $settings['business_state'] ) ? $settings['business_state'] : '' ); ?>">
            </div>
            <div class="wmz-field">
                <label for="business_zip"><?php esc_html_e( 'ZIP', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="text" id="business_zip" name="wpmazic_settings[business_zip]" value="<?php echo esc_attr( isset( $settings['business_zip'] ) ? $settings['business_zip'] : '' ); ?>">
            </div>
            <div class="wmz-field">
                <label for="business_country"><?php esc_html_e( 'Country', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="text" id="business_country" name="wpmazic_settings[business_country]" value="<?php echo esc_attr( isset( $settings['business_country'] ) ? $settings['business_country'] : '' ); ?>">
            </div>
            <div class="wmz-field">
                <label for="business_lat"><?php esc_html_e( 'Latitude', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="text" id="business_lat" name="wpmazic_settings[business_lat]" value="<?php echo esc_attr( isset( $settings['business_lat'] ) ? $settings['business_lat'] : '' ); ?>">
            </div>
            <div class="wmz-field">
                <label for="business_lng"><?php esc_html_e( 'Longitude', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="text" id="business_lng" name="wpmazic_settings[business_lng]" value="<?php echo esc_attr( isset( $settings['business_lng'] ) ? $settings['business_lng'] : '' ); ?>">
            </div>
        </div>
        <div class="wmz-actions">
            <?php submit_button( __( 'Save Local SEO', 'wpmazic-seo-lite' ), 'primary', 'submit', false ); ?>
        </div>
    </div>
</form>

<?php wpmazic_seo_admin_shell_close(); ?>
