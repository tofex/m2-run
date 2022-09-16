<?php

namespace Tofex\Task\Block\Adminhtml\Run;

use Exception;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\ButtonFactory;
use Tofex\Core\Helper\Files;
use Tofex\Task\Model\Run;
use Tofex\Task\Model\RunFactory;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class View
    extends Template
{
    /** @var Files */
    protected $fileHelper;

    /** @var RunFactory $runResourceFactory */
    protected $runFactory;

    /** @var \Tofex\Task\Model\ResourceModel\RunFactory $runResourceFactory */
    protected $runResourceFactory;

    /** @var ButtonFactory */
    protected $buttonFactory;

    /** @var Run */
    private $run;

    /** @var string */
    private $logFileName;

    /** @var string */
    private $errorFileName;

    /**
     * @param Files                                      $fileHelper
     * @param Context                                    $context
     * @param RunFactory                                 $runFactory
     * @param \Tofex\Task\Model\ResourceModel\RunFactory $runResourceFactory
     * @param ButtonFactory                              $buttonFactory
     * @param array                                      $data
     */
    public function __construct(
        Files $fileHelper,
        Context $context,
        RunFactory $runFactory,
        \Tofex\Task\Model\ResourceModel\RunFactory $runResourceFactory,
        ButtonFactory $buttonFactory,
        array $data = [])
    {
        parent::__construct($context, $data);

        $this->fileHelper = $fileHelper;

        $this->runFactory = $runFactory;
        $this->runResourceFactory = $runResourceFactory;
        $this->buttonFactory = $buttonFactory;
    }

    /**
     * Internal constructor, that is called from real constructor
     *
     * @return void
     */
    protected function _construct()
    {
        $this->setData('template', 'Tofex_Task::tofex/task/run.phtml');

        parent::_construct();
    }

    /**
     * @return Run
     */
    protected function getRun(): Run
    {
        if ($this->run === null) {
            $this->run = $this->runFactory->create();

            $runId = $this->getData('run_id');

            if ( ! empty($runId)) {
                $this->runResourceFactory->create()->load($this->run, $runId);
            }
        }

        return $this->run;
    }

    /**
     * @return string
     */
    public function getLogFileName(): string
    {
        if ($this->logFileName === null) {
            $run = $this->getRun();

            if ($run->getId()) {
                try {
                    $this->logFileName =
                        $this->fileHelper->determineFilePath(sprintf('var/log/task/%s/%s/%s.log', $run->getTaskName(),
                            $run->getStoreCode(), $run->getTaskId()));
                } catch (Exception $exception) {
                }
            }
        }

        return $this->logFileName;
    }

    /**
     * @return array
     */
    public function getContent(): array
    {
        $content = [];

        $logFileName = $this->getLogFileName();

        if (file_exists($logFileName)) {
            $fileContent = file_get_contents($logFileName);

            if (is_array($fileContent)) {
                $content = $fileContent;
            } else {
                if (is_string($fileContent)) {
                    $content = preg_split('/\n/', $fileContent);

                    $content = array_map('trim', $content);
                } else {
                    $content[] = 'Invalid file content!';
                }
            }
        } else {
            $content[] = sprintf('Log file does not exists at: %s', $logFileName);
        }

        return $content;
    }

    /**
     * @return string
     */
    public function getErrorFileName(): string
    {
        if ($this->errorFileName === null) {
            $run = $this->getRun();

            if ($run->getId()) {
                try {
                    $this->errorFileName =
                        $this->fileHelper->determineFilePath(sprintf('var/log/task/%s/%s/%s.err', $run->getTaskName(),
                            $run->getStoreCode(), $run->getTaskId()));
                } catch (Exception $exception) {
                }
            }
        }

        return $this->errorFileName;
    }

    /**
     * @return array
     */
    public function getErrorContent(): array
    {
        $content = [];

        $errorFileName = $this->getErrorFileName();

        if (file_exists($errorFileName)) {
            $fileContent = file_get_contents($errorFileName);

            if (is_array($fileContent)) {
                $content = $fileContent;
            } else {
                if (is_string($fileContent)) {
                    $content = preg_split('/\n/', $fileContent);

                    $content = array_map('trim', $content);
                } else {
                    $content[] = 'Invalid file content!';
                }
            }
        }

        return $content;
    }

    /**
     * @return string
     */
    public function getBackButtonHtml(): string
    {
        $backButton = $this->buttonFactory->create([
            'data' => [
                'id'      => 'back',
                'title'   => __('Back'),
                'label'   => __('Back'),
                'class'   => 'back',
                'onclick' => sprintf("window.location.href = '%s';", $this->getUrl('tofex_task/run_result/index')),
            ]
        ]);

        return $backButton->toHtml();
    }
}
