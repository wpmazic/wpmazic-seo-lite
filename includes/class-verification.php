<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPMazic_Verification
{

    public function __construct()
    {
        add_action('wp_head', array($this, 'output_verification_tags'), 2);
        add_action('wp_head', array($this, 'output_ga4_tag'), 20);
    }

    /**
     * Output webmaster verification meta tags.
     */
    public function output_verification_tags()
    {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        $settings = wpmazic_seo_get_settings();

        $tags = array(
            'google-site-verification' => isset($settings['google_site_verification']) ? trim((string) $settings['google_site_verification']) : '',
            'msvalidate.01' => isset($settings['bing_site_verification']) ? trim((string) $settings['bing_site_verification']) : '',
            'yandex-verification' => isset($settings['yandex_site_verification']) ? trim((string) $settings['yandex_site_verification']) : '',
            'baidu-site-verification' => isset($settings['baidu_site_verification']) ? trim((string) $settings['baidu_site_verification']) : '',
        );

        $has_output = false;
        foreach ($tags as $content) {
            if ('' !== $content) {
                $has_output = true;
                break;
            }
        }

        if (!$has_output) {
            return;
        }

        echo "<!-- WPMazic Verification -->\n";
        foreach ($tags as $name => $content) {
            if ('' === $content) {
                continue;
            }
            echo '<meta name="' . esc_attr($name) . '" content="' . esc_attr($content) . '">' . "\n";
        }
        echo "<!-- /WPMazic Verification -->\n";
    }

    /**
     * Output GA4 tracking tag when configured.
     */
    public function output_ga4_tag()
    {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        $settings = wpmazic_seo_get_settings();
        $raw_id = isset($settings['ga4_measurement_id']) ? strtoupper(trim((string) $settings['ga4_measurement_id'])) : '';

        if ('' === $raw_id) {
            return;
        }

        if (!preg_match('/^G-[A-Z0-9]{6,}$/', $raw_id)) {
            return;
        }

        // Enqueue external gtag.js script
        wp_enqueue_script(
            'wpmazic-ga4-gtag',
            'https://www.googletagmanager.com/gtag/js?id=' . esc_attr($raw_id),
            array(),
            null, // No version for external script
            array(
                'strategy' => 'async',
            )
        );

        // Add inline script
        $inline_script = 'window.dataLayer = window.dataLayer || [];' . "\n";
        $inline_script .= 'function gtag(){dataLayer.push(arguments);}' . "\n";
        $inline_script .= "gtag('js', new Date());\n";
        $inline_script .= "gtag('config', '" . esc_js($raw_id) . "');";

        wp_add_inline_script('wpmazic-ga4-gtag', $inline_script);
    }
}

