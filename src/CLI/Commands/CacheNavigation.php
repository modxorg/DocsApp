<?php

namespace MODXDocs\CLI\Commands;

use MODXDocs\Navigation\Tree;
use MODXDocs\Services\VersionsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class CacheNavigation extends Command {
    protected static $defaultName = 'cache:navigation';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Regenerating navigation cache...</info>');

        $versions = array_keys(VersionsService::getAvailableVersions());
        $languages = ['en', 'ru', 'nl', 'es'];
        foreach ($versions as $version) {
            foreach ($languages as $language) {
                $output->writeln('- ' . $version. '/' . $language . '');
                Tree::get($version, $language, true);
            }
        }

        return 0;
    }
}
