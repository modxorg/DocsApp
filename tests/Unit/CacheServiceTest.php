<?php

declare(strict_types=1);

namespace Tests\Unit;

use MODXDocs\Services\CacheService;
use Tests\BaseTestCase;

class CacheServiceTest extends BaseTestCase
{
    private string $cacheDir;
    private ?string $previousCacheDir;
    private mixed $previousCacheEnabled;

    protected function set_up(): void
    {
        parent::set_up();

        $this->cacheDir = sys_get_temp_dir() . '/modxdocs-cache-test-' . uniqid('', true);
        mkdir($this->cacheDir, 0777, true);

        $this->previousCacheDir = $_ENV['CACHE_DIRECTORY'] ?? null;
        $this->previousCacheEnabled = $_ENV['CACHE_ENABLED'] ?? null;
        $_ENV['CACHE_DIRECTORY'] = $this->cacheDir;
        $_ENV['CACHE_ENABLED'] = '1';
    }

    protected function tear_down(): void
    {
        if ($this->previousCacheDir === null) {
            unset($_ENV['CACHE_DIRECTORY']);
        } else {
            $_ENV['CACHE_DIRECTORY'] = $this->previousCacheDir;
        }

        if ($this->previousCacheEnabled === null) {
            unset($_ENV['CACHE_ENABLED']);
        } else {
            $_ENV['CACHE_ENABLED'] = $this->previousCacheEnabled;
        }

        $this->removeDirectory($this->cacheDir);

        parent::tear_down();
    }

    public function testSetAndGetLegitimateKey(): void
    {
        $cache = new CacheService();

        $this->assertTrue($cache->set('rendered/2.x/en/getting-started', 'body'));
        $this->assertSame('body', $cache->get('rendered/2.x/en/getting-started'));
        $this->assertFileExists($this->cacheDir . '/rendered/2.x/en/getting-started.json');
    }

    public function testSetRejectsTraversalKey(): void
    {
        $cache = new CacheService();
        $outsideTarget = dirname($this->cacheDir) . '/outside.json';

        $this->assertFalse($cache->set('rendered/2.x/en/../../../outside', 'pwned'));
        $this->assertFalse($cache->get('rendered/2.x/en/../../../outside'));
        $this->assertFileDoesNotExist($outsideTarget);
        $this->assertFileDoesNotExist($this->cacheDir . '/outside.json');
    }

    public function testSetRejectsBackslashTraversalKey(): void
    {
        $cache = new CacheService();

        $this->assertFalse($cache->set('rendered\\2.x\\en\\..\\..\\..\\outside', 'pwned'));
        $this->assertFileDoesNotExist(dirname($this->cacheDir) . '/outside.json');
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
