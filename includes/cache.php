<?php
declare(strict_types=1);

class Cache
{
    private const DEFAULT_TTL = 3600;

    private static string $cacheDir;

    private static function init(): void
    {
        if (isset(self::$cacheDir)) {
            return;
        }

        $baseDir = dirname(__DIR__) . '/storage/cache';
        if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
            throw new RuntimeException('Das Cache-Verzeichnis konnte nicht erstellt werden.');
        }

        self::$cacheDir = $baseDir;
    }

    private static function path(string $key): string
    {
        self::init();
        $sanitized = preg_replace('/[^A-Za-z0-9_\-]/', '_', $key) ?? 'cache';
        return self::$cacheDir . '/' . $sanitized . '_' . sha1($key) . '.cache';
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $path = self::path($key);
        if (!is_file($path)) {
            return $default;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return $default;
        }

        $payload = json_decode($contents, true);
        if (!is_array($payload) || !array_key_exists('expires_at', $payload) || !array_key_exists('data', $payload)) {
            @unlink($path);
            return $default;
        }

        $expiresAt = (int) $payload['expires_at'];
        if ($expiresAt !== 0 && $expiresAt < time()) {
            @unlink($path);
            return $default;
        }

        return $payload['data'];
    }

    public static function set(string $key, mixed $value, int $ttl = self::DEFAULT_TTL): void
    {
        $path = self::path($key);
        $expiresAt = $ttl > 0 ? time() + $ttl : 0;
        $payload = json_encode([
            'expires_at' => $expiresAt,
            'data' => $value,
        ]);

        if ($payload === false) {
            throw new RuntimeException('Cache-Wert konnte nicht serialisiert werden.');
        }

        file_put_contents($path, $payload, LOCK_EX);
    }

    public static function remember(string $key, callable $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        $sentinel = new stdClass();
        $cached = self::get($key, $sentinel);
        if ($cached !== $sentinel) {
            return $cached;
        }

        $value = $callback();
        self::set($key, $value, $ttl);

        return $value;
    }

    public static function delete(string $key): void
    {
        $path = self::path($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function clear(): void
    {
        self::init();
        foreach (glob(self::$cacheDir . '/*.cache') ?: [] as $file) {
            @unlink($file);
        }
    }
}
