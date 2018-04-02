<?php
namespace MODXDocs\Containers;

use Monolog\Processor\UidProcessor;
use Monolog\Handler\StreamHandler;
use Slim\Container;


class Logger
{
    public static function load(Container $container)
    {
        $container['logger'] = function ($container) {
            $settings = $container->get('settings')['logger'];

            $logger = new \Monolog\Logger($settings['name']);
            $logger->pushProcessor(new UidProcessor());
            $logger->pushHandler(new StreamHandler($settings['path'], $settings['level']));

            return $logger;
        };
    }
}