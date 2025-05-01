<?php

namespace MODXDocs\Twig;

use MODXDocs\Views\Base;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Psr\Http\Message\ServerRequestInterface as Request;

class DocExtensions extends AbstractExtension
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('base_href', [$this, 'getBaseHref']),
            new TwigFunction('icon', [$this, 'getInlineSvg'], ['is_safe' => ['html']]),
        ];
    }

    public function getBaseHref(): string
    {
        $scheme = $_ENV['SSL'] === '1' ? 'https' : 'http';
        $uri = $this->request->getUri();
        $port = \in_array($uri->getPort(), [80, 443, null], true) ? '' : (':' . $uri->getPort());

        return $scheme . '://' . $uri->getHost() . $port . '/';
    }

    public function getBaseUrl(): string
    {
        $scheme = $_ENV['SSL'] === '1' ? 'https' : 'http';
        return $scheme . '://' . $this->request->getUri()->getHost();
    }

    public static function getInlineSvg($name, $title = '', $classes = '', $role = 'presentation', $attributes = ''): string
    {
        $rev = Base::getRevision();
        $url = '/template/dist/sprite.svg?v=' . $rev . '#' . $name;

        return '<svg role="' . $role . '" class="c-icon c-icon--' . $name . ' ' . $classes . '" title="' . $title . '" ' . $attributes . '><title>' . $title . '</title><use xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="' . $url . '" href="' . $url . '"></use></svg>';
    }
}
