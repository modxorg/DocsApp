<?php

namespace MODXDocs\Views\Stats;

use MODXDocs\Containers\DB;
use MODXDocs\Services\CacheService;
use MODXDocs\Views\Base;
use PDO;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\RouteParserInterface;

class Searches extends Base
{
    /**
     * @var CacheService
     */
    private $cache;
    private PDO $db;
    /** @var RouteParserInterface */
    private $router;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->router = $this->container->get('router');
        $this->db = $this->container->get('db');
        $this->cache = CacheService::getInstance();
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     * @throws \Exception
     */
    public function get(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $crumbs = [];
        $crumbs[] = [
            'title' => 'Search Statistics', // @todo i18n
            'href' => $this->router->urlFor('stats/searches')
        ];

        $startTime = microtime(true);

        $phs = [
            'page_title' => 'Search Statistics',
            'crumbs' => $crumbs,
            'canonical_url' => '',

            'top_searches' => $this->getTopSearches(),
            'searches_without_results' => $this->getSearchesWithoutResults(),
            'recent_searches' => $this->getRecentSearches(),
//            'versions' => $this->versionsService->getVersions($pageRequest),
//            'nav' => $tree->renderTree($this->view),

//            'timing' => number_format((microtime(true) - $startTime) * 1000),
//            'terms' => $sq->getAllTerms(),
//            'exact_terms' => $sq->getExactTerms(),
//            'fuzzy_terms' => $sq->getFuzzyTerms(),
//            'ignored_terms' => $sq->getIgnoredTerms(),
//            'pagination' => $pagination,
        ];

        return $this->render($request, $response, 'stats/searches.twig', $phs);
    }

    private function getTopSearches(): array
    {
        $results = $this->cache->get('stats/searches/top');
        if (is_array($results)) {
            return $results;
        }
        $statement = $this->db->prepare('SELECT search_query, result_count, search_count, first_seen, last_seen FROM Searches ORDER BY search_count DESC LIMIT 50');

        $results = [];
        if ($statement->execute() && $terms = $statement->fetchAll(PDO::FETCH_ASSOC)) {
            foreach ($terms as $term) {
                $results[] = $term;
            }
        }

        $this->cache->set('stats/searches/top', $results, strtotime('+48 hours'));
        return $results;
    }

    private function getSearchesWithoutResults(): array
    {
        $results = $this->cache->get('stats/searches/without_results');
        if (is_array($results)) {
            return $results;
        }
        $statement = $this->db->prepare('SELECT search_query, result_count, search_count, first_seen, last_seen FROM Searches ORDER BY result_count ASC, search_count DESC LIMIT 50');

        $results = [];
        if ($statement->execute() && $terms = $statement->fetchAll(PDO::FETCH_ASSOC)) {
            foreach ($terms as $term) {
                $results[] = $term;
            }
        }

        $this->cache->set('stats/searches/without_results', $results, strtotime('+48 hours'));
        return $results;
    }

    private function getRecentSearches(): array
    {
        $results = $this->cache->get('stats/searches/recent');
        if (is_array($results)) {
            return $results;
        }
        $statement = $this->db->prepare('SELECT search_query, result_count, search_count, first_seen, last_seen FROM Searches ORDER BY last_seen DESC LIMIT 50');

        $results = [];
        if ($statement->execute() && $terms = $statement->fetchAll(PDO::FETCH_ASSOC)) {
            foreach ($terms as $term) {
                $results[] = $term;
            }
        }

        $this->cache->set('stats/searches/recent', $results, strtotime('+3 hours'));
        return $results;
    }
}
