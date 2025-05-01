<?php

namespace MODXDocs\Containers;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\RouteParserInterface;
use Slim\Views\Twig;
use Twig\Extension\DebugExtension;

use MODXDocs\Twig\DocExtensions;

class View
{
    const BASE_REQUEST_HANDLER = 'index.php';

    public static function load(ContainerInterface $container)
    {
        $container->set('view', function (ContainerInterface $container) {
            $request = $container->get(ServerRequestInterface::class);
            $router = $container->get(RouteParserInterface::class);

            $templateDir = $_ENV['TEMPLATE_DIRECTORY'] ?? null;
            if ($templateDir === null) {
                throw new \RuntimeException('TEMPLATE_DIRECTORY environment variable is not set');
            }
            // Remove quotes if present
            $templateDir = trim($templateDir, '"\'');
            
            $view = Twig::create($templateDir, [
                'cache' => $_ENV['DEV'] === '1' ? false : $_ENV['CACHE_DIRECTORY'] . '/twig',
                'debug' => true,
            ]);
            $view->addExtension(new DebugExtension());

            // Add Slim specific extension
            $basePath = rtrim(str_ireplace(static::BASE_REQUEST_HANDLER, '', $request->getUri()->getPath()), '/');
            $view->addExtension(new DocExtensions($router, $request));

            return $view;
        });
    }
}
