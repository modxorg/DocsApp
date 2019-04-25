<?php

namespace MODXDocs\Containers;

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
        $container[FilePathService::class] = function () {
            return new FilePathService();
        };

        $container[DocumentService::class] = function (Container $container) {
            return new DocumentService(
                $container->get(FilePathService::class)
            );
        };

        $container[VersionsService::class] = function (Container $container) {
            return new VersionsService(
                $container->get('router')
            );
        };

        $container[TranslationService::class] = function (Container $container) {
            return new TranslationService(
                $container->get('db'),
                $container->get('router')
            );
        };

        $container[SearchService::class] = function (Container $container) {
            return new SearchService(
                $container->get('db'),
                $container->get('router')
            );
        };
    }
}
