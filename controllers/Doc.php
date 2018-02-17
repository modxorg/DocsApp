<?php

namespace MODXDocs\Controllers;

use DirectoryIterator;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment;
use MODXDocs\Helpers\LinkRenderer;
use Webuni\CommonMark\TableExtension\TableExtension;
use Slim\Exception\NotFoundException;
use Slim\Http\Request;
use Slim\Http\Response;
use Spatie\YamlFrontMatter\YamlFrontMatter;
use TOC\MarkupFixer;
use TOC\TocGenerator;

class Doc extends Base
{
    protected $basePath;
    protected $baseUri;
    protected $file;
    protected $language;
    protected $docPath;

    public function setVersion($version)
    {
        $path = $this->container->get('settings')['docSources'] . '/' . $version . '/';
        if (!file_exists($path) || !is_dir($path)) {
            throw new NotFoundException($this->request, $this->response);
        }
        $this->setVariable('version', $version);
        $this->basePath = $path;
        $this->baseUri = '/' . $version;
    }

    public function setLanguage($language)
    {
        $path = $this->basePath . $language . '/';
        if (!file_exists($path) || !is_dir($path)) {
            throw new NotFoundException($this->request, $this->response);
        }
        $this->setVariable('language', $language);
        $this->language = $language;
        $this->basePath .= $language . '/';
        $this->baseUri .= '/' . $language . '/';
    }

    public function setDocPath($path)
    {
        if (is_array($path)) {
            $path = implode('/', $path);
        }
        $path = rtrim($path, '/');
        $file = $path . '.md';
        if (!file_exists($this->basePath . $file)) {
            // See if we have an index file instead
            $file = $path . '/index.md';

            if (!file_exists($this->basePath . $file)) {
                throw new NotFoundException($this->request, $this->response);
            }
        }
        $this->docPath = $path;
        $this->file = $file;
    }

    public function initialize(Request $request, Response $response, array $args = array())
    {
        parent::initialize($request, $response, $args);
        $this->setVersion($request->getAttribute('version'));
        $this->setLanguage($request->getAttribute('language'));
        $this->setDocPath($request->getAttribute('path'));
        $this->getVersions();
        return true;
    }

    public function get(Request $request, Response $response, array $args = array())
    {
        $init = $this->initialize($request, $response, $args);
        if ($init !== true) {
            return $init;
        }

        $fileContents = file_get_contents($this->basePath . $this->file);

        // Parse the front matter and make it available as meta
        $obj = YamlFrontMatter::parse($fileContents);
        $data = $obj->matter();
        $this->setVariable('meta', $data);

        // Generate a page title crumbs kinda thing
        $path = $request->getAttribute('path');
        $path = str_replace('-', ' ', $path);
        $path = explode('/', $path);
        $path = array_map('ucfirst', $path);
        $path = array_reverse($path);
        $path = implode(' / ', $path);
        $this->setVariable('page_title', $path);

        // Process the content
        $content = $obj->body();

        // Grab the markdown
        $environment = Environment::createCommonMarkEnvironment();
        $environment->addExtension(new TableExtension());
        $environment->addInlineRenderer('League\CommonMark\Inline\Element\Link',
            new LinkRenderer($this->baseUri)
        );
        $markdown = new CommonMarkConverter([
            'html_input' => 'allow',
        ], $environment);
        $content = $markdown->convertToHtml($content);

        $fixer = new MarkupFixer();
        $content = $fixer->fix($content);
        $this->setVariable('parsed', $content);

        // Generate table of contents
        $tocGenerator = new TocGenerator();
        $toc = $tocGenerator->getHtmlMenu($content, 2);
        $this->setVariable('toc', $toc);


        return $this->render('documentation.twig');
    }

    public function getVersions()
    {
        $dir = new DirectoryIterator($this->container->get('settings')['docSources']);

        $versions = [];
        foreach ($dir as $fileinfo) {
            if (!$fileinfo->isDir() || $fileinfo->isDot()) {
                continue;
            }

            $file = $fileinfo->getPathname() . '/' . $this->language . '/' . $this->docPath;
            $file = rtrim($file, '/');
            if (file_exists($file . '.md') || file_exists($file . '/index.md')) {
                $versions[] = [
                    'title' => $fileinfo->getFilename(),
                    'uri' => '/' . $fileinfo->getFilename() . '/' . $this->language . '/' . $this->docPath,
                ];
            }
        }

        $this->setVariable('versions', $versions);
    }
}