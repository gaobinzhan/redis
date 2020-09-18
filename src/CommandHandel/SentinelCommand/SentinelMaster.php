<?php
/**
 * @author gaobinzhan <gaobinzhan@gmail.com>
 */


namespace EasySwoole\Redis\CommandHandel\SentinelCommand;


use EasySwoole\Redis\CommandConst;
use EasySwoole\Redis\CommandHandel\AbstractCommandHandel;
use EasySwoole\Redis\Response;

class SentinelMaster extends AbstractCommandHandel
{
    protected $commandName = 'SENTINELMASTER';

    public function handelCommandData(...$data)
    {
        $masterName = array_shift($data);
        return [CommandConst::SENTINEL, 'MASTER', $masterName];
    }

    public function handelRecv(Response $recv)
    {
        $masterInfo = $recv->getData();
        $result = [];
        $masterInfoCount = count($masterInfo);
        for ($i = 0; $i < $masterInfoCount / 2; $i++) {
            $result[$masterInfo[$i * 2]] = $masterInfo[$i * 2 + 1];
        }

        return $result;
    }
}