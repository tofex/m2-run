<?php

namespace Tofex\Task\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Data
    extends AbstractHelper
{
    /** @var StoreManagerInterface */
    protected $storeManager;

    /**
     * @param Context               $context
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(Context $context, StoreManagerInterface $storeManager)
    {
        parent::__construct($context);

        $this->storeManager = $storeManager;
    }

    /**
     * @param string $taskName
     * @param string $section
     * @param string $field
     * @param mixed  $defaultValue
     * @param bool   $isFlag
     * @param bool   $forceTaskConfigValue
     *
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getTaskConfigValue(
        string $taskName,
        string $section,
        string $field,
        $defaultValue = null,
        bool $isFlag = false,
        bool $forceTaskConfigValue = false)
    {
        $storeId = $this->storeManager->getStore()->getId();
        $value = null;

        $overwrite = ! empty($taskName) &&
            $this->scopeConfig->isSetFlag('task_' . $taskName . '/' . $section . '/overwrite_task_general', 'store',
                $storeId);

        $forceTaskConfigValue =
            $forceTaskConfigValue || ! in_array($section, ['general', 'logging', 'summary_success', 'summary_error']);

        if ($overwrite || $forceTaskConfigValue) {
            $value =
                $this->scopeConfig->getValue('task_' . $taskName . '/' . $section . '/' . $field, 'store', $storeId);

            if ($isFlag === true && ! is_null($value)) {
                $value = $this->scopeConfig->isSetFlag('task_' . $taskName . '/' . $section . '/' . $field, 'store',
                    $storeId);
            }
        }

        if (is_null($value)) {
            $value = $this->scopeConfig->getValue('task_general' . '/' . $section . '/' . $field, 'store', $storeId);

            if ($isFlag === true && ! is_null($value)) {
                $value =
                    $this->scopeConfig->isSetFlag('task_general' . '/' . $section . '/' . $field, 'store', $storeId);
            }
        }

        if (is_null($value)) {
            $value = $defaultValue;
        }

        return $value;
    }

    /**
     * @param string $path
     *
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getStoreConfig(string $path)
    {
        $storeId = $this->storeManager->getStore()->getId();

        return $this->scopeConfig->getValue($path, 'store', $storeId);
    }
}
