<?php

namespace MODXDocs\CLI\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TeamTNT\TNTSearch\TNTSearch;

class SearchCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'search:query';

    protected function configure()
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Searches the documentation.')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Searches for the provided terms.')
            ->addArgument('query', InputArgument::REQUIRED, 'What to search for')

        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig([
            'storage' => '/Users/mhamstra/Sites/docs.modx.local/app/search_index/',
            'driver' => 'filesystem',
        ]);

        $tnt->selectIndex('en');
        $tnt->asYouType = true;

        $results = $tnt->search($input->getArgument('query'), 10);
        var_dump(array_map(function($result) { return $result['path']; }, $results));
    }
}
