<?php

namespace MODXDocs\Views;

use MODXDocs\Helpers\DbValueGuard;
use MODXDocs\Model\PageRequest;
use MODXDocs\Model\SearchQuery;
use MODXDocs\Navigation\Tree;
use MODXDocs\Services\SearchService;
use MODXDocs\Services\VersionsService;
use PDO;
use Psr\Container\ContainerInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use MODXDocs\Exceptions\RedirectNotFoundException;
use MODXDocs\Helpers\Redirector;

class NotFound extends Base
{
    private const MARKDOWN_SUFFIX = '.md';
    private const SUPPORTED_LANGUAGES = ['en', 'ru', 'nl', 'es'];
    private SearchService $searchService;
    private PDO $db;

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
            return $response->withHeader('Location', $uri)->withStatus(301);
        }

        try {
            $redirectUri = Redirector::findNewURI($currentUri);

            return $response->withHeader('Location', $redirectUri)->withStatus(301);
        } catch (RedirectNotFoundException $e) {
            $this->logNotFoundRequest($currentUri);

            [$version, $language] = $this->resolveVersionAndLanguage($request, $currentUri);
            $request = $request
                ->withAttribute('version', $version)
                ->withAttribute('language', $language);

            $tree = Tree::get($version, $language);

            // Prepare a somewhat normalised search query
            $query = str_replace(['-', '_', '+', '/'], ' ', strtolower(urldecode($currentUri)));
            $query = explode(' ', $query);
            // Filter out version/language segments and common old url structures
            $query = array_diff($query, array_merge(
                ['display', 'revolution20', 'revo', '_legacy', '1.x', '2.x', 'current'],
                self::SUPPORTED_LANGUAGES
            ));
            $query = trim(implode(' ', $query));

            // Run the search in the resolved version/language
            $pageRequest = new PageRequest($version, $language, '');
            $sq = new SearchQuery($this->searchService, $query, $pageRequest);
            $result = $this->searchService->execute($sq);

            // Maximum 5 results, with a score of at least 30 (75% confidence)
            $pageIDs = $result->getResults(0, 5);
            $pageIDs = array_filter($pageIDs, static function ($value) {
                return $value >= 30;
            });

            $searchResults = $this->searchService->populateResults($pageRequest, $result, $pageIDs);
            $lang = $this->getLang($language);

            return $this->render404($request, $response, [
                'req_url' => urlencode($currentUri),
                'page_title' => $lang['not_found_title'],
                'nav' => $tree->renderTree($this->view),

                'search_results' => $searchResults,
                'search_query' => $query,
                'terms' => $sq->getAllTerms(),

                // We always disregard the path here, because we know the request is always invalid
                'path' => null,
            ]);
        }
    }

    /**
     * Prefer route attributes; otherwise parse /{version}/{language}/… from the URI
     * when those segments look valid.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveVersionAndLanguage(Request $request, string $uri): array
    {
        $version = $request->getAttribute('version');
        $language = $request->getAttribute('language');

        if ($version === null || $language === null) {
            $parts = explode('/', trim($uri, '/'));
            if (count($parts) >= 2) {
                $version = $version ?? $parts[0];
                $language = $language ?? $parts[1];
            }
        }

        $availableVersions = array_keys(VersionsService::getAvailableVersions());
        if ($version === null || !in_array($version, $availableVersions, true)) {
            $version = VersionsService::getCurrentVersion();
        }

        if ($language === null || !in_array($language, self::SUPPORTED_LANGUAGES, true)) {
            $language = VersionsService::getDefaultLanguage();
        }

        return [$version, $language];
    }

    private function logNotFoundRequest(string $requestUri): void
    {
        if (!DbValueGuard::fits($requestUri, DbValueGuard::URL)) {
            return;
        }

        try {
            $fetch = $this->db->prepare('SELECT id, url, hit_count FROM PageNotFound WHERE url = :url');
            $fetch->bindValue(':url', $requestUri);
            if ($fetch->execute() && $log = $fetch->fetch(\PDO::FETCH_ASSOC)) {
                $update = $this->db->prepare('UPDATE PageNotFound SET hit_count = :hit_count, last_seen = :last_seen WHERE id = :id');
                $update->bindValue('hit_count', (int)$log['hit_count'] + 1);
                $update->bindValue('last_seen', time());
                $update->bindValue('id', $log['id']);
                $update->execute();
            } else {
                $insert = $this->db->prepare('INSERT INTO PageNotFound (url, hit_count, last_seen) VALUES (:url, 1, :last_seen)');
                $insert->bindValue('url', $requestUri);
                $insert->bindValue('last_seen', time());
                $insert->execute();
            }
        } catch (\PDOException $e) {
            // Silence logging errors.. not interesting
        }
    }
}
