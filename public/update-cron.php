<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use MODXDocs\Helpers\SettingsParser;

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


if (!file_exists(dirname(__DIR__) . '/.update-sources')) {
    file_put_contents(dirname(__DIR__) . '/.update-sources', '1');
}

http_response_code(200);
echo 'Update scheduled.';
