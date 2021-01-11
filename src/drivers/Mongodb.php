<?php
declare (strict_types = 1);
//------------------------
//· Mongodb缓存类
//-------------------------
namespace denha\cache\drivers;

use denha\cache\CacheInterfaceUp;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use MongoDB\Driver\Exception\Exception as MongoDBException;
use MongoDB\Driver\Manager as MongoDBClient;
use MongoDB\Driver\Query;
use MongoDB\Driver\WriteConcern;
use Psr\SimpleCache\CacheInterface;
use \Exception;

class Mongodb implements CacheInterface, CacheInterfaceUp
{

    public $instance;
    public $bulk;
    public $writeConcern;

    private $config = [
        'host'       => '127.0.0.1',
        'username'   => '',
        'password'   => '',
        'port'       => 27017,
        'timeout'    => 10,
        'database'   => 'Cache',
        'collection' => 'Keys',
        'ttl'        => 3600,
    ];

    public function __construct($config = [])
    {
        $this->setConfig($config);
        $this->connect();
    }

    public static function getConfigOptions()
    {
        return ['host', 'username', 'password', 'port', 'timeout', 'database', 'collection', 'ttl'];
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

        if (!extension_loaded('MongoDB')) {
            throw new Exception("Plase Use Redis because Find PHP Extensions MongoDB");
        }

        try {
            $this->instance = $this->instance ?: new MongoDBClient($this->buildConnectionURI(), ['connectTimeoutMS' => $this->config['timeout'] * 1000]);
        } catch (MongoDBException $e) {
            throw new MongoDBException('Failed to connect to MongoDB server', 0, $e);
        }

        if ($this->instance instanceof \MongoDB\Driver\Manager) {
            $this->writeConcern = new WriteConcern(WriteConcern::MAJORITY, 100);

            // 创建索引
            if (!$this->checkIndexs()) {
                $this->createIndex();
            }
        }

        return true;

    }

    public function close()
    {
        $this->instance = null;

        return $this;
    }

    /** 检测索引 */
    protected function checkIndexs()
    {
        $collectionIndexs = $this->instance->executeCommand('Cache', new Command(['collStats' => $this->config['collection']]))->toArray()[0]->indexDetails;

        if (!isset($collectionIndexs->ttl) || !isset($collectionIndexs->key)) {
            return false;
        }

        unset($collectionIndexs);

        return true;
    }

    /** 创建缓存索引 */
    protected function createIndex()
    {
        $cmdIndex = [
            'createIndexes' => $this->config['collection'],
            'indexes'       => [
                [
                    'name'               => 'ttl',
                    'key'                => ['ttl' => 1],
                    'expireAfterSeconds' => 0,
                ],
                [
                    'name'   => 'key',
                    'key'    => ['key' => 1],
                    'unique' => true,
                ],
            ],
        ];

        $this->instance->executeCommand($this->config['database'], new Command($cmdIndex));

        unset($cmdIndex);

        return true;
    }

    /** 获取过期时间 */
    protected function getTtl($ttl)
    {
        if ($ttl > 0) {
            $ttl = time() + $ttl;
        } elseif ($ttl <= 0 && $ttl !== null) {
            $ttl = time() + 316224000; // 默认十年过期
        } else {
            $ttl = $this->config['ttl'] > 0 ? time() + $this->config['ttl'] : time() + 31622400;
        }

        return $ttl;
    }

    /** 组合链接Url */
    protected function buildConnectionURI()
    {
        $parts = [
            'mongodb://',
            ($this->config['username'] ?: ''),
            ($this->config['password'] ? ':' . $this->config['password'] : ''),
            ($this->config['username'] ? '@' : ''),
            $this->config['host'],
            ($this->config['port'] != '27017' ? ':' . $this->config['port'] : ''),
        ];

        return implode('', $parts);
    }

    public function get($key, $default = null)
    {
        $query = new Query(['key' => $key], ['projection' => ['_id' => 0, 'key' => 0], 'limit' => 1]);
        $rows  = $this->instance->executeQuery($this->config['database'] . '.' . $this->config['collection'], $query)->toArray();

        unset($query);

        if (isset($rows[0]) && is_object($rows[0]->value)) {
            return (array) $rows[0]->value;
        } elseif (isset($rows[0])) {
            return $rows[0]->value;
        } else {
            return $default;
        }

    }

    public function set($key, $value, $ttl = null)
    {

        $bulk = new BulkWrite();

        $bulk->update(
            ['key' => $key],
            ['$set' => ['key' => $key, 'value' => $value, 'ttl' => new UTCDateTime($this->getTtl($ttl) * 1000)]],
            ['multi' => false, 'upsert' => true]
        );

        return $this->instance->executeBulkWrite($this->config['database'] . '.' . $this->config['collection'], $bulk, $this->writeConcern);

    }

    public function delete($key)
    {

        $bulk = new BulkWrite();
        $bulk->delete(['key' => $key]);

        return $this->instance->executeBulkWrite($this->config['database'] . '.' . $this->config['collection'], $bulk);
    }

    public function clear()
    {
        $bulk = new BulkWrite();
        $bulk->delete([]);

        $this->instance->executeBulkWrite($this->config['database'] . '.' . $this->config['collection'], $bulk);

        return true;
    }

    public function getMultiple($keys, $default = null)
    {

        $values = [];
        foreach ($keys as $key) {
            $values[] = $this->get($key, $default);
        }

        return $values;
    }

    public function setMultiple($values, $ttl = 0)
    {

        $ttl  = new UTCDateTime($this->getTtl($ttl) * 1000);
        $bulk = new BulkWrite();

        foreach ($values as $key => $value) {
            $bulk->update(
                ['key' => $key],
                ['$set' => ['key' => $key, 'value' => $value, 'ttl' => $ttl]],
                ['multi' => true, 'upsert' => true]
            );
        }

        return $this->instance->executeBulkWrite($this->config['database'] . '.' . $this->config['collection'], $bulk, $this->writeConcern);

    }

    public function deleteMultiple($keys)
    {

        $ors = [];
        foreach ($keys as $key => $value) {
            $ors[] = ['key' => $value];
        }

        $bulk = new BulkWrite();
        $bulk->delete(['$or' => $ors]);

        $this->instance->executeBulkWrite($this->config['database'] . '.' . $this->config['collection'], $bulk);

        return true;
    }

    public function has($key)
    {

        $query = new Query(['key' => $key], ['projection' => ['_id' => 0, 'key' => 0], 'limit' => 1]);
        $rows  = $this->instance->executeQuery($this->config['database'] . '.' . $this->config['collection'], $query)->toArray();

        return isset($rows[0]) ? true : false;
    }

    public function __call($method, $params)
    {
        return $this->instance->$method(...$params);
    }
}
