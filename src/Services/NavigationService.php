<?php

namespace MODXDocs\Services;

use Monolog\Logger;
use Slim\Http\Request;
use Slim\Router;
use Slim\Views\Twig;
use Spatie\YamlFrontMatter\YamlFrontMatter;

use MODXDocs\Navigation\NavigationItem;
use MODXDocs\Navigation\NavigationItemBuilder;

class NavigationService
{
    private $twig;
    private $logger;
    private $router;
    private $requestPathService;
    private $requestAttributesService;
    private $filePathService;

    public function __construct(
        Twig $twig,
        Logger $logger,
        Router $router,
        RequestPathService $requestPathService,
        RequestAttributesService $requestAttributesService,
        FilePathService $filePathService
    )
    {
        $this->twig = $twig;
        $this->logger = $logger;
        $this->router = $router;
        $this->requestPathService = $requestPathService;
        $this->requestAttributesService = $requestAttributesService;
        $this->filePathService = $filePathService;
    }

    public function getTopNavigation(Request $request)
    {
        return $this->getNavigationForParent(
            (new NavigationItemBuilder())
                ->forTopMenu()
                ->withCurrentFilePath($this->filePathService->getFilePath($request))
                ->withBasePath($this->requestPathService->getAbsoluteBaseFilePath($request))
                ->withFilePath($this->requestPathService->getAbsoluteBaseFilePath($request))
                ->withUrlPath($this->requestPathService->getBaseUrlPath($request))
                ->withVersion($this->requestAttributesService->getVersion($request))
                ->withLanguage($this->requestAttributesService->getLanguage($request))
                ->build()
        );
    }

    public function getTopNavigationForItem(NavigationItem $navigationItem)
    {
        return $this->getNavigationForParent($navigationItem);
    }

    public function getNavigation(Request $request)
    {
        $baseNavigationItem = (new NavigationItemBuilder())
            ->withCurrentFilePath($this->filePathService->getFilePath($request))
            ->withVersion($this->requestAttributesService->getVersion($request))
            ->withLanguage($this->requestAttributesService->getLanguage($request))
            ->build();

        // Make the navigation dependent on the current parent (administration, developing, xpdo, etc)
        $docPath = array_filter(explode('/', $this->requestAttributesService->getPath($request)));

        // If the docpath is empty, we are on the frontpage
        if (count($docPath) === 0) {
            return $this->renderNav(
                $this->getNavigationForParent(
                    NavigationItemBuilder::copyFromItem($baseNavigationItem)
                        ->withBasePath($this->requestPathService->getAbsoluteBaseFilePath($request))
                        ->withFilePath($this->requestPathService->getAbsoluteBaseFilePath($request))
                        ->withUrlPath($this->requestPathService->getBaseUrlPath($request))
                        ->withLevel(NavigationItem::HOME_MENU_LEVEL)
                        ->withDepth(NavigationItem::HOME_MENU_DEPTH)
                        ->build()
                )
            );
        }

        $menuFilePath = $this->requestPathService->getAbsoluteBaseFilePath($request) . $docPath[0];
        $menuUrlPath = $this->requestPathService->getBaseUrlPath($request) . $docPath[0];

        if (file_exists($menuFilePath) && is_dir($menuFilePath)) {
            return $this->renderNav(
                $this->getNavigationForParent(
                    NavigationItemBuilder::copyFromItem($baseNavigationItem)
                        ->withBasePath($this->requestPathService->getAbsoluteBaseFilePath($request))
                        ->withFilePath($menuFilePath)
                        ->withUrlPath($menuUrlPath)
                        ->build()
                )
            );
        }

        // This should not happen
        return null;
    }

    public function getNavParent(Request $request)
    {
        $navigationItem = (new NavigationItemBuilder())
            ->withCurrentFilePath($this->filePathService->getFilePath($request))
            ->withFilePath($this->requestPathService->getAbsoluteBaseFilePath($request))
            ->withPath($this->requestAttributesService->getPath($request))
            ->withVersion($this->requestAttributesService->getVersion($request))
            ->withLanguage($this->requestAttributesService->getLanguage($request))
            ->build();

        // Make the navigation dependent on the current parent (administration, developing, xpdo, etc)
        $docPath = array_filter(explode('/', $navigationItem->getPath()));

        // No top parent for the front page
        if (count($docPath) === 0) {
            return null;
        }

        $menuFilePath = $navigationItem->getFilePath() . $docPath[0];

        if (file_exists($menuFilePath) && is_dir($menuFilePath)) {
            if (file_exists($menuFilePath . '.md')) {
                return $this->getNavItem(
                    $navigationItem,
                    new \SplFileInfo($menuFilePath . '.md'),
                    $docPath[0]
                );
            }

            return $this->getNavItem(
                $navigationItem,
                new \SplFileInfo($menuFilePath . '/index.md'),
                $docPath[0]
            );
        }

        return null;
    }

    private function getNavigationForParent(NavigationItem $navigationItem)
    {
        $nav = [];

        try {
            $dir = new \DirectoryIterator($navigationItem->getFilePath());
        } catch (\Exception $e) {
            $this->logger->addError(
                'Exception '
                . get_class($e)
                . ' fetching navigation for '
                . $navigationItem->getFilePath()
                . ': '
                . $e->getMessage()
            );

            return $nav;
        }

        foreach ($dir as $file) {
            if ($file->isDot()) {
                continue;
            }

            $filePath = $file->getPathname();
            $filePath = strpos($filePath, '.md') !== false ? substr($filePath, 0, strpos($filePath, '.md')) : $filePath;

            $relativeFilePath = str_replace($navigationItem->getBasePath(), '', $filePath);

            if ($file->isFile() && $file->getExtension() === 'md') {
                if ($file->getFilename() === 'index.md') {
                    continue;
                }

                $current = $this->getNavItem(
                    $navigationItem,
                    $file,
                    $relativeFilePath
                );
                $current['level'] = $navigationItem->getLevel();

                if ($navigationItem->getLevel() < $navigationItem->getDepth() && is_dir($filePath . '/')) {
                    $current['classes'] .= ' has-children';
                    $current['children'] = $this->getNavigationForParent(
                        NavigationItemBuilder::copyFromItem($navigationItem)
                            ->withFilePath($filePath . '/')
                            ->withLevel($navigationItem->getLevel() + 1)
                            ->build()
                    );
                }
                $nav[] = $current;
            } elseif ($file->isDir()) {
                if (file_exists($file->getPathname() . '/index.md')) {
                    $current = $this->getNavItem(
                        $navigationItem,
                        new \SplFileInfo($file->getPathname() . '/index.md'),
                        $relativeFilePath
                    );
                    $current['level'] = $navigationItem->getLevel();
                    $current['classes'] .= ' has-children';

                    if ($navigationItem->getLevel() < $navigationItem->getDepth()) {
                        $current['children'] = $this->getNavigationForParent(
                            NavigationItemBuilder::copyFromItem($navigationItem)
                                ->withFilePath($file->getPathname())
                                ->withLevel($navigationItem->getLevel() + 1)
                                ->build()
                        );
                    }

                    $nav[] = $current;
                }
            }

        }

        usort($nav, function ($item, $item2) {
            $so1 = array_key_exists('sortorder', $item) ? (int)$item['sortorder'] : null;
            $so2 = array_key_exists('sortorder', $item2) ? (int)$item2['sortorder'] : null;

            if ($so1 && !$so2) {
                return -1;
            }

            if (!$so1 && $so2) {
                return 1;
            }

            if (!$so1 && !$so2) {
                return strnatcmp($item['title'], $item2['title']);
            }

            return $so1 - $so2;
        });

        return $nav;
    }

    private function getNavItem(NavigationItem $navigationItem, \SplFileInfo $file, $relativeFilePath)
    {
        $fm = static::getNavFrontmatter($file);
        $current = [
            'title' => static::getNavTitle($file),
            'uri' => $this->router->pathFor('documentation', [
                'version' => $navigationItem->getVersion(),
                'language' => $navigationItem->getLanguage(),
                'path' => $relativeFilePath,
            ]),
            'classes' => 'item',
        ];

        if (array_key_exists('sortorder', $fm)) {
            $current['sortorder'] = (int)$fm['sortorder'];
        }

        if (strpos($navigationItem->getCurrentFilePath(), $relativeFilePath) !== false) {
            $current['classes'] .= ' active';
        }

        return $current;
    }

    private function renderNav(array $nav)
    {
        return $this->twig->fetch(
            'partials/nav.twig', [
                'children' => $nav
            ]
        );
    }

    private static function getNavTitle(\SplFileInfo $file)
    {
        $fm = static::getNavFrontmatter($file);
        $title = array_key_exists('title', $fm) ? $fm['title'] : '';

        if (empty($title)) {
            $name = $file->getFilename();
            $title = strpos($name, '.md') !== false ? substr($name, 0, strpos($name, '.md')) : $name;
            $title = implode(' ', explode('-', $title));
        }

        return $title;
    }

    private static function getNavFrontmatter(\SplFileInfo $file)
    {
        $fileContents = $file->isFile() ? file_get_contents($file->getPathname()) : false;
        $obj = YamlFrontMatter::parse($fileContents);
        return $obj->matter();
    }
}