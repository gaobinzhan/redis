<?php
/**
 * @author gaobinzhan <gaobinzhan@gmail.com>
 */


namespace EasySwoole\Redis;


use EasySwoole\Redis\CommandHandel\Auth;
use EasySwoole\Redis\CommandHandel\SentinelCommand\SentinelCkQuorum;
use EasySwoole\Redis\CommandHandel\SentinelCommand\SentinelFailOver;
use EasySwoole\Redis\CommandHandel\SentinelCommand\SentinelGetMasterAddrByName;
use EasySwoole\Redis\CommandHandel\SentinelCommand\SentinelMaster;
use EasySwoole\Redis\CommandHandel\SentinelCommand\SentinelMasters;
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
     * @var array $masterNodeList
     */
    protected $masterNodeList = [];

    /**
     * @var SentinelClient[] $sentinelClientList
     */
    protected $sentinelClientList = [];

    /**
     * @var SentinelClient $defaultSentinelClient ;
     */
    protected $defaultSentinelClient = null;

    public function __construct(RedisSentinelConfig $config)
    {
        $this->config = $config;
        $this->masterNodeInit();
    }

    protected function masterNodeInit()
    {
        $serverList = $this->config->getServerList();
        foreach ($serverList as $server) {
            list($host, $port) = $server;
            $this->sentinelClientList[] = new SentinelClient($host, $port, $this->config->getPackageMaxLength());
        }

        foreach ($this->sentinelClientList as $sentinelClient) {
            $this->clientConnect($sentinelClient);
            $masterInfos = $this->sentinelMasters();

            if ($masterInfos && !empty($masterInfos)) {
                $this->masterNodeList = $masterInfos;
                break;
            }
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
}