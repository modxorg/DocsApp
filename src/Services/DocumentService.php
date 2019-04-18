<?php

namespace MODXDocs\Services;

use MODXDocs\Exceptions\NotFoundException;
use MODXDocs\Model\Page;
use MODXDocs\Model\PageRequest;
use Spatie\YamlFrontMatter\YamlFrontMatter;

class DocumentService
{
    private $filePathService;

    public function __construct(FilePathService $filePathService)
    {
        $this->filePathService = $filePathService;
    }

    /**
     * @param PageRequest $request
     * @return Page
     * @throws NotFoundException
     */
    public function load(PageRequest $request) : Page
    {
        $path = $this->filePathService->getFilePath($request);

        if ($path === null || !file_exists($path)) {
            throw new NotFoundException();
        }

        $fileContents = file_get_contents($path);
        $parsed = YamlFrontMatter::parse($fileContents);

        return new Page(
            $this,
            $request->getVersion(),
            $request->getLanguage(),
            $request->getPath(),
            $path,
            $parsed->matter(),
            $parsed->body()
        );
    }
}
