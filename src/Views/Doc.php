<?php

namespace MODXDocs\Views;

use Psr\Container\ContainerInterface;
use Slim\Exception\NotFoundException;
use Slim\Http\Request;
use Slim\Http\Response;

use MODXDocs\Navigation\NavigationItemBuilder;
use MODXDocs\Services\NavigationService;
use MODXDocs\Services\RequestPathService;
use MODXDocs\Services\DocumentService;
use MODXDocs\Services\VersionsService;
use MODXDocs\Services\FilePathService;
use MODXDocs\Services\RequestAttributesService;

class Doc extends Base
{
    /** @var RequestPathService */
    private $requestPathService;

    /** @var FilePathService */
    private $filePathService;

    /** @var DocumentService */
    private $documentService;

    /** @var NavigationService */
    private $navigationService;

    /** @var VersionsService */
    private $versionsService;

    /** @var RequestAttributesService */
    private $requestAttributesService;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->requestPathService = $this->container->get(RequestPathService::class);
        $this->filePathService = $this->container->get(FilePathService::class);
        $this->documentService = $this->container->get(DocumentService::class);
        $this->navigationService = $this->container->get(NavigationService::class);
        $this->versionsService = $this->container->get(VersionsService::class);
        $this->requestAttributesService = $this->container->get(RequestAttributesService::class);
    }

    public function get(Request $request, Response $response)
    {
        if (!$this->requestPathService->isValidRequest($request) || !$this->filePathService->isValidRequest($request)) {
            throw new NotFoundException($request, $response);
        }

        return $this->render($request, $response, 'documentation.twig', [
            'page_title' => static::getPageTitle($request->getAttribute('path')),

            'meta' => $this->documentService->getMeta($request),
            'parsed' => $this->documentService->getContent($request),
            'toc' => $this->documentService->getTableOfContents($request),

            'versions' => $this->versionsService->getVersions($request),
            'nav' => $this->navigationService->getNavigation($request),
            'current_nav_parent' => $this->navigationService->getNavParent(
                (new NavigationItemBuilder())
                    ->withCurrentFilePath($this->filePathService->getFilePath($request))
                    ->withFilePath($this->requestPathService->getAbsoluteBaseFilePath($request))
                    ->withPath($this->requestAttributesService->getPath($request))
                    ->withVersion($this->requestAttributesService->getVersion($request))
                    ->withLanguage($this->requestAttributesService->getLanguage($request))
                    ->build()
            ),
        ]);
    }

    private static function getPageTitle($path)
    {
        // Generate a page title crumbs kinda thing
        $path = str_replace('-', ' ', $path);
        $path = explode('/', $path);
        $path = array_map('ucfirst', $path);
        $path = array_reverse($path);

        return implode(' / ', $path);
    }
}
