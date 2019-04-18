<?php

namespace MODXDocs\Views;

use MODXDocs\Exceptions\NotFoundException;
use MODXDocs\Model\PageRequest;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

use MODXDocs\Services\NavigationService;
use MODXDocs\Services\DocumentService;
use MODXDocs\Services\VersionsService;

class Doc extends Base
{
    /** @var DocumentService */
    private $documentService;

    /** @var NavigationService */
    private $navigationService;

    /** @var VersionsService */
    private $versionsService;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->documentService = $this->container->get(DocumentService::class);
        $this->navigationService = $this->container->get(NavigationService::class);
        $this->versionsService = $this->container->get(VersionsService::class);
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

        return $this->render($request, $response, 'documentation.twig', [
            'title' => $page->getTitle(),
            'page_title' => $page->getPageTitle(),
            'crumbs' => $crumbs,

            'meta' => $page->getMeta(),
            'parsed' => $page->getRenderedBody(),
            'toc' => $page->getTableOfContents(),

            'versions' => $this->versionsService->getVersions($pageRequest),
            'nav' => $this->navigationService->getNavigation($pageRequest),
            'current_nav_parent' => $this->navigationService->getNavParent($pageRequest),
        ]);
    }
}
