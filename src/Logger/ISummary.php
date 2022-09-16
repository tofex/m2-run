<?php

namespace Tofex\Task\Logger;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
interface ISummary
{
    /**
     * @return Record[]
     */
    public function getRecords(): array;

    /**
     * @param Record $record
     */
    public function addRecord(Record $record);
}
