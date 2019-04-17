<?php

namespace MODXDocs\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Slim\Http\Request;
use Slim\Interfaces\RouterInterface;

class DocExtensions extends AbstractExtension
{
    private $router;
    private $request;

    public function __construct(RouterInterface $router, Request $request)
    {
        $this->router = $router;
        $this->request = $request;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('base_href', [$this, 'getBaseHref']),
            new TwigFunction('icon', [$this, 'getInlineSvg'], ['is_safe' => ['html']]),
        ];
    }

    public function getBaseHref()
    {
        $scheme = getenv('SSL') === '1' ? 'https' : 'http';
        $uri = $this->request->getUri();
        $port = \in_array($uri->getPort(), [80, 443, null], true) ? '' : (':' . $uri->getPort());

        return $scheme . '://' . $uri->getHost() . $port . '/';
    }

    public static function getInlineSvg($name, $title = '', $classes = '') {
        return '<svg role="presentation" class="c-icon c-icon--'.$name.' '.$classes.'" title="'.$title.'"><use xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="/template/dist/sprite.svg#'.$name.'" href="/template/dist/sprite.svg#'.$name.'"></use></svg>';
    }
}
