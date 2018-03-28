<?php
return [
    'settings' => [
        'host' => 'docs.modx.local',
        'directory' => '/',
        'docSources' => __DIR__ . '/doc-sources/',
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
