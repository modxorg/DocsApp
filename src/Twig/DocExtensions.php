<?php

namespace MODXDocs\Twig;

use MODXDocs\Views\Base;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\RouteParserInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DocExtensions extends AbstractExtension
{
    /** @var RouteParserInterface */
    private $router;
    /** @var ServerRequestInterface|null */
    private static $request;

    public function __construct(RouteParserInterface $router)
    {
        $this->router = $router;
    }

    public static function setRequest(ServerRequestInterface $request): void
    {
        static::$request = $request;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('path_for', [$this, 'pathFor']),
            new TwigFunction('base_href', [$this, 'getBaseHref']),
            new TwigFunction('icon', [$this, 'getInlineSvg'], ['is_safe' => ['html']]),
        ];
    }

    public function pathFor(string $routeName, array $data = [], array $queryParams = []): string
    {
        return $this->router->urlFor($routeName, $data, $queryParams);
    }

    public function getBaseHref()
    {
        $scheme = getenv('SSL') === '1' ? 'https' : 'http';
        if (!static::$request) {
            return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/';
        }

        $uri = static::$request->getUri();
        $port = \in_array($uri->getPort(), [80, 443, null], true) ? '' : (':' . $uri->getPort());

        return $scheme . '://' . $uri->getHost() . $port . '/';
    }

    public static function getInlineSvg($name, $title = '', $classes = '', $role = 'presentation', $attributes = '') {
        $rev = Base::getRevision();
        $url = '/template/dist/sprite.svg?v=' . $rev . '#' . $name;

        return '<svg role="'.$role.'" class="c-icon c-icon--'.$name.' '.$classes.'" title="'.$title.'" '.$attributes.'><title>'.$title.'</title><use xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="' . $url . '" href="' . $url . '"></use></svg>';
    }
}
