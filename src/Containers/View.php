<?php

namespace MODXDocs\Containers;

use Psr\Container\ContainerInterface;
use Slim\Views\Twig;
use Slim\Views\TwigExtension;
use Twig\Extension\DebugExtension;

use MODXDocs\Twig\DocExtensions;

class View
{
    const BASE_REQUEST_HANDLER = 'index.php';

    public static function load(ContainerInterface $container)
    {
        $container['view'] = function (ContainerInterface $container) {
            $request = $container->get('request');
            $router = $container->get('router');

            $view = new Twig(getenv('TEMPLATE_DIRECTORY'), [
                'cache' => getenv('DEV') === '1' ? false : getenv('CACHE_DIRECTORY') . '/twig',
                'debug' => true,
            ]);
            $view->addExtension(new DebugExtension());


            // Instantiate and add Slim specific extension
            $basePath = rtrim(str_ireplace(static::BASE_REQUEST_HANDLER, '', $request->getUri()->getBasePath()), '/');
            $view->addExtension(new TwigExtension($router, $basePath));
            $view->addExtension(new DocExtensions($router, $request));

            return $view;
        };
    }
}
