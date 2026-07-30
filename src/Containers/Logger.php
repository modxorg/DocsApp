<?php

namespace MODXDocs\Containers;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Psr\Container\ContainerInterface;

class Logger
{
    public static function load(ContainerInterface $container): void
    {
        $container->set('logger', function (ContainerInterface $container) {
            $logger = new MonologLogger('app');
            $logger->pushHandler(new StreamHandler(
                isset($_ENV['docker']) ? 'php://stdout' : $_ENV['BASE_DIRECTORY'] . '/logs/app.log',
                Level::Debug
            ));
            return $logger;
        });
    }
}
