<?php

namespace MODXDocs\CLI\Commands\Index;

use MODXDocs\CLI\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'index:init')]
class Init extends Command
{
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
        $container = $docsApp->getContainer();

        /** @var \PDO $db */
        $db = $container->get('db');

        $db->exec('CREATE TABLE IF NOT EXISTS Translations (
  en VARCHAR(255) PRIMARY KEY,
  ru VARCHAR(255),
  nl VARCHAR(255),
  es VARCHAR(255),
  INDEX idx_translations_ru (ru),
  INDEX idx_translations_nl (nl),
  INDEX idx_translations_es (es)
)');

        try {
            $db->exec('ALTER TABLE Translations ADD COLUMN es VARCHAR(255)');
        } catch (\PDOException $e) {
            $output->writeln('<comment>Error adding Translations.es column: ' . $e->getMessage() . '</comment>');
        }
        try {
            $db->exec('CREATE INDEX idx_translations_es ON Translations (es)');
        } catch (\PDOException $e) {
            $output->writeln('<comment>Error creating Translations.es index: ' . $e->getMessage() . '</comment>');
        }

        $db->exec('CREATE TABLE IF NOT EXISTS Search_Terms (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  term VARCHAR(100) NOT NULL,
  phonetic_term VARCHAR(100),
  language VARCHAR(10),
  version VARCHAR(25),
  total_occurrences INT UNSIGNED DEFAULT 0,
  INDEX idx_search_terms_term (term),
  INDEX idx_search_terms_phonetic_term (phonetic_term),
  INDEX idx_search_terms_language (language),
  INDEX idx_search_terms_version (version)
)');

        $db->exec('CREATE TABLE IF NOT EXISTS Search_Pages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  url VARCHAR(255) NOT NULL,
  title VARCHAR(190),
  UNIQUE INDEX idx_search_pages_url (url)
)');

        $db->exec('CREATE TABLE IF NOT EXISTS Search_Terms_Occurrences (
  page BIGINT UNSIGNED NOT NULL,
  term BIGINT UNSIGNED NOT NULL,
  weight SMALLINT NOT NULL,
  INDEX idx_occurrences_term (term),
  INDEX idx_occurrences_page (page)
)');

        $db->exec('CREATE TABLE IF NOT EXISTS Searches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  search_query VARCHAR(190),
  result_count INT UNSIGNED DEFAULT 0,
  search_count INT UNSIGNED DEFAULT 0,
  first_seen BIGINT UNSIGNED,
  last_seen BIGINT UNSIGNED,
  INDEX idx_searches_search_query (search_query),
  INDEX idx_searches_result_count (result_count),
  INDEX idx_searches_first_seen (first_seen),
  INDEX idx_searches_last_seen (last_seen)
)');

        $db->exec('CREATE TABLE IF NOT EXISTS PageNotFound (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  url VARCHAR(255),
  hit_count INT UNSIGNED DEFAULT 0,
  last_seen BIGINT UNSIGNED,
  INDEX idx_pagenotfound_url (url),
  INDEX idx_pagenotfound_hit_count (hit_count),
  INDEX idx_pagenotfound_last_seen (last_seen)
)');

        $db->exec('CREATE TABLE IF NOT EXISTS Page_History (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  url VARCHAR(255),
  git_hash VARCHAR(190),
  ts BIGINT UNSIGNED,
  name VARCHAR(190),
  email VARCHAR(190),
  message VARCHAR(500),
  added INT UNSIGNED DEFAULT 0,
  removed INT UNSIGNED DEFAULT 0,
  INDEX idx_page_history_url (url),
  INDEX idx_page_history_ts (ts),
  INDEX idx_page_history_email (email)
)');

        try {
            $db->exec('ALTER TABLE Page_History ADD COLUMN added INT UNSIGNED DEFAULT 0');
        } catch (\PDOException $e) {
            $output->writeln('<comment>Error adding Page_History.added column: ' . $e->getMessage() . '</comment>');
        }
        try {
            $db->exec('ALTER TABLE Page_History ADD COLUMN removed INT UNSIGNED DEFAULT 0');
        } catch (\PDOException $e) {
            $output->writeln('<comment>Error adding Page_History.removed column: ' . $e->getMessage() . '</comment>');
        }

        return 0;
    }
}
