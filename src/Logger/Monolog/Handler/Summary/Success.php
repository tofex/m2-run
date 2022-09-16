<?php

namespace Tofex\Task\Logger\Monolog\Handler\Summary;

use Monolog\Logger;
use Psr\Log\LogLevel;
use Tofex\Core\Helper\Registry;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Success
    extends AbstractHandler
{
    /** @var string */
    protected $taskKey;

    /** @var bool */
    private $initialized = false;

    /** @var bool */
    private $taskLogWarnAsError = true;

    /**
     * @param Registry $registryHelper
     */
    public function __construct(Registry $registryHelper)
    {
        parent::__construct($registryHelper);

        $taskName = $this->registryHelper->registry('current_task_name');
        $taskId = $this->registryHelper->registry('current_task_id');

        $this->taskKey = md5(json_encode([$taskName, $taskId]));

        $this->registryHelper->register(sprintf('task_summary_success_%s', $this->taskKey), $this);
    }

    /**
     * @param array $record
     *
     * @return bool
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

        return parent::isHandling($record) && array_key_exists('level', $record) &&
            $record[ 'level' ] < ($this->taskLogWarnAsError ? Logger::WARNING : Logger::ERROR);
    }

    /**
     * @return bool
     */
    protected function initialize(): bool
    {
        if ( ! $this->initialized) {
            $this->taskLogWarnAsError = $this->registryHelper->registry('current_task_log_warn_as_error');

            $taskLogLevel = $this->registryHelper->registry('current_task_log_level');

            if ( ! empty($taskLogLevel)) {
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

                $this->initialized = true;
            } else {
                return false;
            }
        }

        return true;
    }
}
