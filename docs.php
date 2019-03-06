#!/usr/bin/env php
<?php
require __DIR__.'/vendor/autoload.php';

use MODXDocs\CLI\Application;
use MODXDocs\CLI\Commands\SearchIndexCommand;
use MODXDocs\CLI\Commands\SearchCommand;

$application = new Application('MODX Documentation', '1.0.0');
$application->add(new SearchIndexCommand());
$application->add(new SearchCommand());
$application->run();
