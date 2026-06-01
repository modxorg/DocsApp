<?php

namespace MODXDocs\Containers;

use MODXDocs\Services\IndexService;
use MODXDocs\Services\SearchService;
use MODXDocs\Services\TranslationService;
use Slim\Container;

use MODXDocs\Services\FilePathService;
use MODXDocs\Services\DocumentService;
use MODXDocs\Services\VersionsService;

class Services
{
    public static function load(Container $container): void
    {
        $container->set(FilePathService::class, function () {
            return new FilePathService();
        };

        $container->set(DocumentService::class, function (Container $container) {
            return new DocumentService(
                $container->get(FilePathService::class),
                $container->get('db')
            );
        };

        $container->set(VersionsService::class, function (Container $container) {
            return new VersionsService(
                $container->get('router')
            );
        };

        $container->set(TranslationService::class, function (Container $container) {
            return new TranslationService(
                $container->get('db'),
                $container->get('router')
            );
        };

        $container->set(SearchService::class, function (Container $container) {
            return new SearchService(
                $container->get('db'),
                $container->get(DocumentService::class)
            );
        };
        $container->set(IndexService::class, function (Container $container) {
            return new IndexService(
                $container->get('db'),
                $container->get(DocumentService::class)
            );
        };
    }
}
