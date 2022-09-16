<?php

namespace Tofex\Task\Console\Command\Script;

use Exception;
use Magento\Framework\App\Area;
use Magento\Framework\Phrase;
use Magento\Framework\Phrase\RendererInterface;
use Magento\Store\Model\App\Emulation;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tofex\Core\Console\Command\Script;
use Tofex\Core\Helper\Instances;
use Tofex\Task\Task\Base;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class Task
    extends Script
{
    /** @var Instances */
    protected $instanceHelper;

    /** @var Emulation */
    protected $appEmulation;

    /** @var RendererInterface */
    protected $renderer;

    /** @var Base */
    private $task;

    /**
     * @param Instances         $instanceHelper
     * @param Emulation         $appEmulation
     * @param RendererInterface $renderer
     */
    public function __construct(
        Instances $instanceHelper,
        Emulation $appEmulation,
        RendererInterface $renderer)
    {
        $this->instanceHelper = $instanceHelper;

        $this->appEmulation = $appEmulation;
        $this->renderer = $renderer;
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int 0 if everything went fine, or an error code
     * @throws Exception
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $storeCode = $input->getOption('store_code');

        $this->appEmulation->startEnvironmentEmulation($storeCode, Area::AREA_ADMINHTML, true);

        Phrase::setRenderer($this->renderer);

        $taskName = $this->getTaskName();

        if (empty($taskName)) {
            throw new Exception('Please specify a task name!');
        }

        $taskId = $input->getOption('id');

        if (empty($taskId)) {
            $taskId = date('Y-m-d_H-i-s');
        }

        $task = $this->getTask();

        $task->init($storeCode, $taskName, $taskId, $input->getOption('log_level'), $input->getOption('console'),
            $input->getOption('test'));

        $task->launch();

        $this->appEmulation->stopEnvironmentEmulation();

        return 0;
    }

    /**
     * @return string
     */
    abstract protected function getTaskName(): string;

    /**
     * @return string
     */
    abstract protected function getClassName(): string;

    /**
     * @return Base
     * @throws Exception
     */
    public function getTask(): Base
    {
        if ($this->task === null) {
            $this->task = $this->instanceHelper->getInstance($this->getClassName());

            if ( ! ($this->task instanceof Base)) {
                throw new Exception(sprintf('Task must extend %s', Base::class));
            }
        }

        return $this->task;
    }
}
