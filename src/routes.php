<?php
// Routes

$app->get('/', \MODXDocs\Controllers\Doc::class . ':home')->setName('home');
$app->get('/{version}/{language}/search',\MODXDocs\Controllers\Search::class . ':get')->setName('search');
$app->post('/{version}/{language}/search',\MODXDocs\Controllers\Search::class . ':post')->setName('search');
$app->get('/{version}/{language}/{path:.*}',\MODXDocs\Controllers\Doc::class . ':get')->setName('documentation');

