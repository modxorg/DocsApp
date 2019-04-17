<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use MODXDocs\CLI\Application;
use MODXDocs\DocsApp;
use MODXDocs\Helpers\SettingsParser;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

$settingsParser = new SettingsParser();

// Validate the request as coming from GitHub
$secret = getenv('UPDATE_SECRET');
$body = (string)file_get_contents('php://input');
$signature = isset($_SERVER['HTTP_X_HUB_SIGNATURE']) ? (string)$_SERVER['HTTP_X_HUB_SIGNATURE'] : false;
$sha1 = hash_hmac('sha1', $body, $secret);
if (!$signature || hash_equals('sha1='.$sha1, $signature) !== true) {
    http_response_code(400);
    file_put_contents(dirname(__DIR__) . '/logs/' . date('Ymd-his') . '_invalid_pull.log', print_r(['body' => $body, 'signature' => $signature], true));
    echo "Invalid signature. Are you sure you're GitHub?\n";
    @session_write_close();
    exit();
}

$docsApp = new DocsApp($settingsParser->getSlimConfig());
$cliApp = new Application($docsApp);
$cliApp->setAutoExit(false);

$input = new ArrayInput([
    'command' => 'sources:update',
]);

// You can use NullOutput() if you don't need the output
$output = new BufferedOutput();
$cliApp->run($input, $output);
$content = $output->fetch();

// Write output to a log file.
file_put_contents(dirname(__DIR__) . '/logs/' . date('Ymd-his') . '_pull.log', $content);

//echo $content;

http_response_code(200);
echo 'Done.';
