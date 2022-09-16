<?php

namespace Tofex\Task\Model\ResourceModel\Run;

use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Tofex\Task\Model\Run;
use Zend_Db_Select;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Collection
    extends AbstractCollection
{
    /**
     * @return void
     */
    public function _construct()
    {
        $this->_init(Run::class, \Tofex\Task\Model\ResourceModel\Run::class);
    }

    /**
     * @return Select
     */
    public function getSelectCountSql(): Select
    {
        $selectCount = parent::getSelectCountSql();

        $selectCount->reset(Zend_Db_Select::HAVING);

        return $selectCount;
    }

    /**
     * @return void
     */
    public function addIsRunningFilter()
    {
        $this->addFieldToFilter('finish_at', ['null' => true]);
    }
}
