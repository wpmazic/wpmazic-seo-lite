<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$settings = get_option('wpmazic_settings', array());
$default_settings = function_exists('wpmazic_seo_get_default_settings') ? wpmazic_seo_get_default_settings() : array();

$public_post_types = get_post_types(array('public' => true), 'names');
$excluded_types = array(
    'attachment',
    'revision',
    'nav_menu_item',
    'custom_css',
    'customize_changeset',
    'oembed_cache',
    'user_request',
    'wp_block',
    'wp_template',
    'wp_template_part',
    'wp_navigation',
    'wp_global_styles',
);
$tracked_post_types = array_values(array_diff($public_post_types, $excluded_types));
if (empty($tracked_post_types)) {
    $tracked_post_types = array('post', 'page');
}

$published_posts = (int) wp_count_posts('post')->publish;
$published_pages = (int) wp_count_posts('page')->publish;
$total_content = 0;

foreach ($tracked_post_types as $tracked_type) {
    $counts = wp_count_posts($tracked_type);
    if (is_object($counts) && isset($counts->publish)) {
        $total_content += (int) $counts->publish;
    }
}

$post_type_placeholders = implode(', ', array_fill(0, count($tracked_post_types), '%s'));

$count_with_meta_key = static function ($meta_key) use ($wpdb, $post_type_placeholders, $tracked_post_types) {
    $sql = "
        SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
        WHERE p.post_status = 'publish'
          AND p.post_type IN ({$post_type_placeholders})
          AND pm.meta_key = %s
          AND pm.meta_value <> ''
    ";

    $params = array_merge($tracked_post_types, array((string) $meta_key));
    return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
};

$with_title = $count_with_meta_key('_wpmazic_title');
$with_description = $count_with_meta_key('_wpmazic_description');
$with_focus = $count_with_meta_key('_wpmazic_focus_keyword');

$with_complete_meta_sql = "
    SELECT COUNT(DISTINCT p.ID)
    FROM {$wpdb->posts} p
    WHERE p.post_status = 'publish'
      AND p.post_type IN ({$post_type_placeholders})
      AND EXISTS (
          SELECT 1
          FROM {$wpdb->postmeta} mt
          WHERE mt.post_id = p.ID
            AND mt.meta_key = %s
            AND mt.meta_value <> ''
      )
      AND EXISTS (
          SELECT 1
          FROM {$wpdb->postmeta} md
          WHERE md.post_id = p.ID
            AND md.meta_key = %s
            AND md.meta_value <> ''
      )
";
$with_complete_meta = (int) $wpdb->get_var(
    $wpdb->prepare(
        $with_complete_meta_sql,
        array_merge($tracked_post_types, array('_wpmazic_title', '_wpmazic_description'))
    )
);

$missing_title = max(0, $total_content - $with_title);
$missing_description = max(0, $total_content - $with_description);
$missing_focus = max(0, $total_content - $with_focus);
$missing_meta = max(0, $total_content - $with_complete_meta);

$meta_coverage = $total_content > 0 ? round(($with_complete_meta / $total_content) * 100, 1) : 0;
$focus_coverage = $total_content > 0 ? round(($with_focus / $total_content) * 100, 1) : 0;
$overall_score = $total_content > 0 ? round(($meta_coverage * 0.65) + ($focus_coverage * 0.35), 1) : 0;

$table_exists = static function ($table_name) use ($wpdb) {
    $found = $wpdb->get_var(
        $wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $table_name
        )
    );

    return is_string($found) && $found === $table_name;
};

$redirects_table = wpmazic_seo_get_table_name( 'redirects' );
$errors_table = wpmazic_seo_get_table_name( '404' );

$redirects = $table_exists($redirects_table) ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$redirects_table}") : 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$errors_404 = $table_exists($errors_table) ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$errors_table}") : 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$crawl_labels = array();
$crawl_values = array();

if ($table_exists($errors_table)) {
    $window_start = wp_date('Y-m-d 00:00:00', strtotime('-13 days', current_time('timestamp')));
    $crawl_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DATE(last_hit) AS day, COUNT(*) AS hits
             FROM {$errors_table}
             WHERE last_hit >= %s
             GROUP BY DATE(last_hit)
             ORDER BY day ASC",
            $window_start
        ),
        ARRAY_A
    );

    $crawl_map = array();
    foreach ((array) $crawl_rows as $row) {
        if (!is_array($row) || empty($row['day'])) {
            continue;
        }
        $crawl_map[(string) $row['day']] = isset($row['hits']) ? (int) $row['hits'] : 0;
    }

    $today_ts = current_time('timestamp');
    for ($i = 13; $i >= 0; $i--) {
        $day_key = wp_date('Y-m-d', strtotime('-' . $i . ' days', $today_ts));
        $crawl_labels[] = $day_key;
        $crawl_values[] = isset($crawl_map[$day_key]) ? (int) $crawl_map[$day_key] : 0;
    }
}

$stats = array(
    __('Published Posts', 'wpmazic-seo-lite') => $published_posts,
    __('Published Pages', 'wpmazic-seo-lite') => $published_pages,
    __('Plugin Version', 'wpmazic-seo-lite') => defined('WPMAZIC_SEO_VERSION') ? WPMAZIC_SEO_VERSION : '2.1.0',
);

$improvement_items = array();
if ($missing_meta > 0) {
    $improvement_items[] = array(
        'message' => sprintf(
            /* translators: %d: content count */
            _n('%d page is missing complete SEO title + description.', '%d pages are missing complete SEO title + description.', $missing_meta, 'wpmazic-seo-lite'),
            (int) $missing_meta
        ),
        'url' => wpmazic_seo_admin_page_url('bulk-editor'),
        'action' => __('Fix Meta', 'wpmazic-seo-lite'),
    );
}
if ($missing_focus > 0) {
    $improvement_items[] = array(
        'message' => sprintf(
            /* translators: %d: content count */
            _n('%d page has no focus keyword.', '%d pages have no focus keyword.', $missing_focus, 'wpmazic-seo-lite'),
            (int) $missing_focus
        ),
        'url' => wpmazic_seo_admin_page_url('analysis'),
        'action' => __('Review Content', 'wpmazic-seo-lite'),
    );
}
if ($errors_404 > 0) {
    $improvement_items[] = array(
        'message' => sprintf(
            /* translators: %d: 404 count */
            _n('%d 404 URL is currently logged.', '%d 404 URLs are currently logged.', $errors_404, 'wpmazic-seo-lite'),
            (int) $errors_404
        ),
        'url' => wpmazic_seo_admin_page_url('404-monitor'),
        'action' => __('Open 404 Monitor', 'wpmazic-seo-lite'),
    );
}

$dashboard_chart_payload = array(
    'coverage' => array(
        'labels' => array(
            __('Optimized Pages', 'wpmazic-seo-lite'),
            __('Needs Metadata', 'wpmazic-seo-lite'),
        ),
        'values' => array(
            (int) $with_complete_meta,
            (int) $missing_meta,
        ),
    ),
    'gaps' => array(
        'labels' => array(
            __('Missing SEO Title', 'wpmazic-seo-lite'),
            __('Missing Meta Description', 'wpmazic-seo-lite'),
            __('Missing Focus Keyword', 'wpmazic-seo-lite'),
        ),
        'values' => array(
            (int) $missing_title,
            (int) $missing_description,
            (int) $missing_focus,
        ),
    ),
    'crawl' => array(
        'labels' => $crawl_labels,
        'values' => $crawl_values,
    ),
);

wpmazic_seo_admin_shell_open(
    __('Dashboard', 'wpmazic-seo-lite'),
    __('Quick overview of SEO health, setup progress, and important shortcuts.', 'wpmazic-seo-lite')
);
?>

<div class="wmz-grid">
    <?php foreach ($stats as $label => $value): ?>
        <div class="wmz-stat">
            <p class="wmz-stat-label"><?php echo esc_html($label); ?></p>
            <p class="wmz-stat-value"><?php echo esc_html((string) $value); ?></p>
        </div>
    <?php endforeach; ?>
</div>

<div class="wmz-card">
    <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-3">
        <h2><?php esc_html_e('SEO Improvement Dashboard', 'wpmazic-seo-lite'); ?></h2>
    </div>
    <p class="wmz-subtle">
        <?php esc_html_e('These charts highlight what needs attention first so your team can improve SEO coverage faster.', 'wpmazic-seo-lite'); ?>
    </p>

    <div class="wmz-grid tw-mt-3">
        <div class="wmz-stat">
            <p class="wmz-stat-label"><?php esc_html_e('Overall Readiness', 'wpmazic-seo-lite'); ?></p>
            <p class="wmz-stat-value"><?php echo esc_html(number_format_i18n($overall_score, 1) . '%'); ?></p>
        </div>
        <div class="wmz-stat">
            <p class="wmz-stat-label"><?php esc_html_e('Meta Coverage', 'wpmazic-seo-lite'); ?></p>
            <p class="wmz-stat-value"><?php echo esc_html(number_format_i18n($meta_coverage, 1) . '%'); ?></p>
        </div>
        <div class="wmz-stat">
            <p class="wmz-stat-label"><?php esc_html_e('Focus Keyword Coverage', 'wpmazic-seo-lite'); ?></p>
            <p class="wmz-stat-value"><?php echo esc_html(number_format_i18n($focus_coverage, 1) . '%'); ?></p>
        </div>
        <div class="wmz-stat">
            <p class="wmz-stat-label"><?php esc_html_e('Redirects / 404s', 'wpmazic-seo-lite'); ?></p>
            <p class="wmz-stat-value">
                <?php echo esc_html(number_format_i18n($redirects) . ' / ' . number_format_i18n($errors_404)); ?>
            </p>
        </div>
    </div>

    <div
        id="wmz-dashboard-charts"
        class="tw-grid lg:tw-grid-cols-3 tw-gap-4 tw-mt-4"
        data-chart-payload="<?php echo esc_attr( wp_json_encode( $dashboard_chart_payload ) ); ?>"
    >
        <div class="tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4">
            <h3 class="wmz-section-title"><?php esc_html_e('Metadata Coverage', 'wpmazic-seo-lite'); ?></h3>
            <?php if ($total_content > 0): ?>
                <div class="tw-h-56">
                    <canvas id="wmz-dashboard-coverage-chart"></canvas>
                </div>
            <?php else: ?>
                <p class="wmz-help"><?php esc_html_e('No published pages found yet.', 'wpmazic-seo-lite'); ?></p>
            <?php endif; ?>
        </div>

        <div class="tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-p-4">
            <h3 class="wmz-section-title"><?php esc_html_e('Content Gaps', 'wpmazic-seo-lite'); ?></h3>
            <?php if ($total_content > 0): ?>
                <div class="tw-h-56">
                    <canvas id="wmz-dashboard-gaps-chart"></canvas>
                </div>
            <?php else: ?>
                <p class="wmz-help"><?php esc_html_e('Add content to start SEO gap tracking.', 'wpmazic-seo-lite'); ?></p>
            <?php endif; ?>
        </div>

        <div class="tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4">
            <h3 class="wmz-section-title"><?php esc_html_e('404 Trend (Last 14 Days)', 'wpmazic-seo-lite'); ?></h3>
            <?php if (!empty($crawl_labels)): ?>
                <div class="tw-h-56">
                    <canvas id="wmz-dashboard-crawl-chart"></canvas>
                </div>
            <?php else: ?>
                <p class="wmz-help"><?php esc_html_e('No 404 trend data available yet.', 'wpmazic-seo-lite'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-p-4 tw-mt-4">
        <h3 class="wmz-section-title"><?php esc_html_e('Recommended Next Actions', 'wpmazic-seo-lite'); ?></h3>
        <?php if (!empty($improvement_items)): ?>
            <ul class="tw-list-none tw-m-0 tw-p-0 tw-space-y-2">
                <?php foreach ($improvement_items as $item): ?>
                    <li
                        class="tw-flex tw-items-center tw-justify-between tw-gap-3 tw-rounded-lg tw-border tw-border-slate-200 tw-bg-slate-50 tw-px-3 tw-py-2">
                        <span class="tw-text-sm tw-text-slate-700"><?php echo esc_html($item['message']); ?></span>
                        <a class="button button-small"
                            href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['action']); ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="wmz-help">
                <?php esc_html_e('No critical SEO gaps detected right now. Keep publishing and monitor trends.', 'wpmazic-seo-lite'); ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<div class="wmz-card">
    <h2><?php esc_html_e('Getting Started', 'wpmazic-seo-lite'); ?></h2>
    <div class="tw-grid md:tw-grid-cols-3 tw-gap-4 tw-text-sm">
        <a href="<?php echo esc_url(wpmazic_seo_admin_page_url('settings')); ?>"
            class="tw-no-underline tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4 hover:tw-border-wmz-300">
            <p class="tw-font-semibold tw-text-slate-900">
                <?php esc_html_e('Configure Global Settings', 'wpmazic-seo-lite'); ?></p>
            <p class="tw-text-slate-600 tw-mt-1">
                <?php esc_html_e('Set titles, schema, index rules, and social profiles.', 'wpmazic-seo-lite'); ?></p>
        </a>
        <a href="<?php echo esc_url(wpmazic_seo_admin_page_url('bulk-editor')); ?>"
            class="tw-no-underline tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4 hover:tw-border-wmz-300">
            <p class="tw-font-semibold tw-text-slate-900">
                <?php esc_html_e('Fill Missing Meta', 'wpmazic-seo-lite'); ?></p>
            <p class="tw-text-slate-600 tw-mt-1">
                <?php esc_html_e('Use the bulk editor to improve title and description coverage.', 'wpmazic-seo-lite'); ?>
            </p>
        </a>
        <a href="<?php echo esc_url(home_url('/sitemap.xml')); ?>" target="_blank" rel="noopener noreferrer"
            class="tw-no-underline tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4 hover:tw-border-wmz-300">
            <p class="tw-font-semibold tw-text-slate-900">
                <?php esc_html_e('Review XML Sitemap', 'wpmazic-seo-lite'); ?></p>
            <p class="tw-text-slate-600 tw-mt-1">
                <?php esc_html_e('Confirm published content appears and noindex pages are excluded.', 'wpmazic-seo-lite'); ?>
            </p>
        </a>
    </div>
</div>

<div class="wmz-card">
    <h2><?php esc_html_e('Feature Status', 'wpmazic-seo-lite'); ?></h2>
    <div class="tw-grid md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-3">
        <?php
        $feature_status = array(
            'enable_sitemap' => __('XML Sitemap', 'wpmazic-seo-lite'),
            'enable_schema' => __('Schema Markup', 'wpmazic-seo-lite'),
            'enable_og' => __('Open Graph / Twitter', 'wpmazic-seo-lite'),
            'enable_redirects' => __('Redirect Manager', 'wpmazic-seo-lite'),
            'enable_404_monitor' => __('404 Monitor', 'wpmazic-seo-lite'),
            'enable_breadcrumbs' => __('Breadcrumbs', 'wpmazic-seo-lite'),
            'enable_image_seo' => __('Image SEO', 'wpmazic-seo-lite'),
            'enable_indexnow' => __('IndexNow', 'wpmazic-seo-lite'),
            'enable_link_tracking' => __('Internal Link Tracking', 'wpmazic-seo-lite'),
            'enable_llms_txt' => __('llms.txt Endpoint', 'wpmazic-seo-lite'),
            'enable_image_sitemap' => __('Image Sitemap', 'wpmazic-seo-lite'),
            'enable_auto_slug_redirect' => __('Auto Slug Redirect', 'wpmazic-seo-lite'),
            'enable_dynamic_og_image' => __('Dynamic OG Image', 'wpmazic-seo-lite'),
            'enable_security_bad_bots' => __('Bad Bot Blocker', 'wpmazic-seo-lite'),
            'enable_security_headers' => __('Security Headers', 'wpmazic-seo-lite'),
            'enable_security_disable_xmlrpc' => __('Disable XML-RPC', 'wpmazic-seo-lite'),
            'enable_security_hide_wp_version' => __('Hide WP Version', 'wpmazic-seo-lite'),
            'enable_security_block_author_enum' => __('Block Author Enumeration', 'wpmazic-seo-lite'),
        );
        foreach ($feature_status as $key => $label):
            $default_value = isset($default_settings[$key]) ? (int) $default_settings[$key] : 1;
            $enabled = isset($settings[$key]) ? !empty($settings[$key]) : (bool) $default_value;
            ?>
            <div
                class="tw-flex tw-items-center tw-justify-between tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-px-3 tw-py-2">
                <span class="tw-text-sm tw-text-slate-700"><?php echo esc_html($label); ?></span>
                <span
                    class="wmz-pill <?php echo $enabled ? '' : 'tw-border-slate-300 tw-bg-slate-100 tw-text-slate-600'; ?>">
                    <?php echo esc_html($enabled ? __('Enabled', 'wpmazic-seo-lite') : __('Disabled', 'wpmazic-seo-lite')); ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php wpmazic_seo_admin_shell_close(); ?>
