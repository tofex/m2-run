<?php

namespace Tofex\Task\Logger\Monolog\Handler;

use Exception;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;
use Tofex\Core\Helper\Registry;
use Tofex\Core\Helper\Stores;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class ErrorLog
    extends Base
{
    /** @var Registry */
    protected $registryHelper;

    /** @var Stores */
    protected $storeHelper;

    /** @var string */
    protected $taskKey;

    /** @var bool */
    private $initialized = false;

    /** @var bool */
    private $taskLogWarnAsError = true;

    /**
     * @param Registry        $registryHelper
     * @param DriverInterface $filesystem
     * @param Stores          $storeHelper
     *
     * @throws Exception
     */
    public function __construct(Registry $registryHelper, DriverInterface $filesystem, Stores $storeHelper)
    {
        parent::__construct($filesystem);

        $this->registryHelper = $registryHelper;
        $this->storeHelper = $storeHelper;

        $taskName = $this->registryHelper->registry('current_task_name');
        $taskId = $this->registryHelper->registry('current_task_id');

        $this->taskKey = md5(json_encode([$taskName, $taskId]));
    }

    /**
     * {@inheritdoc}
     * @throws NoSuchEntityException
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
     * @throws NoSuchEntityException
     */
    protected function initialize(): bool
    {
        if ( ! $this->initialized) {
            $taskName = $this->registryHelper->registry('current_task_name');
            $taskId = $this->registryHelper->registry('current_task_id');
            $this->taskLogWarnAsError = $this->registryHelper->registry('current_task_log_warn_as_error');

            if ( ! empty($taskName) && ! empty($taskId)) {
                $this->url = implode('/', [
                    BP,
                    'var',
                    'log',
                    'task',
                    $taskName,
                    $this->storeHelper->getStore()->getCode(),
                    sprintf('%s.err', $taskId)
                ]);
                $this->initialized = true;
            } else {
                return false;
            }
        }

        return true;
    }
}
