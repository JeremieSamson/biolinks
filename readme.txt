=== BioLinks ===
Contributors: jeremiesamson
Tags: link in bio, social links, bio page, link page, click tracking
Requires at least: 5.9
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Self-hosted link in bio page for WordPress. 5 templates, click tracking, analytics dashboard. No third-party, no subscription.

== Description ==

BioLinks lets you create a beautiful, self-hosted link in bio page on your own WordPress site.

No third-party accounts. No subscriptions. No limits. Just a free, open-source plugin that gets the job done.

**Features:**

* **5 visual templates** — Dark, Light, Minimal, Colorful, Glassmorphism
* **Custom accent color** — Native WordPress color picker to match your brand
* **Social media icons** — Instagram, YouTube, TikTok, LinkedIn, Twitter/X, Facebook, GitHub, Pinterest, Twitch, Snapchat (SVG icons displayed automatically)
* **Click tracking** — Built-in analytics with daily charts and per-link stats inside your WordPress admin
* **Google Analytics auto-detection** — Works with SEOPress, Yoast, and MonsterInsights out of the box
* **Standalone page** — No theme header/footer, works with any WordPress theme
* **Zero external dependencies** — Everything hosted on your server. No CDN, no cookies, no third-party tracking. GDPR friendly by design
* **Drag & drop link ordering** — Reorder your links with a simple drag and drop
* **Media Library integration** — Upload your profile photo directly from the WordPress media library

**Why BioLinks instead of Linktree?**

Linktree hosts your page on their domain (linktr.ee). With BioLinks, your page lives on your own WordPress site. You keep full control over design, data, and SEO.

== Installation ==

1. Upload the `biolinks` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Your page is created automatically at `/links/`.
4. Go to **BioLinks** in the admin menu to configure your links, photo, and template.

== Frequently Asked Questions ==

= Is BioLinks really free? =

Yes, 100%. No premium version, no locked features. The full plugin, forever.

= What's the difference with Linktree? =

Linktree hosts your page on their domain (linktr.ee). With BioLinks, your page lives on your own WordPress site. You keep full control over design, data, and SEO.

= Is it compatible with my WordPress theme? =

BioLinks generates a standalone page without your theme's header or footer. It works with any WordPress theme.

= Can I customize the design? =

Choose from 5 templates and customize the accent color. For advanced customization, you can add custom CSS in the WordPress customizer.

= What happens when I uninstall the plugin? =

All BioLinks data (links, settings, statistics) and the generated page are permanently deleted. Make sure to export any data you need before uninstalling.

= Does it work with caching plugins? =

Yes. The bio page is a standard WordPress page and works with all major caching plugins (WP Super Cache, W3 Total Cache, LiteSpeed Cache, etc.).

== Screenshots ==

1. Profile configuration — Set your photo, display name, bio, and page slug.
2. Social networks — Enter URLs for your social accounts. Only filled networks are displayed.
3. Add a link — Add links with a name, URL, optional icon, and position.
4. Manage your links — View all links with click stats. Reorder with drag & drop.
5. Appearance settings — Choose from 5 templates and pick a custom accent color.
6. Statistics dashboard — Daily click charts and per-link breakdown.

== Changelog ==

= 1.1.1 =
* Made the optional "Powered by BioLinks" footer credit a clickable link to the developer's website
* Renamed the admin option to "Support the developer" with a clearer description of its purpose
* Fixed WordPress admin bar appearing unstyled on the standalone bio page for logged-in users

= 1.1.0 =
* Added internationalization (i18n) support with French translation
* Bundled Chart.js and SortableJS locally (no more external CDN)
* Added optional "Powered by BioLinks" footer credit linking to the developer's website (disabled by default)
* Added uninstall cleanup (removes all data on uninstall)
* Used dbDelta() for database table creation
* Added full plugin header for WordPress.org compatibility

= 1.0.0 =
* Initial release
* 5 visual templates (Dark, Light, Minimal, Colorful, Glass)
* Custom accent color with WordPress color picker
* Social media icons (10 networks)
* Click tracking with daily charts and per-link stats
* Google Analytics auto-detection (SEOPress, Yoast, MonsterInsights)
* Standalone page without theme header/footer
* Drag & drop link ordering
* Media Library integration for profile photo
* Import from Click Tracker plugin

== Upgrade Notice ==

= 1.1.1 =
Recommended update: footer credit is now a proper clickable link, and the admin bar no longer appears unstyled on the bio page.

= 1.1.0 =
Recommended update: bundled JS assets locally, added i18n support, and WordPress.org compatibility improvements.
