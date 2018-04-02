<?php
namespace MODXDocs\Containers;

use Slim\Container;
use Slim\Views\Twig;
use Slim\Views\TwigExtension;


class View
{
    const BASE_REQUEST_HANDLER = 'index.php';

    public static function load(Container $container)
    {
        $container['view'] = function ($container) {
            $view = new Twig($container->get('settings')['template_dir'], [
                'cache' => $container->get('settings')['cache_dir'],
                'debug' => true,
            ]);
            $view->addExtension(new \Twig_Extension_Debug());


            // Instantiate and add Slim specific extension
            $basePath = rtrim(str_ireplace(static::BASE_REQUEST_HANDLER, '', $container['request']->getUri()->getBasePath()), '/');
            $view->addExtension(new TwigExtension($container['router'], $basePath));

            return $view;
        };
    }
}