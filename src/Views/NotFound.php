<?php

namespace MODXDocs\Views;

use MODXDocs\Model\PageRequest;
use MODXDocs\Model\SearchQuery;
use MODXDocs\Navigation\Tree;
use MODXDocs\Services\SearchService;
use MODXDocs\Services\VersionsService;
use PDO;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

use MODXDocs\Exceptions\RedirectNotFoundException;
use MODXDocs\Helpers\Redirector;

class NotFound extends Base
{
    private const MARKDOWN_SUFFIX = '.md';

    /** @var SearchService */
    private $searchService;

    /** @var PDO */
    private $db;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->db = $container->get('db');
        $this->searchService = $this->container->get(SearchService::class);
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

            $this->logNotFoundRequest($currentUri);

            // Render the default tree on the 404 page
            // @todo See if it's possible to use version/language specific trees without breaking when invalid
            $tree = Tree::get(VersionsService::getCurrentVersion(), VersionsService::getDefaultLanguage());

            // Prepare a somewhat normalised search query
            $query = str_replace(['-', '_', '+', '/'], ' ', strtolower(urldecode($currentUri)));
            $query = explode(' ', $query);
            // Filter out some common old url structures
            $query = array_diff($query, ['display', 'revolution20', 'revo', '_legacy', '1.x', '2.x']);
            $query = trim(implode(' ', $query));

            // Run the search
            $pageRequest = new PageRequest(VersionsService::getCurrentVersion(), VersionsService::getDefaultLanguage(), '');
            $sq = new SearchQuery($this->searchService, $query, $pageRequest, false);
            $result = $this->searchService->execute($sq);

            // Maximum 5 results, with a score of at least 30 (75% confidence)
            $pageIDs = $result->getResults(0, 5);
            $pageIDs = array_filter($pageIDs, static function($value) {
                return $value >= 30;
            });

            $searchResults = $this->searchService->populateResults($pageRequest, $result, $pageIDs);

            return $this->render404($request, $response, [
                'req_url' => urlencode($currentUri),
                'page_title' => 'Oops, page not found.',
                'nav' => $tree->renderTree($this->view),

                'version' => VersionsService::getCurrentVersion(),
                'version_branch' => VersionsService::getCurrentVersionBranch(),
                'language' => VersionsService::getDefaultLanguage(),

                'search_results' => $searchResults,
                'search_query' => $query,
                'terms' => $sq->getAllTerms(),

                // We always disregard the path here, because we know the request is always invalid
                'path' => null,
            ]);
        }
    }

    private function logNotFoundRequest(string $requestUri): void
    {
        try {
            $fetch = $this->db->prepare('SELECT rowid, url, hit_count FROM PageNotFound WHERE url = :url');
            $fetch->bindValue(':url', $requestUri);
            if ($fetch->execute() && $log = $fetch->fetch(\PDO::FETCH_ASSOC)) {
                $update = $this->db->prepare('UPDATE PageNotFound SET hit_count = :hit_count, last_seen = :last_seen WHERE ROWID = :rowid');
                $update->bindValue('hit_count', (int)$log['hit_count'] + 1);
                $update->bindValue('last_seen', time());
                $update->bindValue('rowid', $log['rowid']);
                $update->execute();
            }
            else {
                $insert = $this->db->prepare('INSERT INTO PageNotFound (url, hit_count, last_seen) VALUES (:url, 1, :last_seen)');
                $insert->bindValue('url', $requestUri);
                $insert->bindValue('last_seen', time());
                $insert->execute();
            }
        }
        catch (\PDOException $e) {
            // Silence logging errors.. not interesting
        }
    }

    private function searchForPage(string $currentUri)
    {

    }
}
