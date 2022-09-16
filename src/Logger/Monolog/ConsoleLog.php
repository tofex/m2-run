<?php

namespace Tofex\Task\Logger\Monolog;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class ConsoleLog
    extends AbstractLog
{
    /**
     * @return string
     */
    protected function getLogName(): string
    {
        return 'task_log_console';
    }

    /**
     * @return string
     */
    protected function getHandlerClass(): string
    {
        return Handler\ConsoleLog::class;
    }
}
