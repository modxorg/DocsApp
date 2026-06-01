<?php
declare(strict_types=1);

namespace Tests\Functional;

use Tests\BaseTestCase;

class DocTest extends BaseTestCase
{
    public function testGetGettingStarted() : void
    {
        $response = $this->runApp('GET', '/2.x/en/getting-started');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Welcome to MODX Revolution', (string)$response->getBody());
        $this->assertStringNotContainsString('WordPress', (string)$response->getBody());
    }
}
