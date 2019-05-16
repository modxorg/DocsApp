<?php

namespace MODXDocs\CLI\Commands\Index;

use MODXDocs\CLI\Application;
use MODXDocs\Exceptions\NotFoundException;
use MODXDocs\Model\PageRequest;
use MODXDocs\Navigation\Tree;
use MODXDocs\Services\DocumentService;
use MODXDocs\Services\SearchService;
use MODXDocs\Services\VersionsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Search extends Command {
    protected static $defaultName = 'index:search';

    /** @var DocumentService */
    protected $docService;
    /** @var SearchService */
    protected $searchService;

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $app = $this->getApplication();
        if (!$app instanceof Application) {
            $output->writeln('<error>Command not loaded on right Application</error>');
            return 1;
        }
        $docsApp = $app->getDocsApp();
        if (!$docsApp) {
            $output->writeln('<error>DocsApp not available</error>');
            return 1;
        }
        $container = $docsApp->getContainer();

        $time = microtime(true);

        /** @var \PDO $db */
        $db = $container->get('db');

        $this->docService = $container->get(DocumentService::class);
        $this->searchService = $container->get(SearchService::class);

        // Wipe the current index
        // @todo Make this unnecessary by selectively removing/updating what needs updating instead
        $db->exec('DELETE FROM Search_Terms');
        $db->exec('DELETE FROM Search_Pages');
        $db->exec('DELETE FROM Search_Terms_Occurrences');

        $versions = array_keys(VersionsService::getAvailableVersions(false));
        $languages = ['en', 'ru', 'nl'];

        $count = 0;
        foreach ($versions as $version) {
            foreach ($languages as $language) {
                $nav = Tree::get($version, $language);
                foreach ($nav->getAllItems() as $item) {
                    $count++;
                    $this->indexFile($output, $db, $language, $version, [
                        'title' => $item['title'],
                        'file' => getenv('DOCS_DIRECTORY') . $item['file'],
                        'url' => $item['uri'],
                    ]);
                }
            }
        }

        $took = microtime(true) - $time;
        $output->writeln('Done! Indexed ' . $count . ' files across ' . count($versions) . ' versions and ' . count($languages) . ' in ' . $took . 'ms.');
        return 0;
    }

    private function indexFile(OutputInterface $output, \PDO $db, string $language, string $version, array $file)
    {
        $output->writeln('<comment>Indexing ' . $file['file'] . '...</comment>');

        $insertPage = $db->prepare('INSERT INTO Search_Pages (url, title) VALUES (:url, :title)');
        $insertTermOcc = $db->prepare('INSERT INTO Search_Terms_Occurrences (page, term, weight) VALUES (:page, :term, :weight)');

//            $db->exec('DELETE FROM Search_Pages WHERE url = ' . $file['url']);
        $insertPage->bindValue(':url', $file['url']);
        $insertPage->bindValue(':title', $file['title']);
        $insertPage->execute();

        $pageId = $db->lastInsertId();

        $titleMap = SearchService::filterStopwords($language, SearchService::stringToMap($file['title']));
        $titleTerms = $this->indexWords($db, array_keys($titleMap), $version, $language);

        $db->beginTransaction();
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
        $db->commit();

        try {
            $page = $this->docService->load(new PageRequest($version, $language, substr($file['url'], strlen('/' . $version . '/' . $language . '/'))));
        } catch (NotFoundException $e) {
            $output->writeln('<error>- Could not load file to index body</error>');
            return;
        }

        $toc = $page->getTableOfContents();
        $toc = strip_tags($toc);
        $tocMap = SearchService::filterStopwords($language, SearchService::stringToMap($toc));
        $tocTerms = $this->indexWords($db, array_keys($tocMap), $version, $language);

        $db->beginTransaction();
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
        $db->commit();

        $body = $page->getRenderedBody();
        $body = strip_tags($body);
        $bodyMap = SearchService::filterStopwords($language, SearchService::stringToMap($body));
        $bodyTerms = $this->indexWords($db, array_keys($bodyMap), $version, $language);

        $db->beginTransaction();
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
        $db->commit();
    }

    private function indexWords(\PDO $db, array $words, $version, $language)
    {
        $map = [];

        $db->beginTransaction();
        $fetchStmt = $db->prepare('SELECT rowid FROM Search_Terms WHERE term = :term AND version = :version AND language = :language');
        $insertStmt = $db->prepare('INSERT INTO Search_Terms (term, phonetic_term, language, version, total_occurrences) VALUES (:term, :phonetic_term, :language, :version, 0)');
        foreach ($words as $word) {
            $fetchStmt->bindValue(':term', utf8_encode($word));
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
