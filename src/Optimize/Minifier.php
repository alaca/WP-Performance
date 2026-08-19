<?php

declare(strict_types=1);

namespace WPP\Optimize;

use WPP\Support\Assets;

/**
 * Minifies CSS/JS strings, produces cached minified copies of local files, and
 * fetches+minifies assets for combination. Dependency-free.
 */
final class Minifier
{
    private CssMinifier $css;
    private JsMinifier $js;

    public function __construct()
    {
        $this->css = new CssMinifier();
        $this->js  = new JsMinifier();
    }

    public function cssCode(string $code): string
    {
        return $this->css->minify($code);
    }

    public function jsCode(string $code): string
    {
        return $this->js->minify($code);
    }

    /** Minify a local file into the cache dir; return the cached URL (or original on failure). */
    public function file(string $url, string $type): string
    {
        $path = Assets::toPath($url);
        if ($path === null) {
            return $url;
        }

        $hash      = md5($path . '|' . (string) @filemtime($path));
        $cacheFile = WPP_CACHE_DIR . $hash . '.' . $type;
        $cacheUrl  = WPP_CACHE_URL . $hash . '.' . $type;

        if (is_file($cacheFile)) {
            return $cacheUrl;
        }
        if (! is_dir(WPP_CACHE_DIR)) {
            wp_mkdir_p(WPP_CACHE_DIR);
        }

        $code = file_get_contents($path);
        if ($code === false) {
            return $url;
        }

        // CSS is absolutized because the cached copy lives in a different directory.
        $min = $type === 'css' ? $this->css->process($code, $url) : $this->js->minify($code);

        if (file_put_contents($cacheFile, $min, LOCK_EX) === false) {
            return $url;
        }

        return $cacheUrl;
    }

    /** Read + minify a CSS file for combination (absolutizing its url() refs). */
    public function cssForCombine(string $url): string
    {
        $code = $this->read($url);
        return Assets::toPath($url) !== null
            ? $this->css->process($code, $url)
            : $this->css->minify($code);
    }

    /** Read + minify a JS file for combination; terminate with ; for ASI safety. */
    public function jsForCombine(string $url): string
    {
        return $this->js->minify($this->read($url)) . ';';
    }

    private function read(string $url): string
    {
        $content = Assets::contents($url);
        if ($content !== null) {
            return $content;
        }
        $response = wp_remote_get($url, ['timeout' => 5]);
        if (is_wp_error($response)) {
            return '';
        }

        // A 404 still has a body, and baking an error page into a bundle is
        // indistinguishable from a successful fetch once it is cached.
        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return '';
        }

        return (string) wp_remote_retrieve_body($response);
    }
}
