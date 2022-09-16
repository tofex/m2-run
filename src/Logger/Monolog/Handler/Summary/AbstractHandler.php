<?php

namespace Tofex\Task\Logger\Monolog\Handler\Summary;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Psr\Log\LogLevel;
use Tofex\Core\Helper\Registry;
use Tofex\Task\Logger\ISummary;
use Tofex\Task\Logger\Record;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class AbstractHandler
    extends AbstractProcessingHandler
    implements ISummary
{
    /** @var Registry */
    protected $registryHelper;

    /** @var Record[] */
    private $records = [];

    /**
     * @param Registry $registryHelper
     */
    public function __construct(Registry $registryHelper)
    {
        parent::__construct(Logger::EMERGENCY + 1);

        $this->registryHelper = $registryHelper;
    }

    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param array $record
     *
     * @return void
     */
    protected function write(array $record)
    {
        if (array_key_exists('level', $record) && array_key_exists('formatted', $record)) {
            $monologLogLevel = $record[ 'level' ];
            switch ($monologLogLevel) {
                case Logger::EMERGENCY:
                    $logLevel = LogLevel::EMERGENCY;
                    break;
                case Logger::ALERT:
                    $logLevel = LogLevel::ALERT;
                    break;
                case Logger::CRITICAL:
                    $logLevel = LogLevel::CRITICAL;
                    break;
                case Logger::ERROR:
                    $logLevel = LogLevel::ERROR;
                    break;
                case Logger::WARNING:
                    $logLevel = LogLevel::WARNING;
                    break;
                case Logger::NOTICE:
                    $logLevel = LogLevel::NOTICE;
                    break;
                case Logger::INFO:
                    $logLevel = LogLevel::INFO;
                    break;
                case Logger::DEBUG:
                    $logLevel = LogLevel::DEBUG;
                    break;
                default:
                    $logLevel = LogLevel::INFO;
            }

            $this->records[] = new Record($logLevel, (string)$record[ 'formatted' ]);
        }
    }

    /**
     * @return Record[]
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * @param Record $record
     */
    public function addRecord(Record $record)
    {
        $this->records[] = $record;
    }
}
