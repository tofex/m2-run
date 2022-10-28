<?php /** @noinspection PhpDeprecationInspection */

namespace Tofex\Task\Cron\Execution;

use Exception;
use Magento\Cron\Model\ConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\Config\ValueFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Psr\Log\LoggerInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class Base
    extends Value
{
    /** @var LoggerInterface */
    protected $logging;

    /** @var ValueFactory */
    protected $configValueFactory;

    /** @var ConfigInterface */
    protected $configInterface;

    /**
     * @param Context               $context
     * @param Registry              $registry
     * @param ScopeConfigInterface  $config
     * @param TypeListInterface     $cacheTypeList
     * @param ValueFactory          $configValueFactory
     * @param ConfigInterface       $configInterface
     * @param AbstractResource|null $resource
     * @param AbstractDb|null       $resourceCollection
     * @param array                 $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ValueFactory $configValueFactory,
        ConfigInterface $configInterface,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = [])
    {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);

        $this->logging = $context->getLogger();
        $this->configValueFactory = $configValueFactory;
        $this->configInterface = $configInterface;
    }

    /**
     * Add availability call after load as public
     *
     * @return Base
     */
    public function _afterLoad(): Base
    {
        parent::_afterLoad();

        $path = sprintf('crontab/default/jobs/task_%s/schedule/cron_expr', $this->getTaskName());

        /** @var Value $config */
        $config = $this->configValueFactory->create();

        /** @noinspection PhpDeprecationInspection */
        $config->load($path, 'path');

        if ($config->getId()) {
            $value = $config->getValue();
        } else {
            $tasksData = $this->configInterface->getJobs();

            $value = null;

            if (array_key_exists('default', $tasksData)) {
                $defaultTaskData = $tasksData[ 'default' ];
                if (array_key_exists(sprintf('task_%s', $this->getTaskName()), $defaultTaskData)) {
                    $taskData = $defaultTaskData[ sprintf('task_%s', $this->getTaskName()) ];
                    if (array_key_exists('schedule', $taskData)) {
                        $value = $taskData[ 'schedule' ];
                    }
                }
            }
        }

        $this->setValue($value);

        return $this;
    }

    /**
     * Save object data
     *
     * @return Base
     */
    public function save(): Base
    {
        $path = sprintf('crontab/default/jobs/task_%s/schedule/cron_expr', $this->getTaskName());

        /** @var Value $config */
        $config = $this->configValueFactory->create();

        /** @noinspection PhpDeprecationInspection */
        $config->load($path, 'path');

        $config->setPath($path);
        $config->setScope('default');
        $config->setScopeId(0);
        $config->setValue($this->getValue());

        try {
            /** @noinspection PhpDeprecationInspection */
            $config->save();
        } catch (Exception $exception) {
            $this->logging->error($exception);
        }

        return $this;
    }

    /**
     * Returns the name of the task to initialize
     *
     * @return string
     */
    abstract protected function getTaskName(): string;
}
