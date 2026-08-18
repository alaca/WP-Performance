# WP Performance

[![Tested](https://img.shields.io/wordpress/plugin/tested/wp-performance.svg)](https://wordpress.org/plugins/wp-performance) [![Rating](https://img.shields.io/wordpress/plugin/stars/wp-performance.svg)](https://wordpress.org/plugins/wp-performance) [![Downloads](https://img.shields.io/wordpress/plugin/dt/wp-performance.svg)](https://wordpress.org/plugins/wp-performance) [![Version](https://img.shields.io/wordpress/plugin/v/wp-performance.svg)](https://wordpress.org/plugins/wp-performance)

Cache and performance plugin for WordPress. Page caching, CSS/JS/HTML optimization, modern image formats and Core Web Vitals tooling, with a React admin.

Version 2.0 is a ground-up rewrite: no third-party PHP dependencies, its own cache engine, asset parser and minifiers.

## Features

**Caching** page cache served before WordPress loads, block cache for parts of a page, cache preloading, browser caching, GZIP, optional Redis object cache.

**CSS** minify, combine, load without blocking, remove unused CSS per page, critical CSS, self-host Google Fonts, resource hints.

**JavaScript** minify, combine, defer, delay until interaction, disable per file or per page.

**Media** lazy load, responsive images, missing width/height for CLS, LCP preload with fetchpriority, WebP and AVIF conversion.

**Also** HTML minification, database cleanup, CDN rewriting, one-click presets, health checks, restore points, WP-CLI, multisite.

Add-ons for Cloudflare, Varnish and link prefetching ship with the plugin.

## Requirements

- WordPress 6.9 or newer
- PHP 8.1 or newer

## Development

```bash
composer install     # autoloader
npm install          # build toolchain
npm run build        # compile the admin app and blocks into build/
npm start            # watch mode
```

`build/` and `vendor/` are generated and not committed, so both commands are required before the plugin will run from a clone.

### Tests

```bash
composer test              # every suite
php tests/run.php cache    # suites matching "cache"
```

The suites are standalone: they stub the slice of the WordPress API the code touches, so they need no database and no WordPress install. See [tests/README.md](tests/README.md).

### Translations

```bash
bin/make-translations.sh
```

Regenerates `languages/wpp.pot`, compiles each `.po`, and builds the script-translation JSON the admin bundle loads. Requires WP-CLI and GNU gettext.

## Reporting bugs

Open an issue on [GitHub](https://github.com/alaca/WP-Performance/issues).

## License

GPL-2.0-or-later.
