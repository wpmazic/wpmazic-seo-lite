# WPMazic SEO Lite

WPMazic SEO Lite is a lightweight SEO toolkit for WordPress.

It includes metadata controls, XML sitemap support, schema output, redirects, 404 monitoring, breadcrumbs, image SEO, IndexNow support, and migration tools.

## Highlights

- SEO title and meta description controls
- XML sitemap and image sitemap support
- Schema output for common content types
- Redirect manager and 404 monitor
- Open Graph and Twitter card support
- Robots.txt editor and llms.txt endpoint
- Bulk editor and migration wizard

## External Services

WPMazic SEO Lite does not connect to external services by default.

- Google Analytics 4 tracking is optional. If enabled and configured, the plugin loads `gtag.js` from Google on frontend page loads and sends analytics requests that include the Measurement ID, page URL, and standard browser/device request data.
  Terms: https://policies.google.com/terms
  Privacy: https://policies.google.com/privacy
- IndexNow submission is optional. If enabled, the plugin sends the changed URL, site host, IndexNow key, and key location URL to `https://api.indexnow.org/indexnow` when supported content is published or updated, or when the manual batch tool is run.
  Documentation: https://www.indexnow.org/documentation
  Terms: https://www.indexnow.org/terms
  Privacy: https://www.indexnow.org/terms

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin from the WordPress admin Plugins screen.
3. Open the WPMazic SEO admin pages to configure site settings.
