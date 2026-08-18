<?php

declare(strict_types=1);

namespace WPP\Addons\Cloudflare;

/**
 * Thin Cloudflare API v4 client (zone settings + cache purge).
 */
final class CloudflareApi
{
    private const BASE = 'https://api.cloudflare.com/client/v4';

    /** Seconds the whole zone-settings push may spend, and the cap per call. */
    private const PUSH_BUDGET  = 10;
    private const PUSH_TIMEOUT = 5;

    public function __construct(
        private string $email,
        private string $key,
        private string $zone
    ) {
    }

    public function purgeAll(): array
    {
        return $this->request('POST', "/zones/{$this->zone}/purge_cache", ['purge_everything' => true]);
    }

    /** @param string[] $urls */
    public function purgeUrls(array $urls): array
    {
        $urls = array_values(array_filter(array_map('strval', $urls)));
        if ($urls === []) {
            return ['success' => false, 'errors' => ['No URLs provided']];
        }
        return $this->request('POST', "/zones/{$this->zone}/purge_cache", ['files' => $urls]);
    }

    /**
     * Push the plugin's CF settings to the zone.
     *
     * This runs inside the settings-save request, so the whole push is held to
     * one budget: a zone that stops answering costs the admin a report, not a
     * request that PHP kills after the options have already been written.
     *
     * @param array<string,mixed> $cfg
     * @return array<string,string> setting id => error message, empty when every push succeeded
     */
    public function applyAll(array $cfg, bool $withDevMode = true): array
    {
        $values = [];
        if ($withDevMode) {
            $values['development_mode'] = ! empty($cfg['dev_mode']) ? 'on' : 'off';
        }
        $values['cache_level']       = (string) ($cfg['cache_level'] ?? 'aggressive');
        $values['browser_cache_ttl'] = (int) ($cfg['browser_expire'] ?? 14400);
        $values['rocket_loader']     = ! empty($cfg['rocket_loader']) ? 'on' : 'off';
        $values['brotli']            = ! empty($cfg['brotli']) ? 'on' : 'off';

        $budget   = (float) apply_filters('wpp.cloudflare.push_budget', self::PUSH_BUDGET);
        $deadline = microtime(true) + $budget;

        $failed = [];
        foreach ($values as $id => $value) {
            $timeout = (int) min(self::PUSH_TIMEOUT, floor($deadline - microtime(true)));
            if ($timeout < 1) {
                $failed[$id] = __('Cloudflare did not answer in time, so this setting was not applied.', 'wpp');
                continue;
            }

            $result = $this->setting($id, $value, $timeout);
            if (empty($result['success'])) {
                $messages     = self::errorMessages((array) ($result['errors'] ?? []));
                $failed[$id]  = $messages[0] ?? __('Unknown Cloudflare error.', 'wpp');
            }
        }

        return $failed;
    }

    /**
     * Cloudflare reports errors as {code, message} objects; transport failures
     * are added by request() as plain strings.
     *
     * @param array<int,mixed> $errors
     * @return list<string>
     */
    public static function errorMessages(array $errors): array
    {
        $out = [];
        foreach ($errors as $error) {
            if (is_array($error)) {
                $message = trim((string) ($error['message'] ?? ''));
                $code    = isset($error['code']) ? ' (' . $error['code'] . ')' : '';
                $out[]   = $message === '' ? '' : $message . $code;
                continue;
            }
            $out[] = trim((string) $error);
        }

        return array_values(array_filter($out));
    }

    private function setting(string $id, mixed $value, int $timeout): array
    {
        return $this->request('PATCH', "/zones/{$this->zone}/settings/{$id}", ['value' => $value], $timeout);
    }

    private function request(string $method, string $path, ?array $body = null, int $timeout = 15): array
    {
        $args = [
            'method'  => $method,
            'timeout' => $timeout,
            'headers' => [
                'X-Auth-Email' => $this->email,
                'X-Auth-Key'   => $this->key,
                'Content-Type' => 'application/json',
            ],
        ];
        if ($body !== null) {
            $args['body'] = (string) wp_json_encode($body);
        }

        $response = wp_remote_request(self::BASE . $path, $args);
        if (is_wp_error($response)) {
            return ['success' => false, 'errors' => [$response->get_error_message()]];
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        return is_array($data) ? $data : ['success' => false, 'errors' => ['Invalid response']];
    }
}
