<?php

declare(strict_types=1);

namespace Tests;

use MODXDocs\DocsApp;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class BaseTestCase extends TestCase
{
    private static DocsApp $app;

    public static function setApp(DocsApp $app): void
    {
        self::$app = $app;
    }

    /**
     * Process the application given a request method and URI.
     *
     * @param array|object|null $requestData
     */
    public function runApp(string $requestMethod, string $requestUri, $requestData = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($requestMethod, $requestUri);

        if ($requestData !== null) {
            $stream = (new StreamFactory())->createStream(http_build_query((array) $requestData));
            $request = $request
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withBody($stream)
                ->withParsedBody($requestData);
        }

        return self::$app->getApp()->handle($request);
    }
}
