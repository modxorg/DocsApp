<?php

declare(strict_types=1);

namespace Tests\Unit;

use MODXDocs\Services\FrontMatterValidator;
use Tests\BaseTestCase;

class FrontMatterValidatorTest extends BaseTestCase
{
    private string $tempDir;

    protected function set_up(): void
    {
        parent::set_up();

        $this->tempDir = sys_get_temp_dir() . '/modxdocs-frontmatter-' . uniqid('', true);
        mkdir($this->tempDir, 0777, true);
    }

    protected function tear_down(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tear_down();
    }

    public function testAcceptsValidFrontmatter(): void
    {
        $file = $this->writeMarkdown(
            $this->tempDir . '/page.md',
            "---\n" .
            "title: Nested tags\n" .
            "sortorder: 5\n" .
            "description: How MODX parses nested tags.\n" .
            "---\n\n" .
            "Body\n"
        );

        $this->assertSame([], (new FrontMatterValidator())->validateFile($file));
    }

    public function testReportsDuplicateDescription(): void
    {
        $file = $this->writeMarkdown(
            $this->tempDir . '/page.md',
            "---\n" .
            "title: Nested tags\n" .
            "description: First description.\n" .
            "sortorder: 5\n" .
            "description: Second description.\n" .
            "---\n\n" .
            "Body\n"
        );

        $messages = (new FrontMatterValidator())->validateFile($file);

        $this->assertCount(1, $messages);
        $this->assertStringContainsString('Duplicate key "description"', $messages[0]);
        $this->assertStringContainsString('line 5', $messages[0]);
    }

    public function testReportsInvalidYaml(): void
    {
        $file = $this->writeMarkdown(
            $this->tempDir . '/page.md',
            "---\n" .
            "title: Broken\n" .
            "description: \"unclosed quote\n" .
            "---\n\n" .
            "Body\n"
        );

        $messages = (new FrontMatterValidator())->validateFile($file);

        $this->assertNotSame([], $messages);
        $this->assertNotSame('', $messages[0]);
    }

    public function testSkipsFilesWithoutFrontmatter(): void
    {
        $file = $this->writeMarkdown($this->tempDir . '/page.md', "# Just a heading\n");

        $this->assertSame([], (new FrontMatterValidator())->validateFile($file));
    }

    public function testSkipsReadmeAndReportsOtherDuplicates(): void
    {
        file_put_contents(
            $this->tempDir . '/README.md',
            "---\ndescription: one\ndescription: two\n---\n"
        );
        $bad = $this->writeMarkdown(
            $this->tempDir . '/page.md',
            "---\ntitle: Page\ndescription: one\ndescription: two\n---\n"
        );

        $result = (new FrontMatterValidator($this->tempDir))->validate([]);

        $this->assertSame(1, $result['files']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame(realpath($bad), $result['errors'][0]['file']);
    }

    public function testAcceptsTranslationMatchingEnglishFile(): void
    {
        $this->writeMarkdown(
            $this->tempDir . '/en/getting-started/friendly-urls.md',
            "---\ntitle: Friendly URLs\n---\n"
        );
        $file = $this->writeMarkdown(
            $this->tempDir . '/ru/getting-started/friendly-urls.md',
            "---\n" .
            "title: Дружественные URL\n" .
            "translation: \"getting-started/friendly-urls\"\n" .
            "---\n"
        );

        $this->assertSame([], (new FrontMatterValidator())->validateFile($file));
    }

    public function testAcceptsTranslationMatchingEnglishIndexFile(): void
    {
        $this->writeMarkdown(
            $this->tempDir . '/en/extras/xfpc/index.md',
            "---\ntitle: XFPC\n---\n"
        );
        $file = $this->writeMarkdown(
            $this->tempDir . '/nl/extras/xfpc/index.md',
            "---\n" .
            "title: XFPC\n" .
            "translation: extras/xfpc\n" .
            "---\n"
        );

        $this->assertSame([], (new FrontMatterValidator())->validateFile($file));
    }

    public function testRejectsTranslationWithLanguagePrefix(): void
    {
        $this->writeMarkdown(
            $this->tempDir . '/en/getting-started/friendly-urls.md',
            "---\ntitle: Friendly URLs\n---\n"
        );
        $file = $this->writeMarkdown(
            $this->tempDir . '/ru/page.md',
            "---\n" .
            "title: Page\n" .
            "translation: \"en/getting-started/friendly-urls\"\n" .
            "---\n"
        );

        $messages = (new FrontMatterValidator())->validateFile($file);

        $this->assertNotSame([], $messages);
        $this->assertStringContainsString('must not include a version or language prefix', $messages[0]);
    }

    public function testRejectsTranslationWithMdExtension(): void
    {
        $this->writeMarkdown(
            $this->tempDir . '/en/getting-started/friendly-urls.md',
            "---\ntitle: Friendly URLs\n---\n"
        );
        $file = $this->writeMarkdown(
            $this->tempDir . '/ru/page.md',
            "---\n" .
            "title: Page\n" .
            "translation: getting-started/friendly-urls.md\n" .
            "---\n"
        );

        $messages = (new FrontMatterValidator())->validateFile($file);

        $this->assertNotSame([], $messages);
        $this->assertStringContainsString('must not include a .md extension', $messages[0]);
    }

    public function testRejectsTranslationMissingEnglishFile(): void
    {
        $file = $this->writeMarkdown(
            $this->tempDir . '/ru/page.md',
            "---\n" .
            "title: Page\n" .
            "translation: \"getting-started/does-not-exist\"\n" .
            "---\n"
        );

        $messages = (new FrontMatterValidator())->validateFile($file);

        $this->assertCount(1, $messages);
        $this->assertStringContainsString('does not match an English file', $messages[0]);
        $this->assertStringContainsString('en/getting-started/does-not-exist.md', $messages[0]);
    }

    public function testRejectsEmptyTranslation(): void
    {
        $file = $this->writeMarkdown(
            $this->tempDir . '/ru/page.md',
            "---\n" .
            "title: Page\n" .
            "translation: \"\"\n" .
            "---\n"
        );

        $messages = (new FrontMatterValidator())->validateFile($file);

        $this->assertSame(['translation is empty'], $messages);
    }

    public function testScansRelativePathInsideDocs(): void
    {
        $this->writeMarkdown(
            $this->tempDir . '/3.x/en/page.md',
            "---\ntitle: Page\ndescription: one\ndescription: two\n---\n"
        );
        $this->writeMarkdown(
            $this->tempDir . '/2.x/en/page.md',
            "---\ntitle: Other\ndescription: one\ndescription: two\n---\n"
        );

        $result = (new FrontMatterValidator($this->tempDir))->validate(['3.x/en/']);

        $this->assertSame(1, $result['files']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('3.x/en/page.md', $result['errors'][0]['file']);
    }

    public function testAcceptsDocsPrefixedRelativePath(): void
    {
        $this->writeMarkdown(
            $this->tempDir . '/3.x/page.md',
            "---\ntitle: Page\n---\n"
        );

        $result = (new FrontMatterValidator($this->tempDir))->validate(['docs/3.x']);

        $this->assertSame(1, $result['files']);
        $this->assertSame([], $result['errors']);
    }

    public function testRejectsAbsolutePath(): void
    {
        $result = (new FrontMatterValidator($this->tempDir))->validate([$this->tempDir]);

        $this->assertSame(0, $result['files']);
        $this->assertSame('Path must be inside the docs directory', $result['errors'][0]['message']);
    }

    public function testRejectsParentPath(): void
    {
        $result = (new FrontMatterValidator($this->tempDir))->validate(['../']);

        $this->assertSame(0, $result['files']);
        $this->assertSame('Path must be inside the docs directory', $result['errors'][0]['message']);
    }

    public function testRejectsMissingRelativePath(): void
    {
        $result = (new FrontMatterValidator($this->tempDir))->validate(['3.x/missing']);

        $this->assertSame(0, $result['files']);
        $this->assertSame('Path is not a directory', $result['errors'][0]['message']);
        $this->assertSame('3.x/missing', $result['errors'][0]['file']);
    }

    private function writeMarkdown(string $path, string $contents): string
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, $contents);
        return $path;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
