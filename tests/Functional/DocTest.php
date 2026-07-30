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
}
