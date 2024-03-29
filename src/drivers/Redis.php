<?php
declare (strict_types = 1);
//------------------------
//· Redis缓存类
//-------------------------
namespace denha\cache\drivers;

use denha\cache\CacheInterfaceUp;
use Psr\SimpleCache\CacheInterface;
use Redis as RedisClient;
use \Exception;

class Redis implements CacheInterface, CacheInterfaceUp
{

    public $instance;

    private $config = [
        'host'     => '127.0.0.1',
        'password' => '',
        'port'     => 6379,
        'timeout'  => '',
        'database' => false,
        'ttl'      => 3600,
    ];

    public function __construct($config = [])
    {
        $this->setConfig($config)->connect();
    }

    public function getConfigOptions()
    {
        return ['host', 'password', 'port', 'timeout', 'database', 'ttl'];
    }

    public function getType(){
        return ltrim(__CLASS__,"denha\\cache\\drivers\\"); 
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

        try {
            $isConnected = $this->instance->connect($this->config['host'], (int) $this->config['port'], (int) $this->config['timeout']);
        } catch (\Throwable $th) {
            throw new Exception("Redis Server Not Connects");
        }

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

        if($ttl === null || $ttl < 0){
            $ttl = $this->config['ttl'];
        }else if($ttl === 0){
            $ttl = 0;
        }

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

    public function setMultiple($values, $ttl = null)
    {
        $this->instance->mset($values);

        if($ttl === null || $ttl < 0){
            $ttl = $this->config['ttl'];
        }else if($ttl === 0){
            $ttl = 0;
        }

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
