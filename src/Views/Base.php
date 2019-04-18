<?php

namespace MODXDocs\Views;

use MODXDocs\Model\PageRequest;
use MODXDocs\Services\NavigationService;
use MODXDocs\Services\VersionsService;
use Monolog\Logger;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;
use Psr\Container\ContainerInterface;

abstract class Base
{
    /** @var ContainerInterface */
    protected $container;

    /** @var Logger */
    private $logger;

    /** @var Twig */
    private $view;

    /** @var NavigationService */
    private $navigationService;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        $this->logger = $this->container->get('logger');
        $this->view = $this->container->get('view');

        $this->navigationService = $this->container->get(NavigationService::class);
    }

    protected function render(Request $request, Response $response, $template, array $data = []): \Psr\Http\Message\ResponseInterface
    {

        $pageRequest = PageRequest::fromRequest($request);

        $initialData = [
            'revision' => static::getRevision(),
            'current_uri' => $request->getUri()->getPath(),
            'version' => $pageRequest->getVersion(),
            'version_branch' => $pageRequest->getVersionBranch(),
            'language' => $pageRequest->getLanguage(),
            'path' => $pageRequest->getPath(),
            'logo_link' => $pageRequest->getContextUrl() . VersionsService::getDefaultPath(),
            'topnav' => $this->navigationService->getTopNavigation($pageRequest),
            'is_dev' => (bool) getenv('DEV'),
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

    protected function render404(Request $request, Response $response, array $data = []): \Psr\Http\Message\ResponseInterface
    {
        $initialData = [
            'revision' => static::getRevision(),
            'is_dev' => (bool) getenv('DEV'),
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

    private static function getRevision() : string
    {
        $revision = 'dev';

        $projectDir = getenv('BASE_DIRECTORY');
        if (file_exists($projectDir . '.revision')) {
            $revision = trim((string)file_get_contents($projectDir . '.revision'));
        }

        return $revision;
    }
}
