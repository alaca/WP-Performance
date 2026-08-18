<?php

declare(strict_types=1);

namespace WPP\Database;

/**
 * Database cleanup: counts and deletions for revisions, auto-drafts, trashed
 * posts, spam/trashed comments, transients, and orphaned cron events.
 */
final class DatabaseService
{
    /** @return array<string, int> */
    public function counts(): array
    {
        return [
            'trash'      => $this->trashCount(),
            'spam'       => $this->spamCount(),
            'revisions'  => $this->revisionsCount(),
            'transients' => $this->transientsCount(),
            'autodrafts' => $this->autoDraftsCount(),
            'cron'       => $this->cronCount(),
        ];
    }

    public function clear(string $type): void
    {
        match ($type) {
            'trash'      => $this->clearTrash(),
            'spam'       => $this->clearSpam(),
            'revisions'  => $this->clearRevisions(),
            'transients' => $this->clearTransients(),
            'autodrafts' => $this->clearAutoDrafts(),
            'cron'       => $this->clearCron(),
            'all'        => $this->clearAll(),
            default      => null,
        };

        do_action('wpp.db.cleaned', $type);
    }

    public function clearAll(): void
    {
        $this->clearTrash();
        $this->clearSpam();
        $this->clearRevisions();
        $this->clearTransients();
        $this->clearAutoDrafts();
        $this->clearCron();
    }

    private function revisionsCount(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'");
    }

    private function clearRevisions(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'");
    }

    private function autoDraftsCount(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'");
    }

    private function clearAutoDrafts(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'");
    }

    private function trashCount(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'");
    }

    private function clearTrash(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_status = 'trash'");
    }

    private function spamCount(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved IN ('spam', 'trash')");
    }

    private function clearSpam(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved IN ('spam', 'trash')");
    }

    /** Value rows only: an expiring transient also stores a _timeout_ row. */
    private function transientsCount(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options}
                 WHERE (option_name LIKE %s OR option_name LIKE %s)
                   AND option_name NOT LIKE %s
                   AND option_name NOT LIKE %s",
                $wpdb->esc_like('_transient_') . '%',
                $wpdb->esc_like('_site_transient_') . '%',
                $wpdb->esc_like('_transient_timeout_') . '%',
                $wpdb->esc_like('_site_transient_timeout_') . '%'
            )
        );
    }

    /**
     * Expired transients only. Deleting every match would drop live caches and
     * non-expiring transients that other plugins rely on.
     */
    private function clearTransients(): void
    {
        global $wpdb;
        $expired = (array) $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options}
                 WHERE (option_name LIKE %s OR option_name LIKE %s)
                   AND option_value < %d",
                $wpdb->esc_like('_transient_timeout_') . '%',
                $wpdb->esc_like('_site_transient_timeout_') . '%',
                time()
            )
        );

        $names = [];
        foreach ($expired as $timeout) {
            $timeout = (string) $timeout;
            $names[] = $timeout;
            $names[] = str_starts_with($timeout, '_site_transient_timeout_')
                ? '_site_transient_' . substr($timeout, 24)
                : '_transient_' . substr($timeout, 19);
        }

        foreach (array_chunk($names, 200) as $chunk) {
            $placeholders = implode(', ', array_fill(0, count($chunk), '%s'));
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name IN ({$placeholders})",
                    ...$chunk
                )
            );
        }
    }

    private function cronCount(): int
    {
        $count = 0;
        foreach ((array) _get_cron_array() as $hooks) {
            if (! is_array($hooks)) {
                continue;
            }
            foreach ($hooks as $hook => $events) {
                if (! has_action((string) $hook)) {
                    $count += is_array($events) ? count($events) : 0;
                }
            }
        }
        return $count;
    }

    private function clearCron(): void
    {
        foreach ((array) _get_cron_array() as $timestamp => $hooks) {
            if (! is_array($hooks)) {
                continue;
            }
            foreach ($hooks as $hook => $events) {
                if (has_action((string) $hook) || ! is_array($events)) {
                    continue;
                }
                foreach ($events as $event) {
                    wp_unschedule_event($timestamp, (string) $hook, $event['args'] ?? []);
                }
            }
        }
    }
}
