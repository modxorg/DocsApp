<?php

namespace MODXDocs\Services;

use Slim\Http\Request;

class NotFoundService
{
    const INVALID_VALUE = 'invalid_value';

    private $requestAttributesService;

    public function __construct(RequestAttributesService $requestAttributesService)
    {
        $this->requestAttributesService = $requestAttributesService;
    }

    public function getVersion(Request $request)
    {
        $version = $this->requestAttributesService->getVersion($request, static::INVALID_VALUE);
        if ($version === static::INVALID_VALUE || !static::isValidatePath($version)) {
            return RequestAttributesService::DEFAULT_VERSION;
        }

        return $version;
    }

    public function getVersionBranch(Request $request)
    {
        $version = $this->getVersion($request);
        if ($version === RequestAttributesService::DEFAULT_VERSION) {
            return RequestAttributesService::CURRENT_BRANCH_VERSION;
        }

        return $version;
    }

    public function getLanguage(Request $request)
    {
        $existingPath = $this->getVersionBranch($request);
        $language = $this->requestAttributesService->getLanguage($request, static::INVALID_VALUE);
        $newPath = $existingPath . '/' . $language;
        if ($language === static::INVALID_VALUE || !static::isValidatePath($newPath)) {
            return RequestAttributesService::DEFAULT_LANGUAGE;
        }

        return $language;
    }

    private static function isValidatePath($path)
    {
        return file_exists(getenv('DOCS_DIRECTORY') . $path);
    }
}