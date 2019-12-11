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

        $db->exec('CREATE TABLE IF NOT EXISTS Translations (
  en VARCHAR(255) PRIMARY KEY,
  ru VARCHAR(255),
  nl VARCHAR(255)
) ');



        $db->exec('CREATE TABLE IF NOT EXISTS Search_Terms (
  term VARCHAR(100),
  phonetic_term VARCHAR(100),
  language VARCHAR(10),
  version VARCHAR(25),
  total_occurrences VARCHAR(255)
)');
        try {
            $db->exec('CREATE INDEX term ON Search_Terms (term)');
        }
        catch (\PDOException $e) {
            $output->writeln('<comment>Error creating Search_Terms.phonetic_term index: ' . $e->getMessage() . '</comment>');
        }
        try {
            $db->exec('CREATE INDEX phonetic_term ON Search_Terms (phonetic_term)');
        }
        catch (\PDOException $e) {
            $output->writeln('<comment>Error creating Search_Terms.phonetic_term index: ' . $e->getMessage() . '</comment>');
        }
        try {
            $db->exec('CREATE INDEX language ON Search_Terms (language)');
        }
        catch (\PDOException $e) {
            $output->writeln('<comment>Error creating Search_Terms.language index: ' . $e->getMessage() . '</comment>');
        }
        try {
            $db->exec('CREATE INDEX version ON Search_Terms (version)');
        }
        catch (\PDOException $e) {
            $output->writeln('<comment>Error creating Search_Terms.version index: ' . $e->getMessage() . '</comment>');
        }



        $db->exec('CREATE TABLE IF NOT EXISTS Search_Terms_Occurrences (
  page INT(64),
  term INT(64),
  weight SMALLINT(2)
)');
        try {
            $db->exec('CREATE INDEX term ON Search_Terms_Occurrences (term)');
        }
        catch (\PDOException $e) {
            $output->writeln('<comment>Error creating Search_Terms_Occurrences.term index: ' . $e->getMessage() . '</comment>');
        }
        try {
            $db->exec('CREATE INDEX page ON Search_Terms_Occurrences (page)');
        }
        catch (\PDOException $e) {
            $output->writeln('<comment>Error creating Search_Terms_Occurrences.page index: ' . $e->getMessage() . '</comment>');
        }



        $db->exec('CREATE TABLE IF NOT EXISTS Search_Pages (
  url VARCHAR(100) PRIMARY KEY,
  title VARCHAR(190)
)');


        $db->exec('CREATE TABLE IF NOT EXISTS Searches (
  search_query VARCHAR(190),
  result_count INTEGER(10),
  search_count INTEGER(10),
  first_seen INTEGER(15),
  last_seen INTEGER(15)
)');
        try {
            $db->exec('CREATE INDEX search_query ON Searches (search_query)');
            $db->exec('CREATE INDEX result_count ON Searches (result_count)');
            $db->exec('CREATE INDEX first_seen ON Searches (first_seen)');
            $db->exec('CREATE INDEX last_seen ON Searches (last_seen)');
        }
        catch (\PDOException $e) {
            $output->writeln('<comment>Error creating index for Searches table: ' . $e->getMessage() . '</comment>');
        }

        $db->exec('CREATE TABLE IF NOT EXISTS PageNotFound (
  url VARCHAR(190),
  hit_count INTEGER(10),
  last_seen INTEGER(15)
)');
        try {
            $db->exec('CREATE INDEX url ON PageNotFound (url)');
        }
        catch (\PDOException $e) {
            $output->writeln('<comment>Error creating index for PageNotFound table: ' . $e->getMessage() . '</comment>');
        }
        try {
            $db->exec('CREATE INDEX hit_count ON PageNotFound (hit_count)');
        }
        catch (\PDOException $e) {
            $output->writeln('<comment>Error creating index for PageNotFound table: ' . $e->getMessage() . '</comment>');
        }
        try {
            $db->exec('CREATE INDEX last_seen ON PageNotFound (last_seen)');
        }
        catch (\PDOException $e) {
            $output->writeln('<comment>Error creating index for PageNotFound table: ' . $e->getMessage() . '</comment>');
        }

        return 0;
    }
}
