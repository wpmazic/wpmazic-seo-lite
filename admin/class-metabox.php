<?php
/**
 * WPMazic SEO - Metabox
 *
 * Comprehensive SEO metabox for post-level optimization.
 *
 * @package WPMazic_SEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMazic_Metabox
{

    /**
     * Meta field prefix.
     *
     * @var string
     */
    private $prefix = '_wpmazic_';

    /**
     * All meta keys managed by this metabox (without prefix).
     *
     * @var array
     */
    private $meta_keys = array(
        'title',
        'description',
        'keyword',
        'og_title',
        'og_description',
        'og_image',
        'twitter_title',
        'twitter_description',
        'twitter_image',
        'twitter_card',
        'noindex',
        'nofollow',
        'noarchive',
        'nosnippet',
        'noimageindex',
        'canonical',
        'redirect',
        'hreflang_map',
        'breadcrumb_title',
        'cornerstone',
        'schema_type',
        'faq_items',
    );

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'add_metabox'));
        add_action('save_post', array($this, 'save_metabox'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Enqueue media uploader and admin assets on post edit screens.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets($hook)
    {
        if (!in_array($hook, array('post.php', 'post-new.php'), true)) {
            return;
        }

        global $post;
        if (!$post || !in_array($post->post_type, $this->get_post_types(), true)) {
            return;
        }

        wp_enqueue_media();

        // Enqueue plugin admin styles
        wp_enqueue_style(
            'wpmazic-seo-lite-admin-style',
            WPMAZIC_SEO_URL . 'assets/css/admin.css',
            array(),
            WPMAZIC_SEO_VERSION
        );

        // Add metabox-specific inline CSS
        $metabox_css = '
            .wpmazic-metabox-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif; }
            .wpmazic-metabox-tabs { display: flex; border-bottom: 2px solid #0073aa; margin: 0 0 16px; padding: 0; list-style: none; }
            .wpmazic-metabox-tabs li { margin: 0; }
            .wpmazic-metabox-tabs li a { display: block; padding: 10px 20px; text-decoration: none; color: #555; font-weight: 600; font-size: 13px; border: 1px solid transparent; border-bottom: none; margin-bottom: -2px; background: #f1f1f1; border-radius: 4px 4px 0 0; transition: background .2s, color .2s; }
            .wpmazic-metabox-tabs li a:hover { background: #e2e2e2; color: #0073aa; }
            .wpmazic-metabox-tabs li a.wpmazic-tab-active { background: #fff; color: #0073aa; border-color: #0073aa #0073aa #fff; }
            .wpmazic-tab-content { display: none; padding: 4px 0; }
            .wpmazic-tab-content.wpmazic-tab-active { display: block; }
            .wpmazic-field { margin-bottom: 18px; }
            .wpmazic-field label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px; color: #23282d; }
            .wpmazic-field input[type="text"],
            .wpmazic-field input[type="url"],
            .wpmazic-field textarea,
            .wpmazic-field select { width: 100%; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; box-sizing: border-box; }
            .wpmazic-field textarea { resize: vertical; }
            .wpmazic-field .wpmazic-char-count { display: inline-block; margin-top: 4px; font-size: 12px; color: #666; }
            .wpmazic-field .wpmazic-char-count.wpmazic-char-warning { color: #d63638; font-weight: 600; }
            .wpmazic-field .wpmazic-char-count.wpmazic-char-good { color: #00a32a; }
            .wpmazic-field .description { font-size: 12px; color: #888; margin-top: 4px; font-style: italic; }
            .wpmazic-preview { background: #f9f9f9; border: 1px solid #e2e2e2; border-radius: 6px; padding: 14px 18px; margin-bottom: 18px; }
            .wpmazic-preview-title { color: #1a0dab; font-size: 18px; line-height: 1.3; margin: 0 0 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: Arial, sans-serif; }
            .wpmazic-preview-url { color: #006621; font-size: 13px; line-height: 1.4; margin: 0 0 4px; font-family: Arial, sans-serif; }
            .wpmazic-preview-desc { color: #545454; font-size: 13px; line-height: 1.5; margin: 0; font-family: Arial, sans-serif; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
            .wpmazic-seo-score { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 4px; font-size: 13px; font-weight: 600; margin-bottom: 16px; }
            .wpmazic-seo-score .wpmazic-score-dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }
            .wpmazic-seo-score.wpmazic-score-good { background: #edf7ed; color: #1e7e1e; }
            .wpmazic-seo-score.wpmazic-score-good .wpmazic-score-dot { background: #00a32a; }
            .wpmazic-seo-score.wpmazic-score-ok { background: #fff8e5; color: #996800; }
            .wpmazic-seo-score.wpmazic-score-ok .wpmazic-score-dot { background: #dba617; }
            .wpmazic-seo-score.wpmazic-score-bad { background: #fcf0f1; color: #8a1e1e; }
            .wpmazic-seo-score.wpmazic-score-bad .wpmazic-score-dot { background: #d63638; }
            .wpmazic-analysis-results { background: #f9f9f9; border: 1px solid #e2e2e2; border-radius: 4px; padding: 12px 16px; margin-bottom: 18px; }
            .wpmazic-analysis-results h4 { margin: 0 0 8px; font-size: 13px; }
            .wpmazic-analysis-results ul { margin: 0; padding: 0; list-style: none; }
            .wpmazic-analysis-results ul li { padding: 4px 0 4px 20px; position: relative; font-size: 12px; line-height: 1.5; }
            .wpmazic-analysis-results ul li::before { content: \'\'; position: absolute; left: 0; top: 8px; width: 10px; height: 10px; border-radius: 50%; }
            .wpmazic-analysis-results ul li.wpmazic-check-good::before { background: #00a32a; }
            .wpmazic-analysis-results ul li.wpmazic-check-warn::before { background: #dba617; }
            .wpmazic-analysis-results ul li.wpmazic-check-bad::before { background: #d63638; }
            .wpmazic-image-upload-wrap { display: flex; align-items: flex-start; gap: 12px; }
            .wpmazic-image-upload-wrap .wpmazic-image-preview { max-width: 120px; max-height: 80px; border-radius: 4px; border: 1px solid #ddd; object-fit: cover; }
            .wpmazic-image-upload-wrap .button { flex-shrink: 0; }
            .wpmazic-checkbox-field { margin-bottom: 12px; }
            .wpmazic-checkbox-field label { font-weight: 400; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
            .wpmazic-checkbox-field label strong { font-weight: 600; }
        ';

        wp_add_inline_style('wpmazic-seo-lite-admin-style', $metabox_css);

        // Enqueue plugin admin script
        wp_enqueue_script(
            'wpmazic-seo-lite-admin-script',
            WPMAZIC_SEO_URL . 'assets/js/admin.js',
            array('jquery'),
            WPMAZIC_SEO_VERSION,
            true
        );

        $metabox_config = array(
            'defaultTitle'       => get_the_title( $post ) . ' - ' . get_bloginfo( 'name' ),
            'defaultDescription' => wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 25, '...' ),
            'faqLimit'           => 0,
            'labels'             => array(
                'characters'       => __( 'characters', 'wpmazic-seo-lite' ),
                'remove'           => __( 'Remove', 'wpmazic-seo-lite' ),
                'question'         => __( 'Question', 'wpmazic-seo-lite' ),
                'answer'           => __( 'Answer', 'wpmazic-seo-lite' ),
                'enterQuestion'    => __( 'Enter question', 'wpmazic-seo-lite' ),
                'enterAnswer'      => __( 'Enter short answer', 'wpmazic-seo-lite' ),
                'removeFaq'        => __( 'Remove FAQ item', 'wpmazic-seo-lite' ),
                'scoreGood'        => __( 'SEO Score: Good', 'wpmazic-seo-lite' ),
                'scoreNeedsWork'   => __( 'SEO Score: Needs Improvement', 'wpmazic-seo-lite' ),
                'scorePoor'        => __( 'SEO Score: Poor', 'wpmazic-seo-lite' ),
                'analysisEmpty'    => __( 'Enter a focus keyword to see content analysis.', 'wpmazic-seo-lite' ),
                'checkKeywordInTitle' => __( 'Primary keyword appears in the SEO title.', 'wpmazic-seo-lite' ),
                'checkKeywordInDescription' => __( 'Primary keyword appears in the meta description.', 'wpmazic-seo-lite' ),
                'checkKeywordInContent' => __( 'Primary keyword appears in the content.', 'wpmazic-seo-lite' ),
                'checkTitleLength' => __( 'SEO title length is in a healthy range.', 'wpmazic-seo-lite' ),
                'checkDescriptionLength' => __( 'Meta description length is in a healthy range.', 'wpmazic-seo-lite' ),
                'checkContentLength' => __( 'Content length gives search engines enough context.', 'wpmazic-seo-lite' ),
                'mediaTitle'       => __( 'Select or Upload Image', 'wpmazic-seo-lite' ),
                'mediaButton'      => __( 'Use this image', 'wpmazic-seo-lite' ),
            ),
        );

        wp_add_inline_script(
            'wpmazic-seo-lite-admin-script',
            'window.wpmazicSeoMetabox = ' . wp_json_encode( $metabox_config ) . ';',
            'before'
        );
    }

    /**
     * Get all public post types.
     *
     * @return array
     */
    private function get_post_types()
    {
        $post_types = get_post_types(array('public' => true), 'names');
        return array_values($post_types);
    }

    /**
     * Permission helper for SEO metabox access.
     *
     * @return bool
     */
    private function can_current_user_access()
    {
        if (current_user_can('manage_options')) {
            return true;
        }

        $settings = get_option('wpmazic_settings', array());

        if (!empty($settings['editor_can_edit']) && current_user_can('edit_others_posts')) {
            return true;
        }

        if (!empty($settings['author_can_edit']) && current_user_can('edit_posts')) {
            return true;
        }

        return false;
    }

    /**
     * Register meta box for all public post types.
     */
    public function add_metabox()
    {
        if (!$this->can_current_user_access()) {
            return;
        }

        $post_types = $this->get_post_types();

        foreach ($post_types as $post_type) {
            add_meta_box(
                'wpmazic_seo',
                __('WPMazic SEO', 'wpmazic-seo-lite'),
                array($this, 'render_metabox'),
                $post_type,
                'normal',
                'high'
            );
        }
    }

    /**
     * Helper: get post meta with prefix.
     *
     * @param int    $post_id Post ID.
     * @param string $key     Meta key without prefix.
     * @return mixed
     */
    private function get_meta($post_id, $key)
    {
        return get_post_meta($post_id, $this->prefix . $key, true);
    }

    /**
     * Render the metabox.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_metabox($post)
    {
        wp_nonce_field('wpmazic_metabox', 'wpmazic_metabox_nonce');

        // Retrieve all meta values.
        $meta = array();
        foreach ($this->meta_keys as $key) {
            $meta[$key] = $this->get_meta($post->ID, $key);
        }

        // Defaults.
        $twitter_card = !empty($meta['twitter_card']) ? $meta['twitter_card'] : 'summary_large_image';
        $schema_type = !empty($meta['schema_type']) ? $meta['schema_type'] : 'default';
        $keywords_limit = 0;
        $faq_limit = 0;
        $faq_items_raw = !empty($meta['faq_items']) ? maybe_unserialize($meta['faq_items']) : array();
        if (!is_array($faq_items_raw)) {
            $faq_items_raw = array();
        }
        $faq_items = array();
        foreach ($faq_items_raw as $faq_item) {
            if (!is_array($faq_item)) {
                continue;
            }
            $question = isset($faq_item['question']) ? sanitize_text_field($faq_item['question']) : '';
            $answer = isset($faq_item['answer']) ? sanitize_textarea_field($faq_item['answer']) : '';
            if ('' === trim((string) $question) && '' === trim((string) $answer)) {
                continue;
            }
            $faq_items[] = array(
                'question' => $question,
                'answer' => $answer,
            );
        }
        if (empty($faq_items)) {
            $faq_items[] = array('question' => '', 'answer' => '');
        }

        $site_title = get_bloginfo('name');
        $post_title = get_the_title($post);
        $sep = '-';

        ?>

        <div class="wpmazic-metabox-wrap">

            <!-- Tabs Navigation -->
            <ul class="wpmazic-metabox-tabs">
                <li><a href="#wpmazic-tab-general" class="wpmazic-tab-link wpmazic-tab-active"
                        data-tab="wpmazic-tab-general"><?php esc_html_e('General', 'wpmazic-seo-lite'); ?></a></li>
                <li><a href="#wpmazic-tab-social" class="wpmazic-tab-link"
                        data-tab="wpmazic-tab-social"><?php esc_html_e('Social', 'wpmazic-seo-lite'); ?></a></li>
                <li><a href="#wpmazic-tab-advanced" class="wpmazic-tab-link"
                        data-tab="wpmazic-tab-advanced"><?php esc_html_e('Advanced', 'wpmazic-seo-lite'); ?></a></li>
                <li><a href="#wpmazic-tab-schema" class="wpmazic-tab-link"
                        data-tab="wpmazic-tab-schema"><?php esc_html_e('Schema', 'wpmazic-seo-lite'); ?></a></li>
            </ul>

            <!-- ======================== GENERAL TAB ======================== -->
            <div id="wpmazic-tab-general" class="wpmazic-tab-content wpmazic-tab-active">

                <!-- SEO Score Indicator -->
                <div id="wpmazic-seo-score" class="wpmazic-seo-score wpmazic-score-ok">
                    <span class="wpmazic-score-dot"></span>
                    <span id="wpmazic-score-label"><?php esc_html_e('SEO Score: Analyzing…', 'wpmazic-seo-lite'); ?></span>
                </div>

                <!-- Google Search Preview -->
                <div class="wpmazic-preview" id="wpmazic-serp-preview">
                    <p class="wpmazic-preview-title" id="wpmazic-preview-title">
                        <?php echo esc_html(!empty($meta['title']) ? $meta['title'] : $post_title . ' ' . $sep . ' ' . $site_title); ?>
                    </p>
                    <p class="wpmazic-preview-url"><?php echo esc_url(get_permalink($post->ID)); ?></p>
                    <p class="wpmazic-preview-desc" id="wpmazic-preview-desc">
                        <?php echo esc_html(!empty($meta['description']) ? $meta['description'] : wp_trim_words($post->post_content, 25, '…')); ?>
                    </p>
                </div>

                <!-- SEO Title -->
                <div class="wpmazic-field">
                    <label for="wpmazic_title"><?php esc_html_e('SEO Title', 'wpmazic-seo-lite'); ?></label>
                    <input type="text" id="wpmazic_title" name="wpmazic_title" value="<?php echo esc_attr($meta['title']); ?>"
                        placeholder="<?php echo esc_attr($post_title . ' ' . $sep . ' ' . $site_title); ?>" maxlength="120" />
                    <span class="wpmazic-char-count"
                        id="wpmazic-title-count"><?php echo esc_html(strlen((string) $meta['title'])); ?> / 60
                        <?php esc_html_e('characters', 'wpmazic-seo-lite'); ?></span>
                    <span
                        class="description"><?php esc_html_e('Recommended: 50–60 characters. The title tag displayed in search results.', 'wpmazic-seo-lite'); ?></span>
                </div>

                <!-- Meta Description -->
                <div class="wpmazic-field">
                    <label for="wpmazic_description"><?php esc_html_e('Meta Description', 'wpmazic-seo-lite'); ?></label>
                    <textarea id="wpmazic_description" name="wpmazic_description" rows="3" maxlength="320"
                        placeholder="<?php esc_attr_e('Enter a meta description…', 'wpmazic-seo-lite'); ?>"><?php echo esc_textarea($meta['description']); ?></textarea>
                    <span class="wpmazic-char-count"
                        id="wpmazic-desc-count"><?php echo esc_html(strlen((string) $meta['description'])); ?> / 160
                        <?php esc_html_e('characters', 'wpmazic-seo-lite'); ?></span>
                    <span
                        class="description"><?php esc_html_e('Recommended: 120–160 characters. Shown beneath the title in search results.', 'wpmazic-seo-lite'); ?></span>
                </div>

                <!-- Focus Keyword (Primary) -->
                <div class="wpmazic-field">
                    <label for="wpmazic_keyword"><?php esc_html_e('Focus Keyword (Primary)', 'wpmazic-seo-lite'); ?></label>
                    <input type="text" id="wpmazic_keyword" name="wpmazic_keyword"
                        value="<?php echo esc_attr($meta['keyword']); ?>"
                        placeholder="<?php esc_attr_e('Enter primary focus keyword', 'wpmazic-seo-lite'); ?>" />
                    <span
                        class="description"><?php esc_html_e('The main keyword you want this page to rank for.', 'wpmazic-seo-lite'); ?></span>
                </div>

                <!-- Content Analysis Results (populated via JS) -->
                <div class="wpmazic-analysis-results" id="wpmazic-analysis-results">
                    <h4><?php esc_html_e('Content Analysis', 'wpmazic-seo-lite'); ?></h4>
                    <ul id="wpmazic-analysis-list">
                        <li class="wpmazic-check-warn">
                            <?php esc_html_e('Enter a focus keyword to see content analysis.', 'wpmazic-seo-lite'); ?>
                        </li>
                    </ul>
                </div>

            </div><!-- /General -->

            <!-- ======================== SOCIAL TAB ======================== -->
            <div id="wpmazic-tab-social" class="wpmazic-tab-content">

                <h3 style="margin-top:0;"><?php esc_html_e('Facebook / Open Graph', 'wpmazic-seo-lite'); ?></h3>

                <!-- OG Title -->
                <div class="wpmazic-field">
                    <label for="wpmazic_og_title"><?php esc_html_e('OG Title', 'wpmazic-seo-lite'); ?></label>
                    <input type="text" id="wpmazic_og_title" name="wpmazic_og_title"
                        value="<?php echo esc_attr($meta['og_title']); ?>" placeholder="<?php echo esc_attr($post_title); ?>" />
                    <span
                        class="description"><?php esc_html_e('Title displayed when shared on Facebook and other social platforms.', 'wpmazic-seo-lite'); ?></span>
                </div>

                <!-- OG Description -->
                <div class="wpmazic-field">
                    <label for="wpmazic_og_description"><?php esc_html_e('OG Description', 'wpmazic-seo-lite'); ?></label>
                    <textarea id="wpmazic_og_description" name="wpmazic_og_description" rows="3"
                        placeholder="<?php esc_attr_e('Enter Open Graph description…', 'wpmazic-seo-lite'); ?>"><?php echo esc_textarea($meta['og_description']); ?></textarea>
                </div>

                <!-- OG Image -->
                <div class="wpmazic-field">
                    <label><?php esc_html_e('OG Image', 'wpmazic-seo-lite'); ?></label>
                    <div class="wpmazic-image-upload-wrap">
                        <?php if (!empty($meta['og_image'])): ?>
                            <img src="<?php echo esc_url($meta['og_image']); ?>" class="wpmazic-image-preview"
                                id="wpmazic-og-image-preview" />
                        <?php else: ?>
                            <img src="" class="wpmazic-image-preview" id="wpmazic-og-image-preview" style="display:none;" />
                        <?php endif; ?>
                        <input type="hidden" id="wpmazic_og_image" name="wpmazic_og_image"
                            value="<?php echo esc_url($meta['og_image']); ?>" />
                        <button type="button" class="button wpmazic-upload-image" data-target="wpmazic_og_image"
                            data-preview="wpmazic-og-image-preview"><?php esc_html_e('Upload Image', 'wpmazic-seo-lite'); ?></button>
                        <button type="button" class="button wpmazic-remove-image" data-target="wpmazic_og_image"
                            data-preview="wpmazic-og-image-preview" <?php echo empty($meta['og_image']) ? 'style="display:none;"' : ''; ?>><?php esc_html_e('Remove', 'wpmazic-seo-lite'); ?></button>
                    </div>
                    <span
                        class="description"><?php esc_html_e('Recommended size: 1200×630 pixels.', 'wpmazic-seo-lite'); ?></span>
                </div>

                <hr />
                <h3><?php esc_html_e('Twitter', 'wpmazic-seo-lite'); ?></h3>

                <!-- Twitter Card Type -->
                <div class="wpmazic-field">
                    <label for="wpmazic_twitter_card"><?php esc_html_e('Twitter Card Type', 'wpmazic-seo-lite'); ?></label>
                    <select id="wpmazic_twitter_card" name="wpmazic_twitter_card">
                        <option value="summary_large_image" <?php selected($twitter_card, 'summary_large_image'); ?>>
                            <?php esc_html_e('Summary with Large Image', 'wpmazic-seo-lite'); ?>
                        </option>
                        <option value="summary" <?php selected($twitter_card, 'summary'); ?>>
                            <?php esc_html_e('Summary', 'wpmazic-seo-lite'); ?>
                        </option>
                        <option value="player" <?php selected($twitter_card, 'player'); ?>>
                            <?php esc_html_e('Player', 'wpmazic-seo-lite'); ?>
                        </option>
                        <option value="app" <?php selected($twitter_card, 'app'); ?>>
                            <?php esc_html_e('App', 'wpmazic-seo-lite'); ?>
                        </option>
                    </select>
                </div>

                <!-- Twitter Title -->
                <div class="wpmazic-field">
                    <label for="wpmazic_twitter_title"><?php esc_html_e('Twitter Title', 'wpmazic-seo-lite'); ?></label>
                    <input type="text" id="wpmazic_twitter_title" name="wpmazic_twitter_title"
                        value="<?php echo esc_attr($meta['twitter_title']); ?>"
                        placeholder="<?php echo esc_attr($post_title); ?>" />
                </div>

                <!-- Twitter Description -->
                <div class="wpmazic-field">
                    <label
                        for="wpmazic_twitter_description"><?php esc_html_e('Twitter Description', 'wpmazic-seo-lite'); ?></label>
                    <textarea id="wpmazic_twitter_description" name="wpmazic_twitter_description" rows="3"
                        placeholder="<?php esc_attr_e('Enter Twitter description…', 'wpmazic-seo-lite'); ?>"><?php echo esc_textarea($meta['twitter_description']); ?></textarea>
                </div>

                <!-- Twitter Image -->
                <div class="wpmazic-field">
                    <label><?php esc_html_e('Twitter Image', 'wpmazic-seo-lite'); ?></label>
                    <div class="wpmazic-image-upload-wrap">
                        <?php if (!empty($meta['twitter_image'])): ?>
                            <img src="<?php echo esc_url($meta['twitter_image']); ?>" class="wpmazic-image-preview"
                                id="wpmazic-twitter-image-preview" />
                        <?php else: ?>
                            <img src="" class="wpmazic-image-preview" id="wpmazic-twitter-image-preview" style="display:none;" />
                        <?php endif; ?>
                        <input type="hidden" id="wpmazic_twitter_image" name="wpmazic_twitter_image"
                            value="<?php echo esc_url($meta['twitter_image']); ?>" />
                        <button type="button" class="button wpmazic-upload-image" data-target="wpmazic_twitter_image"
                            data-preview="wpmazic-twitter-image-preview"><?php esc_html_e('Upload Image', 'wpmazic-seo-lite'); ?></button>
                        <button type="button" class="button wpmazic-remove-image" data-target="wpmazic_twitter_image"
                            data-preview="wpmazic-twitter-image-preview" <?php echo empty($meta['twitter_image']) ? 'style="display:none;"' : ''; ?>><?php esc_html_e('Remove', 'wpmazic-seo-lite'); ?></button>
                    </div>
                    <span
                        class="description"><?php esc_html_e('Recommended size: 1200×628 pixels for Summary with Large Image.', 'wpmazic-seo-lite'); ?></span>
                </div>

            </div><!-- /Social -->

            <!-- ======================== ADVANCED TAB ======================== -->
            <div id="wpmazic-tab-advanced" class="wpmazic-tab-content">

                <!-- Robots Meta -->
                <div class="wpmazic-field">
                    <label><?php esc_html_e('Robots Meta', 'wpmazic-seo-lite'); ?></label>
                    <div class="wpmazic-checkbox-field">
                        <label>
                            <input type="checkbox" name="wpmazic_noindex" value="1" <?php checked($meta['noindex'], '1'); ?> />
                            <strong><?php esc_html_e('No Index', 'wpmazic-seo-lite'); ?></strong>
                            — <?php esc_html_e('Prevent search engines from indexing this page.', 'wpmazic-seo-lite'); ?>
                        </label>
                    </div>
                    <div class="wpmazic-checkbox-field">
                        <label>
                            <input type="checkbox" name="wpmazic_nofollow" value="1" <?php checked($meta['nofollow'], '1'); ?> />
                            <strong><?php esc_html_e('No Follow', 'wpmazic-seo-lite'); ?></strong>
                            —
                            <?php esc_html_e('Prevent search engines from following links on this page.', 'wpmazic-seo-lite'); ?>
                        </label>
                    </div>
                    <div class="wpmazic-checkbox-field">
                        <label>
                            <input type="checkbox" name="wpmazic_noarchive" value="1" <?php checked($meta['noarchive'], '1'); ?> />
                            <strong><?php esc_html_e('No Archive', 'wpmazic-seo-lite'); ?></strong>
                            —
                            <?php esc_html_e('Prevent cached archive copy display in search results.', 'wpmazic-seo-lite'); ?>
                        </label>
                    </div>
                    <div class="wpmazic-checkbox-field">
                        <label>
                            <input type="checkbox" name="wpmazic_nosnippet" value="1" <?php checked($meta['nosnippet'], '1'); ?> />
                            <strong><?php esc_html_e('No Snippet', 'wpmazic-seo-lite'); ?></strong>
                            — <?php esc_html_e('Prevent text snippets in search result listings.', 'wpmazic-seo-lite'); ?>
                        </label>
                    </div>
                    <div class="wpmazic-checkbox-field">
                        <label>
                            <input type="checkbox" name="wpmazic_noimageindex" value="1" <?php checked($meta['noimageindex'], '1'); ?> />
                            <strong><?php esc_html_e('No Image Index', 'wpmazic-seo-lite'); ?></strong>
                            — <?php esc_html_e('Prevent images on this page from being indexed.', 'wpmazic-seo-lite'); ?>
                        </label>
                    </div>
                </div>

                <!-- Canonical URL -->
                <div class="wpmazic-field">
                    <label for="wpmazic_canonical"><?php esc_html_e('Canonical URL', 'wpmazic-seo-lite'); ?></label>
                    <input type="url" id="wpmazic_canonical" name="wpmazic_canonical"
                        value="<?php echo esc_url($meta['canonical']); ?>"
                        placeholder="<?php echo esc_url(get_permalink($post->ID)); ?>" />
                    <span
                        class="description"><?php esc_html_e('Override the default canonical URL. Leave blank to use the post permalink.', 'wpmazic-seo-lite'); ?></span>
                </div>

                <!-- Hreflang Map -->
                <div class="wpmazic-field">
                    <label for="wpmazic_hreflang_map"><?php esc_html_e('Hreflang Alternates', 'wpmazic-seo-lite'); ?></label>
                    <textarea id="wpmazic_hreflang_map" name="wpmazic_hreflang_map" rows="4"
                        placeholder="en-US|https://example.com/en/page&#10;fr-FR|https://example.com/fr/page&#10;x-default|https://example.com/page"><?php echo esc_textarea($meta['hreflang_map']); ?></textarea>
                    <span
                        class="description"><?php esc_html_e('One per line. Format: locale|url (or locale=url). Example: en-US|https://example.com/en/page', 'wpmazic-seo-lite'); ?></span>
                </div>

                <!-- Redirect URL -->
                <div class="wpmazic-field">
                    <label for="wpmazic_redirect"><?php esc_html_e('Redirect URL', 'wpmazic-seo-lite'); ?></label>
                    <input type="url" id="wpmazic_redirect" name="wpmazic_redirect"
                        value="<?php echo esc_url($meta['redirect']); ?>"
                        placeholder="<?php esc_attr_e('https://example.com/new-page', 'wpmazic-seo-lite'); ?>" />
                    <span
                        class="description"><?php esc_html_e('301 redirect this post to another URL. Leave blank to disable.', 'wpmazic-seo-lite'); ?></span>
                </div>

                <!-- Breadcrumb Title -->
                <div class="wpmazic-field">
                    <label for="wpmazic_breadcrumb_title"><?php esc_html_e('Breadcrumb Title', 'wpmazic-seo-lite'); ?></label>
                    <input type="text" id="wpmazic_breadcrumb_title" name="wpmazic_breadcrumb_title"
                        value="<?php echo esc_attr($meta['breadcrumb_title']); ?>"
                        placeholder="<?php echo esc_attr($post_title); ?>" />
                    <span
                        class="description"><?php esc_html_e('Custom title used in breadcrumb navigation. Defaults to post title.', 'wpmazic-seo-lite'); ?></span>
                </div>

                <!-- Cornerstone Content -->
                <div class="wpmazic-field">
                    <div class="wpmazic-checkbox-field">
                        <label>
                            <input type="checkbox" name="wpmazic_cornerstone" value="1" <?php checked($meta['cornerstone'], '1'); ?> />
                            <strong><?php esc_html_e('Cornerstone Content', 'wpmazic-seo-lite'); ?></strong>
                            —
                            <?php esc_html_e('Mark this as cornerstone content (most important, comprehensive articles on your site).', 'wpmazic-seo-lite'); ?>
                        </label>
                    </div>
                </div>

            </div><!-- /Advanced -->

            <!-- ======================== SCHEMA TAB ======================== -->
            <div id="wpmazic-tab-schema" class="wpmazic-tab-content">

                <div class="wpmazic-field">
                    <label for="wpmazic_schema_type"><?php esc_html_e('Schema Type', 'wpmazic-seo-lite'); ?></label>
                    <?php
                    $schema_options = array(
                        'default' => __('Default (auto-detect)', 'wpmazic-seo-lite'),
                        'Article' => __('Article', 'wpmazic-seo-lite'),
                        'NewsArticle' => __('NewsArticle', 'wpmazic-seo-lite'),
                        'BlogPosting' => __('BlogPosting', 'wpmazic-seo-lite'),
                        'Product' => __('Product', 'wpmazic-seo-lite'),
                        'FAQ' => __('FAQ', 'wpmazic-seo-lite'),
                        'HowTo' => __('HowTo', 'wpmazic-seo-lite'),
                        'Recipe' => __('Recipe', 'wpmazic-seo-lite'),
                        'Event' => __('Event', 'wpmazic-seo-lite'),
                        'Review' => __('Review', 'wpmazic-seo-lite'),
                        'VideoObject' => __('VideoObject', 'wpmazic-seo-lite'),
                        'Course' => __('Course', 'wpmazic-seo-lite'),
                        'JobPosting' => __('JobPosting', 'wpmazic-seo-lite'),
                        'LocalBusiness' => __('LocalBusiness', 'wpmazic-seo-lite'),
                        'none' => __('None (disable schema)', 'wpmazic-seo-lite'),
                    );
                    ?>
                    <select id="wpmazic_schema_type" name="wpmazic_schema_type">
                        <?php foreach ($schema_options as $schema_key => $schema_label): ?>
                            <option value="<?php echo esc_attr($schema_key); ?>" <?php selected($schema_type, $schema_key); ?>>
                                <?php echo esc_html($schema_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span
                        class="description"><?php esc_html_e('Choose the structured data schema type for this content. "Default" will auto-detect based on post type.', 'wpmazic-seo-lite'); ?></span>
                </div>

                <div class="wpmazic-field">
                    <label><?php esc_html_e('FAQ Rich Snippet Items', 'wpmazic-seo-lite'); ?></label>
                    <div class="wpmazic-faq-items-wrap" id="wpmazic-faq-items-wrap"
                        data-faq-limit="<?php echo esc_attr((string) $faq_limit); ?>">
                        <?php foreach ($faq_items as $i => $item): ?>
                            <?php
                            $question = isset($item['question']) ? $item['question'] : '';
                            $answer = isset($item['answer']) ? $item['answer'] : '';
                            ?>
                            <div class="wpmazic-faq-item-row">
                                <div class="wpmazic-faq-item-fields">
                                    <label
                                        class="wpmazic-faq-question-label"><?php printf(esc_html__('Question %d', 'wpmazic-seo-lite'), $i + 1); ?></label>
                                    <input type="text" name="wpmazic_faq_question[]" value="<?php echo esc_attr($question); ?>"
                                        placeholder="<?php esc_attr_e('Enter question', 'wpmazic-seo-lite'); ?>" />
                                    <label class="wpmazic-faq-answer-label"
                                        style="margin:8px 0 4px;"><?php printf(esc_html__('Answer %d', 'wpmazic-seo-lite'), $i + 1); ?></label>
                                    <textarea name="wpmazic_faq_answer[]" rows="3"
                                        placeholder="<?php esc_attr_e('Enter short answer', 'wpmazic-seo-lite'); ?>"><?php echo esc_textarea($answer); ?></textarea>
                                </div>
                                <button type="button" class="button-link-delete wpmazic-faq-remove"
                                    aria-label="<?php esc_attr_e('Remove FAQ item', 'wpmazic-seo-lite'); ?>"><?php esc_html_e('Remove', 'wpmazic-seo-lite'); ?></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="wpmazic-faq-actions">
                        <button type="button" class="button button-secondary"
                            id="wpmazic-faq-add"><?php esc_html_e('Add FAQ item', 'wpmazic-seo-lite'); ?></button>
                    </div>
                    <span class="description">
                        <?php esc_html_e('Add question/answer pairs for FAQ schema on this page.', 'wpmazic-seo-lite'); ?>
                    </span>
                </div>

            </div><!-- /Schema -->

        </div><!-- /.wpmazic-metabox-wrap -->

        <?php if ( false ) :
        $metabox_js = '
        (function($){
            "use strict";

            /* ============================
             * TABS
             * ============================ */
            $(document).on("click", ".wpmazic-tab-link", function(e){
                e.preventDefault();
                var tab = $(this).data("tab");
                $(".wpmazic-tab-link").removeClass("wpmazic-tab-active");
                $(this).addClass("wpmazic-tab-active");
                $(".wpmazic-tab-content").removeClass("wpmazic-tab-active");
                $("#" + tab).addClass("wpmazic-tab-active");
            });

            /* ============================
             * LIVE PREVIEW + CHAR COUNTERS
             * ============================ */
            var defaultTitle = ' . wp_json_encode($post_title . ' ' . $sep . ' ' . $site_title) . ';
            var defaultDesc  = ' . wp_json_encode(wp_trim_words($post->post_content, 25, '…')) . ';

            function updatePreview() {
                var title = $("#wpmazic_title").val() || defaultTitle;
                var desc  = $("#wpmazic_description").val() || defaultDesc;
                $("#wpmazic-preview-title").text( title );
                $("#wpmazic-preview-desc").text( desc );
            }
        ';
        ?>
        <legacy-script>
            function updateCharCount(inputId, countId, recommended) {
                var len = $('#' + inputId).val().length;
                var $counter = $('#' + countId);
                $counter.text(len + ' / ' + recommended + ' <?php echo esc_js(__('characters', 'wpmazic-seo-lite')); ?>');
                $counter.removeClass('wpmazic-char-warning wpmazic-char-good');
                if (len === 0) {
                    // neutral
                } else if (len > recommended) {
                    $counter.addClass('wpmazic-char-warning');
                } else if (len >= Math.round(recommended * 0.7)) {
                    $counter.addClass('wpmazic-char-good');
                }
            }

            $('#wpmazic_title').on('input keyup', function () {
                updateCharCount('wpmazic_title', 'wpmazic-title-count', 60);
                updatePreview();
            });

            $('#wpmazic_description').on('input keyup', function () {
                updateCharCount('wpmazic_description', 'wpmazic-desc-count', 160);
                updatePreview();
            });

            // Initialize.
            updateCharCount('wpmazic_title', 'wpmazic-title-count', 60);
            updateCharCount('wpmazic_description', 'wpmazic-desc-count', 160);
            updatePreview();

            /* ============================
            * FAQ REPEATER
            * ============================ */
            var faqLimit = <?php echo (int) $faq_limit; ?>;
            var faqQuestionLabel = <?php echo wp_json_encode(__('Question', 'wpmazic-seo-lite')); ?>;
            var faqAnswerLabel = <?php echo wp_json_encode(__('Answer', 'wpmazic-seo-lite')); ?>;
            var faqQuestionPlaceholder = <?php echo wp_json_encode(__('Enter question', 'wpmazic-seo-lite')); ?>;
            var faqAnswerPlaceholder = <?php echo wp_json_encode(__('Enter short answer', 'wpmazic-seo-lite')); ?>;

            function buildFaqRow(item, index) {
                item = item || {};
                var $row = $('
                    < div /> ', { 'class': 'wpmazic - faq - item - row' });
                    var $fields = $('
                        < div /> ', { 'class': 'wpmazic - faq - item - fields' }).appendTo($row);

                    $('<label />', {
                            'class': 'wpmazic-faq-question-label',
                            text: faqQuestionLabel + ' ' + (index + 1)
                        }).appendTo($fields);
                $('<input />', {
                    type: 'text',
                    name: 'wpmazic_faq_question[]',
                    value: item.question || '',
                    placeholder: faqQuestionPlaceholder
                }).appendTo($fields);
                $('<label />', {
                    'class': 'wpmazic-faq-answer-label',
                    text: faqAnswerLabel + ' ' + (index + 1),
                    style: 'margin:8px 0 4px;'
                }).appendTo($fields);
                $('<textarea />', {
                    name: 'wpmazic_faq_answer[]',
                    rows: 3,
                    placeholder: faqAnswerPlaceholder
                }).val(item.answer || '').appendTo($fields);
                $('<button />', {
                    type: 'button',
                    'class': 'button-link-delete wpmazic-faq-remove',
                    'aria-label': '<?php echo esc_js(__('Remove FAQ item', 'wpmazic-seo-lite')); ?>',
                    text: '<?php echo esc_js(__('Remove', 'wpmazic-seo-lite')); ?>'
                }).appendTo($row);

                return $row;
            }

            function refreshFaqRows() {
                var $wrap = $('#wpmazic-faq-items-wrap');
                if (!$wrap.length) {
                    return;
                }

                var $rows = $wrap.find('.wpmazic-faq-item-row');
                if (!$rows.length) {
                    $wrap.append(buildFaqRow({}, 0));
                    $rows = $wrap.find('.wpmazic-faq-item-row');
                }

                $rows.each(function (index) {
                    $(this).find('.wpmazic-faq-question-label').text(faqQuestionLabel + ' ' + (index + 1));
                    $(this).find('.wpmazic-faq-answer-label').text(faqAnswerLabel + ' ' + (index + 1));
                });

                var rowCount = $rows.length;
                var canAdd = faqLimit === 0 || rowCount < faqLimit; $('#wpmazic-faq-add').prop('disabled', !canAdd);
                $rows.find('.wpmazic-faq-remove').show(); if (rowCount === 1) {
                    $rows.first().find('.wpmazic-faq-remove').hide();
                }
            } $(document).on('click', '#wpmazic-faq-add', function (e) {
                e.preventDefault(); var $wrap = $('#wpmazic-faq-items-wrap'); if (!$wrap.length) { return; } var
                    rowCount = $wrap.find('.wpmazic-faq-item-row').length; if (faqLimit > 0 && rowCount >= faqLimit) {
                        return;
                    }

                $wrap.append(buildFaqRow({}, rowCount));
                refreshFaqRows();
            });

            $(document).on('click', '.wpmazic-faq-remove', function (e) {
                e.preventDefault();
                $(this).closest('.wpmazic-faq-item-row').remove();
                refreshFaqRows();
            });

            refreshFaqRows();

            /* ============================
            * SEO SCORE + CONTENT ANALYSIS
            * ============================ */
            function runAnalysis() {
                var keyword = $('#wpmazic_keyword').val().trim().toLowerCase();
                var title = $('#wpmazic_title').val().trim();
                var desc = $('#wpmazic_description').val().trim();
                var checks = [];
                var score = 0;
                var total = 0;
                var contentRaw = '';
                var contentText = '';
                var words = [];
                var sentences = [];
                var wordCount = 0;

                if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                    contentRaw = tinymce.get('content').getContent({ format: 'raw' }) || '';
                }
                if (!contentRaw) {
                    contentRaw = $('#content').val() || <?php echo wp_json_encode((string) $post->post_content); ?>;
                }

                contentText = String(contentRaw)
                    .replace(/<style[\s\S]*?<\ /style >/gi, ' ')
                    .replace(/<script[\s\S]*?<\ /script >/gi, ' ')
                    .replace(/<\ / ? [^>] +>/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim()
                    .toLowerCase();
                words = contentText ? contentText.split(' ') : [];
                wordCount = words.length;
                sentences = contentText ? contentText.split(/[.!?]+/).filter(function (item) { return item.trim(); })
                    : [];

                if (!keyword) {
                    $('#wpmazic-analysis-list').html('<li class="wpmazic-check-warn"><?php echo esc_js(__('Enter a focus keyword to see content analysis.', 'wpmazic-seo-lite')); ?></li>');
                    $('#wpmazic-seo-score').removeClass('wpmazic-score-good wpmazic-score-ok
                                    wpmazic - score - bad').addClass('wpmazic - score - ok');
                                    $('#wpmazic-score-label').text('<?php echo esc_js(__('SEO Score: N/A', 'wpmazic-seo-lite')); ?>');
                    return;
                }

                // Check: Keyword in title
                total++;
                if (title.toLowerCase().indexOf(keyword) !== -1) {
                    checks.push({ status: 'good', text: '<?php echo esc_js(__('Focus keyword found in SEO title.', 'wpmazic-seo-lite')); ?>' });
                    score++;
                } else {
                    checks.push({ status: 'bad', text: '<?php echo esc_js(__('Focus keyword not found in SEO title.', 'wpmazic-seo-lite')); ?>' });
                }

                // Check: Keyword in description
                total++;
                if (desc.toLowerCase().indexOf(keyword) !== -1) {
                    checks.push({ status: 'good', text: '<?php echo esc_js(__('Focus keyword found in meta description.', 'wpmazic-seo-lite')); ?>' });
                    score++;
                } else {
                    checks.push({ status: 'bad', text: '<?php echo esc_js(__('Focus keyword not found in meta description.', 'wpmazic-seo-lite')); ?>' });
                }

                // Check: Title length
                total++;
                if (title.length >= 30 && title.length <= 60) {
                    checks.push({
                        status: 'good',
                        text: '<?php echo esc_js(__('SEO title length is optimal.', 'wpmazic-seo-lite')); ?>'
                    });
                    score++;
                } else if (title.length > 0 && title.length < 30) {
                    checks.push({
                        status: 'warn',
                        text: '<?php echo esc_js(__('SEO title is too short. Aim for 50–60 characters.', 'wpmazic-seo-lite')); ?>'
                    }); score += 0.5;
                } else if (title.length > 60) {
                    checks.push({ status: 'warn', text: '<?php echo esc_js(__('SEO title is too long. Aim for 50–60 characters.', 'wpmazic-seo-lite')); ?>' });
                    score += 0.5;
                } else {
                    checks.push({ status: 'bad', text: '<?php echo esc_js(__('SEO title is empty.', 'wpmazic-seo-lite')); ?>' });
                }

                // Check: Description length
                total++;
                if (desc.length >= 120 && desc.length <= 160) {
                    checks.push({
                        status: 'good',
                        text: '<?php echo esc_js(__('Meta description length is optimal.', 'wpmazic-seo-lite')); ?>'
                    }); score++;
                } else if (desc.length > 0 && desc.length < 120) {
                    checks.push({
                        status: 'warn',
                        text: '<?php echo esc_js(__('Meta description is short. Aim for 120–160 characters.', 'wpmazic-seo-lite')); ?>'
                    }); score += 0.5;
                } else if (desc.length > 160) {
                    checks.push({ status: 'warn', text: '<?php echo esc_js(__('Meta description is too long. Aim for 120–160 characters.', 'wpmazic-seo-lite')); ?>' });
                    score += 0.5;
                } else {
                    checks.push({ status: 'bad', text: '<?php echo esc_js(__('Meta description is empty.', 'wpmazic-seo-lite')); ?>' });
                }

                // Check: Keyword at beginning of title
                total++;
                if (title.toLowerCase().indexOf(keyword) === 0) {
                    checks.push({ status: 'good', text: '<?php echo esc_js(__('Focus keyword appears at the beginning of the SEO title.', 'wpmazic-seo-lite')); ?>' });
                    score++;
                } else if (title.toLowerCase().indexOf(keyword) > 0) {
                    checks.push({ status: 'warn', text: '<?php echo esc_js(__('Focus keyword does not appear at the beginning of the SEO title.', 'wpmazic-seo-lite')); ?>' });
                    score += 0.5;
                } else {
                    checks.push({ status: 'bad', text: '<?php echo esc_js(__('Focus keyword is missing from the SEO title.', 'wpmazic-seo-lite')); ?>' });
                }

                // Check: Content length recommendation
                total++;
                if (wordCount >= 600) {
                    checks.push({ status: 'good', text: '<?php echo esc_js(__('Content length is strong (600+ words).', 'wpmazic-seo-lite')); ?>' });
                    score++;
                } else if (wordCount >= 300) {
                    checks.push({ status: 'warn', text: '<?php echo esc_js(__('Content length is moderate. Add more depth for competitive topics.', 'wpmazic-seo-lite')); ?>' });
                    score += 0.5;
                } else {
                    checks.push({ status: 'bad', text: '<?php echo esc_js(__('Content appears thin (under 300 words).', 'wpmazic-seo-lite')); ?>' });
                }

                // Check: Keyword density
                total++;
                var keywordOccurrences = 0;
                if (keyword) {
                    var escapedKeyword = keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    var keywordRegex = new RegExp('\\b' + escapedKeyword + '\\b', 'gi');
                    var keywordMatch = contentText.match(keywordRegex);
                    keywordOccurrences = keywordMatch ? keywordMatch.length : 0;
                }
                var density = wordCount > 0 ? (keywordOccurrences / wordCount) * 100 : 0;
                if (density >= 0.5 && density <= 2.5) {
                    checks.push({
                        status: 'good',
                        text: '<?php echo esc_js(__('Keyword density is in a natural range.', 'wpmazic-seo-lite')); ?>'
                            + ' (' + density.toFixed(2) + '%)'
                    }); score++;
                } else if (density > 0 &&
                    density < 0.5) {
                        checks.push({
                            status: 'warn',
                            text: '<?php echo esc_js(__('Keyword density is low. Add natural mentions.', 'wpmazic-seo-lite')); ?>'
                                + ' (' + density.toFixed(2) + '%)'
                        }); score += 0.5;
                } else if (density > 2.5
                ) {
                    checks.push({
                        status: 'warn', text: '<?php echo esc_js(__('Keyword density may be too high.', 'wpmazic-seo-lite')); ?>' + ' (' + density.toFixed(2) +
                            '%)'
                    });
                    score += 0.5;
                } else {
                    checks.push({ status: 'bad', text: '<?php echo esc_js(__('Keyword not found in content body.', 'wpmazic-seo-lite')); ?>' });
                }

                // Check: Heading structure
                total++;
                var h1Count = (String(contentRaw).match(/<h1\b /gi) || []).length; var
                    h2Count = (String(contentRaw).match(/<h2\b/gi) || []).length; if (
                    h1Count === 1 && h2Count >= 1) {
                    checks.push({ status: 'good', text: '<?php echo esc_js(__('Heading structure looks good (H1 + H2).', 'wpmazic-seo-lite')); ?>' });
                    score++;
                } else if (h1Count <= 1 && h2Count >= 1) {
                    checks.push({ status: 'warn', text: '<?php echo esc_js(__('H2 headings are present. Confirm one clear H1.', 'wpmazic-seo-lite')); ?>' });
                    score += 0.5;
                } else {
                    checks.push({ status: 'bad', text: '<?php echo esc_js(__('Heading hierarchy needs improvement.', 'wpmazic-seo-lite')); ?>' });
                }

                // Check: Internal / external links
                var linkNodes = String(contentRaw).match(/<a\b[^>
                                                                        ]* href=["'][^"']+["'][^>] *>/gi) || [];
                var internalLinks = 0;
                var externalLinks = 0;
                for (var li = 0; li < linkNodes.length; li++) {
                    var
                    hrefMatch = linkNodes[li].match(/href=["']([^"']+)["']/i); if
                        (!hrefMatch) { continue; } var href = String(hrefMatch[1]
                            || '').toLowerCase(); if (href.indexOf('mailto:') === 0 ||
                                href.indexOf('tel:') === 0 || href.indexOf('#') === 0) {
                        continue;
                    } if (href.indexOf('http') !== 0 ||
                        href.indexOf(location.hostname.toLowerCase()) !== -1) {
                        internalLinks++;
                    } else { externalLinks++; }
                } total++; if (
                    internalLinks >= 2) {
                    checks.push({
                        status: 'good', text: '<?php echo esc_js(__('Internal linking is healthy.', 'wpmazic-seo-lite')); ?>'
                            + ' (' + internalLinks + ')'
                    });
                    score++;
                } else if (internalLinks === 1) {
                    checks.push({ status: 'warn', text: '<?php echo esc_js(__('Only one internal link found. Add more contextual links.', 'wpmazic-seo-lite')); ?>' });
                    score += 0.5;
                } else {
                    checks.push({ status: 'bad', text: '<?php echo esc_js(__('No internal links found in content body.', 'wpmazic-seo-lite')); ?>' });
                }

                total++;
                if (externalLinks >= 1) {
                    checks.push({
                        status: 'good', text: '<?php echo esc_js(__('External references detected.', 'wpmazic-seo-lite')); ?>'
                            + ' (' + externalLinks + ')'
                    });
                    score++;
                } else {
                    checks.push({ status: 'warn', text: '<?php echo esc_js(__('No external references detected. Add citations where relevant.', 'wpmazic-seo-lite')); ?>' });
                    score += 0.5;
                }

                // Check: Image ALT text in content
                total++;
                var imageNodes = String(contentRaw).match(/<img\b[^>]*>/gi)
                    || [];
                var missingAlt = 0;
                for (var im = 0; im < imageNodes.length; im++) {
                    if (
                        ! /alt=["'][^"']*["']/i.test(imageNodes[im])) {
                        missingAlt++;
                    }
                } if (imageNodes.length === 0) {
                    checks.push({
                        status: 'warn',
                        text: '<?php echo esc_js(__('No inline images found in content body.', 'wpmazic-seo-lite')); ?>'
                    }); score += 0.5;
                } else if (missingAlt === 0) {
                    checks.push({
                        status: 'good',
                        text: '<?php echo esc_js(__('All detected content images include ALT text.', 'wpmazic-seo-lite')); ?>'
                    }); score++;
                } else {
                    checks.push({
                        status: 'bad',
                        text: '<?php echo esc_js(__('Some content images are missing ALT text.', 'wpmazic-seo-lite')); ?>'
                            + ' (' + missingAlt + ')'
                    });
                } // Check:
                Readability(Flesch estimate) total++; var
                    syllableCount = 0; for (var wi = 0; wi < words.length;
                    wi++) {
                        var word = String(words[wi] || ''
                        ).replace(/[^a-z]/g, ''); if (!word) {
                            continue;
                        } if (word.length <= 3) {
                            syllableCount += 1;
                            continue;
                        } var
                            reduced = word.replace(/(?:es|ed|e)$/g, ''
                            ).replace(/^y/, ''); var
                                syllables = reduced.match(/[aeiouy]{1,2}/g);
                    syllableCount += Math.max(1, syllables ?
                        syllables.length : 1);
                } var flesch = null; if (
                    wordCount > 0 && sentences.length > 0) {
                    flesch = 206.835 - 1.015 * (wordCount /
                        sentences.length) - 84.6 * (syllableCount /
                            wordCount);
                }

                if (flesch !== null && flesch >= 60) {
                    checks.push({
                        status: 'good', text: '<?php echo esc_js(__('Readability score is good.', 'wpmazic-seo-lite')); ?>' + ' (Flesch ' +
                            flesch.toFixed(1) + ')'
                    });
                    score++;
                } else if (flesch !== null && flesch >= 40) {
                    checks.push({
                        status: 'warn', text: '<?php echo esc_js(__('Readability is moderate. Simplify phrasing where possible.', 'wpmazic-seo-lite')); ?>'
                            + ' (Flesch ' + flesch.toFixed(1) + ')'
                    });
                    score += 0.5;
                } else {
                    checks.push({
                        status: 'bad', text: '<?php echo esc_js(__('Readability is difficult. Use shorter and clearer sentences.', 'wpmazic-seo-lite')); ?>'
                    });
                }

                // Check: Passive voice and sentence length
                total++;
                var passiveCount = 0;
                var passiveRegex =
                    /\b(is|are|was|were|be|been|being|am)\b\s+\w+(ed|en)\b/i;
                for (var si = 0; si < sentences.length; si++) {
                    if
                        (passiveRegex.test(sentences[si])) {
                        passiveCount++;
                    }
                } var
                    passiveRatio = sentences.length > 0 ? (
                        passiveCount / sentences.length) * 100 : 0;
                if (passiveRatio <= 10) {
                    checks.push({
                        status: 'good',
                        text: '<?php echo esc_js(__('Passive voice usage is low.', 'wpmazic-seo-lite')); ?>'
                            + ' (' + passiveRatio.toFixed(1) + '%)'
                    });
                    score++;
                } else if (passiveRatio <= 20) {
                    checks.push({
                        status: 'warn',
                        text: '<?php echo esc_js(__('Passive voice is moderate.', 'wpmazic-seo-lite')); ?>'
                            + ' (' + passiveRatio.toFixed(1) + '%)'
                    });
                    score += 0.5;
                } else {
                    checks.push({
                        status: 'bad',
                        text: '<?php echo esc_js(__('Passive voice is high. Prefer active voice.', 'wpmazic-seo-lite')); ?>'
                            + ' (' + passiveRatio.toFixed(1) + '%)'
                    });
                } total++; var
                    avgSentenceWords = sentences.length > 0 ?
                        wordCount / sentences.length : 0;
                if (avgSentenceWords >= 12 &&
                    avgSentenceWords <= 20) {
                        checks.push({
                            status: 'good',
                            text: '<?php echo esc_js(__('Average sentence length is balanced.', 'wpmazic-seo-lite')); ?>'
                                + ' (' + avgSentenceWords.toFixed(1)
                                + ')'
                        }); score++;
                } else if (
                    avgSentenceWords > 20) {
                    checks.push({
                        status: 'warn', text:
                            '<?php echo esc_js(__('Sentences are long on average. Break up complex lines.', 'wpmazic-seo-lite')); ?>'
                    });
                    score += 0.5;
                } else {
                    checks.push({
                        status: 'warn', text:
                            '<?php echo esc_js(__('Sentence length is short. Add detail where needed.', 'wpmazic-seo-lite')); ?>'
                    });
                    score += 0.5;
                }

                // Check: Featured snippet opportunity
                tip
                total++;
                var firstParagraphMatch =
                    String(contentRaw).match(/<p\b[^>
                                                                                                    ]*> ([\s\S] *?) <\ /p>/i);
                var firstParagraphWords = 0;
                if (firstParagraphMatch &&
                    firstParagraphMatch[1]) {
                    firstParagraphWords =
                        String(firstParagraphMatch[1]).replace(/
                            <\ /?[^>]+>/g, '
                                                                                                            ').trim().split(/\s+/).filter(function(item){
                                                                                                            return item;
                }).length;
            }

            if (firstParagraphWords >=
                40 && firstParagraphWords <=
                60) {
                    checks.push({
                        status: 'good',
                        text: '<?php echo esc_js(__('First paragraph is snippet-friendly (40-60 words).', 'wpmazic-seo-lite')); ?>'
                    }); score++;
            } else if (
                firstParagraphWords > 0) {
                checks.push({
                    status:
                        'warn', text: '<?php echo esc_js(__('Adjust first paragraph toward 40-60 words for snippet potential.', 'wpmazic-seo-lite')); ?>'
                });
                score += 0.5;
            } else {
                checks.push({
                    status:
                        'warn', text: '<?php echo esc_js(__('Add a clear opening paragraph to improve snippet eligibility.', 'wpmazic-seo-lite')); ?>'
                });
                score += 0.5;
            }

            // Render checks
            var html = '';
            for (var i = 0; i <
                checks.length; i++) {
                html
                += '<li class="wpmazic-check-'
                + checks[i].status
                + '">' +
                checks[i].text
                + '</li>';
            }
            $('#wpmazic-analysis-list').html(html);
            // Score rating var
            pct = (score / total
            ) * 100; var
                scoreClass,
                scoreLabel; if (
                pct >= 80) {
                scoreClass =
                    'wpmazic-score-good';
                scoreLabel = '<?php echo esc_js(__('SEO Score: Good', 'wpmazic-seo-lite')); ?>';
            } else if (pct >=
                50) {
                scoreClass =
                    'wpmazic-score-ok';
                scoreLabel = '<?php echo esc_js(__('SEO Score: Needs Improvement', 'wpmazic-seo-lite')); ?>';
            } else {
                scoreClass =
                    'wpmazic-score-bad';
                scoreLabel = '<?php echo esc_js(__('SEO Score: Poor', 'wpmazic-seo-lite')); ?>';
            }

            $('#wpmazic-seo-score')
                .removeClass('wpmazic-score-good
                                                                                                                    wpmazic - score - ok
                                                                                                                    wpmazic - score - bad')
                    .addClass(
                        scoreClass);
            $('#wpmazic-score-label').text(
                scoreLabel + ' (' +
                Math.round(pct) +
                '%)');
                                                                                                                    }

            $('#wpmazic_title,
                                                                                                                    #wpmazic_description,
                #wpmazic_keyword,
                #content').on('input
                                                                                                                    keyup', function(){
                                                                                                                    runAnalysis();
                                                                                                                    });

            if (typeof tinymce
                !== 'undefined') {
                $(document).on('tinymce-editor-init',
                    function (event,
                        editor) {
                        if (editor &&
                            editor.id ===
                            'content') {
                            editor.on('keyup
                                                                                                                    change', function(){
                                                                                                                    runAnalysis();
                        });
            }
                                                                                                                    });
                                                                                                                    }
            runAnalysis();

            /*
            ============================
            * MEDIA UPLOADER
            *
            ============================
            */
            $(document).on('click',
                '.wpmazic-upload-image',
                function (e) {
                    e.preventDefault();
                    var $btn = $(this);
                    var targetId =
                        $btn.data('target');
                    var previewId =
                        $btn.data('preview');

                    var frame =
                        wp.media({
                            title: '<?php echo esc_js(__('Select or Upload Image', 'wpmazic-seo-lite')); ?>',
                            button: {
                                text:
                                    '<?php echo esc_js(__('Use this image', 'wpmazic-seo-lite')); ?>'
                            },
                            multiple: false
                        });

                    frame.on('select',
                        function () {
                            var attachment =
                                frame.state().get('selection').first().toJSON();
                            $('#' +
                                targetId).val(
                                    attachment.url);
                            $('#' +
                                previewId).attr('src',
                                    attachment.url).show();
                            $btn.siblings('.wpmazic-remove-image').show();
                        });

                    frame.open();
                });

            $(document).on('click',
                '.wpmazic-remove-image',
                function (e) {
                    e.preventDefault();
                    var targetId =
                        $(this).data('target');
                    var previewId =
                        $(this).data('preview');
                    $('#' +
                        targetId).val('');
                    $('#' +
                        previewId).attr('src',
                            '').hide();
                    $(this).hide();
                });

                                                                                                                    }) (jQuery);
        </legacy-script>
        <?php endif;
    }

    /**
     * Save metabox data.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public function save_metabox($post_id, $post)
    {

        // 1. Verify nonce exists.
        if (!isset($_POST['wpmazic_metabox_nonce'])) {
            return;
        }

        // 2. Verify nonce is valid.
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wpmazic_metabox_nonce'])), 'wpmazic_metabox')) {
            return;
        }

        // 3. Skip autosave.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // 4. Check capabilities.
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (!$this->can_current_user_access()) {
            return;
        }

        // 5. Only save for supported post types.
        if (!in_array($post->post_type, $this->get_post_types(), true)) {
            return;
        }

        // --- Text fields (sanitize_text_field) ---
        $text_fields = array(
            'wpmazic_title' => 'title',
            'wpmazic_keyword' => 'keyword',
            'wpmazic_og_title' => 'og_title',
            'wpmazic_twitter_title' => 'twitter_title',
            'wpmazic_twitter_card' => 'twitter_card',
            'wpmazic_breadcrumb_title' => 'breadcrumb_title',
            'wpmazic_schema_type' => 'schema_type',
        );

        foreach ($text_fields as $post_key => $meta_key) {
            if (isset($_POST[$post_key])) {
                update_post_meta(
                    $post_id,
                    $this->prefix . $meta_key,
                    sanitize_text_field(wp_unslash($_POST[$post_key]))
                );
            }
        }

        // --- Textarea fields (sanitize_textarea_field) ---
        $textarea_fields = array(
            'wpmazic_description' => 'description',
            'wpmazic_og_description' => 'og_description',
            'wpmazic_twitter_description' => 'twitter_description',
            'wpmazic_hreflang_map' => 'hreflang_map',
        );

        foreach ($textarea_fields as $post_key => $meta_key) {
            if (isset($_POST[$post_key])) {
                update_post_meta(
                    $post_id,
                    $this->prefix . $meta_key,
                    sanitize_textarea_field(wp_unslash($_POST[$post_key]))
                );
            }
        }

        // --- URL fields (esc_url_raw) ---
        $url_fields = array(
            'wpmazic_og_image' => 'og_image',
            'wpmazic_twitter_image' => 'twitter_image',
            'wpmazic_canonical' => 'canonical',
            'wpmazic_redirect' => 'redirect',
        );

        foreach ($url_fields as $post_key => $meta_key) {
            if (isset($_POST[$post_key])) {
                $url_value = esc_url_raw(wp_unslash($_POST[$post_key]));
                update_post_meta($post_id, $this->prefix . $meta_key, $url_value);
            }
        }

        // --- Checkbox fields ---
        $checkbox_fields = array(
            'wpmazic_noindex' => 'noindex',
            'wpmazic_nofollow' => 'nofollow',
            'wpmazic_noarchive' => 'noarchive',
            'wpmazic_nosnippet' => 'nosnippet',
            'wpmazic_noimageindex' => 'noimageindex',
            'wpmazic_cornerstone' => 'cornerstone',
        );

        foreach ($checkbox_fields as $post_key => $meta_key) {
            $value = isset($_POST[$post_key]) ? '1' : '0';
            update_post_meta($post_id, $this->prefix . $meta_key, $value);
        }

        delete_post_meta($post_id, $this->prefix . 'keywords_extra');

        // --- FAQ schema items ---
        if (isset($_POST['wpmazic_faq_question'], $_POST['wpmazic_faq_answer']) && is_array($_POST['wpmazic_faq_question']) && is_array($_POST['wpmazic_faq_answer'])) {
            $questions = array_values(wp_unslash($_POST['wpmazic_faq_question']));
            $answers = array_values(wp_unslash($_POST['wpmazic_faq_answer']));
            $faq_items = array();
            $count = max(count($questions), count($answers));

            for ($i = 0; $i < $count; $i++) {
                $question = isset($questions[$i]) ? sanitize_text_field($questions[$i]) : '';
                $answer = isset($answers[$i]) ? sanitize_textarea_field($answers[$i]) : '';

                if ('' === trim((string) $question) || '' === trim((string) $answer)) {
                    continue;
                }

                $faq_items[] = array(
                    'question' => $question,
                    'answer' => $answer,
                );
            }

            if (!empty($faq_items)) {
                update_post_meta($post_id, $this->prefix . 'faq_items', $faq_items);
            } else {
                delete_post_meta($post_id, $this->prefix . 'faq_items');
            }
        } else {
            delete_post_meta($post_id, $this->prefix . 'faq_items');
        }

        // --- Validate schema_type against whitelist ---
        $allowed_schema = array(
            'default',
            'Article',
            'NewsArticle',
            'BlogPosting',
            'Product',
            'FAQ',
            'HowTo',
            'Recipe',
            'Event',
            'Review',
            'VideoObject',
            'Course',
            'JobPosting',
            'LocalBusiness',
            'none',
        );
        $saved_schema = get_post_meta($post_id, $this->prefix . 'schema_type', true);
        if (!in_array($saved_schema, $allowed_schema, true)) {
            update_post_meta($post_id, $this->prefix . 'schema_type', 'default');
        }

        // --- Validate twitter_card against whitelist ---
        $allowed_cards = array('summary', 'summary_large_image', 'player', 'app');
        $saved_card = get_post_meta($post_id, $this->prefix . 'twitter_card', true);
        if (!in_array($saved_card, $allowed_cards, true)) {
            update_post_meta($post_id, $this->prefix . 'twitter_card', 'summary_large_image');
        }
    }
}

?>
