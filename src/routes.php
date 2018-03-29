<?php
// Routes

$app->get('/', \MODXDocs\Controllers\Doc::class . ':home')->setName('home');
$app->get('/{version}/{language}/{path:.*}',\MODXDocs\Controllers\Doc::class . ':get')->setName('documentation');

