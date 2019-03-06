<?php
namespace MODXDocs\Containers;

use Slim\Container;

use MODXDocs\Views\NotFound;


class PageNotFound
{
    public static function load(Container $container)
    {
        $container['notFoundHandler'] = function ($container) {
            return function ($request, $response) use ($container) {
                $pageNotFound = new NotFound($container);

                return $pageNotFound->get($request, $response);
            };
        };
    }
}