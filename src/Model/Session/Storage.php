<?php

namespace Tofex\Task\Model\Session;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Storage
    extends \Magento\Framework\Session\Storage
{
    /**
     * Constructor
     *
     * @param string $namespace
     * @param array  $data
     */
    public function __construct($namespace = 'task', array $data = [])
    {
        parent::__construct($namespace, $data);
    }
}
