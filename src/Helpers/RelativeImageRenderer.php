<?php

namespace MODXDocs\Helpers;

use League\CommonMark\ElementRendererInterface;
use League\CommonMark\HtmlElement;
use League\CommonMark\Inline\Element\AbstractInline;
use League\CommonMark\Inline\Element\Image;
use League\CommonMark\Inline\Renderer\InlineRendererInterface;
use League\CommonMark\Util\RegexHelper;
use League\CommonMark\Util\Xml;

class RelativeImageRenderer implements InlineRendererInterface
{
    private $relativeFilePath;

    public function __construct($relativeFilePath)
    {
        $this->relativeFilePath = $relativeFilePath;
    }

    /**
     * @param Image                    $inline
     * @param ElementRendererInterface $htmlRenderer
     *
     * @return HtmlElement
     */
    public function render(AbstractInline $inline, ElementRendererInterface $htmlRenderer)
    {
        if (!($inline instanceof Image)) {
            throw new \InvalidArgumentException('Incompatible inline type: ' . get_class($inline));
        }

        $attrs = [];
        foreach ($inline->getData('attributes', []) as $key => $value) {
            $attrs[$key] = Xml::escape($value, true);
        }

        $url = $inline->getUrl();

        $path = '/' . dirname($this->relativeFilePath) . '/';
        $imageIsRelative = strpos($url, '/') !== 0 && strpos($url, 'http') !== 0;
        if ($imageIsRelative) {
            $url = $path . $url;
        }

        if (RegexHelper::isLinkPotentiallyUnsafe($url)) {
            $url = '';
        }
        $attrs['src'] = Xml::escape($url, true);

        $alt = $htmlRenderer->renderInlines($inline->children());
        $alt = preg_replace('/\<[^>]*alt="([^"]*)"[^>]*\>/', '$1', $alt);
        $attrs['alt'] = preg_replace('/\<[^>]*\>/', '', $alt);

        if (isset($inline->data['title'])) {
            $attrs['title'] = Xml::escape($inline->data['title'], true);
        }

        return new HtmlElement('img', $attrs, '', true);
    }

}
