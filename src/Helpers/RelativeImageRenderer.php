<?php

namespace MODXDocs\Helpers;

use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\RegexHelper;
use League\Config\ConfigurationAwareInterface;
use League\Config\ConfigurationInterface;

class RelativeImageRenderer implements NodeRendererInterface, ConfigurationAwareInterface
{
    private $relativeFilePath;
    private $config;

    public function __construct($relativeFilePath)
    {
        $this->relativeFilePath = $relativeFilePath;
    }

    public function setConfiguration(ConfigurationInterface $configuration): void
    {
        $this->config = $configuration;
    }

    /**
     * @param Image $node
     * @param ChildNodeRendererInterface $childRenderer
     *
     * @return HtmlElement
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer)
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
        $attrs['src'] = $url;

        $alt = $childRenderer->renderNodes($node->children());
        $alt = preg_replace('/\<[^>]*alt="([^"]*)"[^>]*\>/', '$1', $alt);
        $attrs['alt'] = preg_replace('/\<[^>]*\>/', '', $alt);

        if ($title = $node->data->get('title', null)) {
            $attrs['title'] = $title;
        }

        return new HtmlElement('img', $attrs, '', true);
    }
}
