<?php

namespace MODXDocs\Views;

use MODXDocs\Exceptions\NotFoundException;
use MODXDocs\Model\SearchQuery;
use MODXDocs\Navigation\Tree;
use MODXDocs\Model\PageRequest;
use MODXDocs\Services\DocumentService;
use MODXDocs\Services\SearchService;
use MODXDocs\Services\VersionsService;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Router;

class Search extends Base
{
    /** @var DocumentService */
    private $documentService;
    /** @var Router */
    private $router;
    /** @var VersionsService */
    private $versionsService;

    /** @var SearchService */
    private $searchService;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->router = $this->container->get('router');
        $this->versionsService = $this->container->get(VersionsService::class);
        $this->searchService = $this->container->get(SearchService::class);
        $this->documentService = $this->container->get(DocumentService::class);
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

        $startTime = microtime(true);
        $live = (bool)$request->getParam('live');
        $sq = new SearchQuery($this->searchService, $query, $pageRequest, $live);

        $result = $this->searchService->execute($sq);
        $resultCount = $result->getCount();

        $limit = 10;
        $page = abs((int)$request->getParam('page', 1));
        $totalPages = ceil($resultCount / $limit);
        $start = 0 + ($page - 1) * $limit;

        $pageIDs = $result->getResults($start, $limit);
        $metas = $this->searchService->getPageMetas(array_keys($pageIDs));
        $results = [];
        foreach ($pageIDs as $id => $score) {
            $sr = $metas[$id] ?? [];
            $sr['link'] = str_replace('/' . $pageRequest->getVersionBranch() . '/', '/' . $pageRequest->getVersion() . '/', $sr['link']);
            $sr['weight'] = $score;
            $sr['score'] = (int)min(100, $pageIDs[$id] / 40 * 100);
            $sr['details'] = $result->getDetailedMatches($id);

            $sr['crumbs'] = [];
            $sr['snippet'] = '';

            try {
                $document = $this->documentService->load(
                    new PageRequest(
                        $pageRequest->getVersion(),
                        $pageRequest->getLanguage(),
                        str_replace(
                            '/' . $pageRequest->getVersion() . '/' . $pageRequest->getLanguage() . '/',
                            '',
                            $sr['link']
                        )
                    )
                );

                $parent = $document;
                while ($parent = $parent->getParentPage()) {
                    $sr['crumbs'][] = [
                        'title' => $parent->getTitle(),
                        'href' => $parent->getUrl(),
                    ];
                }
                $sr['crumbs'] = array_reverse($sr['crumbs']);

                $meta = $document->getMeta();
                if (array_key_exists('description', $meta) && !empty($meta['description'])) {
                    $sr['snippet'] = $meta['description'];
                }
                else {
                    $body = $document->getRenderedBody();
                    $body = strip_tags($body);
                    $sr['snippet'] = mb_substr($body, 0, 250) . (mb_strlen($body) > 255 ? '...' : '');
                }
            } catch (NotFoundException $e) {
                $sr['snippet'] = '<em>Unable to load result details.</em>';
            }

            $results[] = $sr;
        }

        $title = 'Search the documentation';
        $pagination = [];
        if (!empty($query)) {
            if ($resultCount > 0) {
                $title = $resultCount . ' results for "' . $query . '"';
                $pagination = $this->getPagination($page, $pageRequest, $query, $totalPages);
            }
            else {
                $title = 'No results for "' . $query . '"';
            }
        }


        $tree = Tree::get($pageRequest->getVersion(), $pageRequest->getLanguage());
        $tree->setActivePath($pageRequest->getContextUrl() . $pageRequest->getPath());

        $template = $live ? 'search_ajax.twig' : 'search.twig';

        return $this->render($request, $response, $template, [
            'page_title' => $title,
            'search_query' => $query,
            'result_count' => $resultCount,
            'results' => $results,
            'crumbs' => $crumbs,
            'canonical_url' => '',
            'versions' => $this->versionsService->getVersions($pageRequest),
            'nav' => $tree->renderTree($this->view),

            'timing' => number_format((microtime(true) - $startTime) * 1000),
            'terms' => $sq->getAllTerms(),
            'exact_terms' => $sq->getExactTerms(),
            'fuzzy_terms' => $sq->getFuzzyTerms(),
            'ignored_terms' => $sq->getIgnoredTerms(),
            'pagination' => $pagination,
        ]);
    }

    protected function getPagination(int $page, PageRequest $pageRequest, string $query, int $totalPages): array
    {
        $pagination = [];
        $max = 3;
        $looped = 0;
        $prev = $page - 1;
        while ($prev > 1 && $looped < $max) {
            $looped++;
            $pagination[] = [
                'page' => $prev,
                'href' => $this->router->pathFor('search',
                    ['version' => $pageRequest->getVersion(), 'language' => $pageRequest->getLanguage()],
                    ['q' => $query, 'page' => $prev])
            ];
            $prev--;
        }

        if ($page > 1) {
            $pagination[] = [
                'page' => 'First',
                'href' => $this->router->pathFor('search',
                    ['version' => $pageRequest->getVersion(), 'language' => $pageRequest->getLanguage()],
                    ['q' => $query])
            ];
        }

        $pagination = array_reverse($pagination);


        $pagination[] = [
            'current' => true,
            'page' => $page,
            'href' => $this->router->pathFor('search',
                ['version' => $pageRequest->getVersion(), 'language' => $pageRequest->getLanguage()],
                ['q' => $query, 'page' => $page])
        ];

        $looped = 0;
        $next = $page + 1;
        while ($next > 1 && $looped < $max && $next < $totalPages) {
            $looped++;
            $pagination[] = [
                'page' => $next,
                'href' => $this->router->pathFor('search',
                    ['version' => $pageRequest->getVersion(), 'language' => $pageRequest->getLanguage()],
                    ['q' => $query, 'page' => $next])
            ];
            $next++;
        }

        if ($next < $totalPages) {

            $pagination[] = [
                'page' => 'Last',
                'href' => $this->router->pathFor('search',
                    ['version' => $pageRequest->getVersion(), 'language' => $pageRequest->getLanguage()],
                    ['q' => $query, 'page' => $totalPages])
            ];
        }

        return $pagination;
    }
}
