<?php
/**
 * @author gaobinzhan <gaobinzhan@gmail.com>
 */

namespace EasySwoole\Redis\CommandHandel\SentinelCommand;

use EasySwoole\Redis\CommandConst;
use EasySwoole\Redis\CommandHandel\AbstractCommandHandel;
use EasySwoole\Redis\Response;

class SentinelPing extends AbstractCommandHandel
{
    public $commandName = 'PING';


    public function handelCommandData(...$data)
    {
        return ['PING'];
    }


    public function handelRecv(Response $recv)
    {
        return $recv->getData();
    }
}
