=== WP Performance ===
Contributors: alaca
Donate link: https://profiles.wordpress.org/alaca
Tags: cache, performance, page speed, optimization, core web vitals
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 2.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Make your site fast. Page caching, CSS/JS/HTML optimization, modern images, and Core Web Vitals tools in one simple plugin.

== Description ==

WP Performance makes your WordPress site load faster without the headache. Turn on a few options and the plugin handles caching, asset optimization, and image delivery for you.

Not sure where to start? Open the **Overview** tab and pick a one-click preset (Safe, Balanced, or Aggressive). You can fine-tune anything afterwards.

Version 2.0 is a complete rebuild with a fast new dashboard and no third-party dependencies. Everything below works together out of the box.

### Caching ###

* **Page cache** - serves a static HTML copy of each page, before WordPress even loads
* **Block cache** - cache just the slow parts of a page, even on pages you can't fully cache (more below)
* **Cache preloading** - warm the cache from your sitemap
* **Browser caching** - tells browsers to keep files so repeat visits are instant
* **GZIP compression** - sends smaller files to the browser
* **Object cache** - optional Redis drop-in to cut database load
* **Smart by default** - skips logged-in users and WooCommerce/EDD cart pages, and ignores tracking parameters (utm, gclid, fbclid) so clean URLs share one cache
* **Mobile cache** - keep a separate copy for mobile devices when you need it

### CSS ###

* Minify CSS (inline and files)
* Combine CSS files
* Load CSS without blocking the page
* Remove unused CSS, per page
* Critical CSS
* Combine Google Fonts, or host them on your own server
* Control font loading (font-display)
* DNS prefetch and preconnect hints

### JavaScript ###

* Minify JavaScript (inline and files)
* Combine JavaScript files
* Defer JavaScript
* Delay JavaScript until the visitor interacts (great for third-party scripts)
* Disable scripts you don't need, site-wide or per page

### HTML ###

* Minify HTML
* Remove comments and unneeded markup

### Images and media ###

* Lazy load images and videos (uses native browser lazy loading)
* Responsive images
* Add missing width and height to stop layout shift (better CLS)
* Preload your largest image and set fetchpriority for a faster LCP
* Convert images to WebP and AVIF
* Custom image sizes and one-click thumbnail regeneration
* Disable emojis and embeds

### Database ###

* Clean up revisions, auto-drafts, trash, spam, transients, and stale cron tasks
* Schedule cleanups daily, weekly, or monthly

### CDN ###

* Rewrite asset URLs to your CDN, with exclusions

### Tools ###

* **Overview dashboard** with environment health checks
* **One-click presets** - Safe, Balanced, Aggressive
* **Restore points** - roll back to your last five saved configurations
* **Troubleshooting log** with a live viewer
* **Export and import** your settings
* **Per-page exclusions** from the post editor
* **WP-CLI** commands
* **Multisite** support

### Block cache ###

Block cache stores the rendered output of a section of a page and reuses it, even on pages that are not fully cached (like logged-in or dynamic pages). Use it three ways:

1. **Cache block** - add the "Cache" block in the editor and put any blocks inside it
2. **Shortcode** - `[wpp_cache ttl="600"]...[/wpp_cache]`
3. **PHP** - in a template:

`if ( wpp_cache_start( 'sidebar', [ 'ttl' => 3600 ] ) ) { expensive_render(); wpp_cache_end(); }`

You choose how long to cache and whether to keep a separate copy per page, per device, per role, or for logged-in visitors.

### Add-ons ###

* **Cloudflare** - purge cache and control Cloudflare settings
* **Varnish** - auto-purge Varnish cache
* **Prefetch** - prefetch links on hover for instant navigation

### WP-CLI ###

* `wp wpp flush` - clear the page cache
* `wp wpp flush-blocks` - clear the block cache
* `wp wpp enable` - enable WP Performance
* `wp wpp disable` - temporarily disable WP Performance
* `wp wpp cleanup [<type>]` - run a database cleanup (all, trash, spam, revisions, autodrafts, transients, cron)

### Languages ###

* English
* Hrvatski (Croatian)

### Report bugs ###

Found a bug? Please open an issue on [GitHub](https://github.com/alaca/WP-Performance).

== Installation ==

1. Upload the "wp-performance" folder to "/wp-content/plugins/"
2. Activate the plugin from the Plugins menu in WordPress
3. Open WP Performance and apply a preset from the Overview tab, or turn on the options you want

If you already used an older version, your existing settings are migrated automatically.

== Screenshots ==

1. Overview dashboard with presets and health checks
2. Page cache settings
3. Image and media optimization
4. Database cleanup
5. Settings, restore points, and the troubleshooting log

== Frequently Asked Questions ==

= Where do I start? =
Open the Overview tab and apply a preset. Safe is risk-free, Balanced suits most sites, and Aggressive turns everything on (test your site afterwards).

= What is the block cache for? =
It caches just one section of a page. That is useful on pages you can't fully cache, like a logged-in dashboard, where one part (a menu, a product grid) is still expensive to build every time.

= Does it work with Nginx? =
Yes. WP Performance generates the Nginx rules for you and shows them under Settings. Copy them into your server config and reload Nginx.

= Does it support Multisite? =
Yes.

= Do I need Redis? =
No. Redis is optional. If it is available, you can enable the object cache for an extra speed boost; everything else works without it.

= Where can I get help? =
Use the [wordpress.org support forum](https://wordpress.org/support/plugin/wp-performance), or open an issue on [GitHub](https://github.com/alaca/WP-Performance).

== Changelog ==

= 2.0.0 =
A complete rebuild with a new dashboard and many new features.
[NEW] Overview dashboard with environment health checks
[NEW] One-click presets (Safe, Balanced, Aggressive)
[NEW] Block cache - cache parts of a page via the Cache block, the [wpp_cache] shortcode, or wpp_cache_start()
[NEW] Delay JavaScript until the visitor interacts
[NEW] Remove unused CSS, per page
[NEW] Host Google Fonts locally
[NEW] Convert images to WebP and AVIF
[NEW] Preload the LCP image and set fetchpriority
[NEW] Add missing image width and height to reduce layout shift
[NEW] Redis object cache drop-in
[UPDATE] Rebuilt the cache engine, asset optimizer, and CSS/JS/HTML minifiers from scratch, with no third-party dependencies
[UPDATE] New React dashboard
[UPDATE] Existing settings are migrated automatically on update
[REMOVED] AMP support

= 1.1.8.3 =
[FIX] Cloudflare add-on - respect existing headers

For the full history of older releases, see [changelog.txt](https://plugins.svn.wordpress.org/wp-performance/trunk/changelog.txt).
