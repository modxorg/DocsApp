<?php

namespace MODXDocs\Helpers;

use League\CommonMark\ElementRendererInterface;
use League\CommonMark\HtmlElement;
use League\CommonMark\Inline\Element\AbstractInline;
use League\CommonMark\Inline\Element\Link;
use League\CommonMark\Inline\Renderer\InlineRendererInterface;

use MODXDocs\Exceptions\RedirectNotFoundException;
use MODXDocs\Services\VersionsService;

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
        } else {
            // Check if the link points to somewhere valid
            $docs = getenv('DOCS_DIRECTORY');
            $href = static::replaceCurrentUrl($href);
            if (!file_exists($docs . $href . '.md') && !file_exists($docs . $href . '/index.md')) {
                try {
                    $newUri = Redirector::findNewURI($href);
                    $attributes['href'] = $newUri;
                } catch (RedirectNotFoundException $e) {
                    $attributes['class'] = 'link__broken';
                }
            }
        }

        return new HtmlElement('a', $attributes, $htmlRenderer->renderInlines($inline->children()));
    }

    private function getHref($url)
    {
        if (static::isExternalUrl($url)) {
            return $url;
        }

        if (substr($url, -3) === '.md') {
            $url = substr($url,0,-3);
        }

        if (strpos($url, '#') === 0) {
            return $this->baseUri . $this->currentDoc . $url;
        }

        $versions = array_keys(VersionsService::getAvailableVersions());
        $temp = ltrim($url, '/');
        foreach ($versions as $key) {
            if (strpos($temp, $key) === 0) {
                return '/' . $temp;
            }
        }

        return $this->baseUri . ltrim($url, '/');
    }

    private static function replaceCurrentUrl($href)
    {
        $href = ltrim($href, '/');
        // If the URL starts with `current/`, then replace it with the actual branch name
        if (strpos($href, VersionsService::getCurrentVersion()) !== 0) {
            return $href;
        }

        return VersionsService::getCurrentVersionBranch() . substr($href, strlen(VersionsService::getCurrentVersion()));
    }

    private static function isExternalUrl($url)
    {
        return preg_match('#^(?:[a-z]+:)?//|^mailto:#', $url);
    }
}
