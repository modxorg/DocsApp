<?php

namespace MODXDocs\Helpers;

use Dotenv\Dotenv;

class SettingsParser
{
    const DEFAULT_FILE = '.env';
    const DEV_FILE = '.env-dev';

    public function __construct()
    {
        $baseDir = dirname(dirname(__DIR__)) . '/';
        $dotFile = static::getDotFile($baseDir);
        $dotEnv = Dotenv::createUnsafeImmutable($baseDir, $dotFile);
        $dotEnv->safeLoad();

        if (!is_dir((string)getenv('BASE_DIRECTORY'))) {
            $this->setLocalPaths($baseDir);
        }
    }

    public function getSlimConfig()
    {
        return [
            'settings' => [
                'displayErrorDetails' => getenv('DEV') === '1',
                'addContentLengthHeader' => false,
            ]
        ];
    }

    private static function getDotFile($baseDir)
    {
        if (file_exists($baseDir . SettingsParser::DEFAULT_FILE)) {
            return SettingsParser::DEFAULT_FILE;
        }

        return SettingsParser::DEV_FILE;
    }

    private function setLocalPaths(string $baseDir): void
    {
        $paths = [
            'BASE_DIRECTORY' => $baseDir,
            'DOCS_DIRECTORY' => $baseDir . 'docs/',
            'TEMPLATE_DIRECTORY' => $baseDir . 'templates/',
            'CACHE_DIRECTORY' => $baseDir . 'cache',
        ];

        foreach ($paths as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}
