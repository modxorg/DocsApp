<?php

declare(strict_types=1);

namespace Tests\Functional;

use Tests\BaseTestCase;

class HomepageTest extends BaseTestCase
{
    public function testGetHomepageRedirectsToCurrentDocs(): void
    {
        $response = $this->runApp('GET', '/');

        $this->assertSame(301, $response->getStatusCode());
        $this->assertStringContainsString('/current/en/', $response->getHeaderLine('Location'));
    }

    public function testGetDocsHomepage(): void
    {
        $response = $this->runApp('GET', '/2.x/en/index');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Creative Freedom', (string) $response->getBody());
        $this->assertStringNotContainsString('Hello', (string) $response->getBody());
    }

    public function testPostHomepageNotAllowed(): void
    {
        $response = $this->runApp('POST', '/', ['test']);

        $this->assertSame(405, $response->getStatusCode());
    }
}
