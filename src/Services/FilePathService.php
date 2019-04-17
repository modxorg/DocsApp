<?php

namespace MODXDocs\Services;


use MODXDocs\Model\PageRequest;

class FilePathService
{
    public function isValidRequest(PageRequest $request) : bool
    {
        return $this->getFilePath($request) !== null;
    }

    public function getFilePath(PageRequest $request) : ?string
    {
        $basePath = rtrim($this->getAbsoluteContextPath($request), '/');

        $fullRequestPath = $basePath . '/' . trim($request->getPath(), '/');

        $file = $fullRequestPath . '.md';
        if (file_exists($file)) {
            return $file;
        }

        // See if we have an index file instead
        $file = $fullRequestPath . '/index.md';
        if (file_exists($file)) {
            return $file;
        }

        return null;
    }

    public function getAbsoluteRootPath() : string
    {
        return getenv('DOCS_DIRECTORY');
    }

    public function getAbsoluteContextPath(PageRequest $request) : string
    {
        return $this->getAbsoluteRootPath() . $request->getActualContextUrl();
    }
}
