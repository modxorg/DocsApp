<?php

namespace MODXDocs\Containers;

use Psr\Container\ContainerInterface;

class DB
{
    public static function load(ContainerInterface $container): void
    {
        $container->set('db', function () {
            $dir = $_ENV['BASE_DIRECTORY'] . 'db/db.sqlite';
            $db = new \PDO('sqlite:' . $dir);
            $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(\PDO::ATTR_TIMEOUT, 10000);
            $db->exec('PRAGMA busy_timeout = 15000');
            return $db;
        });
    }
}
