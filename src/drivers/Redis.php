<?php
declare (strict_types = 1);
//------------------------
//· 文件缓存类
//-------------------------
namespace denha\cache\drivers;

use Psr\SimpleCache\CacheInterface;
use Redis as RedisClient;
use \Exception;

class Redis implements CacheInterface
{

    public $instance;

    private $config = [
        'host'      => '127.0.0.1',
        'password'  => '',
        'port'      => 6379,
        'timeout'   => '',
        'database ' => false,
        'ttl'       => 3600,
    ];

    public function __construct($config = [])
    {
        $this->setConfig($config);
        $this->connect();
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

        if (!extension_loaded('Redis')) {
            throw new Exception("PHP Extensions Not Find : Redis");
        }

        $this->instance = $this->instance ?: new RedisClient();

        $isConnected = $this->instance->connect($this->config['host'], (int) $this->config['port'], (int) $this->config['timeout']);

        if (!$isConnected) {
            throw new Exception("Redis Server Not Connect");
        }

        if ($isConnected && $this->config['password']) {
            if (!$this->instance->auth($this->config['password'])) {
                throw new Exception("Redis Auth Password Error");
            }
        }

        if ($this->config['database'] !== false) {
            $this->instance->select((int) $this->config['database']);
        }

        return true;

    }

    public static function getConfigOptions()
    {
        return ['host', 'password', 'port', 'timeout', 'database', 'ttl'];
    }

    public function get($key, $default = null)
    {
        $value = $this->instance->get($key);

        return $value == false ? $default : $value;
    }

    public function set($key, $value, $ttl = null)
    {

        $ttl = $ttl > 0 ? $ttl : $this->config['ttl'];

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
            $this->delete($key);
        }

        return true;
    }

    public function has($key)
    {
        return $this->instance->exists($key);
    }
}
