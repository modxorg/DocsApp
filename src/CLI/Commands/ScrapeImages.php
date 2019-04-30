<?php

namespace MODXDocs\CLI\Commands;

use MODXDocs\CLI\Application;
use MODXDocs\Exceptions\NotFoundException;
use MODXDocs\Model\PageRequest;
use MODXDocs\Navigation\Tree;
use MODXDocs\Services\DocumentService;
use MODXDocs\Services\VersionsService;
use Spatie\YamlFrontMatter\YamlFrontMatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class ScrapeImages extends Command {
    protected static $defaultName = 'scrape';

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
        /** @var DocumentService $docs */
        $docs = $container->get(DocumentService::class);

        $root = VersionsService::getDocsRoot();
        $downloadedRoot = '/Users/mhamstra/Sites/docs.modx.local/download/';
        $targetRoot = $downloadedRoot . '/c/';

        $tree = Tree::get('2.x', 'en');
        $images = [];
        foreach ($tree->getAllItems() as $item) {

            $itemFile = $root . $item['file'];

            $contents = file_get_contents($itemFile);
            $parsedContents = YamlFrontMatter::parseFile($itemFile);
            $body = $parsedContents->body();
            if (empty($body)) {
                $output->writeln($item['file'] . ' is empty');
            }

            $pattern = "/\!\[([^]]+)?\]\((?P<url>.+?)\)/";
            preg_match_all($pattern, $body, $matches);

            if (count($matches[0]) === 0) {
                continue;
            }

            $files = [];
            foreach ($matches[0] as $idx => $match) {
                $url = $rawUrl = $matches['url'][$idx];
                if (strpos($url, '?') !== false) {
                    $url = substr($url, 0, strpos($url, '?'));
                }
                $files[] = [
                    'match' => $match,
                    'url' => $url,
                    'raw_url' => $rawUrl,
                ];
            }

            $output->writeln('<info>Parsing files in ' . $itemFile . '...</info>');

            $changed = false;
            foreach ($files as &$file) {
                $oldUrl = $file['url'];
                if (strpos(ltrim($oldUrl, '/'), 'download/') === 0) {
                    // Make sure we use full-size images
                    $oldUrl = '/' . ltrim($oldUrl, '/');
                    $oldUrl = str_replace('/thumbnails/', '/attachments/', $oldUrl);
                    $oldPath = $downloadedRoot . substr($oldUrl, strlen('/download/'));

                    if (!file_exists($oldPath)) {
                        $output->writeln('<comment>- $oldPath ' . $oldPath . ' does not exist</comment>');
                    }


                    $targetUrl = strtolower(ltrim(basename($file['url']), '/'));
                    $targetUrl = str_replace([' ', '%20'], '-', $targetUrl);
                    if (strpos($itemFile, 'index.md') !== false) {
                        $targetPath = $root . dirname($item['file']) . '/' . $targetUrl;
                    }
                    else {
                        $targetPath = $root . dirname($item['file']) . '/' . $targetUrl;
                    }

                    $output->writeln('- ' . $file['url'] . ' => ' . $targetPath . ' [' . $targetUrl . ']');

                    if (!mkdir($concurrentDirectory = dirname($targetPath), 0777,
                            true) && !is_dir($concurrentDirectory)) {
                        throw new \RuntimeException(sprintf('Directory "%s" was not created',
                            $concurrentDirectory));
                    }
                    if (!copy($oldPath, $targetPath)) {
                        $output->writeln('<comment>- ERROR copying ' . $oldPath . ' => ' . $targetPath . '</comment>');
                    }
                    else {
                        $contents = str_replace($file['raw_url'], $targetUrl, $contents);
                        $changed = true;
                    }
                }
                else {
                    $output->writeln('<comment>- $oldUrl ' . $oldUrl . ' not in /download/</comment>');
                }
            }
            unset($file);
            if ($changed) {
                file_put_contents($itemFile, $contents);
            }




        }

        return 0;
    }


    private function getCommitHash($path) {
        $process = new Process(['git', 'rev-parse', 'HEAD']);
        $process->setWorkingDirectory($path);
        $process->run();
        $out = $process->getOutput();
        return trim($out);
    }
}
