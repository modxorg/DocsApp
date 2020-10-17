<?php

namespace MODXDocs\CLI\Commands\Index;

use MODXDocs\CLI\Application;
use MODXDocs\Navigation\Tree;
use MODXDocs\Services\VersionsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Translations extends Command {
    protected static $defaultName = 'index:translations';

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

        // Wipe the current index
        $db->exec('DELETE FROM Translations');

        foreach (array_keys(VersionsService::getAvailableVersions(false)) as $version) {
            $output->writeln('<info>Indexing translations for ' . $version . '..</info>');
            $this->indexVersion($output, $db, $version);
        }

        return 0;
    }

    /**
     * @param OutputInterface $output
     * @param $db
     */
    protected function indexVersion(OutputInterface $output, \PDO $db, $version): void
    {
        $navEn = Tree::get($version, 'en');
        $mapEn = [];
        $items = $navEn->getAllItems();
        foreach ($items as $item) {
            $mapEn[$item['uri']] = [];
        }

        $languages = ['ru', 'nl', 'es'];

        foreach ($languages as $language) {
            $output->writeln('<info>- Processing ' . $version . '/' . $language . '...</info>');
            $languageNav = Tree::get($version, $language);
            $languageItems = $languageNav->getAllItems();

            foreach ($languageItems as $item) {
                $translationOf = array_key_exists('translation',
                    $item) ? $item['translation'] : str_replace('/' . $language . '/', '/en/', $item['uri']);
                if (strpos($translationOf, '/' . $version . '/en/') !== 0) {
                    $translationOf = '/' . $version . '/en/' . trim($translationOf, '/');
                }
                if (array_key_exists($translationOf, $mapEn)) {
                    $mapEn[$translationOf][$language] = $item['uri'];
                } else {
                    $output->writeln('<comment>Original path "' . $translationOf . '" not found for "' . $item['file'] . '"</comment>');
                }
            }
        }

        $mapEn = array_filter($mapEn, function ($item) {
            return count($item) > 0;
        });

        $fetch = 'SELECT * FROM Translations WHERE en = :source';
        $fetchStmt = $db->prepare($fetch);
        $fetchStmt->bindParam(':source', $source);

        $insert = 'INSERT INTO Translations (en, ru, nl, es) VALUES (:en, :ru, :nl, :es)';
        $insertStmt = $db->prepare($insert);
        foreach ($mapEn as $source => $translations) {
            $insertStmt->bindValue(':en', $source);
            $insertStmt->bindValue(':ru', $translations['ru'] ?? '');
            $insertStmt->bindValue(':nl', $translations['nl'] ?? '');
            $insertStmt->bindValue(':es', $translations['es'] ?? '');

            $insertStmt->execute();
        }
    }
}
