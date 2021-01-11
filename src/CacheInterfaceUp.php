<?php

declare (strict_types = 1);

namespace denha\cache;

interface CacheInterfaceUp
{
    // 设置配置信息
    public function setConfig($config = []);
    // 获取配置信息
    public function getConfigOptions();
    // 链接实例
    public function connect();
    // 关闭连接
    public function close();
}
