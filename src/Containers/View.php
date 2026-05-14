<?php

namespace MODXDocs\Containers;

use Psr\Container\ContainerInterface;
use Slim\Views\Twig;
use Twig\Extension\DebugExtension;

use MODXDocs\Twig\DocExtensions;

class View
{
    const BASE_REQUEST_HANDLER = 'index.php';

    public static function load(ContainerInterface $container)
    {
        $container['view'] = function (ContainerInterface $container) {
            $view = Twig::create(getenv('TEMPLATE_DIRECTORY'), [
                'cache' => getenv('DEV') === '1' ? false : getenv('CACHE_DIRECTORY') . '/twig',
                'debug' => true,
            ]);
            $view->addExtension(new DebugExtension());
            $view->addExtension(new DocExtensions($container->get('router')));

            return $view;
        };
    }
}
