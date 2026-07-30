<?php

declare(strict_types=1);

namespace Tests\Unit;

use MODXDocs\Model\PageRequest;
use MODXDocs\Services\FilePathService;
use Tests\BaseTestCase;

class FilePathServiceTest extends BaseTestCase
{
    public function testGetFilePathRejectsTraversalOutsideDocsRoot(): void
    {
        $service = new FilePathService();
        $docsRoot = realpath($service->getDocsRoot());
        $this->assertNotFalse($docsRoot, 'DOCS_DIRECTORY must resolve');

        $innocentReadme = realpath($docsRoot . '/../README.md');
        $this->assertNotFalse($innocentReadme, 'Expected app README.md next to docs/');
        $this->assertStringContainsString(
            'DocsApp for MODX',
            (string) file_get_contents($innocentReadme)
        );

        $request = new PageRequest('2.x', 'en', '../../../README');
        $resolved = $service->getFilePath($request);

        $this->assertNull($resolved);
    }

    public function testGetFilePathResolvesMarkdownInsideDocsRoot(): void
    {
        $service = new FilePathService();
        $docsRoot = realpath($service->getDocsRoot());
        $this->assertNotFalse($docsRoot);

        $request = new PageRequest('2.x', 'en', 'getting-started');
        $resolved = $service->getFilePath($request);

        $this->assertNotNull($resolved);
        $resolvedReal = realpath($resolved);
        $this->assertNotFalse($resolvedReal);
        $this->assertStringStartsWith($docsRoot . DIRECTORY_SEPARATOR, $resolvedReal);
        $this->assertStringEndsWith('.md', $resolvedReal);
    }

    public function testGetStaticFilePathRejectsTraversalOutsideDocsRoot(): void
    {
        $service = new FilePathService();
        $request = new PageRequest('2.x', 'en', '../../../README.md');

        $this->assertNull($service->getStaticFilePath($request));
    }
}
