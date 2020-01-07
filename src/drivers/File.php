<?php
//------------------------
//· 文件缓存类
//-------------------------
namespace denha\cache\drivers;

use Psr\SimpleCache\CacheInterface;

class File implements CacheInterface
{
    public $config = [
        'ext'  => '.txt',
        'ttl'  => 3600,
        'path' => DATA_CACHE_PATH,
    ];

    public static $instance = [];

    public function __construct($config = [])
    {
        $this->setConfig($config);
        $this->connect();
    }

    public static function getConfigOptions()
    {
        return ['ext', 'ttl', 'path'];
    }

    public function get(string $key, $defaul = '')
    {
        $path = $this->config['path'] . md5($key) . $this->config['ext'];
        if (is_file($path)) {
            $data              = file_get_contents($path);
            list($value, $ttl) = !empty($data) ? explode(':::', $data) : [$default, 0];
            // 过期删除
            if ($ttl && $ttl > TIME) {
                $this->delete($key);
                $value = $defaul;
            } else {
                $value = json_decode($value, true);
            }
        } else {
            $value = $defaul;
        }

        return $value;
    }

    public function set(string $key, $value, int $ttl = 0)
    {
        $path    = $this->config['path'] . md5($key) . $this->config['ext'];
        $content = json_encode($value) . ':::' . ($this->config['ttl'] && $this->config['ttl'] > 0 ? (TIME + $ttl) : 0);
        file_put_contents($path, $content);

        return true;
    }

    public function delete(string $key)
    {
        $path = $this->config['path'] . md5($key) . $this->config['ext'];

        if (is_file($path)) {
            unlink($path);
        }

        return true;
    }

    public function clear()
    {
        if (is_dir($this->config['path'])) {
            $lists = scandir($this->config['path']);
            foreach ($lists as $path) {
                if ($path != "." && $path != "..") {
                    if (is_file($path)) {
                        unlink($path);
                    }
                }
            }
        }

        return true;
    }

    public function getMultiple(array $keys, $default = '')
    {
        foreach ($keys as $key) {
            $data[] = $this->get($keys, $default);
        }

        return $data;
    }

    public function setMultiple(array $values, int $ttl = 0)
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(array $keys)
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key)
    {
        $path = $this->config['path'] . md5($key) . $this->config['ext'];
        if (!is_file($path)) {
            return false;
        }

        $data = file_get_contents($path);
        if (empty($data)) {
            $this->delete($key);
            return false;
        }

        list($value, $ttl) = explode(':::', $data);
        if ($ttl && $ttl > TIME) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    public function connect()
    {
        if (!isset($this->config['path'])) {
            throw new Exception("Cache Config.Path not find");
        }

        !is_dir($this->config['path']) ?: mkdir($this->config['path'], 0755, true);
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
    }
}
