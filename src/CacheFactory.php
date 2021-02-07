<?php
//------------------------
//· 缓存类
//-------------------------

declare (strict_types = 1);

namespace denha\cache;

class CacheFactory
{

    public static $instance = [];

    private static $handlerClass = [
        /** 文件缓存 */
        'File'    => \denha\cache\drivers\File::class,
        /** Mongodb缓存 */
        'Mongodb' => \denha\cache\drivers\Mongodb::class,
        /** PReids缓存 */
        'Predis'  => \denha\cache\drivers\Predis::class,
        /** Reids缓存 */
        'Redis'   => \denha\cache\drivers\Redis::class,
        /** Cookie缓存 */
        'Cookie'   => \denha\cache\drivers\Cookie::class,
    ];

    public static function message($config = [])
    {

        $type = $config['type'] ?? 'File';
        $id   = md5(json_encode($config));

        if (!isset(self::$handlerClass[$type])) {
            throw new Exception("Cache Handers Not Find :" . $type);
        }

        if (!isset(self::$instance[$id])) {
            self::$instance[$id] = new self::$handlerClass[$type]($config);
        }

        return self::$instance[$id];
    }

}
