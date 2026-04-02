<?php
/**
 * Plugin Name: WPMazic SEO Lite
 * Plugin URI:  https://wordpress.org/plugins/search/wpmazic-seo-lite/
 * Description: Lightweight SEO suite with meta tags, schema, sitemap, redirects, 404 monitor, breadcrumbs, image SEO, IndexNow, and migration tools.
 * Version:     1.0.0
 * Author:      WPMazic Team
 * Author URI:  https://wpmazic.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpmazic-seo-lite
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */ 

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// -------------------------------------------------------------------------
// Admin notice queue (keeps notices in the right place).
// -------------------------------------------------------------------------
if ( ! function_exists( 'wpmazic_seo_lite_add_notice' ) ) {
    /**
     * Queue a WordPress admin notice to be rendered inside the plugin shell.
     *
     * @param string $type    success|error|warning|info
     * @param string $message Notice message (plain text).
     * @return void
     */
    function wpmazic_seo_lite_add_notice( $type, $message ) {
        $type = sanitize_key( (string) $type );
        if ( ! in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ) {
            $type = 'info';
        }

        $message = (string) $message;
        if ( '' === trim( $message ) ) {
            return;
        }

        $queued   = isset( $GLOBALS['wpmazic_seo_lite_notices'] ) && is_array( $GLOBALS['wpmazic_seo_lite_notices'] ) ? $GLOBALS['wpmazic_seo_lite_notices'] : array();
        $queued[] = array(
            'type'    => $type,
            'message' => $message,
        );
        $GLOBALS['wpmazic_seo_lite_notices'] = $queued;
    }
}

if ( ! function_exists( 'wpmazic_seo_lite_render_notices' ) ) {
    /**
     * Render queued notices (and clear queue).
     *
     * @return void
     */
    function wpmazic_seo_lite_render_notices() {
        $queued = isset( $GLOBALS['wpmazic_seo_lite_notices'] ) && is_array( $GLOBALS['wpmazic_seo_lite_notices'] ) ? $GLOBALS['wpmazic_seo_lite_notices'] : array();
        if ( empty( $queued ) ) {
            return;
        }

        $GLOBALS['wpmazic_seo_lite_notices'] = array();
        foreach ( $queued as $notice ) {
            $type    = isset( $notice['type'] ) ? sanitize_key( (string) $notice['type'] ) : 'info';
            $message = isset( $notice['message'] ) ? (string) $notice['message'] : '';
            if ( '' === trim( $message ) ) {
                continue;
            }

            echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
        }
    }
}

if ( ! function_exists( 'wpmazic_seo_lite_is_self_activation_request' ) ) {
    /**
     * Check whether this request is activating this plugin package.
     *
     * @return bool
     */
    function wpmazic_seo_lite_is_self_activation_request() {
        if ( ! is_admin() ) {
            return false;
        }

        $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
        $plugin = isset( $_GET['plugin'] ) ? sanitize_text_field( wp_unslash( $_GET['plugin'] ) ) : '';

        return ( 'activate' === $action ) && ( plugin_basename( __FILE__ ) === $plugin );
    }
}

if ( ! function_exists( 'wpmazic_seo_safe_can_activate_plugins' ) ) {
    /**
     * Safely check activation capability after pluggable user APIs are loaded.
     *
     * @return bool
     */
    function wpmazic_seo_safe_can_activate_plugins() {
        return function_exists( 'wp_get_current_user' )
            && function_exists( 'current_user_can' )
            && current_user_can( 'activate_plugins' );
    }
}

if ( ! function_exists( 'wpmazic_seo_lite_is_pro_active' ) ) {
    /**
     * Determine whether the Pro edition is active on this site or network.
     *
     * @return bool
     */
    function wpmazic_seo_lite_is_pro_active() {
        $pro_plugins = array(
            'wpmazic-seo-pro/wpmazic-seo.php',
            'wpmazic-seo/wpmazic-seo.php',
        );

        foreach ( $pro_plugins as $pro_plugin ) {
            if ( function_exists( 'is_plugin_active' ) && is_plugin_active( $pro_plugin ) ) {
                return true;
            }
        }

        $active_plugins = (array) get_option( 'active_plugins', array() );
        foreach ( $pro_plugins as $pro_plugin ) {
            if ( in_array( $pro_plugin, $active_plugins, true ) ) {
                return true;
            }
        }

        if ( is_multisite() ) {
            foreach ( $pro_plugins as $pro_plugin ) {
                if ( function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $pro_plugin ) ) {
                    return true;
                }
            }

            $network_active_plugins = get_site_option( 'active_sitewide_plugins', array() );
            if ( is_array( $network_active_plugins ) ) {
                foreach ( $pro_plugins as $pro_plugin ) {
                    if ( isset( $network_active_plugins[ $pro_plugin ] ) ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}

// Prevent loading Lite alongside Pro to avoid shared symbol conflicts.
if ( wpmazic_seo_lite_is_pro_active() ) {
    if ( wpmazic_seo_lite_is_self_activation_request() ) {
        wp_die(
            esc_html__( 'WPMazic SEO Lite cannot be activated while WPMazic SEO Pro is active. Deactivate Pro first.', 'wpmazic-seo-lite' ),
            esc_html__( 'Plugin Conflict', 'wpmazic-seo-lite' ),
            array( 'back_link' => true, 'response' => 409 )
        );
    }

    if ( is_admin() && wpmazic_seo_safe_can_activate_plugins() ) {
        if ( ! function_exists( 'deactivate_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        deactivate_plugins( plugin_basename( __FILE__ ), true );
    }
    return;
}

if ( ! defined( 'WPMAZIC_SEO_VERSION' ) ) {
    define( 'WPMAZIC_SEO_VERSION', '1.0.0' );
}
if ( ! defined( 'WPMAZIC_SEO_DB_VERSION' ) ) {
    define( 'WPMAZIC_SEO_DB_VERSION', '2.8.0' );
}
if ( ! defined( 'WPMAZIC_SEO_EDITION' ) ) {
    define( 'WPMAZIC_SEO_EDITION', 'lite' );
}
if ( ! defined( 'WPMAZIC_SEO_LITE_MAX_REDIRECTS' ) ) {
    define( 'WPMAZIC_SEO_LITE_MAX_REDIRECTS', 9999 );
}
if ( ! defined( 'WPMAZIC_SEO_LITE_MAX_404_ROWS' ) ) {
    define( 'WPMAZIC_SEO_LITE_MAX_404_ROWS', 9999 );
}
if ( ! defined( 'WPMAZIC_SEO_LITE_MAX_RANK_KEYWORDS' ) ) {
    define( 'WPMAZIC_SEO_LITE_MAX_RANK_KEYWORDS', 9999 );
}
if ( ! defined( 'WPMAZIC_SEO_FILE' ) ) {
    define( 'WPMAZIC_SEO_FILE', __FILE__ );
}
if ( ! defined( 'WPMAZIC_SEO_PATH' ) ) {
    define( 'WPMAZIC_SEO_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WPMAZIC_SEO_URL' ) ) {
    define( 'WPMAZIC_SEO_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'WPMAZIC_SEO_BASENAME' ) ) {
    define( 'WPMAZIC_SEO_BASENAME', plugin_basename( __FILE__ ) );
}

/**
 * Settings helper.
 *
 * @return array
 */
function wpmazic_seo_get_settings() {
    return get_option( 'wpmazic_settings', array() );
}

/**
 * Whether this WordPress.org package should enforce Lite-only restrictions.
 *
 * @return bool
 */
function wpmazic_seo_is_lite() {
    // The WordPress.org build must keep all bundled features available.
    return false;
}

/**
 * Legacy compatibility shim for older integrations.
 *
 * The WordPress.org package no longer enforces feature caps.
 *
 * @param string $feature Limit key.
 * @return int
 */
function wpmazic_seo_lite_limit( $feature ) {
    $limit = 0;

    /**
     * Filter compatibility limits for legacy callers.
     *
     * @param int    $limit   Current limit.
     * @param string $feature Limit key.
     */
    return (int) apply_filters( 'wpmazic_seo_lite_limit', $limit, $feature );
}

/**
 * Check if a named feature is available in this edition.
 *
 * @param string $feature Feature key.
 * @return bool
 */
function wpmazic_seo_is_feature_available( $feature ) {
    // Compatibility helper retained for bundled Lite features.
    return true;
}

/**
 * Default settings.
 *
 * @return array
 */
function wpmazic_seo_get_default_settings() {
    return array(
        'site_name'            => get_bloginfo( 'name' ),
        'separator'            => '-',
        'enable_sitemap'       => 1,
        'enable_schema'        => 1,
        'enable_og'            => 1,
        'enable_breadcrumbs'   => 1,
        'enable_redirects'     => 1,
        'enable_404_monitor'   => 1,
        'enable_image_seo'     => 1,
        'enable_indexnow'      => 0,
        'enable_link_tracking' => 1,
        'enable_generator_meta' => 0,
        'enable_security_bad_bots' => 0,
        'enable_security_headers' => 0,
        'enable_security_disable_xmlrpc' => 0,
        'enable_security_hide_wp_version' => 0,
        'enable_security_block_author_enum' => 0,
        'security_bad_bots_custom' => '',
        'security_bad_bots_whitelist' => '',
        'enable_llms_txt'      => 1,
        'enable_image_sitemap' => 1,
        'sitemap_post_types_filter_enabled' => 0,
        'sitemap_post_types_filter_mode' => 'exclude',
        'sitemap_post_types_selected' => array(),
        'sitemap_post_types_include' => array(),
        'sitemap_post_types_exclude' => array(),
        'sitemap_taxonomies_filter_enabled' => 0,
        'sitemap_taxonomies_filter_mode' => 'exclude',
        'sitemap_taxonomies_selected' => array(),
        'sitemap_taxonomies_include' => array(),
        'sitemap_taxonomies_exclude' => array(),
        'enable_auto_slug_redirect' => 1,
        'google_site_verification' => '',
        'bing_site_verification' => '',
        'yandex_site_verification' => '',
        'baidu_site_verification' => '',
        'ga4_measurement_id' => '',
        'enable_ga4_tracking' => 0,
        'enable_dynamic_og_image' => 1,
        'enable_auto_search_ping' => 0,
        'generator_meta_text' => 'WPMazic SEO',
        'title_template_singular' => '%title% %sep% %sitename%',
        'description_template_singular' => '%excerpt%',
        'rss_before_content'   => '',
        'rss_after_content'    => '',
        'robots_max_snippet'   => -1,
        'robots_max_video_preview' => -1,
        'robots_max_image_preview' => 'large',
        'indexnow_api_key'     => wp_generate_password( 32, false ),
        'breadcrumb_separator' => '/',
        'breadcrumb_home_text' => 'Home',
    );
}

/**
 * Activation hook.
 */
function wpmazic_seo_activate( $show_wizard = true ) {
    global $wpdb;

    if ( ! function_exists( 'deactivate_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if ( wpmazic_seo_lite_is_pro_active() ) {
        deactivate_plugins( plugin_basename( __FILE__ ), true );
        wp_die(
            esc_html__( 'WPMazic SEO Lite cannot be activated while WPMazic SEO Pro is active. Deactivate Pro first.', 'wpmazic-seo-lite' ),
            esc_html__( 'Plugin Conflict', 'wpmazic-seo-lite' ),
            array( 'back_link' => true, 'response' => 409 )
        );
    }

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset  = $wpdb->get_charset_collate();
    $defaults = wpmazic_seo_get_default_settings();
    $existing = wpmazic_seo_get_settings();

    if ( empty( $existing ) ) {
        add_option( 'wpmazic_settings', $defaults );
    } else {
        update_option( 'wpmazic_settings', array_merge( $defaults, $existing ) );
    }

    $table_redirects = $wpdb->prefix . 'wpmazic_redirects';
    dbDelta( "CREATE TABLE {$table_redirects} (
        id          bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        source      varchar(500)        NOT NULL DEFAULT '',
        target      varchar(500)        NOT NULL DEFAULT '',
        type        smallint(3)         NOT NULL DEFAULT 301,
        hits        bigint(20) unsigned NOT NULL DEFAULT 0,
        status      varchar(20)         NOT NULL DEFAULT 'active',
        created_at  datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY source_idx (source(191)),
        KEY status_idx (status)
    ) {$charset};" );

    $table_404 = $wpdb->prefix . 'wpmazic_404';
    dbDelta( "CREATE TABLE {$table_404} (
        id          bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        url         varchar(500)        NOT NULL DEFAULT '',
        referer     varchar(500)        NOT NULL DEFAULT '',
        user_agent  varchar(500)        NOT NULL DEFAULT '',
        ip_address  varchar(45)         NOT NULL DEFAULT '',
        hits        int(11) unsigned    NOT NULL DEFAULT 1,
        last_hit    datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_at  datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY url_idx (url(191)),
        KEY last_hit_idx (last_hit)
    ) {$charset};" );

    $table_links = $wpdb->prefix . 'wpmazic_links';
    dbDelta( "CREATE TABLE {$table_links} (
        id             bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        post_id        bigint(20) unsigned NOT NULL DEFAULT 0,
        target_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
        anchor_text    varchar(500)        NOT NULL DEFAULT '',
        url            varchar(500)        NOT NULL DEFAULT '',
        type           varchar(20)         NOT NULL DEFAULT 'internal',
        created_at     datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY post_id_idx (post_id),
        KEY target_post_id_idx (target_post_id),
        KEY type_idx (type)
    ) {$charset};" );

    $table_indexnow = $wpdb->prefix . 'wpmazic_indexnow';
    dbDelta( "CREATE TABLE {$table_indexnow} (
        id           bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        url          varchar(500)        NOT NULL DEFAULT '',
        status       varchar(50)         NOT NULL DEFAULT 'pending',
        response     text,
        submitted_at datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY status_idx (status),
        KEY submitted_at_idx (submitted_at)
    ) {$charset};" );

    update_option( 'wpmazic_db_version', WPMAZIC_SEO_DB_VERSION );
    if ( $show_wizard ) {
        update_option( 'wpmazic_show_migration_wizard', 1 );
    }
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wpmazic_seo_activate' );

register_deactivation_hook(
    __FILE__,
    function () {
        wp_clear_scheduled_hook( 'wpmazic_seo_weekly_email_report' );
        flush_rewrite_rules();
    }
);

add_action(
    'plugins_loaded',
    function () {
        $installed_db = get_option( 'wpmazic_db_version', '0' );
        if ( version_compare( $installed_db, WPMAZIC_SEO_DB_VERSION, '<' ) ) {
            wpmazic_seo_activate( false );
        }
    }
);

require_once WPMAZIC_SEO_PATH . 'includes/class-security.php';
require_once WPMAZIC_SEO_PATH . 'includes/class-meta-tags.php';
require_once WPMAZIC_SEO_PATH . 'includes/class-sitemap.php';
require_once WPMAZIC_SEO_PATH . 'includes/class-schema.php';

$wpmazic_feature_files = array(
    'includes/class-redirects.php',
    'includes/class-404-monitor.php',
    'includes/class-breadcrumbs.php',
    'includes/class-image-seo.php',
    'includes/class-indexnow.php',
    'includes/class-internal-links.php',
    'includes/class-robots-txt.php',
    'includes/class-rss-seo.php',
    'includes/class-llms-txt.php',
    'includes/class-author-seo.php',
    'includes/class-migration.php',
    'includes/class-image-sitemap.php',
    'includes/class-verification.php',
    'includes/class-search-ping.php',
    'includes/class-dynamic-og.php',
);

foreach ( $wpmazic_feature_files as $wpmazic_file ) {
    $wpmazic_path = WPMAZIC_SEO_PATH . $wpmazic_file;
    if ( file_exists( $wpmazic_path ) ) {
        require_once $wpmazic_path;
    }
}

if ( is_admin() ) {
    require_once WPMAZIC_SEO_PATH . 'admin/class-admin.php';
    require_once WPMAZIC_SEO_PATH . 'admin/class-metabox.php';
}

add_action(
    'init',
    function () {
        load_plugin_textdomain( 'wpmazic-seo-lite', false, dirname( WPMAZIC_SEO_BASENAME ) . '/languages' );

        $settings = wpmazic_seo_get_settings();

        if ( class_exists( 'WPMazic_Security' ) ) {
            new WPMazic_Security();
        }

        new WPMazic_Meta_Tags();
        new WPMazic_Sitemap();
        new WPMazic_Schema();

        if ( class_exists( 'WPMazic_Redirects' ) && ( ! isset( $settings['enable_redirects'] ) || ! empty( $settings['enable_redirects'] ) ) ) {
            new WPMazic_Redirects();
        }

        if ( class_exists( 'WPMazic_Monitor_404' ) && ( ! isset( $settings['enable_404_monitor'] ) || ! empty( $settings['enable_404_monitor'] ) ) ) {
            new WPMazic_Monitor_404();
        }

        if ( class_exists( 'WPMazic_Breadcrumbs' ) && ( ! isset( $settings['enable_breadcrumbs'] ) || ! empty( $settings['enable_breadcrumbs'] ) ) ) {
            new WPMazic_Breadcrumbs();
        }

        if ( class_exists( 'WPMazic_Image_SEO' ) && ( ! isset( $settings['enable_image_seo'] ) || ! empty( $settings['enable_image_seo'] ) ) ) {
            new WPMazic_Image_SEO();
        }

        if ( class_exists( 'WPMazic_IndexNow' ) && ( ! isset( $settings['enable_indexnow'] ) || ! empty( $settings['enable_indexnow'] ) ) ) {
            new WPMazic_IndexNow();
        }

        if ( class_exists( 'WPMazic_Internal_Links' ) && ( ! isset( $settings['enable_link_tracking'] ) || ! empty( $settings['enable_link_tracking'] ) ) ) {
            new WPMazic_Internal_Links();
        }

        if ( class_exists( 'WPMazic_Robots_Txt' ) ) {
            new WPMazic_Robots_Txt();
        }

        if ( class_exists( 'WPMazic_RSS_SEO' ) ) {
            new WPMazic_RSS_SEO();
        }

        if ( class_exists( 'WPMazic_LLMS_Txt' ) && ( ! isset( $settings['enable_llms_txt'] ) || ! empty( $settings['enable_llms_txt'] ) ) ) {
            new WPMazic_LLMS_Txt();
        }

        if ( class_exists( 'WPMazic_Author_SEO' ) ) {
            new WPMazic_Author_SEO();
        }

        if ( class_exists( 'WPMazic_Image_Sitemap' ) ) {
            new WPMazic_Image_Sitemap();
        }

        if ( class_exists( 'WPMazic_Verification' ) ) {
            new WPMazic_Verification();
        }

        if ( class_exists( 'WPMazic_Search_Ping' ) ) {
            new WPMazic_Search_Ping();
        }

        if ( class_exists( 'WPMazic_Dynamic_OG' ) ) {
            new WPMazic_Dynamic_OG();
        }
    }
);

if ( is_admin() ) {
    add_action(
        'init',
        function () {
            new WPMazic_Admin();
            new WPMazic_Metabox();
        }
    );

    add_action(
        'admin_menu',
        function () {
            add_menu_page(
                __( 'WPMazic SEO', 'wpmazic-seo-lite' ),
                __( 'WPMazic SEO', 'wpmazic-seo-lite' ),
                'manage_options',
                'wpmazic-seo',
                'wpmazic_seo_render_dashboard_page',
                'dashicons-chart-area',
                58
            );

            $pages = wpmazic_seo_admin_pages();

            foreach ( $pages as $slug => $title ) {
                if ( 'dashboard' === $slug ) {
                    // Top-level menu already renders dashboard; avoid duplicate callback/render.
                    continue;
                }

                $menu_slug = ( 'dashboard' === $slug ) ? 'wpmazic-seo' : 'wpmazic-seo-' . $slug;
                add_submenu_page(
                    'wpmazic-seo',
                    $title,
                    $title,
                    'manage_options',
                    $menu_slug,
                    function () use ( $slug ) {
                        wpmazic_seo_render_admin_template( $slug . '.php' );
                    }
                );
            }

            add_submenu_page(
                'wpmazic-seo',
                __( 'WPMazic SEO Migration Wizard', 'wpmazic-seo-lite' ),
                __( 'Migration Wizard', 'wpmazic-seo-lite' ),
                'manage_options',
                'wpmazic-seo-migration-wizard',
                function () {
                    wpmazic_seo_render_admin_template( 'migration-wizard.php' );
                }
            );
            remove_submenu_page( 'wpmazic-seo', 'wpmazic-seo-migration-wizard' );
        }
    );

    add_action(
        'admin_init',
        function () {
            if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
                return;
            }

            if ( ! get_option( 'wpmazic_show_migration_wizard' ) ) {
                return;
            }

            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $page = isset( $_GET['page'] ) ? sanitize_key( sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) : '';
            if ( 'wpmazic-seo-migration-wizard' === $page ) {
                return;
            }

            delete_option( 'wpmazic_show_migration_wizard' );
            wp_safe_redirect( admin_url( 'admin.php?page=wpmazic-seo-migration-wizard' ) );
            exit;
        }
    );

    add_action(
        'admin_enqueue_scripts',
        function ( $hook ) {
            $hook = (string) $hook;
            if ( '' === $hook || false === strpos( (string) $hook, 'wpmazic-seo' ) ) {
                return;
            }

            wp_enqueue_style(
                'wpmazic-seo-lite-admin-style',
                WPMAZIC_SEO_URL . 'assets/css/admin.css',
                array(),
                WPMAZIC_SEO_VERSION
            );

            wp_enqueue_script(
                'wpmazic-seo-lite-admin-script',
                WPMAZIC_SEO_URL . 'assets/js/admin.js',
                array( 'jquery' ),
                WPMAZIC_SEO_VERSION,
                true
            );

            wp_localize_script(
                'wpmazic-seo-lite-admin-script',
                'wpmazicSeo',
                array(
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce'   => wp_create_nonce( 'wpmazic_nonce' ),
                )
            );

            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $page = isset( $_GET['page'] ) ? sanitize_key( sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) : '';
            if ( in_array( $page, array( 'wpmazic-seo', 'wpmazic-seo-dashboard' ), true ) ) {
                wp_enqueue_script(
                    'wpmazic-seo-lite-chartjs',
                    WPMAZIC_SEO_URL . 'assets/vendor/chart.umd.min.js',
                    array(),
                    '4.5.1',
                    true
                );
            }
        }
    );

    add_filter(
        'admin_body_class',
        function ( $classes ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $page = isset( $_GET['page'] ) ? sanitize_key( sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) : '';
            if ( '' !== $page && false !== strpos( (string) $page, 'wpmazic-seo' ) ) {
                $classes .= ' wpmazic-seo-admin';
            }
            return $classes;
        }
    );
}

/**
 * Dashboard page callback.
 */
function wpmazic_seo_render_dashboard_page() {
    wpmazic_seo_render_admin_template( 'dashboard.php' );
}

/**
 * Return admin page slug => label map.
 *
 * @return array
 */
function wpmazic_seo_admin_pages() {
    $pages = array(
        'dashboard'   => __( 'Dashboard', 'wpmazic-seo-lite' ),
        'settings'    => __( 'Settings', 'wpmazic-seo-lite' ),
        'analysis'    => __( 'SEO Analysis', 'wpmazic-seo-lite' ),
        'redirects'   => __( 'Redirects', 'wpmazic-seo-lite' ),
        '404-monitor' => __( '404 Monitor', 'wpmazic-seo-lite' ),
        'tools'       => __( 'Tools', 'wpmazic-seo-lite' ),
        'bulk-editor' => __( 'Bulk Editor', 'wpmazic-seo-lite' ),
        'local-seo'   => __( 'Local SEO', 'wpmazic-seo-lite' ),
    );

    return $pages;
}

/**
 * Resolve current page slug.
 *
 * @return string
 */
function wpmazic_seo_admin_current_slug() {
    $page = isset( $_GET['page'] ) && is_scalar( $_GET['page'] )
        ? sanitize_key( wp_unslash( (string) $_GET['page'] ) )
        : 'wpmazic-seo';
    if ( 'wpmazic-seo' === $page ) {
        return 'dashboard';
    }
    if ( 0 === strpos( (string) $page, 'wpmazic-seo-' ) ) {
        return substr( (string) $page, strlen( 'wpmazic-seo-' ) );
    }
    return 'dashboard';
}

/**
 * Build admin URL for plugin page.
 *
 * @param string $slug Page slug key.
 * @return string
 */
function wpmazic_seo_admin_page_url( $slug ) {
    $menu_slug = ( 'dashboard' === $slug ) ? 'wpmazic-seo' : 'wpmazic-seo-' . $slug;
    return admin_url( 'admin.php?page=' . $menu_slug );
}

/**
 * Return grouped navigation map for the admin shell.
 *
 * @return array
 */
function wpmazic_seo_admin_shell_nav_groups() {
    return array(
        __( 'Overview', 'wpmazic-seo-lite' ) => array(
            'dashboard',
            'analysis',
        ),
        __( 'Optimization', 'wpmazic-seo-lite' ) => array(
            'settings',
            'bulk-editor',
            'local-seo',
        ),
        __( 'Monitoring', 'wpmazic-seo-lite' ) => array(
            'redirects',
            '404-monitor',
        ),
        __( 'Administration', 'wpmazic-seo-lite' ) => array(
            'tools',
        ),
    );
}

/**
 * Render page shell and grouped navigation.
 *
 * @param string $title Page title.
 * @param string $description Optional subtitle.
 */
function wpmazic_seo_admin_shell_open( $title, $description = '' ) {
    $pages       = wpmazic_seo_admin_pages();
    $current     = wpmazic_seo_admin_current_slug();
    $grouped_nav = wpmazic_seo_admin_shell_nav_groups();
    $brand_logo  = '';

    if ( 'migration-wizard' === $current ) {
        $pages['migration-wizard'] = __( 'Migration Wizard', 'wpmazic-seo-lite' );
    }

    $assigned = array();
    foreach ( $grouped_nav as $group_label => $slugs ) {
        $valid = array();
        foreach ( $slugs as $slug ) {
            if ( isset( $pages[ $slug ] ) ) {
                $valid[]   = $slug;
                $assigned[] = $slug;
            }
        }
        $grouped_nav[ $group_label ] = $valid;
    }

    $unassigned = array();
    foreach ( $pages as $slug => $label ) {
        if ( ! in_array( $slug, $assigned, true ) ) {
            $unassigned[] = $slug;
        }
    }
    if ( ! empty( $unassigned ) ) {
        $grouped_nav[ __( 'More', 'wpmazic-seo-lite' ) ] = $unassigned;
    }

    $docs_url    = 'https://wpmazic.com/wpmazic-seo-lite/';
    $support_url = 'https://wpmazic.com/my-account/support-tickets';
    $logo_candidates = array(
        'assets/images/brand-logo-48.png',
        'assets/images/brand-logo-64.png',
        'assets/images/brand-logo.png',
        'assets/images/brand-logo.svg',
    );
    foreach ( $logo_candidates as $logo_rel_path ) {
        if ( file_exists( WPMAZIC_SEO_PATH . $logo_rel_path ) ) {
            $brand_logo = WPMAZIC_SEO_URL . $logo_rel_path;
            break;
        }
    }
    ?>
    <div class="wrap">
        <div class="wmz-shell tw-font-sans tw-mt-2">
            <div class="wmz-shell-layout">
                <aside class="wmz-shell-aside">
                    <div class="wmz-shell-aside-header">
                        <?php if ( ! empty( $brand_logo ) ) : ?>
                            <span class="wmz-shell-logo wmz-shell-logo-has-image" style="background:transparent !important;border:0 !important;box-shadow:none !important;padding:0 !important;border-radius:0 !important;">
                                <img src="<?php echo esc_url( $brand_logo ); ?>" alt="<?php esc_attr_e( 'Brand Logo', 'wpmazic-seo-lite' ); ?>" class="wmz-shell-logo-image" style="max-width:48px !important;max-height:48px !important;width:auto !important;height:auto !important;border:0 !important;box-shadow:none !important;outline:0 !important;display:block !important;filter:none !important;background:transparent !important;" />
                            </span>
                        <?php else : ?>
                            <span class="wmz-shell-logo">W</span>
                        <?php endif; ?>
                        <div class="wmz-shell-brand-copy">
                            <p class="wmz-shell-brand-title"><?php echo ( defined( 'WPMAZIC_SEO_EDITION' ) && 'lite' === WPMAZIC_SEO_EDITION ) ? esc_html__( 'WPMazic SEO Lite', 'wpmazic-seo-lite' ) : esc_html__( 'WPMazic SEO', 'wpmazic-seo-lite' ); ?></p>
                            <p class="wmz-shell-brand-subtitle"><?php esc_html_e( 'Search growth suite for WordPress', 'wpmazic-seo-lite' ); ?></p>
                            <span class="wmz-shell-version"><?php echo esc_html( 'v' . WPMAZIC_SEO_VERSION ); ?></span>
                        </div>
                    </div>

                    <nav class="wmz-shell-nav" aria-label="<?php esc_attr_e( 'WPMazic SEO navigation', 'wpmazic-seo-lite' ); ?>">
                        <?php
                        $index = 0;
                        foreach ( $grouped_nav as $group_label => $slugs ) :
                            if ( empty( $slugs ) ) {
                                continue;
                            }
                            $index++;
                            $panel_id         = 'wmz-shell-group-' . $index;
                            $contains_current = in_array( $current, $slugs, true );
                            ?>
                            <section class="wmz-shell-group">
                                <button
                                    type="button"
                                    class="wmz-shell-group-toggle"
                                    data-wmz-group-toggle
                                    data-panel="<?php echo esc_attr( $panel_id ); ?>"
                                    aria-expanded="<?php echo $contains_current ? 'true' : 'false'; ?>"
                                >
                                    <span><?php echo esc_html( $group_label ); ?></span>
                                    <span class="wmz-shell-group-arrow" aria-hidden="true">&#9662;</span>
                                </button>
                                <ul
                                    id="<?php echo esc_attr( $panel_id ); ?>"
                                    class="wmz-shell-group-list"
                                    <?php echo $contains_current ? '' : 'hidden'; ?>
                                >
                                    <?php foreach ( $slugs as $slug ) : ?>
                                        <?php
                                        $label   = isset( $pages[ $slug ] ) ? $pages[ $slug ] : ucfirst( str_replace( '-', ' ', (string) $slug ) );
                                        $url     = ( 'migration-wizard' === $slug )
                                            ? admin_url( 'admin.php?page=wpmazic-seo-migration-wizard' )
                                            : wpmazic_seo_admin_page_url( $slug );
                                        $classes = ( $slug === $current ) ? 'wmz-shell-nav-link is-active' : 'wmz-shell-nav-link';
                                        ?>
                                        <li>
                                            <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $classes ); ?>">
                                                <?php echo esc_html( $label ); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endforeach; ?>
                    </nav>
                </aside>

                <main class="wmz-shell-main">
                    <div class="wmz-shell-actionbar">
                        <div class="wmz-shell-actionbar-state">
                            <span class="wmz-shell-state-dot" aria-hidden="true"></span>
                            <span><?php esc_html_e( 'Ready', 'wpmazic-seo-lite' ); ?></span>
                        </div>
                        <div class="wmz-shell-actionbar-actions">
                            <a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary">
                                <?php esc_html_e( 'Docs', 'wpmazic-seo-lite' ); ?>
                            </a>
                            <a href="<?php echo esc_url( $support_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary">
                                <?php esc_html_e( 'Support', 'wpmazic-seo-lite' ); ?>
                            </a>
                            <button type="button" class="button button-primary wmz-shell-save-trigger">
                                <?php esc_html_e( 'Save This Page', 'wpmazic-seo-lite' ); ?>
                            </button>
                        </div>
                    </div>

                    <div class="wmz-shell-page-head">
                        <h1><?php echo esc_html( $title ); ?></h1>
                        <?php if ( ! empty( $description ) ) : ?>
                            <p><?php echo esc_html( $description ); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="wmz-shell-page-content">
                        <div id="wmz-shell-global-notices" class="wmz-shell-global-notices"></div>
                        <?php wpmazic_seo_lite_render_notices(); ?>
                        <div class="wmz-shell-notice">
                            <?php esc_html_e( 'Work is saved through each page form actions. Use the page save buttons for exact updates.', 'wpmazic-seo-lite' ); ?>
                        </div>
                <?php
}

/**
 * Close page shell wrapper.
 */
function wpmazic_seo_admin_shell_close() {
    echo '</div></main></div></div></div>';
}

/**
 * Template renderer.
 *
 * SECURITY FIXES:
 * - Added template whitelist to prevent path traversal attacks.
 * - Validates template name against allowed list before inclusion.
 * - Uses basename() to strip any directory traversal attempts.
 *
 * @param string $template Template file under templates/admin/.
 */
function wpmazic_seo_render_admin_template( $template ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You are not allowed to access this page.', 'wpmazic-seo-lite' ) );
    }

    // Security: Whitelist of allowed template files.
    $allowed_templates = array(
        '404-monitor.php',
        'analysis.php',
        'bulk-editor.php',
        'dashboard.php',
        'local-seo.php',
        'migration-wizard.php',
        'redirects.php',
        'settings.php',
        'tools.php',
    );

    // Security: Sanitize template name - use basename to strip paths.
    $template_file = basename( sanitize_file_name( $template ) );

    // Security: Validate against whitelist.
    if ( ! in_array( $template_file, $allowed_templates, true ) ) {
        wp_die( esc_html__( 'Invalid template requested.', 'wpmazic-seo-lite' ), '', array( 'response' => 403 ) );
    }

    $path = WPMAZIC_SEO_PATH . 'templates/admin/' . $template_file;

    // Security: Final path validation - ensure file exists and is within plugin directory.
    $real_path = realpath( $path );
    $admin_dir = realpath( WPMAZIC_SEO_PATH . 'templates/admin/' );

    if ( false === $real_path || false === $admin_dir || 0 !== strpos( (string) $real_path, (string) $admin_dir ) ) {
        wp_die( esc_html__( 'Template not found.', 'wpmazic-seo-lite' ), '', array( 'response' => 404 ) );
    }

    if ( file_exists( $real_path ) ) {
        // SECURITY: Do NOT use extract() here - let templates access data via explicit variables only.
        include $real_path;
        return;
    }

    echo '<div class="wrap"><h1>' . esc_html__( 'WPMazic SEO', 'wpmazic-seo-lite' ) . '</h1><p>' . esc_html__( 'Template not found.', 'wpmazic-seo-lite' ) . '</p></div>';
}
