<?php
/**
 * @author gaobinzhan <gaobinzhan@gmail.com>
 */


namespace Test;


use EasySwoole\Redis\Config\RedisConfig;
use EasySwoole\Redis\Config\RedisSentinelConfig;
use EasySwoole\Redis\Redis;
use EasySwoole\Redis\RedisSentinel;
use PHPUnit\Framework\TestCase;

class RedisSentinelTest extends TestCase
{
    /** @var RedisSentinel $redis */
    protected $redis;

    protected function setUp()
    {
        parent::setUp(); // TODO: Change the autogenerated stub
        $this->redis = new RedisSentinel(new RedisSentinelConfig(REDIS_SENTINEL_SERVER_LIST, [
            'sentinelAuth' => REDIS_SENTINEL_SERVER_AUTH
        ]));
    }

    public function testSentinelMasters()
    {
        $result = $this->redis->sentinelMasters();
        $this->assertEquals('mymaster', $result['mymaster']['name']);
        $this->assertEquals('192.168.3.2', $result['mymaster']['ip']);
        $this->assertEquals(6379, $result['mymaster']['port']);
    }

    public function testSentinelMaster()
    {
        $result = $this->redis->sentinelMaster('mymaster');
        $this->assertEquals('mymaster', $result['name']);
        $this->assertEquals('192.168.3.2', $result['ip']);
        $this->assertEquals(6379, $result['port']);
    }

    public function testSentinelReplicas()
    {
        $result = $this->redis->sentinelReplicas('mymaster');
        $this->assertEquals('192.168.3.3:6379', $result[0]['name']);
        $this->assertEquals('192.168.3.4:6379', $result[1]['name']);
    }

    public function testSentinelSentinels()
    {
        $result = $this->redis->sentinelSentinels('mymaster');
        $this->assertEquals('192.168.3.12', $result[0]['ip']);
        $this->assertEquals('192.168.3.13', $result[1]['ip']);
    }
}