<?php
/**
 * @author gaobinzhan <gaobinzhan@gmail.com>
 */


namespace EasySwoole\Redis\CommandHandel;


use EasySwoole\Redis\CommandConst;
use EasySwoole\Redis\Response;

class XTrim extends AbstractCommandHandel
{
    protected $commandName = 'XTRIM';

    public function handelCommandData(...$data)
    {
        $key = array_shift($data);
        $maxLen = array_shift($data);
        $isApproximate = array_shift($data);

        $commandData = [CommandConst::XTRIM, $key];
        if (!is_null($maxLen) && is_int($maxLen)) {
            if ($isApproximate) {
                $commandData = array_merge($commandData, ['MAXLEN', '~', $maxLen]);
            } else {
                $commandData = array_merge($commandData, ['MAXLEN', $maxLen]);
            }
        }

        return $commandData;
    }

    public function handelRecv(Response $recv)
    {
        return $recv->getData();
    }
}