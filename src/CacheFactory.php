<?php
//------------------------
//· 缓存类
//-------------------------

declare (strict_types = 1);

namespace denha\cache;

use denha\cache\drivers\File;

class CacheFactory
{

    public static $instance = [];

    public static function message($config = [])
    {

        $drivers = $config['type'] ?: 'File';
        $id      = md5(json_encode($config));

        if (!isset(self::$instance[$id])) {
            self::$instance[$id] = new $drivers($config);
        }

        return self::$instance[$id];
    }

}
