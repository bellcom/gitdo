<?php

namespace GitDo;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;

class DB
{
    protected static $db;

    protected $connection;

    protected function __construct()
    {
        $conf = Config::get('database');

        $this->connection = DriverManager::getConnection([
            'driver'   => 'pdo_sqlite',
            'user'     => $conf['user'],
            'password' => $conf['password'],
            'path'     => __DIR__.'/../../../app/db.sqlite',
        ], new Configuration());
    }

    public static function getInstance()
    {
        if (empty(self::$db)) {
            self::$db = new DB();
        }

        return self::$db;
    }

    public function getConnection()
    {
        return $this->connection;
    }
}
