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

## External services

WPMazic SEO Lite does not send tracking data or make external service requests by default. External services are used only after the site owner enables the related setting or runs the related admin tool.

- Google Analytics 4 / Google tag (`www.googletagmanager.com`): used to add Google Analytics 4 tracking to the public frontend. Data is sent only when the site owner enters a GA4 Measurement ID and enables "GA4 Frontend Tracking" in the plugin settings. When enabled, the plugin loads Google's `gtag.js` script in frontend page source. Google receives the configured Measurement ID, the visitor's page URL, and standard browser/request data needed to deliver analytics. This feature is off by default.
  Terms: https://policies.google.com/terms
  Privacy: https://policies.google.com/privacy
- IndexNow (`api.indexnow.org`): used to notify participating search engines when site URLs are published or updated. Data is sent only when the site owner enables IndexNow in the plugin settings, or when an administrator manually runs the IndexNow batch submission tool after IndexNow is enabled. The plugin sends the site host, the submitted URL or URLs, the configured IndexNow key, and the key location URL to `https://api.indexnow.org/indexnow`. This feature is off by default.
  Documentation: https://www.indexnow.org/documentation
  Terms: https://www.indexnow.org/terms
  Privacy: https://www.indexnow.org/terms

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin from the WordPress admin Plugins screen.
3. Open the WPMazic SEO admin pages to configure site settings.
