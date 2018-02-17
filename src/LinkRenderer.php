<?php
namespace MODXDocs\Helpers;
use League\CommonMark\ElementRendererInterface;
use League\CommonMark\HtmlElement;
use League\CommonMark\Inline\Element\AbstractInline;
use League\CommonMark\Inline\Element\Link;
use League\CommonMark\Inline\Renderer\InlineRendererInterface;

class LinkRenderer implements InlineRendererInterface
{
    private $host;

    public function __construct($baseUri)
    {
        $this->baseUri = $baseUri;
    }

    public function render(AbstractInline $inline, ElementRendererInterface $htmlRenderer)
    {
        if (!($inline instanceof Link)) {
            throw new \InvalidArgumentException('Incompatible inline type: ' . get_class($inline));
        }

        $attrs = array();

        if (isset($inline->attributes['title'])) {
            $attrs['title'] = $htmlRenderer->escape($inline->data['title'], true);
        }

        $url = $inline->getUrl();
        if ($this->isExternalUrl($url)) {
            $attrs['class'] = 'external-link';
            $attrs['target'] = '_blank';
            $attrs['rel'] = 'noreferrer noopener';
        }
        else {
            if (strpos($url, '/') !== 0) {
                $url = $this->baseUri . $url;
            }
        }
        $attrs['href'] = $url;

        return new HtmlElement('a', $attrs, $htmlRenderer->renderInlines($inline->children()));
    }

    private function isExternalUrl($url)
    {
        return preg_match('#^(?:[a-z]+:)?//|^mailto:#', $url);
    }
}