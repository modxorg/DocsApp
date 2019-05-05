<?php

namespace MODXDocs\Services;

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

    public function getFuzzyTermReferences($version, $language, $term): array
    {
        $findTermsStmt = $this->db->prepare('SELECT rowid, term, phonetic_term FROM Search_Terms WHERE phonetic_term = :phonetic AND language = :language AND version = :version');
        $findTermsStmt->bindValue(':language', $language);
        $findTermsStmt->bindValue(':version', $version);
        $findTermsStmt->bindValue(':phonetic', soundex($term));

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

        $placeholders = str_repeat ('?, ',  count ($allTerms) - 1) . '?';
        $selectOccurrencesStmt = $this->db->prepare('SELECT page, term, weight FROM Search_Terms_Occurrences WHERE term IN (' . $placeholders . ')');

        if ($selectOccurrencesStmt->execute(array_values($allTerms))) {
            $occurrences = $selectOccurrencesStmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($occurrences as $occurrence) {
                $results->addMatch($occurrence['term'], $occurrence['page'], $occurrence['weight']);
            }
        }

        $results->process();
        return $results;
    }

    public function getPageMetas(array $pageIDs)
    {
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
}
