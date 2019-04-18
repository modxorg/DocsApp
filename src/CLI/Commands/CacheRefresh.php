<?php

namespace MODXDocs\CLI\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class CacheRefresh extends Command {
    protected static $defaultName = 'cache:refresh';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $root = rtrim(getenv('CACHE_DIRECTORY'), '/') . '/';

        $output->writeln('<info>Emptying caches...</info>');

        $directories = [
            $root . 'nav/', // @deprecated, this was from the old NavigationService which is no longer being written to
            $root . 'rendered/',
            $root . 'twig/',
        ];
        foreach ($directories as $directory) {
            if (file_exists($directory) && is_dir($directory)) {
                $output->writeln('- Removing: ' . $directory);
                $rm = new Process(['rm', '-r', $directory]);
                $rm->setWorkingDirectory($root);
                if ($output->isVerbose()) {
                    $output->writeln('<info>' . $rm->getCommandLine() . '</info>');
                }

                $rm->run(function ($type, $buffer) use ($output) {
                    $output->writeln('' . $buffer);
                });
            }
            else {
                $output->writeln('- Already empty: ' . $directory);
            }
        }

        $command = $this->getApplication()->find('cache:navigation');
        $command->run(new ArrayInput([
            'command' => 'cache:navigation',
        ]), $output);

        return 0;
    }
}
