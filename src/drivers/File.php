<?php
declare (strict_types = 1);
//------------------------
//· 文件缓存类
//-------------------------
namespace denha\cache\drivers;

use denha\cache\CacheInterfaceUp;
use Psr\SimpleCache\CacheInterface;
use \Exception;

class File implements CacheInterface, CacheInterfaceUp
{
    private $config = [
        'ext'         => '.txt',
        'ttl'         => 3600,
        'path'        => DATA_CACHE_PATH,
        'probability' => 1, // 1 或者 0
        'diviso'      => 1000, // 回收机制的概率分母
    ];

    public function __construct($config = [])
    {
        $this->setConfig($config);
        $this->connect();
    }

    public function getConfigOptions()
    {
        return ['ext', 'ttl', 'path', 'probability', 'diviso'];
    }

    public function connect()
    {
        if (!isset($this->config['path'])) {
            throw new Exception("Cache Config.Path not find");
        }

        is_dir($this->config['path']) ?: mkdir($this->config['path'], 0755, true);
    }

    public function close()
    {

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

    public function get($key, $default = null)
    {

        // 触发回收机制
        $this->recover();

        $path = $this->config['path'] . md5($key) . $this->config['ext'];

        if (is_file($path)) {
            $data                        = file_get_contents($path);
            list($value, $ttl, $thisKey) = !empty($data) ? explode(':::', $data) : [$default, 0, $key];

            // 过期删除
            if ($ttl && $ttl < time()) {
                $this->delete($key);
                $value = $default;
            } else {
                $value = json_decode($value, true);
            }
        } else {
            $value = $default;
        }

        return $value;
    }

    public function set($key, $value, $ttl = null)
    {

        if ($ttl > 0) {
            $ttl = time() + $ttl;
        } elseif ($ttl <= 0 && $ttl !== null) {
            $ttl = 0;
        } else {
            $ttl = $this->config['ttl'] > 0 ? time() + $this->config['ttl'] : 0;
        }

        $path = $this->config['path'] . md5($key) . $this->config['ext'];

        $content = json_encode($value, JSON_UNESCAPED_UNICODE) . ':::' . $ttl . ':::' . $key;
        file_put_contents($path, $content);

        return true;
    }

    public function delete($key)
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
                    if (is_file($this->config['path'] . $path)) {
                        unlink($this->config['path'] . $path);
                    }
                }
            }
        }

        return true;
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

        if ($ttl && $ttl < time()) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    public function recover()
    {
        if (!$this->config['probability']) {
            return false;
        }

        if (rand(1, (int) $this->config['diviso']) !== 1) {
            return false;
        }

        $lists = scandir($this->config['path']);

        foreach ($lists as $path) {
            if ($path != "." && $path != ".." && is_file($this->config['path'] . $path)) {
                $data = file_get_contents($this->config['path'] . $path);

                list($value, $ttl, $thisKey) = !empty($data) ? explode(':::', $data) : [$default, 0, $key];

                // 过期删除
                if ($ttl && $ttl < time()) {
                    unlink($this->config['path'] . $path);
                }
            }
        }
    }

}
