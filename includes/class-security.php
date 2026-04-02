<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPMazic_Security
{

    /**
     * Runtime settings.
     *
     * @var array
     */
    private $settings = array();

    /**
     * Base bot signatures that are commonly abusive/scanner traffic.
     *
     * @var string[]
     */
    private $default_bad_bots = array(
        'sqlmap',
        'nikto',
        'masscan',
        'zgrab',
        'nmap',
        'acunetix',
        'dirbuster',
        'wpscan',
        'fuzz faster u fool',
        'nessus',
        'python-requests',
        'libwww-perl',
        'go-http-client',
    );

    /**
     * Boot runtime security features.
     */
    public function __construct()
    {
        $this->settings = function_exists('wpmazic_seo_get_settings') ? wpmazic_seo_get_settings() : get_option('wpmazic_settings', array());

        if ($this->is_enabled('enable_security_bad_bots')) {
            // Run early on every web request.
            $this->maybe_block_bad_bot();
        }

        if ($this->is_enabled('enable_security_headers')) {
            add_action('send_headers', array($this, 'send_security_headers'), 20);
        }

        if ($this->is_enabled('enable_security_disable_xmlrpc')) {
            add_filter('xmlrpc_enabled', '__return_false');
            add_filter('pings_open', '__return_false', 20, 2);
            $this->maybe_block_xmlrpc_request();
        }

        if ($this->is_enabled('enable_security_hide_wp_version')) {
            remove_action('wp_head', 'wp_generator');
            add_filter('the_generator', '__return_empty_string');
            add_filter('script_loader_src', array($this, 'strip_version_query_arg'), 9999);
            add_filter('style_loader_src', array($this, 'strip_version_query_arg'), 9999);
        }

        if ($this->is_enabled('enable_security_block_author_enum')) {
            add_action('template_redirect', array($this, 'maybe_block_author_enumeration'), 0);
        }
    }

    /**
     * Check whether a setting toggle is enabled.
     *
     * @param string $key Setting key.
     * @return bool
     */
    private function is_enabled($key)
    {
        return !empty($this->settings[$key]);
    }

    /**
     * Block known abusive bots by user agent signature.
     */
    public function maybe_block_bad_bot()
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        if (defined('WP_CLI') && WP_CLI) {
            return;
        }

        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower(trim(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])))) : '';
        if ('' === $user_agent) {
            return;
        }

        $whitelist = $this->build_signature_list(isset($this->settings['security_bad_bots_whitelist']) ? $this->settings['security_bad_bots_whitelist'] : '');
        foreach ($whitelist as $allowed_signature) {
            if ('' !== $allowed_signature && false !== strpos((string) $user_agent, (string) $allowed_signature)) {
                return;
            }
        }

        $custom_blocked = $this->build_signature_list(isset($this->settings['security_bad_bots_custom']) ? $this->settings['security_bad_bots_custom'] : '');
        $blocked = array_merge($this->default_bad_bots, $custom_blocked);

        foreach ($blocked as $signature) {
            if ('' !== $signature && false !== strpos((string) $user_agent, (string) $signature)) {
                do_action('wpmazic_security_bad_bot_blocked', $signature, $user_agent);
                $this->deny_request(403, __('Forbidden', 'wpmazic-seo-lite'));
            }
        }
    }

    /**
     * Parse multiline signature lists from settings.
     *
     * @param mixed $raw Raw setting value.
     * @return string[]
     */
    private function build_signature_list($raw)
    {
        $raw = is_string($raw) ? $raw : '';
        if ('' === $raw) {
            return array();
        }

        $lines = preg_split('/\r\n|\r|\n/', (string) $raw);
        if (!is_array($lines)) {
            return array();
        }

        $list = array();
        foreach ($lines as $line) {
            $line = strtolower(trim((string) $line));
            if ('' === $line) {
                continue;
            }
            if ('#' === substr((string) $line, 0, 1)) {
                continue;
            }
            $list[] = $line;
        }

        $list = array_unique($list);
        return array_values($list);
    }

    /**
     * Send conservative, compatibility-safe security headers.
     */
    public function send_security_headers()
    {
        if (headers_sent()) {
            return;
        }

        if (function_exists('send_nosniff_header')) {
            send_nosniff_header();
        } else {
            header('X-Content-Type-Options: nosniff');
        }

        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), camera=(), microphone=()');

        if (is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Block direct access to xmlrpc.php when disabled.
     */
    public function maybe_block_xmlrpc_request()
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))) : '';
        $script_name = isset($_SERVER['SCRIPT_NAME']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['SCRIPT_NAME']))) : '';

        if (false !== strpos((string) $request_uri, 'xmlrpc.php') || false !== strpos((string) $script_name, 'xmlrpc.php')) {
            $this->deny_request(403, __('XML-RPC is disabled on this site.', 'wpmazic-seo-lite'));
        }
    }

    /**
     * Remove ?ver= from frontend script/style URLs.
     *
     * @param mixed $src Asset URL.
     * @return mixed
     */
    public function strip_version_query_arg($src)
    {
        if (is_admin()) {
            return $src;
        }

        if (!is_string($src)) {
            $src = is_scalar($src) ? (string) $src : '';
        }

        if ('' === $src) {
            return $src;
        }

        if (false === strpos((string) $src, 'ver=')) {
            return $src;
        }

        return remove_query_arg('ver', $src);
    }

    /**
     * Hide author archives/IDs that can expose usernames.
     */
    public function maybe_block_author_enumeration()
    {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $author_qs = isset($_GET['author']) ? sanitize_text_field(wp_unslash($_GET['author'])) : '';

        $has_author_query = '' !== trim((string) $author_qs);
        $is_author_path = false !== stripos((string) $request_uri, '/author/');
        $is_author_page = function_exists('is_author') && is_author();

        if ($has_author_query || $is_author_path || $is_author_page) {
            $this->deny_request(404, __('Not Found', 'wpmazic-seo-lite'));
        }
    }

    /**
     * Terminate request with a status and plain response.
     *
     * @param int    $status_code HTTP status.
     * @param string $message     Body text.
     */
    private function deny_request($status_code, $message)
    {
        if (!headers_sent()) {
            status_header((int) $status_code);
            nocache_headers();
            header('Content-Type: text/plain; charset=' . get_option('blog_charset', 'UTF-8'));
        }
        echo esc_html((string) $message);
        exit;
    }

    /**
     * Backward-compatible nonce verification helper.
     *
     * @param string $nonce  Nonce value.
     * @param string $action Nonce action.
     * @return int|false
     */
    public static function verify_nonce($nonce, $action = 'wpmazic_nonce')
    {
        return wp_verify_nonce($nonce, $action);
    }

    /**
     * Text sanitizer helper.
     *
     * @param mixed $value Raw value.
     * @return string
     */
    public static function sanitize_text($value)
    {
        return sanitize_text_field($value);
    }

    /**
     * Textarea sanitizer helper.
     *
     * @param mixed $value Raw value.
     * @return string
     */
    public static function sanitize_textarea($value)
    {
        return sanitize_textarea_field($value);
    }

    /**
     * URL sanitizer helper.
     *
     * @param mixed $url Raw URL.
     * @return string
     */
    public static function sanitize_url($url)
    {
        return esc_url_raw($url);
    }
}
