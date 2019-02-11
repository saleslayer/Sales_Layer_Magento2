<?php
namespace Saleslayer\Synccatalog\Block\Adminhtml\Synccatalog\Edit\Tab;

// use \Magento\Catalog\Model\Category as categoryModel;

/**
 * Synccatalog page edit form Parameters tab
 */
class Categories extends \Magento\Backend\Block\Widget\Form\Generic implements \Magento\Backend\Block\Widget\Tab\TabInterface
{

    /**
     * @var \Magento\Catalog\Model\Category
     */
    protected $categoryModel;
    /**
     * @var \Magento\Eav\Model\Entity\Attribute\Source\Boolean ;
     */
    protected $booleanSource;
    /**
     * @var \Magento\Catalog\Model\Category\Attribute\Source\Layout ;
     */
    protected $layoutSource;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Data\FormFactory $formFactory
     * @param \Magento\Catalog\Model\Category $categoryModel
     * @param \Magento\Eav\Model\Entity\Attribute\Source\Boolean $booleanSource
     * @param \Magento\Catalog\Model\Category\Attribute\Source\Layout $layoutSource
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        \Magento\Catalog\Model\Category $categoryModel,
        \Magento\Eav\Model\Entity\Attribute\Source\Boolean $booleanSource,
        \Magento\Catalog\Model\Category\Attribute\Source\Layout $layoutSource,
        array $data = []
    ) {
        parent::__construct($context, $registry, $formFactory, $data);

        $this->categoryModel = $categoryModel;
        $this->booleanSource = $booleanSource;
        $this->layoutSource = $layoutSource;
    }

    /**
     * Prepare form
     *
     * @return $this
     */
    protected function _prepareForm()
    {
        /* @var $model \Magento\Cms\Model\Page */
        $model = $this->_coreRegistry->registry('synccatalog');

        /** @var \Magento\Framework\Data\Form $form */
        $form = $this->_formFactory->create();

        $form->setHtmlIdPrefix('synccatalog_main_');

        $fieldset = $form->addFieldset('base_fieldset', ['legend' => __('Categories parameters')]);

        if ($model->getId()) {
            $fieldset->addField('id', 'hidden', ['name' => 'id']);
        }

        $modelData = $model->getData();

        if(empty($modelData)){
            $modelData['category_page_layout'] = '1column';
        }


        $root_categories = $this->categoryModel->getCategories(1);
        $default_categories = array();
        foreach ($root_categories as $root_category) {
            array_push($default_categories, array('label' => $root_category->getName(), 'value' => $root_category->getEntityId()));
        }

        $fieldset->addField(
            'default_cat_id',
            'select',
            [
                'name' => 'default_cat_id',
                'label' => __('Default category'),
                'title' => __('Default category'),
                'required' => false,
                'values' => $default_categories,
                'disabled' => false,
                'class' => 'conn_field'
            ]
        );

        $fieldset->addField(
            'category_is_anchor',
            'select',
            [
                'type' => 'int',
                'name' => 'category_is_anchor',
                'label' => 'Is Anchor',
                'title' => 'Is Anchor',
                'required' => false,
                'values' => array(array('label'=>_('Yes'),'value'=>$this->booleanSource::VALUE_YES),array('label'=>_('No'),'value'=>$this->booleanSource::VALUE_NO)),
                'disabled' => false,
                'class' => 'conn_field'
            ]
        );

        $fieldset->addField(
            'category_page_layout',
            'select',
            [
                'name' => 'category_page_layout',
                'label' => 'Layout',
                'title' => 'Layout',
                'required' => false,
                'values' => $this->layoutSource->getAllOptions(),
                'disabled' => false,
                'class' => 'conn_field'
            ]
        );

        $this->_eventManager->dispatch('adminhtml_synccatalog_edit_tab_categories_prepare_form', ['form' => $form]);
        
        $form->setValues($modelData);
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Prepare label for tab
     *
     * @return string
     */
    public function getTabLabel()
    {
        return __('Category parameters');
    }

    /**
     * Prepare title for tab
     *
     * @return string
     */
    public function getTabTitle()
    {
        return __('Category parameters');
    }

    /**
     * {@inheritdoc}
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isHidden()
    {
        return false;
    }

}
