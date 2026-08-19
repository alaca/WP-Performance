<?php

declare(strict_types=1);

namespace WPP\Optimize;

use WPP\Settings\SettingsService;
use WPP\Support\Assets;
use WPP\Support\Url;

/**
 * Rewrites a rendered page's HTML to apply CSS/JS/image/HTML optimizations.
 * Targeted regex over <link>/<script>/<style>/<img>/<iframe> only; the page is
 * never round-tripped through a DOM, which avoids reserialization corruption.
 */
final class AssetParser
{
    /** Placeholders marking where a removed tag stood, so replacements keep source order. */
    private const CSS_ANCHOR = '<!--wpp-css-->';
    private const JS_ANCHOR  = '<!--wpp-js-->';

    private bool $loggedIn = false;
    private string $currentUrl = '';

    public function __construct(
        private SettingsService $settings,
        private Minifier $minifier,
        private HtmlOptimizer $htmlOptimizer,
        private Collection $collection,
        private FontHost $fontHost
    ) {
    }

    public function parse(string $html): string
    {
        if (stripos($html, '</head>') === false) {
            return $html;
        }

        $this->loggedIn   = is_user_logged_in();
        $this->currentUrl = (is_ssl() ? 'https' : 'http') . '://'
            . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');

        $excludes = $this->postExcludes();

        $css     = $this->settings->get('css');
        $js      = $this->settings->get('js');
        $cdn     = $this->settings->get('cdn');
        $media   = $this->settings->get('media');
        $htmlOpt = $this->settings->get('html');

        $combineCss = [];
        $combineJs  = [];

        $googleFonts = [];
        $usedUrls    = [];
        $styleTags   = [];
        $hintTags    = [];
        $delayed     = false;

        if (! $excludes['css'] && ! $this->typeExcluded($css)) {
            $html = $this->processCss($html, $css, $cdn, $combineCss, $googleFonts, $usedUrls);
            if (! empty($css['minify_inline'])) {
                $html = $this->minifyInlineStyles($html);
            }

            if (! empty($css['remove_unused']) && $usedUrls !== []) {
                $used = $this->buildUsedCss($usedUrls, $html, $css);
                if ($used !== '') {
                    $styleTags[] = '<style id="wpp-used-css">' . $used . '</style>';
                }
                $fullUrl = $this->combine($usedUrls, 'css', $cdn);
                if ($fullUrl !== null) {
                    $hintTags[] = '<link rel="preload" as="style" href="' . esc_url($fullUrl)
                        . '" onload="this.onload=null;this.rel=\'stylesheet\'">'
                        . '<noscript><link rel="stylesheet" href="' . esc_url($fullUrl) . '"></noscript>';
                } else {
                    // The originals already stood down for the anchor, so a
                    // bundle that could not be built has to hand them back.
                    foreach ($usedUrls as $usedUrl) {
                        $styleTags[] = $this->styleTag($usedUrl, true);
                    }
                }
            }
        }
        if (! $excludes['js'] && ! $this->typeExcluded($js)) {
            $html = $this->processJs($html, $js, $cdn, $combineJs, $delayed);
        }

        $imgPreloads = [];
        if (! $excludes['image']) {
            $html = $this->processImages($html, $media, $cdn, $imgPreloads);
        }
        if (! $excludes['video']) {
            $html = $this->processIframes($html, $media);
        }

        foreach ($imgPreloads as $preload) {
            $hintTags[] = $this->imagePreloadTag($preload);
        }

        $deferCss = ! empty($css['defer']);

        foreach ($combineCss as $mediaValue => $urls) {
            $url = $this->combine($urls, 'css', $cdn);
            if ($url !== null) {
                $styleTags[] = $this->styleTag($url, $deferCss, (string) $mediaValue);
                continue;
            }
            foreach ($urls as $original) {
                $styleTags[] = $this->styleTag($original, $deferCss, (string) $mediaValue);
            }
        }
        foreach ($googleFonts as $endpoint => $families) {
            $googleUrl = $this->googleFontsUrl((string) $endpoint, $families, $css);
            $hosted    = ! empty($css['host_fonts']) ? $this->fontHost->localize($googleUrl) : null;
            if ($hosted !== null) {
                $styleTags[] = $this->styleTag($hosted['css_url'], $deferCss);
                foreach ($hosted['fonts'] as $fontUrl) {
                    $hintTags[] = '<link rel="preload" as="font" type="font/woff2" crossorigin href="' . esc_url($fontUrl) . '">';
                }
            } else {
                $styleTags[] = $this->styleTag($googleUrl, $deferCss);
            }
        }
        foreach ((array) ($css['dns_prefetch'] ?? []) as $origin) {
            if ($origin !== '') {
                $hintTags[] = '<link rel="dns-prefetch" href="' . esc_url((string) $origin) . '">';
            }
        }
        foreach ((array) ($css['preconnect'] ?? []) as $origin) {
            if ($origin !== '') {
                $hintTags[] = '<link rel="preconnect" href="' . esc_url((string) $origin) . '" crossorigin>';
            }
        }
        if ($deferCss && trim((string) ($css['critical_path'] ?? '')) !== '') {
            array_unshift($styleTags, '<style id="wpp-critical-css">' . $css['critical_path'] . '</style>');
        }

        // Stylesheets go back where the originals stood: moving them to the end
        // of <head> would put them after any inline <style> that overrode them.
        $html = $this->placeAnchored($html, self::CSS_ANCHOR, implode('', $styleTags), '</head>');

        if ($hintTags !== []) {
            $html = $this->injectBefore($html, '</head>', implode('', $hintTags));
        }

        if ($combineJs !== []) {
            $url = $this->combine($combineJs, 'js', $cdn);
            $tag = '';
            if ($url !== null) {
                if (! empty($js['delay'])) {
                    $tag = '<script type="wpp/lazy" data-wpp-src="' . esc_url($url) . '"></script>';
                    $delayed = true;
                } else {
                    $tag = '<script src="' . esc_url($url) . '"' . (! empty($js['defer']) ? ' defer' : '') . '></script>';
                }
            } else {
                foreach ($combineJs as $original) {
                    $tag .= '<script src="' . esc_url($original) . '"' . (! empty($js['defer']) ? ' defer' : '') . '></script>';
                }
            }
            $html = $this->placeAnchored($html, self::JS_ANCHOR, $tag, '</body>');
        }

        if ($delayed) {
            $html = $this->injectBefore($html, '</body>', $this->loaderScript());
        }

        if (! $excludes['html'] && ! empty($htmlOpt['enabled']) && ! Url::anyMatch((array) ($htmlOpt['exclude_urls'] ?? []), $this->currentUrl)) {
            $html = $this->htmlOptimizer->optimize($html, $htmlOpt);
        }

        $this->collection->flush();

        return $html;
    }

    /**
     * Per-post optimization excludes set via the edit-screen metabox.
     *
     * @return array{css:bool,js:bool,html:bool,image:bool,video:bool}
     */
    private function postExcludes(): array
    {
        $out = ['css' => false, 'js' => false, 'html' => false, 'image' => false, 'video' => false];

        if (function_exists('is_singular') && is_singular()) {
            $id = get_queried_object_id();
            if ($id) {
                foreach (array_keys($out) as $type) {
                    if (get_post_meta($id, '_wpp_exclude_' . $type, true)) {
                        $out[$type] = true;
                    }
                }
            }
        }

        return $out;
    }

    /** @param array<string,mixed> $cfg */
    private function typeExcluded(array $cfg): bool
    {
        if ($this->loggedIn && ! empty($cfg['disable_loggedin'])) {
            return true;
        }
        return Url::anyMatch((array) ($cfg['exclude_urls'] ?? []), $this->currentUrl);
    }

    /**
     * @param array<string,mixed> $css
     * @param array<string,mixed> $cdn
     * @param array<string,string[]> $combine grouped by media value
     * @param array<string,string[]> $googleFonts grouped by API endpoint
     * @param string[] $usedUrls
     */
    private function processCss(string $html, array $css, array $cdn, array &$combine, array &$googleFonts, array &$usedUrls): string
    {
        $anchored = false;

        $result = preg_replace_callback('#<link\b(?:"[^"]*"|\'[^\']*\'|[^"\'>])*>#i', function (array $m) use ($css, $cdn, &$combine, &$googleFonts, &$usedUrls, &$anchored): string {
            $attrs = $this->parseAttrs($m[0]);
            $rel   = preg_split('/\s+/', strtolower(trim((string) ($attrs['rel'] ?? '')))) ?: [];
            $href  = (string) ($attrs['href'] ?? '');

            // rel="alternate stylesheet" is a sheet the browser leaves disabled.
            if (! in_array('stylesheet', $rel, true) || in_array('alternate', $rel, true)
                || $href === '' || str_contains($href, '/wpp-cache/')
            ) {
                return $m[0];
            }

            $local = Assets::isLocal($href);
            $key   = $this->key($href);
            $this->collection->record('css', $this->group($href, $local), $key);

            if ($this->isDisabled($css, $key)) {
                return '';
            }

            if (str_contains($href, 'fonts.googleapis.com')) {
                return $this->googleFontLink($href, $attrs, $css, $googleFonts, $anchored);
            }

            $mediaValue = strtolower(trim((string) ($attrs['media'] ?? '')));
            if ($mediaValue === '') {
                $mediaValue = 'all';
            }

            // Only genuine stylesheets may be collected: the href comes from page
            // markup, so anything else would be read off disk and re-published.
            // A media-scoped sheet is left alone: its rules would otherwise be
            // inlined unconditionally.
            if (! empty($css['remove_unused']) && $local && $this->isStylesheetUrl($href)
                && ($mediaValue === 'all' || $mediaValue === 'screen')
            ) {
                $usedUrls[] = $href;
                return $this->anchor(self::CSS_ANCHOR, $anchored);
            }

            if ($local && $this->mapHas($css['inline'] ?? [], $key)) {
                // Inlining moves the rules into the document, so relative url()
                // and @import must be resolved against the original stylesheet
                // or every referenced font and image 404s.
                $content = $this->minifier->cssForCombine($href);
                if ($content !== '') {
                    return '<style>' . $content . '</style>';
                }
            }

            $deferThis = ! empty($css['defer']) && ! $this->fileExcluded($css, $href);

            if ($local && $this->mapHas($css['combine'] ?? [], $key) && (empty($css['defer']) || $deferThis)) {
                $combine[$mediaValue][] = $href;
                return $this->anchor(self::CSS_ANCHOR, $anchored);
            }

            if ($local && $this->mapHas($css['minify'] ?? [], $key)) {
                $href = $this->minifier->file($href, 'css');
            }
            if (! empty($cdn['enabled']) && $local) {
                $href = $this->cdnRewrite($href, $cdn);
            }
            $attrs['href'] = $href;

            if ($deferThis) {
                // The noscript copy is what styles the page when the onload swap
                // never runs. Its id is dropped so the two never collide.
                $fallback = $attrs;
                unset($fallback['id']);

                // A preload whose media query does not match is never fetched, so
                // its onload never fires: the media moves onto the handler.
                unset($attrs['media']);
                $attrs['rel']    = 'preload';
                $attrs['as']     = 'style';
                $attrs['onload'] = 'this.onload=null;' . $this->onloadMedia($mediaValue) . "this.rel='stylesheet'";

                return $this->buildVoidTag('link', $attrs)
                    . '<noscript>' . $this->buildVoidTag('link', $fallback) . '</noscript>';
            }

            return $this->buildVoidTag('link', $attrs);
        }, $html);

        return $result ?? $html;
    }

    private function minifyInlineStyles(string $html): string
    {
        $result = preg_replace_callback('#<style\b([^>]*)>(.*?)</style>#is', function (array $m): string {
            if (trim($m[2]) === '') {
                return $m[0];
            }
            return '<style' . $m[1] . '>' . $this->minifier->cssCode($m[2]) . '</style>';
        }, $html);

        return $result ?? $html;
    }

    /**
     * @param array<string,mixed> $js
     * @param array<string,mixed> $cdn
     * @param string[] $combine
     */
    private function processJs(string $html, array $js, array $cdn, array &$combine, bool &$delayed): string
    {
        $delay    = ! empty($js['delay']);
        $anchored = false;

        $result = preg_replace_callback('#<script\b((?:"[^"]*"|\'[^\']*\'|[^"\'>])*)>(.*?)</script>#is', function (array $m) use ($js, $cdn, &$combine, &$delayed, &$anchored, $delay): string {
            $attrs   = $this->parseAttrs('<script' . $m[1] . '>');
            $content = $m[2];
            $type    = strtolower((string) ($attrs['type'] ?? ''));

            // Allow-list, not a deny-list: anything that is not executable
            // JavaScript (JSON-LD, import maps, speculation rules, template and
            // data blocks) must be passed through byte for byte.
            if (! $this->isJavaScriptType($type)) {
                return $m[0];
            }

            $src = (string) ($attrs['src'] ?? '');

            if ($src === '') {
                $code = (! empty($js['minify_inline']) && trim($content) !== '')
                    ? $this->minifier->jsCode($content)
                    : $content;

                if ($delay && $type !== 'module' && trim($code) !== '' && ! $this->delayExcluded($js, $code)) {
                    $attrs['type'] = 'wpp/lazy';
                    $delayed = true;
                    return $this->scriptTag($attrs, $code);
                }
                return $code !== $content ? $this->scriptTag($attrs, $code) : $m[0];
            }

            if (str_contains($src, '/wpp-cache/')) {
                return $m[0];
            }

            $local = Assets::isLocal($src);
            $key   = $this->key($src);
            $this->collection->record('js', $this->group($src, $local), $key);

            if ($this->isDisabled($js, $key)) {
                return '';
            }

            if ($local && $this->mapHas($js['inline'] ?? [], $key)) {
                $code = Assets::contents($src);
                if ($code !== null) {
                    if ($this->mapHas($js['minify'] ?? [], $key)) {
                        $code = $this->minifier->jsCode($code);
                    }
                    unset($attrs['src']);
                    if ($delay && $type !== 'module' && ! $this->delayExcluded($js, $src)) {
                        $attrs['type'] = 'wpp/lazy';
                        $delayed = true;
                    }
                    return $this->scriptTag($attrs, $code);
                }
            }

            $deferThis = ! empty($js['defer']) && $type !== 'module' && ! $this->fileExcluded($js, $src);

            // A script the user excluded from delay must not be swept into the
            // combined bundle, which is itself delayed as a whole.
            $delayExcluded = $delay && $this->delayExcluded($js, $src);

            if ($local && ! $delayExcluded
                && $this->mapHas($js['combine'] ?? [], $key)
                && (empty($js['defer']) || $deferThis)
            ) {
                $combine[] = $src;
                // The bundle replaces the first combined tag: emitting it before
                // </body> would run it after inline code that depends on it.
                return $this->anchor(self::JS_ANCHOR, $anchored);
            }

            if ($local && $this->mapHas($js['minify'] ?? [], $key)) {
                $src = $this->minifier->file($src, 'js');
            }
            if (! empty($cdn['enabled']) && $local) {
                $src = $this->cdnRewrite($src, $cdn);
            }

            if ($delay && $type !== 'module' && ! $this->delayExcluded($js, $src)) {
                unset($attrs['src']);
                $attrs['type']         = 'wpp/lazy';
                $attrs['data-wpp-src'] = $src;
                $delayed = true;
                return $this->scriptTag($attrs, '');
            }

            $attrs['src'] = $src;
            if ($deferThis) {
                $attrs['defer'] = true;
            }

            return $this->scriptTag($attrs, '');
        }, $html);

        return $result ?? $html;
    }

    /** @param array<string,mixed> $js whether a script src/inline-code is excluded from delay */
    private function delayExcluded(array $js, string $needle): bool
    {
        foreach ((array) ($js['delay_exclude'] ?? []) as $pattern) {
            if ($pattern !== '' && str_contains($needle, (string) $pattern)) {
                return true;
            }
        }
        return false;
    }

    private function loaderScript(): string
    {
        return '<script id="wpp-delay-js">'
            . '(function(){var d=false,ev=["keydown","mousedown","mousemove","touchstart","touchmove","wheel","scroll"];'
            . 'function load(){if(d)return;d=true;ev.forEach(function(e){window.removeEventListener(e,load,{passive:true});});'
            . 'var n=[].slice.call(document.querySelectorAll(\'script[type="wpp/lazy"]\'));'
            . '(function go(i){if(i>=n.length){document.dispatchEvent(new Event("DOMContentLoaded"));window.dispatchEvent(new Event("load"));return;}'
            . 'var o=n[i],s=document.createElement("script");for(var k=0;k<o.attributes.length;k++){var a=o.attributes[k];if(a.name!=="type"&&a.name!=="data-wpp-src"){s.setAttribute(a.name,a.value);}}'
            . 'var src=o.getAttribute("data-wpp-src");if(src){s.src=src;s.onload=s.onerror=function(){go(i+1);};o.parentNode.replaceChild(s,o);}'
            . 'else{s.text=o.textContent;o.parentNode.replaceChild(s,o);go(i+1);}})(0);}'
            . 'ev.forEach(function(e){window.addEventListener(e,load,{passive:true});});})();</script>';
    }

    /**
     * @param array<string,mixed> $media
     * @param array<string,mixed> $cdn
     * @param array<int,array{src:string,srcset:string,sizes:string,type:string}> $preloads collected LCP images
     */
    private function processImages(string $html, array $media, array $cdn, array &$preloads): string
    {
        // The page-URL control, matching the video one: the by-name/by-src
        // control is images_exclude.
        if (Url::anyMatch((array) ($media['images_exclude_urls'] ?? []), $this->currentUrl)) {
            return $html;
        }

        $disableOnMobile = ! empty($media['images_lazy_disable_mobile']) && wp_is_mobile();
        $lazy       = ! empty($media['images_lazy']) && ! $disableOnMobile;
        $cdnOn      = ! empty($cdn['enabled']);
        $responsive = ! empty($media['images_responsive']);
        $dimensions = ! empty($media['images_dimensions']);
        $lcp        = max(0, (int) ($media['lcp_images'] ?? 0));
        $nextgen    = ! empty($media['webp']);

        if (! $lazy && ! $cdnOn && ! $responsive && ! $dimensions && ! $nextgen && $lcp === 0) {
            return $html;
        }

        $containers = array_filter((array) ($media['images_exclude_containers'] ?? []));
        if ($containers !== []) {
            $html = $this->markContainers($html, $containers);
        }
        if ($nextgen) {
            $html = $this->markPictureImages($html);
        }

        $index  = 0;
        $result = preg_replace_callback('#<img\b(?:"[^"]*"|\'[^\']*\'|[^"\'>])*>#i', function (array $m) use ($media, $cdn, $lazy, $cdnOn, $responsive, $dimensions, $lcp, $nextgen, &$preloads, &$index): string {
            $inPicture = str_contains($m[0], 'data-wpp-picture="1"');
            if ($inPicture) {
                $m[0] = str_replace(' data-wpp-picture="1"', '', $m[0]);
            }

            if (str_contains($m[0], 'data-wpp-skip')) {
                return str_replace(' data-wpp-skip="1"', '', $m[0]);
            }

            $attrs = $this->parseAttrs($m[0]);
            $src   = (string) ($attrs['src'] ?? '');
            if ($src === '' || $this->imgExcluded($attrs, $src, $media)) {
                return $m[0];
            }

            $isLcp = $lcp > 0 && $index < $lcp;
            $index++;
            $changed = false;

            if ($dimensions && (empty($attrs['width']) || empty($attrs['height']))) {
                [$w, $h] = $this->imageDimensions($src);
                if ($w !== null && $h !== null) {
                    if (empty($attrs['width'])) {
                        $attrs['width'] = $w;
                    }
                    if (empty($attrs['height'])) {
                        $attrs['height'] = $h;
                    }
                    $changed = true;
                }
            }

            if ($responsive && (string) ($attrs['srcset'] ?? '') === '' && Assets::isLocal($src)
                && function_exists('attachment_url_to_postid')
            ) {
                $id = attachment_url_to_postid(Assets::stripQuery($src));
                if ($id) {
                    $srcset = wp_get_attachment_image_srcset($id, 'full');
                    if ($srcset) {
                        $attrs['srcset'] = $srcset;
                        $sizes = wp_get_attachment_image_sizes($id, 'full');
                        if ($sizes) {
                            $attrs['sizes'] = $sizes;
                        }
                        $changed = true;
                    }
                }
            }

            // Next-gen siblings are probed on disk, which only the origin URLs
            // can resolve to.
            $originSrc    = $src;
            $originSrcset = (string) ($attrs['srcset'] ?? '');

            if ($cdnOn && Assets::isLocal($src)) {
                $attrs['src'] = $this->cdnRewrite($src, $cdn);
                $changed = true;
            }

            // Responsive images serve from srcset, so leaving it alone means
            // most images bypass the CDN entirely.
            if ($cdnOn && ! empty($attrs['srcset']) && is_string($attrs['srcset'])) {
                $rewritten = $this->cdnRewriteSrcset((string) $attrs['srcset'], $cdn);
                if ($rewritten !== $attrs['srcset']) {
                    $attrs['srcset'] = $rewritten;
                    $changed = true;
                }
            }

            if ($isLcp) {
                $attrs['loading'] = 'eager';
                if (empty($attrs['fetchpriority'])) {
                    $attrs['fetchpriority'] = 'high';
                }
                $changed = true;
            } elseif ($lazy) {
                if (empty($attrs['loading'])) {
                    $attrs['loading'] = 'lazy';
                    $changed = true;
                }
                if (empty($attrs['decoding'])) {
                    $attrs['decoding'] = 'async';
                    $changed = true;
                }
            }

            $imgTag = $changed ? $this->buildVoidTag('img', $attrs) : $m[0];

            // An <img> that already sits in a <picture> only ever sees that
            // picture's own <source>s, so wrapping it again disables them.
            $sources = $nextgen && ! $inPicture
                ? $this->nextgenSources($originSrc, $originSrcset, (string) ($attrs['sizes'] ?? ''), $cdnOn ? $cdn : null)
                : [];

            if ($isLcp) {
                $preloads[] = $this->preloadFor($sources, $attrs, $src);
            }

            return $sources === [] ? $imgTag : $this->wrapPicture($imgTag, $sources);
        }, $html);

        return $result ?? $html;
    }

    /**
     * Mark every <img> that already lives inside a <picture>.
     */
    private function markPictureImages(string $html): string
    {
        if (stripos($html, '<picture') === false) {
            return $html;
        }

        $result = preg_replace_callback(
            '#<picture\b(?:"[^"]*"|\'[^\']*\'|[^"\'>])*>.*?</picture\s*>#is',
            static fn (array $m): string => (string) preg_replace('#<img\b#i', '<img data-wpp-picture="1"', $m[0]),
            $html
        );

        return $result ?? $html;
    }

    /**
     * @param list<array{srcset:string,sizes:string,type:string}> $sources
     */
    private function wrapPicture(string $imgTag, array $sources): string
    {
        $tags = '';
        foreach ($sources as $source) {
            $tags .= '<source srcset="' . esc_attr($source['srcset']) . '"'
                . ($source['sizes'] !== '' ? ' sizes="' . esc_attr($source['sizes']) . '"' : '')
                . ' type="' . $source['type'] . '">';
        }

        return '<picture>' . $tags . $imgTag . '</picture>';
    }

    /**
     * The avif/webp <source>s whose sibling files all exist, best format first.
     *
     * @param array<string,mixed>|null $cdn
     * @return list<array{srcset:string,sizes:string,type:string}>
     */
    private function nextgenSources(string $src, string $srcset, string $sizes, ?array $cdn): array
    {
        $sources = [];
        foreach (['avif' => 'image/avif', 'webp' => 'image/webp'] as $format => $mime) {
            $nextgen = $this->nextgenSrcset($src, $srcset, $format, $cdn);
            if ($nextgen === '') {
                continue;
            }
            $sources[] = [
                'srcset' => $nextgen,
                // A <source> without sizes defaults to a source size of 100vw,
                // so width descriptors would resolve to a larger candidate than
                // the <img> itself picks.
                'sizes'  => $this->hasWidthDescriptor($nextgen) ? $sizes : '',
                'type'   => $mime,
            ];
        }

        return $sources;
    }

    /**
     * Build a next-gen srcset from the origin src/srcset. Empty unless every
     * candidate has a sibling: a partial set would let the browser pick a small
     * next-gen file for a slot the original srcset covered.
     *
     * @param array<string,mixed>|null $cdn
     */
    private function nextgenSrcset(string $src, string $srcset, string $format, ?array $cdn): string
    {
        $entries = [];

        if ($srcset !== '') {
            foreach (explode(',', $srcset) as $candidate) {
                $candidate = trim($candidate);
                if ($candidate === '') {
                    continue;
                }
                $parts      = preg_split('/\s+/', $candidate, 2);
                $url        = $parts[0];
                $descriptor = isset($parts[1]) ? ' ' . $parts[1] : '';
                if (! $this->nextgenExists($url, $format)) {
                    return '';
                }
                $entries[] = $this->nextgenUrl($url, $format, $cdn) . $descriptor;
            }
        } elseif ($src !== '' && $this->nextgenExists($src, $format)) {
            $entries[] = $this->nextgenUrl($src, $format, $cdn);
        }

        return implode(', ', $entries);
    }

    private function hasWidthDescriptor(string $srcset): bool
    {
        return (bool) preg_match('/\s\d+w\s*(?:,|$)/', $srcset);
    }

    /**
     * The LCP preload for one image: whatever the browser will actually decode,
     * which is the first <source> when a <picture> was emitted.
     *
     * @param list<array{srcset:string,sizes:string,type:string}> $sources
     * @param array<string,string|bool> $attrs
     * @return array{src:string,srcset:string,sizes:string,type:string}
     */
    private function preloadFor(array $sources, array $attrs, string $src): array
    {
        if ($sources === []) {
            return [
                'src'    => (string) ($attrs['src'] ?? $src),
                'srcset' => (string) ($attrs['srcset'] ?? ''),
                'sizes'  => (string) ($attrs['sizes'] ?? ''),
                'type'   => '',
            ];
        }

        $first = $sources[0];
        $lead  = $this->firstCandidate($first['srcset']);

        return [
            'src'    => $lead,
            'srcset' => $lead === $first['srcset'] ? '' : $first['srcset'],
            'sizes'  => $first['sizes'],
            'type'   => $first['type'],
        ];
    }

    /** The URL of a srcset's first candidate, without its descriptor. */
    private function firstCandidate(string $srcset): string
    {
        $parts = preg_split('/\s+/', trim(explode(',', $srcset, 2)[0]), 2) ?: [];

        return (string) ($parts[0] ?? '');
    }

    /** @param array<string,mixed>|null $cdn */
    private function nextgenUrl(string $url, string $format, ?array $cdn): string
    {
        $pos   = strpos($url, '?');
        $query = $pos === false ? '' : substr($url, $pos);
        $base  = ($pos === false ? $url : substr($url, 0, $pos)) . '.' . $format;

        return ($cdn !== null && Assets::isLocal($base) ? $this->cdnRewrite($base, $cdn) : $base) . $query;
    }

    /**
     * Existence probe for an image's next-gen sibling. Images are outside
     * Assets::toPath()'s readable set, so the path is resolved here; it is only
     * ever passed to is_file(), never read.
     */
    private function nextgenExists(string $url, string $format): bool
    {
        $url        = Assets::stripQuery(Assets::normalize($url));
        $contentUrl = content_url();
        $home       = home_url();

        if (str_starts_with($url, $contentUrl)) {
            $path = WP_CONTENT_DIR . '/' . ltrim(substr($url, strlen($contentUrl)), '/');
        } elseif (str_starts_with($url, $home)) {
            $path = ABSPATH . ltrim(substr($url, strlen($home)), '/');
        } elseif (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            $path = ABSPATH . ltrim($url, '/');
        } else {
            return false;
        }

        $real = realpath($path . '.' . $format);
        // A zero-byte file is a half-finished conversion: advertising it in a
        // <source> would serve a broken image in place of a working one.
        if ($real === false || ! is_file($real) || filesize($real) === 0) {
            return false;
        }

        foreach ([ABSPATH, WP_CONTENT_DIR] as $root) {
            $resolved = realpath($root);
            if ($resolved !== false && str_starts_with($real, rtrim($resolved, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{0:?string,1:?string} width/height from a sized filename or attachment metadata */
    private function imageDimensions(string $src): array
    {
        if (preg_match('/-(\d+)x(\d+)\.[a-z0-9]+(?:\?.*)?$/i', $src, $m)) {
            return [$m[1], $m[2]];
        }
        if (Assets::isLocal($src) && function_exists('attachment_url_to_postid')) {
            $id = attachment_url_to_postid(Assets::stripQuery($src));
            if ($id) {
                $meta = wp_get_attachment_metadata($id);
                if (! empty($meta['width']) && ! empty($meta['height'])) {
                    return [(string) $meta['width'], (string) $meta['height']];
                }
            }
        }
        return [null, null];
    }

    /**
     * Add a data-wpp-skip marker to <img> tags inside elements matching the
     * given CSS-ish selectors (#id, .class, or a bare id/class name).
     *
     * @param string[] $selectors
     */
    private function markContainers(string $html, array $selectors): string
    {
        foreach ($selectors as $selector) {
            $selector = trim((string) $selector);
            if ($selector === '') {
                continue;
            }

            if (str_starts_with($selector, '#')) {
                $attr = 'id';
                $name = substr($selector, 1);
            } elseif (str_starts_with($selector, '.')) {
                $attr = 'class';
                $name = substr($selector, 1);
            } else {
                $attr = 'id|class';
                $name = $selector;
            }
            $name = preg_quote($name, '#');

            $pattern = '#<([a-zA-Z0-9]+)\b[^>]*\b(?:' . $attr . ')\s*=\s*["\'][^"\']*\b' . $name . '\b[^"\']*["\'][^>]*>#i';
            if (! preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $ranges = [];
            foreach ($matches[0] as $i => $match) {
                $tag      = strtolower($matches[1][$i][0]);
                $openEnd  = (int) $match[1] + strlen($match[0]);
                $ranges[] = [$openEnd, $this->matchingClose($html, $tag, $openEnd)];
            }

            usort($ranges, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
            foreach ($ranges as [$start, $end]) {
                $segment = substr($html, $start, $end - $start);
                $marked  = (string) preg_replace('#<img\b#i', '<img data-wpp-skip="1"', $segment);
                $html    = substr($html, 0, $start) . $marked . substr($html, $end);
            }
        }

        return $html;
    }

    private function matchingClose(string $html, string $tag, int $from): int
    {
        $length = strlen($html);
        if (! preg_match_all('#<(/?)' . preg_quote($tag, '#') . '\b[^>]*>#i', $html, $matches, PREG_OFFSET_CAPTURE, $from)) {
            return $length;
        }

        $depth = 1;
        foreach ($matches[0] as $i => $match) {
            if ($matches[1][$i][0] === '/') {
                $depth--;
                if ($depth === 0) {
                    return (int) $match[1] + strlen($match[0]);
                }
            } else {
                $depth++;
            }
        }

        return $length;
    }

    /** @param array<string,mixed> $media */
    private function processIframes(string $html, array $media): string
    {
        if (empty($media['videos_lazy'])) {
            return $html;
        }

        $excludes = (array) ($media['videos_exclude_urls'] ?? []);
        if (Url::anyMatch($excludes, $this->currentUrl)) {
            return $html;
        }

        $result = preg_replace_callback('#<iframe\b(?:"[^"]*"|\'[^\']*\'|[^"\'>])*>#i', function (array $m) use ($excludes): string {
            $attrs = $this->parseAttrs($m[0]);
            if (! empty($attrs['loading'])) {
                return $m[0];
            }
            if (Url::anyMatch($excludes, (string) ($attrs['src'] ?? ''))) {
                return $m[0];
            }
            $attrs['loading'] = 'lazy';
            return $this->buildVoidTag('iframe', $attrs);
        }, $html);

        return $result ?? $html;
    }

    /** @param array<string,mixed> $media */
    private function imgExcluded(array $attrs, string $src, array $media): bool
    {
        foreach ((array) ($media['images_exclude'] ?? []) as $name) {
            if ($name !== '' && str_contains($src, (string) $name)) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $cfg */
    private function isDisabled(array $cfg, string $key): bool
    {
        if (empty($cfg['disable'][$key])) {
            return false;
        }
        $position = $cfg['disable_position'][$key] ?? 'everywhere';

        if ($position === 'selected') {
            return Url::anyMatch((array) ($cfg['disable_selected'][$key] ?? []), $this->currentUrl);
        }
        if ($position === 'except') {
            return ! Url::anyMatch((array) ($cfg['disable_except'][$key] ?? []), $this->currentUrl);
        }
        return true;
    }

    /**
     * @param string[] $urls
     * @param array<string,mixed> $cdn
     */
    private function combine(array $urls, string $type, array $cdn): ?string
    {
        $hash = md5($this->sourceKey($urls));
        $file = WPP_CACHE_DIR . $hash . '.' . $type;
        $url  = WPP_CACHE_URL . $hash . '.' . $type;

        // The cache key is the source list, so an artifact written from a failed
        // read would be re-served for as long as the sources keep their mtime.
        if (is_file($file) && filesize($file) === 0) {
            @unlink($file);
        }

        if (! is_file($file)) {
            $buffer = $this->combineSources($urls, $type);
            if ($buffer === null || ! $this->writeAtomic($file, $buffer)) {
                return null;
            }
        }

        return ! empty($cdn['enabled']) ? $this->cdnRewrite($url, $cdn) : $url;
    }

    /**
     * Concatenate the sources, or null when any of them could not be read.
     *
     * @param string[] $urls
     */
    private function combineSources(array $urls, string $type): ?string
    {
        $buffer = '';
        foreach ($urls as $assetUrl) {
            $piece = $this->fetchForCombine($assetUrl, $type);
            if ($this->fetchFailed($piece, $assetUrl)) {
                return null;
            }
            $buffer .= $piece . "\n";
        }

        return $type === 'css' ? CssMinifier::hoistAtRules($buffer) : $buffer;
    }

    /** An empty result is a failed read unless the source file is itself empty. */
    private function fetchFailed(string $piece, string $url): bool
    {
        if (trim($piece, " \t\r\n;") !== '') {
            return false;
        }
        $path = Assets::toPath($url);

        return $path === null || (int) @filesize($path) > 0;
    }

    /** Write through a temp file so a partial write is never published. */
    private function writeAtomic(string $file, string $contents): bool
    {
        $dir = dirname($file);
        if (! is_dir($dir) && ! wp_mkdir_p($dir)) {
            return false;
        }

        $tmp = $file . '.' . getmypid() . '.tmp';
        if (file_put_contents($tmp, $contents, LOCK_EX) !== strlen($contents) || ! rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }

        return true;
    }

    /**
     * Cache identity of a source list: edits to a file must produce a new key
     * even when its URL never changes.
     *
     * @param string[] $urls
     */
    private function sourceKey(array $urls): string
    {
        $parts = [];
        foreach ($urls as $url) {
            $path    = Assets::toPath((string) $url);
            $parts[] = $url . ':' . ($path === null ? '' : (string) @filemtime($path));
        }

        return implode('|', $parts);
    }

    private function fetchForCombine(string $assetUrl, string $type): string
    {
        return $type === 'css'
            ? $this->minifier->cssForCombine($assetUrl)
            : $this->minifier->jsForCombine($assetUrl);
    }

    /**
     * Build (and cache per URL) the used-CSS for the current page.
     *
     * @param string[] $urls
     * @param array<string,mixed> $css
     */
    private function buildUsedCss(array $urls, string $html, array $css): string
    {
        $safelist = (array) ($css['used_safelist'] ?? []);

        // Keyed on the queried object rather than the raw URL. Keying on the URL
        // let anyone mint unlimited cache entries with junk query strings and
        // fill the disk.
        $hash = md5($this->usedCssScope() . '|' . $this->sourceKey($urls) . '|' . implode('|', $safelist));
        $file = WPP_CACHE_DIR . 'used/' . $hash . '.css';

        if (is_file($file)) {
            return (string) file_get_contents($file);
        }
        if (! is_dir(WPP_CACHE_DIR . 'used/') && ! wp_mkdir_p(WPP_CACHE_DIR . 'used/')) {
            return '';
        }

        $used = CssMinifier::hoistAtRules((new UsedCss())->build($html, $urls, $safelist));
        file_put_contents($file, $used, LOCK_EX);

        return $used;
    }

    /** @param array<string,mixed> $cdn */
    /** @param array<string,mixed> $cdn Rewrite each candidate, keeping its descriptor. */
    private function cdnRewriteSrcset(string $srcset, array $cdn): string
    {
        $out = [];
        foreach (explode(',', $srcset) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }
            $parts = preg_split('/\s+/', $candidate, 2);
            $url   = $parts[0];
            $desc  = isset($parts[1]) ? ' ' . $parts[1] : '';
            $out[] = (Assets::isLocal($url) ? $this->cdnRewrite($url, $cdn) : $url) . $desc;
        }

        return implode(', ', $out);
    }

    private function cdnRewrite(string $url, array $cdn): string
    {
        $hostname = rtrim(trim((string) ($cdn['hostname'] ?? '')), '/');

        // Without a scheme the prefix is a document-relative path, which would
        // 404 every asset on the site.
        if ($hostname === ''
            || (! str_starts_with($hostname, '//') && parse_url($hostname, PHP_URL_SCHEME) === null)
        ) {
            return $url;
        }

        if (Url::anyMatch((array) ($cdn['exclude'] ?? []), $url)) {
            return $url;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $hostname . $url;
        }

        $path = $this->originPath($url);

        return $path === null ? $url : $hostname . $path;
    }

    /** Path of a URL served by this site, ignoring the scheme, or null. */
    private function originPath(string $url): ?string
    {
        $strip  = static fn (string $u): string => (string) preg_replace('#^https?://#i', '', $u);
        $bare   = $strip(Assets::normalize($url));
        $origin = $strip(home_url());

        if ($origin === '' || ! str_starts_with($bare, $origin)) {
            return null;
        }

        $path = substr($bare, strlen($origin));

        if ($path === '') {
            return '/';
        }

        return str_starts_with($path, '/') ? $path : null;
    }

    private function anchor(string $marker, bool &$anchored): string
    {
        if ($anchored) {
            return '';
        }
        $anchored = true;

        return $marker;
    }

    /** Put $insert where the anchor stands, or before $fallback when there is none. */
    private function placeAnchored(string $html, string $marker, string $insert, string $fallback): string
    {
        if (str_contains($html, $marker)) {
            return str_replace($marker, $insert, $html);
        }

        return $insert === '' ? $html : $this->injectBefore($html, $fallback, $insert);
    }

    /** @param array{src:string,srcset:string,sizes:string,type:string} $preload */
    private function imagePreloadTag(array $preload): string
    {
        $tag = '<link rel="preload" as="image" fetchpriority="high" href="' . esc_url($preload['src']) . '"';

        // The type is what lets the browser both match the preload to the
        // <picture> source it will select and skip a format it cannot decode.
        if ($preload['type'] !== '') {
            $tag .= ' type="' . esc_attr($preload['type']) . '"';
        }

        // Without the candidate list the browser preloads the src and then
        // downloads a different candidate for the element itself.
        if ($preload['srcset'] !== '') {
            $tag .= ' imagesrcset="' . esc_attr($preload['srcset']) . '"';
        }
        if ($preload['sizes'] !== '') {
            $tag .= ' imagesizes="' . esc_attr($preload['sizes']) . '"';
        }

        return $tag . '>';
    }

    /** Assignment restoring a stylesheet's media once a preload has loaded. */
    private function onloadMedia(string $media): string
    {
        $media = str_replace(['\\', "'"], '', $media);

        return $media === '' || $media === 'all' ? '' : "this.media='" . $media . "';";
    }

    private function injectBefore(string $html, string $needle, string $insert): string
    {
        $pos = stripos($html, $needle);
        if ($pos === false) {
            return $html . $insert;
        }
        return substr($html, 0, $pos) . $insert . substr($html, $pos);
    }

    private function group(string $url, bool $local): string
    {
        if (! $local) {
            return 'external';
        }
        $path = (string) Assets::toPath($url);
        if (str_contains($path, '/plugins/') || str_contains($path, '/mu-plugins/')) {
            return 'plugin';
        }
        return 'theme';
    }

    private function key(string $url): string
    {
        return Assets::stripQuery(Assets::normalize($url));
    }

    /** @param array<string,mixed> $map */
    /** A bounded identifier for the page being rendered. */
    private function usedCssScope(): string
    {
        if (function_exists('is_singular') && is_singular()) {
            $id = get_queried_object_id();
            if ($id) {
                return 'post-' . $id;
            }
        }

        if (function_exists('is_front_page') && is_front_page()) {
            return 'front';
        }

        $template = $GLOBALS['template'] ?? '';
        return is_string($template) && $template !== '' ? 'tpl-' . basename($template) : 'generic';
    }

    /** True only for script types the browser executes as JavaScript. */
    private function isJavaScriptType(string $type): bool
    {
        return in_array($type, [
            '',
            'text/javascript',
            'application/javascript',
            'application/ecmascript',
            'text/ecmascript',
            'module',
        ], true);
    }

    private function isStylesheetUrl(string $url): bool
    {
        $path = (string) parse_url(Assets::stripQuery($url), PHP_URL_PATH);
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'css';
    }

    private function mapHas(array $map, string $key): bool
    {
        return ! empty($map[$key]);
    }

    /** @param array<string,mixed> $cfg whether a URL is in the "exclude from async" list */
    private function fileExcluded(array $cfg, string $url): bool
    {
        foreach ((array) ($cfg['file_exclude'] ?? []) as $pattern) {
            if ($pattern !== '' && (str_contains($url, (string) $pattern) || Url::matches((string) $pattern, $url))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Handle a Google Fonts stylesheet link: collect for combination, or apply
     * font-display + async in place.
     *
     * @param array<string,string|bool> $attrs
     * @param array<string,mixed> $css
     * @param array<string,string[]> $googleFonts
     */
    private function googleFontLink(string $href, array $attrs, array $css, array &$googleFonts, bool &$anchored): string
    {
        parse_str((string) parse_url($href, PHP_URL_QUERY), $query);

        // parse_str keeps only the last value, and the css2 endpoint repeats
        // family= once per family.
        $families = $this->queryValues((string) parse_url($href, PHP_URL_QUERY), 'family');
        $endpoint = str_contains((string) parse_url($href, PHP_URL_PATH), '/css2') ? 'css2' : 'css';

        if (! empty($css['combine_fonts']) || ! empty($css['host_fonts'])) {
            if ($families !== []) {
                foreach ($families as $family) {
                    $googleFonts[$endpoint][] = $family;
                }
                return $this->anchor(self::CSS_ANCHOR, $anchored);
            }
        }

        $display = (string) ($css['font_display'] ?? 'none');
        if ($display !== 'none' && $display !== '' && ! isset($query['display'])) {
            $href .= (parse_url($href, PHP_URL_QUERY) ? '&' : '?') . 'display=' . $display;
            $attrs['href'] = $href;
        }

        if (! empty($css['defer']) && ! $this->fileExcluded($css, $href)) {
            $attrs['rel']    = 'preload';
            $attrs['as']     = 'style';
            $attrs['onload'] = "this.onload=null;this.rel='stylesheet'";
        }

        return $this->buildVoidTag('link', $attrs);
    }

    private function styleTag(string $url, bool $defer, string $media = 'all'): string
    {
        $attr = $media === '' || $media === 'all' ? '' : ' media="' . esc_attr($media) . '"';
        $plain = '<link rel="stylesheet" href="' . esc_url($url) . '"' . $attr . '>';

        if ($defer) {
            // The noscript copy is what styles the page when the onload swap
            // never runs, which is otherwise a completely unstyled document.
            return '<link rel="preload" as="style" href="' . esc_url($url)
                . '" onload="this.onload=null;' . $this->onloadMedia($media) . 'this.rel=\'stylesheet\'">'
                . '<noscript>' . $plain . '</noscript>';
        }

        return $plain;
    }

    /**
     * @param string[] $families raw, still-encoded family values
     * @param array<string,mixed> $css
     */
    private function googleFontsUrl(string $endpoint, array $families, array $css): string
    {
        $families = array_values(array_unique($families));

        $url = $endpoint === 'css2'
            ? 'https://fonts.googleapis.com/css2?family=' . implode('&family=', $families)
            : 'https://fonts.googleapis.com/css?family=' . implode('|', $families);

        $display = (string) ($css['font_display'] ?? 'none');
        if (in_array($display, ['auto', 'block', 'swap', 'fallback', 'optional'], true)) {
            $url .= '&display=' . $display;
        }

        return $url;
    }

    /**
     * Values of a repeated query parameter, left encoded as the source had them.
     *
     * @return string[]
     */
    private function queryValues(string $query, string $name): array
    {
        $out = [];
        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            if (rawurldecode($key) === $name && $value !== '') {
                $out[] = $value;
            }
        }

        return $out;
    }

    /**
     * Parse a single tag's attributes into a map. Boolean attributes => true.
     *
     * @return array<string, string|bool>
     */
    private function parseAttrs(string $tag): array
    {
        $inner = (string) preg_replace('#^<\s*[a-zA-Z0-9]+#', '', trim($tag));
        $inner = (string) preg_replace('#/?>$#', '', $inner);

        $attrs = [];
        preg_match_all(
            '#([a-zA-Z0-9:_.\-]+)\s*(=\s*("([^"]*)"|\'([^\']*)\'|([^\s"\'=>]+)))?#',
            $inner,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $m) {
            $name = strtolower($m[1]);
            if ($name === '') {
                continue;
            }
            if (! isset($m[2]) || $m[2] === '') {
                $attrs[$name] = true;
                continue;
            }
            $value = $m[4] ?? '';
            if ($value === '' && ($m[5] ?? '') !== '') {
                $value = $m[5];
            }
            if ($value === '' && ($m[6] ?? '') !== '') {
                $value = $m[6];
            }
            $attrs[$name] = html_entity_decode($value, ENT_QUOTES);
        }

        return $attrs;
    }

    /** @param array<string, string|bool> $attrs */
    private function buildVoidTag(string $name, array $attrs): string
    {
        return '<' . $name . $this->renderAttrs($attrs) . '>';
    }

    /** @param array<string, string|bool> $attrs */
    private function scriptTag(array $attrs, string $content): string
    {
        return '<script' . $this->renderAttrs($attrs) . '>' . $content . '</script>';
    }

    /** @param array<string, string|bool> $attrs */
    private function renderAttrs(array $attrs): string
    {
        $out = '';
        foreach ($attrs as $name => $value) {
            if ($value === true) {
                $out .= ' ' . $name;
            } elseif ($value === false || $value === null) {
                continue;
            } else {
                $out .= ' ' . $name . '="' . esc_attr((string) $value) . '"';
            }
        }
        return $out;
    }
}
