<?php

namespace MODXDocs\CLI\Commands;

use MODXDocs\CLI\Application;
use MODXDocs\Services\SitemapService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'sitemap:generate',
    description: 'Generates a sitemap index and per-version/language sitemap XML files under public/. Run after documentation sources change; also invoked by `sources:update`.'
)]
class SitemapGenerate extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $app = $this->getApplication();
        if (!$app instanceof Application) {
            $output->writeln('<error>Command not loaded on right Application</error>');
            return 1;
        }
        if (!$app->getDocsApp()) {
            $output->writeln('<error>DocsApp not available</error>');
            return 1;
        }

        $output->writeln('<info>Generating sitemaps...</info>');

        try {
            $result = (new SitemapService())->generate();
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 1;
        }

        $output->writeln(sprintf(
            'Done! Wrote <info>%d</info> files covering <info>%d</info> URLs.',
            $result['files'],
            $result['urls']
        ));

        if ($output->isVerbose()) {
            foreach ($result['sitemaps'] as $loc) {
                $output->writeln('  - ' . $loc);
            }
        }

        return 0;
    }
}
