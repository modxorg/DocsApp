<?php

namespace MODXDocs\Containers;

use Slim\Container;

use MODXDocs\Services\FilePathService;
use MODXDocs\Services\RequestPathService;
use MODXDocs\Services\NavigationService;
use MODXDocs\Services\RequestAttributesService;
use MODXDocs\Services\DocumentService;
use MODXDocs\Services\VersionsService;
use MODXDocs\Services\NotFoundService;

class Services
{
    public static function load(Container $container)
    {
        $container[RequestAttributesService::class] = function () {
            return new RequestAttributesService();
        };

        $container[NavigationService::class] = function (Container $container) {
            return new NavigationService(
                $container->get('view'),
                $container->get('logger'),
                $container->get('router'),
                $container->get(RequestPathService::class),
                $container->get(RequestAttributesService::class),
                $container->get(FilePathService::class)
            );
        };

        $container[RequestPathService::class] = function (Container $container) {
            return new RequestPathService(
                $container->get(RequestAttributesService::class)
            );
        };

        $container[FilePathService::class] = function (Container $container) {
            return new FilePathService(
                $container->get(RequestPathService::class),
                $container->get(RequestAttributesService::class)
            );
        };

        $container[DocumentService::class] = function (Container $container) {
            return new DocumentService(
                $container->get(RequestPathService::class),
                $container->get(FilePathService::class),
                $container->get(RequestAttributesService::class)
            );
        };

        $container[VersionsService::class] = function (Container $container) {
            return new VersionsService(
                $container->get('router'),
                $container->get(FilePathService::class),
                $container->get(RequestAttributesService::class)
            );
        };

        $container[NotFoundService::class] = function (Container $container) {
            return new NotFoundService(
                $container->get(RequestAttributesService::class)
            );
        };
    }
}