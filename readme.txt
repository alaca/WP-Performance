=== WP Performance ===
Contributors: alaca
Donate link: https://profiles.wordpress.org/alaca
Tags: wp performance, cache, performance, speed optimization, seo
Requires at least: 4.5
Tested up to: 5.1
Requires PHP: 5.6
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

WP Performance is a cache & performance plugin.
This plugin generates static html files from your dynamic content, and it uses mod_rewrite to load cache files which is the fastest method.

### Multisite Support ###
WP Performance does not support multisite yet.

### Features ###

* Page cache
* Cache preloading
* Browser cache
* GZIP Compression
* Minify CSS, JavaScript, and HTML
* Asynchronously load CSS/JavaScript
* Combine CSS/JavaScript
* Disable CSS/JavaScript
* Resource hints
* Critical CSS path generator
* Database Cleaner
* Export/import settings
* Lazy Load images
* Responsive images
* Regenerate thumbs
* CDN
* WP-CLI Support

### Add-ons ###

* Cloudflare integration
* Varnish cache
* Dynamic page preload

### Supported languages ### 

* English
* Hrvatski
* 中文 (by @cmhello)


### WP-CLI Support ###

* **wp wpp flush** - Clear the cache
* **wp wpp disable** - Temporarily disable WP Performance
* **wp wpp enable** - Enable WP Performance
* **wp wpp cleanup** - Run all database cleanups
* **wp wpp cleanup trash** - Run trash cleanup
* **wp wpp cleanup spam** - Run spam cleanup
* **wp wpp cleanup revisions** - Run revisions cleanup
* **wp wpp cleanup drafts** - Run drafts cleanup
* **wp wpp cleanup transients** - Run transients cleanup
* **wp wpp cleanup cron** - Run cron tasks cleanup

### Report bugs ### 
If you encounter any bug, please create an issue on [Github](https://github.com/alaca/WP-Performance). 

== Installation ==

1. Upload "wp-performance" to the "/wp-content/plugins/" directory
2. Activate the plugin through the Plugins menu in WordPress


== Screenshots ==

1. Cache page
2. CSS optimization page
3. JavaScript optimization page
4. Images
5. DB Optimizer
6. CDN
7. Settings

== Changelog ==

= 1.1.3.2 =
[FIX] Parser minify error

= 1.1.3.1 =
[FIX] Autoloader errors

= 1.1.3 =
[NEW] Dynamic page preload addon
[NEW] Minify HTML

= 1.1.2 =
[UPDATE] Chinese language file

= 1.1.1 =
[NEW] Remove cache directory after uninstall
[IMPROVE] Move log file into cache dir
[FIX] Empty template errors

= 1.1.0 =
[NEW] Exclude user agents from cache
[NEW] Exclude search engines from cache
[NEW] Add-ons page
[IMPROVE] Admin UI
[FIX] XML files cache

= 1.0.9 =
[IMPROVE] JavaScript async loading
[IMPROVE] Security
[IMPROVE] Parser
[FIX] LazyLoad
[FIX] Exclude urls

= 1.0.8.1 =
[FIX] Minor bug fixes

= 1.0.8 =
[NEW] Cloudflare integration
[IMPROVE] Admin UI for desktop devices
[IMPROVE] Admin UI for mobile devices
[FIX] Disabled resources disappearing options
[FIX] Minor bugfixes

= 1.0.7 =
[FIX] Add items to menu error
[FIX] Parser warnings for responsive images
[IMPROVE] Admin UI on mobile devices

= 1.0.6 =
[NEW] Group resources ( Theme, Plugins, External )
[IMPROVE] Security
[IMPROVE] Admin UI on mobile devices
[UPDATE] Translations
[FIX] Disable external resources
[FIX] Clear cache on front-page
[FIX] Various minor bugfixes

= 1.0.5 =
[NEW] Drafts cleanup
[NEW] WP CLI commands - run database cleanups
[IMPROVE] Admin UI
[UPDATE] Translations
[FIX] Minor bugfixes

= 1.0.4 =
[NEW] Resource hints
[NEW] Exclude page from Image optimization on edit page screen
[NEW] Exclude URL(s) from Image optimization
[IMPROVE] Metabox UI on Edit page
[IMPROVE] Admin UI
[UPDATE] Translations
[FIX] Cron tasks cleanup
[FIX] Combine Google fonts

= 1.0.3 =
[NEW] Cron tasks cleanup
[NEW] Exclude page from Cache, JS and CSS optimization on edit page screen
[NEW] Admin toolbar links
[IMPROVE] Admin Database UI
[IMPROVE] Admin UI on mobile devices
[UPDATE] Translations
[FIX] htaccess backup handling
[FIX] Minor bugfixes

= 1.0.2 =
[NEW] Detect server software
[NEW] Generate NGINX rewrite rules
[NEW] Clear collected files list
[IMPROVE] Admin UI
[UPDATE] Translations
[FIX] Htaccess permissions notice

= 1.0.1 =
[FIX] PCRE2 parser issues
[FIX] Clear cache JS error
[NEW] Chinese translation
