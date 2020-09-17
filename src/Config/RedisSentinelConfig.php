<?php
/**
 * @author gaobinzhan <gaobinzhan@gmail.com>
 */


namespace EasySwoole\Redis\Config;


class RedisSentinelConfig extends RedisConfig
{
    protected $serverList = [
        [
            'host' => '127.0.0.1',
            'port' => 26379,
        ]
    ];

    protected $sentinelAuth = null;

    public function __construct(array $serverList = [], array $data = null, $autoCreateProperty = false)
    {
        !empty($serverList) && ($this->serverList = $serverList);
        parent::__construct($data, $autoCreateProperty);
    }

    /**
     * @return array|array[]
     */
    public function getServerList()
    {
        return $this->serverList;
    }

    /**
     * @param array|array[] $serverList
     */
    public function setServerList($serverList): void
    {
        $this->serverList = $serverList;
    }

    /**
     * @return null
     */
    public function getSentinelAuth()
    {
        return $this->sentinelAuth;
    }

    /**
     * @param null $sentinelAuth
     */
    public function setSentinelAuth($sentinelAuth): void
    {
        $this->sentinelAuth = $sentinelAuth;
    }
}