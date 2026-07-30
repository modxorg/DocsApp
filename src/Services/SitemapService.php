<?php

namespace MODXDocs\Services;

use MODXDocs\Navigation\Tree;

/**
 * Generates a sitemap index plus per-version/language sitemap files.
 *
 * Files are written under public/ so Apache serves them as static XML
 * without hitting PHP — important with ~4k+ documentation URLs.
 *
 * Where translations exist, each <url> includes xhtml:link hreflang
 * alternates (including self and x-default → English), matching the
 * HTML alternate links rendered in the layout.
 */
class SitemapService
{
    public const LANGUAGES = ['en', 'ru', 'nl', 'es'];

    private string $baseUrl;
    private string $publicDir;
    private string $docsDir;
    private string $sitemapsDir;

    public function __construct(?string $baseUrl = null, ?string $publicDir = null, ?string $docsDir = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? ($_ENV['CANONICAL_BASE_URL'] ?? ''), '/') . '/';
        $this->publicDir = rtrim($publicDir ?? ($_ENV['BASE_DIRECTORY'] . 'public'), '/');
        $this->docsDir = rtrim($docsDir ?? ($_ENV['DOCS_DIRECTORY'] ?? ''), '/');
        $this->sitemapsDir = $this->publicDir . '/sitemaps';
    }

    /**
     * @return array{files: int, urls: int, sitemaps: list<string>}
     */
    public function generate(): array
    {
        if (!is_dir($this->sitemapsDir) && !mkdir($this->sitemapsDir, 0755, true) && !is_dir($this->sitemapsDir)) {
            throw new \RuntimeException('Unable to create sitemaps directory: ' . $this->sitemapsDir);
        }

        $this->cleanOldSitemaps();

        $sitemapFiles = [];
        $totalUrls = 0;

        $branches = array_keys(VersionsService::getAvailableVersions(false));

        foreach ($branches as $branch) {
            $urlVersion = $branch === VersionsService::getCurrentVersionBranch()
                ? VersionsService::getCurrentVersion()
                : $branch;

            $hreflangLookup = $this->buildHreflangLookup($branch, $urlVersion);

            foreach (self::LANGUAGES as $language) {
                $urls = $this->collectUrls($branch, $urlVersion, $language);
                if ($urls === []) {
                    continue;
                }

                $filename = $urlVersion . '-' . $language . '.xml';
                $path = $this->sitemapsDir . '/' . $filename;
                $this->writeUrlset($path, $urls, $hreflangLookup);
                $sitemapFiles[] = [
                    'loc' => $this->baseUrl . 'sitemaps/' . $filename,
                    'lastmod' => $this->newestLastmod($urls),
                ];
                $totalUrls += count($urls);
            }
        }

        $this->writeSitemapIndex($this->publicDir . '/sitemap.xml', $sitemapFiles);

        return [
            'files' => count($sitemapFiles) + 1,
            'urls' => $totalUrls,
            'sitemaps' => array_column($sitemapFiles, 'loc'),
        ];
    }

    /**
     * @return array<string, string|null> path => lastmod (Y-m-d) or null
     */
    private function collectUrls(string $branch, string $urlVersion, string $language): array
    {
        $languageRoot = $this->docsDir . '/' . $branch . '/' . $language;
        if (!is_dir($languageRoot)) {
            return [];
        }

        $urls = [];

        // Language home is skipped by Tree::getAllItems() (top-level index.md)
        $indexFile = $languageRoot . '/index.md';
        if (is_file($indexFile)) {
            $urls['/' . $urlVersion . '/' . $language . '/index'] = $this->lastmodFromFile($indexFile);
        }

        $tree = Tree::get($branch, $language);
        foreach ($tree->getAllItems() as $item) {
            $path = $this->canonicalPath($item['uri'] ?? '', $branch, $urlVersion, $language);
            if ($path === null) {
                continue;
            }

            // Prefer the first occurrence; file + directory can share a URI
            if (isset($urls[$path])) {
                continue;
            }

            $file = isset($item['file']) ? $this->docsDir . '/' . ltrim($item['file'], '/') : null;
            $urls[$path] = $file ? $this->lastmodFromFile($file) : null;
        }

        ksort($urls);

        return $urls;
    }

    /**
     * Build a lookup of canonical path => language cluster for hreflang.
     *
     * Same mapping rules as index:translations / TranslationService, but
     * produces canonical URL paths (e.g. /current/… instead of /2.x/…).
     * Only clusters with 2+ languages are included.
     *
     * @return array<string, array<string, string>> path => [lang => path, ...]
     */
    private function buildHreflangLookup(string $branch, string $urlVersion): array
    {
        $clusters = [];

        $navEn = Tree::get($branch, 'en');
        foreach ($navEn->getAllItems() as $item) {
            $enPath = $this->canonicalPath($item['uri'] ?? '', $branch, $urlVersion, 'en');
            if ($enPath !== null) {
                $clusters[$enPath] = ['en' => $enPath];
            }
        }

        foreach (['ru', 'nl', 'es'] as $language) {
            $languageNav = Tree::get($branch, $language);
            foreach ($languageNav->getAllItems() as $item) {
                $langPath = $this->canonicalPath($item['uri'] ?? '', $branch, $urlVersion, $language);
                if ($langPath === null) {
                    continue;
                }

                $translationOf = array_key_exists('translation', $item)
                    ? $item['translation']
                    : str_replace('/' . $language . '/', '/en/', $item['uri']);

                if (strpos($translationOf, '/' . $branch . '/en/') !== 0) {
                    $translationOf = '/' . $branch . '/en/' . trim($translationOf, '/');
                }

                $enPath = $this->canonicalPath($translationOf, $branch, $urlVersion, 'en');
                if ($enPath === null || !isset($clusters[$enPath])) {
                    continue;
                }

                $clusters[$enPath][$language] = $langPath;
            }
        }

        // Language homes (not in Tree::getAllItems)
        $indexCluster = [];
        foreach (self::LANGUAGES as $language) {
            $indexFile = $this->docsDir . '/' . $branch . '/' . $language . '/index.md';
            if (is_file($indexFile)) {
                $indexCluster[$language] = '/' . $urlVersion . '/' . $language . '/index';
            }
        }
        if (count($indexCluster) > 1) {
            $clusters[$indexCluster['en'] ?? reset($indexCluster)] = $indexCluster;
        }

        $lookup = [];
        foreach ($clusters as $cluster) {
            if (count($cluster) < 2) {
                continue;
            }
            // Stable language order matching SitemapService::LANGUAGES
            $ordered = [];
            foreach (self::LANGUAGES as $language) {
                if (isset($cluster[$language])) {
                    $ordered[$language] = $cluster[$language];
                }
            }
            foreach ($ordered as $path) {
                $lookup[$path] = $ordered;
            }
        }

        return $lookup;
    }

    private function canonicalPath(string $uri, string $branch, string $urlVersion, string $language): ?string
    {
        $uri = '/' . ltrim($uri, '/');
        $prefix = '/' . $branch . '/' . $language . '/';
        if (!str_starts_with($uri, $prefix)) {
            // Tree built with "current" already, or unexpected shape
            $altPrefix = '/' . $urlVersion . '/' . $language . '/';
            if (!str_starts_with($uri, $altPrefix)) {
                return null;
            }
            return rtrim($uri, '/') ?: null;
        }

        $remainder = substr($uri, strlen($prefix));
        if ($remainder === false || $remainder === '') {
            return null;
        }

        return '/' . $urlVersion . '/' . $language . '/' . $remainder;
    }

    private function lastmodFromFile(string $file): ?string
    {
        if (!is_file($file)) {
            return null;
        }

        $mtime = filemtime($file);
        if ($mtime === false) {
            return null;
        }

        return gmdate('Y-m-d', $mtime);
    }

    /**
     * Newest lastmod among URL entries, for the sitemap index.
     * Falls back to today if no file mtimes were available.
     *
     * @param array<string, string|null> $urls
     */
    private function newestLastmod(array $urls): string
    {
        $newest = null;
        foreach ($urls as $lastmod) {
            if ($lastmod !== null && ($newest === null || $lastmod > $newest)) {
                $newest = $lastmod;
            }
        }

        return $newest ?? gmdate('Y-m-d');
    }

    /**
     * @param array<string, string|null> $urls
     * @param array<string, array<string, string>> $hreflangLookup
     */
    private function writeUrlset(string $path, array $urls, array $hreflangLookup): void
    {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->writeAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');

        foreach ($urls as $pathPart => $lastmod) {
            $xml->startElement('url');
            $xml->writeElement('loc', $this->baseUrl . ltrim($pathPart, '/'));
            if ($lastmod !== null) {
                $xml->writeElement('lastmod', $lastmod);
            }

            if (isset($hreflangLookup[$pathPart])) {
                foreach ($hreflangLookup[$pathPart] as $lang => $altPath) {
                    $xml->startElement('xhtml:link');
                    $xml->writeAttribute('rel', 'alternate');
                    $xml->writeAttribute('hreflang', $lang);
                    $xml->writeAttribute('href', $this->baseUrl . ltrim($altPath, '/'));
                    $xml->endElement();
                }

                if (isset($hreflangLookup[$pathPart]['en'])) {
                    $xml->startElement('xhtml:link');
                    $xml->writeAttribute('rel', 'alternate');
                    $xml->writeAttribute('hreflang', 'x-default');
                    $xml->writeAttribute('href', $this->baseUrl . ltrim($hreflangLookup[$pathPart]['en'], '/'));
                    $xml->endElement();
                }
            }

            $xml->endElement();
        }

        $xml->endElement();
        $xml->endDocument();

        if (file_put_contents($path, $xml->outputMemory()) === false) {
            throw new \RuntimeException('Unable to write sitemap: ' . $path);
        }
    }

    /**
     * @param list<array{loc: string, lastmod: string}> $sitemaps
     */
    private function writeSitemapIndex(string $path, array $sitemaps): void
    {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('sitemapindex');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($sitemaps as $sitemap) {
            $xml->startElement('sitemap');
            $xml->writeElement('loc', $sitemap['loc']);
            $xml->writeElement('lastmod', $sitemap['lastmod']);
            $xml->endElement();
        }

        $xml->endElement();
        $xml->endDocument();

        if (file_put_contents($path, $xml->outputMemory()) === false) {
            throw new \RuntimeException('Unable to write sitemap index: ' . $path);
        }
    }

    private function cleanOldSitemaps(): void
    {
        $index = $this->publicDir . '/sitemap.xml';
        if (is_file($index)) {
            unlink($index);
        }

        foreach (glob($this->sitemapsDir . '/*.xml') ?: [] as $file) {
            unlink($file);
        }
    }
}
