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
        $container[FilePathService::class] = function () {
            return new FilePathService();
        };

        $container[DocumentService::class] = function (ContainerInterface $container) {
            return new DocumentService(
                $container->get(FilePathService::class),
                $container->get('db')
            );
        };

        $container[VersionsService::class] = function (ContainerInterface $container) {
            return new VersionsService(
                $container->get('router')
            );
        };

        $container[TranslationService::class] = function (ContainerInterface $container) {
            return new TranslationService(
                $container->get('db'),
                $container->get('router')
            );
        };

        $container[SearchService::class] = function (ContainerInterface $container) {
            return new SearchService(
                $container->get('db'),
                $container->get(DocumentService::class)
            );
        };
        $container[IndexService::class] = function (ContainerInterface $container) {
            return new IndexService(
                $container->get('db'),
                $container->get(DocumentService::class)
            );
        };
    }
}
