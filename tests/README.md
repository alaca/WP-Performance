# Tests

Standalone suites that exercise the plugin's logic with no database and no
WordPress install. `bootstrap.php` stubs the slice of the WordPress API the code
touches, mirroring real core behavior where a test depends on it (for example
`sanitize_text_field` collapsing whitespace and stripping tags).

```bash
composer test           # everything
php tests/run.php       # everything
php tests/run.php cache # only suites whose name contains "cache"
```

Exit code is non-zero when any assertion fails, so this drops straight into CI.

## Suites

| Suite | Covers |
| --- | --- |
| `cache-paths` | The load-bearing invariant: `CacheStore` (writer, inside WordPress) and `advanced-cache.php` (reader, before WordPress) must derive the same path for the same request. Also host sanitization and traversal safety. |
| `settings` | Group defaults, key whitelisting, type preservation, list replacement, emptying a list, per-file maps, filter-injected add-on groups. |
| `settings-tools` | Presets, settings history and restore points, the troubleshooting logger. |
| `migration` | Every legacy `wpp_*` option mapping, per-post exclusions to post meta, image-size reshaping, obsolete-option cleanup, idempotency. |
| `minifiers` | Adversarial CSS/JS/HTML inputs that commonly corrupt hand-written minifiers. |
| `fragment-cache` | Block cache: both storage backends, TTL expiry, generation flush, vary dimensions, nesting, and the editor-block filters. |
| `prefetch` | The emitted Speculation Rules document, its exclusions, and the fallback script. |

## Adding a suite

Drop a file in `suites/` that returns a callable. Assertions available:
`wpp_ok`, `wpp_same`, `wpp_contains`, `wpp_not_contains`. Global state
(`WPP_Test_State`) resets between suites; a temp directory is created per run
and removed on shutdown.

```php
<?php
return static function (): void {
    wpp_same('expected', actual(), 'what this proves');
};
```
