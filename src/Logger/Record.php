<?php

namespace Tofex\Task\Logger;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Record
{
    /** @var string */
    private $level;

    /** @var string */
    private $message;

    /**
     * Record constructor.
     *
     * @param string $level
     * @param string $message
     */
    public function __construct(string $level, string $message)
    {
        $this->level = $level;
        $this->message = $message;
    }

    /**
     * @return string
     */
    public function getLevel(): string
    {
        return $this->level;
    }

    /**
     * @param string $level
     */
    public function setLevel(string $level)
    {
        $this->level = $level;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param string $message
     */
    public function setMessage(string $message)
    {
        $this->message = $message;
    }
}
