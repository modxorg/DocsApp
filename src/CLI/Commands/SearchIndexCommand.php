<?php

namespace MODXDocs\CLI\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TeamTNT\TNTSearch\TNTSearch;

class SearchIndexCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'search:index';

    protected function configure()
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Indexes the documentation.')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Runs a full index.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $tnt = new TNTSearch;

        $config = [
            'storage' => '/Users/mhamstra/Sites/docs.modx.local/app/search_index/',
            'driver' => 'filesystem',
            'location' => '/Users/mhamstra/Sites/docs.modx.local/app/doc-sources/2.x/ru/',
            'extension' => 'md',
            'exclude' => []
        ];
        $tnt->loadConfig($config);

        $indexer = $tnt->createIndex('2.x_ru');
        $indexer->run();
    }
}
