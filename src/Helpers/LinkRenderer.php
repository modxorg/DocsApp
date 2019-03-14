<?php
namespace MODXDocs\Helpers;

use League\CommonMark\ElementRendererInterface;
use League\CommonMark\HtmlElement;
use League\CommonMark\Inline\Element\AbstractInline;
use League\CommonMark\Inline\Element\Link;
use League\CommonMark\Inline\Renderer\InlineRendererInterface;
use MODXDocs\Views\NotFound;


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
            throw new \InvalidArgumentException('Incompatible inline type: ' . \get_class($inline));
        }

        $href = $this->getHref($inline->getUrl());
        $attributes = [
            'href' => $href,
        ];

        if (isset($inline->attributes['title']) && $inline->attributes['title'] !== '') {
            $attributes['title'] = $htmlRenderer->escape($inline->data['title'], true);
        }

        if (static::isExternalUrl($inline->getUrl())) {
            $attributes['class'] = 'link__external';
            $attributes['target'] = '_blank';
            $attributes['rel'] = 'noreferrer noopener';
        }
        else {
            // Check if the link points to somewhere valid
            $docs = getenv('DOCS_DIRECTORY');
            if (!file_exists($docs . $href . '.md') && !file_exists($docs . $href . '/index.md')) {
                $newUri = Redirector::findNewURI($href);
                if ($newUri === false) {
                    $attributes['class'] = 'link__broken';
                }
                else {
                    $attributes['href'] = $newUri;
                }
            }
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
