<?php

namespace MODXDocs\Containers;

use Slim\Container;

use MODXDocs\Services\FilePathService;
use MODXDocs\Services\NavigationService;
use MODXDocs\Services\DocumentService;
use MODXDocs\Services\VersionsService;

class Services
{
    public static function load(Container $container): void
    {
        $container[NavigationService::class] = function (Container $container) {
            return new NavigationService(
                $container->get('view'),
                $container->get('logger'),
                $container->get('router'),
                $container->get(FilePathService::class)
            );
        };

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
    }
}
