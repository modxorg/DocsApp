<?php

namespace MODXDocs\Controllers;

use DirectoryIterator;
use Knp\Menu\Matcher\Matcher;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment;
use MODXDocs\Helpers\LinkRenderer;
use MODXDocs\Helpers\TocRenderer;
use TeamTNT\TNTSearch\Exceptions\IndexNotFoundException;
use TeamTNT\TNTSearch\TNTSearch;
use Webuni\CommonMark\TableExtension\TableExtension;
use Slim\Exception\NotFoundException;
use Slim\Http\Request;
use Slim\Http\Response;
use Spatie\YamlFrontMatter\YamlFrontMatter;
use TOC\MarkupFixer;
use TOC\TocGenerator;

class Search extends Doc
{
//    public function initialize(Request $request, Response $response, array $args = array())
//    {
//        $this->setVersion($request->getAttribute('version'));
//        $this->setLanguage($request->getAttribute('language'));
//        $this->getTopNavigation();
//        $this->getVersions();
//        $this->getNavigation();
//        return true;
//    }

    public function get(Request $request, Response $response, array $args = array())
    {
        $init = $this->initialize($request, $response, $args);
        if ($init !== true) {
            return $init;
        }

        $this->setVariable('page_title', 'Search the MODX Documentation');


        return $this->render('search.twig');
    }

    public function post(Request $request, Response $response, array $args = array())
    {
        $init = $this->initialize($request, $response, $args);
        if ($init !== true) {
            return $init;
        }

        $query = $this->request->getParam('query');
        $this->setVariable('query', $query);

        $this->setVariable('page_title', 'Search the MODX Documentation');

        try {
            $tnt = $this->getSearchIndex();

            $output = [];
            $results = $tnt->searchBoolean($query, 20);

            foreach ($results as $result) {

                $file = new \SplFileInfo($result['path']);
                $filePath = $file->getPathname();
                $filePath = strpos($filePath, '.md') !== false ? substr($filePath, 0, strpos($filePath, '.md')) : $filePath;
                $relativeFilePath = str_replace($this->basePath, '', $filePath);

                $fileContents = file_get_contents($file->getPathname());
                $obj = YamlFrontMatter::parse($fileContents);
                $content = $obj->body();
                $content = $this->parseMarkdown($content);
                $content = strip_tags($content);

                $data = [
                    'title' => $this->getTitle($file),
                    'snippet' => $tnt->snippet($query, $content),
                    'url' => $this->container->router->pathFor('documentation', [
                        'language' => $this->language,
                        'version' => $this->version,
                        'path' => $relativeFilePath,
                    ]),
                ];

                $output[] = $data;
            }
            $this->setVariable('results', $output);

        } catch (IndexNotFoundException $e) {
            $this->setVariable('error', ' Sorry, the index for ' . $this->version . ' in ' . $this->language . ' is not currently available.');
        }


        return $this->render('search.twig');
    }

    /**
     * @return TNTSearch
     * @throws \TeamTNT\TNTSearch\Exceptions\IndexNotFoundException
     */
    private function getSearchIndex(): TNTSearch
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig([
            'storage' => $this->container['settings']['searchIndex'],
            'driver' => 'filesystem',
        ]);

        $tnt->selectIndex($this->version . '_' . strtolower($this->language));
        $tnt->asYouType = true;

        return $tnt;
    }

}
