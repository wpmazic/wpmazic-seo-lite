<?php
/**
 * WPMazic SEO - Admin Class
 *
 * Handles AJAX endpoints, settings sanitization, and admin-side logic.
 * Menu registration and page rendering are handled in the main plugin file.
 *
 * @package WPMazic_SEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMazic_Admin
{

    /**
     * Constructor — register hooks.
     */
    public function __construct()
    {
        // Settings API
        add_action('admin_init', array($this, 'register_settings'));

        // AJAX: core
        add_action('wp_ajax_wpmazic_save_settings', array($this, 'save_settings'));
        add_action('wp_ajax_wpmazic_get_stats', array($this, 'get_stats'));

        // AJAX: bulk editor
        add_action('wp_ajax_wpmazic_bulk_save', array($this, 'bulk_save'));

        // AJAX: redirects / 404
        add_action('wp_ajax_wpmazic_delete_redirect', array($this, 'delete_redirect'));
        add_action('wp_ajax_wpmazic_delete_404', array($this, 'delete_404'));

        // AJAX: tools
        add_action('wp_ajax_wpmazic_save_robots', array($this, 'save_robots'));
        add_action('wp_ajax_wpmazic_optimize_db', array($this, 'optimize_db'));
        add_action('wp_ajax_wpmazic_clear_cache', array($this, 'clear_cache'));
    }

    // ------------------------------------------------------------------
    // Settings API
    // ------------------------------------------------------------------

    /**
     * Register the plugin settings with WordPress.
     */
    public function register_settings()
    {
        register_setting('wpmazic_settings', 'wpmazic_settings', array($this, 'sanitize_settings'));
    }

    /**
     * Sanitize every known settings field.
     *
     * @param  array $settings Raw settings array.
     * @return array           Sanitized settings array.
     */
    public function sanitize_settings($settings)
    {
        if (!is_array($settings)) {
            return array();
        }

        $clean = array();

        // --- General -------------------------------------------------------
        if (isset($settings['site_name'])) {
            $clean['site_name'] = WPMazic_Security::sanitize_text($settings['site_name']);
        }
        if (isset($settings['separator'])) {
            $clean['separator'] = WPMazic_Security::sanitize_text($settings['separator']);
        }
        if (isset($settings['twitter_site'])) {
            $clean['twitter_site'] = WPMazic_Security::sanitize_text($settings['twitter_site']);
        }
        if (isset($settings['title_template_singular'])) {
            $clean['title_template_singular'] = WPMazic_Security::sanitize_text($settings['title_template_singular']);
        }
        if (isset($settings['description_template_singular'])) {
            $clean['description_template_singular'] = WPMazic_Security::sanitize_text($settings['description_template_singular']);
        }
        if (isset($settings['rss_before_content'])) {
            $clean['rss_before_content'] = WPMazic_Security::sanitize_textarea($settings['rss_before_content']);
        }
        if (isset($settings['rss_after_content'])) {
            $clean['rss_after_content'] = WPMazic_Security::sanitize_textarea($settings['rss_after_content']);
        }
        if (isset($settings['generator_meta_text'])) {
            $clean['generator_meta_text'] = WPMazic_Security::sanitize_text($settings['generator_meta_text']);
        }
        if (isset($settings['google_site_verification'])) {
            $clean['google_site_verification'] = WPMazic_Security::sanitize_text($settings['google_site_verification']);
        }
        if (isset($settings['bing_site_verification'])) {
            $clean['bing_site_verification'] = WPMazic_Security::sanitize_text($settings['bing_site_verification']);
        }
        if (isset($settings['yandex_site_verification'])) {
            $clean['yandex_site_verification'] = WPMazic_Security::sanitize_text($settings['yandex_site_verification']);
        }
        if (isset($settings['baidu_site_verification'])) {
            $clean['baidu_site_verification'] = WPMazic_Security::sanitize_text($settings['baidu_site_verification']);
        }
        if (isset($settings['ga4_measurement_id'])) {
            $clean['ga4_measurement_id'] = strtoupper(WPMazic_Security::sanitize_text($settings['ga4_measurement_id']));
        }
        if (isset($settings['security_bad_bots_custom'])) {
            $clean['security_bad_bots_custom'] = WPMazic_Security::sanitize_textarea($settings['security_bad_bots_custom']);
        }
        if (isset($settings['security_bad_bots_whitelist'])) {
            $clean['security_bad_bots_whitelist'] = WPMazic_Security::sanitize_textarea($settings['security_bad_bots_whitelist']);
        }

        $allowed_public_post_types = array_values(
            get_post_types(
                array(
                    'public' => true,
                ),
                'names'
            )
        );
        $allowed_public_taxonomies = array_values(
            get_taxonomies(
                array(
                    'public' => true,
                ),
                'names'
            )
        );

        $clean['sitemap_post_types_include'] = $this->sanitize_slug_array(
            isset($settings['sitemap_post_types_include']) ? $settings['sitemap_post_types_include'] : array(),
            $allowed_public_post_types
        );
        $clean['sitemap_post_types_exclude'] = $this->sanitize_slug_array(
            isset($settings['sitemap_post_types_exclude']) ? $settings['sitemap_post_types_exclude'] : array(),
            $allowed_public_post_types
        );
        $clean['sitemap_post_types_selected'] = $this->sanitize_slug_array(
            isset($settings['sitemap_post_types_selected']) ? $settings['sitemap_post_types_selected'] : array(),
            $allowed_public_post_types
        );
        $clean['sitemap_taxonomies_include'] = $this->sanitize_slug_array(
            isset($settings['sitemap_taxonomies_include']) ? $settings['sitemap_taxonomies_include'] : array(),
            $allowed_public_taxonomies
        );
        $clean['sitemap_taxonomies_exclude'] = $this->sanitize_slug_array(
            isset($settings['sitemap_taxonomies_exclude']) ? $settings['sitemap_taxonomies_exclude'] : array(),
            $allowed_public_taxonomies
        );
        $clean['sitemap_taxonomies_selected'] = $this->sanitize_slug_array(
            isset($settings['sitemap_taxonomies_selected']) ? $settings['sitemap_taxonomies_selected'] : array(),
            $allowed_public_taxonomies
        );

        $clean['sitemap_post_types_filter_mode'] = isset($settings['sitemap_post_types_filter_mode']) && 'include' === sanitize_key((string) $settings['sitemap_post_types_filter_mode'])
            ? 'include'
            : 'exclude';
        $clean['sitemap_taxonomies_filter_mode'] = isset($settings['sitemap_taxonomies_filter_mode']) && 'include' === sanitize_key((string) $settings['sitemap_taxonomies_filter_mode'])
            ? 'include'
            : 'exclude';

        // --- Feature toggles (cast to 0 | 1) ------------------------------
        $toggles = array(
            'enable_sitemap',
            'enable_schema',
            'enable_og',
            'enable_breadcrumbs',
            'enable_redirects',
            'enable_404_monitor',
            'enable_image_seo',
            'enable_indexnow',
            'enable_link_tracking',
            'enable_generator_meta',
            'enable_llms_txt',
            'enable_image_sitemap',
            'enable_auto_slug_redirect',
            'enable_dynamic_og_image',
            'enable_auto_search_ping',
            'enable_security_bad_bots',
            'enable_security_headers',
            'enable_security_disable_xmlrpc',
            'enable_security_hide_wp_version',
            'enable_security_block_author_enum',
            'sitemap_post_types_filter_enabled',
            'sitemap_taxonomies_filter_enabled',
        );
        foreach ($toggles as $toggle) {
            $clean[$toggle] = isset($settings[$toggle]) && absint($settings[$toggle]) ? 1 : 0;
        }

        // --- Breadcrumbs ---------------------------------------------------
        if (isset($settings['breadcrumb_separator'])) {
            $clean['breadcrumb_separator'] = WPMazic_Security::sanitize_text($settings['breadcrumb_separator']);
        }
        if (isset($settings['breadcrumb_home_text'])) {
            $clean['breadcrumb_home_text'] = WPMazic_Security::sanitize_text($settings['breadcrumb_home_text']);
        }

        // --- Social URLs ---------------------------------------------------
        $social_fields = array(
            'social_facebook',
            'social_twitter',
            'social_instagram',
            'social_linkedin',
        );
        foreach ($social_fields as $field) {
            if (isset($settings[$field])) {
                $clean[$field] = WPMazic_Security::sanitize_url($settings[$field]);
            }
        }

        // --- Business / Local SEO ------------------------------------------
        $business_text_fields = array(
            'business_name',
            'business_address',
            'business_phone',
            'business_city',
            'business_state',
            'business_zip',
            'business_country',
            'business_type',
        );
        foreach ($business_text_fields as $field) {
            if (isset($settings[$field])) {
                $clean[$field] = WPMazic_Security::sanitize_text($settings[$field]);
            }
        }

        // Latitude / Longitude — float values
        if (isset($settings['business_lat'])) {
            $clean['business_lat'] = floatval($settings['business_lat']);
        }
        if (isset($settings['business_lng'])) {
            $clean['business_lng'] = floatval($settings['business_lng']);
        }

        // Business hours — textarea (multiline)
        if (isset($settings['business_hours'])) {
            $clean['business_hours'] = WPMazic_Security::sanitize_textarea($settings['business_hours']);
        }

        // --- Permissions ---------------------------------------------------
        $permission_toggles = array(
            'editor_can_edit',
            'author_can_edit',
        );
        foreach ($permission_toggles as $perm) {
            $clean[$perm] = isset($settings[$perm]) && absint($settings[$perm]) ? 1 : 0;
        }

        // --- IndexNow -----------------------------------------------------
        if (isset($settings['indexnow_api_key'])) {
            $clean['indexnow_api_key'] = preg_replace('/[^a-zA-Z0-9]/', '', (string) $settings['indexnow_api_key']);
        }

        // --- Noindex toggles -----------------------------------------------
        $noindex_toggles = array(
            'noindex_categories',
            'noindex_tags',
            'noindex_archives',
            'noindex_attachments',
        );
        foreach ($noindex_toggles as $ni) {
            $clean[$ni] = isset($settings[$ni]) && absint($settings[$ni]) ? 1 : 0;
        }

        // --- Advanced robots snippet controls -----------------------------
        if (isset($settings['robots_max_snippet'])) {
            $clean['robots_max_snippet'] = intval($settings['robots_max_snippet']);
        }

        if (isset($settings['robots_max_video_preview'])) {
            $clean['robots_max_video_preview'] = intval($settings['robots_max_video_preview']);
        }

        if (isset($settings['robots_max_image_preview'])) {
            $allowed_image_preview = array('none', 'standard', 'large');
            $value = sanitize_text_field(wp_unslash($settings['robots_max_image_preview']));
            $clean['robots_max_image_preview'] = in_array($value, $allowed_image_preview, true) ? $value : 'large';
        }

        // --- Robots meta (textarea) ----------------------------------------
        if (isset($settings['robots_meta'])) {
            $clean['robots_meta'] = WPMazic_Security::sanitize_textarea($settings['robots_meta']);
        }

        /**
         * Filter the sanitized settings before they are stored.
         *
         * @param array $clean    Sanitized settings.
         * @param array $settings Raw settings input.
         */
        return apply_filters('wpmazic_sanitize_settings', $clean, $settings);
    }

    // ------------------------------------------------------------------
    // Helper: permission gate used by every AJAX handler.
    // ------------------------------------------------------------------

    /**
     * Verify nonce + capability. Sends JSON error and dies on failure.
     *
     * SECURITY: Added strict capability check for all AJAX requests.
     *
     * @param string $capability Required capability (default: manage_options).
     */
    private function verify_ajax_request($capability = 'manage_options')
    {
        // SECURITY: Verify nonce first to prevent CSRF attacks.
        if (!check_ajax_referer('wpmazic_nonce', 'nonce', false)) {
            wp_send_json_error(
                array('message' => __('Security check failed. Please refresh the page and try again.', 'wpmazic-seo-lite')),
                403
            );
        }

        // SECURITY: Verify user capability - prevents unauthorized access even with valid nonce.
        if (!current_user_can($capability)) {
            wp_send_json_error(
                array('message' => __('You do not have permission to perform this action.', 'wpmazic-seo-lite')),
                403
            );
        }

        // SECURITY: Additional check for POST requests.
        if ('POST' !== strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? 'GET')))) {
            wp_send_json_error(
                array('message' => __('Invalid request method.', 'wpmazic-seo-lite')),
                405
            );
        }
    }

    /**
     * Recursively sanitize decoded JSON payloads before downstream validation.
     *
     * @param mixed $value Payload value.
     * @return mixed
     */
    private function sanitize_decoded_payload($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->sanitize_decoded_payload($item);
            }
            return $value;
        }

        if (is_string($value)) {
            return sanitize_textarea_field($value);
        }

        if (is_bool($value) || is_int($value) || is_float($value) || null === $value) {
            return $value;
        }

        return '';
    }

    // ------------------------------------------------------------------
    // AJAX: Save Settings
    // ------------------------------------------------------------------

    /**
     * Save plugin settings via AJAX.
     *
     * SECURITY: Properly sanitizes all input before processing.
     */
    public function save_settings()
    {
        $this->verify_ajax_request();

        // SECURITY: Get and validate input
        $raw_settings = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : '';

        // Validate input is not empty
        if (empty($raw_settings)) {
            wp_send_json_error(array('message' => __('No settings to save.', 'wpmazic-seo-lite')));
        }

        // Support JSON-encoded payload from JS.
        if (is_string($raw_settings)) {
            $decoded = json_decode($raw_settings, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $settings = $this->sanitize_decoded_payload($decoded);
            } else {
                wp_send_json_error(array('message' => __('Invalid settings data format.', 'wpmazic-seo-lite')));
            }
        } elseif (is_array($raw_settings)) {
            $settings = $this->sanitize_decoded_payload($raw_settings);
        } else {
            $settings = array();
        }

        if (!is_array($settings)) {
            wp_send_json_error(array('message' => __('Invalid settings data.', 'wpmazic-seo-lite')));
        }

        // SECURITY: Run through sanitizer before saving.
        $clean = $this->sanitize_settings($settings);
        update_option('wpmazic_settings', $clean);

        wp_send_json_success(array('message' => __('Settings saved successfully.', 'wpmazic-seo-lite')));
    }

    // ------------------------------------------------------------------
    // AJAX: Get Stats
    // ------------------------------------------------------------------

    /**
     * Return comprehensive SEO statistics via AJAX.
     */
    public function get_stats()
    {
        $this->verify_ajax_request();

        global $wpdb;

        // Post / page counts
        $total_posts = (int) wp_count_posts('post')->publish;
        $total_pages = (int) wp_count_posts('page')->publish;

        // Posts that have a non-empty _wpmazic_title or _wpmazic_description meta
        $posts_with_seo = (int) $wpdb->get_var(
            "SELECT COUNT( DISTINCT p.ID )
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_status = 'publish'
               AND p.post_type IN ('post','page')
               AND pm.meta_key IN ('_wpmazic_title','_wpmazic_description')
               AND pm.meta_value != ''"
        );

        $total_content = $total_posts + $total_pages;
        $seo_coverage = $total_content > 0
            ? round(($posts_with_seo / $total_content) * 100, 1)
            : 0;

        // Redirects table
        $redirects_table = $wpdb->prefix . 'wpmazic_redirects';
        $total_redirects = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$redirects_table}");

        // 404 table
        $errors_table = $wpdb->prefix . 'wpmazic_404';
        $total_404s = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$errors_table}");

        $stats = array(
            'total_posts' => $total_posts,
            'total_pages' => $total_pages,
            'posts_with_seo' => $posts_with_seo,
            'seo_coverage' => $seo_coverage,
            'total_redirects' => $total_redirects,
            'total_404s' => $total_404s,
        );

        /**
         * Filter the stats payload before returning.
         *
         * @param array $stats
         */
        wp_send_json_success(apply_filters('wpmazic_stats', $stats));
    }

    // ------------------------------------------------------------------
    // AJAX: Bulk Save (Bulk Editor)
    // ------------------------------------------------------------------

    /**
     * Save bulk-edited SEO meta for multiple posts at once.
     *
     * SECURITY FIXES:
     * - Added rate limiting to prevent abuse (max 20 items per request).
     * - Added capability check per post (current_user_can edit_post).
     * - Validates all input strictly.
     *
     * Expects $_POST['items'] as an array of objects:
     *   [ { post_id, title, description, focus_keyword }, … ]
     */
    public function bulk_save()
    {
        $this->verify_ajax_request();

        // SECURITY: Get and validate input
        $items_raw = isset($_POST['items']) ? wp_unslash($_POST['items']) : '';

        // Validate input is not empty
        if (empty($items_raw)) {
            wp_send_json_error(array('message' => __('No items to save.', 'wpmazic-seo-lite')));
        }

        // Decode JSON if needed
        if (is_array($items_raw)) {
            $items = $this->sanitize_decoded_payload($items_raw);
        } else {
            $decoded_items = json_decode($items_raw, true);
            $items = is_array($decoded_items) ? $this->sanitize_decoded_payload($decoded_items) : $decoded_items;
        }

        // Validate JSON decoding succeeded
        if (null === $items && json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => __('Invalid data format.', 'wpmazic-seo-lite')));
        }

        if (empty($items) || !is_array($items)) {
            wp_send_json_error(array('message' => __('No items to save.', 'wpmazic-seo-lite')));
        }

        // SECURITY: Keep batch saves small enough for shared hosting admin requests.
        $max_items = 20;
        if (count($items) > $max_items) {
            wp_send_json_error(
                array(
                    'message' => sprintf(
                        /* translators: %d: maximum number of items */
                        __('Too many items. Maximum %d posts can be updated at once.', 'wpmazic-seo-lite'),
                        $max_items
                    ),
                )
            );
        }

        $updated = 0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $post_id = isset($item['post_id']) ? absint($item['post_id']) : 0;

            // SECURITY: Validate post exists.
            $post = get_post($post_id);
            if (!$post) {
                continue;
            }

            // SECURITY: Check user has permission to edit this specific post.
            if (!current_user_can('edit_post', $post_id)) {
                continue;
            }

            // SECURITY: Sanitize and save meta fields.
            if (isset($item['title'])) {
                update_post_meta(
                    $post_id,
                    '_wpmazic_title',
                    WPMazic_Security::sanitize_text($item['title'])
                );
            }
            if (isset($item['description'])) {
                update_post_meta(
                    $post_id,
                    '_wpmazic_description',
                    WPMazic_Security::sanitize_textarea($item['description'])
                );
            }
            if (isset($item['focus_keyword'])) {
                update_post_meta(
                    $post_id,
                    '_wpmazic_keyword',
                    WPMazic_Security::sanitize_text($item['focus_keyword'])
                );
            }

            $updated++;
        }

        wp_send_json_success(
            array(
                'message' => sprintf(
                    /* translators: %d: number of posts updated */
                    __('%d posts updated successfully.', 'wpmazic-seo-lite'),
                    $updated
                ),
                'updated' => $updated,
            )
        );
    }

    // ------------------------------------------------------------------
    // AJAX: Delete Redirect
    // ------------------------------------------------------------------

    /**
     * Delete a single redirect row by ID.
     */
    public function delete_redirect()
    {
        $this->verify_ajax_request();

        global $wpdb;

        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid redirect ID.', 'wpmazic-seo-lite')));
        }

        $table = $wpdb->prefix . 'wpmazic_redirects';
        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));

        if (false === $deleted) {
            wp_send_json_error(array('message' => __('Failed to delete redirect.', 'wpmazic-seo-lite')));
        }

        wp_send_json_success(array('message' => __('Redirect deleted.', 'wpmazic-seo-lite')));
    }

    // ------------------------------------------------------------------
    // AJAX: Delete 404 Entry
    // ------------------------------------------------------------------

    /**
     * Delete a single 404 log entry by ID.
     */
    public function delete_404()
    {
        $this->verify_ajax_request();

        global $wpdb;

        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid 404 entry ID.', 'wpmazic-seo-lite')));
        }

        $table = $wpdb->prefix . 'wpmazic_404';
        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));

        if (false === $deleted) {
            wp_send_json_error(array('message' => __('Failed to delete 404 entry.', 'wpmazic-seo-lite')));
        }

        wp_send_json_success(array('message' => __('404 entry deleted.', 'wpmazic-seo-lite')));
    }

    // ------------------------------------------------------------------
    // AJAX: Save robots.txt (Tools page)
    // ------------------------------------------------------------------

    /**
     * Save custom robots.txt content.
     *
     * SECURITY: Uses strict sanitization for robots.txt content.
     */
    public function save_robots()
    {
        $this->verify_ajax_request();

        // SECURITY: Validate and sanitize robots.txt content.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $content = isset($_POST['robots_content']) ? sanitize_textarea_field(wp_unslash($_POST['robots_content'])) : '';

        // SECURITY: Limit robots.txt size to prevent abuse (max 10KB).
        if (strlen($content) > 10240) {
            wp_send_json_error(array('message' => __('robots.txt content is too large (max 10KB).', 'wpmazic-seo-lite')));
        }

        update_option('wpmazic_robots_txt', $content);

        wp_send_json_success(array('message' => __('robots.txt saved successfully.', 'wpmazic-seo-lite')));
    }

    // ------------------------------------------------------------------
    // AJAX: Optimize Database (Tools page)
    // ------------------------------------------------------------------

    /**
     * Run lightweight housekeeping on plugin database tables.
     *
     * - Purge old 404 entries (older than 90 days)
     * - Purge orphaned post-meta
     * - OPTIMIZE plugin tables
     */
    public function optimize_db()
    {
        $this->verify_ajax_request();

        global $wpdb;

        $cleaned = 0;

        // 1. Delete 404 entries older than 90 days.
        $errors_table = $wpdb->prefix . 'wpmazic_404';
        $cleaned += (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$errors_table} WHERE created_at < %s",
                gmdate('Y-m-d H:i:s', strtotime('-90 days'))
            )
        );

        // 2. Remove orphaned wpmazic post-meta (post no longer exists).
        $cleaned += (int) $wpdb->query(
            "DELETE pm FROM {$wpdb->postmeta} pm
             LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE p.ID IS NULL
               AND pm.meta_key LIKE '_wpmazic\_%'"
        );

        // 3. Optimize plugin tables.
        $tables = array(
            $wpdb->prefix . 'wpmazic_redirects',
            $wpdb->prefix . 'wpmazic_404',
        );
        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL
        }

        wp_send_json_success(array(
            'message' => sprintf(
                /* translators: %d: number of rows cleaned */
                __('Database optimized. %d stale rows removed.', 'wpmazic-seo-lite'),
                $cleaned
            ),
            'cleaned' => $cleaned,
        ));
    }

    // ------------------------------------------------------------------
    // AJAX: Clear Cache (Tools page)
    // ------------------------------------------------------------------

    /**
     * Flush all transients and caches created by the plugin.
     */
    public function clear_cache()
    {
        $this->verify_ajax_request();

        global $wpdb;

        // Delete all transients with the wpmazic_ prefix.
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_wpmazic\_%'
                OR option_name LIKE '_transient_timeout_wpmazic\_%'"
        );

        // Allow other plugin modules to flush their own caches.
        do_action('wpmazic_clear_cache');

        wp_send_json_success(array('message' => __('Cache cleared successfully.', 'wpmazic-seo-lite')));
    }

    /**
     * Sanitize array of slugs and limit to allowed values.
     *
     * @param mixed $values Raw submitted values.
     * @param array $allowed Allowed slug list.
     * @return array
     */
    private function sanitize_slug_array($values, $allowed = array())
    {
        if (!is_array($values)) {
            return array();
        }

        $clean = array();
        foreach ($values as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $slug = sanitize_key((string) $value);
            if ('' === $slug) {
                continue;
            }

            if (!empty($allowed) && !in_array($slug, $allowed, true)) {
                continue;
            }

            $clean[] = $slug;
        }

        return array_values(array_unique($clean));
    }
}
