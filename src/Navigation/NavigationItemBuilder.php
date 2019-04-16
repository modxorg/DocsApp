<?php
namespace MODXDocs\Navigation;

class NavigationItemBuilder
{
    const NOT_INITIALIZED = 'NOT_INITIALIZED';

    private $currentFilePath = NavigationItemBuilder::NOT_INITIALIZED;
    private $basePath = NavigationItemBuilder::NOT_INITIALIZED;
    private $filePath = NavigationItemBuilder::NOT_INITIALIZED;
    private $urlPath = NavigationItemBuilder::NOT_INITIALIZED;
    private $level = NavigationItemBuilder::NOT_INITIALIZED;
    private $depth = NavigationItemBuilder::NOT_INITIALIZED;

    private $version = NavigationItemBuilder::NOT_INITIALIZED;
    private $language = NavigationItemBuilder::NOT_INITIALIZED;
    private $path = NavigationItemBuilder::NOT_INITIALIZED;

    public function __construct()
    {
        $this->level = NavigationItem::DEFAULT_LEVEL;
        $this->depth = NavigationItem::DEFAULT_DEPTH;
    }

    public function forTopMenu()
    {
        $this->level = NavigationItem::TOP_MENU_LEVEL;
        $this->depth = NavigationItem::TOP_MENU_DEPTH;
        return $this;
    }

    public function withCurrentFilePath($currentFilePath)
    {
        $this->currentFilePath = $currentFilePath;
        return $this;
    }

    public function withBasePath($basePath)
    {
        $this->basePath = $basePath;
        return $this;
    }

    public function withFilePath($filePath)
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function withUrlPath($urlPath)
    {
        $this->urlPath = $urlPath;
        return $this;
    }

    public function withLevel($level)
    {
        $this->level = $level;
        return $this;
    }

    public function withDepth($depth)
    {
        $this->depth = $depth;
        return $this;
    }

    public function withVersion($version)
    {
        $this->version = $version;
        return $this;
    }

    public function withLanguage($language)
    {
        $this->language = $language;
        return $this;
    }

    public function withPath($path)
    {
        $this->path = $path;
        return $this;
    }

    public function build()
    {
        return new NavigationItem(
            $this->currentFilePath,
            $this->basePath,
            $this->filePath,
            $this->urlPath,
            $this->level,
            $this->depth,
            $this->version,
            $this->language,
            $this->path
        );
    }

    public static function copyFromItem(NavigationItem $item)
    {
        return (new NavigationItemBuilder())
            ->withCurrentFilePath($item->getCurrentFilePath())
            ->withBasePath($item->getBasePath())
            ->withFilePath($item->getFilePath())
            ->withUrlPath($item->getUrlPath())
            ->withLevel($item->getLevel())
            ->withDepth($item->getDepth())
            ->withVersion($item->getVersion())
            ->withLanguage($item->getLanguage())
            ->withPath($item->getPath());
    }
}