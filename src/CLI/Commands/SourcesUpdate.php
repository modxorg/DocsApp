<?php

namespace MODXDocs\CLI\Commands;

use MODXDocs\CLI\Application;
use MODXDocs\Services\VersionsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class SourcesUpdate extends Command {
    protected static $defaultName = 'sources:update';

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
        $definition = $versionService->getDefinition();

        if (empty($definition)) {
            $output->writeln('<error>Doc sources definition is missing or invalid JSON</error>');
            return 1;
        }

        $output->write('<info>Found ' . count($definition) . ' documentation sources: ' . implode(', ', array_keys($definition)) . '</info>');

        foreach ($definition as $key => $info) {
            $output->writeln("\n<fg=yellow;options=bold>Updating \"{$key}\" ({$info['type']})</>");
            switch ($info['type']) {
                case 'git':
                    $this->updateRepository($output, $key, $info['url'], $info['branch']);
                break;

                case 'local':
                    $root = VersionsService::getDocsRoot();
                    if (!file_exists($root . $key) || !is_dir($root . $key)) {
                        $output->writeln('<error>Local source "' . $key . '" does not seem to exist.</error>');
                    }
                    else {
                        $output->writeln('Local sources require manual updates.');
                    }
                    break;

                default:
                    $output->writeln('<error>Unsupported type "' . $info['type'] . '"</error>');
                    break;
            }
        }

        return 0;
    }

    public function updateRepository(OutputInterface $output, $version, $url, $branch)
    {
        $root = VersionsService::getDocsRoot();
        $fullPath = $root . $version . '/';

        if (!file_exists($fullPath) || !is_dir($fullPath) || !is_dir($fullPath . '.git/')) {
            $output->writeln('<error>Target directory ' . $fullPath . ' does not exist or is not a git repository, you may need to run "sources:init"</error>');
            return;
        }


        $beforeHash = $this->getCommitHash($fullPath);
        $output->writeln("Before: <options=bold>{$beforeHash}</>");

        // Discard all local changes: git reset --hard origin/2.x
        $reset = new Process(['git', 'reset', '--hard', 'origin/' . $branch]);
        $reset->setWorkingDirectory($fullPath);
        if ($output->isVerbose()) {
            $output->writeln('<info>' . $reset->getCommandLine() . '</info>');
        }
        $reset->run(function ($type, $buffer) use ($output) {
            if ($type === 'err') {
                $output->writeln("<error> {$buffer} </error>");
            }
            else {
                $output->writeln($buffer);
            }
        });

        // Pull in from remote: git pull origin 2.x
        $pull = new Process(['git', 'pull', 'origin', $branch]);
        $pull->setWorkingDirectory($fullPath);
        if ($output->isVerbose()) {
            $output->writeln('<info>' . $pull->getCommandLine() . '</info>');
        }
        $pull->run(function ($type, $buffer) use ($output) {
            $output->writeln($buffer);
        });

        $afterHash = $this->getCommitHash($fullPath);
        $output->writeln("After: <options=bold>{$afterHash}</>");

        $changedFiles = '';
        $diff = new Process(['git', 'diff', '--name-only', $beforeHash, $afterHash]);
        $diff->setWorkingDirectory($fullPath);
        if ($output->isVerbose()) {
            $output->writeln('<info>' . $diff->getCommandLine() . '</info>');
        }
        $diff->run(function ($type, $buffer) use (&$changedFiles) {
            $changedFiles = trim($buffer);
        });
        $changedFiles = array_filter(explode("\n", $changedFiles));

        if (count($changedFiles) > 0) {
            $output->writeln('Changed files: <info>' . implode('</info>, <info>', $changedFiles) . '</info>');
        }
        else {
            $output->writeln('No changed files.');
        }
    }

    private function getCommitHash($path) {
        $process = new Process(['git', 'rev-parse', 'HEAD']);
        $process->setWorkingDirectory($path);
        $process->run();
        $out = $process->getOutput();
        return trim($out);
    }
}
