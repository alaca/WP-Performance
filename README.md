# WP Performance #

[![](https://img.shields.io/wordpress/plugin/tested/wp-performance.svg)](https://wordpress.org/plugins/wp-performance) [![](https://img.shields.io/wordpress/plugin/stars/wp-performance.svg)](https://wordpress.org/plugins/wp-performance) [![](https://img.shields.io/wordpress/plugin/dt/wp-performance.svg)](https://wordpress.org/plugins/wp-performance) [![](https://img.shields.io/wordpress/plugin/v/wp-performance.svg)](https://wordpress.org/plugins/wp-performance)

**Contributors**: alaca
**Tags**: wp performance, cache, performance, speed optimization, wpp
**Requires at least**: 4.5
**Tested up to**: 5.0.3
**Requires PHP**: 5.6
**Stable tag**: 1.0
**License**: GPLv2 or later
**License URI**: http://www.gnu.org/licenses/gpl-2.0.html

## Description ##

WP Performance is a performance plugin that aims to make that "speed up" process a bit simpler by adding a variety of performance tweaks to your WordPress site.
This plugin generates static html files from your dynamic content, and it uses mod_rewrite to load cache files which is the fastest method.

## Multisite Support ##
WP Performance does not support multisite yet.

## Features ##

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


## Supported languages ##

* English
* Hrvatski
* 中文 (by @cmhello)

## Installation ##

1. Upload "wp-performance" to the "/wp-content/plugins/" directory
2. Activate the plugin through the Plugins menu in WordPress


## WP-CLI Support ##

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


## Changelog ##

### 1.0.5 ###
* [NEW] Drafts cleanup
* [NEW] WP CLI commands - database cleanups
* [IMPROVE] Admin UI
* [FIX] Minor bugfixes

### 1.0.4 ###
* [NEW] Resource hints
* [NEW] Exclude page from Image optimization on edit page screen
* [NEW] Exclude URL(s) from Image optimization
* [IMPROVE] Metabox UI on Edit page
* [IMPROVE] Admin UI
* [UPDATE] Translations
* [FIX] Cron tasks cleanup
* [FIX] Combine Google fonts

### 1.0.3 ###
* [NEW] Cron tasks cleanup
* [NEW] Exclude page from Cache, JS and CSS optimization on edit page screen
* [NEW] Admin toolbar links
* [IMPROVE] Admin Database UI
* [IMPROVE] Admin UI on mobile devices
* [UPDATE] Translations
* [FIX] htaccess backup handling
* [FIX] Minor bugfixes

### 1.0.2 ###
* [NEW] Detect server software
* [NEW] Generate NGINX rewrite rules
* [NEW] Clear collected files list
* [IMPROVE] Admin UI
* [UPDATE] Translations
* [FIX] Htaccess permissions notice

### 1.0.1 ###
* [FIX] PCRE2 parser issues
* [FIX] Clear cache JS error
* [NEW] Chinese translation