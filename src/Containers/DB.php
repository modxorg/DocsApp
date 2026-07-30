<?php

namespace MODXDocs\Containers;

use Psr\Container\ContainerInterface;

class DB
{
    public static function load(ContainerInterface $container): void
    {
        $container->set('db', function () {
            $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $name = $_ENV['DB_NAME'] ?? 'modxdocs';
            $user = $_ENV['DB_USER'] ?? 'modxdocs';
            $pass = $_ENV['DB_PASSWORD'] ?? '';

            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
            $db = new \PDO($dsn, $user, $pass);
            $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

            return $db;
        });
    }
}
