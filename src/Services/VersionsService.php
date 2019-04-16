<?php

namespace MODXDocs\Services;

use Slim\Http\Request;
use Slim\Router;

class VersionsService
{
    const VERSION_DEFAULT_PATH = 'index';

    private $router;
    private $filePathService;
    private $requestAttributesService;

    public function __construct(
        Router $router,
        FilePathService $filePathService,
        RequestAttributesService $requestAttributesService
    )
    {
        $this->router = $router;
        $this->filePathService = $filePathService;
        $this->requestAttributesService = $requestAttributesService;
    }

    public function getVersions(Request $request)
    {
        $dir = new \DirectoryIterator(getenv('DOCS_DIRECTORY'));

        $versions = [];

        foreach ($dir as $fileInfo) {
            if (!$fileInfo->isDir() || $fileInfo->isDot()) {
                continue;
            }

            // Do not include the current branch
            if ($this->requestAttributesService->getVersionBranch($request) === $fileInfo->getFilename()) {
                continue;
            }

            $file = $fileInfo->getPathname()
                . '/'
                . $this->requestAttributesService->getLanguage($request)
                . '/'
                . $this->requestAttributesService->getPath($request);

            if (file_exists($file . '.md') || file_exists($file . '/index.md')) {
                $versions[] = $this->createVersion($request, $fileInfo);
            }
        }

        return $versions;
    }

    private function createVersion(Request $request, \DirectoryIterator $fileInfo)
    {
        return [
            'title' => static::getVersionTitle($fileInfo->getFilename()),
            'uri' => $this->router->pathFor('documentation', [
                'version' => static::getVersionUrl($fileInfo->getFilename()),
                'language' => $this->requestAttributesService->getLanguage($request),
                'path' => $this->requestAttributesService->getPath($request, static::VERSION_DEFAULT_PATH),
            ])
        ];
    }

    private static function getVersionUrl($version)
    {
        // If we found another version e.g. 2.x, and 2.x is the `current` branch, use `current`
        // instead of 2.x in the URL
        if (RequestAttributesService::CURRENT_BRANCH_VERSION === $version) {
            return RequestAttributesService::DEFAULT_VERSION;
        }

        return $version;
    }

    private static function getVersionTitle($fileVersion)
    {
        if (RequestAttributesService::CURRENT_BRANCH_VERSION === $fileVersion) {
            return $fileVersion . ' (current)';
        }

        return $fileVersion;
    }
}