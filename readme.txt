=== WPMazic SEO Lite ===
Contributors: wpmazicteam
Tags: seo, sitemap, schema, redirects, open graph
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Core SEO toolkit for WordPress with migration wizard, metadata tools, sitemap, schema basics, redirects, and crawl helpers.

== Description ==

WPMazic SEO Lite helps you launch SEO fundamentals quickly.

**Included Free Features**

* Setup wizard + 1-click migration from Yoast, Rank Math, and AIOSEO
* SEO title/meta controls with snippet preview for posts, pages, and custom post types
* Focus keyword analysis and per-post SEO controls
* XML sitemap plus image sitemap support
* Schema output for common content types including Article, FAQ, Product, and LocalBusiness
* Open Graph, Twitter Cards, dynamic OG image fallback, and author SEO enhancements
* Redirect manager, 404 monitor, and auto slug redirect
* Search engine verification tags, robots.txt editor, llms.txt editor, and IndexNow
* Bulk editor, migration wizard, local SEO, breadcrumbs, image SEO, and internal link tools

== External services ==

This plugin does not connect to external services by default. External requests happen only if the site owner enables and configures the relevant feature.

* Google Analytics 4 frontend tracking: this is used to load Google Analytics tracking on the public site. When the site owner enables GA4 tracking and saves a Measurement ID, the plugin loads `gtag.js` from `www.googletagmanager.com` and sends pageview-related analytics requests to Google when visitors load frontend pages. The Measurement ID is included in page source, and Google may also receive the visitor's page URL plus standard browser and device request data needed for analytics delivery.
  Terms: https://policies.google.com/terms
  Privacy: https://policies.google.com/privacy
* IndexNow submission: this is used to notify participating search engines that a URL was added, updated, or deleted. When the site owner enables IndexNow, the plugin sends the changed page URL, the site host, the configured IndexNow key, and the key location URL to `https://api.indexnow.org/indexnow` when supported content is published or updated. The same data is also sent when the site owner manually runs the IndexNow batch tool from the plugin's Tools screen.
  Documentation: https://www.indexnow.org/documentation
  Terms: https://www.indexnow.org/terms
  Privacy: https://www.indexnow.org/terms

== Privacy ==

WPMazic SEO Lite stores plugin settings in your WordPress database.

If the 404 Monitor feature is enabled, the plugin also stores the requested 404 URL, the referring URL when available, the hit count, and the last-hit timestamp for logged 404 events.

If you enable features that connect to external services (see the "External services" section), your site may send data to those services as described there.

== Installation ==

1. Upload `wpmazic-seo-lite` to `/wp-content/plugins/`
2. Activate from the Plugins screen in WordPress
3. Open WPMazic SEO in admin and complete setup

== Frequently Asked Questions ==

= Does Lite include migration from Yoast/Rank Math/AIOSEO? =

Yes. Use the setup wizard.

= Where is the sitemap? =

Visit `https://yourdomain.com/sitemap.xml`.

= Does this package include all bundled features? =

Yes. The plugin includes the features bundled in this package. If you use a separate commercial WPMazic product, treat it as a separate offering outside the WordPress.org plugin directory package.

= Does the plugin load remote assets in wp-admin? =

No. Admin CSS and JS are bundled locally inside the plugin package.

== Screenshots ==

1. Dashboard overview
2. Settings screen
3. Tools (robots.txt + llms.txt editor)
4. Redirect manager
5. SEO meta box editor

== Support ==

Support is handled through the plugin support forum:
https://wordpress.org/support/plugin/wpmazic-seo-lite/

== Changelog ==

= 1.0.0 =
* Initial Lite release
* Free-feature package with setup wizard, migration, and core SEO toolkit

== Upgrade Notice ==

= 1.0.0 =
Initial release.
