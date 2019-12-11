<?php

namespace MODXDocs\Views\Stats;

use MODXDocs\Containers\DB;
use MODXDocs\Services\CacheService;
use MODXDocs\Views\Base;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Router;

class NotFoundRequests extends Base
{
    /**
     * @var CacheService
     */
    private $cache;
    /** @var DB */
    private $db;
    /** @var Router */
    private $router;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->router = $this->container->get('router');
        $this->db = $this->container->get('db');
        $this->cache = CacheService::getInstance();
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    public function get(Request $request, Response $response)
    {
        $crumbs = [];
        $crumbs[] = [
            'title' => 'Page Not Found Errors', // @todo i18n
            'href' => $this->router->pathFor('stats/page-not-found')
        ];

        $phs = [
            'page_title' => 'Page Not Found Statistics',
            'crumbs' => $crumbs,
            'canonical_url' => '',

            'top_requests' => $this->getTopRequests(),
            'recent_requests' => $this->getRecentRequests(),
//            'versions' => $this->versionsService->getVersions($pageRequest),
//            'nav' => $tree->renderTree($this->view),

//            'timing' => number_format((microtime(true) - $startTime) * 1000),
//            'terms' => $sq->getAllTerms(),
//            'exact_terms' => $sq->getExactTerms(),
//            'fuzzy_terms' => $sq->getFuzzyTerms(),
//            'ignored_terms' => $sq->getIgnoredTerms(),
//            'pagination' => $pagination,
        ];

        return $this->render($request, $response, 'stats/not-found-requests.twig', $phs);
    }

    private function getTopRequests()
    {
        $results = $this->cache->get('stats/notfoundrequests/top');
        if (is_array($results)) {
            return $results;
        }
        $statement = $this->db->prepare('SELECT url, hit_count, last_seen FROM PageNotFound ORDER BY hit_count DESC LIMIT 50');

        $results = [];
        if ($statement->execute() && $requests = $statement->fetchAll(\PDO::FETCH_ASSOC)) {
            foreach ($requests as $req) {
                $results[] = $req;
            }
        }

        $this->cache->set('stats/notfoundrequests/top', $results, strtotime('+12 hours'));
        return $results;
    }

    private function getRecentRequests()
    {
        $results = $this->cache->get('stats/notfoundrequests/recent');
        if (is_array($results)) {
            return $results;
        }
        $statement = $this->db->prepare('SELECT url, hit_count, last_seen FROM PageNotFound ORDER BY last_seen DESC LIMIT 50');

        $results = [];
        if ($statement->execute() && $requests = $statement->fetchAll(\PDO::FETCH_ASSOC)) {
            foreach ($requests as $req) {
                $results[] = $req;
            }
        }

        $this->cache->set('stats/notfoundrequests/recent', $results, strtotime('+3 hours'));
        return $results;
    }
}
