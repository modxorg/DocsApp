<?php

namespace MODXDocs\Services;

use MODXDocs\Model\PageRequest;

class FilePathService
{
    public function isValidRequest(PageRequest $request): bool
    {
        return $this->getFilePath($request) !== null;
    }

    public function getFilePath(PageRequest $request): ?string
    {
        $docsRoot = $this->getResolvedDocsRoot();
        if ($docsRoot === null) {
            return null;
        }

        $basePath = rtrim($this->getAbsoluteContextPath($request), '/');
        $relative = trim($request->getPath(), '/');
        $fullRequestPath = $relative === '' ? $basePath : $basePath . '/' . $relative;

        foreach ([$fullRequestPath . '.md', $fullRequestPath . '/index.md'] as $candidate) {
            $resolved = $this->resolveInsideDocsRoot($candidate, $docsRoot);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * Resolve a static file (e.g. image) under the docs tree for the request path.
     */
    public function getStaticFilePath(PageRequest $request): ?string
    {
        $docsRoot = $this->getResolvedDocsRoot();
        if ($docsRoot === null) {
            return null;
        }

        $basePath = rtrim($this->getAbsoluteContextPath($request), '/');
        $relative = trim($request->getPath(), '/');
        $candidate = $relative === '' ? $basePath : $basePath . '/' . $relative;

        return $this->resolveInsideDocsRoot($candidate, $docsRoot);
    }

    public function getDocsRoot()
    {
        return $_ENV['DOCS_DIRECTORY'];
    }

    public function getAbsoluteContextPath(PageRequest $request): string
    {
        return $this->getDocsRoot() . $request->getActualContextUrl();
    }

    private function getResolvedDocsRoot(): ?string
    {
        $docsRoot = realpath($this->getDocsRoot());

        return $docsRoot === false ? null : $docsRoot;
    }

    private function resolveInsideDocsRoot(string $candidate, string $docsRoot): ?string
    {
        if (!file_exists($candidate)) {
            return null;
        }

        $resolved = realpath($candidate);
        if ($resolved === false) {
            return null;
        }

        if ($resolved !== $docsRoot && !str_starts_with($resolved, $docsRoot . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $resolved;
    }
}
