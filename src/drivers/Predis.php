<?php
declare (strict_types = 1);
//------------------------
//· 文件缓存类
//-------------------------
namespace denha\cache\drivers;

use denha\cache\CacheInterfaceUp;
use Predis\Connection\ConnectionException as PredisConnectionException;
use Psr\SimpleCache\CacheInterface;
use \Exception;

class Predis implements CacheInterface, CacheInterfaceUp
{

    public $instance;

    private $config = [
        'host'      => '127.0.0.1',
        'password'  => '',
        'port'      => 6379,
        'timeout'   => '',
        'database ' => 3000,
        'ttl'       => 3600,
    ];

    public function __construct($config = [])
    {
        $this->setConfig($config);
        $this->connect();
    }

    public static function getConfigOptions()
    {
        return ['host', 'password', 'port', 'timeout', 'database', 'ttl'];
    }

    /**
     * 覆盖配置信息
     * @date   2020-01-07T11:13:12+0800
     * @author ChenMingjiang
     * @param  array                    $config [description]
     */
    public function setConfig($config = [])
    {
        if ($config) {
            $this->config = array_merge($this->config, $config);
        }

        return $this;
    }

    public function connect()
    {

        if (extension_loaded('Redis')) {
            throw new Exception("Plase Use Redis because Find PHP Extensions Redis");
        }

        if (!class_exists('Predis\Client')) {
            throw new Exception("Plase Composer require predis/predis , not find Predis Class");
        }

        $this->instance = $this->instance ?: new PredisClient($this->config);

        try {
            $this->instance->connect();
        } catch (PredisConnectionException $e) {
            throw new phpFastCacheDriverException('Failed to connect to predis server. Check the Predis documentation: https://github.com/nrk/predis/tree/v1.1#how-to-install-and-use-predis', 0, $e);
        }

        return true;

    }

    public function close()
    {
        $this->instance = null;

        return $this;
    }

    public function get($key, $default = null)
    {
        $value = $this->instance->get($key);

        return $value === false ? $default : json_decode($value, true);
    }

    public function set($key, $value, $ttl = null)
    {

        $ttl   = $ttl > 0 ? $ttl : $this->config['ttl'];
        $value = json_encode($value, JSON_UNESCAPED_UNICODE);

        if ($ttl > 0) {
            return $this->instance->Setex($key, $ttl, $value);
        } else {
            return $this->instance->set($key, $value);
        }

    }

    public function delete($key)
    {
        return $this->instance->del($key);
    }

    public function clear()
    {
        return $this->instance->flushDB();
    }

    public function getMultiple($keys, $default = null)
    {

        return $this->instance->mget($keys);
    }

    public function setMultiple($values, $ttl = 0)
    {
        $this->instance->mset($values);

        $ttl = $ttl > 0 ? $ttl : $this->config['ttl'];

        if ($ttl > 0) {
            foreach ($values as $key => $value) {
                $this->instance->expire($key, $ttl);
            }
        }

        return true;
    }

    public function deleteMultiple($keys)
    {
        foreach ($keys as $key => $value) {
            $this->del($key);
        }

        return true;
    }

    public function has($key)
    {
        return $this->instance->exists($key);
    }

    public function __call($method, $params)
    {
        return $this->instance->$method(...$params);
    }
}
