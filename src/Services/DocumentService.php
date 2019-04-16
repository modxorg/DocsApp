<?php

namespace MODXDocs\Services;

use Knp\Menu\Matcher\Matcher;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment;
use Slim\Http\Request;
use Spatie\YamlFrontMatter\YamlFrontMatter;
use Spatie\YamlFrontMatter\Document;
use TOC\MarkupFixer;
use TOC\TocGenerator;
use Webuni\CommonMark\TableExtension\TableExtension;

use MODXDocs\Helpers\LinkRenderer;
use MODXDocs\Helpers\TocRenderer;

class DocumentService
{
    const TOC_TOP_LEVEL = 2;
    const TOC_DEPTH = 6;

    private $requestPathService;
    private $filePathService;
    private $requestAttributesService;

    /** @var boolean */
    private $loaded;

    /** @var Document */
    private $document;

    /** @var string */
    private $content;

    /** @var string */
    private $tableOfContents;

    public function __construct(
        RequestPathService $requestPathService,
        FilePathService $filePathService,
        RequestAttributesService $requestAttributesService
    )
    {
        $this->requestPathService = $requestPathService;
        $this->filePathService = $filePathService;
        $this->requestAttributesService = $requestAttributesService;

        $this->loaded = false;
    }

    private function initialize(Request $request)
    {
        $this->document = $this->getParsedDocument($request);
        $this->content = $this->getDocumentContent($request);
        $this->tableOfContents = $this->getDocumentTableOfContents($request);

        $this->loaded = true;
    }

    public function getMeta(Request $request)
    {
        if (!$this->loaded) {
            $this->initialize($request);
        }

        return $this->document->matter();
    }

    public function getContent(Request $request)
    {
        if (!$this->loaded) {
            $this->initialize($request);
        }

        return $this->content;
    }

    public function getTableOfContents(Request $request)
    {
        if (!$this->loaded) {
            $this->initialize($request);
        }

        return $this->tableOfContents;
    }

    private function getParsedDocument(Request $request)
    {
        $fileContents = file_get_contents($this->filePathService->getFilePath($request));

        // Parse the front matter and make it available as meta
        return YamlFrontMatter::parse($fileContents);
    }

    private function getDocumentContent(Request $request)
    {
        $body = $this->document->body();

        // Grab the markdown
        $environment = Environment::createCommonMarkEnvironment();
        $environment->addExtension(new TableExtension());
        $environment->addInlineRenderer('League\CommonMark\Inline\Element\Link',
            new LinkRenderer(
                $this->requestPathService->getBaseUrlPath($request),
                $this->requestAttributesService->getPath($request)
            )
        );

        $markdown = new CommonMarkConverter([
            'html_input' => 'allow',
        ], $environment);

        $content = $markdown->convertToHtml($body);

        $fixer = new MarkupFixer();
        return $fixer->fix($content);
    }

    private function getDocumentTableOfContents(Request $request)
    {
        $tocGenerator = new TocGenerator();

        $renderer = new TocRenderer(new Matcher(),
            $this->requestPathService->getFullUrlPath($request),
            [
                'currentClass' => 'active',
                'ancestorClass' => 'active_ancestor'
            ]
        );

        return $tocGenerator->getHtmlMenu(
            $this->content,
            static::TOC_TOP_LEVEL,
            static::TOC_DEPTH,
            $renderer
        );
    }
}