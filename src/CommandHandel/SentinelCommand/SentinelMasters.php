<?php
/**
 * @author gaobinzhan <gaobinzhan@gmail.com>
 */


namespace EasySwoole\Redis\CommandHandel\SentinelCommand;


use EasySwoole\Redis\CommandConst;
use EasySwoole\Redis\CommandHandel\AbstractCommandHandel;
use EasySwoole\Redis\Response;

class SentinelMasters extends AbstractCommandHandel
{
    protected $commandName = 'SENTINELMASTERS';

    public function handelCommandData(...$data)
    {
        return [CommandConst::SENTINEL, 'MASTERS'];
    }

    public function handelRecv(Response $recv)
    {
        $masterInfos = $recv->getData();
        $result = [];
        foreach ($masterInfos as $masterInfo) {
            $masterInfoCount = count($masterInfo);
            for ($i = 0; $i < $masterInfoCount / 2; $i++) {
                $result[$masterInfo[1]][$masterInfo[$i * 2]] = $masterInfo[$i * 2 + 1];
            }
        }

        return $result;
    }
}