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
        ];
    }

    public function getBaseHref()
    {
        $scheme = getenv('SSL') === '1' ? 'https' : 'http';
        $uri = $this->request->getUri();
        $port = \in_array($uri->getPort(), [80, 443, null], true) ? '' : (':' . $uri->getPort());

        return $scheme . '://' . $uri->getHost() . $port;
    }
}
