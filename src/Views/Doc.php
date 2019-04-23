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

class Doc extends Base
{
    /** @var TranslationService */
    private $translationService;
    /** @var DocumentService */
    private $documentService;
    /** @var VersionsService */
    private $versionsService;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->documentService = $this->container->get(DocumentService::class);
        $this->versionsService = $this->container->get(VersionsService::class);
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

            'versions' => $this->versionsService->getVersions($pageRequest),
            'nav' => $tree->renderTree($this->view),
            'translations' => $this->getTranslations($pageRequest),
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
}
