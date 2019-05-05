<?php



namespace MODXDocs\Model;

use MODXDocs\Services\DocumentService;

class SearchResults {

    /**
     * @var DocumentService
     */
    private $documentService;
    /**
     * @var SearchQuery
     */
    private $query;

    private $resultDetails = [];
    private $results = [];

    public function __construct(DocumentService $documentService, SearchQuery $query)
    {
        $this->documentService = $documentService;
        $this->query = $query;
        $this->exactTerms = $query->getExactTerms();
        $this->fuzzyTerms = $query->getFuzzyTerms();
    }

    public function addMatch($termId, $page, $weight): void
    {
        if (!array_key_exists($page, $this->results)) {
            $this->results[$page] = 0.00;
        }
        if (!array_key_exists($page, $this->resultDetails)) {
            $this->resultDetails[$page] = [];
        }

        // Reduce weight for fuzzy matches
        $isExact = array_key_exists($termId, $this->exactTerms);
        if (!$isExact) {
            $weight *= 0.75;
        }

        $this->results[$page] += $weight;

//        if (!array_key_exists($page, $this->matches)) {
//            $this->matches[$page] = [];
//        }
//        $this->matches[$page][] = [
//            'term' => $termId, 'weight' => $weight
//        ];

        $this->resultDetails[$page][] = [$isExact, $termId, $weight];
    }

    public function getDetailedMatches($page)
    {
        if (array_key_exists($page, $this->resultDetails)) {
            return $this->resultDetails[$page];
        }
        return [];
    }

    public function process(): void
    {
        // Sort best match first
        arsort($this->results, SORT_NUMERIC);
    }

    public function getCount(): int
    {
        return count($this->results);
    }

    public function getResults($offset, $limit = 10)
    {
        return array_slice($this->results, $offset, $limit, true);
    }
}
