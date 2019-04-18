<?php

namespace MODXDocs\Services;

use MODXDocs\Model\PageRequest;
use Slim\Router;

class VersionsService
{
    private const CURRENT_VERSION = 'current';
    private const CURRENT_VERSION_BRANCH = '2.x';
    private const DEFAULT_LANGUAGE = 'en';
    private const DEFAULT_PATH = 'index';

    private $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public static function getAvailableVersions($includeCurrent = true): array
    {
        $versions = [];

        $base = getenv('BASE_DIRECTORY');
        $config = null;
        $files = ['sources.dist.json', 'sources.json'];
        foreach ($files as $file) {
            $path = $base . $file;
            if (file_exists($path)) {
                $config = json_decode(file_get_contents($path), true);
            }
        }

        if ($config === null) {
            return [];
        }

        foreach ($config as $versionKey => $details) {
            $versions[$versionKey] = $details;
        }

        if ($includeCurrent
            && !array_key_exists(self::getCurrentVersion(), $versions)
            && array_key_exists(self::getCurrentVersionBranch(), $versions)) {
            $versions[self::getCurrentVersion()] = $versions[self::getCurrentVersionBranch()];
        }

        return $versions;
    }

    public function getVersions(PageRequest $request)
    {
        $dir = new \DirectoryIterator(getenv('DOCS_DIRECTORY'));

        $versions = [];

        foreach ($dir as $fileInfo) {
            if (!$fileInfo->isDir() || $fileInfo->isDot()) {
                continue;
            }

            // Do not include the current branch
            if ($request->getVersion() === $fileInfo->getFilename()) {
                continue;
            }

            // If we're on the current aliased branch (i.e. if getVersion is current, getVersionBranch is 2.x), skip it
            if ($request->getVersionBranch() === $fileInfo->getFilename()) {
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
            'key' => $versionKey,
            'uri' => $this->router->pathFor('documentation', [
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
        return static::CURRENT_VERSION;
    }

    public static function getCurrentVersionBranch(): string
    {
        return static::CURRENT_VERSION_BRANCH;
    }

    public static function getDefaultLanguage(): string
    {
        return static::DEFAULT_LANGUAGE;
    }

    public static function getDefaultPath(): string
    {
        return static::DEFAULT_PATH;
    }

    public static function getDocsRoot(): string
    {
        return getenv('DOCS_DIRECTORY');
    }
}
