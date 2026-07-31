<?php

namespace MODXDocs\Middlewares;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Short-circuit known scanner / bot noise with a bare 404 so it never
 * hits routing, the fancy not-found page, or PageNotFound logging.
 */
class NoiseRequestMiddleware implements MiddlewareInterface
{
    /** @var array<string, true> */
    private const EXACT = [
        '/sbbi' => true,
        '/login' => true,
        '/.env' => true,
        '/manager/html' => true,
        '/pages/viewinfo.action' => true,
        '/pages/viewpage.action' => true,
        '/wp-login.php' => true,
        '/xmlrpc.php' => true,
        '/wp-report.php' => true,
        '/wp-craft-report-conf.php' => true,
        '/wp-craft-report-conf.php.suspected' => true,
    ];

    private const PREFIXES = [
        '/sbbi/',
        '/wp-',
        '/wp/',
        '/wordpress/',
        '/wp-admin',
    ];

    public function process(Request $request, RequestHandler $handler): Response
    {
        $path = $this->normalizePath($request->getUri()->getPath());

        if ($this->shouldIgnore($path)) {
            return (new SlimResponse())->withStatus(404);
        }

        return $handler->handle($request);
    }

    private function normalizePath(string $path): string
    {
        $normalized = preg_replace('#/+#', '/', $path) ?? $path;

        return strtolower($normalized);
    }

    private function shouldIgnore(string $path): bool
    {
        if (isset(self::EXACT[$path])) {
            return true;
        }

        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        // Nested probes like /blog/wp-includes/wlwmanifest.xml or //site/xmlrpc.php
        return str_contains($path, '/wp-admin')
            || str_contains($path, '/wp-includes/')
            || str_contains($path, '/wp-content/')
            || str_ends_with($path, '/xmlrpc.php')
            || str_ends_with($path, '/wp-login.php')
            || str_ends_with($path, 'wlwmanifest.xml');
    }
}
