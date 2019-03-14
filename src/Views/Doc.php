<?php
namespace MODXDocs\Views;

use Knp\Menu\Matcher\Matcher;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment;
use MODXDocs\Helpers\TocRenderer;
use Webuni\CommonMark\TableExtension\TableExtension;
use Slim\Exception\NotFoundException;
use Slim\Http\Request;
use Slim\Http\Response;
use Spatie\YamlFrontMatter\YamlFrontMatter;
use TOC\MarkupFixer;
use TOC\TocGenerator;

use MODXDocs\Helpers\LinkRenderer;


class Doc extends Base
{
    protected $basePath;
    protected $baseUri;
    protected $file;
    protected $version;
    protected $language;
    protected $docPath;

    public function setVersion($version)
    {
        $path = getenv('DOCS_DIRECTORY') . '/' . $version . '/';
        if (!file_exists($path) || !is_dir($path)) {
            throw new NotFoundException($this->request, $this->response);
        }
        $this->version = $version;
        $this->setVariable('version', $version);
        $this->setVariable('version_branch', $version === 'current' ? '2.x' : $version);

        $this->basePath = $path;
        $this->baseUri = $version;
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
        $this->setVariable('doc_path', $this->docPath);
        $this->setVariable('file_path', $this->file);
    }

    public function initialize(Request $request, Response $response, array $args = array())
    {
        parent::initialize($request, $response, $args);
        $this->setVersion($request->getAttribute('version'));
        $this->setLanguage($request->getAttribute('language'));
        $this->getTopNavigation();
        $this->setDocPath($request->getAttribute('path'));
        $this->getVersions();
        $this->getNavigation();
        return true;
    }

    public function home(Request $request, Response $response, array $args = array())
    {
        $request = $request
            ->withAttribute('version', 'current')
            ->withAttribute('language', 'en')
            ->withAttribute('path', 'index');

        return $this->get($request, $response, $args);
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
            new LinkRenderer($this->baseUri, $this->docPath)
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
        $renderer = new TocRenderer(new Matcher(), [
            'currentClass'  => 'active',
            'ancestorClass' => 'active_ancestor'
        ], $request->getAttribute('version') . '/' . $request->getAttribute('language') . '/' . $this->docPath);
        $toc = $tocGenerator->getHtmlMenu($content, 2, 6, $renderer);
        $this->setVariable('toc', $toc);


        return $this->render('documentation.twig');
    }

    public function getVersions()
    {
        $dir = new \DirectoryIterator(getenv('DOCS_DIRECTORY'));

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

    public function getTopNavigation()
    {
        $topNav = $this->getNavigationForParent($this->basePath, 1, 1);
        $this->setVariable('topnav', $topNav);
    }

    public function getNavigation()
    {
        // Make the navigation dependent on the current parent (administration, developing, xpdo, etc)
        $docPath = explode('/', $this->docPath);
        $menuPath = $this->basePath . $docPath[0];
        if (file_exists($menuPath) && is_dir($menuPath)) {
            if (file_exists($menuPath . '.md')) {
                $item = $this->getNavItem(new \SplFileInfo($menuPath . '.md'), $docPath[0]);
            }
            else {
                $item = $this->getNavItem(new \SplFileInfo($menuPath . '/index.md'), $docPath[0]);
            }
            $this->setVariable('current_nav_parent', $item);
            $nav = $this->getNavigationForParent($menuPath);
        }
        // Fall back to listing 2 levels deep on home pages
        else {
            $nav = $this->getNavigationForParent($this->basePath, 1, 2);
        }

        $out = $this->container->view->fetch('partials/nav.twig', ['children' => $nav]);

        $this->setVariable('nav', $out);
    }

    public function getNavigationForParent($path, $level = 1, $maxDepth = 10)
    {
        $nav = [];
        try {
            $dir = new \DirectoryIterator($path);
        }
        catch (\Exception $e) {
            $this->logger->addError('Exception ' . get_class($e) . ' fetching navigation for ' . $path . ': ' . $e->getMessage());
            return $nav;
        }
        foreach ($dir as $file) {
            if ($file->isDot()) {
                continue;
            }

            $filePath = $file->getPathname();
            $filePath = strpos($filePath, '.md') !== false ? substr($filePath, 0, strpos($filePath, '.md')) : $filePath;
            $relativeFilePath = str_replace($this->basePath, '', $filePath);

            if ($file->isFile() && $file->getExtension() === 'md') {
                if ($file->getFilename() === 'index.md') {
                    continue;
                }

                $current = $this->getNavItem($file, $relativeFilePath);
                $current['level'] = $level;

                if ($level < $maxDepth && is_dir($filePath . '/')) {
                    $current['classes'] .= ' has-children';
                    $current['children'] = $this->getNavigationForParent($filePath . '/', $level + 1, $maxDepth);
                }
                $nav[] = $current;
            }

            // We handle directories slightly differently
            elseif ($file->isDir()) {
                if (file_exists($file->getPathname() . '/index.md')) {
                    $current = $this->getNavItem(new \SplFileInfo($file->getPathname() . '/index.md'), $relativeFilePath);
                    $current['level'] = $level;
                    $current['classes'] .= ' has-children';
                    if ($level < $maxDepth) {
                        $current['children'] = $this->getNavigationForParent($file->getPathname(), $level + 1, $maxDepth);
                    }
                    $nav[] = $current;
                }
            }

        }

        usort($nav, function ($item, $item2) {
            $so1 = array_key_exists('sortorder', $item) ? (int)$item['sortorder'] : null;
            $so2 = array_key_exists('sortorder', $item2) ? (int)$item2['sortorder'] : null;
            if ($so1 && !$so2) { return -1; }
            if (!$so1 && $so2) { return 1; }
            if (!$so1 && !$so2) { return strnatcmp($item['title'], $item2['title']); }
            return $so1 - $so2;
        });

        return $nav;
    }

    private function getNavItem(\SplFileInfo $file, $relativeFilePath)
    {
        $fm = $this->getFrontmatter($file);
        $current = [
            'title' => $this->getTitle($file),
            'uri' => $this->container->router->pathFor('documentation', [
                'language' => $this->language,
                'version' => $this->version,
                'path' => $relativeFilePath,
            ]),
            'classes' => 'item',
        ];
        if (array_key_exists('sortorder', $fm)) {
            $current['sortorder'] = (int)$fm['sortorder'];
        }
        if (strpos($this->file, $relativeFilePath) !== false) {
            $current['classes'] .= ' active';
        }

        return $current;
    }

    private function getFrontmatter(\SplFileInfo $file) {
        // Parse the front matter from the file
        $fileContents = $file->isFile() ? file_get_contents($file->getPathname()) : false;
        $obj = YamlFrontMatter::parse($fileContents);
        return $obj->matter();
    }

    private function getTitle(\SplFileInfo $file)
    {
        $fm = $this->getFrontmatter($file);
        $title = array_key_exists('title', $fm) ? $fm['title'] : '';
        if (empty($title)) {
            $name = $file->getFilename();
            $title = strpos($name, '.md') !== false ? substr($name, 0, strpos($name, '.md')) : $name;
            $title = implode(' ', explode('-', $title));
        }
        return $title;
    }
}
