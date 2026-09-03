<?php

namespace Tests\Unit;

use MODXDocs\Helpers\AlertCalloutFixer;
use PHPUnit\Framework\TestCase;

class AlertCalloutFixerTest extends TestCase
{
    /** @var AlertCalloutFixer */
    private $fixer;

    protected function setUp(): void
    {
        $this->fixer = new AlertCalloutFixer();
    }

    public function testConvertsNoteAlert(): void
    {
        $html = <<<'HTML'
<blockquote>
<p>[!NOTE]
Hello <strong>bold</strong>.</p>
</blockquote>
<p>After</p>
HTML;

        $out = $this->fixer->fix($html);

        $this->assertStringContainsString('c-callout c-callout--info', $out);
        $this->assertStringContainsString('c-callout__title', $out);
        $this->assertStringContainsString('Note', $out);
        $this->assertStringContainsString('<strong>bold</strong>', $out);
        $this->assertStringNotContainsString('[!NOTE]', $out);
        $this->assertStringNotContainsString('<blockquote>', $out);
        $this->assertStringContainsString('<p>After</p>', $out);
    }

    public function testAcceptsSpacedMarkerFromIssue40(): void
    {
        $html = <<<'HTML'
<blockquote>
<p>[! WARNING]
Careful.</p>
</blockquote>
HTML;

        $out = $this->fixer->fix($html);

        $this->assertStringContainsString('c-callout--alert', $out);
        $this->assertStringContainsString('Warning', $out);
        $this->assertStringContainsString('Careful.', $out);
        $this->assertStringNotContainsString('[! WARNING]', $out);
    }

    public function testTipWithSeparateMarkerParagraph(): void
    {
        $html = <<<'HTML'
<blockquote>
<p>[!TIP]</p>
<p>Multi
line tip.</p>
</blockquote>
HTML;

        $out = $this->fixer->fix($html);

        $this->assertStringContainsString('c-callout--success', $out);
        $this->assertStringContainsString('Tip', $out);
        $this->assertStringContainsString('Multi', $out);
        $this->assertStringNotContainsString('[!TIP]', $out);
    }

    public function testLeavesOrdinaryBlockquotesAlone(): void
    {
        $html = '<blockquote><p>Just a quote</p></blockquote>';
        $this->assertSame($html, $this->fixer->fix($html));
    }

    public function testMapsImportantToWarningCallout(): void
    {
        $html = '<blockquote><p>[!IMPORTANT] Read this.</p></blockquote>';
        $out = $this->fixer->fix($html);
        $this->assertStringContainsString('c-callout--warning', $out);
        $this->assertStringContainsString('Important', $out);
    }
}
