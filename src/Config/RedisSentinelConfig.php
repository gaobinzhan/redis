<?php
/**
 * @author gaobinzhan <gaobinzhan@gmail.com>
 */


namespace EasySwoole\Redis\Config;


class RedisSentinelConfig extends RedisConfig
{
    protected $nodeList = [
        [
            'host' => '127.0.0.1',
            'port' => 26379,
        ]
    ];

    protected $masterName = 'mymaster';

    protected $sentinelAuth = null;

    public function __construct(array $nodeList = [], array $data = null, $autoCreateProperty = false)
    {
        !empty($nodeList) && ($this->nodeList = $nodeList);
        parent::__construct($data, $autoCreateProperty);
    }

    /**
     * @return array|array[]
     */
    public function getNodeList()
    {
        return $this->nodeList;
    }

    /**
     * @param array|array[] $nodeList
     */
    public function setNodeList($nodeList): void
    {
        $this->nodeList = $nodeList;
    }

    /**
     * @return string
     */
    public function getMasterName(): string
    {
        return $this->masterName;
    }

    /**
     * @param string $masterName
     */
    public function setMasterName(string $masterName): void
    {
        $this->masterName = $masterName;
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