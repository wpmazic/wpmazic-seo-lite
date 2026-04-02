<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'You are not allowed to access this page.', 'wpmazic-seo-lite' ) );
}

$dashboard_url = wpmazic_seo_admin_page_url( 'dashboard' );
$skip_url      = wp_nonce_url(
    add_query_arg(
        array(
            'page'   => 'wpmazic-seo-migration-wizard',
            'skip'   => 1,
        ),
        admin_url( 'admin.php' )
    ),
    'wpmazic_skip_migration_wizard'
);

if ( isset( $_GET['skip'] ) && check_admin_referer( 'wpmazic_skip_migration_wizard' ) ) {
    delete_option( 'wpmazic_show_migration_wizard' );
    update_option( 'wpmazic_migration_wizard_completed', current_time( 'mysql' ) );
    wp_safe_redirect( $dashboard_url );
    exit;
}

$sources  = class_exists( 'WPMazic_Migration' ) ? WPMazic_Migration::get_supported_sources() : array();
$detected = class_exists( 'WPMazic_Migration' ) ? WPMazic_Migration::get_detected_sources() : array();

$request_flag = static function ( $key, $default = false ) {
    if ( ! isset( $_REQUEST[ $key ] ) ) {
        return (bool) $default;
    }

    return '' !== sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) );
};

$step            = isset( $_REQUEST['step'] ) ? absint( wp_unslash( $_REQUEST['step'] ) ) : 1;
$step            = min( 4, max( 1, $step ) );
$selected_source_raw = isset( $_REQUEST['source'] ) ? wp_unslash( $_REQUEST['source'] ) : 'auto';
$selected_source     = is_scalar( $selected_source_raw ) ? sanitize_key( (string) $selected_source_raw ) : 'auto';
if ( ! isset( $sources[ $selected_source ] ) ) {
    $selected_source = 'auto';
}

$overwrite        = $request_flag( 'overwrite' );
$include_social   = $request_flag( 'include_social', true );
$include_robots   = $request_flag( 'include_robots', true );
$include_advanced = $request_flag( 'include_advanced_robots', true );
$include_image_seo = $request_flag( 'include_image_seo', true );

$result_key = 'wpmazic_migration_wizard_result_' . get_current_user_id();
$result     = get_transient( $result_key );
if ( ! is_array( $result ) ) {
    $result = array();
}

if ( isset( $_POST['wpmazic_run_migration'] ) && check_admin_referer( 'wpmazic_run_migration' ) ) {
    $run_source_raw = isset( $_POST['source'] ) ? wp_unslash( $_POST['source'] ) : 'auto';
    $run_source     = is_scalar( $run_source_raw ) ? sanitize_key( (string) $run_source_raw ) : 'auto';
    if ( ! isset( $sources[ $run_source ] ) ) {
        $run_source = 'auto';
    }

    $run_args = array(
        'source'                  => $run_source,
        'overwrite'               => ! empty( $_POST['overwrite'] ),
        'include_social'          => ! empty( $_POST['include_social'] ),
        'include_robots'          => ! empty( $_POST['include_robots'] ),
        'include_advanced_robots' => ! empty( $_POST['include_advanced_robots'] ),
        'include_image_seo'       => ! empty( $_POST['include_image_seo'] ),
    );

    if ( class_exists( 'WPMazic_Migration' ) ) {
        $result = WPMazic_Migration::run_metadata_import( $run_args );
        set_transient( $result_key, $result, HOUR_IN_SECONDS );
    } else {
        $result = array(
            'error' => __( 'Migration service is not available.', 'wpmazic-seo-lite' ),
        );
        set_transient( $result_key, $result, HOUR_IN_SECONDS );
    }

    delete_option( 'wpmazic_show_migration_wizard' );
    update_option( 'wpmazic_migration_wizard_completed', current_time( 'mysql' ) );

    wp_safe_redirect(
        add_query_arg(
            array(
                'page'   => 'wpmazic-seo-migration-wizard',
                'step'   => 4,
                'source' => $run_source,
            ),
            admin_url( 'admin.php' )
        )
    );
    exit;
}

$step_labels = array(
    1 => __( 'Welcome', 'wpmazic-seo-lite' ),
    2 => __( 'Source', 'wpmazic-seo-lite' ),
    3 => __( 'Options', 'wpmazic-seo-lite' ),
    4 => __( 'Done', 'wpmazic-seo-lite' ),
);

wpmazic_seo_admin_shell_open(
    __( 'Migration Wizard', 'wpmazic-seo-lite' ),
    __( 'Move your existing SEO metadata into WPMazic SEO in a few quick steps.', 'wpmazic-seo-lite' )
);
?>

<div class="wmz-card">
    <div class="tw-flex tw-flex-wrap tw-gap-2 tw-items-center">
        <?php foreach ( $step_labels as $index => $label ) : ?>
            <?php
            $is_active = $index === $step;
            $is_done   = $index < $step;
            $classes   = $is_active
                ? 'tw-bg-wmz-600 tw-text-white tw-border-wmz-600'
                : ( $is_done ? 'tw-bg-wmz-50 tw-text-wmz-700 tw-border-wmz-200' : 'tw-bg-white tw-text-slate-500 tw-border-slate-300' );
            ?>
            <span class="tw-inline-flex tw-items-center tw-rounded-full tw-border tw-px-3 tw-py-1 tw-text-xs tw-font-semibold <?php echo esc_attr( $classes ); ?>">
                <?php echo esc_html( $index . '. ' . $label ); ?>
            </span>
        <?php endforeach; ?>
    </div>
</div>

<?php if ( 1 === $step ) : ?>
    <div class="wmz-card">
        <h2><?php esc_html_e( 'Welcome to WPMazic SEO', 'wpmazic-seo-lite' ); ?></h2>
        <p class="wmz-subtle"><?php esc_html_e( 'This wizard imports SEO metadata from your current plugin so your rankings are not affected when switching.', 'wpmazic-seo-lite' ); ?></p>

        <div class="tw-mt-3 tw-grid md:tw-grid-cols-2 tw-gap-3">
            <div class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4">
                <p class="tw-font-semibold tw-text-slate-900"><?php esc_html_e( 'What will be imported', 'wpmazic-seo-lite' ); ?></p>
                <ul class="wmz-list tw-mt-2">
                    <li><?php esc_html_e( 'SEO title and meta description', 'wpmazic-seo-lite' ); ?></li>
                    <li><?php esc_html_e( 'Focus keyword', 'wpmazic-seo-lite' ); ?></li>
                    <li><?php esc_html_e( 'Canonical URL and robots directives', 'wpmazic-seo-lite' ); ?></li>
                    <li><?php esc_html_e( 'Open Graph and Twitter metadata', 'wpmazic-seo-lite' ); ?></li>
                </ul>
            </div>
            <div class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-p-4">
                <p class="tw-font-semibold tw-text-slate-900"><?php esc_html_e( 'Detected SEO plugins', 'wpmazic-seo-lite' ); ?></p>
                <?php if ( ! empty( $detected ) ) : ?>
                    <ul class="wmz-list tw-mt-2">
                        <?php foreach ( $detected as $slug ) : ?>
                            <li><?php echo esc_html( isset( $sources[ $slug ] ) ? $sources[ $slug ] : $slug ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p class="wmz-subtle tw-mt-2"><?php esc_html_e( 'No active SEO plugin detected. You can still import by selecting a source in the next step.', 'wpmazic-seo-lite' ); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="wmz-actions">
            <form method="get">
                <input type="hidden" name="page" value="wpmazic-seo-migration-wizard">
                <input type="hidden" name="step" value="2">
                <?php submit_button( __( 'Next', 'wpmazic-seo-lite' ), 'primary', 'submit', false ); ?>
            </form>
            <a class="button button-secondary" href="<?php echo esc_url( $skip_url ); ?>"><?php esc_html_e( 'Skip Wizard', 'wpmazic-seo-lite' ); ?></a>
        </div>
    </div>
<?php elseif ( 2 === $step ) : ?>
    <div class="wmz-card">
        <h2><?php esc_html_e( 'Choose Source Plugin', 'wpmazic-seo-lite' ); ?></h2>
        <p class="wmz-subtle"><?php esc_html_e( 'Pick the plugin you are migrating from. Auto detect is best for most sites.', 'wpmazic-seo-lite' ); ?></p>

        <form method="get">
            <input type="hidden" name="page" value="wpmazic-seo-migration-wizard">
            <input type="hidden" name="step" value="3">

            <div class="wmz-field tw-mt-3">
                <label for="wmz-source"><?php esc_html_e( 'Source plugin', 'wpmazic-seo-lite' ); ?></label>
                <select class="wmz-select" name="source" id="wmz-source">
                    <?php foreach ( $sources as $source_key => $source_label ) : ?>
                        <option value="<?php echo esc_attr( $source_key ); ?>" <?php selected( $selected_source, $source_key ); ?>>
                            <?php echo esc_html( $source_label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="wmz-actions">
                <a class="button button-secondary" href="<?php echo esc_url( add_query_arg( array( 'page' => 'wpmazic-seo-migration-wizard', 'step' => 1 ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Back', 'wpmazic-seo-lite' ); ?></a>
                <?php submit_button( __( 'Next', 'wpmazic-seo-lite' ), 'primary', 'submit', false ); ?>
            </div>
        </form>
    </div>
<?php elseif ( 3 === $step ) : ?>
    <div class="wmz-card">
        <h2><?php esc_html_e( 'Import Options', 'wpmazic-seo-lite' ); ?></h2>
        <p class="wmz-subtle"><?php esc_html_e( 'Review options, then click submit to start migration.', 'wpmazic-seo-lite' ); ?></p>

        <form method="post">
            <?php wp_nonce_field( 'wpmazic_run_migration' ); ?>
            <input type="hidden" name="step" value="3">
            <input type="hidden" name="source" value="<?php echo esc_attr( $selected_source ); ?>">

            <div class="wmz-inline-checks tw-mt-3">
                <label><input type="checkbox" name="include_social" value="1" <?php checked( $include_social ); ?>> <?php esc_html_e( 'Import OG/Twitter metadata', 'wpmazic-seo-lite' ); ?></label>
                <label><input type="checkbox" name="include_robots" value="1" <?php checked( $include_robots ); ?>> <?php esc_html_e( 'Import noindex/nofollow', 'wpmazic-seo-lite' ); ?></label>
                <label><input type="checkbox" name="include_advanced_robots" value="1" <?php checked( $include_advanced ); ?>> <?php esc_html_e( 'Import noarchive/nosnippet/noimageindex', 'wpmazic-seo-lite' ); ?></label>
                <label><input type="checkbox" name="include_image_seo" value="1" <?php checked( $include_image_seo ); ?>> <?php esc_html_e( 'Import image ALT/title/caption/description metadata', 'wpmazic-seo-lite' ); ?></label>
                <label><input type="checkbox" name="overwrite" value="1" <?php checked( $overwrite ); ?>> <?php esc_html_e( 'Overwrite existing WPMazic values', 'wpmazic-seo-lite' ); ?></label>
            </div>

            <p class="wmz-help tw-mt-3">
                <?php esc_html_e( 'Selected source:', 'wpmazic-seo-lite' ); ?>
                <strong><?php echo esc_html( isset( $sources[ $selected_source ] ) ? $sources[ $selected_source ] : $selected_source ); ?></strong>
            </p>

            <div class="wmz-actions">
                <a class="button button-secondary" href="<?php echo esc_url( add_query_arg( array( 'page' => 'wpmazic-seo-migration-wizard', 'step' => 2, 'source' => $selected_source ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Back', 'wpmazic-seo-lite' ); ?></a>
                <input type="hidden" name="wpmazic_run_migration" value="1">
                <?php submit_button( __( 'Submit and Import', 'wpmazic-seo-lite' ), 'primary', 'submit', false ); ?>
            </div>
        </form>
    </div>
<?php else : ?>
    <div class="wmz-card">
        <h2><?php esc_html_e( 'Migration Complete', 'wpmazic-seo-lite' ); ?></h2>

        <?php if ( ! empty( $result['error'] ) ) : ?>
            <div class="notice notice-error inline"><p><?php echo esc_html( $result['error'] ); ?></p></div>
        <?php elseif ( ! empty( $result ) && class_exists( 'WPMazic_Migration' ) ) : ?>
            <div class="notice notice-success inline"><p><?php echo esc_html( WPMazic_Migration::build_summary_message( $result ) ); ?></p></div>

            <div class="wmz-grid tw-mt-3">
                <div class="wmz-stat"><p class="wmz-stat-label"><?php esc_html_e( 'Scanned', 'wpmazic-seo-lite' ); ?></p><p class="wmz-stat-value"><?php echo esc_html( (string) (int) $result['scanned'] ); ?></p></div>
                <div class="wmz-stat"><p class="wmz-stat-label"><?php esc_html_e( 'Titles', 'wpmazic-seo-lite' ); ?></p><p class="wmz-stat-value"><?php echo esc_html( (string) (int) $result['title'] ); ?></p></div>
                <div class="wmz-stat"><p class="wmz-stat-label"><?php esc_html_e( 'Descriptions', 'wpmazic-seo-lite' ); ?></p><p class="wmz-stat-value"><?php echo esc_html( (string) (int) $result['description'] ); ?></p></div>
                <div class="wmz-stat"><p class="wmz-stat-label"><?php esc_html_e( 'Keywords', 'wpmazic-seo-lite' ); ?></p><p class="wmz-stat-value"><?php echo esc_html( (string) (int) $result['keyword'] ); ?></p></div>
                <div class="wmz-stat"><p class="wmz-stat-label"><?php esc_html_e( 'Canonicals', 'wpmazic-seo-lite' ); ?></p><p class="wmz-stat-value"><?php echo esc_html( (string) (int) $result['canonical'] ); ?></p></div>
                <div class="wmz-stat"><p class="wmz-stat-label"><?php esc_html_e( 'Robots Flags', 'wpmazic-seo-lite' ); ?></p><p class="wmz-stat-value"><?php echo esc_html( (string) ( (int) $result['noindex'] + (int) $result['nofollow'] + (int) $result['noarchive'] + (int) $result['nosnippet'] + (int) $result['noimageindex'] ) ); ?></p></div>
                <div class="wmz-stat"><p class="wmz-stat-label"><?php esc_html_e( 'Image Meta', 'wpmazic-seo-lite' ); ?></p><p class="wmz-stat-value"><?php echo esc_html( (string) ( ( isset( $result['image_alt'] ) ? (int) $result['image_alt'] : 0 ) + ( isset( $result['image_title'] ) ? (int) $result['image_title'] : 0 ) + ( isset( $result['image_caption'] ) ? (int) $result['image_caption'] : 0 ) + ( isset( $result['image_description'] ) ? (int) $result['image_description'] : 0 ) + ( isset( $result['image_seo_title'] ) ? (int) $result['image_seo_title'] : 0 ) + ( isset( $result['image_seo_description'] ) ? (int) $result['image_seo_description'] : 0 ) + ( isset( $result['image_seo_keyword'] ) ? (int) $result['image_seo_keyword'] : 0 ) ) ); ?></p></div>
            </div>
        <?php else : ?>
            <p class="wmz-subtle"><?php esc_html_e( 'No migration run found in this session. Start again from step 1 when you are ready to import metadata.', 'wpmazic-seo-lite' ); ?></p>
        <?php endif; ?>

        <div class="wmz-actions">
            <a class="button button-primary" href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Go to Dashboard', 'wpmazic-seo-lite' ); ?></a>
            <a class="button button-secondary" href="<?php echo esc_url( add_query_arg( array( 'page' => 'wpmazic-seo-migration-wizard', 'step' => 2 ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Run Another Import', 'wpmazic-seo-lite' ); ?></a>
        </div>
    </div>
<?php endif; ?>

<?php wpmazic_seo_admin_shell_close(); ?>
