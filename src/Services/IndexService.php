<?php

namespace MODXDocs\Services;

use MODXDocs\Exceptions\NotFoundException;
use MODXDocs\Helpers\DbValueGuard;
use MODXDocs\Model\PageRequest;

class IndexService
{
    private \PDO $db;
    private DocumentService $documentService;
    protected bool $indexSearchTerms = true;

    /**
     * @var bool|mixed
     */
    protected $indexHistory = true;

    public function __construct(\PDO $db, DocumentService $documentService)
    {
        $this->db = $db;
        $this->documentService = $documentService;
    }

    public function setIndexOptions(bool $indexSearchTerms = true, $indexHistory = true): void
    {
        $this->indexSearchTerms = $indexSearchTerms;
        $this->indexHistory = $indexHistory;
    }

    public function indexFile(string $language, string $version, string $path)
    {
        $uri = strpos($path, '.md') !== false ? substr($path, 0, strpos($path, '.md')) : $path;
        $directoryPrefix = '/' . $version . '/' . $language . '/';
        $cleanedUri = strpos($uri, $directoryPrefix) === 0 ? substr($uri, strlen($directoryPrefix)) : $uri;

        try {
            $pr = new PageRequest($version, $language, $cleanedUri);
            $page = $this->documentService->load($pr);
        } catch (NotFoundException $e) {
            return 'Could not load file to index ' . $uri;
        }

        if ($this->indexSearchTerms) {
            if (!DbValueGuard::fits($uri, DbValueGuard::URL)) {
                return 'URL too long to index (' . mb_strlen($uri) . ' chars, max ' . DbValueGuard::URL . '): ' . $uri;
            }

            try {
                $this->indexSearchTermsForPage($page, $uri, $language, $version);
            } catch (\PDOException $e) {
                return 'Failed to index search terms for ' . $uri . ': ' . $e->getMessage();
            }
        }

        if ($this->indexHistory) {
            try {
                $this->indexPageHistory($page);
            } catch (\PDOException $e) {
                return 'Failed to index history for ' . $uri . ': ' . $e->getMessage();
            }
        }

        return true;
    }

    private function indexSearchTermsForPage($page, string $uri, string $language, string $version): void
    {
        $selectPageId = $this->db->prepare('SELECT id FROM Search_Pages WHERE url = :url');
        $selectPageId->bindValue(':url', $uri);
        $selectPageId->execute();
        $pageId = $selectPageId->fetchColumn();

        $deleteTermsOccs = $this->db->prepare('DELETE FROM Search_Terms_Occurrences WHERE page = :page');
        $deleteTermsOccs->bindValue(':page', $pageId);
        $deleteTermsOccs->execute();

        $deletePage = $this->db->prepare('DELETE FROM Search_Pages WHERE url = :url');
        $deletePage->bindValue(':url', $uri);
        $deletePage->execute();

        $title = DbValueGuard::truncate($page->getTitle(), DbValueGuard::TITLE);

        $insertPage = $this->db->prepare('INSERT INTO Search_Pages (url, title) VALUES (:url, :title)');
        $insertPage->bindValue(':url', $uri);
        $insertPage->bindValue(':title', $title);
        $insertPage->execute();
        $pageId = $this->db->lastInsertId();

        $insertTermOcc = $this->db->prepare('INSERT INTO Search_Terms_Occurrences (page, term, weight) VALUES (:page, :term, :weight)');

        $titleMap = SearchService::filterStopwords($language, SearchService::stringToMap($title));
        $titleTerms = $this->indexWords($this->db, array_keys($titleMap), $version, $language);
        $this->insertTermOccurrences($insertTermOcc, $pageId, $titleTerms, $titleMap, 15, 25, 2);

        $toc = strip_tags($page->getTableOfContents());
        $tocMap = SearchService::filterStopwords($language, SearchService::stringToMap($toc));
        $tocTerms = $this->indexWords($this->db, array_keys($tocMap), $version, $language);
        $this->insertTermOccurrences($insertTermOcc, $pageId, $tocTerms, $tocMap, 4, 20, 5, true);

        $body = strip_tags($page->getRenderedBody());
        $bodyMap = SearchService::filterStopwords($language, SearchService::stringToMap($body));
        $bodyTerms = $this->indexWords($this->db, array_keys($bodyMap), $version, $language);
        $this->insertTermOccurrences($insertTermOcc, $pageId, $bodyTerms, $bodyMap, 1, 20, 20, false, true);
    }

    private function insertTermOccurrences(
        \PDOStatement $insertTermOcc,
        $pageId,
        array $terms,
        array $termMap,
        int $baseWeight,
        int $maxWeight,
        int $highCountThreshold,
        bool $multiplyBase = false,
        bool $capAtMax = false
    ): void {
        try {
            $this->db->beginTransaction();
            foreach ($terms as $term => $termRowId) {
                if ($termRowId === null) {
                    continue;
                }

                $insertTermOcc->bindValue(':page', $pageId);
                $insertTermOcc->bindValue(':term', $termRowId);

                $weight = $baseWeight;
                if (isset($termMap[$term])) {
                    $count = $termMap[$term];
                    if ($capAtMax) {
                        $weight = $count >= $highCountThreshold ? $maxWeight : $count;
                    } elseif ($multiplyBase) {
                        $weight = $count >= $highCountThreshold ? $maxWeight : $count * $baseWeight;
                    } elseif ($count >= $highCountThreshold) {
                        $weight = $maxWeight;
                    }
                }

                $insertTermOcc->bindValue(':weight', $weight);
                $insertTermOcc->execute();
            }
            $this->db->commit();
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
        }
    }

    private function indexPageHistory($page): void
    {
        $relativeFilePath = $page->getRelativeFilePath();
        if (!DbValueGuard::fits($relativeFilePath, DbValueGuard::URL)) {
            return;
        }

        $deleteHistory = $this->db->prepare('DELETE FROM Page_History WHERE url = :url');
        $deleteHistory->bindValue(':url', $relativeFilePath);
        $deleteHistory->execute();

        $insertHistory = $this->db->prepare('INSERT INTO Page_History (url, git_hash, ts, name, email, message, added, removed) VALUES (:url, :git_hash, :ts, :name, :email, :message, :added, :removed)');
        $commits = $page->getFileCommits();

        try {
            $this->db->beginTransaction();
            foreach ($commits as $commit) {
                $insertHistory->bindValue(':url', $relativeFilePath);
                $insertHistory->bindValue(':git_hash', DbValueGuard::truncate($commit['hash'], DbValueGuard::GIT_HASH));
                $insertHistory->bindValue(':ts', $commit['timestamp']);
                $insertHistory->bindValue(':name', DbValueGuard::truncate($commit['name'], DbValueGuard::NAME));
                $insertHistory->bindValue(':email', DbValueGuard::truncate($commit['email'], DbValueGuard::EMAIL));
                $insertHistory->bindValue(':message', DbValueGuard::truncate($commit['message'], DbValueGuard::MESSAGE));
                $insertHistory->bindValue(':added', (int)$commit['added']);
                $insertHistory->bindValue(':removed', (int)$commit['removed']);
                $insertHistory->execute();
            }
            $this->db->commit();
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
        }
    }

    private function indexWords(\PDO $db, array $words, $version, $language): array
    {
        $map = [];
        $version = DbValueGuard::truncate($version, DbValueGuard::VERSION);
        $language = DbValueGuard::truncate($language, DbValueGuard::LANGUAGE);

        try {
            $db->beginTransaction();
            $fetchStmt = $db->prepare('SELECT id FROM Search_Terms WHERE term = :term AND version = :version AND language = :language');
            $insertStmt = $db->prepare('INSERT INTO Search_Terms (term, phonetic_term, language, version, total_occurrences) VALUES (:term, :phonetic_term, :language, :version, 0)');

            foreach ($words as $word) {
                if (!DbValueGuard::fits($word, DbValueGuard::TERM)) {
                    continue;
                }

                $fetchStmt->bindValue(':term', $word);
                $fetchStmt->bindValue(':version', $version);
                $fetchStmt->bindValue(':language', $language);
                if ($fetchStmt->execute() && $termId = $fetchStmt->fetch(\PDO::FETCH_COLUMN)) {
                    $map[$word] = $termId;
                    continue;
                }

                try {
                    $insertStmt->bindValue(':term', $word);
                    $insertStmt->bindValue(':phonetic_term', DbValueGuard::truncate(SearchService::fuzzyTerm($word, $language), DbValueGuard::PHONETIC_TERM));
                    $insertStmt->bindValue(':language', $language);
                    $insertStmt->bindValue(':version', $version);
                    $insertStmt->execute();
                    $map[$word] = $db->lastInsertId();
                } catch (\PDOException $e) {
                    continue;
                }
            }

            $db->commit();
        } catch (\PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
        }

        return $map;
    }
}
