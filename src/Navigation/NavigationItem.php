<?php

namespace MODXDocs\Navigation;

class NavigationItem
{
    const DEFAULT_LEVEL = 1;
    const DEFAULT_DEPTH = 10;

    const TOP_MENU_LEVEL = 1;
    const TOP_MENU_DEPTH = 1;

    const HOME_MENU_LEVEL = 1;
    const HOME_MENU_DEPTH = 2;

    private $currentFilePath;
    private $basePath;
    private $filePath;
    private $urlPath;
    private $level;
    private $depth;

    private $version;
    private $language;
    private $path;

    public function __construct(
        $currentFilePath,
        $basePath,
        $filePath,
        $urlPath,
        $level,
        $depth,
        $version,
        $language,
        $path
    )
    {
        $this->currentFilePath = $currentFilePath;
        $this->basePath = $basePath;
        $this->filePath = $filePath;
        $this->urlPath = $urlPath;
        $this->level = $level;
        $this->depth = $depth;

        $this->version = $version;
        $this->language = $language;
        $this->path = $path;
    }

    public function getCurrentFilePath()
    {
        return $this->currentFilePath;
    }

    public function getBasePath()
    {
        return $this->basePath;
    }

    public function getFilePath()
    {
        return $this->filePath;
    }

    public function getUrlPath()
    {
        return $this->urlPath;
    }

    public function getLevel()
    {
        return $this->level;
    }

    public function getDepth()
    {
        return $this->depth;
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function getLanguage()
    {
        return $this->language;
    }

    public function getPath()
    {
        return $this->path;
    }
}