<?php
//------------------------
//· 缓存类
//-------------------------

declare (strict_types = 1);

namespace denha\cache\drivers;

use Psr\SimpleCache\CacheInterface;

class Cookie implements CacheInterface
{
    private $config = [
        // Cookie 作用域
        'domain'   => '',
        //  作用路径
        'path'     => '/',
        //  httponly => true || false
        'httponly' => false,
        //  secure  => true || false
        'secure'   => false,
        // samesite => None || Lax  || Strict 默认Lax
        'samesite' => '',
        // Cookie 前缀，同一域名下安装多套系统时，请修改Cookie前缀
        'prefix'   => 'dh_',
        // Cookie 保存时间
        'expire'   => 0,
        // Cookie 内容加密
        'auth'     => true,
        // 是否开启json解析
        'isjson'   => true,
    ];

    public function __construct($config = [])
    {
        $this->setConfig($config);
    }

    public function setConfig(array $options)
    {

        $this->config = array_merge($this->config, array_change_key_case($options));
        return $this;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function getName($key)
    {

        return $this->config['prefix'] ? $this->config['prefix'] . $key : $key;
    }

    public function get($key, $default = null)
    {
        $key = $this->getName($key);

        $data = null;
        if (isset($_COOKIE[$key])) {
            $data = $_COOKIE[$key];
            $data = $this->config['auth'] ? auth($data, 'DECODE') : $data;
            $data = $this->config['isjson'] ? json_decode($data, true) : $data;
        }

        return $data;
    }

    public function set($key, $value, $ttl = null)
    {
        $key = $this->getName($key);

        $value = $this->config['isjson'] ? json_encode($value) : $value;
        $value = $this->config['auth'] ? auth($value) : $value;

        $this->config['expire'] = $ttl ? $ttl : $this->config['expire'];

        $options = [
            'expires'  => $this->config['expire'] ? (TIME + $this->config['expire']) : 0,
            'path'     => $this->config['path'] ?? '/',
            'domain'   => $this->config['domain'] ?? '',
            'httponly' => $this->config['httponly'] ?? false,
            'secure'   => $this->config['secure'] ?? false,
            'samesite' => $this->config['samesite'] ?? '',
        ];

        setcookie($key, $value, $options);
    }

    public function delete($key)
    {
        $key = $this->getName($key);

        setcookie($key, '', ['expires' => (TIME - 1)]);
    }

    public function clear()
    {
        foreach ($_COOKIE as $key => $value) {
            $this->delete($key);
        }
    }

    public function getMultiple($keys, $default = null)
    {
        foreach ($keys as $key => $value) {
            $data[] = $this->get($key, $default);
        }

        return $data;
    }

    public function setMultiple($values, $ttl = 0)
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
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
        $key = $this->getName($key);

        return isset($_COOKIE[$key]) ? true : false;
    }
}
