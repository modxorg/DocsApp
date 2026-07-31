<?php

namespace MODXDocs\Middlewares;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class RequestMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $uri = $request->getUri();
        $path = $uri->getPath();

        if ($path !== '/' && substr($path, -1) === '/') {
            // permanently redirect paths with a trailing slash
            // to their non-trailing counterpart
            $uri = $uri->withPath(substr($path, 0, -1));

            if ($request->getMethod() === 'GET') {
                $response = new \Slim\Psr7\Response();
                return $response->withHeader('Location', (string)$uri)->withStatus(301);
            }

            return $handler->handle($request->withUri($uri));
        }

        return $handler->handle($request);
    }
}
