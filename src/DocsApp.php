<?php
namespace MODXDocs;

use Slim\App;

use MODXDocs\Containers\View;
use MODXDocs\Containers\PageNotFound;
use MODXDocs\Containers\Logger;
use MODXDocs\Middlewares\RequestMiddleware;
use MODXDocs\Views\Doc;


class DocsApp
{
    private $app;

    public function __construct(array $settings)
    {
        session_start();

        $this->app = new App($settings);
    }

    public function run()
    {
        $this->routes();
        $this->dependencies();
        $this->middlewares();

        $this->app->run();
    }

    private function routes()
    {
        $this->app->get('/', Doc::class . ':home')->setName('home');
        $this->app->get('/{version}/{language}/{path:.*}',Doc::class . ':get')->setName('documentation');
    }

    private function middlewares()
    {
        $this->app->add(new RequestMiddleware());
    }

    private function dependencies()
    {
        $containers = [
            View::class,
            PageNotFound::class,
            Logger::class,
        ];

        foreach ($containers as $container) {
            call_user_func([$container, 'load'], $this->app->getContainer());
        }
    }
}