<?php
//------------------------
//· 缓存类
//-------------------------

declare (strict_types = 1);

namespace denha\cache;

class CacheFactory
{

    public static $instance = [];

    public static function message($config = [])
    {

        $type = $config['type'] ?: 'File';
        $id   = md5(json_encode($config));

        if (!isset(self::$instance[$id])) {
            $driversClass        = 'denha\cache\drivers\\' . $type;
            self::$instance[$id] = new $driversClass($config);
        }

        return self::$instance[$id];
    }

}
