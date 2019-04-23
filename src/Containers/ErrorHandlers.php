<?php

namespace MODXDocs\Containers;

use MODXDocs\Views\Error;
use Slim\Container;

use MODXDocs\Views\NotFound;

class ErrorHandlers
{
    public static function load(Container $container)
    {
        $container['notFoundHandler'] = function ($container) {
            return function ($request, $response) use ($container) {
                $pageNotFound = new NotFound($container);

                return $pageNotFound->get($request, $response);
            };
        };
        $container['errorHandler'] = $container['phpErrorHandler'] = function ($container) {
            return function ($request, $response, $exception) use ($container) {
                $pageNotFound = new Error($container, $exception);

                return $pageNotFound->get($request, $response);
            };
        };
    }
}
