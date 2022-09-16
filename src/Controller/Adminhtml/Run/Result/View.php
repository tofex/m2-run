<?php

namespace Tofex\Task\Controller\Adminhtml\Run\Result;

use Exception;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Result\Page;
use Tofex\BackendWidget\Controller\Backend\Object\Edit;
use Tofex\Task\Traits\Run;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class View
    extends Edit
{
    use Run;

    /**
     * @return string
     */
    protected function getObjectNotFoundMessage(): string
    {
        return __('Could not find run!');
    }

    /**
     * @return Page|void
     * @throws Exception
     */
    public function execute()
    {
        $object = $this->initObject();

        if ( ! $object) {
            $this->_redirect($this->getIndexUrlRoute(), $this->getIndexUrlParams());

            return;
        }

        if ($object->getId() && ! $this->allowEdit() && ! $this->allowView()) {
            $this->_redirect($this->getIndexUrlRoute(), $this->getIndexUrlParams());

            return;
        }

        $this->initAction();

        $blockClass = \Tofex\Task\Block\Adminhtml\Run\View::class;

        /** @var AbstractBlock $block */
        $block = $this->_view->getLayout()->createBlock($blockClass, '', ['data' => ['run_id' => $object->getId()]]);

        $this->_addContent($block);

        $this->finishAction(__('View Log'));

        return $this->_view->getPage();
    }
}
