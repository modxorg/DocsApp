<?php

namespace MODXDocs\Views;

use MODXDocs\Exceptions\NotFoundException;
use MODXDocs\Navigation\Tree;
use MODXDocs\Model\PageRequest;
use MODXDocs\Services\TranslationService;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

use MODXDocs\Services\DocumentService;
use MODXDocs\Services\VersionsService;
use Slim\Http\Stream;

class Doc extends Base
{
    /** @var TranslationService */
    private $translationService;
    /** @var DocumentService */
    private $documentService;
    /** @var VersionsService */

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->documentService = $this->container->get(DocumentService::class);
        $this->translationService = $this->container->get(TranslationService::class);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Slim\Exception\NotFoundException
     */
    public function get(Request $request, Response $response)
    {
        $pageRequest = PageRequest::fromRequest($request);

        // Throws our own NotFoundException when it doesn't exist; rethrow for Slim
        try {
            $page = $this->documentService->load($pageRequest);
        } catch (NotFoundException $e) {
            $filePath = VersionsService::getDocsRoot() . $pageRequest->getActualContextUrl() . $pageRequest->getPath();
            if (file_exists($filePath)) {
                return $this->renderFile($request, $response, $filePath);
            }
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $crumbs = [];

        $parent = $page;
        while ($parent = $parent->getParentPage()) {
            $crumbs[] = [
                'title' => $parent->getTitle(),
                'href' => $parent->getUrl(),
            ];
        }

        $crumbs[] = [
            'title' => 'Home', // @todo i18n
            'href' => $pageRequest->getContextUrl() . VersionsService::getDefaultPath(),
        ];

        $crumbs = array_reverse($crumbs);

        $tree = Tree::get($pageRequest->getVersion(), $pageRequest->getLanguage());
        $tree->setActivePath($pageRequest->getContextUrl() . $pageRequest->getPath());

        return $this->render($request, $response, 'documentation.twig', [
            'title' => $page->getTitle(),
            'page_title' => $page->getPageTitle(),
            'crumbs' => $crumbs,
            'canonical_url' => $page->getCanonicalUrl(),

            'meta' => $page->getMeta(),
            'parsed' => $page->getRenderedBody(),
            'toc' => $page->getTableOfContents(),
            'relative_file_path' => $page->getRelativeFilePath(),

//            'versions' => $this->versionsService->getVersions($pageRequest),
            'nav' => $tree->renderTree($this->view),
            'translations' => $this->getTranslations($pageRequest),

            'history' => $page->getHistory(),

            'suggested_languages' => $this->getSuggestedLanguages($request, $pageRequest),
        ]);
    }

    private function getTranslations(PageRequest $pageRequest)
    {
        $translations = $this->translationService->getAvailableTranslations($pageRequest);
        foreach ($translations as $lang => &$uri) {
            if ($pageRequest->getVersion() !== $pageRequest->getVersionBranch()) {
                $uri = str_replace('/' . $pageRequest->getVersionBranch() . '/', '/' . $pageRequest->getVersion() . '/', $uri);
            }
        }
        return $translations;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $filePath
     * @return Response
     * @throws \Slim\Exception\NotFoundException
     */
    protected function renderFile(Request $request, Response $response, $filePath): Response
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        // If it's an image, allow it
        if (strpos($mime, 'image/') === 0) {
            $etag = 'm-' . filemtime($filePath);
            $provided = $request->getHeaderLine('If-None-Match');
            $age = getenv('DEV') ? 10 : 86400;
            if ($etag === $provided) {
                return $response->withStatus(304)
                    ->withHeader('Cache-Control', 'max-age=' . $age)
                    ->withHeader('ETag', filemtime($filePath));
            }

            return $response->withBody(new Stream(fopen($filePath, 'rb')))
                ->withHeader('Content-Type', $mime)
                ->withHeader('Cache-Control', 'max-age=' . $age)
                ->withHeader('ETag', $etag);
        }

        throw new \Slim\Exception\NotFoundException($request, $response);
    }

    private function getSuggestedLanguages(Request $request, PageRequest $pageRequest)
    {
        // Only run on the homepage, suggest alternative languages
        if ($request->getAttribute('path') !== null && $pageRequest->getPath() !== 'index') {
            return [];
        }

        // Based on the wonderful https://www.codingwithjesse.com/blog/use-accept-language-header/
        $requestedLanguages = [];
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            // break up string into pieces (languages and q factors)
            preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $lang_parse);

            if (count($lang_parse[1])) {
                // create a list like "en" => 0.8
                $requestedLanguages = array_combine($lang_parse[1], $lang_parse[4]);

                // set default to 1 for any without q factor
                foreach ($requestedLanguages as $requestLanguage => $val) {
                    if ($val === '') {
                        $requestedLanguages[$requestLanguage] = 1;
                    }
                }

                // sort list based on value
                arsort($requestedLanguages, SORT_NUMERIC);
            }
        }

        $currentLanguage = $pageRequest->getLanguage();
        $suggestions = [];
        $documentationLanguages = ['en', 'ru', 'nl'];
        // look through sorted list and use first one that matches a supported language
        foreach ($requestedLanguages as $requestLanguage => $priority) {
            foreach ($documentationLanguages as $docLanguage) {
                if ($docLanguage !== $currentLanguage && strpos($requestLanguage, $docLanguage) === 0) {
                    $suggestions[] = $docLanguage;
                }
            }
        }
        return array_unique($suggestions);
    }
}
