<?php

namespace MODXDocs\Helpers;

use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\RegexHelper;

class RelativeImageRenderer implements NodeRendererInterface
{
    private const VIDEO_EXTENSIONS = ['mp4', 'webm', 'ogg'];

    private string $relativeFilePath;

    public function __construct(string $relativeFilePath)
    {
        $this->relativeFilePath = $relativeFilePath;
    }

    /**
     * @param Image $node
     * @param ChildNodeRendererInterface $childRenderer
     *
     * @return HtmlElement
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement
    {
        if (!($node instanceof Image)) {
            throw new \InvalidArgumentException('Incompatible node type: ' . get_class($node));
        }

        $attrs = $node->data->get('attributes', []);

        $url = $node->getUrl();

        $path = '/' . dirname($this->relativeFilePath) . '/';
        $imageIsRelative = strpos($url, '/') !== 0 && strpos($url, 'http') !== 0;
        if ($imageIsRelative) {
            $url = $path . $url;
        }

        if (RegexHelper::isLinkPotentiallyUnsafe($url)) {
            $url = '';
        }

        $alt = $childRenderer->renderNodes($node->children());
        $alt = preg_replace('/\<[^>]*alt="([^"]*)"[^>]*\>/', '$1', $alt);
        $alt = preg_replace('/\<[^>]*\>/', '', $alt);

        $title = $node->getTitle();

        if ($this->isVideoUrl($url)) {
            return $this->renderVideo($url, $alt, $title);
        }

        $attrs['src'] = $url;
        $attrs['alt'] = $alt;

        if ($title !== null) {
            $attrs['title'] = $title;
        }

        return new HtmlElement('img', $attrs, '', true);
    }

    private function isVideoUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return false;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, self::VIDEO_EXTENSIONS, true);
    }

    private function renderVideo(string $url, string $alt, ?string $title): HtmlElement
    {
        $videoAttrs = [
            'controls' => true,
            'preload' => 'metadata',
            'src' => $url,
        ];

        if ($title !== null && $title !== '') {
            $videoAttrs['title'] = $title;
        } elseif ($alt !== '') {
            $videoAttrs['title'] = $alt;
        }

        $fallback = \htmlspecialchars($alt !== '' ? $alt : 'Video', \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
        $video = new HtmlElement('video', $videoAttrs, $fallback);

        return new HtmlElement('div', ['class' => 'video-embed video-embed--local'], $video);
    }
}
