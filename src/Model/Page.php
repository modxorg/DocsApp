<?php

namespace MODXDocs\Model;

use Knp\Menu\Matcher\Matcher;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Embed\Bridge\OscaroteroEmbedAdapter;
use League\CommonMark\Extension\Embed\Embed;
use League\CommonMark\Extension\Embed\EmbedExtension;
use League\CommonMark\Extension\Embed\EmbedRenderer;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Renderer\HtmlDecorator;
use MODXDocs\Exceptions\NotFoundException;
use MODXDocs\Helpers\LinkRenderer;
use MODXDocs\Helpers\MarkupFixer;
use MODXDocs\Helpers\RelativeImageRenderer;
use MODXDocs\Helpers\TocRenderer;
use MODXDocs\Services\CacheService;
use MODXDocs\Services\DocumentService;
use MODXDocs\Services\VersionsService;
use PDO;
use Symfony\Component\Process\Process;
use TOC\TocGenerator;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\SmartPunct\SmartPunctExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;

class Page
{
    private array $meta;
    private string $version;
    private string $language;
    private string $path;
    private string $body;
    private string $currentUrl;
    private DocumentService $documentService;
    private string $relativeFilePath;
    private PDO $db;
    private ?string $renderedBody = null;
    private ?string $socialImageUrl = null;
    private bool $regenerateSocialImage = false; // Toggle for testing

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

        $docRoot = $_ENV['DOCS_DIRECTORY'];
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

        // Parse the markdown
        $environment = new Environment([
            'html_input' => 'allow',
            'max_nesting_level' => 10,
            'allow_unsafe_links' => false,
            'autolink' => [
                'allowed_protocols' => ['https', 'http', 'mailto'],
                'default_protocol' => 'https',
            ],
            'footnote' => [
                'backref_class' => 'footnote-backref',
                'backref_symbol' => '↩',
            ],
            'embed' => [
                'adapter' => new OscaroteroEmbedAdapter(), // See the "Adapter" documentation below
                'allowed_domains' => ['youtube.com', 'github.com'],
                'fallback' => 'link',
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new FootnoteExtension());
        $environment->addExtension(new SmartPunctExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new EmbedExtension());

        $environment->addRenderer(
            Link::class,
            new LinkRenderer(
                '/' . $this->version . '/' . $this->language . '/',
                $this->currentUrl
            )
        );
        $environment->addRenderer(
            Image::class,
            new RelativeImageRenderer(
                $this->relativeFilePath
            )
        );
        $environment->addRenderer(Embed::class, new HtmlDecorator(new EmbedRenderer(), 'div', ['class' => 'video-embed']));

        $converter = new MarkdownConverter($environment);

        try {
            $content = $converter->convert($this->body)->getContent();
        } catch (CommonMarkException $e) {
            $content = '<p class="error">There was an error parsing this document. Below is the source markdown.</p>';
            $content .= '<pre><code>' . $this->body . '</code></pre>';
        }

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
        return $_ENV['CANONICAL_BASE_URL'] . $version . '/' . $this->language . '/' . $this->path;
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
        $paths = array_filter($paths, function ($v) {
            return strtolower($v) === 'index';
        });
        $path = end($paths);
        $path = str_replace('-', ' ', $path);
        $path = ucfirst($path);
        return $path;
    }

    public function getTableOfContents($topLevel = 2, $depth = 6): string
    {
        $tocGenerator = new TocGenerator();

        $renderer = new TocRenderer(
            new Matcher(),
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

    public function getHistory(): array
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
                    'gravatar' => $this->getAvatarFor($commit['email']),
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

    public function getFileCommits(): array
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
        $cmd->setWorkingDirectory($_ENV['DOCS_DIRECTORY'] . substr($this->relativeFilePath, 0, strpos($this->relativeFilePath, '/')));

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
            } elseif (is_array($currentCommit)) {
                // This must be a line with added/removed counts, so append that information
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

    private function getAvatarFor($email): string
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

    public function getSocialImageUrl(): string
    {
        if ($this->socialImageUrl === null) {
            $this->generateSocialImage();
        }
        return $this->socialImageUrl;
    }

    private function generateSocialImage(): void
    {
        $imageDir = $_ENV['BASE_DIRECTORY'] . 'public/images/social/';
        if (!is_dir($imageDir)) {
            mkdir($imageDir, 0755, true);
        }

        $breadcrumbText = $this->getBreadcrumbText();
        // Create a hash of the content that would go on the image - using CRC32 as we only need to avoid
        // conflicts in ~5000 items and don't need cryptographic security
        $contentHash = hash('crc32b', $this->getTitle() . $breadcrumbText);
        $imagePath = $imageDir . $contentHash . '.png';

        // If image exists and we're not forcing regeneration, return the URL
        if (file_exists($imagePath) && !$this->regenerateSocialImage) {
            $this->socialImageUrl = '/images/social/' . $contentHash . '.png';
            return;
        }

        // Create the image
        $image = imagecreatetruecolor(1200, 630);

        // Load background image
        $backgroundPath = $_ENV['BASE_DIRECTORY'] . 'public/images/social/.background.png';
        if (file_exists($backgroundPath)) {
            $background = imagecreatefrompng($backgroundPath);
            imagecopy($image, $background, 0, 0, 0, 0, 1200, 630);
            imagedestroy($background);
        } else {
            // Fallback light blue background to indicate missing background file
            $lightBlue = imagecolorallocate($image, 240, 248, 255);
            imagefill($image, 0, 0, $lightBlue);
        }

        // Set up colors
        $textColor = imagecolorallocate($image, 16, 44, 83); // rgb(16, 44, 83)
        $grayColor = imagecolorallocate($image, 128, 128, 128);

        // Load fonts
        $regularFont = $_ENV['BASE_DIRECTORY'] . 'public/fonts/Inter_24pt-Regular.ttf';
        $boldFont = $_ENV['BASE_DIRECTORY'] . 'public/fonts/Inter_24pt-Bold.ttf';


        // Draw breadcrumbs
        $maxBreadcrumbWidth = 1000;
        $breadcrumbSize = 22;

        // Check if breadcrumb text is too long
        $bbox = imagettfbbox($breadcrumbSize, 0, $regularFont, $breadcrumbText);
        $textWidth = $bbox[2] - $bbox[0];

        if ($textWidth > $maxBreadcrumbWidth) {
            // Truncate text and add ellipsis
            $ellipsis = '...';
            $ellipsisWidth = imagettfbbox(
                $breadcrumbSize,
                0,
                $regularFont,
                $ellipsis
            )[2] - imagettfbbox($breadcrumbSize, 0, $regularFont, $ellipsis)[0];

            while ($textWidth > $maxBreadcrumbWidth - $ellipsisWidth) {
                $breadcrumbText = substr($breadcrumbText, 0, -1);
                $bbox = imagettfbbox($breadcrumbSize, 0, $regularFont, $breadcrumbText);
                $textWidth = $bbox[2] - $bbox[0];
            }
            $breadcrumbText .= $ellipsis;
        }

        imagettftext($image, $breadcrumbSize, 0, 50, 90, $grayColor, $regularFont, $breadcrumbText);

        // Draw page title
        $title = $this->getTitle();
        $maxTitleWidth = 1100; // 1200 - 50px margin on each side
        $maxTitleHeight = 450; // Leave some space at bottom

        // Word wrap the title
        $wrappedTitle = wordwrap($title, 30, "\n", true);
        $lines = explode("\n", $wrappedTitle);

        $y = 234;
        $fontSize = 56;
        $lineHeight = ceil($fontSize * 1.35);

        foreach ($lines as $line) {
            if ($y > $maxTitleHeight) {
                break;
            }

            $bbox = imagettfbbox($fontSize, 0, $boldFont, $line);
            $lineWidth = $bbox[2] - $bbox[0];

            // If line is too long, truncate it
            if ($lineWidth > $maxTitleWidth) {
                $line = substr($line, 0, -1) . '...';
            }

            imagettftext($image, $fontSize, 0, 50, $y, $textColor, $boldFont, $line);
            $y += $lineHeight;
        }

        // Save the image
        imagepng($image, $imagePath);
        imagedestroy($image);

        $this->socialImageUrl = '/images/social/' . $contentHash . '.png';
    }

    private function getBreadcrumbText(): string
    {
        $breadcrumbs = [];
        $currentPage = $this;

        while ($currentPage) {
            array_unshift($breadcrumbs, $currentPage->getTitle());
            $currentPage = $currentPage->getParentPage();
        }

        return implode(' / ', $breadcrumbs);
    }
}
