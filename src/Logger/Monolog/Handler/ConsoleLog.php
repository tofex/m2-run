<?php

namespace Tofex\Task\Logger\Monolog\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\ConsoleOutput;
use Tofex\Core\Helper\Registry;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class ConsoleLog
    extends AbstractProcessingHandler
{
    /** @var Registry */
    protected $registryHelper;

    /** @var string */
    protected $taskKey;

    /** @var ConsoleOutput */
    private $output;

    /** @var bool */
    private $initialized = false;

    /**
     * @param Registry $registryHelper
     */
    public function __construct(Registry $registryHelper)
    {
        parent::__construct();

        $this->registryHelper = $registryHelper;

        $taskName = $this->registryHelper->registry('current_task_name');
        $taskId = $this->registryHelper->registry('current_task_id');

        $this->taskKey = md5(json_encode([$taskName, $taskId]));
    }

    /**
     * {@inheritdoc}
     */
    public function isHandling(array $record): bool
    {
        if ( ! $this->initialize()) {
            return false;
        }

        $taskName = $this->registryHelper->registry('current_task_name');
        $taskId = $this->registryHelper->registry('current_task_id');

        $taskKey = md5(json_encode([$taskName, $taskId]));

        if ($taskKey !== $this->taskKey) {
            return false;
        }

        return parent::isHandling($record);
    }

    /**
     * @return bool
     */
    protected function initialize(): bool
    {
        if ( ! $this->initialized) {
            $console = $this->registryHelper->registry('current_task_console');
            $taskName = $this->registryHelper->registry('current_task_name');
            $taskId = $this->registryHelper->registry('current_task_id');
            $taskLogLevel = $this->registryHelper->registry('current_task_log_level');

            if ($console && ! empty($taskName) && ! empty($taskId) && ! empty($taskLogLevel)) {
                switch (strtolower($taskLogLevel)) {
                    case 'off':
                        $level = Logger::EMERGENCY + 1;
                        break;
                    case LogLevel::EMERGENCY:
                        $level = Logger::EMERGENCY;
                        break;
                    case LogLevel::ALERT:
                        $level = Logger::ALERT;
                        break;
                    case LogLevel::CRITICAL:
                        $level = Logger::CRITICAL;
                        break;
                    case LogLevel::ERROR:
                        $level = Logger::ERROR;
                        break;
                    case LogLevel::WARNING:
                        $level = Logger::WARNING;
                        break;
                    case LogLevel::NOTICE:
                        $level = Logger::NOTICE;
                        break;
                    case LogLevel::INFO:
                        $level = Logger::INFO;
                        break;
                    case LogLevel::DEBUG:
                        $level = Logger::DEBUG;
                        break;
                    default:
                        $level = Logger::INFO;
                }
                $this->setLevel($level);
                $this->output = new ConsoleOutput();
                $this->initialized = true;
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param array $record
     *
     * @return void
     */
    protected function write(array $record): void
    {
        if (array_key_exists('formatted', $record)) {
            $this->output->write((string)$record[ 'formatted' ]);
        }
    }
}
