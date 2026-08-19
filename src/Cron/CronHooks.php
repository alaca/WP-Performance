<?php

declare(strict_types=1);

namespace WPP\Cron;

use WPP\Database\DatabaseService;
use WPP\Foundation\Hooks\HookProvider;
use WPP\Settings\SettingsService;

/**
 * Schedules and runs the sitemap cache preload and the periodic database cleanup,
 * keeping the schedules in sync with the relevant settings.
 */
final class CronHooks extends HookProvider
{
    public const PRELOAD = 'wpp_preload';
    public const DB      = 'wpp_db_cleanup';

    public function __construct(
        private SettingsService $settings,
        private DatabaseService $database,
        private Preloader $preloader
    ) {
    }

    protected function filters(): array
    {
        return ['cron_schedules' => 'addSchedules'];
    }

    protected function actions(): array
    {
        return [
            'init'                 => 'sync',
            self::PRELOAD          => 'runPreload',
            self::DB               => 'runDbCleanup',
            'wpp.settings.updated' => ['onSettingsUpdated', 10, 1],
        ];
    }

    /** @param array<string,array{interval:int,display:string}> $schedules */
    public function addSchedules(array $schedules): array
    {
        $schedules['wpp_weekly']  = ['interval' => WEEK_IN_SECONDS, 'display' => __('Once weekly', 'wpp')];
        $schedules['wpp_monthly'] = ['interval' => MONTH_IN_SECONDS, 'display' => __('Once monthly', 'wpp')];
        return $schedules;
    }

    public function sync(): void
    {
        $sitemaps = array_filter((array) $this->settings->get('cache')['sitemaps']);
        if ($sitemaps !== [] && ! wp_next_scheduled(self::PRELOAD)) {
            wp_schedule_event(time() + 300, 'hourly', self::PRELOAD);
        } elseif ($sitemaps === [] && wp_next_scheduled(self::PRELOAD)) {
            wp_clear_scheduled_hook(self::PRELOAD);
        }

        $recurrence = $this->recurrence((string) ($this->settings->get('database')['frequency'] ?? 'none'));
        $current    = wp_get_schedule(self::DB);

        if ($recurrence !== null && $current !== $recurrence) {
            wp_clear_scheduled_hook(self::DB);
            wp_schedule_event(time() + HOUR_IN_SECONDS, $recurrence, self::DB);
            $this->updateNextRun();
        } elseif ($recurrence === null && $current !== false) {
            wp_clear_scheduled_hook(self::DB);
            delete_option('wpp_db_cleanup_next');
        }
    }

    public function onSettingsUpdated(string $group): void
    {
        if ($group === 'cache' || $group === 'database') {
            $this->sync();
        }
    }

    public function runPreload(): void
    {
        $this->preloader->run();
    }

    public function runDbCleanup(): void
    {
        $db = $this->settings->get('database');
        $map = [
            'cleanup_trash'      => 'trash',
            'cleanup_spam'       => 'spam',
            'cleanup_revisions'  => 'revisions',
            'cleanup_transients' => 'transients',
            'cleanup_autodrafts' => 'autodrafts',
            'cleanup_cron'       => 'cron',
        ];
        foreach ($map as $flag => $type) {
            if (! empty($db[$flag])) {
                $this->database->clear($type);
            }
        }
        $this->updateNextRun();
    }

    private function recurrence(string $frequency): ?string
    {
        return match ($frequency) {
            'daily'   => 'daily',
            'weekly'  => 'wpp_weekly',
            'monthly' => 'wpp_monthly',
            default   => null,
        };
    }

    private function updateNextRun(): void
    {
        $next = wp_next_scheduled(self::DB);
        if ($next) {
            update_option('wpp_db_cleanup_next', $next, false);
        }
    }
}
