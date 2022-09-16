<?php /** @noinspection PhpDeprecationInspection */

namespace Tofex\Task\Block\Adminhtml\Run;

use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Grid\Column;
use Magento\Backend\Block\Widget\Grid\Column\Extended;
use Magento\Backend\Helper\Data;
use Magento\Eav\Model\Config;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Validator\UniversalFactory;
use Tofex\Core\Helper\Database;
use Tofex\Core\Helper\Registry;
use Tofex\Help\Arrays;
use Tofex\Help\Variables;
use Tofex\Task\Model\Config\Source\TaskName;
use Tofex\Task\Model\Run;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Grid
    extends \Tofex\BackendWidget\Block\Grid
{
    /** @var TaskName */
    protected $taskName;

    /**
     * @param Context                          $context
     * @param Data                             $backendHelper
     * @param Database                         $databaseHelper
     * @param Arrays                           $arrayHelper
     * @param Variables                        $variableHelper
     * @param Registry                         $registryHelper
     * @param \Tofex\BackendWidget\Helper\Grid $gridHelper
     * @param UniversalFactory                 $universalFactory
     * @param Config                           $eavConfig
     * @param TaskName                         $taskName
     * @param array                            $data
     */
    public function __construct(
        Context $context,
        Data $backendHelper,
        Database $databaseHelper,
        Arrays $arrayHelper,
        Variables $variableHelper,
        Registry $registryHelper,
        \Tofex\BackendWidget\Helper\Grid $gridHelper,
        UniversalFactory $universalFactory,
        Config $eavConfig,
        TaskName $taskName,
        array $data = [])
    {
        parent::__construct($context, $backendHelper, $databaseHelper, $arrayHelper, $variableHelper, $registryHelper,
            $gridHelper, $universalFactory, $eavConfig, $data);

        $this->taskName = $taskName;
    }

    /**
     * @return void
     * @throws FileSystemException
     */
    public function _construct()
    {
        parent::_construct();

        $this->setDefaultSort('start_at');
        $this->setDefaultDir('DESC');
    }

    /**
     * @param AbstractDb $collection
     *
     * @return void
     */
    protected function prepareCollection(AbstractDb $collection)
    {
        $collection->addExpressionFieldToSelect('status', 'IF({{0}} IS NULL,1,IF({{1}} IS NOT NULL OR {{2}} > 0,2,3))',
            ['finish_at', 'finish_at', 'max_memory_usage']);
        $collection->addExpressionFieldToSelect('duration', 'TIMESTAMPDIFF(SECOND, {{0}}, {{1}})',
            ['start_at', 'finish_at']);
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function prepareFields()
    {
        $this->addTextColumn('store_code', __('Store Code'));
        $this->addOptionsColumn('task_name', __('Task Name'), $this->taskName->toArray());
        $this->addTextColumn('task_id', __('Task Id'));
        $this->addTextColumn('process_id', __('Process Id'));
        $this->addOptionsColumnWithFilterConditionAndFrame('status', __('Status'), [
            1 => __('Running'),
            2 => __('Finished'),
            3 => __('Broken')
        ], [$this, 'filterStatus'], [$this, 'decorateStatus']);
        $this->addYesNoColumn('success', __('Success'));
        $this->addYesNoColumn('empty_run', __('Empty Run'));
        $this->addYesNoColumn('test', __('Test'));
        $this->addDatetimeColumn('start_at', __('Start Date'));
        $this->addDatetimeColumn('finish_at', __('Finish Date'));
        $this->addNumberColumnWithFilterCondition('duration', __('Duration'), [$this, 'filterDuration']);
        $this->addNumberColumn('max_memory_usage', __('Memory'));
    }

    /**
     * @param AbstractCollection $collection
     * @param Column             $column
     *
     * @return void
     * @noinspection PhpDeprecationInspection
     */
    protected function filterStatus(AbstractCollection $collection, Column $column)
    {
        if ($this->getCollection()) {
            $field = $column->getData('filter_index') ? $column->getData('filter_index') : $column->getData('index');

            $filter = $column->getFilter();

            $condition = $filter->getCondition();

            $preparedCondition = $collection->getConnection()->prepareSqlCondition($field, $condition);

            $collection->getSelect()->having($preparedCondition);
        }
    }

    /**
     * @param AbstractCollection $collection
     * @param Column             $column
     *
     * @return void
     * @noinspection PhpDeprecationInspection
     */
    protected function filterDuration(AbstractCollection $collection, Column $column)
    {
        if ($this->getCollection()) {
            $field = $column->getData('filter_index') ? $column->getData('filter_index') : $column->getData('index');

            $filter = $column->getFilter();

            $condition = $filter->getCondition();

            $preparedCondition = $collection->getConnection()->prepareSqlCondition($field, $condition);

            $collection->getSelect()->having($preparedCondition);
        }
    }

    /**
     * Decorate status column values
     *
     * @param string   $value
     * @param Run      $row
     * @param Extended $column
     * @param bool     $isExport
     *
     * @return string
     */
    public function decorateStatus(
        string $value,
        Run $row,
        /** @noinspection PhpUnusedParameterInspection */ Extended $column,
        /** @noinspection PhpUnusedParameterInspection */ bool $isExport): string
    {
        $class = '';

        switch ($row->getData('status')) {
            case 1:
                $class = 'task-run-status-running';
                break;
            case 2:
                $class = 'task-run-status-finished';
                break;
            case 3:
                $class = 'task-run-status-broken';
                break;
        }

        return '<span class="' . $class . '"><span>' . $value . '</span></span>';
    }
}
