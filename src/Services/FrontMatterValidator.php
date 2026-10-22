<?php

namespace MODXDocs\Services;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Spatie\YamlFrontMatter\YamlFrontMatter;
use SplFileInfo;
use Symfony\Component\Yaml\Exception\ParseException;

class FrontMatterValidator
{
    private const SKIP_DIRECTORIES = [
        '.git' => true,
        '.github' => true,
        '.idea' => true,
        'convert' => true,
        'vendor' => true,
        'node_modules' => true,
    ];

    private const SKIP_FILES = [
        'README.md' => true,
        'CONTRIBUTING.md' => true,
    ];

    private const LANGUAGES = ['en', 'ru', 'nl', 'es'];

    private const VERSION_PREFIXES = ['2.x', '3.x', 'current'];

    private ?string $docsDirectory;

    public function __construct(?string $docsDirectory = null)
    {
        $this->docsDirectory = $docsDirectory;
    }

    /**
     * Scan markdown under the docs directory.
     *
     * An empty path scans the whole docs directory. Any other path must be
     * relative to that directory, for example "3.x" or "3.x/en".
     *
     * @param string[] $paths
     * @return array{files: int, errors: list<array{file: string, message: string}>}
     */
    public function validate(array $paths = []): array
    {
        $errors = [];
        $files = 0;
        $docsRoot = $this->docsRoot();

        if ($docsRoot === null) {
            return [
                'files' => 0,
                'errors' => [[
                    'file' => '',
                    'message' => 'Docs directory is not available',
                ]],
            ];
        }

        if ($paths === []) {
            $paths = [''];
        }

        foreach ($paths as $path) {
            $resolved = $this->resolveScanPath((string) $path, $docsRoot);
            if ($resolved['path'] === null) {
                $errors[] = [
                    'file' => $path,
                    'message' => $resolved['message'],
                ];
                continue;
            }

            foreach ($this->iterateMarkdownFiles($resolved['path']) as $file) {
                $files++;
                foreach ($this->validateFile($file) as $message) {
                    $errors[] = [
                        'file' => $file,
                        'message' => $message,
                    ];
                }
            }
        }

        return [
            'files' => $files,
            'errors' => $errors,
        ];
    }

    private function docsRoot(): ?string
    {
        $configured = $this->docsDirectory ?? ($_ENV['DOCS_DIRECTORY'] ?? '');
        if (!is_string($configured) || $configured === '') {
            $configured = dirname(__DIR__, 2) . '/docs';
        }

        $resolved = realpath($configured);
        if ($resolved === false || !is_dir($resolved)) {
            return null;
        }

        return $resolved;
    }

    /**
     * @return array{path: ?string, message: string}
     */
    private function resolveScanPath(string $path, string $docsRoot): array
    {
        $normalized = trim(str_replace('\\', '/', $path));
        if ($this->isAbsolutePath($normalized)) {
            return [
                'path' => null,
                'message' => 'Path must be inside the docs directory',
            ];
        }

        $relative = trim($normalized, '/');

        if ($relative === '' || $relative === '.') {
            return ['path' => $docsRoot, 'message' => ''];
        }

        if (str_contains($relative, '..')) {
            return [
                'path' => null,
                'message' => 'Path must be inside the docs directory',
            ];
        }

        if (str_starts_with($relative, 'docs/')) {
            $relative = substr($relative, strlen('docs/'));
        }

        if ($relative === '') {
            return ['path' => $docsRoot, 'message' => ''];
        }

        $resolved = realpath($docsRoot . '/' . $relative);
        if ($resolved === false || !is_dir($resolved) || !$this->isInsideDocs($resolved, $docsRoot)) {
            return [
                'path' => null,
                'message' => 'Path is not a directory',
            ];
        }

        return ['path' => $resolved, 'message' => ''];
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('#^[A-Za-z]:/#', $path) === 1;
    }

    private function isInsideDocs(string $path, string $docsRoot): bool
    {
        return $path === $docsRoot || str_starts_with($path, $docsRoot . DIRECTORY_SEPARATOR);
    }

    /**
     * @return list<string>
     */
    public function validateFile(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            return ['Could not read file'];
        }

        if (!str_starts_with($contents, "---\n") && !str_starts_with($contents, "---\r\n")) {
            return [];
        }

        $duplicate = $this->findDuplicateTopLevelKeys($contents);
        if ($duplicate !== null) {
            return [$duplicate];
        }

        try {
            $parsed = YamlFrontMatter::parse($contents);
        } catch (ParseException $e) {
            return [$e->getMessage()];
        } catch (\Throwable $e) {
            return [$e->getMessage()];
        }

        return $this->validateTranslation($parsed->matter(), $path);
    }

    /**
     * @param array<string, mixed> $matter
     * @return list<string>
     */
    private function validateTranslation(array $matter, string $path): array
    {
        if (!array_key_exists('translation', $matter)) {
            return [];
        }

        $value = $matter['translation'];
        if (!is_string($value)) {
            return ['translation must be a string path without version or language prefix'];
        }

        $value = trim($value);
        $errors = [];

        if ($value === '') {
            return ['translation is empty'];
        }

        if (str_contains($value, '\\') || str_contains($value, ' ') || str_contains($value, '..')) {
            $errors[] = sprintf('translation "%s" has an invalid path format', $value);
        }

        if (str_starts_with($value, '/') || str_ends_with($value, '/')) {
            $errors[] = sprintf(
                'translation "%s" must be a relative path without a leading or trailing slash',
                $value
            );
        }

        if (str_ends_with(strtolower($value), '.md')) {
            $errors[] = sprintf('translation "%s" must not include a .md extension', $value);
        }

        $firstSegment = explode('/', ltrim($value, '/'))[0];
        if (in_array($firstSegment, self::LANGUAGES, true) || in_array($firstSegment, self::VERSION_PREFIXES, true)) {
            $errors[] = sprintf(
                'translation "%s" must not include a version or language prefix',
                $value
            );
        }

        $relative = $this->normalizeTranslationPath($value);
        $versionRoot = $this->inferVersionRoot($path);
        if ($relative !== null && $versionRoot !== null && !$this->englishFileExists($versionRoot, $relative)) {
            $errors[] = sprintf(
                'translation "%s" does not match an English file (expected en/%s.md or en/%s/index.md)',
                $value,
                $relative,
                $relative
            );
        }

        return $errors;
    }

    private function normalizeTranslationPath(string $value): ?string
    {
        $relative = trim($value);
        $relative = trim($relative, '/');
        if ($relative === '') {
            return null;
        }

        if (str_ends_with(strtolower($relative), '.md')) {
            $relative = substr($relative, 0, -3);
        }

        $segments = explode('/', $relative);
        if (isset($segments[0]) && in_array($segments[0], self::VERSION_PREFIXES, true)) {
            array_shift($segments);
        }
        if (isset($segments[0]) && $segments[0] === 'en') {
            array_shift($segments);
        }

        $relative = implode('/', $segments);
        return $relative === '' ? null : $relative;
    }

    private function inferVersionRoot(string $file): ?string
    {
        $normalized = str_replace('\\', '/', $file);
        $parts = explode('/', $normalized);
        foreach ($parts as $index => $part) {
            if ($index > 0 && in_array($part, self::LANGUAGES, true)) {
                return implode('/', array_slice($parts, 0, $index));
            }
        }

        return null;
    }

    private function englishFileExists(string $versionRoot, string $relative): bool
    {
        $base = rtrim($versionRoot, '/\\') . '/en/' . $relative;

        return is_file($base . '.md') || is_file($base . '/index.md');
    }

    /**
     * @return \Generator<int, string>
     */
    private function iterateMarkdownFiles(string $root): \Generator
    {
        $directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator(
            $directory,
            static function (SplFileInfo $current): bool {
                if ($current->isDir()) {
                    return !isset(self::SKIP_DIRECTORIES[$current->getFilename()]);
                }

                if (strtolower($current->getExtension()) !== 'md') {
                    return false;
                }

                return !isset(self::SKIP_FILES[$current->getFilename()]);
            }
        );

        $iterator = new RecursiveIteratorIterator($filter);
        foreach ($iterator as $file) {
            yield $file->getPathname();
        }
    }

    private function findDuplicateTopLevelKeys(string $contents): ?string
    {
        if (!preg_match('/^---\r?\n(.*?)\r?\n---(?:\r?\n|$)/s', $contents, $matches)) {
            return null;
        }

        $seen = [];
        $lines = preg_split('/\r?\n/', $matches[1]);
        if ($lines === false) {
            return null;
        }

        foreach ($lines as $index => $line) {
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ' ') || str_starts_with($line, "\t")) {
                continue;
            }

            if (!str_contains($line, ':')) {
                continue;
            }

            $key = trim(explode(':', $line, 2)[0]);
            if ($key === '') {
                continue;
            }

            $lineNumber = $index + 2;
            if (isset($seen[$key])) {
                return sprintf('Duplicate key "%s" detected at line %d', $key, $lineNumber);
            }

            $seen[$key] = $lineNumber;
        }

        return null;
    }
}
