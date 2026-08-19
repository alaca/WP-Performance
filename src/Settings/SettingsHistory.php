<?php

declare(strict_types=1);

namespace WPP\Settings;

/**
 * Keeps the last few full-settings snapshots so a save can be rolled back.
 *
 * On the first settings change in a request the complete prior state is captured
 * (one snapshot per request, however many groups change), newest first, capped.
 */
final class SettingsHistory
{
    private const OPTION = 'wpp_history';
    private const MAX    = 5;

    private bool $captured = false;

    public function __construct(private SettingsService $settings)
    {
    }

    public function register(): void
    {
        add_action('wpp.settings.updating', [$this, 'onUpdating'], 10, 1);
    }

    public function onUpdating(string $group): void
    {
        if ($this->captured) {
            return;
        }
        $this->captured = true;
        $this->push($group);
    }

    /** @return list<array{time:int, trigger:string, groups:array<string,mixed>}> newest first */
    public function all(): array
    {
        $list = get_option(self::OPTION, []);
        return is_array($list) ? array_values($list) : [];
    }

    /** Lightweight list for the REST/UI (the heavy group payload is stripped). */
    public function entries(): array
    {
        $out = [];
        foreach ($this->all() as $i => $snap) {
            $out[] = [
                'index'   => $i,
                'time'    => (int) ($snap['time'] ?? 0),
                'trigger' => (string) ($snap['trigger'] ?? ''),
            ];
        }
        return $out;
    }

    public function restore(int $index): bool
    {
        $list = $this->all();
        if (! isset($list[$index]['groups']) || ! is_array($list[$index]['groups'])) {
            return false;
        }

        foreach ($list[$index]['groups'] as $group => $values) {
            // replace(), not update(): a snapshot is the whole state, so a rule
            // added after it has to disappear rather than merge back in.
            if (is_string($group) && is_array($values) && $this->settings->knows($group)) {
                $this->settings->replace($group, $values);
            }
        }

        return true;
    }

    public function clear(): void
    {
        delete_option(self::OPTION);
    }

    private function push(string $trigger): void
    {
        $snapshot = [
            'time'    => time(),
            'trigger' => $trigger,
            'groups'  => $this->settings->all(),
        ];

        $list = $this->all();

        // Skip if the prior state is identical to the newest snapshot already kept.
        if (isset($list[0]['groups']) && $list[0]['groups'] == $snapshot['groups']) {
            return;
        }

        array_unshift($list, $snapshot);
        update_option(self::OPTION, array_slice($list, 0, self::MAX), false);
    }
}
