<?php

namespace MODXDocs\Services;

class CacheService
{
    private $cacheRoot;
    private $enabled;

    public function __construct()
    {
        $this->cacheRoot = rtrim(getenv('CACHE_DIRECTORY'), '/') . '/';
        $this->enabled = getenv('CACHE_ENABLED') === "1";
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
        if (!file_exists($file)) {
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

    public function set($key, $value, $expiration = null, $hash = null)
    {
        if (!$this->enabled) {
            return false;
        }

        $file = $this->keyToFile($key);
        $data = [
            'generated' => date('Y-m-d H:i:s'),
            'contents' => $value,
            'hash' => $hash,
            'expiration' => $expiration,
        ];

        $this->ensurePathsExist(dirname($file));

        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return true;
    }

    private function keyToFile($key)
    {
        return $this->cacheRoot . strtolower($key) . '.json';
    }

    private function ensurePathsExist(string $path): bool
    {
        if (file_exists($path) && is_dir($path)) {
            return true;
        }

        if (!mkdir($path, 0777, true) && !is_dir($path)) {
            return false;
        }

        return true;
    }
}
