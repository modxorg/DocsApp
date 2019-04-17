<?php

namespace MODXDocs\Services;

use Slim\Http\Request;

class FilePathService
{
    private $requestPathService;
    private $requestAttributesService;

    public function __construct(
        RequestPathService $requestPathService,
        RequestAttributesService $requestAttributesService
    )
    {
        $this->requestPathService = $requestPathService;
        $this->requestAttributesService = $requestAttributesService;
    }

    public function isValidRequest(Request $request)
    {
        return $this->getFilePath($request) !== null;
    }

    public function getFilePath(Request $request)
    {
        return $this->constructFilePath($request);
    }

    private function constructFilePath(Request $request)
    {
        $basePath = rtrim($this->requestPathService->getAbsoluteFullFilePath($request), '/');

        $file = $basePath . '.md';
        if (file_exists($file)) {
            return $file;
        }

        // See if we have an index file instead
        $file = $basePath . '/index.md';
        if (file_exists($file)) {
            return $file;
        }

        return null;
    }
}