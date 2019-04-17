<?php


namespace MODXDocs\Model;

use MODXDocs\Services\VersionsService;
use Slim\Http\Request;

class PageRequest {
    private $version;
    private $versionBranch;
    private $language;
    private $path;

    public function __construct(string $version, string $language, string $path)
    {
        $this->version = $version;
        $this->language = $language;
        $this->path = $path;
        $this->versionBranch = $this->version === VersionsService::getCurrentVersion() ? VersionsService::getCurrentVersionBranch() : $this->version;
    }

    public static function fromRequest(Request $request): self
    {
        return new static(
            $request->getAttribute('version', VersionsService::getCurrentVersion()),
            $request->getAttribute('language', VersionsService::getDefaultLanguage()),
            $request->getAttribute('path', VersionsService::getDefaultPath())
        );
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getContextUrl(): string
    {
        return '/' . $this->version . '/' . $this->language . '/';
    }

    public function getActualContextUrl(): string
    {
        return '/' . $this->versionBranch . '/' . $this->language . '/';
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getVersionBranch(): string
    {
        return $this->versionBranch;
    }
}
