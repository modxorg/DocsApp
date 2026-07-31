<?php

namespace MODXDocs\Model;

use MODXDocs\Services\SearchService;
use voku\helper\StopWords;

class SearchQuery
{
    private SearchService $searchService;
    private string $queryString;
    private PageRequest $pageRequest;
    private array $stopWords;
    private array $exactTerms = [];
    private array $fuzzyTerms = [];
    private array $ignoredTerms = [];

    public function __construct(SearchService $searchService, $queryString, PageRequest $pageRequest)
    {
        $this->searchService = $searchService;
        $this->pageRequest = $pageRequest;
        $this->stopWords = (new StopWords())->getStopWordsFromLanguage($pageRequest->getLanguage());
        $this->parseQueryString($queryString);
    }

    private function parseQueryString($queryString): void
    {
        $this->queryString = $queryString;

        $q = strtolower(trim($queryString));
        $q = preg_split('/[\s\-\\\:]+/', $q, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($q as $term) {
            $this->addTerm($term);
        }
    }

    private function addTerm(string $term): void
    {
        // Force exact match with "quotes"
        if (strpos($term, '"') === 0 && strrpos($term, '"') === strlen($term) - 1) {
            $term = trim($term, '"');
            $references = $this->searchService->getExactTermReferences(
                $this->pageRequest->getVersionBranch(),
                $this->pageRequest->getLanguage(),
                $term
            );

            foreach ($references as $ref => $t) {
                $this->exactTerms[$ref] = $t;
            }
            return;
        }

        // Cleanup the string
        $term = trim($term, '"\'$,.-():;&#_?/\\');

        // Shorter than 2 characters? Ignore
        if (mb_strlen($term) < SearchService::MIN_TERM_LENGTH) {
            $this->ignoredTerms[] = $term;
            return;
        }

        // In the stopwords list? Ignore.
        if (in_array($term, $this->stopWords, true)) {
            $this->ignoredTerms[] = $term;
            return;
        }

        // Get fuzzy matches
        $references = array_replace(
            $this->searchService->getStartsWithReferences(
                $this->pageRequest->getVersionBranch(),
                $this->pageRequest->getLanguage(),
                $term
            ),
            $this->searchService->getFuzzyTermReferences(
                $this->pageRequest->getVersionBranch(),
                $this->pageRequest->getLanguage(),
                $term
            )
        );

        foreach ($references as $ref => $t) {
            if ($t === $term) {
                $this->exactTerms[$ref] = $t;
            } else {
                $this->fuzzyTerms[$ref] = $t;
            }
        }
    }


    /**
     * @return string
     */
    public function getQueryString(): string
    {
        return $this->queryString;
    }

    public function getAllTerms(): array
    {
        return array_unique($this->exactTerms + $this->fuzzyTerms);
    }

    public function getSearchTermReferences(): array
    {
        $all = array_merge(array_keys($this->exactTerms), array_keys($this->fuzzyTerms));
        $all = array_filter(array_unique($all));
        return $all;
    }

    public function getExactTerms(): array
    {
        return $this->exactTerms;
    }

    public function getFuzzyTerms(): array
    {
        return $this->fuzzyTerms;
    }

    public function getIgnoredTerms(): array
    {
        return $this->ignoredTerms;
    }
}
