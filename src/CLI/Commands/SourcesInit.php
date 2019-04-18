<?php

namespace MODXDocs\CLI\Commands;

use MODXDocs\CLI\Application;
use MODXDocs\Services\VersionsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class SourcesInit extends Command {
    protected static $defaultName = 'sources:init';

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

        /** @var VersionsService $versionService */
        $versionService = $container->get(VersionsService::class);
        $definition = $versionService::getAvailableVersions(false);

        if (empty($definition)) {
            $output->writeln('<error>Doc sources definition is missing or invalid JSON</error>');
            return 1;
        }

        $output->write('<info>Found ' . count($definition) . ' documentation sources: ' . implode(', ', array_keys($definition)) . '</info>');

        foreach ($definition as $key => $info) {
            $output->writeln("\n<fg=yellow;options=bold>Initialising \"{$key}\" ({$info['type']})</>");
            switch ($info['type']) {
                case 'git':
                    $this->initRepository($output, $key, $info['url'], $info['branch']);
                break;

                case 'local':
                    $root = VersionsService::getDocsRoot();
                    if (file_exists($root . $key) && is_dir($root . $key)) {
                        $output->writeln('Source ' . $key . ' is of type local, and the directory exists.');
                    }
                    else {
                        $output->writeln('<comment>Source ' . $key . ' is of type local, so you have to initialise it manually.</comment>');
                    }
                    break;

                default:
                    $output->writeln('<error>Unsupported type "' . $info['type'] . '"</error>');
                    break;
            }
        }

        return 0;
    }

    public function initRepository(OutputInterface $output, $version, $url, $branch)
    {
        $root = VersionsService::getDocsRoot();
        $fullPath = $root . $version . '/';

        if (file_exists($fullPath) && is_dir($fullPath) && is_dir($fullPath . '.git/')) {
            $output->writeln('<error>Already a git repository: ' . $fullPath . '</error>');
        }
        else {
            $output->writeln('Cloning ' . $url . ' on branch ' . $branch . ' into docs directory ' . $version . '...');
            $clone = new Process(['git', 'clone', '-b', $branch, '--single-branch', $url, $version]);
            $clone->setWorkingDirectory($root);
            if ($output->isVerbose()) {
                $output->writeln('<info>' . $clone->getCommandLine() . '</info>');
            }

            $clone->run(function ($type, $buffer) use ($output) {
                $output->writeln('' . $buffer);
            });
        }
    }
}
