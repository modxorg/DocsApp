<?php

namespace MODXDocs\CLI\Commands;

use MODXDocs\CLI\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'stats:cleanup',
    description: 'Removes search and page-not-found analytics records older than a given number of days (default 90). Intended to be run via cron.'
)]
class StatsCleanup extends Command
{
    protected function configure(): void
    {
        $this->addOption(
            'days',
            null,
            InputOption::VALUE_REQUIRED,
            'Delete records whose last_seen is older than this many days',
            90
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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

        $days = (int) $input->getOption('days');
        if ($days < 1) {
            $output->writeln('<error>--days must be a positive integer</error>');
            return 1;
        }

        /** @var \PDO $db */
        $db = $docsApp->getContainer()->get('db');
        $cutoff = time() - ($days * 86400);

        $output->writeln(sprintf(
            '<info>Deleting Searches and PageNotFound records with last_seen before %s (%d days ago)...</info>',
            date('Y-m-d H:i:s', $cutoff),
            $days
        ));

        try {
            $searches = $db->prepare('DELETE FROM Searches WHERE last_seen < :cutoff');
            $searches->bindValue(':cutoff', $cutoff, \PDO::PARAM_INT);
            $searches->execute();
            $searchesDeleted = $searches->rowCount();

            $notFound = $db->prepare('DELETE FROM PageNotFound WHERE last_seen < :cutoff');
            $notFound->bindValue(':cutoff', $cutoff, \PDO::PARAM_INT);
            $notFound->execute();
            $notFoundDeleted = $notFound->rowCount();
        } catch (\PDOException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 1;
        }

        $output->writeln(sprintf(
            'Done! Removed <info>%d</info> search record(s) and <info>%d</info> not-found record(s).',
            $searchesDeleted,
            $notFoundDeleted
        ));

        return 0;
    }
}
