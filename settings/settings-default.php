<?php
return [
    'settings' => [
        'base_dir' => dirname(__DIR__),
        'docs_dir' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'doc-sources',
        'template_dir' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'templates',
        'cache_dir' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'cache',

        'host' => 'docs.modx.local',
        'directory' => '/',

        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        // Monolog settings
        'logger' => [
            'name' => 'slim-app',
            'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],
    ],
];
