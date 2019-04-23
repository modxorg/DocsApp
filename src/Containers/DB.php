<?php

namespace MODXDocs\Containers;

use Psr\Container\ContainerInterface;

class DB
{
    public static function load(ContainerInterface $container)
    {
        $container['db'] = function (ContainerInterface $container) {
            $dir = getenv('BASE_DIRECTORY') . '/db/';
            $db = new \PDO('sqlite:' . $dir . 'db.sqlite');
            $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return $db;
        };
    }
}
