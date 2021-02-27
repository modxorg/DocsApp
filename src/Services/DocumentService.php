<?php

namespace MODXDocs\Services;

use MODXDocs\Exceptions\NotFoundException;
use MODXDocs\Model\Page;
use MODXDocs\Model\PageRequest;
use PDO;
use Spatie\YamlFrontMatter\YamlFrontMatter;

class DocumentService
{
    /**
     * @var PDO
     */
    protected $db;
    private $filePathService;

    public function __construct(FilePathService $filePathService, PDO $db)
    {
        $this->filePathService = $filePathService;
        $this->db = $db;
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
            $this->db,
            $request->getVersion(),
            $request->getLanguage(),
            $request->getPath(),
            $path,
            $parsed->matter(),
            $parsed->body()
        );
    }
}
