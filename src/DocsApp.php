<?php
namespace MODXDocs;

use Slim\App;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use MODXDocs\Containers\View;
use MODXDocs\Containers\PageNotFound;
use MODXDocs\Containers\Logger;
use MODXDocs\Middlewares\RequestMiddleware;
use MODXDocs\Views\Doc;


class DocsApp
{
    /** @var App */
    private $app;

    public function __construct(array $settings)
    {
        session_start();

        $this->app = new App($settings);
        $this->routes();
        $this->dependencies();
        $this->middlewares();
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


    public function run()
    {
        $this->app->run();
    }

    public function process(ServerRequestInterface $request, ResponseInterface $response)
    {
        return $this->app->process($request, $response);
    }

}
