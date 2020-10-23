<?php
/**
 * @author gaobinzhan <gaobinzhan@gmail.com>
 */


namespace EasySwoole\Redis;


use EasySwoole\Redis\CommandHandel\Auth;
use EasySwoole\Redis\CommandHandel\SentinelCommand\SentinelCkQuorum;
use EasySwoole\Redis\CommandHandel\SentinelCommand\SentinelFailOver;
use EasySwoole\Redis\CommandHandel\SentinelCommand\SentinelFlushConfig;
use EasySwoole\Redis\CommandHandel\SentinelCommand\SentinelGetMasterAddrByName;
use EasySwoole\Redis\CommandHandel\SentinelCommand\SentinelMaster;
use EasySwoole\Redis\CommandHandel\SentinelCommand\SentinelMasters;
use EasySwoole\Redis\CommandHandel\SentinelCommand\SentinelPing;
use EasySwoole\Redis\CommandHandel\SentinelCommand\SentinelReplicas;
use EasySwoole\Redis\CommandHandel\SentinelCommand\SentinelReset;
use EasySwoole\Redis\CommandHandel\SentinelCommand\SentinelSentinels;
use EasySwoole\Redis\Config\RedisSentinelConfig;
use EasySwoole\Redis\Exception\RedisSentinelException;

class RedisSentinel extends Redis
{
    /**
     * @var RedisSentinelConfig $config
     */
    protected $config;

    /**
     * @var SentinelClient[] $sentinelClientList
     */
    protected $sentinelClientList = [];

    /**
     * @var SentinelClient $defaultSentinelClient ;
     */
    protected $defaultSentinelClient = null;

    /**
     * @var Client $masterClient
     */
    protected $masterClient = null;

    public function __construct(RedisSentinelConfig $config)
    {
        $this->config = $config;
        $this->sentinelClientInit();
        $this->masterClientInit();
    }

    protected function sentinelClientInit()
    {
        $nodeList = $this->config->getNodeList();
        foreach ($nodeList as $node) {
            list($host, $port) = $node;
            $this->sentinelClientList[] = new SentinelClient($host, $port, $this->config->getPackageMaxLength());
        }
        if (empty($nodeList) || empty($this->sentinelClientList)) {
            throw new RedisSentinelException('redis sentinel node list error!');
        }
    }

    protected function masterClientInit()
    {
        foreach ($this->sentinelClientList as $sentinelClient) {
            $this->clientConnect($sentinelClient);
            $masterInfo = $this->sentinelGetMasterAddrByName($this->config->getMasterName());
            if ($masterInfo && isset($masterInfo['ip']) && isset($masterInfo['port'])) {
                $this->masterClient = new Client((string)$masterInfo['ip'], (int)$masterInfo['port'], $this->config->getPackageMaxLength());
                break;
            }
        }

        if (empty($this->masterClient)) {
            throw new RedisSentinelException('redis sentinel master name error!');
        }
    }

    protected function getSentinelClient(): SentinelClient
    {
        if (next($this->sentinelClientList) === false) {
            reset($this->sentinelClientList);
        }
        return current($this->sentinelClientList);
    }

    public function clientConnect(SentinelClient $client, float $timeout = null): bool
    {
        if ($client->isConnected()) {
            return true;
        }
        if ($timeout === null) {
            $timeout = $this->config->getTimeout();
        }
        $client->setIsConnected($client->connect($timeout));
        if ($client->isConnected() && !empty($this->config->getSentinelAuth())) {
            if (!$this->clientAuth($client, $this->config->getSentinelAuth())) {
                $client->setIsConnected(false);
                throw new RedisSentinelException("auth to redis host {$this->config->getHost()}:{$this->config->getPort()} fail");
            }
        }
        return $client->isConnected();
    }

    public function sendCommandByClient(array $commandList, SentinelClient $client): bool
    {
        //重置重试次数
        $this->tryConnectTimes = 0;
        while ($this->tryConnectTimes <= $this->config->getReconnectTimes()) {
            if ($this->clientConnect($client)) {
                if ($client->sendCommand($commandList)) {
                    $this->defaultSentinelClient = $client;
                    $this->reset();
                    return true;
                }
            }
            $this->tryConnectTimes++;
            $client = $this->getSentinelClient();
        }
        /*
         * 链接超过重连次数，应该抛出异常
         */
        throw new RedisSentinelException("connect to redis host {$client->getHost()}:{$client->getPort()} fail after retry {$this->tryConnectTimes} times");
    }

    public function recvByClient(SentinelClient $client, $timeout = null)
    {
        $result = $client->recv($timeout ?? $this->config->getTimeout());

        if ($result->getStatus() === $result::STATUS_TIMEOUT) {
            //节点断线处理
            $this->clientDisconnect($client);
            throw new RedisSentinelException($this->lastSocketError, $this->lastSocketErrno);
        }

        if ($result->getStatus() == $result::STATUS_ERR) {
            $this->setErrorMsg($result->getMsg());
            $this->setErrorType($result->getErrorType());
            throw new RedisSentinelException($result->getMsg());
        }
        return $result;
    }

    public function clientDisconnect(SentinelClient $client)
    {
        if ($client->isConnected()) {
            $client->setIsConnected(false);
            $this->lastSocketError = $client->socketError();
            $this->lastSocketErrno = $client->socketErrno();
            $client->close();
        }
    }

    public function clientAuth(SentinelClient $client, $password): bool
    {
        $handelClass = new Auth($this);
        $command = $handelClass->getCommand($password);

        if (!$this->sendCommandByClient($command, $client)) {
            return false;
        }
        $recv = $this->recvByClient($client);
        if ($recv === null) {
            return false;
        }
        return $handelClass->getData($recv);
    }

    public function sentinelMasters()
    {
        $client = $this->defaultSentinelClient;
        $handelClass = new SentinelMasters($this);
        $command = $handelClass->getCommand();

        if (!$this->sendCommandByClient($command, $client)) {
            return false;
        }
        $recv = $this->recvByClient($client);
        if ($recv === null) {
            return false;
        }
        return $handelClass->getData($recv);
    }

    public function sentinelMaster(string $masterName)
    {
        $client = $this->defaultSentinelClient;
        $handelClass = new SentinelMaster($this);
        $command = $handelClass->getCommand($masterName);

        if (!$this->sendCommandByClient($command, $client)) {
            return false;
        }
        $recv = $this->recvByClient($client);
        if ($recv === null) {
            return false;
        }
        return $handelClass->getData($recv);
    }

    public function sentinelReplicas(string $masterName)
    {
        $client = $this->defaultSentinelClient;
        $handelClass = new SentinelReplicas($this);
        $command = $handelClass->getCommand($masterName);

        if (!$this->sendCommandByClient($command, $client)) {
            return false;
        }
        $recv = $this->recvByClient($client);
        if ($recv === null) {
            return false;
        }
        return $handelClass->getData($recv);
    }

    public function sentinelSentinels(string $masterName)
    {
        $client = $this->defaultSentinelClient;
        $handelClass = new SentinelSentinels($this);
        $command = $handelClass->getCommand($masterName);

        if (!$this->sendCommandByClient($command, $client)) {
            return false;
        }
        $recv = $this->recvByClient($client);
        if ($recv === null) {
            return false;
        }
        return $handelClass->getData($recv);
    }

    public function sentinelGetMasterAddrByName(string $masterName)
    {
        $client = $this->defaultSentinelClient;
        $handelClass = new SentinelGetMasterAddrByName($this);
        $command = $handelClass->getCommand($masterName);

        if (!$this->sendCommandByClient($command, $client)) {
            return false;
        }
        $recv = $this->recvByClient($client);
        if ($recv === null) {
            return false;
        }
        return $handelClass->getData($recv);
    }

    public function sentinelReset(string $pattern)
    {
        $client = $this->defaultSentinelClient;
        $handelClass = new SentinelReset($this);
        $command = $handelClass->getCommand($pattern);

        if (!$this->sendCommandByClient($command, $client)) {
            return false;
        }
        $recv = $this->recvByClient($client);
        if ($recv === null) {
            return false;
        }
        return $handelClass->getData($recv);
    }

    public function sentinelFailOver(string $masterName)
    {
        $client = $this->defaultSentinelClient;
        $handelClass = new SentinelFailOver($this);
        $command = $handelClass->getCommand($masterName);

        if (!$this->sendCommandByClient($command, $client)) {
            return false;
        }
        $recv = $this->recvByClient($client);
        if ($recv === null) {
            return false;
        }
        return $handelClass->getData($recv);
    }

    public function sentinelCkQuorum(string $masterName)
    {
        $client = $this->defaultSentinelClient;
        $handelClass = new SentinelCkQuorum($this);
        $command = $handelClass->getCommand($masterName);

        if (!$this->sendCommandByClient($command, $client)) {
            return false;
        }
        $recv = $this->recvByClient($client);
        if ($recv === null) {
            return false;
        }
        return $handelClass->getData($recv);
    }

    public function sentinelFlushConfig()
    {
        $client = $this->defaultSentinelClient;
        $handelClass = new SentinelFlushConfig($this);
        $command = $handelClass->getCommand();

        if (!$this->sendCommandByClient($command, $client)) {
            return false;
        }
        $recv = $this->recvByClient($client);
        if ($recv === null) {
            return false;
        }
        return $handelClass->getData($recv);
    }

    public function sentinelPing()
    {
        $client = $this->defaultSentinelClient;
        $handelClass = new SentinelPing($this);
        $command = $handelClass->getCommand();

        if (!$this->sendCommandByClient($command, $client)) {
            return false;
        }
        $recv = $this->recvByClient($client);
        if ($recv === null) {
            return false;
        }
        return $handelClass->getData($recv);
    }
}