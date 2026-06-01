<?php

namespace MODXDocs\Containers;

use Psr\Container\ContainerInterface;

class DB
{
    public static function load(\DI\Container $container)
    {
        $container->set('db', function (\Psr\Container\ContainerInterface $container) {
            $dir = getenv('BASE_DIRECTORY') . 'db/db.sqlite';
            $db = new \PDO('sqlite:' . $dir);
            $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(\PDO::ATTR_TIMEOUT, 10000);
            $db->exec('PRAGMA busy_timeout = 15000');
            return $db;
        });
    }
}
