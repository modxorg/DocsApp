<?php

namespace MODXDocs\Services;

use MODXDocs\Exceptions\NotFoundException;
use MODXDocs\Model\PageRequest;
use MODXDocs\Model\SearchQuery;
use MODXDocs\Model\SearchResults;
use voku\helper\StopWords;
use voku\helper\StopWordsLanguageNotExists;

class SearchService
{
    public const MIN_TERM_LENGTH = 2;

    /**
     * @var \PDO
     */
    private $db;

    private static $stopwords;

    /**
     * @var DocumentService
     */
    private $documentService;

    public function __construct(\PDO $db, DocumentService $documentService)
    {
        $this->db = $db;
        $this->documentService = $documentService;
    }

    public function getExactTermReferences($version, $language, $term): array
    {
        $statement = $this->db->prepare('SELECT rowid, term, phonetic_term FROM Search_Terms WHERE term = :term AND language = :language AND version = :version');
        $statement->bindValue(':language', $language);
        $statement->bindValue(':version', $version);
        $statement->bindValue(':term', $term);

        $return = [];
        if ($statement->execute() && $exactTerms = $statement->fetchAll(\PDO::FETCH_ASSOC)) {
            foreach ($exactTerms as $exactTerm) {
                $return[$exactTerm['rowid']] = $exactTerm['term'];
            }
        }
        return $return;
    }

    public function getStartsWithReferences($version, $language, $term): array
    {
        $findTermsStmt = $this->db->prepare('SELECT rowid, term, phonetic_term FROM Search_Terms WHERE term LIKE :term AND language = :language AND version = :version');
        $findTermsStmt->bindValue(':language', $language);
        $findTermsStmt->bindValue(':version', $version);
        $findTermsStmt->bindValue(':term', $term . '%');

        $return = [];
        if ($findTermsStmt->execute() && $startingTerms = $findTermsStmt->fetchAll(\PDO::FETCH_ASSOC)) {
            if (count($startingTerms) > 50) {
                // too generic; ignore
                return [];
            }

            foreach ($startingTerms as $startingTerm) {
                $return[$startingTerm['rowid']] = $startingTerm['term'];
            }
        }
        return $return;
    }

    public function getFuzzyTermReferences($version, $language, $term): array
    {
        $findTermsStmt = $this->db->prepare('SELECT rowid, term, phonetic_term FROM Search_Terms WHERE phonetic_term = :phonetic AND language = :language AND version = :version');
        $findTermsStmt->bindValue(':language', $language);
        $findTermsStmt->bindValue(':version', $version);
        $findTermsStmt->bindValue(':phonetic', self::fuzzyTerm($term, $language));

        $return = [];
        if ($findTermsStmt->execute() && $phoneticTerms = $findTermsStmt->fetchAll(\PDO::FETCH_ASSOC)) {
            if (count($phoneticTerms) > 50) {
                // too generic; fallback to exact match
                return $this->getExactTermReferences($version, $language, $term);
            }

            foreach ($phoneticTerms as $phoneticTerm) {
                $return[$phoneticTerm['rowid']] = $phoneticTerm['term'];
            }
        }
        return $return;
    }

    public function execute(SearchQuery $query)
    {
        $results = new SearchResults($this->documentService, $query);
        $allTerms = $query->getSearchTermReferences();
        if (count($allTerms) === 0) {
            return $results;
        }

        $placeholders = str_repeat ('?, ',  count ($allTerms) - 1) . '?';
        $selectOccurrencesStmt = $this->db->prepare('SELECT page, term, weight FROM Search_Terms_Occurrences WHERE term IN (' . $placeholders . ')');

        if ($selectOccurrencesStmt->execute(array_values($allTerms))) {
            $occurrences = $selectOccurrencesStmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($occurrences as $occurrence) {
                $results->addMatch($occurrence['term'], $occurrence['page'], $occurrence['weight']);
            }
        }

        $results->process();
        
        $this->logSearch($query, $results);
        
        return $results;
    }

    public function getPageMetas(array $pageIDs)
    {
        if (count($pageIDs) === 0) {
            return [];
        }

        $placeholders = str_repeat ('?, ',  count ($pageIDs) - 1) . '?';
        $getPagesStmt = $this->db->prepare('SELECT rowid, url, title FROM Search_Pages WHERE rowid IN (' . $placeholders . ')');
        $getPagesStmt->execute($pageIDs);

        $metas = [];

        while ($row = $getPagesStmt->fetch(\PDO::FETCH_ASSOC)) {
            $id = $row['rowid'];
            $metas[$id] = [
                'id' => $row['rowid'],
                'link' => $row['url'],
                'title' => $row['title'],
            ];
        }

        return $metas;
    }

    public static function fuzzyTerm(string $term, string $language): string
    {
        return soundex($term);
    }

    public static function stringToMap(string $value): array
    {
        $value = strtolower(trim($value));
        $map = preg_split('/[\s\-\\\:]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        $map = array_map(static function($v) { return trim($v, '"\'$,.-():;&#_?/\\'); }, $map);
        $map = array_filter($map);
        $map = array_filter($map, static function($v) {
            return mb_strlen($v) >= SearchService::MIN_TERM_LENGTH;
        });
        $map = array_count_values($map);
        return $map;
    }

    /**
     * @param string $language
     * @param array $map
     * @return array
     */
    public static function filterStopwords(string $language, array $map): array
    {
        if (!self::$stopwords) {
            self::$stopwords = new StopWords();
        }
        try {
            $stopwords = self::$stopwords->getStopWordsFromLanguage($language);
            foreach ($stopwords as $stopword) {
                if (array_key_exists($stopword, $map)) {
                    unset($map[$stopword]);
                }
            }
        } catch (StopWordsLanguageNotExists $e) {
        }
        return $map;
    }

    public function populateResults(PageRequest $pageRequest, SearchResults $result, array $pageIDs): array
    {
        $return = [];
        $metas = $this->getPageMetas(array_keys($pageIDs));

        foreach ($pageIDs as $id => $score) {
            $sr = $metas[$id] ?? [];
            $sr['link'] = str_replace('/' . $pageRequest->getVersionBranch() . '/', '/' . $pageRequest->getVersion() . '/', $sr['link']);
            $sr['weight'] = $score;
            $sr['score'] = (int)($pageIDs[$id] / 40 * 100);
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

            $return[] = $sr;
        }

        return $return;
    }

    private function logSearch(SearchQuery $query, SearchResults $results)
    {
        try {
            $fetch = $this->db->prepare('SELECT rowid,* FROM Searches WHERE search_query = :query LIMIT 1');
            $fetch->bindValue(':query', $query->getQueryString());
            if ($fetch->execute() && $log = $fetch->fetch(\PDO::FETCH_ASSOC)) {
                $update = $this->db->prepare('UPDATE Searches SET result_count = :result_count, search_count = :search_count, last_seen = :last_seen WHERE ROWID = :rowid');
                $update->bindValue('result_count', $results->getCount());
                $update->bindValue('search_count', (int)$log['search_count'] + 1);
                $update->bindValue('last_seen', time());
                $update->bindValue('rowid', $log['rowid']);
                $update->execute();
            } else {
                $insert = $this->db->prepare('INSERT INTO Searches (search_query, result_count, search_count, first_seen, last_seen) VALUES (:search_query, :result_count, 1, :first_seen, :last_seen)');
                $insert->bindValue('search_query', $query->getQueryString());
                $insert->bindValue('result_count', $results->getCount());
                $insert->bindValue('first_seen', time());
                $insert->bindValue('last_seen', time());
                $insert->execute();
            }
        }
        catch (\PDOException $e) {
            // Silence logging errors.. not critical enough to bother
        }
    }
}
