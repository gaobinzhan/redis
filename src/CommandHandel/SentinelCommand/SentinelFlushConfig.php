<?php
/**
 * @author gaobinzhan <gaobinzhan@gmail.com>
 */


namespace EasySwoole\Redis\CommandHandel\SentinelCommand;


use EasySwoole\Redis\CommandConst;
use EasySwoole\Redis\CommandHandel\AbstractCommandHandel;
use EasySwoole\Redis\Response;

class SentinelFlushConfig extends AbstractCommandHandel
{
    protected $commandName = 'SENTINELFLUSHCONFI';

    public function handelCommandData(...$data)
    {
        return [CommandConst::SENTINEL, 'FLUSHCONFIG'];
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