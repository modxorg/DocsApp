<?php

namespace MODXDocs\Helpers;

use Dotenv\Dotenv;

class SettingsParser
{
    private const DEFAULT_FILE = '.env';
    private const DEV_FILE = '.env-dev';

    public function __construct()
    {
        $baseDir = dirname(__DIR__, 2) . '/';
        $dotFile = static::getDotFile($baseDir);
        $dotEnv = Dotenv::createImmutable($baseDir, $dotFile);
        $dotEnv->load();
    }

    public function getSlimConfig(): array
    {
        return [
            'settings' => [
                'displayErrorDetails' => $_ENV['DEV'] === '1',
                'addContentLengthHeader' => false,
            ]
        ];
    }

    private static function getDotFile($baseDir): string
    {
        if (file_exists($baseDir . self::DEFAULT_FILE)) {
            return self::DEFAULT_FILE;
        }

        return self::DEV_FILE;
    }
}
