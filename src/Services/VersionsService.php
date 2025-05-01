<?php

namespace MODXDocs\Services;

use MODXDocs\Model\PageRequest;
use Slim\Interfaces\RouteParserInterface;

class VersionsService
{
    private const CURRENT_VERSION = 'current';
    private const CURRENT_VERSION_BRANCH = '2.x';
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
        $versions = self::getAvailableVersions();
        $currentVersion = self::getCurrentVersion();
        $currentVersionBranch = self::getCurrentVersionBranch();

        $result = [];
        foreach ($versions as $versionKey => $details) {
            $result[] = [
                'key' => $versionKey,
                'name' => $details['name'] ?? $versionKey,
                'branch' => $details['branch'] ?? $versionKey,
                'url' => $this->router->urlFor('documentation', [
                    'version' => $versionKey,
                    'language' => $request->getLanguage(),
                    'path' => VersionsService::getDefaultPath()
                ]),
                'is_current' => $versionKey === $currentVersion,
                'is_current_branch' => $versionKey === $currentVersionBranch,
            ];
        }

        return $result;
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

    public static function getDocsRoot()
    {
        return $_ENV['DOCS_DIRECTORY'];
    }
}
