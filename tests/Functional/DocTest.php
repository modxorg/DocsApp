<?php

declare(strict_types=1);

namespace Tests\Functional;

use Tests\BaseTestCase;

class DocTest extends BaseTestCase
{
    public function testGetGettingStarted(): void
    {
        $response = $this->runApp('GET', '/2.x/en/getting-started');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Getting Started', (string) $response->getBody());
        $this->assertStringNotContainsString('WordPress', (string) $response->getBody());
    }

    public function testServesImagesFromDocsTree(): void
    {
        $response = $this->runApp(
            'GET',
            '/3.x/en/getting-started/installation/setup-opt1.png'
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('image/', $response->getHeaderLine('Content-Type'));
        $this->assertNotSame('', (string) $response->getBody());
    }

    public function testPathTraversalDoesNotServeAppReadmeOutsideDocsTree(): void
    {
        $response = $this->runApp('GET', '/2.x/en/../../../README');

        $this->assertSame(404, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringNotContainsString('DocsApp for MODX', $body);
        $this->assertStringNotContainsString('Slim application that serves up', $body);
    }

    public function testDoubleEncodedPathTraversalDoesNotServeAppReadme(): void
    {
        $response = $this->runApp(
            'GET',
            '/2.x/en/%252e%252e/%252e%252e/%252e%252e/README'
        );

        $this->assertSame(404, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringNotContainsString('DocsApp for MODX', $body);
        $this->assertStringNotContainsString('Slim application that serves up', $body);
    }
}
