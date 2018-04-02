<?php
namespace MODXDocs\Helpers;

use League\CommonMark\ElementRendererInterface;
use League\CommonMark\HtmlElement;
use League\CommonMark\Inline\Element\AbstractInline;
use League\CommonMark\Inline\Element\Link;
use League\CommonMark\Inline\Renderer\InlineRendererInterface;


class LinkRenderer implements InlineRendererInterface
{
    protected $baseUri;
    protected $currentDoc;

    public function __construct($baseUri, $currentDoc)
    {
        $this->baseUri = $baseUri;
        $this->currentDoc = $currentDoc;
    }

    public function render(AbstractInline $inline, ElementRendererInterface $htmlRenderer)
    {
        if (!($inline instanceof Link)) {
            throw new \InvalidArgumentException('Incompatible inline type: ' . get_class($inline));
        }

        $attributes = [
            'href' => $this->getHref($inline->getUrl())
        ];

        if (isset($inline->attributes['title']) and strlen($inline->attributes['title']) > 0) {
            $attributes['title'] = $htmlRenderer->escape($inline->data['title'], true);
        }

        if (static::isExternalUrl($inline->getUrl())) {
            $attributes['class'] = 'external-link';
            $attributes['target'] = '_blank';
            $attributes['rel'] = 'noreferrer noopener';
        }

        return new HtmlElement('a', $attributes, $htmlRenderer->renderInlines($inline->children()));
    }

    private function getHref($url) {
        if (static::isExternalUrl($url)) {
            return $url;
        }

        if (strpos($url, '#') === 0) {
            return $this->baseUri . $this->currentDoc . $url;
        }

        return $this->baseUri . $url;
    }

    private static function isExternalUrl($url)
    {
        return preg_match('#^(?:[a-z]+:)?//|^mailto:#', $url);
    }
}