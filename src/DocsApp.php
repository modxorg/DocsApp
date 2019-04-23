<?php

namespace MODXDocs;

use MODXDocs\Containers\DB;
use MODXDocs\Views\Search;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

use MODXDocs\Containers\View;
use MODXDocs\Containers\ErrorHandlers;
use MODXDocs\Containers\Logger;
use MODXDocs\Containers\Services;
use MODXDocs\Middlewares\RequestMiddleware;
use MODXDocs\Views\Doc;

class DocsApp
{
    /** @var App */
    private $app;

    public function __construct(array $settings)
    {
        $this->app = new App($settings);

        $this->routes();
        $this->dependencies();
        $this->middlewares();
    }

    private function routes()
    {
        $this->app->get('/', Doc::class . ':get')->setName('home');
        $this->app->get('/{version}/{language}/search', Search::class . ':get')->setName('search');
        $this->app->get('/{version}/{language}/{path:.*}', Doc::class . ':get')->setName('documentation');
    }

    private function middlewares()
    {
        $this->app->add(new RequestMiddleware());
    }

    private function dependencies()
    {
        $containers = [
            DB::class,
            View::class,
            ErrorHandlers::class,
            Logger::class,
            Services::class
        ];

        foreach ($containers as $container) {
            call_user_func([$container, 'load'], $this->app->getContainer());
        }
    }

    public function getContainer()
    {
        return $this->app->getContainer();
    }

    public function run()
    {
        $this->app->run();
    }

    public function process(Request $request, Response $response)
    {
        return $this->app->process($request, $response);
    }

}
