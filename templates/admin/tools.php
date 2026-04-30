<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$wmz_clip_text = static function ($text, $limit = 160) {
    $text = trim(preg_replace('/\s+/', ' ', (string) $text));
    if ('' === $text) {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text) > $limit) {
            return mb_substr((string) $text, 0, $limit - 3) . '...';
        }
        return $text;
    }

    if (strlen($text) > $limit) {
        return substr((string) $text, 0, $limit - 3) . '...';
    }

    return $text;
};

$wmz_build_meta_description = static function ($post) use ($wmz_clip_text) {
    if (!$post instanceof WP_Post) {
        return '';
    }

    $source = '';
    if (!empty($post->post_excerpt)) {
        $source = $post->post_excerpt;
    } elseif (!empty($post->post_content)) {
        $source = $post->post_content;
    } else {
        $source = $post->post_title;
    }

    $source = wp_strip_all_tags(strip_shortcodes((string) $source));
    return $wmz_clip_text($source, 160);
};

$wmz_build_focus_keyword = static function ($title, $content = '') {
    $stop_words = array(
        'a',
        'an',
        'and',
        'the',
        'of',
        'for',
        'to',
        'in',
        'on',
        'with',
        'by',
        'at',
        'from',
        'is',
        'are',
        'be',
        'this',
        'that',
        'your',
        'you',
        'how',
        'what',
        'when',
        'where',
        'why',
        'who',
        'or',
        'as',
        'it',
        'its',
        'into',
        'about',
    );

    $extract_tokens = static function ($text) use ($stop_words) {
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', (string) $text);
        $tokens = preg_split('/\s+/u', trim((string) $text));
        if (!is_array($tokens)) {
            return array();
        }

        $clean = array();
        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ('' === $token) {
                continue;
            }

            $lower = function_exists('mb_strtolower') ? mb_strtolower((string) $token) : strtolower((string) $token);
            if (in_array($lower, $stop_words, true)) {
                continue;
            }

            if (preg_match('/^\d+$/', (string) $token)) {
                continue;
            }

            $length = function_exists('mb_strlen') ? mb_strlen($token) : strlen($token);
            if ($length < 3) {
                continue;
            }

            $clean[] = $token;
        }

        return $clean;
    };

    $title_tokens = $extract_tokens($title);
    if (count($title_tokens) >= 2) {
        return sanitize_text_field(implode(' ', array_slice($title_tokens, 0, 4)));
    }

    $content_tokens = $extract_tokens($content);
    $pool = array_merge($title_tokens, $content_tokens);
    if (empty($pool)) {
        return '';
    }

    $frequency = array_count_values(
        array_map(
            static function ($item) {
                return function_exists('mb_strtolower') ? mb_strtolower((string) $item) : strtolower((string) $item);
            },
            $pool
        )
    );
    arsort($frequency);

    $top_tokens = array_slice(array_keys($frequency), 0, 4);
    return sanitize_text_field(implode(' ', $top_tokens));
};

if (isset($_POST['wpmazic_save_robots']) && check_admin_referer('wpmazic_save_robots')) {
    if (!current_user_can('manage_options')) {
        wpmazic_seo_lite_add_notice('error', __('Permission denied.', 'wpmazic-seo-lite'));
    } else {
        $robots = isset($_POST['robots_content']) ? wp_unslash($_POST['robots_content']) : '';
        $robots = sanitize_textarea_field($robots);
        update_option('wpmazic_robots_txt', $robots);
        wpmazic_seo_lite_add_notice('success', __('robots.txt saved successfully.', 'wpmazic-seo-lite'));
    }
}

if (isset($_POST['wpmazic_save_llms']) && check_admin_referer('wpmazic_save_llms')) {
    if (!current_user_can('manage_options')) {
        wpmazic_seo_lite_add_notice('error', __('Permission denied.', 'wpmazic-seo-lite'));
    } else {
        $llms = isset($_POST['llms_content']) ? wp_unslash($_POST['llms_content']) : '';
        $llms = sanitize_textarea_field($llms);
        update_option('wpmazic_llms_txt', $llms);
        wpmazic_seo_lite_add_notice('success', __('llms.txt saved successfully.', 'wpmazic-seo-lite'));
    }
}

if (isset($_POST['wpmazic_optimize_db']) && check_admin_referer('wpmazic_optimize_db')) {
    if (!current_user_can('manage_options')) {
        wpmazic_seo_lite_add_notice('error', __('Permission denied.', 'wpmazic-seo-lite'));
    } else {
        $errors_table = wpmazic_seo_get_table_name( '404' );
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$errors_table} WHERE created_at < %s",
                gmdate('Y-m-d H:i:s', strtotime('-90 days'))
            )
        );

        $wpdb->query(
            "DELETE pm FROM {$wpdb->postmeta} pm
         LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE p.ID IS NULL
           AND pm.meta_key LIKE '_wpmazic\_%'"
        );

        foreach ( array( 'redirects', '404', 'links', 'indexnow' ) as $table_key ) {
            $table_name = wpmazic_seo_get_table_name( $table_key );
            if ( '' === $table_name ) {
                continue;
            }

            $wpdb->query( 'OPTIMIZE TABLE ' . $table_name ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        wpmazic_seo_lite_add_notice('success', __('Database optimized.', 'wpmazic-seo-lite'));
    }
}

if (isset($_POST['wpmazic_clear_cache']) && check_admin_referer('wpmazic_clear_cache')) {
    if (!current_user_can('manage_options')) {
        wpmazic_seo_lite_add_notice('error', __('Permission denied.', 'wpmazic-seo-lite'));
    } else {
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_wpmazic\_%'
                OR option_name LIKE '_transient_timeout_wpmazic\_%'"
        );
        do_action('wpmazic_clear_cache');
        wpmazic_seo_lite_add_notice('success', __('Plugin cache cleared.', 'wpmazic-seo-lite'));
    }
}

if (isset($_POST['wpmazic_generate_missing_meta']) && check_admin_referer('wpmazic_generate_missing_meta')) {
    if (!current_user_can('manage_options')) {
        wpmazic_seo_lite_add_notice('error', __('Permission denied.', 'wpmazic-seo-lite'));
    } else {
    $post_types = get_post_types(
        array(
            'public' => true,
        ),
        'names'
    );
    unset($post_types['attachment']);

    $post_ids = get_posts(
        array(
            'numberposts' => -1,
            'post_type' => array_values($post_types),
            'post_status' => 'publish',
            'fields' => 'ids',
        )
    );

    $updated_titles = 0;
    $updated_desc = 0;

    foreach ($post_ids as $post_id) {
        $existing_title = get_post_meta($post_id, '_wpmazic_title', true);
        $existing_desc = get_post_meta($post_id, '_wpmazic_description', true);

        if ('' === trim((string) $existing_title)) {
            update_post_meta($post_id, '_wpmazic_title', sanitize_text_field(get_the_title($post_id)));
            $updated_titles++;
        }

        if ('' === trim((string) $existing_desc)) {
            $post = get_post($post_id);
            if ($post) {
                $source_text = $post->post_excerpt ? $post->post_excerpt : $post->post_content;
                $source_text = wp_strip_all_tags(strip_shortcodes($source_text));
                $source_text = preg_replace('/\s+/', ' ', (string) $source_text);
                $source_text = trim((string) $source_text);
                if (mb_strlen($source_text) > 160) {
                    $source_text = mb_substr((string) $source_text, 0, 157) . '...';
                }
                update_post_meta($post_id, '_wpmazic_description', sanitize_textarea_field($source_text));
                $updated_desc++;
            }
        }
    }

    echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('Meta generation completed. Titles updated: %1$d, Descriptions updated: %2$d.', 'wpmazic-seo-lite'), absint($updated_titles), absint($updated_desc)) . '</p></div>';
    }
}

if (isset($_POST['wpmazic_autofill_all_seo']) && check_admin_referer('wpmazic_autofill_all_seo')) {
    if (!current_user_can('manage_options')) {
        wpmazic_seo_lite_add_notice('error', __('Permission denied.', 'wpmazic-seo-lite'));
    } else {
    $content_post_types = get_post_types(
        array(
            'public' => true,
        ),
        'names'
    );
    unset($content_post_types['attachment']);

    $content_ids = get_posts(
        array(
            'numberposts' => -1,
            'post_type' => array_values($content_post_types),
            'post_status' => 'publish',
            'fields' => 'ids',
        )
    );

    $images = get_posts(
        array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'fields' => 'ids',
        )
    );

    $stats = array(
        'content_scanned' => 0,
        'images_scanned' => 0,
        'seo_title' => 0,
        'seo_desc' => 0,
        'seo_keyword' => 0,
        'og_title' => 0,
        'og_desc' => 0,
        'twitter_title' => 0,
        'twitter_desc' => 0,
        'canonical' => 0,
        'image_alt' => 0,
        'image_caption' => 0,
        'image_description' => 0,
        'image_seo_title' => 0,
        'image_seo_desc' => 0,
        'image_seo_keyword' => 0,
    );

    foreach ($content_ids as $post_id) {
        $post = get_post($post_id);
        if (!$post) {
            continue;
        }

        $stats['content_scanned']++;

        $generated_title = sanitize_text_field(get_the_title($post_id));
        $generated_desc = $wmz_build_meta_description($post);
        $generated_keyword = $wmz_build_focus_keyword($post->post_title, $post->post_content);

        if ('' === trim((string) get_post_meta($post_id, '_wpmazic_title', true)) && '' !== $generated_title) {
            update_post_meta($post_id, '_wpmazic_title', $generated_title);
            $stats['seo_title']++;
        }

        if ('' === trim((string) get_post_meta($post_id, '_wpmazic_description', true)) && '' !== $generated_desc) {
            update_post_meta($post_id, '_wpmazic_description', sanitize_textarea_field($generated_desc));
            $stats['seo_desc']++;
        }

        if ('' === trim((string) get_post_meta($post_id, '_wpmazic_keyword', true)) && '' !== $generated_keyword) {
            update_post_meta($post_id, '_wpmazic_keyword', $generated_keyword);
            $stats['seo_keyword']++;
        }

        $final_title = trim((string) get_post_meta($post_id, '_wpmazic_title', true));
        if ('' === $final_title) {
            $final_title = $generated_title;
        }

        $final_desc = trim((string) get_post_meta($post_id, '_wpmazic_description', true));
        if ('' === $final_desc) {
            $final_desc = $generated_desc;
        }

        if ('' === trim((string) get_post_meta($post_id, '_wpmazic_og_title', true)) && '' !== $final_title) {
            update_post_meta($post_id, '_wpmazic_og_title', $final_title);
            $stats['og_title']++;
        }

        if ('' === trim((string) get_post_meta($post_id, '_wpmazic_og_description', true)) && '' !== $final_desc) {
            update_post_meta($post_id, '_wpmazic_og_description', sanitize_textarea_field($final_desc));
            $stats['og_desc']++;
        }

        if ('' === trim((string) get_post_meta($post_id, '_wpmazic_twitter_title', true)) && '' !== $final_title) {
            update_post_meta($post_id, '_wpmazic_twitter_title', $final_title);
            $stats['twitter_title']++;
        }

        if ('' === trim((string) get_post_meta($post_id, '_wpmazic_twitter_description', true)) && '' !== $final_desc) {
            update_post_meta($post_id, '_wpmazic_twitter_description', sanitize_textarea_field($final_desc));
            $stats['twitter_desc']++;
        }

        if ('' === trim((string) get_post_meta($post_id, '_wpmazic_canonical', true))) {
            $canonical = get_permalink($post_id);
            if (!empty($canonical)) {
                update_post_meta($post_id, '_wpmazic_canonical', esc_url_raw($canonical));
                $stats['canonical']++;
            }
        }
    }

    foreach ($images as $image_id) {
        $attachment = get_post($image_id);
        if (!$attachment) {
            continue;
        }

        $stats['images_scanned']++;

        $title = trim((string) $attachment->post_title);
        if ('' === $title) {
            $file_name = get_attached_file($image_id);
            $title = sanitize_text_field(str_replace(array('-', '_'), ' ', pathinfo((string) $file_name, PATHINFO_FILENAME)));
        }

        if ('' === $title) {
            $title = get_bloginfo('name');
        }

        $alt = trim((string) get_post_meta($image_id, '_wp_attachment_image_alt', true));
        if ('' === $alt) {
            update_post_meta($image_id, '_wp_attachment_image_alt', sanitize_text_field($title));
            $stats['image_alt']++;
            $alt = $title;
        }

        $update_attachment = array(
            'ID' => $image_id,
        );
        $needs_update = false;

        if ('' === trim((string) $attachment->post_excerpt)) {
            $update_attachment['post_excerpt'] = $wmz_clip_text($alt, 140);
            $stats['image_caption']++;
            $needs_update = true;
        }

        if ('' === trim((string) $attachment->post_content)) {
            $update_attachment['post_content'] = $wmz_clip_text($alt . ' ' . __('image', 'wpmazic-seo-lite'), 300);
            $stats['image_description']++;
            $needs_update = true;
        }

        if ($needs_update) {
            wp_update_post($update_attachment);
        }

        if ('' === trim((string) get_post_meta($image_id, '_wpmazic_title', true))) {
            update_post_meta($image_id, '_wpmazic_title', sanitize_text_field($title));
            $stats['image_seo_title']++;
        }

        $attachment_desc = $wmz_build_meta_description(get_post($image_id));
        if ('' === trim((string) get_post_meta($image_id, '_wpmazic_description', true)) && '' !== $attachment_desc) {
            update_post_meta($image_id, '_wpmazic_description', sanitize_textarea_field($attachment_desc));
            $stats['image_seo_desc']++;
        }

        $image_keyword = $wmz_build_focus_keyword($title, $attachment_desc);
        if ('' === trim((string) get_post_meta($image_id, '_wpmazic_keyword', true)) && '' !== $image_keyword) {
            update_post_meta($image_id, '_wpmazic_keyword', sanitize_text_field($image_keyword));
            $stats['image_seo_keyword']++;
        }
    }

    echo '<div class="notice notice-success is-dismissible"><p>' .
        sprintf(
            esc_html__('Auto SEO completed. Content scanned: %1$d, Images scanned: %2$d. Updated -> SEO Title: %3$d, Description: %4$d, Keyword: %5$d, OG Title: %6$d, OG Description: %7$d, Twitter Title: %8$d, Twitter Description: %9$d, Canonical: %10$d, Image ALT: %11$d, Image Caption: %12$d, Image Description: %13$d, Image SEO Title: %14$d, Image SEO Description: %15$d, Image SEO Keyword: %16$d.', 'wpmazic-seo-lite'),
            absint($stats['content_scanned']),
            absint($stats['images_scanned']),
            absint($stats['seo_title']),
            absint($stats['seo_desc']),
            absint($stats['seo_keyword']),
            absint($stats['og_title']),
            absint($stats['og_desc']),
            absint($stats['twitter_title']),
            absint($stats['twitter_desc']),
            absint($stats['canonical']),
            absint($stats['image_alt']),
            absint($stats['image_caption']),
            absint($stats['image_description']),
            absint($stats['image_seo_title']),
            absint($stats['image_seo_desc']),
            absint($stats['image_seo_keyword'])
        ) .
        '</p></div>';
    }
}

if (isset($_POST['wpmazic_generate_focus_keywords']) && check_admin_referer('wpmazic_generate_focus_keywords')) {
    if (!current_user_can('manage_options')) {
        wpmazic_seo_lite_add_notice('error', __('Permission denied.', 'wpmazic-seo-lite'));
    } else {
    $post_types = get_post_types(
        array(
            'public' => true,
        ),
        'names'
    );
    unset($post_types['attachment']);

    $post_ids = get_posts(
        array(
            'numberposts' => -1,
            'post_type' => array_values($post_types),
            'post_status' => 'publish',
            'fields' => 'ids',
        )
    );

    $stop_words = array(
        'a',
        'an',
        'and',
        'the',
        'of',
        'for',
        'to',
        'in',
        'on',
        'with',
        'by',
        'at',
        'from',
        'is',
        'are',
        'be',
        'this',
        'that',
        'your',
        'you',
        'how',
    );

    $updated_keywords = 0;

    foreach ($post_ids as $post_id) {
        $existing_keyword = trim((string) get_post_meta($post_id, '_wpmazic_keyword', true));
        if ('' !== $existing_keyword) {
            continue;
        }

        $title = wp_strip_all_tags((string) get_the_title($post_id));
        if ('' === trim((string) $title)) {
            continue;
        }

        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', (string) $title);
        $tokens = preg_split('/\s+/u', trim((string) $normalized));
        if (!is_array($tokens) || empty($tokens)) {
            continue;
        }

        $filtered = array();
        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ('' === $token) {
                continue;
            }

            $lower = function_exists('mb_strtolower') ? mb_strtolower((string) $token) : strtolower((string) $token);
            if (in_array($lower, $stop_words, true)) {
                continue;
            }

            $filtered[] = $token;
        }

        if (count($filtered) < 2) {
            $filtered = array_slice($tokens, 0, 3);
        }

        $phrase = trim(implode(' ', array_slice($filtered, 0, 4)));
        if ('' === $phrase) {
            continue;
        }

        update_post_meta($post_id, '_wpmazic_keyword', sanitize_text_field($phrase));
        $updated_keywords++;
    }

    echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('Focus keyword generation completed. Updated posts: %d.', 'wpmazic-seo-lite'), absint($updated_keywords)) . '</p></div>';
    }
}

if (isset($_POST['wpmazic_submit_indexnow_batch']) && check_admin_referer('wpmazic_submit_indexnow_batch')) {
    if (!current_user_can('manage_options')) {
        wpmazic_seo_lite_add_notice('error', __('Permission denied.', 'wpmazic-seo-lite'));
    } else {
    $settings = function_exists('wpmazic_seo_get_settings') ? wpmazic_seo_get_settings() : get_option('wpmazic_settings', array());
    $api_key = !empty($settings['indexnow_api_key']) ? preg_replace('/[^a-zA-Z0-9-]/', '', (string) $settings['indexnow_api_key']) : '';

    if (empty($settings['enable_indexnow'])) {
        wpmazic_seo_lite_add_notice('error', __('IndexNow is disabled. Enable it in Settings before submitting URLs.', 'wpmazic-seo-lite'));
    } elseif ('' === $api_key) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('IndexNow API key is missing. Please add it in Settings first.', 'wpmazic-seo-lite') . '</p></div>';
    } else {
        $limit = isset($_POST['wpmazic_indexnow_limit']) ? absint(wp_unslash($_POST['wpmazic_indexnow_limit'])) : 50;
        $limit = max(1, min(500, $limit));

        $post_types = get_post_types(
            array(
                'public' => true,
            ),
            'names'
        );
        unset($post_types['attachment']);

        $post_ids = get_posts(
            array(
                'numberposts' => $limit,
                'post_type' => array_values($post_types),
                'post_status' => 'publish',
                'orderby' => 'modified',
                'order' => 'DESC',
                'fields' => 'ids',
            )
        );

        $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        $key_location = home_url('/indexnow-key/' . $api_key . '.txt');
        $submitted = 0;
        $failed = 0;

        foreach ($post_ids as $post_id) {
            $url = get_permalink($post_id);
            if (empty($url)) {
                continue;
            }

            $payload = array(
                'host' => $host,
                'key' => $api_key,
                'keyLocation' => $key_location,
                'urlList' => array(esc_url_raw($url)),
            );

            $response = wp_remote_post(
                'https://api.indexnow.org/indexnow',
                array(
                    'timeout' => 8,
                    'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
                    'body' => wp_json_encode($payload),
                )
            );

            $status = 'error';
            $code = 0;
            $body = '';

            if (is_wp_error($response)) {
                $body = $response->get_error_message();
            } else {
                $code = (int) wp_remote_retrieve_response_code($response);
                $body = (string) wp_remote_retrieve_body($response);
                $status = ($code >= 200 && $code < 300) ? 'success' : 'error';
            }

            $wpdb->insert(
                wpmazic_seo_get_table_name( 'indexnow' ),
                array(
                    'url' => esc_url_raw($url),
                    'status' => $status,
                    'response' => sprintf('HTTP %d: %s', $code, substr(sanitize_textarea_field($body), 0, 3000)),
                ),
                array('%s', '%s', '%s')
            );

            if ('success' === $status) {
                $submitted++;
            } else {
                $failed++;
            }
        }

        echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('IndexNow batch submission completed. Success: %1$d, Failed: %2$d.', 'wpmazic-seo-lite'), absint($submitted), absint($failed)) . '</p></div>';
    }
    }
}

if (isset($_POST['wpmazic_fill_image_alt']) && check_admin_referer('wpmazic_fill_image_alt')) {
    if (!current_user_can('manage_options')) {
        wpmazic_seo_lite_add_notice('error', __('Permission denied.', 'wpmazic-seo-lite'));
    } else {
    $images = get_posts(
        array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'fields' => 'ids',
        )
    );

    $updated_alt = 0;

    foreach ($images as $image_id) {
        $alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
        if ('' !== trim((string) $alt)) {
            continue;
        }

        $attachment = get_post($image_id);
        if (!$attachment) {
            continue;
        }

        $fallback = trim((string) $attachment->post_title);
        if ('' === $fallback) {
            $fallback = get_bloginfo('name');
        }

        update_post_meta($image_id, '_wp_attachment_image_alt', sanitize_text_field($fallback));
        $updated_alt++;
    }

    echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('Image ALT optimization completed. Updated images: %d.', 'wpmazic-seo-lite'), absint($updated_alt)) . '</p></div>';
    }
}

if (isset($_POST['wpmazic_rebuild_links']) && check_admin_referer('wpmazic_rebuild_links')) {
    if (!current_user_can('manage_options')) {
        wpmazic_seo_lite_add_notice('error', __('Permission denied.', 'wpmazic-seo-lite'));
    } else {
    $table_links = wpmazic_seo_get_table_name( 'links' );
    $wpdb->query("TRUNCATE TABLE {$table_links}"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

    $post_types = get_post_types(
        array(
            'public' => true,
        ),
        'names'
    );
    unset($post_types['attachment']);

    $posts = get_posts(
        array(
            'numberposts' => -1,
            'post_type' => array_values($post_types),
            'post_status' => 'publish',
        )
    );

    $host = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
    $scanned = 0;
    $stored_links = 0;

    foreach ($posts as $post) {
        if (!post_type_supports($post->post_type, 'editor')) {
            continue;
        }

        $content = (string) $post->post_content;
        $scanned++;

        if ('' === trim((string) $content)) {
            continue;
        }

        if (!preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER)) {
            continue;
        }

        foreach ($matches as $match) {
            $href = isset($match[1]) ? trim((string) $match[1]) : '';
            $anchor = isset($match[2]) ? wp_strip_all_tags($match[2]) : '';

            if ('' === $href || '#' === $href || 0 === strpos((string) $href, 'mailto:') || 0 === strpos((string) $href, 'tel:')) {
                continue;
            }

            if (0 === strpos((string) $href, '/')) {
                $href = home_url($href);
            }

            $parsed_host = strtolower((string) wp_parse_url($href, PHP_URL_HOST));
            if ('' === $parsed_host || $parsed_host !== $host) {
                continue;
            }

            $clean_url = esc_url_raw($href);
            if ('' === $clean_url) {
                continue;
            }

            $target_post_id = url_to_postid($clean_url);

            $wpdb->insert(
                $table_links,
                array(
                    'post_id' => (int) $post->ID,
                    'target_post_id' => (int) $target_post_id,
                    'anchor_text' => substr(sanitize_text_field($anchor), 0, 500),
                    'url' => substr((string) $clean_url, 0, 500),
                    'type' => 'internal',
                ),
                array('%d', '%d', '%s', '%s', '%s')
            );
            $stored_links++;
        }
    }

    echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('Internal link index rebuilt. Posts scanned: %1$d, Links stored: %2$d.', 'wpmazic-seo-lite'), absint($scanned), absint($stored_links)) . '</p></div>';
    }
}

$settings = get_option('wpmazic_settings', array());
$robots_default = "User-agent: *\nDisallow:\n\nSitemap: " . home_url('/sitemap.xml');
if (!empty($settings['enable_image_sitemap'])) {
    $robots_default .= "\nSitemap: " . home_url('/image-sitemap.xml');
}
$robots_content = get_option('wpmazic_robots_txt', $robots_default);
$llms_default = "# " . get_bloginfo('name') . "\n# AI access guidance for this website\n\nSite: " . home_url('/') . "\nSitemap: " . home_url('/sitemap.xml') . "\nPreferred Attribution: Please cite source URLs when referencing this content.";
$llms_content = get_option('wpmazic_llms_txt', $llms_default);

wpmazic_seo_admin_shell_open(
    __('Tools', 'wpmazic-seo-lite'),
    __('Maintenance and growth tools for metadata coverage, internal links, and crawl hygiene.', 'wpmazic-seo-lite')
);
?>

<div class="wmz-card">
    <h2><?php esc_html_e('robots.txt Editor', 'wpmazic-seo-lite'); ?></h2>
    <form method="post">
        <?php wp_nonce_field('wpmazic_save_robots'); ?>
        <input type="hidden" name="wpmazic_save_robots" value="1">
        <textarea class="wmz-textarea" name="robots_content"
            rows="12"><?php echo esc_textarea($robots_content); ?></textarea>
        <div class="wmz-actions">
            <?php submit_button(__('Save robots.txt', 'wpmazic-seo-lite'), 'primary', 'submit', false); ?>
        </div>
    </form>
</div>

<div class="wmz-card">
    <h2><?php esc_html_e('llms.txt Editor', 'wpmazic-seo-lite'); ?></h2>
    <p class="wmz-subtle">
        <?php esc_html_e('Define AI crawler guidance for your site at /llms.txt.', 'wpmazic-seo-lite'); ?></p>
    <form method="post">
        <?php wp_nonce_field('wpmazic_save_llms'); ?>
        <input type="hidden" name="wpmazic_save_llms" value="1">
        <textarea class="wmz-textarea" name="llms_content"
            rows="10"><?php echo esc_textarea($llms_content); ?></textarea>
        <div class="wmz-actions">
            <?php submit_button(__('Save llms.txt', 'wpmazic-seo-lite'), 'secondary', 'submit', false); ?>
            <a class="button" href="<?php echo esc_url(home_url('/llms.txt')); ?>" target="_blank"
                rel="noopener noreferrer"><?php esc_html_e('View llms.txt', 'wpmazic-seo-lite'); ?></a>
        </div>
    </form>
</div>

<div class="tw-grid md:tw-grid-cols-2 tw-gap-4">
    <div class="wmz-card">
        <h2><?php esc_html_e('One-Click Auto SEO (All Content)', 'wpmazic-seo-lite'); ?></h2>
        <p class="wmz-subtle">
            <?php esc_html_e('Scans posts, pages, custom post types, and images. It generates only missing SEO metadata and does not overwrite existing values.', 'wpmazic-seo-lite'); ?>
        </p>
        <form method="post" class="wmz-actions">
            <?php wp_nonce_field('wpmazic_autofill_all_seo'); ?>
            <input type="hidden" name="wpmazic_autofill_all_seo" value="1">
            <button type="submit"
                class="button button-primary"><?php esc_html_e('Run Auto SEO', 'wpmazic-seo-lite'); ?></button>
        </form>
    </div>

    <div class="wmz-card">
        <h2><?php esc_html_e('Generate Missing SEO Meta', 'wpmazic-seo-lite'); ?></h2>
        <p class="wmz-subtle">
            <?php esc_html_e('Automatically create missing SEO titles and descriptions for published content.', 'wpmazic-seo-lite'); ?>
        </p>
        <form method="post" class="wmz-actions">
            <?php wp_nonce_field('wpmazic_generate_missing_meta'); ?>
            <input type="hidden" name="wpmazic_generate_missing_meta" value="1">
            <button type="submit"
                class="button button-primary"><?php esc_html_e('Generate Missing Meta', 'wpmazic-seo-lite'); ?></button>
        </form>
    </div>

    <div class="wmz-card">
        <h2><?php esc_html_e('Rebuild Internal Link Index', 'wpmazic-seo-lite'); ?></h2>
        <p class="wmz-subtle">
            <?php esc_html_e('Re-scan all published content and refresh internal-link data for orphan detection.', 'wpmazic-seo-lite'); ?>
        </p>
        <form method="post" class="wmz-actions">
            <?php wp_nonce_field('wpmazic_rebuild_links'); ?>
            <input type="hidden" name="wpmazic_rebuild_links" value="1">
            <button type="submit"
                class="button"><?php esc_html_e('Rebuild Link Index', 'wpmazic-seo-lite'); ?></button>
        </form>
    </div>

    <div class="wmz-card">
        <h2><?php esc_html_e('Generate Missing Focus Keywords', 'wpmazic-seo-lite'); ?></h2>
        <p class="wmz-subtle">
            <?php esc_html_e('Create focus keyword phrases from page titles when no keyword is currently set.', 'wpmazic-seo-lite'); ?>
        </p>
        <form method="post" class="wmz-actions">
            <?php wp_nonce_field('wpmazic_generate_focus_keywords'); ?>
            <input type="hidden" name="wpmazic_generate_focus_keywords" value="1">
            <button type="submit"
                class="button"><?php esc_html_e('Generate Keywords', 'wpmazic-seo-lite'); ?></button>
        </form>
    </div>

    <div class="wmz-card">
        <h2><?php esc_html_e('Instant Indexing (IndexNow Batch)', 'wpmazic-seo-lite'); ?></h2>
        <p class="wmz-subtle">
            <?php esc_html_e('Submit recently updated URLs to IndexNow for faster recrawl and indexing.', 'wpmazic-seo-lite'); ?>
        </p>
        <form method="post" class="wmz-actions">
            <?php wp_nonce_field('wpmazic_submit_indexnow_batch'); ?>
            <input type="hidden" name="wpmazic_submit_indexnow_batch" value="1">
            <label class="tw-inline-flex tw-items-center tw-gap-2">
                <span class="wmz-subtle"><?php esc_html_e('URL limit', 'wpmazic-seo-lite'); ?></span>
                <input type="number" class="wmz-input" name="wpmazic_indexnow_limit" min="1" max="500" value="50"
                    style="width:96px;">
            </label>
            <button type="submit"
                class="button"><?php esc_html_e('Submit to IndexNow', 'wpmazic-seo-lite'); ?></button>
        </form>
    </div>

    <div class="wmz-card">
        <h2><?php esc_html_e('Fill Missing Image ALT Text', 'wpmazic-seo-lite'); ?></h2>
        <p class="wmz-subtle">
            <?php esc_html_e('Backfill ALT text for existing images using media titles to improve image SEO.', 'wpmazic-seo-lite'); ?>
        </p>
        <form method="post" class="wmz-actions">
            <?php wp_nonce_field('wpmazic_fill_image_alt'); ?>
            <input type="hidden" name="wpmazic_fill_image_alt" value="1">
            <button type="submit"
                class="button"><?php esc_html_e('Optimize Image ALT', 'wpmazic-seo-lite'); ?></button>
        </form>
    </div>

    <div class="wmz-card">
        <h2><?php esc_html_e('Database Optimizer', 'wpmazic-seo-lite'); ?></h2>
        <p class="wmz-subtle">
            <?php esc_html_e('Removes stale 404 rows, orphaned SEO post meta, and optimizes plugin tables.', 'wpmazic-seo-lite'); ?>
        </p>
        <form method="post" class="wmz-actions">
            <?php wp_nonce_field('wpmazic_optimize_db'); ?>
            <input type="hidden" name="wpmazic_optimize_db" value="1">
            <button type="submit"
                class="button"><?php esc_html_e('Optimize Database', 'wpmazic-seo-lite'); ?></button>
        </form>
    </div>

    <div class="wmz-card">
        <h2><?php esc_html_e('Clear Cache', 'wpmazic-seo-lite'); ?></h2>
        <p class="wmz-subtle">
            <?php esc_html_e('Delete plugin transients and refresh runtime SEO cache values.', 'wpmazic-seo-lite'); ?>
        </p>
        <form method="post" class="wmz-actions">
            <?php wp_nonce_field('wpmazic_clear_cache'); ?>
            <input type="hidden" name="wpmazic_clear_cache" value="1">
            <button type="submit" class="button"><?php esc_html_e('Clear Cache', 'wpmazic-seo-lite'); ?></button>
        </form>
    </div>
</div>

<?php wpmazic_seo_admin_shell_close(); ?>
