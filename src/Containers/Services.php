<?php

namespace MODXDocs\Containers;

use MODXDocs\Services\IndexService;
use MODXDocs\Services\SearchService;
use MODXDocs\Services\TranslationService;
use Psr\Container\ContainerInterface;

use MODXDocs\Services\FilePathService;
use MODXDocs\Services\DocumentService;
use MODXDocs\Services\VersionsService;

class Services
{
    public static function load(ContainerInterface $container): void
    {
        $container->set(FilePathService::class, function () {
            return new FilePathService();
        });

        $container->set(DocumentService::class, function (ContainerInterface $container) {
            return new DocumentService(
                $container->get(FilePathService::class),
                $container->get('db')
            );
        });

        $container->set(VersionsService::class, function (ContainerInterface $container) {
            return new VersionsService(
                $container->get('router')
            );
        });

        $container->set(TranslationService::class, function (ContainerInterface $container) {
            return new TranslationService(
                $container->get('db'),
                $container->get('router')
            );
        });

        $container->set(SearchService::class, function (ContainerInterface $container) {
            return new SearchService(
                $container->get('db'),
                $container->get(DocumentService::class)
            );
        });

        $container->set(IndexService::class, function (ContainerInterface $container) {
            return new IndexService(
                $container->get('db'),
                $container->get(DocumentService::class)
            );
        });
    }
}
