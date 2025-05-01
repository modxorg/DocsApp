<?php

namespace MODXDocs\Helpers;

use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use MODXDocs\Exceptions\RedirectNotFoundException;
use MODXDocs\Services\VersionsService;

class LinkRenderer implements NodeRendererInterface
{
    protected string $baseUri;
    protected string $currentDoc;

    public function __construct(string $baseUri, string $currentDoc)
    {
        $this->baseUri = $baseUri;
        $this->currentDoc = $currentDoc;
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement
    {
        if (!($node instanceof Link)) {
            throw new \InvalidArgumentException('Incompatible node type: ' . \get_class($node));
        }

        $href = $this->getHref($node->getUrl());
        $attributes = $node->data->get('attributes', []);
        $attributes['href'] = $href;

        // Handle hashes in links
        $hash = '';
        $hashPosition = strpos($href, '#');
        if ($hashPosition !== false) {
            $hash = substr($href, $hashPosition);
            $href = substr($href, 0, $hashPosition);
        }

        if (($title = $node->data->get('title', null)) !== null && $title !== '') {
            $attributes['title'] = $title;
        }

        if (static::isExternalUrl($node->getUrl())) {
            $attributes['class'] = 'is-externallink';
            $attributes['target'] = '_blank';
            $attributes['rel'] = 'noreferrer noopener';
        } else {
            // Check if the link points to somewhere valid
            $docs = $_ENV['DOCS_DIRECTORY'];
            $href = static::replaceCurrentUrl($href);
            if (!file_exists($docs . $href . '.md') && !file_exists($docs . $href . '/index.md')) {
                try {
                    $newUri = Redirector::findNewURI($href);
                    $attributes['href'] = $newUri . $hash;
                } catch (RedirectNotFoundException $e) {
                    $attributes['class'] = 'is-brokenlink';
                }
            }
        }

        return new HtmlElement('a', $attributes, $childRenderer->renderNodes($node->children()));
    }

    private function getHref($url): string
    {
        if (static::isExternalUrl($url)) {
            return $url;
        }

        if (substr($url, -3) === '.md') {
            $url = substr($url, 0, -3);
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

    private static function replaceCurrentUrl($href): string
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
