<?php
if (PHP_SAPI === 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $url = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';

use MODXDocs\DocsApp;
use MODXDocs\Helpers\SettingsParser;

$settingsParser = new SettingsParser();

$app = new DocsApp($settingsParser->getSlimConfig());
$app->run();
