#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use MODXDocs\CLI\Application;
use MODXDocs\DocsApp;
use MODXDocs\Helpers\SettingsParser;

$settingsParser = new SettingsParser();

$docsApp = new DocsApp($settingsParser->getSlimConfig());

$cliApp = new Application($docsApp);
$cliApp->run();
