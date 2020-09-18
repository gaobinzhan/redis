<?php
/**
 * @author gaobinzhan <gaobinzhan@gmail.com>
 */


namespace EasySwoole\Redis\CommandHandel\SentinelCommand;


use EasySwoole\Redis\CommandConst;
use EasySwoole\Redis\CommandHandel\AbstractCommandHandel;
use EasySwoole\Redis\Response;

class SentinelFailOver extends AbstractCommandHandel
{
    protected $commandName = 'SENTINELFAILOVER';

    public function handelCommandData(...$data)
    {
        $masterName = array_shift($data);
        return [CommandConst::SENTINEL, 'FAILOVER', $masterName];
    }

    public function handelRecv(Response $recv)
    {
        $data = $recv->getData();
        if ($data === 'OK') {
            return true;
        }

        return $data;
    }
}