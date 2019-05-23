<?php


namespace MODXDocs\Model;

use Knp\Menu\Matcher\Matcher;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment;
use MODXDocs\Exceptions\NotFoundException;
use MODXDocs\Helpers\LinkRenderer;
use MODXDocs\Helpers\RelativeImageRenderer;
use MODXDocs\Helpers\TocRenderer;
use MODXDocs\Services\CacheService;
use MODXDocs\Services\DocumentService;
use MODXDocs\Services\VersionsService;
use TOC\MarkupFixer;
use TOC\TocGenerator;
use Webuni\CommonMark\TableExtension\TableExtension;
use League\CommonMark\Inline\Element\Image;
use League\CommonMark\Inline\Element\Link;

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
    /**
     * @var string
     */
    private $relativeFilePath;

    public function __construct(DocumentService $documentService, string $version, string $language, string $requestPath, string $filePath, array $meta, string $body)
    {
        $this->version = $version;
        $this->meta = $meta;
        $this->body = $body;
        $this->language = $language;
        $this->path = $requestPath;
        $this->currentUrl = '/' . $version . '/' . $language . '/' . $requestPath;
        $this->documentService = $documentService;

        $docRoot = getenv('DOCS_DIRECTORY');
        if (strpos($filePath, $docRoot) === 0) {
            $filePath = ltrim(substr($filePath, strlen($docRoot)), '/');
        }
        $this->relativeFilePath = $filePath;
    }

    private function renderBody(): void
    {
        $cache = CacheService::getInstance();
        $key = 'rendered/' . trim($this->currentUrl, '/');
        $hash = md5($this->body);
        if ($rendered = $cache->get($key, $hash)) {
            $this->renderedBody = $rendered;
            return;
        }

        // Grab the markdown
        $environment = Environment::createCommonMarkEnvironment();
        $environment->addExtension(new TableExtension());
        $environment->addInlineRenderer(Link::class,
            new LinkRenderer(
                '/' . $this->version . '/' . $this->language . '/',
                $this->currentUrl
            )
        );
        $environment->addInlineRenderer(Image::class,
            new RelativeImageRenderer(
                $this->relativeFilePath
            )
        );

        $markdown = new CommonMarkConverter([
            'html_input' => 'allow',
        ], $environment);

        $content = $markdown->convertToHtml($this->body);

        $fixer = new MarkupFixer();
        $this->renderedBody = $fixer->fix($content);
        $cache->set($key, $this->renderedBody, null, $hash);
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
    public function getUrl(): string
    {
        return $this->currentUrl;
    }

    public function getCanonicalUrl(): string
    {
        $version = $this->version === VersionsService::getCurrentVersionBranch() ? VersionsService::getCurrentVersion() : $this->version;
        return getenv('CANONICAL_BASE_URL') . $version . '/' . $this->language . '/' . $this->path;
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

    public function getPageTitle(): string
    {
        $titles = [];
        $titles[] = $this->getTitle();

        if ($parent = $this->getParentPage()) {
            $titles[] = $parent->getTitle();
        }

        return implode(' - ', $titles);
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        if (array_key_exists('title', $this->meta)) {
            return $this->meta['title'];
        }
        $paths = explode('/', $this->path);
        $paths = array_filter($paths, function($v) { return strtolower($v) === 'index'; });
        $path = end($paths);
        $path = str_replace('-', ' ', $path);
        $path = ucfirst($path);
        return $path;
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

        try {
            return $tocGenerator->getHtmlMenu(
                $this->getRenderedBody(),
                $topLevel,
                $depth,
                $renderer
            );
        } catch (\TypeError $e) {
            // https://github.com/caseyamcl/toc/issues/6
            return 'Error generating table of contents for page.';
        }
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

    /**
     * @return string
     */
    public function getRelativeFilePath(): string
    {
        return $this->relativeFilePath;
    }
}
