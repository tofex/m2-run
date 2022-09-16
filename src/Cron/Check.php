<?php

namespace Tofex\Task\Cron;

use Magento\Framework\Exception\AlreadyExistsException;
use Tofex\Task\Model\ResourceModel\Run\CollectionFactory;
use Tofex\Task\Model\ResourceModel\RunFactory;
use Tofex\Task\Model\Run;
use Zend_Date;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Check
{
    /** @var CollectionFactory */
    protected $runCollectionFactory;

    /** @var RunFactory $runResourceFactory */
    protected $runResourceFactory;

    /**
     * @param CollectionFactory $runCollectionFactory
     * @param RunFactory        $runResourceFactory
     */
    public function __construct(CollectionFactory $runCollectionFactory, RunFactory $runResourceFactory)
    {
        $this->runCollectionFactory = $runCollectionFactory;
        $this->runResourceFactory = $runResourceFactory;
    }

    /**
     * @throws AlreadyExistsException
     */
    public function execute()
    {
        $collection = $this->runCollectionFactory->create();

        $collection->addIsRunningFilter();

        $runResource = $this->runResourceFactory->create();

        /** @var Run $run */
        foreach ($collection as $run) {
            $processId = $run->getProcessId();

            if ( ! empty($processId)) {
                if (is_callable('shell_exec') && false === stripos(ini_get('disable_functions'), 'shell_exec')) {
                    $count = trim(shell_exec(sprintf('ps -p %d -o pid= | wc -l', $processId)));

                    if ($count > 0) {
                        continue;
                    }
                } else {
                    continue;
                }
            }

            $run->setFinishAt(Zend_Date::now());

            $runResource->save($run);
        }
    }
}
