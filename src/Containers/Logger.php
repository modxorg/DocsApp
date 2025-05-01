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
        $container->set('logger', function (ContainerInterface $container) {
            $logger = new \Monolog\Logger('app');
            $logger->pushHandler(new \Monolog\Handler\StreamHandler(
                isset($_ENV['docker']) ? 'php://stdout' : $_ENV['BASE_DIRECTORY'] . '/logs/app.log',
                \Monolog\Logger::DEBUG
            ));
            return $logger;
        });
    }
}