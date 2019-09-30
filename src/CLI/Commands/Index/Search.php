<?php

namespace MODXDocs\CLI\Commands\Index;

use MODXDocs\CLI\Application;
use MODXDocs\Exceptions\NotFoundException;
use MODXDocs\Model\PageRequest;
use MODXDocs\Navigation\Tree;
use MODXDocs\Services\DocumentService;
use MODXDocs\Services\IndexService;
use MODXDocs\Services\SearchService;
use MODXDocs\Services\VersionsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Search extends Command {
    protected static $defaultName = 'index:search';

    public function getDescription()
    {
        return 'Completely re-indexes the documentation for search purposes. Run after `index:init`';
    }

    /** @var DocumentService */
    protected $docService;
    /** @var SearchService */
    protected $searchService;
    /** @var IndexService */
    protected $indexService;

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
        $this->indexService = $container->get(IndexService::class);

        // Wipe the current index
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
                    $output->writeln('<comment>Indexing ' . $item['file'] . '...</comment>');

                    $result = $this->indexService->indexFile($language, $version, $item['uri']);
                    if ($result !== true) {
                        $output->writeln('<error>- Error: ' . $result . '</error>');
                    }
                }
            }
        }

        $took = microtime(true) - $time;
        $output->writeln('Done! Indexed ' . $count . ' files across ' . count($versions) . ' versions and ' . count($languages) . ' in ' . $took . 'ms.');
        return 0;
    }
}
