<?php

namespace MODXDocs\CLI\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class CacheClear extends Command {
    protected static $defaultName = 'cache:clear';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $root = rtrim(getenv('CACHE_DIRECTORY'), '/') . '/';

        $output->writeln('<info>Clearing "nav" and "rendered" cache..</info>');

        $clone = new Process(['rm', '-r', $root . 'nav/', $root . 'rendered/']);
        $clone->setWorkingDirectory($root);
        if ($output->isVerbose()) {
            $output->writeln('<info>' . $clone->getCommandLine() . '</info>');
        }

        $clone->run(function ($type, $buffer) use ($output) {
            $output->writeln('' . $buffer);
        });

        return 0;
    }
}
