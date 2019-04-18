<?php

namespace MODXDocs\Views;

use MODXDocs\Navigation\Tree;
use MODXDocs\Model\PageRequest;
use MODXDocs\Services\VersionsService;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Router;

class Search extends Base
{
    /** @var Router */
    private $router;

    /** @var VersionsService */
    private $versionsService;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->router = $this->container->get('router');
        $this->versionsService = $this->container->get(VersionsService::class);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    public function get(Request $request, Response $response)
    {
        // The PageRequest gives us the version/language/etc.
        $pageRequest = PageRequest::fromRequest($request);

        $query = trim((string)$request->getParam('q', ''));

        $crumbs = [];
        $crumbs[] = [
            'title' => 'Search ' . $pageRequest->getVersion(), // @todo i18n
            'href' => $this->router->pathFor('search', ['version' => $pageRequest->getVersion(), 'language' => $pageRequest->getLanguage()])
        ];
        $crumbs[] = [
            'title' => '"' . $query . '"',
            'href' => $this->router->pathFor('search', ['version' => $pageRequest->getVersion(), 'language' => $pageRequest->getLanguage()], ['q' => $query])
        ];

        $title = 'Search the documentation';
        $results = [];
        $resultCount = 0;
        if (!empty($query)) {
            $returnFakeResults = random_int(0, 100) > 33;

            if ($returnFakeResults) {
                $resultCount = random_int(5, 532);
                $show = random_int(5, 25);
                $i = 1;
                while ($i < $show) {
                    $results[] = [
                        'title' => 'Using Friendly URLs',
                        'snippet' => '...when using <span class="search--highlight">Friendly URLs</span> you can get awesome SEO Benefits for freebies..',
                        'url' => $pageRequest->getContextUrl() . 'foo/bar',
                        'idx' => $i,
                    ];
                    $i++;
                }
            }

            if ($resultCount > 0) {
                $title = $resultCount . ' results for "' . $query . '"';
            }
            else {
                $title = 'No results for "' . $query . '"';
            }
        }

        $tree = Tree::get($pageRequest->getVersion(), $pageRequest->getLanguage());
        $tree->setActivePath($pageRequest->getContextUrl() . $pageRequest->getPath());
        return $this->render($request, $response, 'search.twig', [
            'page_title' => $title,
            'search_query' => $query,
            'result_count' => $resultCount,
            'results' => $results,
            'crumbs' => $crumbs,
            'canonical_url' => '',
            'versions' => $this->versionsService->getVersions($pageRequest),
            'nav' => $tree->renderTree($this->view),
        ]);
    }
}
