<?php

namespace MODXDocs\Services;

use Slim\Http\Request;

class RequestPathService
{
    const MODE_VERSION_BRANCH = 'MODE_VERSION_BRANCH';
    const MODE_VERSION = 'MODE_VERSION';

    private $requestAttributesService;

    public function __construct(RequestAttributesService $requestAttributesService)
    {
        $this->requestAttributesService = $requestAttributesService;
    }

    public function getFullUrlPath(Request $request)
    {
        return $this->getFullPath($request, static::MODE_VERSION);
    }

    public function getBaseUrlPath(Request $request)
    {
        return $this->getBasePath($request, static::MODE_VERSION);
    }

    public function getAbsoluteBaseFilePath(Request $request)
    {
        return getenv('DOCS_DIRECTORY') . $this->getBasePath($request, static::MODE_VERSION_BRANCH);
    }

    public function getAbsoluteFullFilePath(Request $request)
    {
        return getenv('DOCS_DIRECTORY') . $this->getFullPath($request, static::MODE_VERSION_BRANCH);
    }

    public function isValidRequest(Request $request)
    {
        return static::isValidPath($this->getAbsoluteBaseFilePath($request));
    }

    private function getFullPath(Request $request, $mode)
    {
        $requestPath = $this->requestAttributesService->getPath($request);

        if ($requestPath === null) {
            return $this->getBasePath($request, $mode);
        }

        return rtrim($this->getBasePath($request, $mode), '/') . '/' . $requestPath;
    }

    private function getBasePath(Request $request, $mode)
    {
        if ($mode === static::MODE_VERSION_BRANCH) {
            return $this->requestAttributesService->getVersionBranch($request)
                . '/'
                . $this->requestAttributesService->getLanguage($request)
                . '/';
        }

        return $this->requestAttributesService->getVersion($request)
            . '/'
            . $this->requestAttributesService->getLanguage($request)
            . '/';
    }

    private static function isValidPath($path)
    {
        return file_exists($path) || is_dir($path);
    }
}