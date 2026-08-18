<?php

declare(strict_types=1);

namespace WPP\Addons\Prefetch;

use WPP\Foundation\Container\Container;
use WPP\Foundation\Modules\Module;
use WPP\Settings\SettingsService;

/**
 * Loads the next page in the background via the Speculation Rules API, so
 * navigation feels instant. Falls back to hover prefetch where unsupported.
 */
final class PrefetchModule implements Module
{
    /** @var array<string, mixed> */
    private array $config = [];

    public function id(): string
    {
        return 'prefetch';
    }

    public function boot(Container $container): void
    {
        add_filter('wpp.settings.groups', static function (array $groups): array {
            $groups['prefetch'] = [
                'option'   => 'wpp_prefetch',
                'defaults' => [
                    'enabled'   => false,
                    'mode'      => 'prerender', // prerender | prefetch
                    'eagerness' => 'moderate',  // conservative | moderate | eager
                    'exclude'   => [],
                ],
            ];
            return $groups;
        });

        if (is_admin()) {
            return;
        }

        $config = $container->get(SettingsService::class)->get('prefetch');
        if (empty($config['enabled'])) {
            return;
        }

        $this->config = $config;
        add_action('wp_footer', [$this, 'speculationRules'], 99);
        add_action('wp_footer', [$this, 'fallback'], 100);
    }

    public function speculationRules(): void
    {
        $mode = ($this->config['mode'] ?? '') === 'prefetch' ? 'prefetch' : 'prerender';

        // Same-origin links only, minus admin/login, query-string action links
        // (logout, add-to-cart, etc.), and anything marked nofollow.
        $conditions = [
            ['href_matches' => '/*'],
            ['not' => ['href_matches' => '/*\\?*']],
            ['not' => ['selector_matches' => '[rel~="nofollow"]']],
        ];
        foreach ($this->exclusions() as $pattern) {
            $conditions[] = ['not' => ['href_matches' => $pattern]];
        }

        $rules = [
            $mode => [[
                'source'    => 'document',
                'where'     => ['and' => $conditions],
                'eagerness' => $this->eagerness(),
            ]],
        ];

        echo '<script type="speculationrules">' . wp_json_encode($rules) . "</script>\n";
    }

    /**
     * Prefetch for browsers without Speculation Rules support. It gets the same
     * exclusions and eagerness as the rules above; only prerendering is beyond
     * it, so 'mode' has no fallback equivalent.
     */
    public function fallback(): void
    {
        $eagerness = $this->eagerness();
        $config    = [
            'exclude' => array_map([$this, 'toRegex'], $this->exclusions()),
            'event'   => $eagerness === 'conservative' ? 'pointerdown' : 'mouseover',
            'eager'   => $eagerness === 'eager',
        ];

        echo '<script id="wpp-prefetch-fallback">(function(){'
            . 'if(HTMLScriptElement.supports&&HTMLScriptElement.supports("speculationrules"))return;'
            . 'var c=' . wp_json_encode($config, JSON_HEX_TAG) . ';'
            . 'var rx=c.exclude.map(function(s){return new RegExp(s);});'
            . 'var seen={};'
            . 'function ok(a){if(!a||!a.href||a.hostname!==location.hostname)return false;'
            . 'if(a.getAttribute("href").charAt(0)==="#"||a.search)return false;'
            . 'if(a.rel&&a.rel.split(/\s+/).indexOf("nofollow")>-1)return false;'
            . 'for(var i=0;i<rx.length;i++){if(rx[i].test(a.pathname))return false;}return true;}'
            . 'function pf(u){if(seen[u])return;seen[u]=1;'
            . 'var l=document.createElement("link");l.rel="prefetch";l.href=u;document.head.appendChild(l);}'
            . 'if(c.eager){var all=document.querySelectorAll("a[href]");'
            . 'for(var i=0;i<all.length;i++){if(ok(all[i]))pf(all[i].href);}return;}'
            . 'document.addEventListener(c.event,function(e){var a=e.target.closest&&e.target.closest("a");'
            . 'if(ok(a))pf(a.href);},{passive:true});})();</script>' . "\n";
    }

    /** @return list<string> URL patterns no handler may ever prefetch. */
    private function exclusions(): array
    {
        $patterns = [
            (string) parse_url(admin_url('/'), PHP_URL_PATH) . '*',
            (string) parse_url(wp_login_url(), PHP_URL_PATH) . '*',
        ];

        foreach ((array) ($this->config['exclude'] ?? []) as $pattern) {
            $pattern = trim((string) $pattern);
            if ($pattern !== '') {
                $patterns[] = $pattern;
            }
        }

        return $patterns;
    }

    private function eagerness(): string
    {
        return in_array($this->config['eagerness'] ?? '', ['conservative', 'moderate', 'eager'], true)
            ? (string) $this->config['eagerness']
            : 'moderate';
    }

    /** Speculation Rules glob to a JavaScript regular expression source. */
    private function toRegex(string $pattern): string
    {
        $escaped = str_replace(
            ['\\', '/', '.', '+', '?', '^', '$', '{', '}', '(', ')', '|', '[', ']'],
            ['\\\\', '\\/', '\\.', '\\+', '\\?', '\\^', '\\$', '\\{', '\\}', '\\(', '\\)', '\\|', '\\[', '\\]'],
            $pattern
        );

        return '^' . str_replace('*', '.*', $escaped) . '$';
    }
}
