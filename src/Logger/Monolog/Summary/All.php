<?php

namespace Tofex\Task\Logger\Monolog\Summary;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class All
    extends AbstractSummary
{
    /**
     * @return string
     */
    protected function getSummaryName(): string
    {
        return 'task_summary_all';
    }

    /**
     * @return string
     */
    protected function getHandlerClass(): string
    {
        return \Tofex\Task\Logger\Monolog\Handler\Summary\All::class;
    }
}
