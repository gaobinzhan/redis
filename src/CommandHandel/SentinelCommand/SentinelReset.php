<?php
/**
 * @author gaobinzhan <gaobinzhan@gmail.com>
 */


namespace EasySwoole\Redis\CommandHandel\SentinelCommand;


use EasySwoole\Redis\CommandConst;
use EasySwoole\Redis\CommandHandel\AbstractCommandHandel;
use EasySwoole\Redis\Response;

class SentinelReset extends AbstractCommandHandel
{
    protected $commandName = 'SENTINELRESET';

    public function handelCommandData(...$data)
    {
        $pattern = array_shift($data);
        return [CommandConst::SENTINEL, 'RESET', $pattern];
    }

    public function handelRecv(Response $recv)
    {
        return $recv->getData();
    }
}