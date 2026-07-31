<?php

namespace MODXDocs\Containers;

use MODXDocs\Views\Error;
use Psr\Container\ContainerInterface;
use Slim\App;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use MODXDocs\Views\NotFound;
use Throwable;
use Slim\Psr7\Response;

class ErrorHandlers
{
    public static function load(ContainerInterface $container): void
    {
        $app = $container->get(App::class);

        // Add Error Middleware
        $errorMiddleware = $app->addErrorMiddleware(
            $_ENV['DEV'] === '1', // displayErrorDetails
            true, // logErrors
            true, // logErrorDetails
            $container->get('logger')
        );

        // Set the Not Found Handler
        $errorMiddleware->setErrorHandler(
            HttpNotFoundException::class,
            function (ServerRequestInterface $request, Throwable $exception, bool $displayErrorDetails) use ($container): ResponseInterface {
                $pageNotFound = new NotFound($container);
                return $pageNotFound->get($request, new Response());
            }
        );

        // Set the Error Handler
        $errorMiddleware->setErrorHandler(
            Throwable::class,
            function (ServerRequestInterface $request, Throwable $exception, bool $displayErrorDetails) use ($container): ResponseInterface {
                $error = new Error($container, $exception);
                return $error->get($request, new Response());
            }
        );
    }
}
