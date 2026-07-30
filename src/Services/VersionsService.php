<?php

namespace MODXDocs\Services;

use MODXDocs\Model\PageRequest;
use Slim\Interfaces\RouteParserInterface;

class VersionsService
{
    private const CURRENT_VERSION = 'current';
    private const CURRENT_VERSION_BRANCH = '3.x';
    private const DEFAULT_LANGUAGE = 'en';
    private const DEFAULT_PATH = 'index';

    private RouteParserInterface $router;

    public function __construct(RouteParserInterface $router)
    {
        $this->router = $router;
    }

    public static function getAvailableVersions($includeCurrent = true): array
    {
        $versions = [];

        $base = $_ENV['BASE_DIRECTORY'];
        $config = null;
        $files = ['sources.dist.json', 'sources.json'];
        foreach ($files as $file) {
            $path = $base . $file;
            if (file_exists($path)) {
                try {
                    $config = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    $config = null;
                }
            }
        }

        if ($config === null) {
            return [];
        }

        foreach ($config as $versionKey => $details) {
            $versions[$versionKey] = $details;
        }

        if (
            $includeCurrent
            && !array_key_exists(self::getCurrentVersion(), $versions)
            && array_key_exists(self::getCurrentVersionBranch(), $versions)
        ) {
            $versions[self::getCurrentVersion()] = $versions[self::getCurrentVersionBranch()];
        }

        return $versions;
    }

    public function getVersions(PageRequest $request): array
    {
        $dir = new \DirectoryIterator($_ENV['DOCS_DIRECTORY']);

        $versions = [];

        foreach ($dir as $fileInfo) {
            if (!$fileInfo->isDir() || $fileInfo->isDot()) {
                continue;
            }

            $file = $fileInfo->getPathname()
                . '/'
                . $request->getLanguage()
                . '/'
                . $request->getPath();

            if (file_exists($file . '.md') || file_exists($file . '/index.md')) {
                $versions[] = $this->createVersion($request, $fileInfo);
            }
        }

        return $versions;
    }

    private function createVersion(PageRequest $request, \DirectoryIterator $fileInfo)
    {
        $versionKey = static::getVersionUrl($fileInfo->getFilename());
        return [
            'title' => static::getVersionTitle($fileInfo->getFilename()),
            'active' => $versionKey === $request->getVersion(),
            'key' => $versionKey,
            'uri' => $this->router->urlFor('documentation', [
                'version' => $versionKey,
                'language' => $request->getLanguage(),
                'path' => $request->getPath(),
            ])
        ];
    }

    private static function getVersionUrl($version)
    {
        // If we found another version e.g. 2.x, and 2.x is the `current` branch, use `current`
        // instead of 2.x in the URL
        if (static::getCurrentVersionBranch() === $version) {
            return static::getCurrentVersion();
        }

        return $version;
    }

    private static function getVersionTitle($fileVersion)
    {
        if (static::getCurrentVersionBranch() === $fileVersion) {
            return $fileVersion . ' (current)';
        }

        return $fileVersion;
    }

    public static function getCurrentVersion(): string
    {
        return self::CURRENT_VERSION;
    }

    public static function getCurrentVersionBranch(): string
    {
        return self::CURRENT_VERSION_BRANCH;
    }

    public static function getDefaultLanguage(): string
    {
        return self::DEFAULT_LANGUAGE;
    }

    public static function getDefaultPath(): string
    {
        return self::DEFAULT_PATH;
    }

    public static function getDocsRoot(): string
    {
        return $_ENV['DOCS_DIRECTORY'];
    }
}
