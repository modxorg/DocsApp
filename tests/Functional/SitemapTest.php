<?php

declare(strict_types=1);

namespace Tests\Functional;

use MODXDocs\Services\SitemapService;
use MODXDocs\Services\VersionsService;
use Tests\BaseTestCase;

class SitemapTest extends BaseTestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/modxdocs-sitemap-' . uniqid('', true);
        mkdir($this->tempDir . '/sitemaps', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
        parent::tearDown();
    }

    public function testGenerateWritesIndexAndSplitSitemaps(): void
    {
        $service = new SitemapService(
            'https://docs.modx.org/',
            $this->tempDir,
            $_ENV['DOCS_DIRECTORY']
        );

        $result = $service->generate();

        $this->assertGreaterThan(0, $result['urls']);
        $this->assertFileExists($this->tempDir . '/sitemap.xml');

        $index = simplexml_load_file($this->tempDir . '/sitemap.xml');
        $this->assertNotFalse($index);
        $this->assertSame('sitemapindex', $index->getName());
        $this->assertGreaterThan(0, count($index->sitemap));

        $childLoc = (string) $index->sitemap[0]->loc;
        $this->assertStringStartsWith('https://docs.modx.org/sitemaps/', $childLoc);
        $this->assertStringEndsWith('.xml', $childLoc);

        $childFile = $this->tempDir . '/sitemaps/' . basename($childLoc);
        $this->assertFileExists($childFile);

        $urlset = simplexml_load_file($childFile);
        $this->assertNotFalse($urlset);
        $this->assertSame('urlset', $urlset->getName());
        $this->assertGreaterThan(0, count($urlset->url));

        $firstLoc = (string) $urlset->url[0]->loc;
        $this->assertStringStartsWith('https://docs.modx.org/', $firstLoc);

        // Current branch must be published under /current/, never the raw branch name
        $currentBranch = VersionsService::getCurrentVersionBranch();
        $currentEn = $this->tempDir . '/sitemaps/current-en.xml';
        $this->assertFileExists($currentEn);
        $currentXml = file_get_contents($currentEn);
        $this->assertIsString($currentXml);
        $this->assertStringNotContainsString('/' . $currentBranch . '/', $currentXml);

        // Index lastmod should be the newest lastmod among that child sitemap's URLs
        $newestInChild = null;
        foreach ($urlset->url as $url) {
            if (!isset($url->lastmod)) {
                continue;
            }
            $lastmod = (string) $url->lastmod;
            if ($newestInChild === null || $lastmod > $newestInChild) {
                $newestInChild = $lastmod;
            }
        }
        $this->assertNotNull($newestInChild);
        $this->assertSame($newestInChild, (string) $index->sitemap[0]->lastmod);
    }

    public function testCanonicalUsesCurrentForCurrentBranch(): void
    {
        $service = new SitemapService(
            'https://docs.modx.org/',
            $this->tempDir,
            $_ENV['DOCS_DIRECTORY']
        );
        $service->generate();

        $currentEn = $this->tempDir . '/sitemaps/current-en.xml';
        $this->assertFileExists($currentEn);

        $xml = file_get_contents($currentEn);
        $this->assertIsString($xml);
        $this->assertStringContainsString(
            '<loc>https://docs.modx.org/current/en/index</loc>',
            $xml
        );
        $this->assertStringNotContainsString('/3.x/', $xml);

        // Legacy branch alias file must not be generated
        $this->assertFileDoesNotExist($this->tempDir . '/sitemaps/3.x-en.xml');
        $this->assertFileExists($this->tempDir . '/sitemaps/2.x-en.xml');
    }

    public function testHreflangAlternatesOnTranslatedPages(): void
    {
        $service = new SitemapService(
            'https://docs.modx.org/',
            $this->tempDir,
            $_ENV['DOCS_DIRECTORY']
        );
        $service->generate();

        $currentEn = $this->tempDir . '/sitemaps/current-en.xml';
        $this->assertFileExists($currentEn);

        $xml = file_get_contents($currentEn);
        $this->assertIsString($xml);
        $this->assertStringContainsString('xmlns:xhtml="http://www.w3.org/1999/xhtml"', $xml);

        // Language home: self + other languages + x-default → English
        $this->assertMatchesRegularExpression(
            '#<loc>https://docs\.modx\.org/current/en/index</loc>\s*'
            . '<lastmod>[^<]+</lastmod>\s*'
            . '<xhtml:link rel="alternate" hreflang="en" href="https://docs\.modx\.org/current/en/index"/>\s*'
            . '<xhtml:link rel="alternate" hreflang="ru" href="https://docs\.modx\.org/current/ru/index"/>\s*'
            . '<xhtml:link rel="alternate" hreflang="nl" href="https://docs\.modx\.org/current/nl/index"/>\s*'
            . '<xhtml:link rel="alternate" hreflang="es" href="https://docs\.modx\.org/current/es/index"/>\s*'
            . '<xhtml:link rel="alternate" hreflang="x-default" href="https://docs\.modx\.org/current/en/index"/>#',
            $xml
        );

        // Reciprocal: Russian home lists English + x-default
        $currentRu = $this->tempDir . '/sitemaps/current-ru.xml';
        $this->assertFileExists($currentRu);
        $ruXml = file_get_contents($currentRu);
        $this->assertIsString($ruXml);
        $this->assertStringContainsString(
            'hreflang="en" href="https://docs.modx.org/current/en/index"',
            $ruXml
        );
        $this->assertStringContainsString(
            'hreflang="x-default" href="https://docs.modx.org/current/en/index"',
            $ruXml
        );
    }

    public function testLanguagesConstantMatchesApp(): void
    {
        $this->assertSame(['en', 'ru', 'nl', 'es'], SitemapService::LANGUAGES);
        $this->assertSame(VersionsService::getCurrentVersion(), 'current');
        $this->assertSame(VersionsService::getCurrentVersionBranch(), '3.x');
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
