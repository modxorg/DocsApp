<?php

namespace MODXDocs;

use DI\Container;
use DI\ContainerBuilder;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Interfaces\RouteParserInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Routing\RouteCollector;
use Slim\Views\TwigMiddleware;
use MODXDocs\Containers\ErrorHandlers;
use MODXDocs\Containers\Logger;
use MODXDocs\Containers\Services;
use MODXDocs\Containers\View;
use MODXDocs\Containers\DB;
use MODXDocs\Middlewares\NoiseRequestMiddleware;
use MODXDocs\Middlewares\RequestMiddleware;

class DocsApp
{
    private App $app;
    private Container $container;

    public function __construct(array $settings)
    {
        // Create Container using ContainerBuilder
        $containerBuilder = new ContainerBuilder();

        // Define container entries
        $containerBuilder->addDefinitions([
            'settings' => $settings,
            ResponseFactoryInterface::class => function () {
                return new ResponseFactory();
            },
            ServerRequestInterface::class => function () {
                return (new ServerRequestFactory())->createFromGlobals();
            },
            'request' => function (Container $c) {
                return $c->get(ServerRequestInterface::class);
            },
            'response' => function (Container $c) {
                return $c->get(ResponseFactoryInterface::class)->createResponse();
            },
            RouteCollector::class => function (Container $c) {
                return $c->get(App::class)->getRouteCollector();
            },
            RouteParserInterface::class => function (Container $c) {
                return $c->get(RouteCollector::class)->getRouteParser();
            },
            'router' => function (Container $c) {
                return $c->get(RouteParserInterface::class);
            }
        ]);

        $this->container = $containerBuilder->build();

        // Create App with Container
        AppFactory::setContainer($this->container);
        $this->app = AppFactory::create();

        // Store app in container
        $this->container->set(App::class, $this->app);

        // Register services
        Services::load($this->container);
        Logger::load($this->container);
        View::load($this->container);
        DB::load($this->container);

        // Add middleware (last added runs first)
        $this->app->add(new RequestMiddleware());
        $this->app->add(TwigMiddleware::createFromContainer($this->app));

        // Add error handling
        ErrorHandlers::load($this->container);

        // Outermost: drop scanner noise before routing / fancy 404 / logging
        $this->app->add(new NoiseRequestMiddleware());

        // Add routes
        $this->addRoutes();
    }

    private function addRoutes(): void
    {
        $app = $this->app;
        $container = $this->container;

        $app->get('/', function ($request, $response) {
            return $response->withHeader('Location', '/current/en/')->withStatus(301);
        });

        $app->get('/stats/searches', function ($request, $response) use ($container) {
            $page = new \MODXDocs\Views\Stats\Searches($container);
            return $page->get($request, $response);
        })->setName('stats/searches');

        $app->get('/stats/page-not-found', function ($request, $response) use ($container) {
            $page = new \MODXDocs\Views\Stats\NotFoundRequests($container);
            return $page->get($request, $response);
        })->setName('stats/page-not-found');

        $app->get('/{version}/{language}/search', function ($request, $response, $args) use ($container) {
            $page = new \MODXDocs\Views\Search($container);
            return $page->get($request, $response);
        })->setName('search');

        $app->get('/{version}/{language}/{path:.*}', function ($request, $response, $args) use ($container) {
            $pageRequest = \MODXDocs\Model\PageRequest::fromRequest($request);
            $documentService = $container->get(\MODXDocs\Services\DocumentService::class);
            try {
                $document = $documentService->load($pageRequest);
            } catch (\MODXDocs\Exceptions\NotFoundException $e) {
                $pageNotFound = new \MODXDocs\Views\NotFound($container);
                return $pageNotFound->get($request, $response);
            }

            $page = new \MODXDocs\Views\Doc($container);
            return $page->get($request, $response, $document);
        })->setName('documentation');
    }

    public function run(): void
    {
        $this->app->run();
    }

    public function getApp(): App
    {
        return $this->app;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}
