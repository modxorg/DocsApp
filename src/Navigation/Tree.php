<?php

namespace MODXDocs\Navigation;

use MODXDocs\Services\CacheService;
use MODXDocs\Services\VersionsService;
use Spatie\YamlFrontMatter\YamlFrontMatter;

class Tree {
    private $items;
    /**
     * @var string
     */
    private $version;
    /**
     * @var string
     */
    private $language;

    public function __construct(string $version, string $language, array $items)
    {
        $this->items = $items;
        $this->version = $version;
        $this->language = $language;
    }

    public static function get(string $version, string $language, $regenerateCache = false): Tree
    {
        $cache = CacheService::getInstance();
        $cacheKey = 'navigation/' . $version . '/' . $language;
        $items = $cache->get($cacheKey);
        if (!$regenerateCache && is_array($items)) {
            return new self($version, $language, $items);
        }

        $root = getenv('DOCS_DIRECTORY');
        $items = self::_getItems($root, $version, $language);

        $cache->set($cacheKey, $items);
        return new self($version, $language, $items);
    }

    private static function _getItems($root, $version, $language): array
    {
        $realVersion = $version === VersionsService::getCurrentVersion() ? VersionsService::getCurrentVersionBranch() : $version;
        $directoryPath = $root . $realVersion . '/' . $language;
        $directoryPrefix = $realVersion . '/' . $language . '/';
        return self::_getNestedItems($root, $directoryPrefix, $directoryPath, $version, $language, 1);
    }

    private static function _getNestedItems($root, $directoryPrefix, $directoryPath, $version, $language, $level): array
    {
        if (!file_exists($directoryPath) || !is_dir($directoryPath)) {
            return [];
        }
        $nav = [];
        $dir = new \DirectoryIterator($directoryPath);
        foreach ($dir as $file) {
            if ($file->isDot()) {
                continue;
            }


            $filePath = str_replace('\\','/',$file->getPathname());
            $relativeFilePath = str_replace($root, '', $filePath);
            $relativeUrl = strpos($relativeFilePath, '.md') !== false ? substr($relativeFilePath, 0, strpos($relativeFilePath, '.md')) : $relativeFilePath;
            $relativeUrl = strpos($relativeUrl, $directoryPrefix) === 0 ? substr($relativeUrl, strlen($directoryPrefix)) : $relativeUrl;

            if ($file->isDir()) {
                $index = $filePath . '/index.md';
                // We only process directories with an index file
                if (!file_exists($index)) {
                    continue;
                }

                $item = [
                    'file' => $relativeFilePath . '/index.md',
                    'title' => $file->getFilename(),
                    'uri' => '/' . $version . '/' . $language . '/' . $relativeUrl,
                    'classes' => 'c-nav__item',
                    'level' => $level,
                    'children' => self::_getNestedItems($root, $directoryPrefix, $filePath, $version, $language, $level + 1),
                ];
                self::augmentFromMatter($item, $index);

                if (count($item['children']) > 0) {
                    $item['classes'] .= ' c-nav__item--has-children';
                }

                $nav[] = $item;
            }

            elseif ($file->isFile() && $file->getExtension() === 'md') {
                if ($file->getFilename() === 'index.md') {
                    continue;
                }

                $item = [
                    'file' => $relativeFilePath,
                    'title' => $file->getFilename(),
                    'uri' => '/' . $version . '/' . $language . '/' . $relativeUrl,
                    'classes' => 'c-nav__item',
                    'level' => $level,
                    'children' => [],
                ];
                self::augmentFromMatter($item, $filePath);

                if (is_dir($root . $directoryPrefix . $relativeUrl . '/')) {
                    $item['children'] = self::_getNestedItems($root,  $directoryPrefix, $root . $directoryPrefix . $relativeUrl . '/', $version, $language, $level + 1);
                    if (count($item['children']) > 0) {
                        $item['classes'] .= ' c-nav__item--has-children';
                    }
                }
                $nav[] = $item;
            }

        }

        usort($nav, static function ($item, $item2) {
            $so1 = array_key_exists('sortorder', $item) ? (int)$item['sortorder'] : null;
            $so2 = array_key_exists('sortorder', $item2) ? (int)$item2['sortorder'] : null;

            if ($so1 && !$so2) {
                return -1;
            }

            if (!$so1 && $so2) {
                return 1;
            }

            if (!$so1 && !$so2) {
                return strnatcmp(
                    mb_strtolower($item['title']),
                    mb_strtolower($item2['title'])
                );
            }

            return $so1 - $so2;
        });

        return $nav;
    }

    private static function augmentFromMatter(&$item, $path)
    {
        $fileContents = file_get_contents($path);
        $obj = YamlFrontMatter::parse($fileContents);
        $fm =  $obj->matter();
        $title = array_key_exists('title', $fm) ? $fm['title'] : '';

        if (empty($title)) {
            $name = basename($path);
            $title = strpos($name, '.md') !== false ? substr($name, 0, strpos($name, '.md')) : $name;
            $title = implode(' ', explode('-', $title));
        }

        $item['title'] = $title;

        if (array_key_exists('sortorder', $fm)) {
            $item['sortorder'] = $fm['sortorder'];
        }
        if (array_key_exists('note', $fm)) {
            $item['note'] = $fm['note'];
        }
        if (array_key_exists('suggest_delete', $fm)) {
            $item['suggest_delete'] = $fm['suggest_delete'];
        }
        if (array_key_exists('translation', $fm)) {
            $item['translation'] = $fm['translation'];
        }
        if (array_key_exists('description', $fm)) {
            $item['description'] = $fm['description'];
        }

        return $title;
    }

    public function setActivePath($path): void
    {
        foreach ($this->items as &$item) {
            $this->_setActiveOnItem($item, $path);
        }
    }

    private function _setActiveOnItem(&$item, $path): void
    {
        if ($path === $item['uri']) {
            $item['classes'] .= ' c-nav__item--activepage';
        }
        if (strpos($path, $item['uri']) === 0) {
            $item['classes'] .= ' c-nav__item--active';
            foreach ($item['children'] as &$childItem) {
                $this->_setActiveOnItem($childItem, $path);
            }
        }
    }

    public function renderTree(\Slim\Views\Twig $twig, $template = 'partials/nav.twig'): string
    {
        return $twig->fetch($template,
            [
                'language' => $this->language,
                'version' => $this->version,
                'children' => $this->items
            ]
        );
    }

    private function getSelfAndNested(array $item) {
        $children = $item['children'];
        unset($item['children']);
        $a = [$item];
        foreach ($children as $child) {
            $a =  array_merge($a, $this->getSelfAndNested($child));
        }
        return $a;
    }

    public function getAllItems()
    {
        $return = [];
        foreach ($this->items as $item) {
            $return = array_merge($return, $this->getSelfAndNested($item));
        }
        return $return;
    }

}
