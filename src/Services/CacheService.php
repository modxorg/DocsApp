<?php

namespace MODXDocs\Services;

class CacheService
{
    private string $cacheRoot;
    private bool $enabled;

    public function __construct()
    {
        $this->cacheRoot = rtrim($_ENV['CACHE_DIRECTORY'], '/') . '/';
        $this->enabled = (bool)$_ENV['CACHE_ENABLED'];
    }

    public static function getInstance(): CacheService
    {
        return new self();
    }

    public function get($key, $hash = null)
    {
        if (!$this->enabled) {
            return false;
        }

        $file = $this->keyToFile($key);
        if ($file === null || !file_exists($file)) {
            return false;
        }

        $data = file_get_contents($file);
        $data = json_decode($data, true);
        if (is_array($data)) {
            if ($hash !== null && $data['hash'] !== $hash) {
                return false;
            }

            if (is_numeric($data['expiration']) && time() > $data['expiration']) {
                return false;
            }

            return $data['contents'];
        }

        return false;
    }

    public function set($key, $value, $expiration = null, $hash = null): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $file = $this->keyToFile($key);
        if ($file === null) {
            return false;
        }

        $data = [
            'generated' => date('Y-m-d H:i:s'),
            'contents' => $value,
            'hash' => $hash,
            'expiration' => $expiration,
        ];

        $directory = dirname($file);
        if (!$this->ensurePathsExist($directory) || !$this->isInsideCacheRoot($directory)) {
            return false;
        }

        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return true;
    }

    private function keyToFile($key): ?string
    {
        $normalized = $this->normalizeKey((string) $key);
        if ($normalized === null) {
            return null;
        }

        return $this->cacheRoot . $normalized . '.json';
    }

    private function normalizeKey(string $key): ?string
    {
        $key = strtolower(str_replace('\\', '/', $key));
        $key = str_replace("\0", '', $key);
        $key = trim($key, '/');

        if ($key === '') {
            return null;
        }

        $parts = [];
        foreach (explode('/', $key) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                return null;
            }
            $parts[] = $part;
        }

        if ($parts === []) {
            return null;
        }

        return implode('/', $parts);
    }

    private function isInsideCacheRoot(string $path): bool
    {
        $root = realpath(rtrim($this->cacheRoot, '/'));
        if ($root === false) {
            return false;
        }

        $resolved = realpath($path);
        if ($resolved === false) {
            return false;
        }

        return $resolved === $root
            || str_starts_with($resolved, $root . DIRECTORY_SEPARATOR);
    }

    private function ensurePathsExist(string $path): bool
    {
        if (file_exists($path) && is_dir($path)) {
            return true;
        }

        if (!@mkdir($path, 0777, true) && !is_dir($path)) {
            return false;
        }

        return true;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
