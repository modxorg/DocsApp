<?php

namespace MODXDocs\Helpers;

use Dotenv\Dotenv;
use Monolog\Logger;

class SettingsParser
{
    const DEFAULT_FILE = '.env';
    const DEV_FILE = '.env-dev';

    public function __construct()
    {
        $baseDir = dirname(dirname(__DIR__)) . '/';
        $dotFile = static::getDotFile($baseDir);
        $dotenv = Dotenv::create($baseDir, $dotFile);
        $dotenv->load();
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
}