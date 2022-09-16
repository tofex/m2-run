<?php

namespace Tofex\Task\Console\Command;

use Magento\Framework\App\Area;
use Symfony\Component\Console\Input\InputOption;
use Tofex\Core\Console\Command\Command;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class Task
    extends Command
{
    /**
     * @return string
     */
    protected function getCommandName(): string
    {
        return sprintf('task:%s', $this->getTaskName());
    }

    /**
     * @return array
     */
    protected function getCommandDefinition(): array
    {
        return [
            new InputOption('store_code', null, InputOption::VALUE_REQUIRED, 'Code of the store to run import for',
                'admin'),
            new InputOption('id', null, InputOption::VALUE_OPTIONAL, 'Id of the task'),
            new InputOption('log_level', null, InputOption::VALUE_OPTIONAL, 'Log level'),
            new InputOption('console', 'c', InputOption::VALUE_NONE, 'Log on the console'),
            new InputOption('test', 't', InputOption::VALUE_NONE, 'Task runs in test mode')
        ];
    }

    /**
     * @return string
     */
    protected function getArea(): string
    {
        return Area::AREA_ADMINHTML;
    }

    /**
     * @return string
     */
    protected abstract function getTaskName(): string;
}
