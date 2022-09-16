<?php

namespace Tofex\Task\Task;

use Exception;
use Magento\Framework\App\Area;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\AppInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\Store;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Tofex\Core\Helper\Registry;
use Tofex\Help\Files;
use Tofex\Task\Helper\Data;
use Tofex\Task\Logger\ISummary;
use Tofex\Task\Logger\Monolog\Summary\AbstractSummary;
use Tofex\Task\Logger\Record;
use Tofex\Task\Model\RunFactory;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class Base
    implements AppInterface
{
    /** The Id of the Event Stream for all Log events from Level INFO to FATAL. */
    const SUMMARY_TYPE_ALL = 'all';

    /** The Id of the Event Stream for all Log events from Level ERROR to FATAL. */
    const SUMMARY_TYPE_ERROR = 'error';

    /** The Id of the Event Stream for all Log events from Level INFO. */
    const SUMMARY_TYPE_SUCCESS = 'success';

    /** @var Files */
    protected $fileHelper;

    /** @var Registry */
    protected $registryHelper;

    /** @var Data */
    protected $helper;

    /** @var LoggerInterface */
    protected $logging;

    /** @var TransportBuilder */
    protected $transportBuilder;

    /** @var Emulation */
    protected $appEmulation;

    /** @var DirectoryList */
    protected $directoryList;

    /** @var RunFactory */
    protected $runFactory;

    /** @var \Tofex\Task\Model\ResourceModel\RunFactory */
    protected $runResourceFactory;

    /** @var string */
    private $storeCode;

    /** @var string */
    private $taskName;

    /** @var string */
    private $taskId;

    /** @var bool */
    private $test = false;

    /** @var resource */
    private $lockFile;

    /** @var array */
    private $dependencyList = [];

    /** @var boolean */
    private $waitForPredecessor = false;

    /** @var array */
    private $prohibitSummarySending = [];

    /** @var bool */
    private $allowAdminStore = true;

    /** @var array */
    private $registryValues = [];

    /**
     * @param Files                                      $fileHelper
     * @param Registry                                   $registryHelper
     * @param Data                                       $helper
     * @param LoggerInterface                            $logging
     * @param Emulation                                  $appEmulation
     * @param DirectoryList                              $directoryList
     * @param TransportBuilder                           $transportBuilder
     * @param RunFactory                                 $runFactory
     * @param \Tofex\Task\Model\ResourceModel\RunFactory $runResourceFactory
     */
    public function __construct(
        Files $fileHelper,
        Registry $registryHelper,
        Data $helper,
        LoggerInterface $logging,
        Emulation $appEmulation,
        DirectoryList $directoryList,
        TransportBuilder $transportBuilder,
        RunFactory $runFactory,
        \Tofex\Task\Model\ResourceModel\RunFactory $runResourceFactory)
    {
        $this->fileHelper = $fileHelper;
        $this->registryHelper = $registryHelper;
        $this->helper = $helper;

        $this->logging = $logging;
        $this->appEmulation = $appEmulation;
        $this->directoryList = $directoryList;
        $this->transportBuilder = $transportBuilder;
        $this->runFactory = $runFactory;
        $this->runResourceFactory = $runResourceFactory;
    }

    /**
     * @return void
     */
    abstract protected function prepare();

    /**
     * @param bool $success
     *
     * @return void
     */
    abstract protected function dismantle(bool $success);

    /**
     * @param string      $storeCode
     * @param string      $taskName
     * @param string      $taskId
     * @param string|null $logLevel
     * @param bool        $console
     * @param bool        $test
     *
     * @return void
     * @throws NoSuchEntityException
     */
    public function init(
        string $storeCode,
        string $taskName,
        string $taskId,
        string $logLevel = null,
        bool $console = false,
        bool $test = false)
    {
        $this->storeRegistryValues();

        $this->storeCode = $storeCode;
        $this->taskName = $taskName;
        $this->taskId = $taskId;
        $this->test = $test;

        $maxMemory = $this->getTaskSetting('max_memory');

        if ( ! empty($maxMemory)) {
            ini_set('memory_limit', sprintf('%dM', $maxMemory));
        }

        $list = $this->getTaskSetting('depends_on');

        if ( ! empty($list)) {
            $this->dependencyList = explode(";", $list);
        }

        if ($this->getTaskSetting('wait_for_predecessor')) {
            $this->waitForPredecessor = true;
        }

        if (empty($logLevel)) {
            $logLevel =
                (string)$this->helper->getTaskConfigValue($this->taskName, 'logging', 'log_level', LogLevel::INFO);
        }

        $logWarnAsError = (int)$this->helper->getTaskConfigValue($this->taskName, 'logging', 'log_warn_as_error', 1);

        $this->registryHelper->register('current_task_name', $taskName, false, true);
        $this->registryHelper->register('current_task_id', $taskId, false, true);
        $this->registryHelper->register('current_task_log_level', $logLevel, false, true);
        $this->registryHelper->register('current_task_log_warn_as_error', $logWarnAsError, false, true);
        $this->registryHelper->register('current_task_console', $console, false, true);
    }

    /**
     * @return void
     */
    protected function storeRegistryValues()
    {
        $this->registryValues[ 'current_task_name' ] = $this->registryHelper->registry('current_task_name');
        $this->registryValues[ 'current_task_id' ] = $this->registryHelper->registry('current_task_id');
        $this->registryValues[ 'current_task_log_level' ] = $this->registryHelper->registry('current_task_log_level');
        $this->registryValues[ 'current_task_log_warn_as_error' ] =
            $this->registryHelper->registry('current_task_log_warn_as_error');
        $this->registryValues[ 'current_task_console' ] = $this->registryHelper->registry('current_task_console');
    }

    /**
     * @return void
     * @throws Exception
     */
    public function launch()
    {
        $memoryUsageStart = $this->getCurrentMemoryUsage();

        if ( ! $this->allowAdminStore && strcasecmp(trim($this->storeCode), 'admin') === 0) {
            throw new Exception(__('Task is not allowed to run with admin store.'));
        }

        $run = $this->runFactory->create();

        $run->start($this->storeCode, $this->taskName, $this->taskId, $this->test);

        $this->runResourceFactory->create()->save($run);

        $start = time();

        $success = true;

        // add Current task to the dependency list
        if ( ! $this->waitForPredecessor) {
            $this->dependencyList[] = $this->taskName;
        }

        if ($this->checkDependencyTask($this->dependencyList)) {
            if ($this->waitForPredecessor) {
                flock($this->getLockFile($this->taskName), LOCK_EX);
            } else {
                flock($this->getLockFile($this->taskName), LOCK_EX | LOCK_NB);
            }
        } else {
            $success = false;
        }

        if ($this->isTest()) {
            $this->logging->info(__('Task is running in test mode'));
        }

        try {
            $this->prepare();
        } catch (Exception $exception) {
            $this->logging->error(sprintf(__('Could not prepare task because: %s'), $exception->getMessage()));
            $this->logging->error($exception);

            $success = false;
        }

        if ($success) {
            try {
                $this->logging->info(sprintf(__('Running task: %s'), $this->getTaskName()));

                $this->runTask();

                $this->logging->info(sprintf(__('Finished task: %s'), $this->getTaskName()));
            } catch (Exception $exception) {
                $this->logging->error(sprintf(__('Could not run task because: %s'), $exception->getMessage()));
                $this->logging->error($exception);

                $success = false;
            }
        }

        try {
            $this->dismantle($success);
        } catch (Exception $exception) {
            $this->logging->error(sprintf(__('Could not dismantle task because: %s'), $exception->getMessage()));
            $this->logging->error($exception);
        }

        try {
            $this->sendSummary(static::SUMMARY_TYPE_SUCCESS);
        } catch (Exception $exception) {
            $this->logging->error(sprintf(__('Could not send success summary because: %s'), $exception->getMessage()));
            $this->logging->error($exception);
        }

        try {
            $this->sendSummary(static::SUMMARY_TYPE_ERROR);
        } catch (Exception $exception) {
            $this->logging->error(sprintf(__('Could not send error summary because: %s'), $exception->getMessage()));
            $this->logging->error($exception);
        }

        $this->unLockFile($this->taskName);

        $duration = time() - $start;

        $minutes = intval(floor($duration / 60));
        $seconds = $duration % 60;

        $memoryUsageEnd = $this->getCurrentMemoryUsage();

        $this->logging->info(sprintf(__('Duration: %d minute(s), %d second(s)'), $minutes, $seconds));
        $this->logging->info(sprintf(__('Max memory usage: %s MB'),
            number_format(bcsub($memoryUsageEnd, $memoryUsageStart), 0, ',', '.')));

        $run->finish(bcsub($memoryUsageEnd, $memoryUsageStart), $success, $this->isEmptyRun());

        $this->runResourceFactory->create()->save($run);

        $this->resetRegistryValues();
    }

    /**
     * @return bool
     */
    abstract public function isEmptyRun(): bool;

    /**
     * @return void
     */
    protected function resetRegistryValues()
    {
        foreach ($this->registryValues as $key => $value) {
            $this->registryHelper->register($key, $value, false, true);
        }
    }

    /**
     * Ability to handle exceptions that may have occurred during bootstrap and launch
     *
     * Return values:
     * - true: exception has been handled, no additional action is needed
     * - false: exception has not been handled - pass the control to Bootstrap
     *
     * @param Bootstrap $bootstrap
     * @param Exception $exception
     *
     * @return bool
     */
    public function catchException(Bootstrap $bootstrap, Exception $exception): bool
    {
        $this->logging->emergency($exception);

        return true;
    }

    /**
     * @param string $taskName
     *
     * @return resource
     * @throws FileSystemException
     */
    private function createLockFile(string $taskName)
    {
        $tempPath = $this->directoryList->getPath(DirectoryList::TMP);

        $this->fileHelper->createDirectory($tempPath);

        $file = sprintf('%s/task_%s.lock', $tempPath, $taskName);

        $lockFile = fopen($file, is_file($file) ? 'w' : 'x');

        fwrite($lockFile, date('c'));

        return $lockFile;
    }

    /**
     * @param string $taskName
     *
     * @return resource
     * @throws FileSystemException
     */
    private function getLockFile(string $taskName)
    {
        if ($this->lockFile === null) {
            $this->lockFile = $this->createLockFile($taskName);
        }

        return $this->lockFile;
    }

    /**
     * @param string $taskName
     *
     * @throws FileSystemException
     */
    private function unLockFile(string $taskName)
    {
        $lockFile = $this->getLockFile($taskName);

        flock($lockFile, LOCK_UN);

        if ($lockFile) {
            fclose($lockFile);
        }

        $this->lockFile = null;
    }

    /**
     * Function to check for dependencies
     *
     * @param array $dependencyList
     *
     * @return bool
     * @throws FileSystemException
     */
    private function checkDependencyTask(array $dependencyList): bool
    {
        foreach ($dependencyList as $taskName) {
            if ( ! flock($this->createLockFile($taskName), LOCK_EX | LOCK_NB)) {
                $this->logging->error(sprintf(__('The task: %s is still running and block the process of this task: %s.'),
                    $taskName, $this->taskName));

                return false;
            } else {
                flock($this->createLockFile($taskName), LOCK_UN);

                if ($this->createLockFile($taskName)) {
                    fclose($this->createLockFile($taskName));
                }
            }
        }

        return true;
    }

    /**
     * @param string $storeCode
     * @param string $taskName
     * @param bool   $test
     *
     * @return array
     * @throws Exception
     */
    public function launchFromAdmin(string $storeCode, string $taskName, bool $test = false)
    {
        $this->init($storeCode, $taskName, date('Y-m-d_H-i-s'), null, false, $test);

        $this->launch();

        return $this->getSummary(static::SUMMARY_TYPE_ALL, false, true);
    }

    /**
     * @return  void
     */
    abstract protected function runTask();

    /**
     * @param string $type
     *
     * @return void
     * @throws NoSuchEntityException
     * @throws MailException
     */
    protected function sendSummary(string $type)
    {
        $sendSummary = $this->helper->getTaskConfigValue($this->taskName, 'summary_' . $type, 'send', false, true);

        if ($sendSummary) {
            $content = $this->getSummary($type);

            if ( ! empty($content)) {
                $content = $this->getSummary($type, true, true);

                $sender = $this->helper->getTaskConfigValue($this->taskName, 'summary_' . $type, 'sender', 'general');
                $subject = $this->helper->getTaskConfigValue($this->taskName, 'summary_' . $type, 'subject');
                $recipients = $this->helper->getTaskConfigValue($this->taskName, 'summary_' . $type, 'recipients');
                $copyRecipients =
                    $this->helper->getTaskConfigValue($this->taskName, 'summary_' . $type, 'copy_recipients');
                $blindCopyRecipients =
                    $this->helper->getTaskConfigValue($this->taskName, 'summary_' . $type, 'blind_copy_recipients');

                $senderEmail = $this->helper->getStoreConfig('trans_email/ident_' . $sender . '/email');
                $senderName = $this->helper->getStoreConfig('trans_email/ident_' . $sender . '/name');

                if (empty($subject)) {
                    $subject = __('Task: ') . $this->taskName . ' | ' . __('Summary: ') . $type;
                }

                $storeDefaultTitle = $this->helper->getStoreConfig('design/head/default_title');

                if ( ! empty($storeDefaultTitle)) {
                    $subject = $storeDefaultTitle . ' - ' . $subject;
                }

                if ( ! empty($senderEmail) && ! empty($senderName)) {
                    if ($this->isProhibitSummarySending($type)) {
                        $this->logging->debug(sprintf(__('Suppress sending summary of type: %s with subject: %s to recipients: %s'),
                            $type, $subject, $recipients));
                    } else if ( ! empty($recipients)) {
                        $postObject = new DataObject();
                        $postObject->setData(['subject' => $subject, 'content' => $content]);

                        $this->transportBuilder->setTemplateIdentifier('task_result_template');
                        $this->transportBuilder->setTemplateOptions([
                            'area'  => Area::AREA_ADMINHTML,
                            'store' => Store::DEFAULT_STORE_ID
                        ]);
                        $this->transportBuilder->setTemplateVars(['data' => $postObject]);
                        $this->transportBuilder->setFromByScope(['email' => $senderEmail, 'name' => $senderName],
                            $this->storeCode);

                        $recipientEmails = explode(';', $recipients);

                        foreach ($recipientEmails as $recipientEmail) {
                            $this->transportBuilder->addTo([trim($recipientEmail)]);
                        }

                        if ( ! empty($copyRecipients)) {
                            $copyRecipientEmails = explode(';', $copyRecipients);

                            foreach ($copyRecipientEmails as $recipientEmail) {
                                $this->transportBuilder->addCc(trim($recipientEmail));
                            }
                        }

                        if ( ! empty($blindCopyRecipients)) {
                            $blindCopyRecipientEmails = explode(';', $blindCopyRecipients);

                            foreach ($blindCopyRecipientEmails as $recipientEmail) {
                                $this->transportBuilder->addBcc(trim($recipientEmail));
                            }
                        }

                        try {
                            if ( ! empty($copyRecipients)) {
                                if ( ! empty($blindCopyRecipients)) {
                                    $this->logging->debug(sprintf(__('Sending summary of type: %s with subject: %s to recipients: %s, copy to: %s and blind copy to: %s'),
                                        $type, $subject, $recipients, $copyRecipients, $blindCopyRecipients));
                                } else {
                                    $this->logging->debug(sprintf(__('Sending summary of type: %s with subject: %s to recipients: %s, copy to: %s'),
                                        $type, $subject, $recipients, $copyRecipients));
                                }
                            } else {
                                $this->logging->debug(sprintf(__('Sending summary of type: %s with subject: %s to recipients: %s'),
                                    $type, $subject, $recipients));
                            }

                            $this->appEmulation->stopEnvironmentEmulation();

                            $transport = $this->transportBuilder->getTransport();

                            $transport->sendMessage();

                            $this->appEmulation->startEnvironmentEmulation($this->storeCode, Area::AREA_ADMINHTML,
                                true);
                        } catch (Exception $exception) {
                            $this->logging->error(sprintf(__('Could not send summary of type: %s because: %s'), $type,
                                $exception->getMessage()));
                            $this->logging->error($exception);
                        }
                    } else {
                        $this->logging->error(sprintf(__('Could not send summary of type: %s because no recipients were configured'),
                            $type));
                    }
                } else {
                    $this->logging->error(sprintf(__('Could not send summary of type: %s because no sender was configured'),
                        $type));
                }
            }
        }
    }

    /**
     * @return bool
     */
    public function isTest(): bool
    {
        return $this->test === true;
    }

    /**
     * @param string $type
     * @param bool   $flat
     * @param bool   $addSummaryInformation
     *
     * @return string|array|null
     */
    public function getSummary(
        string $type = self::SUMMARY_TYPE_ALL,
        bool $flat = true,
        bool $addSummaryInformation = false)
    {
        $taskKey = md5(json_encode([$this->getTaskName(), $this->getTaskId()]));

        /** @var ISummary $summary */
        $summary = $this->registryHelper->registry(sprintf('task_summary_%s_%s', $type, $taskKey));

        if ($summary) {
            $records = $summary->getRecords();
            if ($flat) {
                $flatSummary = '';

                if ($addSummaryInformation) {
                    foreach ($this->getSummaryInformation() as $record) {
                        $flatSummary .= "\n" . trim($record->getMessage());
                    }

                    $flatSummary .= "\n";
                }

                foreach ($records as $record) {
                    $flatSummary .= "\n" . trim($record->getMessage());
                }

                return trim($flatSummary);
            } else {
                return $addSummaryInformation ? array_merge($this->getSummaryInformation(), $records) : $records;
            }
        }

        return null;
    }

    /**
     * @param string $section
     * @param string $field
     * @param mixed  $defaultValue
     * @param bool   $isFlag
     * @param bool   $forceTaskConfigValue
     *
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getConfigValue(
        string $section,
        string $field,
        $defaultValue = null,
        bool $isFlag = false,
        bool $forceTaskConfigValue = false)
    {
        return $this->helper->getTaskConfigValue($this->taskName, $section, $field, $defaultValue, $isFlag,
            $forceTaskConfigValue);
    }

    /**
     * @return Record[]
     */
    protected function getSummaryInformation(): array
    {
        return [
            new Record(LogLevel::INFO, sprintf(__('Task Name: %s'), $this->taskName)),
            new Record(LogLevel::INFO, sprintf(__('Task Id: %s'), $this->taskId))
        ];
    }

    /**
     * @param string $field
     * @param mixed  $defaultValue
     * @param bool   $isFlag
     * @param bool   $forceTaskConfigValue
     *
     * @return mixed
     * @throws NoSuchEntityException
     */
    protected function getTaskSetting(
        string $field,
        $defaultValue = null,
        bool $isFlag = false,
        bool $forceTaskConfigValue = true)
    {
        return $this->getConfigValue('settings', $field, $defaultValue, $isFlag, $forceTaskConfigValue);
    }

    /**
     * @param bool $test
     *
     * @return void
     */
    public function setTestMode(bool $test = true)
    {
        $this->test = $test;
    }

    /**
     * Returns the current value of the send mail option.
     *
     * @param string $type
     *
     * @return bool <code>true</code> if a mail should be send,
     *                  <code>false</code> otherwise.
     */
    protected function isProhibitSummarySending(string $type): bool
    {
        return array_key_exists($type, $this->prohibitSummarySending) ? $this->prohibitSummarySending[ $type ] :
            (array_key_exists(static::SUMMARY_TYPE_ALL, $this->prohibitSummarySending) &&
                $this->prohibitSummarySending[ static::SUMMARY_TYPE_ALL ]);
    }

    /**
     * @param string $type
     * @param bool   $prohibitSummarySending
     */
    public function setProhibitSummarySending(
        string $type = self::SUMMARY_TYPE_ALL,
        bool $prohibitSummarySending = true)
    {
        $this->prohibitSummarySending[ $type ] = $prohibitSummarySending;
    }

    /**
     * @return string
     */
    public function getTaskName(): string
    {
        return $this->taskName;
    }

    /**
     * @return string
     */
    public function getTaskId(): string
    {
        return $this->taskId;
    }

    /**
     * @return string
     */
    public function getStoreCode(): string
    {
        return $this->storeCode;
    }

    /**
     * @param bool $allowAdminStore
     */
    public function setAllowAdminStore(bool $allowAdminStore)
    {
        $this->allowAdminStore = $allowAdminStore;
    }

    /**
     * @return float
     */
    protected function getCurrentMemoryUsage(): float
    {
        if (is_callable('shell_exec') && false === stripos(ini_get('disable_functions'), 'shell_exec')) {
            $memoryUsages =
                shell_exec(sprintf("cat /proc/%d/status 2>/dev/null | grep -E '^(VmRSS|VmSwap)' | awk '{print $2}' | xargs",
                    getmypid()));

            if (preg_match_all('/[0-9]+/', $memoryUsages, $matches)) {
                if (array_key_exists(0, $matches)) {
                    $memories = array_map('intval', $matches[ 0 ]);

                    return round(array_sum($memories) / 1024);
                }
            }
        }

        return round(memory_get_peak_usage() / (1024 * 1024));
    }

    /**
     * @param Base $task
     */
    public function addSummaryFromTask(Base $task)
    {
        $this->addSummaryTypeFromTask($task, static::SUMMARY_TYPE_ALL);
        $this->addSummaryTypeFromTask($task, static::SUMMARY_TYPE_SUCCESS);
        $this->addSummaryTypeFromTask($task, static::SUMMARY_TYPE_ERROR);
    }

    /**
     * @param Base   $task
     * @param string $type
     */
    protected function addSummaryTypeFromTask(Base $task, string $type)
    {
        /** @var Record[] $taskSummaryRecords */
        $taskSummaryRecords = $task->getSummary($type, false);

        if ($taskSummaryRecords) {
            /** @var AbstractSummary $summary */
            $summary = $this->registryHelper->registry(sprintf('task_summary_%s', $type));

            if ($summary) {
                foreach ($taskSummaryRecords as $taskSummaryRecord) {
                    $summary->addRecordToTaskHandler($taskSummaryRecord);
                }
            }
        }
    }
}
