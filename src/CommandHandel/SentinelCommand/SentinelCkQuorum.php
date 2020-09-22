<?php
/**
 * @author gaobinzhan <gaobinzhan@gmail.com>
 */


namespace EasySwoole\Redis\CommandHandel\SentinelCommand;


use EasySwoole\Redis\CommandConst;
use EasySwoole\Redis\CommandHandel\AbstractCommandHandel;
use EasySwoole\Redis\Response;

class SentinelCkQuorum extends AbstractCommandHandel
{
    protected $commandName = 'SENTINELCKQUORUM';

    public function handelCommandData(...$data)
    {
        $masterName = array_shift($data);
        return [CommandConst::SENTINEL, 'CKQUORUM', $masterName];
    }

    public function handelRecv(Response $recv)
    {
        return $recv->getData();
    }
}