<?php

namespace MODXDocs;

use MODXDocs\Containers\DB;
use MODXDocs\Views\Search;
use MODXDocs\Views\Stats\NotFoundRequests;
use MODXDocs\Views\Stats\Searches;
use Slim\App;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;

use MODXDocs\Containers\View;
use MODXDocs\Containers\Logger;
use MODXDocs\Containers\Services;
use MODXDocs\Middlewares\RequestMiddleware;
use MODXDocs\Views\Doc;
use MODXDocs\Views\Error;
use MODXDocs\Views\NotFound;

class DocsApp
{
    /** @var App */
    private $app;
    /** @var Container */
    private $container;

    public function __construct(array $settings)
    {
        $this->container = new Container($settings);
        $this->app = AppFactory::create(null, $this->container);
        $this->container['router'] = function () {
            return $this->app->getRouteCollector()->getRouteParser();
        };

        $this->dependencies();
        $this->routes();
        $this->middlewares();
    }

    private function routes()
    {
        $this->app->get('/', Doc::class . ':get')->setName('home');
        $this->app->get('/stats/searches', Searches::class . ':get')->setName('stats/searches');
        $this->app->get('/stats/page-not-found', NotFoundRequests::class . ':get')->setName('stats/page-not-found');
        $this->app->get('/{version}/{language}/search', Search::class . ':get')->setName('search');
        $this->app->get('/{version}/{language}/{path:.*}', Doc::class . ':get')->setName('documentation');
    }

    private function middlewares()
    {
        $this->app->add(new RequestMiddleware());
        $container = $this->container;
        $responseFactory = $this->app->getResponseFactory();

        $errorMiddleware = $this->app->addErrorMiddleware(
            (bool)$this->container->get('settings')['displayErrorDetails'],
            true,
            true
        );
        $errorMiddleware->setDefaultErrorHandler(function (
            ServerRequestInterface $request,
            \Throwable $exception
        ) use ($container, $responseFactory): ResponseInterface {
            return (new Error($container, $exception))->get(
                $request,
                $responseFactory->createResponse()
            );
        });
        $errorMiddleware->setErrorHandler(HttpNotFoundException::class, function (
            ServerRequestInterface $request
        ) use ($container, $responseFactory): ResponseInterface {
            return (new NotFound($container))->get(
                $request,
                $responseFactory->createResponse()
            );
        });
        $errorMiddleware->setErrorHandler(HttpMethodNotAllowedException::class, function (
            ServerRequestInterface $request,
            HttpMethodNotAllowedException $exception
        ) use ($responseFactory): ResponseInterface {
            $response = $responseFactory->createResponse(405);
            $response->getBody()->write($exception->getMessage());

            return $response;
        });
    }

    private function dependencies()
    {
        $containers = [
            DB::class,
            View::class,
            Logger::class,
            Services::class
        ];

        foreach ($containers as $container) {
            call_user_func([$container, 'load'], $this->app->getContainer());
        }
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function run()
    {
        $this->app->run();
    }

    public function process(ServerRequestInterface $request): ResponseInterface
    {
        return $this->app->handle($request);
    }

}
