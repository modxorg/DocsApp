<?php

namespace MODXDocs\Services;

use MODXDocs\Model\PageRequest;
use Slim\Router;
use voku\helper\StopWords;
use voku\helper\StopWordsLanguageNotExists;

class SearchService
{
    /**
     * @var \PDO
     */
    private $db;

    private static $stopwords;

    public function __construct(\PDO $db, Router $router)
    {
        $this->db = $db;
    }

    public function find(PageRequest $pageRequest, string $query, &$resultCount, &$debug): array
    {
        $startTime = microtime(true);
        $terms = array_keys(self::filterStopwords($pageRequest->getLanguage(), self::stringToMap($query)));

        $matchingTerms = [];

        $findTermsStmt = $this->db->prepare('SELECT rowid, term, phonetic_term FROM Search_Terms WHERE phonetic_term = :phonetic AND language = :language AND version = :version');
        $findTermsStmt->bindValue(':language', $pageRequest->getLanguage());
        $findTermsStmt->bindValue(':version', $pageRequest->getVersionBranch());

        $findExactTermsStmt = $this->db->prepare('SELECT rowid, term, phonetic_term FROM Search_Terms WHERE term = :term AND language = :language AND version = :version');
        $findExactTermsStmt->bindValue(':language', $pageRequest->getLanguage());
        $findExactTermsStmt->bindValue(':version', $pageRequest->getVersionBranch());

        foreach ($terms as $term) {
            $findTermsStmt->bindValue(':phonetic', soundex($term));
            if ($findTermsStmt->execute() && $phoneticTerms = $findTermsStmt->fetchAll(\PDO::FETCH_ASSOC)) {
                if (count($phoneticTerms) > 50) {
                    // too generic; fallback to exact match
                    $findExactTermsStmt->bindValue(':term', $term);
                    if ($findExactTermsStmt->execute() && $exactTerms = $findExactTermsStmt->fetchAll(\PDO::FETCH_ASSOC)) {
                        foreach ($exactTerms as $exactTerm) {
                            $matchingTerms[$exactTerm['term']] = $exactTerm['rowid'];
                        }
                    }
                }
                else {
                    foreach ($phoneticTerms as $phoneticTerm) {
                        $matchingTerms[$phoneticTerm['term']] = $phoneticTerm['rowid'];
                    }
                }
            }
        }

        $debug['$matchingTerms'] = $matchingTerms;
        if (count($matchingTerms) === 0) {
            $resultCount = 0;
            return [];
        }

        $placeholders = str_repeat ('?, ',  count ($matchingTerms) - 1) . '?';
        $selectOccurrencesStmt = $this->db->prepare('SELECT page, term, weight FROM Search_Terms_Occurrences WHERE term IN (' . $placeholders . ')');

        $pages = [];
        $debugPages = [];
        if ($selectOccurrencesStmt->execute(array_values($matchingTerms)) && $occurrences = $selectOccurrencesStmt->fetchAll(\PDO::FETCH_ASSOC)) {
            foreach ($occurrences as $occurrence) {
                if (!array_key_exists($occurrence['page'], $pages)) {
                    $pages[$occurrence['page']] = 0;
                    $debugPages[$occurrence['page']] = [];
                }
                $pages[$occurrence['page']] += (int)$occurrence['weight'];
                $debugPages[$occurrence['page']][] = 'Term ' . $occurrence['term'] . ' +' . $occurrence['weight'];
            }
        }

        // Sort best match first
        arsort($pages, SORT_NUMERIC);

        $debug['$pages'] = $pages;
        $debug['$debugPages'] = $debugPages;

        $resultCount = count($pages);

        $page = 1;
        $limit = 10;
        $start = 0 + ($page - 1) * $limit;

        $paginatedResults = array_slice($pages, $start, $limit, true);

        $debug['$paginatedResults'] = $paginatedResults;

        $placeholders = str_repeat ('?, ',  count ($paginatedResults) - 1) . '?';
        $getPagesStmt = $this->db->prepare('SELECT rowid, url, title FROM Search_Pages WHERE rowid IN (' . $placeholders . ')');
        $getPagesStmt->execute(array_keys($paginatedResults));

        while ($row = $getPagesStmt->fetch(\PDO::FETCH_ASSOC)) {
            $id = $row['rowid'];
            $paginatedResults[$id] = [
                'link' => str_replace('/' . $pageRequest->getVersionBranch() . '/', '/' . $pageRequest->getVersion() . '/', $row['url']),
                'title' => $row['title'],
                'weight' => $paginatedResults[$id],
                'actual_score' => $paginatedResults[$id],
                'score' => (int)min(100, $paginatedResults[$id] / 40 * 100),
            ];
        }

        $tookTime = microtime(true) - $startTime;
        $debug['$tookTime'] = round($tookTime * 1000, 3);

        return $paginatedResults;
    }

    public static function stringToMap(string $value): array
    {
        $value = strtolower(trim($value));
        $map = preg_split('/[\s\-\\\:]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        $map = array_map(static function($v) { return trim($v, '"\'$,.-():;&#_?/\\'); }, $map);
        $map = array_filter($map);
        $map = array_filter($map, static function($v) {
            return mb_strlen($v) >= 2;
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
}
