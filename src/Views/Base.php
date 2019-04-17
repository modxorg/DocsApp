<?php

namespace MODXDocs\Views;

use Psr\Container\ContainerInterface;
use Monolog\Logger;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;

use MODXDocs\Services\RequestAttributesService;
use MODXDocs\Services\NavigationService;

abstract class Base
{
    /** @var ContainerInterface */
    protected $container;

    /** @var Logger */
    private $logger;

    /** @var Twig */
    private $view;

    /** @var RequestAttributesService */
    private $requestAttributeService;

    /** @var NavigationService */
    private $navigationService;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        $this->logger = $this->container->get('logger');
        $this->view = $this->container->get('view');

        $this->requestAttributeService = $this->container->get(RequestAttributesService::class);
        $this->navigationService = $this->container->get(NavigationService::class);
    }

    protected function render(Request $request, Response $response, $template, array $data = [])
    {
        $initialData = [
            'revision' => static::getRevision(),
            'current_uri' => $request->getUri()->getPath(),
            'version' => $this->requestAttributeService->getVersion($request),
            'version_branch' => $this->requestAttributeService->getVersionBranch($request),
            'language' => $this->requestAttributeService->getLanguage($request),
            'path' => $this->requestAttributeService->getPath($request),
            'topnav' => $this->navigationService->getTopNavigation($request),
        ];

        return $this->view->render(
            $response,
            $template,
            \array_merge(
                $initialData,
                $data
            )
        );
    }

    protected function render404(Request $request, Response $response, array $data = [])
    {
        $initialData = [
            'revision' => static::getRevision(),
            'current_uri' => $request->getUri()->getPath(),
        ];

        return $this->view->render(
            $response->withStatus(404),
            'notfound.twig',
            \array_merge(
                $initialData,
                $data
            )
        );
    }

    private static function getRevision()
    {
        $revision = 'dev';

        $projectDir = getenv('BASE_DIRECTORY');
        if (file_exists($projectDir . '.revision')) {
            $revision = file_get_contents($projectDir . '.revision');
        }

        return $revision;
    }
}
