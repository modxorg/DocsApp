<?php

namespace MODXDocs\Services;

use MODXDocs\Exceptions\NotFoundException;
use MODXDocs\Model\PageRequest;

class IndexService
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

    public function indexFile(string $language, string $version, string $path)
    {
        $uri = strpos($path, '.md') !== false ? substr($path, 0, strpos($path, '.md')) : $path;
        $directoryPrefix = '/' . $version . '/' . $language . '/';
        $cleanedUri = strpos($uri, $directoryPrefix) === 0 ? substr($uri, strlen($directoryPrefix)) : $uri;


        // Grab the old page id
        $selectPageId = $this->db->prepare('SELECT ROWID FROM Search_Pages WHERE url = :url');
        $selectPageId->bindValue(':url', $uri);
        $pageId = $selectPageId->fetchColumn();

        // Delete term associations, if any
        $deleteTermsOccs = $this->db->prepare('DELETE FROM Search_Terms_Occurrences WHERE page = :page');
        $deleteTermsOccs->bindValue(':page', $pageId);
        $deleteTermsOccs->execute();

        // Delete the old page, if any
        $deletePage = $this->db->prepare('DELETE FROM Search_Pages WHERE url = :url');
        $deletePage->bindValue(':url', $uri);
        $deletePage->execute();

        try {
            $pr = new PageRequest($version, $language, $cleanedUri);
            $page = $this->documentService->load($pr);
        } catch (NotFoundException $e) {
            return 'Could not load file to index ' . $uri;
        }

        $title = $page->getTitle();

        // Create a new page
        $insertPage = $this->db->prepare('INSERT INTO Search_Pages (url, title) VALUES (:url, :title)');
        $insertPage->bindValue(':url', $uri);
        $insertPage->bindValue(':title', $title);
        $insertPage->execute();
        $pageId = $this->db->lastInsertId();

        $insertTermOcc = $this->db->prepare('INSERT INTO Search_Terms_Occurrences (page, term, weight) VALUES (:page, :term, :weight)');
        $titleMap = SearchService::filterStopwords($language, SearchService::stringToMap($title));
        $titleTerms = $this->indexWords($this->db, array_keys($titleMap), $version, $language);

        $this->db->beginTransaction();
        foreach ($titleTerms as $term => $termRowId) {
            $insertTermOcc->bindValue(':page', $pageId);
            $insertTermOcc->bindValue(':term', $termRowId);
            $weight = 15;
            if (isset($titleMap[$term]) && $titleMap[$term] >= 2) {
                $weight = 25;
            }
            $insertTermOcc->bindValue(':weight', $weight);
            $insertTermOcc->execute();
        }
        $this->db->commit();

        // Index the table of contents
        $toc = $page->getTableOfContents();
        $toc = strip_tags($toc);
        $tocMap = SearchService::filterStopwords($language, SearchService::stringToMap($toc));
        $tocTerms = $this->indexWords($this->db, array_keys($tocMap), $version, $language);

        $this->db->beginTransaction();
        foreach ($tocTerms as $term => $termRowId) {
            $insertTermOcc->bindValue(':page', $pageId);
            $insertTermOcc->bindValue(':term', $termRowId);

            $weight = 4;
            if (isset($tocMap[$term])) {
                $weight = $tocMap[$term] >= 5 ? 20 : $tocMap[$term] * $weight;
            }
            $insertTermOcc->bindValue(':weight', $weight);
            $insertTermOcc->execute();
        }
        $this->db->commit();

        // Index the rendered body
        $body = $page->getRenderedBody();
        $body = strip_tags($body);
        $bodyMap = SearchService::filterStopwords($language, SearchService::stringToMap($body));
        $bodyTerms = $this->indexWords($this->db, array_keys($bodyMap), $version, $language);

        $this->db->beginTransaction();
        foreach ($bodyTerms as $term => $termRowId) {
            $insertTermOcc->bindValue(':page', $pageId);
            $insertTermOcc->bindValue(':term', $termRowId);

            $weight = 1;
            if (isset($bodyMap[$term])) {
                $weight = $bodyMap[$term] >= 20 ? 20 : $bodyMap[$term];
            }
            $insertTermOcc->bindValue(':weight', $weight);
            $insertTermOcc->execute();
        }
        $this->db->commit();

        return true;
    }

    private function indexWords(\PDO $db, array $words, $version, $language)
    {
        $map = [];

        $db->beginTransaction();
        $fetchStmt = $db->prepare('SELECT rowid FROM Search_Terms WHERE term = :term AND version = :version AND language = :language');
        $insertStmt = $db->prepare('INSERT INTO Search_Terms (term, phonetic_term, language, version, total_occurrences) VALUES (:term, :phonetic_term, :language, :version, 0)');
        foreach ($words as $word) {
            $fetchStmt->bindValue(':term', $word);
            $fetchStmt->bindValue(':version', $version);
            $fetchStmt->bindValue(':language', $language);
            if ($fetchStmt->execute() && $rowid = $fetchStmt->fetch(\PDO::FETCH_COLUMN)) {
                $map[$word] = $rowid;
                continue;
            }

            $insertStmt->bindValue(':term', $word);
            $insertStmt->bindValue(':phonetic_term', SearchService::fuzzyTerm($word, $language));
            $insertStmt->bindValue(':language', $language);
            $insertStmt->bindValue(':version', $version);


            $insertStmt->execute();

            $map[$word] = $db->lastInsertId();
        }
        $db->commit();
        return $map;
    }
}
