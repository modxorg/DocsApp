<?php


namespace MODXDocs\Model;

use Knp\Menu\Matcher\Matcher;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment;
use MODXDocs\Exceptions\NotFoundException;
use MODXDocs\Helpers\LinkRenderer;
use MODXDocs\Helpers\RelativeImageRenderer;
use MODXDocs\Helpers\TocRenderer;
use MODXDocs\Services\CacheService;
use MODXDocs\Services\DocumentService;
use MODXDocs\Services\VersionsService;
use PDO;
use Symfony\Component\Process\Process;
use TOC\MarkupFixer;
use TOC\TocGenerator;
use Webuni\CommonMark\TableExtension\TableExtension;
use League\CommonMark\Inline\Element\Image;
use League\CommonMark\Inline\Element\Link;

class Page {

    /**
     * @var array
     */
    private $meta;
    /**
     * @var string
     */
    private $version;
    /**
     * @var string
     */
    private $language;
    /**
     * @var string
     */
    private $path;
    /**
     * @var string
     */
    private $body;
    /**
     * @var string
     */
    private $renderedBody;
    /**
     * @var string
     */
    private $currentUrl;
    /**
     * @var DocumentService
     */
    private $documentService;
    /**
     * @var string
     */
    private $relativeFilePath;
    /**
     * @var PDO
     */
    private $db;

    public function __construct(DocumentService $documentService, PDO $db, string $version, string $language, string $requestPath, string $filePath, array $meta, string $body)
    {
        $this->version = $version;
        $this->meta = $meta;
        $this->body = $body;
        $this->language = $language;
        $this->path = $requestPath;
        $this->currentUrl = '/' . $version . '/' . $language . '/' . $requestPath;
        $this->documentService = $documentService;
        $this->db = $db;

        $docRoot = getenv('DOCS_DIRECTORY');
        if (strpos($filePath, $docRoot) === 0) {
            $filePath = ltrim(substr($filePath, strlen($docRoot)), '/');
        }
        $this->relativeFilePath = $filePath;
    }

    private function renderBody(): void
    {
        $cache = CacheService::getInstance();
        $key = 'rendered/' . trim($this->currentUrl, '/');
        $hash = md5($this->body);
        if ($rendered = $cache->get($key, $hash)) {
            $this->renderedBody = $rendered;
            return;
        }

        // Grab the markdown
        $environment = Environment::createCommonMarkEnvironment();
        $environment->addExtension(new TableExtension());
        $environment->addInlineRenderer(Link::class,
            new LinkRenderer(
                '/' . $this->version . '/' . $this->language . '/',
                $this->currentUrl
            )
        );
        $environment->addInlineRenderer(Image::class,
            new RelativeImageRenderer(
                $this->relativeFilePath
            )
        );

        $markdown = new CommonMarkConverter([
            'html_input' => 'allow',
        ], $environment);

        $content = $markdown->convertToHtml($this->body);

        $fixer = new MarkupFixer();
        $this->renderedBody = $fixer->fix($content);
        $cache->set($key, $this->renderedBody, null, $hash);
    }

    /**
     * @return array
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->currentUrl;
    }

    public function getCanonicalUrl(): string
    {
        $version = $this->version === VersionsService::getCurrentVersionBranch() ? VersionsService::getCurrentVersion() : $this->version;
        return getenv('CANONICAL_BASE_URL') . $version . '/' . $this->language . '/' . $this->path;
    }

    /**
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return string
     */
    public function getRenderedBody(): string
    {
        if ($this->renderedBody === null) {
            $this->renderBody();
        }
        return $this->renderedBody;
    }

    public function getPageTitle(): string
    {
        $titles = [];
        $titles[] = $this->getTitle();

        if ($parent = $this->getParentPage()) {
            $titles[] = $parent->getTitle();
        }

        return implode(' - ', $titles);
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        if (array_key_exists('title', $this->meta)) {
            return $this->meta['title'];
        }
        $paths = explode('/', $this->path);
        $paths = array_filter($paths, function($v) { return strtolower($v) === 'index'; });
        $path = end($paths);
        $path = str_replace('-', ' ', $path);
        $path = ucfirst($path);
        return $path;
    }

    public function getTableOfContents($topLevel = 2, $depth = 6) : string
    {
        $tocGenerator = new TocGenerator();

        $renderer = new TocRenderer(new Matcher(),
            $this->currentUrl,
            [
                'currentClass' => 'c-toc__item--active',
                'ancestorClass' => 'c-toc__item--activeancestor',
                'firstClass' => 'c-toc__item--first',
                'lastClass' => 'c-toc__item--last',
            ]
        );

        try {
            return $tocGenerator->getHtmlMenu(
                $this->getRenderedBody(),
                $topLevel,
                $depth,
                $renderer
            );
        } catch (\TypeError $e) {
            // https://github.com/caseyamcl/toc/issues/6
            return 'Error generating table of contents for page.';
        }
    }

    public function getParentPage(): ?Page
    {
        $path = explode('/', $this->path);
        array_pop($path);
        if (count($path) >= 1) {
            $req = new PageRequest($this->version, $this->language, implode('/', $path));
            try {
                return $this->documentService->load($req);
            } catch (NotFoundException $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * @return string
     */
    public function getRelativeFilePath(): string
    {
        return $this->relativeFilePath;
    }

    public function getHistory()
    {
        try {
            $statement = $this->db->prepare('SELECT git_hash, ts, name, email, message FROM Page_History WHERE url = :url ORDER BY ts DESC');
            $statement->bindValue(':url', $this->relativeFilePath);
            if (!$statement->execute()) {
                return [];
            }
            $commits = $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }

        $contributors = [];
        $lastChange = null;
        $lastChangeMessage = null;
        foreach ($commits as $commit) {
            if (!array_key_exists($commit['email'], $contributors)) {
                $contributors[$commit['email']] = [
                    'name' => $commit['name'],
                    'gravatar' => $this->_getAvatarFor($commit['email']),
                    'count' => 0,
                ];
            }
            $contributors[$commit['email']]['count']++;

            if (!$lastChange) {
                $lastChange = (int)$commit['ts'];
                $lastChangeMessage = $commit['message'];
            }
        }

        // Sort based on contribution count, contributor with most commits last because that's shown "in front".
        uasort($contributors, function ($a, $b) {
            return $a['count'] >= $b['count'] ? 1 : -1;
        });

        return [
            'last_change' => $lastChange,
            'last_change_message' => $lastChangeMessage,
            'contributors' => $contributors
        ];
    }

    public function getFileCommits()
    {
        $cmd = new Process([
            'git',
            '--no-pager',
            'log',
            '--numstat',
            '--pretty=format:"> %h | %ct | %aN | %aE |  %s"',
            '--',
            substr($this->relativeFilePath, strpos($this->relativeFilePath, '/') + 1)
        ]);
        $cmd->setWorkingDirectory(getenv('DOCS_DIRECTORY') . substr($this->relativeFilePath, 0, strpos($this->relativeFilePath, '/')));

        if ($cmd->run() !== 0) {
            return [];
        }

        $history = $cmd->getOutput();
        $history = explode("\n", $history);
        $history = array_map('trim', $history);
        $history = array_filter($history);

        $commits = [];
        $currentCommit = null;
        foreach ($history as $line) {
            $line = trim($line, '"');
            if (strpos($line, '>') === 0) {
                // If we've had a previous commit we were filling, we just got a new one, so set it to the array
                if (is_array($currentCommit)) {
                    $commits[] = $currentCommit;
                }

                // Remove the prefixed >
                $line = substr($line, 2);

                // Parse into an array structure we can more easily expand in the future than the raw git log output
                [$hash, $timestamp, $name, $email, $message] = array_map('trim', explode('|', trim($line)));

                // Keep in a temporary array, so we can fill it with added/removed stats from the next line
                $currentCommit = [
                    'hash' => $hash,
                    'timestamp' => $timestamp,
                    'name' => $name,
                    'email' => $email,
                    'message' => $message,
                    'added' => 0,
                    'removed' => 0,
                ];
            }
            // This must be a line with added/removed counts, so append that information
            elseif (is_array($currentCommit)) {
                $line = explode("\t", $line);
                $currentCommit['added'] = (int)$line[0];
                $currentCommit['removed'] = (int)$line[1];
            }
        }

        // Make sure to also save the last commit
        if ($currentCommit) {
            $commits[] = $currentCommit;
        }

        return $commits;
    }

    private function _getAvatarFor($email): string
    {
        $hash = md5(strtolower(trim($email)));

        $cache = CacheService::getInstance();
        $key = 'avatars/' . $hash;
        $gravatarUrl = 'https://www.gravatar.com/avatar/' . $hash . '?s=60&d=retro';
        $avatar = $cache->get($key, $hash);
        if (empty($avatar) && $cache->isEnabled()) {
            $download = file_get_contents($gravatarUrl);
            if (!empty($download)) {
                $avatar = base64_encode($download);
                $cache->set($key, $avatar, strtotime('+1 month'));
            }
        }

        if (!empty($avatar)) {
            return "data:image/jpg;base64,{$avatar}";
        }

        return $gravatarUrl;
    }
}
