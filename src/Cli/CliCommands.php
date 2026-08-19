<?php

declare(strict_types=1);

namespace WPP\Cli;

use WPP\Cache\CacheStore;
use WPP\Cache\FragmentStore;
use WPP\Cache\RuntimeSettings;
use WPP\Database\DatabaseService;
use WPP\Settings\SettingsService;
use WP_CLI;

/**
 * WP-CLI commands: wp wpp flush | flush-blocks | enable | disable | cleanup [<type>]
 */
final class CliCommands
{
    /** Clear the page cache. Pass --network to flush every blog. */
    public function flush(array $args, array $assoc): void
    {
        $keep    = ! empty((new SettingsService())->get('cache')['keep_assets']);
        $network = ! empty($assoc['network']);
        (new CacheStore())->clear($keep, $network);
        WP_CLI::success($network
            ? 'WP Performance cache cleared for the whole network.'
            : 'WP Performance cache cleared.');
    }

    /** Flush the block (fragment) cache. */
    public function flushBlocks(array $args, array $assoc): void
    {
        (new FragmentStore())->flush();
        WP_CLI::success('WP Performance block cache flushed.');
    }

    /** Enable WP Performance. */
    public function enable(array $args, array $assoc): void
    {
        update_option('wpp_disabled', false);
        (new RuntimeSettings(new SettingsService()))->write();
        WP_CLI::success('WP Performance enabled.');
    }

    /** Temporarily disable WP Performance. */
    public function disable(array $args, array $assoc): void
    {
        update_option('wpp_disabled', true);
        (new RuntimeSettings(new SettingsService()))->write();
        WP_CLI::success('WP Performance disabled.');
    }

    /**
     * Run database cleanup.
     *
     * ## OPTIONS
     *
     * [<type>]
     * : One of all, trash, spam, revisions, transients, autodrafts, cron. Defaults to all.
     */
    public function cleanup(array $args, array $assoc): void
    {
        $type  = $args[0] ?? 'all';
        $valid = ['all', 'trash', 'spam', 'revisions', 'transients', 'autodrafts', 'cron'];

        if (! in_array($type, $valid, true)) {
            WP_CLI::error('Invalid type. Use one of: ' . implode(', ', $valid));
            return;
        }

        (new DatabaseService())->clear($type);
        WP_CLI::success("Database cleanup ({$type}) complete.");
    }
}
