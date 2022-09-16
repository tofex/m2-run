<?php

namespace Tofex\Task\Logger\Monolog\Handler\Summary;

use Monolog\Logger;
use Tofex\Core\Helper\Registry;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Error
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

        $this->registryHelper->register(sprintf('task_summary_error_%s', $this->taskKey), $this);
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

        return array_key_exists('level', $record) &&
            $record[ 'level' ] >= ($this->taskLogWarnAsError ? Logger::WARNING : Logger::ERROR);
    }

    /**
     * @return bool
     */
    protected function initialize(): bool
    {
        if ( ! $this->initialized) {
            $this->taskLogWarnAsError = $this->registryHelper->registry('current_task_log_warn_as_error');

            $this->initialized = true;
        }

        return true;
    }
}
