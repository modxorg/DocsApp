<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use MODXDocs\DocsApp;
use MODXDocs\Helpers\SettingsParser;

$settingsParser = new SettingsParser();

$app = new DocsApp($settingsParser->getSlimConfig());
