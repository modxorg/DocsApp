<?php
require dirname(__DIR__) . '/vendor/autoload.php';

$settingsParser = new MODXDocs\Settings\Parser();
$settingsParser->parse([
    dirname(__DIR__) . '/settings/settings-default.php',
    dirname(__DIR__) . '/settings/settings.php'
]);

$app = new MODXDocs\DocsApp($settingsParser->getSettings());
