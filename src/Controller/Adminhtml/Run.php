<?php

namespace Tofex\Task\Controller\Adminhtml;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Block\Template;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Area;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\TranslateInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\App\EmulationFactory;
use Tofex\Core\Helper\Instances;
use Tofex\Core\Helper\Stores;
use Tofex\Task\Helper\Data;
use Tofex\Task\Model\Session;
use Tofex\Task\Task\Base;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class Run
    extends Action
{
    /** @var Stores */
    protected $storeHelper;

    /** @var Instances */
    protected $instanceHelper;

    /** @var Data */
    protected $taskHelper;

    /** @var Session */
    protected $taskSession;

    /** @var \Magento\Backend\Model\Auth\Session */
    protected $authSession;

    /** @var Emulation */
    protected $appEmulation;

    /** @var ResolverInterface */
    protected $localeResolver;

    /** @var TranslateInterface */
    protected $translate;

    /** @var Base */
    private $task;

    /**
     * @param Context                             $context
     * @param Stores                              $storeHelper
     * @param Instances                           $instanceHelper
     * @param Data                                $taskHelper
     * @param Session                             $taskSession
     * @param \Magento\Backend\Model\Auth\Session $authSession
     * @param EmulationFactory                    $appEmulationFactory
     * @param ResolverInterface                   $localeResolver
     * @param TranslateInterface                  $translate
     */
    public function __construct(
        Context $context,
        Stores $storeHelper,
        Instances $instanceHelper,
        Data $taskHelper,
        Session $taskSession,
        \Magento\Backend\Model\Auth\Session $authSession,
        EmulationFactory $appEmulationFactory,
        ResolverInterface $localeResolver,
        TranslateInterface $translate)
    {
        parent::__construct($context);

        $this->storeHelper = $storeHelper;
        $this->instanceHelper = $instanceHelper;
        $this->taskHelper = $taskHelper;

        $this->taskSession = $taskSession;
        $this->authSession = $authSession;
        $this->appEmulation = $appEmulationFactory->create();
        $this->localeResolver = $localeResolver;
        $this->translate = $translate;
    }

    /**
     * @return string
     */
    abstract protected function getTaskName(): string;

    /**
     * @return string
     */
    abstract protected function getClassName(): string;

    /**
     * @return Base
     * @throws Exception
     */
    public function getTask(): Base
    {
        if ($this->task === null) {
            $this->task = $this->instanceHelper->getInstance($this->getClassName());

            if ( ! ($this->task instanceof Base)) {
                throw new Exception(sprintf('Task must extend %s', Base::class));
            }
        }

        return $this->task;
    }

    /**
     * @return Redirect|void
     * @throws Exception
     */
    public function execute()
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $taskName = $this->getTaskName();

        $this->taskSession->setData('task_name', $taskName);

        if (empty($taskName)) {
            $this->taskSession->setData('task_error_reason', __('Please specify a task name!'));

            $resultRedirect->setPath('tofex_task/run/error');

            return $resultRedirect;
        }

        $isAllowed = $this->_authorization->isAllowed('Tofex_Task::tofex_task') &&
            $this->_authorization->isAllowed($this->getTaskResourceId());

        if ( ! $isAllowed) {
            $this->taskSession->setData('task_error_reason', __('No right to execute task!'));

            $resultRedirect->setPath('tofex_task/run/error');

            return $resultRedirect;
        }

        $task = $this->getTask();

        $testMode = $this->getRequest()->getParam('test', false);

        try {
            $storeCode = $this->getRequest()->getParam('store_code');

            if (empty($storeCode)) {
                $storeCode = 'admin';
            }

            $storeId = $this->storeHelper->getStore($storeCode)->getId();

            $this->appEmulation->startEnvironmentEmulation($storeId, Area::AREA_ADMINHTML);

            $locale = null;

            if ($this->getRequest()->getParam('backend_user')) {
                $backendUser = $this->authSession->getUser();

                $locale = $backendUser->getInterfaceLocale();
            }

            if ($this->getRequest()->getParam('locale')) {
                $locale = $this->getRequest()->getParam('locale');
            }

            if ($locale) {
                $this->localeResolver->setLocale($locale);
                $this->translate->setLocale($locale);
                $this->translate->loadData(Area::AREA_ADMINHTML);
            }

            $taskResult = $task->launchFromAdmin($storeCode, $taskName, $testMode !== false);

            $this->appEmulation->stopEnvironmentEmulation();

            $taskTitle = $this->taskHelper->getTaskConfigValue($taskName, 'data', 'title', null, false, true);

            $this->_view->loadLayout(['default', 'tofex_task_run_result']);

            /** @var Template|bool $resultBlock */
            $resultBlock = $this->_view->getLayout()->getBlock('task_result');

            if ($resultBlock === false) {
                throw new LocalizedException(__('Result block not found in layout'));
            }

            $resultBlock->setData('title', __($taskTitle));
            $resultBlock->setData('result', $taskResult);

            $this->_view->renderLayout();
        } catch (Exception $exception) {
            $this->taskSession->setData('task_error_reason', $exception->__toString());

            $resultRedirect->setPath('tofex_task/run/error');

            return $resultRedirect;
        }
    }

    /**
     * @return string
     */
    abstract protected function getTaskResourceId(): string;
}
