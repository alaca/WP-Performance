<?php
/**
 * WP Performance object-cache.php drop-in.
 *
 * Persistent object cache backed by Redis (phpredis). Falls back to a
 * per-request in-memory cache if Redis is unavailable, so the site keeps
 * working either way. Auto-installed/removed by the plugin.
 */

if (! defined('ABSPATH')) {
    return;
}

function wp_cache_init()
{
    $GLOBALS['wp_object_cache'] = new WPP_Object_Cache();
}

function wp_cache_add($key, $data, $group = '', $expire = 0)
{
    return $GLOBALS['wp_object_cache']->add($key, $data, $group, (int) $expire);
}

function wp_cache_add_multiple(array $data, $group = '', $expire = 0)
{
    $result = [];
    foreach ($data as $key => $value) {
        $result[$key] = wp_cache_add($key, $value, $group, $expire);
    }
    return $result;
}

function wp_cache_replace($key, $data, $group = '', $expire = 0)
{
    return $GLOBALS['wp_object_cache']->replace($key, $data, $group, (int) $expire);
}

function wp_cache_set($key, $data, $group = '', $expire = 0)
{
    return $GLOBALS['wp_object_cache']->set($key, $data, $group, (int) $expire);
}

function wp_cache_set_multiple(array $data, $group = '', $expire = 0)
{
    $result = [];
    foreach ($data as $key => $value) {
        $result[$key] = wp_cache_set($key, $value, $group, $expire);
    }
    return $result;
}

function wp_cache_get($key, $group = '', $force = false, &$found = null)
{
    return $GLOBALS['wp_object_cache']->get($key, $group, $force, $found);
}

function wp_cache_get_multiple($keys, $group = '', $force = false)
{
    return $GLOBALS['wp_object_cache']->get_multiple($keys, $group, $force);
}

function wp_cache_delete($key, $group = '')
{
    return $GLOBALS['wp_object_cache']->delete($key, $group);
}

function wp_cache_delete_multiple(array $keys, $group = '')
{
    $result = [];
    foreach ($keys as $key) {
        $result[$key] = wp_cache_delete($key, $group);
    }
    return $result;
}

function wp_cache_incr($key, $offset = 1, $group = '')
{
    return $GLOBALS['wp_object_cache']->incr($key, (int) $offset, $group);
}

function wp_cache_decr($key, $offset = 1, $group = '')
{
    return $GLOBALS['wp_object_cache']->decr($key, (int) $offset, $group);
}

function wp_cache_flush()
{
    return $GLOBALS['wp_object_cache']->flush();
}

function wp_cache_flush_runtime()
{
    return $GLOBALS['wp_object_cache']->flush_runtime();
}

function wp_cache_supports($feature)
{
    return in_array($feature, ['get_multiple', 'set_multiple', 'add_multiple', 'delete_multiple', 'flush_runtime'], true);
}

function wp_cache_close()
{
    return true;
}

function wp_cache_add_global_groups($groups)
{
    $GLOBALS['wp_object_cache']->add_global_groups($groups);
}

function wp_cache_add_non_persistent_groups($groups)
{
    $GLOBALS['wp_object_cache']->add_non_persistent_groups($groups);
}

function wp_cache_switch_to_blog($blog_id)
{
    $GLOBALS['wp_object_cache']->switch_to_blog((int) $blog_id);
}

function wp_cache_reset()
{
}

class WPP_Object_Cache
{
    /** @var array<string,mixed> */
    private array $cache = [];
    private ?\Redis $redis = null;
    private bool $connected = false;
    /** @var array<string,bool> */
    private array $globalGroups = [];
    /** @var array<string,bool> */
    private array $nonPersistent = ['counts' => true, 'plugins' => true];
    private int $blogPrefix = 1;
    private string $salt;

    public int $cache_hits = 0;
    public int $cache_misses = 0;

    public function __construct()
    {
        $this->salt = defined('WP_CACHE_KEY_SALT') ? (string) WP_CACHE_KEY_SALT : '';

        // Without a site-specific default, two installs sharing one Redis would
        // collide on identical keys and read each other's options and users.
        if ($this->salt === '') {
            $this->salt = substr(md5(
                (defined('DB_NAME') ? (string) DB_NAME : '')
                . '|' . (string) ($GLOBALS['table_prefix'] ?? '')
                . '|' . (defined('ABSPATH') ? (string) ABSPATH : '')
            ), 0, 12);
        }
        if (function_exists('get_current_blog_id')) {
            $this->blogPrefix = (int) get_current_blog_id();
        }

        if (class_exists('Redis')) {
            try {
                $this->redis = new \Redis();
                $host = defined('WP_REDIS_HOST') ? WP_REDIS_HOST : '127.0.0.1';
                $port = defined('WP_REDIS_PORT') ? (int) WP_REDIS_PORT : 6379;
                $this->connected = (bool) @$this->redis->connect($host, $port, 1.0);
                if ($this->connected && defined('WP_REDIS_PASSWORD') && WP_REDIS_PASSWORD) {
                    @$this->redis->auth(WP_REDIS_PASSWORD);
                }
                if ($this->connected && defined('WP_REDIS_DATABASE')) {
                    @$this->redis->select((int) WP_REDIS_DATABASE);
                }
            } catch (\Throwable $e) {
                $this->connected = false;
            }
        }
    }

    public function add_global_groups($groups): void
    {
        foreach ((array) $groups as $group) {
            $this->globalGroups[$group] = true;
        }
    }

    public function add_non_persistent_groups($groups): void
    {
        foreach ((array) $groups as $group) {
            $this->nonPersistent[$group] = true;
        }
    }

    public function switch_to_blog($blogId): void
    {
        $this->blogPrefix = (int) $blogId;
    }

    public function get($key, $group = 'default', $force = false, &$found = null)
    {
        $group = $group ?: 'default';
        $id    = $this->id($key, $group);

        if (! $force && array_key_exists($id, $this->cache)) {
            $found = true;
            $this->cache_hits++;
            return is_object($this->cache[$id]) ? clone $this->cache[$id] : $this->cache[$id];
        }

        if ($this->persistent($group)) {
            $value = $this->call(static fn ($redis) => $redis->get($id), false);
            if ($value !== false) {
                $value           = maybe_unserialize($value);
                $this->cache[$id] = $value;
                $found           = true;
                $this->cache_hits++;
                return is_object($value) ? clone $value : $value;
            }
        }

        $found = false;
        $this->cache_misses++;
        return false;
    }

    public function get_multiple($keys, $group = 'default', $force = false): array
    {
        $result = [];
        foreach ((array) $keys as $key) {
            $found        = null;
            $result[$key] = $this->get($key, $group, $force, $found);
        }
        return $result;
    }

    public function set($key, $data, $group = 'default', $expire = 0): bool
    {
        $group = $group ?: 'default';
        $id    = $this->id($key, $group);

        $this->cache[$id] = is_object($data) ? clone $data : $data;

        if ($this->persistent($group)) {
            $serialized = maybe_serialize($data);
            $this->call(static fn ($redis) => $expire > 0
                ? $redis->setex($id, (int) $expire, $serialized)
                : $redis->set($id, $serialized));
        }
        return true;
    }

    public function add($key, $data, $group = 'default', $expire = 0): bool
    {
        $group = $group ?: 'default';
        $id    = $this->id($key, $group);
        if (array_key_exists($id, $this->cache)) {
            return false;
        }
        if ($this->persistent($group) && $this->call(static fn ($redis) => $redis->exists($id), false)) {
            return false;
        }
        return $this->set($key, $data, $group, $expire);
    }

    public function replace($key, $data, $group = 'default', $expire = 0): bool
    {
        $found = null;
        $this->get($key, $group, false, $found);
        return $found ? $this->set($key, $data, $group, $expire) : false;
    }

    public function delete($key, $group = 'default'): bool
    {
        $group = $group ?: 'default';
        $id    = $this->id($key, $group);
        unset($this->cache[$id]);
        if ($this->persistent($group)) {
            $this->call(static fn ($redis) => $redis->del($id));
        }
        return true;
    }

    public function incr($key, $offset = 1, $group = 'default')
    {
        $found = null;
        $value = $this->get($key, $group, false, $found);
        if (! $found) {
            return false;
        }
        $value = max(0, (int) $value + (int) $offset);
        $this->set($key, $value, $group);
        return $value;
    }

    public function decr($key, $offset = 1, $group = 'default')
    {
        return $this->incr($key, -(int) $offset, $group);
    }

    /**
     * Deletes only this install's keys. flushDb() would empty the whole Redis
     * database, which is shared by default with anything else on the server.
     */
    public function flush(): bool
    {
        $this->cache = [];
        if (! $this->connected) {
            return true;
        }

        $pattern = "wpp:{$this->salt}:*";

        return $this->call(static function ($redis) use ($pattern) {
            $redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
            $cursor = null;
            while (($keys = $redis->scan($cursor, $pattern, 500)) !== false) {
                if ($keys === []) {
                    continue;
                }
                method_exists($redis, 'unlink') ? $redis->unlink($keys) : $redis->del($keys);
            }
            return true;
        }) === true;
    }

    public function flush_runtime(): bool
    {
        $this->cache = [];
        return true;
    }

    private function persistent(string $group): bool
    {
        return $this->connected && ! isset($this->nonPersistent[$group]);
    }

    /**
     * Run a Redis command, degrading to the in-memory cache on any failure.
     *
     * phpredis throws RedisException not only when the connection drops but for
     * server replies such as NOAUTH, LOADING, MISCONF and OOM. Uncaught, that is
     * a fatal error on every front-end and admin request, which cannot then be
     * switched off from the admin. Dropping the connection keeps the request
     * alive on the runtime array cache instead.
     */
    private function call(callable $command, mixed $fallback = false): mixed
    {
        if (! $this->connected) {
            return $fallback;
        }

        try {
            return $command($this->redis);
        } catch (\Throwable $e) {
            $this->connected = false;
            return $fallback;
        }
    }

    private function id($key, string $group): string
    {
        $prefix = isset($this->globalGroups[$group]) ? 'global' : (string) $this->blogPrefix;
        return "wpp:{$this->salt}:{$prefix}:{$group}:{$key}";
    }
}
