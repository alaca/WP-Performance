<?php

declare(strict_types=1);

/**
 * Merge the per-source script-translation JSON files that `wp i18n make-json`
 * produces into a single handle-named file.
 *
 * The admin bundle (build/admin/app/index.js) contains the strings from every
 * source file, so all of them must live in one JSON keyed to the script handle.
 * WordPress loads `{domain}-{locale}-{handle}.json` before falling back to the
 * md5-of-path scheme (see load_script_textdomain()), so that is the name we emit.
 *
 * Usage: php bin/merge-script-translations.php <locale> [domain] [handle]
 */

$root   = dirname(__DIR__);
$langs  = $root . '/languages';
$locale = $argv[1] ?? '';
$domain = $argv[2] ?? 'wpp';
$handle = $argv[3] ?? 'wpp-admin';

if ($locale === '') {
    fwrite(STDERR, "Usage: php bin/merge-script-translations.php <locale> [domain] [handle]\n");
    exit(1);
}

// The md5-named fragments for this locale (not the handle file we are creating).
$pattern   = sprintf('%s/%s-%s-*.json', $langs, $domain, $locale);
$handleOut = sprintf('%s/%s-%s-%s.json', $langs, $domain, $locale, $handle);

$messages = [];
$meta     = null;
$found    = 0;

foreach (glob($pattern) ?: [] as $file) {
    if ($file === $handleOut) {
        continue;
    }
    if (! preg_match('/-[0-9a-f]{32}\.json$/', $file)) {
        continue; // only consume md5 fragments
    }

    $data = json_decode((string) file_get_contents($file), true);
    $block = $data['locale_data']['messages'] ?? null;
    if (! is_array($block)) {
        continue;
    }

    if ($meta === null && isset($block[''])) {
        $meta = $block[''];
    }
    foreach ($block as $msgid => $translation) {
        if ($msgid === '') {
            continue;
        }
        // Keep the first non-empty translation we see for a msgid.
        if (! isset($messages[$msgid]) || ($messages[$msgid][0] ?? '') === '') {
            $messages[$msgid] = $translation;
        }
    }

    $found++;
    unlink($file);
}

if ($found === 0) {
    fwrite(STDERR, "No JSON fragments found for locale '{$locale}'.\n");
    exit(1);
}

ksort($messages);
$out = [
    'translation-revision-date' => gmdate('Y-m-d H:iO'),
    'generator'                 => 'wp-performance/merge-script-translations',
    'domain'                    => 'messages',
    'locale_data'               => [
        'messages' => ['' => $meta ?? ['domain' => 'messages', 'lang' => $locale]] + $messages,
    ],
];

file_put_contents(
    $handleOut,
    json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
);

printf("Wrote %s (%d strings, merged from %d files).\n", basename($handleOut), count($messages), $found);
