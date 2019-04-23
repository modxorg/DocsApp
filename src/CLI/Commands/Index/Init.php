<?php

namespace MODXDocs\CLI\Commands\Index;

use MODXDocs\CLI\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Init extends Command {
    protected static $defaultName = 'index:init';

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

        /** @var \PDO $db */
        $db = $container->get('db');

        $db->exec('CREATE TABLE Translations (
  en VARCHAR(255) PRIMARY KEY,
  ru VARCHAR(255),
  nl VARCHAR(255) 
)');


        return 0;
    }
}
