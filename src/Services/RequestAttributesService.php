<?php

namespace MODXDocs\Services;

use Slim\Http\Request;

class RequestAttributesService
{
    const DEFAULT_VERSION = 'current';
    const CURRENT_BRANCH_VERSION = '2.x';
    const DEFAULT_LANGUAGE = 'en';
    const DEFAULT_PATH = null;

    public function getVersion(Request $request, $default = RequestAttributesService::DEFAULT_VERSION)
    {
        return $request->getAttribute('version', $default);
    }

    public function getVersionBranch(Request $request, $default = RequestAttributesService::DEFAULT_VERSION)
    {
        $version = $this->getVersion($request, $default);

        return $version === static::DEFAULT_VERSION ? static::CURRENT_BRANCH_VERSION : $version;
    }

    public function getLanguage(Request $request, $default = RequestAttributesService::DEFAULT_LANGUAGE)
    {
        return $request->getAttribute('language', $default);
    }

    public function getPath(Request $request, $default = RequestAttributesService::DEFAULT_PATH)
    {
        return $request->getAttribute('path', $default);
    }
}