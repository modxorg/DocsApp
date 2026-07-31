<?php

declare(strict_types=1);

namespace Tests\Unit;

use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use MODXDocs\Helpers\RelativeImageRenderer;
use Tests\BaseTestCase;

class RelativeImageRendererTest extends BaseTestCase
{
    public function testRendersMp4AsLocalVideoEmbed(): void
    {
        $renderer = new RelativeImageRenderer('3.x/en/extras/fred/themer/themes.md');
        $html = (string) $renderer->render(
            new Image('basic-use.mp4', 'Basic use demo'),
            $this->childRenderer('Basic use demo')
        );

        $this->assertStringContainsString('class="video-embed video-embed--local"', $html);
        $this->assertStringContainsString('<video controls', $html);
        $this->assertStringContainsString('src="/3.x/en/extras/fred/themer/basic-use.mp4"', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function testRendersPngAsImage(): void
    {
        $renderer = new RelativeImageRenderer('3.x/en/extras/fred/themer/themes.md');
        $html = (string) $renderer->render(
            new Image('shot.png', 'Screenshot'),
            $this->childRenderer('Screenshot')
        );

        $this->assertStringContainsString('<img src="/3.x/en/extras/fred/themer/shot.png"', $html);
        $this->assertStringNotContainsString('<video', $html);
    }

    private function childRenderer(string $alt): ChildNodeRendererInterface
    {
        return new class ($alt) implements ChildNodeRendererInterface {
            public function __construct(private string $alt)
            {
            }

            public function renderNodes(iterable $nodes): string
            {
                return $this->alt;
            }

            public function getInnerSeparator(): string
            {
                return '';
            }

            public function getBlockSeparator(): string
            {
                return '';
            }
        };
    }
}
