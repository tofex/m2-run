<?php

namespace Tofex\Task\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class LogLevel
    implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'off',
                'label' => 'Off: no messages'
            ],
            [
                'value' => \Psr\Log\LogLevel::EMERGENCY,
                'label' => 'Fatal: emergency messages'
            ],
            [
                'value' => \Psr\Log\LogLevel::ALERT,
                'label' => 'Fatal: alert messages'
            ],
            [
                'value' => \Psr\Log\LogLevel::CRITICAL,
                'label' => 'Error: critical messages'
            ],
            [
                'value' => \Psr\Log\LogLevel::ERROR,
                'label' => 'Error: error messages'
            ],
            [
                'value' => \Psr\Log\LogLevel::WARNING,
                'label' => 'Warning: warning messages'
            ],
            [
                'value' => \Psr\Log\LogLevel::NOTICE,
                'label' => 'Info: normal but significant messages'
            ],
            [
                'value' => \Psr\Log\LogLevel::INFO,
                'label' => 'Info: informational messages'
            ],
            [
                'value' => \Psr\Log\LogLevel::DEBUG,
                'label' => 'Debug: debug messages'
            ]
        ];
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'off'                        => 'Off: no messages',
            \Psr\Log\LogLevel::EMERGENCY => 'Fatal: emergency messages',
            \Psr\Log\LogLevel::ALERT     => 'Fatal: alert messages',
            \Psr\Log\LogLevel::CRITICAL  => 'Error: critical messages',
            \Psr\Log\LogLevel::ERROR     => 'Error: error messages',
            \Psr\Log\LogLevel::WARNING   => 'Warning: warning messages',
            \Psr\Log\LogLevel::NOTICE    => 'Info: normal but significant messages',
            \Psr\Log\LogLevel::INFO      => 'Info: informational messages',
            \Psr\Log\LogLevel::DEBUG     => 'Debug: debug messages'
        ];
    }
}
