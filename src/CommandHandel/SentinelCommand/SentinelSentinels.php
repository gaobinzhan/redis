<?php
/**
 * @author gaobinzhan <gaobinzhan@gmail.com>
 */


namespace EasySwoole\Redis\CommandHandel\SentinelCommand;


use EasySwoole\Redis\CommandConst;
use EasySwoole\Redis\CommandHandel\AbstractCommandHandel;
use EasySwoole\Redis\Response;

class SentinelSentinels extends AbstractCommandHandel
{
    protected $commandName = 'SENTINELSENTINELS';

    public function handelCommandData(...$data)
    {
        $masterName = array_shift($data);
        return [CommandConst::SENTINEL, 'SENTINELS', $masterName];
    }

    public function handelRecv(Response $recv)
    {
        $masterInfos = $recv->getData();
        $result = [];
        foreach ($masterInfos as $index => $masterInfo) {
            $masterInfoCount = count($masterInfo);
            for ($i = 0; $i < $masterInfoCount / 2; $i++) {
                $result[$index][$masterInfo[$i * 2]] = $masterInfo[$i * 2 + 1];
            }
        }

        return $result;
    }
}