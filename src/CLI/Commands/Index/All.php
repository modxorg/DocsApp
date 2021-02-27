<?php

namespace MODXDocs\CLI\Commands\Index;

use MODXDocs\CLI\Application;
use MODXDocs\Navigation\Tree;
use MODXDocs\Services\DocumentService;
use MODXDocs\Services\IndexService;
use MODXDocs\Services\SearchService;
use MODXDocs\Services\VersionsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class All extends Command
{
    protected static $defaultName = 'index:all';

    public function getDescription()
    {
        return 'Completely re-indexes the documentation for the search and file history. Run after `index:init`. For selectively updating changed sources, `sources:update` automatically runs the indexer for changed files, or you can call `index:file` with specific file names.';
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

        $search = $input->getOption('skip-search') ? false : true;
        $history = $input->getOption('skip-history') ? false : true;
        $this->indexService->setIndexOptions($search, $history);
        if (!$search) {
            $output->writeln('<comment>- Will not index search terms.</comment>');
        }
        else {
            $db->exec('DELETE FROM Search_Terms');
            $db->exec('DELETE FROM Search_Pages');
            $db->exec('DELETE FROM Search_Terms_Occurrences');
        }
        if (!$history) {
            $output->writeln('<comment>- Will not index history/contributors.</comment>');
        }

        // Wipe the current index

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

    protected function configure()
    {
        $this
            ->addOption('skip-search', null, InputOption::VALUE_NONE, 'Specify this option to skip indexing this page for the search functionality.')
            ->addOption('skip-history', null, InputOption::VALUE_NONE, 'Specify this option to skip indexing the history of this page to render contributors.')
        ;

        $this->setAliases(['index:search']);
    }
}
