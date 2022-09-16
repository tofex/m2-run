<?php

namespace Tofex\Task\Model;

use Exception;
use Magento\Framework\Model\AbstractModel;
use Zend_Date;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 *
 * @method int getId()
 * @method void setId(int $id)
 * @method string getStoreCode()
 * @method void setStoreCode(string $storeCode)
 * @method string getTaskName()
 * @method void setTaskName(string $taskName)
 * @method string getTaskId()
 * @method void setTaskId(string $taskId)
 * @method string getProcessId()
 * @method void setProcessId(string $processId)
 * @method int getSuccess()
 * @method void setSuccess(int $success)
 * @method int getEmptyRun()
 * @method void setEmptyRun(int $emptyRun)
 * @method int getTest()
 * @method void setTest(int $test)
 * @method int getMaxMemoryUsage()
 * @method void setMaxMemoryUsage(int $maxMemoryUsage)
 * @method string getStartAt()
 * @method void setStartAt(string $startAt)
 * @method string getFinishAt()
 * @method void setFinishAt(string $finishAt)
 */
class Run
    extends AbstractModel
{
    /**
     * @return void
     */
    public function _construct()
    {
        $this->_init(ResourceModel\Run::class);
    }

    /**
     * @param string $storeCode
     * @param string $taskName
     * @param string $taskId
     * @param int    $test
     *
     * @throws Exception
     */
    public function start(string $storeCode, string $taskName, string $taskId, int $test)
    {
        $this->setStoreCode($storeCode);
        $this->setTaskName($taskName);
        $this->setTaskId($taskId);
        $this->setProcessId(getmypid());
        $this->setTest($test);
        $this->setStartAt(Zend_Date::now());
    }

    /**
     * @param int  $maxMemoryUsage
     * @param bool $success
     * @param bool $emptyRun
     */
    public function finish(int $maxMemoryUsage, bool $success, bool $emptyRun)
    {
        $this->setMaxMemoryUsage($maxMemoryUsage);
        $this->setSuccess($success ? 1 : 0);
        $this->setEmptyRun($emptyRun ? 1 : 0);
        $this->setFinishAt(Zend_Date::now());
    }
}
