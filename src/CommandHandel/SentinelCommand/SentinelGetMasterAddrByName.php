<?php
/**
 * @author gaobinzhan <gaobinzhan@gmail.com>
 */


namespace EasySwoole\Redis\CommandHandel\SentinelCommand;


use EasySwoole\Redis\CommandConst;
use EasySwoole\Redis\CommandHandel\AbstractCommandHandel;
use EasySwoole\Redis\Response;

class SentinelGetMasterAddrByName extends AbstractCommandHandel
{
    protected $commandName = 'SENTINELGETMASTERADDRBYNAME';

    public function handelCommandData(...$data)
    {
        $masterName = array_shift($data);
        return [CommandConst::SENTINEL, 'GET-MASTER-ADDR-BY-NAME', $masterName];
    }

    public function handelRecv(Response $recv)
    {
        $result = $recv->getData();
        return $result ? ['ip' => $result[0], 'port' => $result[1]] : $result;
    }
}