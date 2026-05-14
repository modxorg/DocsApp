<?php

namespace MODXDocs\Containers;

use Monolog\Logger as MonologLogger;
use Monolog\Processor\UidProcessor;
use Monolog\Handler\StreamHandler;
use Psr\Container\ContainerInterface;

class Logger
{
    public static function load(ContainerInterface $container)
    {
        $container['logger'] = function () {

            $logger = new MonologLogger('modx-docs');

            $logger->pushProcessor(new UidProcessor());
            $logger->pushHandler(new StreamHandler(
                isset($_ENV['docker']) ? 'php://stdout' : getenv('BASE_DIRECTORY') . '/logs/app.log',
                MonologLogger::DEBUG
            ));

            return $logger;
        };
    }
}
