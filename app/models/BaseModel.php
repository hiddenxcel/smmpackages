<?php

require_once __DIR__ . '/../lib/DB.php';

abstract class BaseModel
{
    protected static function db(): PDO
    {
        static $config = null;

        if ($config === null) {
            $config = require __DIR__ . '/../../config/config.php';
        }

        return DB::connect($config['db']);
    }
}
