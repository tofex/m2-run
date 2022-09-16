<?php

namespace Tofex\Task\Model;

use Magento\Framework\Session\SessionManager;
use Tofex\Task\Model\Session\Storage;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Session
    extends SessionManager
{
    /**
     * @param string|array $key
     * @param mixed        $value
     */
    public function setData($key, $value = null)
    {
        /** @var Storage $storage */
        $storage = $this->storage;

        $storage->setData($key, $value);
    }

    /**
     * @param null|string|array $key
     *
     * @return void
     */
    public function unsetData($key = null)
    {
        /** @var Storage $storage */
        $storage = $this->storage;

        $storage->unsetData($key);
    }
}
