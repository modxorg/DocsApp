<?php

namespace MODXDocs\CLI\Commands\Index;

use MODXDocs\CLI\Application;
use MODXDocs\Services\DocumentService;
use MODXDocs\Services\IndexService;
use MODXDocs\Services\SearchService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class File extends Command {
    protected static $defaultName = 'index:file';

    public function getDescription()
    {
        return 'Updates indices (search, history) for a specific file. Run after `index:init`';
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

        $this->docService = $container->get(DocumentService::class);
        $this->searchService = $container->get(SearchService::class);
        $this->indexService = $container->get(IndexService::class);

        $files = $input->getArgument('files');
        if (!is_array($files) || count($files) === 0) {
            throw new \InvalidArgumentException('You must provide at least one filename to index.');
        }

        foreach ($files as $file) {
            [$version, $language] = explode('/', trim($file, '/'));
            $output->writeln('- Updating index for ' . $file);
            $result = $this->indexService->indexFile($language, $version, $file);
            if ($result !== true) {
                $output->writeln('<error>- Error indexing ' . $file . ': ' . $result . '</error>');
            }
        }

        return 0;
    }

    protected function configure()
    {
        $this
            ->addArgument('files', InputArgument::IS_ARRAY, 'File names, relative to the documentation root, that need to be indexed.')
        ;
    }
}
