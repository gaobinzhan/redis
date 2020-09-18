<?php
/**
 * @author gaobinzhan <gaobinzhan@gmail.com>
 */


namespace EasySwoole\Redis\CommandHandel\SentinelCommand;


use EasySwoole\Redis\CommandConst;
use EasySwoole\Redis\CommandHandel\AbstractCommandHandel;
use EasySwoole\Redis\Response;

class SentinelReplicas extends AbstractCommandHandel
{
    protected $commandName = 'SENTINELREPLICAS';

    public function handelCommandData(...$data)
    {
        $masterName = array_shift($data);
        return [CommandConst::SENTINEL, 'REPLICAS', $masterName];
    }

    public function handelRecv(Response $recv)
    {
        $replicas = $recv->getData();
        $result = [];
        foreach ($replicas as $index => $replica) {
            $replicaCount = count($replica);
            for ($i = 0; $i < $replicaCount / 2; $i++) {
                $result[$index][$replica[$i * 2]] = $replica[$i * 2 + 1];
            }
        }

        return $result;
    }
}