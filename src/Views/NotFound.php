<?php

namespace MODXDocs\Views;

use MODXDocs\Navigation\NavigationItemBuilder;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

use MODXDocs\Exceptions\RedirectNotFoundException;
use MODXDocs\Helpers\Redirector;
use MODXDocs\Services\NavigationService;
use MODXDocs\Services\NotFoundService;

class NotFound extends Base
{
    const MARKDOWN_SUFFIX = '.md';

    /** @var NotFoundService */
    private $notFoundService;

    /** @var NavigationService */
    private $navigationService;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->notFoundService = $container->get(NotFoundService::class);
        $this->navigationService = $container->get(NavigationService::class);
    }

    public function get(Request $request, Response $response)
    {
        $currentUri = $request->getUri()->getPath();

        // Make sure links ending in .md get redirected
        if (substr($currentUri, -strlen(static::MARKDOWN_SUFFIX)) === static::MARKDOWN_SUFFIX) {
            $uri = substr($currentUri, 0, -strlen(static::MARKDOWN_SUFFIX));
            return $response->withRedirect($uri, 301);
        }

        try {
            $redirectUri = Redirector::findNewURI($currentUri);

            return $response->withRedirect($redirectUri, 301);
        } catch (RedirectNotFoundException $e) {

            $version = $this->notFoundService->getVersion($request);
            $versionBranch = $this->notFoundService->getVersionBranch($request);
            $language = $this->notFoundService->getLanguage($request);

            $basePath = getenv('DOCS_DIRECTORY') . $versionBranch . '/' . $language;
            $urlPath = $version . '/' . $language;

            return $this->render404($request, $response, [
                'req_url' => urlencode($currentUri),
                'page_title' => 'Oops, page not found.',

                'version' => $version,
                'version_branch' => $versionBranch,
                'language' => $language,

                // We always disregard the path here, because we know the request is always invalid
                'path' => null,

                'topnav' => $this->navigationService->getTopNavigationForItem(
                    (new NavigationItemBuilder())
                        ->forTopMenu()
                        ->withCurrentFilePath(null)
                        ->withBasePath($basePath)
                        ->withFilePath($basePath)
                        ->withUrlPath($urlPath)
                        ->withVersion($this->notFoundService->getVersion($request))
                        ->withLanguage($this->notFoundService->getLanguage($request))
                        ->build()
                ),
            ]);
        }
    }
}
