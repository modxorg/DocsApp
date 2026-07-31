<?php

declare(strict_types=1);

namespace Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\BaseTestCase;

class NoiseRequestTest extends BaseTestCase
{
    #[DataProvider('noisePathsProvider')]
    public function testNoisePathsReturnBare404(string $path): void
    {
        $response = $this->runApp('GET', 'http://localhost' . $path);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
    }

    public static function noisePathsProvider(): array
    {
        return [
            'sbbi' => ['/sbbi'],
            'wp-login' => ['/wp-login.php'],
            'xmlrpc' => ['/xmlrpc.php'],
            'double-slash xmlrpc' => ['//xmlrpc.php'],
            'wlwmanifest nested' => ['//blog/wp-includes/wlwmanifest.xml'],
            'env' => ['/.env'],
            'manager probe' => ['/manager/html'],
            'login probe' => ['/login'],
            'confluence viewinfo' => ['/pages/viewinfo.action'],
            'confluence viewpage' => ['/pages/viewpage.action'],
            'wp-admin ajax' => ['/wp-admin/admin-ajax.php'],
        ];
    }

    public function testLegitimateDocsStillWork(): void
    {
        $response = $this->runApp('GET', '/2.x/en/getting-started');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotSame('', (string) $response->getBody());
    }
}
