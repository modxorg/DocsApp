<?php


namespace MODXDocs\Model;

use Knp\Menu\Matcher\Matcher;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment;
use MODXDocs\Exceptions\NotFoundException;
use MODXDocs\Helpers\LinkRenderer;
use MODXDocs\Helpers\TocRenderer;
use MODXDocs\Services\DocumentService;
use TOC\MarkupFixer;
use TOC\TocGenerator;
use Webuni\CommonMark\TableExtension\TableExtension;

class Page {

    /**
     * @var array
     */
    private $meta;
    /**
     * @var string
     */
    private $version;
    /**
     * @var string
     */
    private $language;
    /**
     * @var string
     */
    private $path;
    /**
     * @var string
     */
    private $body;
    /**
     * @var string
     */
    private $renderedBody;
    /**
     * @var string
     */
    private $currentUrl;
    /**
     * @var DocumentService
     */
    private $documentService;

    public function __construct(DocumentService $documentService, string $version, string $language, string $path, array $meta, string $body)
    {
        $this->version = $version;
        $this->meta = $meta;
        $this->body = $body;
        $this->language = $language;
        $this->path = $path;
        $this->currentUrl = '/' . $version . '/' . $language . '/' . $path;
        $this->documentService = $documentService;
    }

    private function renderBody(): void
    {
        // Grab the markdown
        $environment = Environment::createCommonMarkEnvironment();
        $environment->addExtension(new TableExtension());
        $environment->addInlineRenderer('League\CommonMark\Inline\Element\Link',
            new LinkRenderer(
                '/' . $this->version . '/' . $this->language . '/',
                $this->currentUrl
            )
        );

        $markdown = new CommonMarkConverter([
            'html_input' => 'allow',
        ], $environment);

        $content = $markdown->convertToHtml($this->body);

        $fixer = new MarkupFixer();
        $this->renderedBody = $fixer->fix($content);
    }

    /**
     * @return array
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return string
     */
    public function getRenderedBody(): string
    {
        if ($this->renderedBody === null) {
            $this->renderBody();
        }
        return $this->renderedBody;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        $titles = [];

        if (array_key_exists('title', $this->meta)) {
            $titles[] = $this->meta['title'];
        }
        else {
            $paths = explode('/', $this->path);
            $paths = array_filter($paths, function($v) { return strtolower($v) === 'index'; });
            $path = end($paths);
            $path = str_replace('-', ' ', $path);
            $path = ucfirst($path);
            $titles[] = $path;
        }

        if ($parent = $this->getParentPage()) {
            $titles[] = $parent->getTitle();
        }

        return implode(' - ', $titles);
    }

    public function getTableOfContents($topLevel = 2, $depth = 6) : string
    {
        $tocGenerator = new TocGenerator();

        $renderer = new TocRenderer(new Matcher(),
            $this->currentUrl,
            [
                'currentClass' => 'c-toc__item--active',
                'ancestorClass' => 'c-toc__item--activeancestor',
                'firstClass' => 'c-toc__item--first',
                'lastClass' => 'c-toc__item--last',
            ]
        );

        return $tocGenerator->getHtmlMenu(
            $this->getRenderedBody(),
            $topLevel,
            $depth,
            $renderer
        );
    }

    public function getParentPage(): ?Page
    {
        $path = explode('/', $this->path);
        array_pop($path);
        if (count($path) >= 1) {
            $req = new PageRequest($this->version, $this->language, implode('/', $path));
            try {
                return $this->documentService->load($req);
            } catch (NotFoundException $e) {
                return null;
            }
        }

        return null;
    }
}
