<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings         = get_option( 'wpmazic_settings', array() );
$default_settings = function_exists( 'wpmazic_seo_get_default_settings' ) ? wpmazic_seo_get_default_settings() : array();

$sitemap_post_type_objects = get_post_types(
    array(
        'public' => true,
    ),
    'objects'
);
$sitemap_taxonomy_objects = get_taxonomies(
    array(
        'public' => true,
    ),
    'objects'
);

$sitemap_post_types_filter_enabled = ! empty( $settings['sitemap_post_types_filter_enabled'] );
$sitemap_post_types_filter_mode    = isset( $settings['sitemap_post_types_filter_mode'] ) && 'include' === $settings['sitemap_post_types_filter_mode'] ? 'include' : 'exclude';
$sitemap_post_types_selected       = isset( $settings['sitemap_post_types_selected'] ) && is_array( $settings['sitemap_post_types_selected'] ) ? array_map( 'sanitize_key', $settings['sitemap_post_types_selected'] ) : array();
$sitemap_taxonomies_filter_enabled = ! empty( $settings['sitemap_taxonomies_filter_enabled'] );
$sitemap_taxonomies_filter_mode    = isset( $settings['sitemap_taxonomies_filter_mode'] ) && 'include' === $settings['sitemap_taxonomies_filter_mode'] ? 'include' : 'exclude';
$sitemap_taxonomies_selected       = isset( $settings['sitemap_taxonomies_selected'] ) && is_array( $settings['sitemap_taxonomies_selected'] ) ? array_map( 'sanitize_key', $settings['sitemap_taxonomies_selected'] ) : array();

wpmazic_seo_admin_shell_open(
    __( 'Settings', 'wpmazic-seo-lite' ),
    __( 'Control global SEO defaults, indexing rules, social profiles, and site verification.', 'wpmazic-seo-lite' )
);
?>

<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved successfully.', 'wpmazic-seo-lite' ); ?></p></div>
<?php endif; ?>

<form method="post" action="options.php" class="tw-space-y-4">
    <?php settings_fields( 'wpmazic_settings' ); ?>

    <div class="wmz-card">
        <h2><?php esc_html_e( 'General', 'wpmazic-seo-lite' ); ?></h2>
        <div class="wmz-form-grid">
            <div class="wmz-field">
                <label for="site_name"><?php esc_html_e( 'Site Name', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="text" id="site_name" name="wpmazic_settings[site_name]" value="<?php echo esc_attr( isset( $settings['site_name'] ) ? $settings['site_name'] : get_bloginfo( 'name' ) ); ?>">
            </div>
            <div class="wmz-field">
                <label for="separator"><?php esc_html_e( 'Title Separator', 'wpmazic-seo-lite' ); ?></label>
                <select class="wmz-select" id="separator" name="wpmazic_settings[separator]">
                    <?php foreach ( array( '-', '|', '/', '~', '>' ) as $sep ) : ?>
                        <option value="<?php echo esc_attr( $sep ); ?>" <?php selected( isset( $settings['separator'] ) ? $settings['separator'] : '-', $sep ); ?>><?php echo esc_html( $sep ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="wmz-field">
                <label for="twitter_site"><?php esc_html_e( 'Twitter/X Handle', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="text" id="twitter_site" name="wpmazic_settings[twitter_site]" value="<?php echo esc_attr( isset( $settings['twitter_site'] ) ? $settings['twitter_site'] : '' ); ?>" placeholder="@yourbrand">
                <p class="wmz-help"><?php esc_html_e( 'Used in public Twitter/X meta tags on the frontend when Open Graph & Twitter Cards are enabled.', 'wpmazic-seo-lite' ); ?></p>
            </div>
            <div class="wmz-field">
                <label for="indexnow_api_key"><?php esc_html_e( 'IndexNow API Key', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="text" id="indexnow_api_key" name="wpmazic_settings[indexnow_api_key]" value="<?php echo esc_attr( isset( $settings['indexnow_api_key'] ) ? $settings['indexnow_api_key'] : '' ); ?>">
                <p class="wmz-help"><?php esc_html_e( 'Public key URL: /indexnow-key/{key}.txt. This key is intentionally public for IndexNow validation.', 'wpmazic-seo-lite' ); ?></p>
            </div>
            <div class="wmz-field">
                <label for="generator_meta_text"><?php esc_html_e( 'Branding Meta Text', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="text" id="generator_meta_text" name="wpmazic_settings[generator_meta_text]" value="<?php echo esc_attr( isset( $settings['generator_meta_text'] ) ? $settings['generator_meta_text'] : 'WPMazic SEO' ); ?>">
                <p class="wmz-help"><?php esc_html_e( 'Used in the public <meta name="generator"> tag when Plugin Branding Meta Tag is enabled.', 'wpmazic-seo-lite' ); ?></p>
            </div>
            <div class="wmz-field">
                <label for="ga4_measurement_id"><?php esc_html_e( 'GA4 Measurement ID', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="text" id="ga4_measurement_id" name="wpmazic_settings[ga4_measurement_id]" value="<?php echo esc_attr( isset( $settings['ga4_measurement_id'] ) ? $settings['ga4_measurement_id'] : '' ); ?>" placeholder="G-XXXXXXXXXX">
                <p class="wmz-help"><?php esc_html_e( 'GA4 measurement IDs are public identifiers. When tracking is enabled, this value appears in frontend source as part of the Google tag.', 'wpmazic-seo-lite' ); ?></p>
            </div>
            <div class="wmz-field">
                <label for="enable_ga4_tracking"><?php esc_html_e( 'Enable GA4 Frontend Tracking', 'wpmazic-seo-lite' ); ?></label>
                <label class="wmz-inline-checks">
                    <input type="checkbox" id="enable_ga4_tracking" name="wpmazic_settings[enable_ga4_tracking]" value="1" <?php checked( ! empty( $settings['enable_ga4_tracking'] ) ); ?>>
                    <?php esc_html_e( 'Load the Google tag on the public frontend', 'wpmazic-seo-lite' ); ?>
                </label>
                <p class="wmz-help"><?php esc_html_e( 'Leave this disabled if you do not want GA4 code added to page source.', 'wpmazic-seo-lite' ); ?></p>
            </div>
        </div>
    </div>

    <div class="wmz-card">
        <h2><?php esc_html_e( 'Feature Toggles', 'wpmazic-seo-lite' ); ?></h2>
        <p class="wmz-subtle"><?php esc_html_e( 'Enable only the Lite modules you want active on this site.', 'wpmazic-seo-lite' ); ?></p>
        <div class="wmz-toggle-groups">
            <?php
            $feature_labels = array(
                'enable_sitemap'            => __( 'XML Sitemap', 'wpmazic-seo-lite' ),
                'enable_schema'             => __( 'Schema Markup', 'wpmazic-seo-lite' ),
                'enable_og'                 => __( 'Open Graph & Twitter Cards', 'wpmazic-seo-lite' ),
                'enable_redirects'          => __( 'Redirect Manager', 'wpmazic-seo-lite' ),
                'enable_404_monitor'        => __( '404 Monitor', 'wpmazic-seo-lite' ),
                'enable_breadcrumbs'        => __( 'Breadcrumbs', 'wpmazic-seo-lite' ),
                'enable_image_seo'          => __( 'Image SEO', 'wpmazic-seo-lite' ),
                'enable_indexnow'           => __( 'IndexNow', 'wpmazic-seo-lite' ),
                'enable_link_tracking'      => __( 'Internal Link Tracking', 'wpmazic-seo-lite' ),
                'enable_generator_meta'     => __( 'Plugin Branding Meta Tag', 'wpmazic-seo-lite' ),
                'enable_llms_txt'           => __( 'llms.txt Endpoint', 'wpmazic-seo-lite' ),
                'enable_image_sitemap'      => __( 'Image Sitemap', 'wpmazic-seo-lite' ),
                'enable_auto_slug_redirect' => __( 'Auto Redirect on Slug Change', 'wpmazic-seo-lite' ),
                'enable_dynamic_og_image'   => __( 'Dynamic OG Image Fallback', 'wpmazic-seo-lite' ),
                'enable_auto_search_ping'   => __( 'Auto Ping Search Engines', 'wpmazic-seo-lite' ),
            );

            $feature_groups = array(
                array(
                    'title'       => __( 'Core SEO', 'wpmazic-seo-lite' ),
                    'description' => __( 'Fundamental SEO output and presentation modules.', 'wpmazic-seo-lite' ),
                    'fields'      => array(
                        'enable_schema',
                        'enable_og',
                        'enable_breadcrumbs',
                        'enable_image_seo',
                        'enable_link_tracking',
                        'enable_generator_meta',
                    ),
                ),
                array(
                    'title'       => __( 'Crawl & Sitemaps', 'wpmazic-seo-lite' ),
                    'description' => __( 'Discovery, indexing, and crawler signaling tools.', 'wpmazic-seo-lite' ),
                    'fields'      => array(
                        'enable_sitemap',
                        'enable_image_sitemap',
                        'enable_indexnow',
                        'enable_auto_search_ping',
                        'enable_llms_txt',
                    ),
                ),
                array(
                    'title'       => __( 'Redirects & Sharing', 'wpmazic-seo-lite' ),
                    'description' => __( 'Recovery tools and default social media enhancements.', 'wpmazic-seo-lite' ),
                    'fields'      => array(
                        'enable_redirects',
                        'enable_404_monitor',
                        'enable_auto_slug_redirect',
                        'enable_dynamic_og_image',
                    ),
                ),
            );

            foreach ( $feature_groups as $feature_group ) :
                ?>
                <section class="wmz-toggle-group">
                    <h3 class="wmz-section-title"><?php echo esc_html( $feature_group['title'] ); ?></h3>
                    <p class="wmz-help"><?php echo esc_html( $feature_group['description'] ); ?></p>
                    <div class="wmz-toggle-list">
                        <?php foreach ( $feature_group['fields'] as $field ) : ?>
                            <?php $default_toggle = isset( $default_settings[ $field ] ) ? (int) $default_settings[ $field ] : 1; ?>
                            <label>
                                <input type="checkbox" name="wpmazic_settings[<?php echo esc_attr( $field ); ?>]" value="1" <?php checked( isset( $settings[ $field ] ) ? (int) $settings[ $field ] : $default_toggle, 1 ); ?>>
                                <span><?php echo esc_html( $feature_labels[ $field ] ); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="wmz-card">
        <h2><?php esc_html_e( 'Search Engine Verification', 'wpmazic-seo-lite' ); ?></h2>
        <p class="wmz-subtle"><?php esc_html_e( 'Paste verification content values from webmaster tools. These values are intentionally output as public meta tags in <head>.', 'wpmazic-seo-lite' ); ?></p>
        <div class="wmz-form-grid">
            <div class="wmz-field">
                <label for="google_site_verification"><?php esc_html_e( 'Google Verification Content', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="text" id="google_site_verification" name="wpmazic_settings[google_site_verification]" value="<?php echo esc_attr( isset( $settings['google_site_verification'] ) ? $settings['google_site_verification'] : '' ); ?>">
            </div>
            <div class="wmz-field">
                <label for="bing_site_verification"><?php esc_html_e( 'Bing Verification Content', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="text" id="bing_site_verification" name="wpmazic_settings[bing_site_verification]" value="<?php echo esc_attr( isset( $settings['bing_site_verification'] ) ? $settings['bing_site_verification'] : '' ); ?>">
            </div>
            <div class="wmz-field">
                <label for="yandex_site_verification"><?php esc_html_e( 'Yandex Verification Content', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="text" id="yandex_site_verification" name="wpmazic_settings[yandex_site_verification]" value="<?php echo esc_attr( isset( $settings['yandex_site_verification'] ) ? $settings['yandex_site_verification'] : '' ); ?>">
            </div>
            <div class="wmz-field">
                <label for="baidu_site_verification"><?php esc_html_e( 'Baidu Verification Content', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="text" id="baidu_site_verification" name="wpmazic_settings[baidu_site_verification]" value="<?php echo esc_attr( isset( $settings['baidu_site_verification'] ) ? $settings['baidu_site_verification'] : '' ); ?>">
            </div>
        </div>
    </div>

    <div class="wmz-card">
        <h2><?php esc_html_e( 'Security & Bot Protection', 'wpmazic-seo-lite' ); ?></h2>
        <p class="wmz-subtle"><?php esc_html_e( 'Enable hardening controls to reduce spam crawling and basic attack surface.', 'wpmazic-seo-lite' ); ?></p>

        <div class="wmz-inline-checks tw-mt-3">
            <label><input type="checkbox" name="wpmazic_settings[enable_security_bad_bots]" value="1" <?php checked( ! empty( $settings['enable_security_bad_bots'] ) ); ?>> <?php esc_html_e( 'Bad Bot Blocker (default signatures + custom list)', 'wpmazic-seo-lite' ); ?></label>
            <label><input type="checkbox" name="wpmazic_settings[enable_security_headers]" value="1" <?php checked( ! empty( $settings['enable_security_headers'] ) ); ?>> <?php esc_html_e( 'Security Headers', 'wpmazic-seo-lite' ); ?></label>
            <label><input type="checkbox" name="wpmazic_settings[enable_security_disable_xmlrpc]" value="1" <?php checked( ! empty( $settings['enable_security_disable_xmlrpc'] ) ); ?>> <?php esc_html_e( 'Disable XML-RPC', 'wpmazic-seo-lite' ); ?></label>
            <label><input type="checkbox" name="wpmazic_settings[enable_security_hide_wp_version]" value="1" <?php checked( ! empty( $settings['enable_security_hide_wp_version'] ) ); ?>> <?php esc_html_e( 'Hide WordPress Version from Frontend Assets', 'wpmazic-seo-lite' ); ?></label>
            <label><input type="checkbox" name="wpmazic_settings[enable_security_block_author_enum]" value="1" <?php checked( ! empty( $settings['enable_security_block_author_enum'] ) ); ?>> <?php esc_html_e( 'Block Author Enumeration', 'wpmazic-seo-lite' ); ?></label>
        </div>

        <div class="wmz-form-grid tw-mt-3">
            <div class="wmz-field">
                <label for="security_bad_bots_custom"><?php esc_html_e( 'Custom Blocked Bot Signatures', 'wpmazic-seo-lite' ); ?></label>
                <textarea class="wmz-textarea" id="security_bad_bots_custom" name="wpmazic_settings[security_bad_bots_custom]" rows="6" placeholder="semrushbot&#10;ahrefsbot&#10;custom-crawler"><?php echo esc_textarea( isset( $settings['security_bad_bots_custom'] ) ? $settings['security_bad_bots_custom'] : '' ); ?></textarea>
                <p class="wmz-help"><?php esc_html_e( 'One signature per line. Case-insensitive. Matches user-agent substrings.', 'wpmazic-seo-lite' ); ?></p>
            </div>

            <div class="wmz-field">
                <label for="security_bad_bots_whitelist"><?php esc_html_e( 'Bot Whitelist Signatures', 'wpmazic-seo-lite' ); ?></label>
                <textarea class="wmz-textarea" id="security_bad_bots_whitelist" name="wpmazic_settings[security_bad_bots_whitelist]" rows="6" placeholder="googlebot&#10;bingbot"><?php echo esc_textarea( isset( $settings['security_bad_bots_whitelist'] ) ? $settings['security_bad_bots_whitelist'] : '' ); ?></textarea>
                <p class="wmz-help"><?php esc_html_e( 'Whitelist is checked first. Add trusted bots here to avoid accidental blocking.', 'wpmazic-seo-lite' ); ?></p>
            </div>
        </div>
    </div>

    <div class="wmz-card">
        <h2><?php esc_html_e( 'Title & Description Templates', 'wpmazic-seo-lite' ); ?></h2>
        <div class="wmz-form-grid">
            <div class="wmz-field">
                <label for="title_template_singular"><?php esc_html_e( 'Default Singular Title Template', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="text" id="title_template_singular" name="wpmazic_settings[title_template_singular]" value="<?php echo esc_attr( isset( $settings['title_template_singular'] ) ? $settings['title_template_singular'] : '%title% %sep% %sitename%' ); ?>">
                <p class="wmz-help"><?php esc_html_e( 'Variables: %title%, %sitename%, %sep%, %excerpt%, %primary_keyword%, %post_type%, %category%, %date%, %author%', 'wpmazic-seo-lite' ); ?></p>
            </div>
            <div class="wmz-field">
                <label for="description_template_singular"><?php esc_html_e( 'Default Singular Description Template', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="text" id="description_template_singular" name="wpmazic_settings[description_template_singular]" value="<?php echo esc_attr( isset( $settings['description_template_singular'] ) ? $settings['description_template_singular'] : '%excerpt%' ); ?>">
                <p class="wmz-help"><?php esc_html_e( 'Variables: %excerpt%, %title%, %sitename%, %primary_keyword%, %category%, %date%, %author%', 'wpmazic-seo-lite' ); ?></p>
            </div>
        </div>
    </div>

    <div class="wmz-card">
        <h2><?php esc_html_e( 'Indexing Rules', 'wpmazic-seo-lite' ); ?></h2>
        <div class="wmz-inline-checks">
            <label><input type="checkbox" name="wpmazic_settings[noindex_categories]" value="1" <?php checked( ! empty( $settings['noindex_categories'] ) ); ?>> <?php esc_html_e( 'Noindex Categories', 'wpmazic-seo-lite' ); ?></label>
            <label><input type="checkbox" name="wpmazic_settings[noindex_tags]" value="1" <?php checked( ! empty( $settings['noindex_tags'] ) ); ?>> <?php esc_html_e( 'Noindex Tags', 'wpmazic-seo-lite' ); ?></label>
            <label><input type="checkbox" name="wpmazic_settings[noindex_archives]" value="1" <?php checked( ! empty( $settings['noindex_archives'] ) ); ?>> <?php esc_html_e( 'Noindex Date/Author Archives', 'wpmazic-seo-lite' ); ?></label>
            <label><input type="checkbox" name="wpmazic_settings[noindex_attachments]" value="1" <?php checked( ! empty( $settings['noindex_attachments'] ) ); ?>> <?php esc_html_e( 'Noindex Attachment Pages', 'wpmazic-seo-lite' ); ?></label>
        </div>
        <div class="wmz-form-grid-3 tw-mt-3">
            <div class="wmz-field">
                <label for="robots_max_snippet"><?php esc_html_e( 'max-snippet', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="number" id="robots_max_snippet" name="wpmazic_settings[robots_max_snippet]" value="<?php echo esc_attr( isset( $settings['robots_max_snippet'] ) ? (int) $settings['robots_max_snippet'] : -1 ); ?>">
                <p class="wmz-help"><?php esc_html_e( '-1 allows full snippets. 0 disables snippets.', 'wpmazic-seo-lite' ); ?></p>
            </div>
            <div class="wmz-field">
                <label for="robots_max_video_preview"><?php esc_html_e( 'max-video-preview', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="number" id="robots_max_video_preview" name="wpmazic_settings[robots_max_video_preview]" value="<?php echo esc_attr( isset( $settings['robots_max_video_preview'] ) ? (int) $settings['robots_max_video_preview'] : -1 ); ?>">
                <p class="wmz-help"><?php esc_html_e( '-1 allows full video preview length.', 'wpmazic-seo-lite' ); ?></p>
            </div>
            <div class="wmz-field">
                <label for="robots_max_image_preview"><?php esc_html_e( 'max-image-preview', 'wpmazic-seo-lite' ); ?></label>
                <select class="wmz-select" id="robots_max_image_preview" name="wpmazic_settings[robots_max_image_preview]">
                    <?php
                    $image_preview = isset( $settings['robots_max_image_preview'] ) ? $settings['robots_max_image_preview'] : 'large';
                    foreach ( array( 'none', 'standard', 'large' ) as $preview_value ) :
                        ?>
                        <option value="<?php echo esc_attr( $preview_value ); ?>" <?php selected( $image_preview, $preview_value ); ?>>
                            <?php echo esc_html( $preview_value ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="wmz-help"><?php esc_html_e( 'Use "large" for better image SERP visibility.', 'wpmazic-seo-lite' ); ?></p>
            </div>
        </div>
    </div>

    <div class="wmz-card">
        <h2><?php esc_html_e( 'Sitemap Content Controls', 'wpmazic-seo-lite' ); ?></h2>
        <p class="wmz-subtle"><?php esc_html_e( 'Use simple ON/OFF switches. Turn ON custom filter, then choose Yes/No style mode and select items.', 'wpmazic-seo-lite' ); ?></p>

        <div class="tw-grid md:tw-grid-cols-2 tw-gap-4 tw-mt-3">
            <div class="tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4">
                <h3 class="wmz-section-title"><?php esc_html_e( 'Post Types', 'wpmazic-seo-lite' ); ?></h3>

                <div class="wmz-field">
                    <label>
                        <input type="checkbox" name="wpmazic_settings[sitemap_post_types_filter_enabled]" value="1" <?php checked( $sitemap_post_types_filter_enabled ); ?>>
                        <?php esc_html_e( 'Custom Filter: ON/OFF', 'wpmazic-seo-lite' ); ?>
                    </label>
                    <p class="wmz-help"><?php esc_html_e( 'OFF = all public post types (except global noindex rules).', 'wpmazic-seo-lite' ); ?></p>
                </div>

                <div class="wmz-field tw-mt-3">
                    <label for="sitemap_post_types_filter_mode"><?php esc_html_e( 'Mode', 'wpmazic-seo-lite' ); ?></label>
                    <select class="wmz-select" id="sitemap_post_types_filter_mode" name="wpmazic_settings[sitemap_post_types_filter_mode]">
                        <option value="include" <?php selected( $sitemap_post_types_filter_mode, 'include' ); ?>><?php esc_html_e( 'Yes: Include selected only', 'wpmazic-seo-lite' ); ?></option>
                        <option value="exclude" <?php selected( $sitemap_post_types_filter_mode, 'exclude' ); ?>><?php esc_html_e( 'No: Exclude selected', 'wpmazic-seo-lite' ); ?></option>
                    </select>
                </div>

                <div class="wmz-field tw-mt-3">
                    <label><?php esc_html_e( 'Select Post Types', 'wpmazic-seo-lite' ); ?></label>
                    <div class="wmz-inline-checks">
                        <?php foreach ( $sitemap_post_type_objects as $post_type_slug => $post_type_obj ) : ?>
                            <label>
                                <input type="checkbox" name="wpmazic_settings[sitemap_post_types_selected][]" value="<?php echo esc_attr( $post_type_slug ); ?>" <?php checked( in_array( $post_type_slug, $sitemap_post_types_selected, true ) ); ?>>
                                <?php echo esc_html( $post_type_obj->labels->singular_name ); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="wmz-help"><?php esc_html_e( 'Used only when Custom Filter is ON.', 'wpmazic-seo-lite' ); ?></p>
                </div>
            </div>

            <div class="tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-p-4">
                <h3 class="wmz-section-title"><?php esc_html_e( 'Taxonomies', 'wpmazic-seo-lite' ); ?></h3>

                <div class="wmz-field">
                    <label>
                        <input type="checkbox" name="wpmazic_settings[sitemap_taxonomies_filter_enabled]" value="1" <?php checked( $sitemap_taxonomies_filter_enabled ); ?>>
                        <?php esc_html_e( 'Custom Filter: ON/OFF', 'wpmazic-seo-lite' ); ?>
                    </label>
                    <p class="wmz-help"><?php esc_html_e( 'OFF = all public taxonomies (except global noindex rules).', 'wpmazic-seo-lite' ); ?></p>
                </div>

                <div class="wmz-field tw-mt-3">
                    <label for="sitemap_taxonomies_filter_mode"><?php esc_html_e( 'Mode', 'wpmazic-seo-lite' ); ?></label>
                    <select class="wmz-select" id="sitemap_taxonomies_filter_mode" name="wpmazic_settings[sitemap_taxonomies_filter_mode]">
                        <option value="include" <?php selected( $sitemap_taxonomies_filter_mode, 'include' ); ?>><?php esc_html_e( 'Yes: Include selected only', 'wpmazic-seo-lite' ); ?></option>
                        <option value="exclude" <?php selected( $sitemap_taxonomies_filter_mode, 'exclude' ); ?>><?php esc_html_e( 'No: Exclude selected', 'wpmazic-seo-lite' ); ?></option>
                    </select>
                </div>

                <div class="wmz-field tw-mt-3">
                    <label><?php esc_html_e( 'Select Taxonomies', 'wpmazic-seo-lite' ); ?></label>
                    <div class="wmz-inline-checks">
                        <?php foreach ( $sitemap_taxonomy_objects as $taxonomy_slug => $taxonomy_obj ) : ?>
                            <label>
                                <input type="checkbox" name="wpmazic_settings[sitemap_taxonomies_selected][]" value="<?php echo esc_attr( $taxonomy_slug ); ?>" <?php checked( in_array( $taxonomy_slug, $sitemap_taxonomies_selected, true ) ); ?>>
                                <?php echo esc_html( $taxonomy_obj->labels->singular_name ); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="wmz-help"><?php esc_html_e( 'Used only when Custom Filter is ON.', 'wpmazic-seo-lite' ); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="wmz-card">
        <h2><?php esc_html_e( 'Social Profiles', 'wpmazic-seo-lite' ); ?></h2>
        <div class="wmz-form-grid">
            <div class="wmz-field">
                <label for="social_facebook"><?php esc_html_e( 'Facebook URL', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="url" id="social_facebook" name="wpmazic_settings[social_facebook]" value="<?php echo esc_attr( isset( $settings['social_facebook'] ) ? $settings['social_facebook'] : '' ); ?>">
            </div>
            <div class="wmz-field">
                <label for="social_twitter"><?php esc_html_e( 'Twitter/X URL', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="url" id="social_twitter" name="wpmazic_settings[social_twitter]" value="<?php echo esc_attr( isset( $settings['social_twitter'] ) ? $settings['social_twitter'] : '' ); ?>">
            </div>
            <div class="wmz-field">
                <label for="social_instagram"><?php esc_html_e( 'Instagram URL', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="url" id="social_instagram" name="wpmazic_settings[social_instagram]" value="<?php echo esc_attr( isset( $settings['social_instagram'] ) ? $settings['social_instagram'] : '' ); ?>">
            </div>
            <div class="wmz-field">
                <label for="social_linkedin"><?php esc_html_e( 'LinkedIn URL', 'wpmazic-seo-lite' ); ?></label>
                <input class="wmz-input" type="url" id="social_linkedin" name="wpmazic_settings[social_linkedin]" value="<?php echo esc_attr( isset( $settings['social_linkedin'] ) ? $settings['social_linkedin'] : '' ); ?>">
            </div>
        </div>
    </div>

    <div class="wmz-card">
        <h2><?php esc_html_e( 'RSS SEO Content Controls', 'wpmazic-seo-lite' ); ?></h2>
        <div class="wmz-form-grid">
            <div class="wmz-field">
                <label for="rss_before_content"><?php esc_html_e( 'RSS Content Before Post', 'wpmazic-seo-lite' ); ?></label>
                <textarea class="wmz-textarea" id="rss_before_content" name="wpmazic_settings[rss_before_content]" rows="4"><?php echo esc_textarea( isset( $settings['rss_before_content'] ) ? $settings['rss_before_content'] : '' ); ?></textarea>
                <p class="wmz-help"><?php esc_html_e( 'Placeholders: %post_title%, %post_link%, %site_name%, %site_link%', 'wpmazic-seo-lite' ); ?></p>
            </div>
            <div class="wmz-field">
                <label for="rss_after_content"><?php esc_html_e( 'RSS Content After Post', 'wpmazic-seo-lite' ); ?></label>
                <textarea class="wmz-textarea" id="rss_after_content" name="wpmazic_settings[rss_after_content]" rows="4"><?php echo esc_textarea( isset( $settings['rss_after_content'] ) ? $settings['rss_after_content'] : '' ); ?></textarea>
                <p class="wmz-help"><?php esc_html_e( 'Use this to add source attribution links in feeds.', 'wpmazic-seo-lite' ); ?></p>
            </div>
        </div>
    </div>

    <div class="wmz-card">
        <h2><?php esc_html_e( 'Local SEO', 'wpmazic-seo-lite' ); ?></h2>
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
    </div>

    <div class="wmz-actions">
        <?php submit_button( __( 'Save Settings', 'wpmazic-seo-lite' ), 'primary', 'submit', false ); ?>
    </div>
</form>

<?php wpmazic_seo_admin_shell_close(); ?>
