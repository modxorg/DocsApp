<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use MODXDocs\DocsApp;
use MODXDocs\Helpers\SettingsParser;
use Tests\BaseTestCase;

$settingsParser = new SettingsParser();

BaseTestCase::setApp(new DocsApp($settingsParser->getSlimConfig()));
