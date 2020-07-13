<?php
namespace Saleslayer\Synccatalog\Model;

/**
 * Synccatalog Model
 **/

use \Magento\Framework\Model\Context as context;
use \Magento\Framework\Registry as registry;
use \Magento\Framework\Model\ResourceModel\AbstractResource as resource;
use \Magento\Framework\Data\Collection\AbstractDb as resourceCollection;
use Saleslayer\Synccatalog\Model\SalesLayerConn as SalesLayerConn;
use Saleslayer\Synccatalog\Helper\Data as synccatalogDataHelper;
use Saleslayer\Synccatalog\Helper\Config as synccatalogConfigHelper;
use \Magento\Catalog\Model\Product\Gallery\Processor as galleryProcessor;
use \Magento\Framework\Filesystem\Io\File as fileIo;
use \Magento\Framework\Filesystem\DirectoryList  as directoryListFilesystem;
use \Magento\Catalog\Model\Category as categoryModel;
use \Magento\Catalog\Api\CategoryLinkManagementInterface as categoryLinkManagementInterface;
use \Magento\Catalog\Model\Product as productModel;
use \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as productConfigurableType;
use \Magento\Eav\Model\Entity\Attribute as attribute;
use \Magento\Eav\Model\Entity\Attribute\Set as attribute_set;
use \Magento\Catalog\Api\ProductAttributeManagementInterface as productAttributeManagementInterface;
use \Magento\Catalog\Api\Data\ProductLinkInterface as productLinkInterface;
use \Magento\Catalog\Api\ProductLinkRepositoryInterface as productLinkRepositoryInterface;
use \Magento\Store\Model\Store as storeModel;
use \Magento\Store\Model\System\Store as storeSystemModel;
use \Magento\Indexer\Model\Indexer as indexer;
use \Magento\Indexer\Model\Indexer\Collection as indexerCollection;
use \Magento\Framework\App\ResourceConnection as resourceConnection;
use \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection as collectionOption;
use \Magento\Cron\Model\Schedule as cronSchedule;
use \Magento\Framework\App\Config\ScopeConfigInterface as scopeConfigInterface;
use \Magento\Tax\Model\ClassModel as tax_class_model;

class Synccatalog extends \Magento\Framework\Model\AbstractModel
{
    
    protected $synccatalogDataHelper;
    protected $synccatalogConfigHelper;
    protected $galleryProcessor;
    protected $fileIo;
    protected $categoryModel;
    protected $categoryLinkManagementInterface;
    protected $productModel;
    protected $productConfigurableType;
    protected $attribute;
    protected $attribute_set;
    protected $productAttributeManagementInterface;
    protected $productLinkInterface;
    protected $productLinkRepositoryInterface;
    protected $storeModel;
    protected $storeSystemModel;
    protected $indexer;
    protected $indexerCollection;
    protected $resourceConnection;
    protected $collectionOption;
    protected $cronSchedule;
    protected $scopeConfigInterface;
    protected $tax_class_model;
    protected $salesLayerConn;
    protected $connection;
    protected $directoryListFilesystem;
    
    const sl_API_version    = '1.17';
    const sl_connector_type = 'CN_MAGNT';
    const sl_data           = 'Synccatalog_data';
    const sl_connectorid    = 'Synccatalog_connectorid';
    const sl_secretkey      = 'Synccatalog_secretkey';
    const sl_rememberme     = 'Synccatalog_rememberme';
    const sl_time           = 'Synccatalog_response_time';
    const sl_def_lang       = 'Synccatalog_response_default_language';
    const sl_lang           = 'Synccatalog_response_language';
    const sl_lang_used      = 'Synccatalog_response_languages_used';
    const sl_conn_schema    = 'Synccatalog_response_connector_schema';
    const sl_data_schema    = 'Synccatalog_response_data_schema';
    const sl_api_version    = 'Synccatalog_response_api_version';
    const sl_action         = 'Synccatalog_response_action';
    const sl_default_cat_id = 'Synccatalog_default_category_id';
    
    protected $sl_DEBBUG         = 0;
    protected $sl_time_ini_process = '';
    protected $manage_indexers = 1;
    protected $avoid_images_updates = 0;
    protected $sync_data_hour_from = 0;
    protected $sync_data_hour_until = 0;

    protected $comp_id;
    protected $default_category_id;
    protected $avoid_stock_update;
    protected $sl_language;
    protected $sl_data_schema;
    protected $processing_connector_id;

    protected $category_field_name                  = 'section_name';
    protected $category_field_url_key               = 'section_url_key';
    protected $category_field_description           = 'section_description';
    protected $category_field_image                 = 'section_image';
    protected $category_field_meta_title            = 'section_meta_title';
    protected $category_field_meta_keywords         = 'section_meta_keywords';
    protected $category_field_meta_description      = 'section_meta_description';
    protected $category_field_active                = 'section_active';
    protected $category_path_base                   = BP.'/pub/media/catalog/category/';
    protected $category_images_sizes                = array();
    protected $category_is_anchor                   = 0;
    protected $category_page_layout                 = '1column';

    protected $categories_collection                = array();
    protected $categories_collection_names          = array();
    protected $saleslayer_root_category_id          = '';

    protected $entity_type                          = '';
    protected $default_attribute_set_id;
    protected $attribute_set_collection             = array();
    protected $attributes_collection                = array();
    protected $products_collection                  = array();
    protected $products_collection_skus             = array();
    protected $products_collection_names            = array();
    protected $product_field_name                   = 'product_name';
    protected $product_field_description            = 'product_description';
    protected $product_field_description_short      = 'product_description_short';
    protected $product_field_price                  = 'product_price';
    protected $product_field_image                  = 'product_image';
    protected $product_field_sku                    = 'sku';
    protected $product_field_qty                    = 'qty';
    protected $product_field_meta_title             = 'product_meta_title';
    protected $product_field_meta_keywords          = 'product_meta_keywords';
    protected $product_field_meta_description       = 'product_meta_description';
    protected $product_field_length                 = 'product_length';
    protected $product_field_width                  = 'product_width';
    protected $product_field_height                 = 'product_height';
    protected $product_field_weight                 = 'product_weight';
    protected $product_field_status                 = 'product_status';
    protected $product_field_related_references     = 'related_products_references';
    protected $product_field_crosssell_references   = 'crosssell_products_references';
    protected $product_field_upsell_references      = 'upsell_products_references';
    protected $product_field_attribute_set_id       = 'attribute_set_id';
    protected $product_field_tax_class_id           = 'product_tax_class_id';
    protected $product_path_base                    = BP.'/pub/media/catalog/product/';
    protected $product_tmp_path_base                = BP.'/pub/media/tmp/catalog/product/';
    protected $product_images_sizes                 = array();
    protected $products_previous_categories;

    protected $image_extension                      = '';
    protected $small_image_extension                = '';
    protected $thumbnail_extension                  = '';
    protected $product_additional_fields            = array();
    protected $product_additional_fields_images     = array();
    protected $has_product_field_sku                = false;
    protected $has_product_field_qty                = false;

    protected $syncedProducts                       = 0;
    protected $format_images_sizes                  = array();
    protected $format_field_image                   = 'format_image';

    protected $all_store_view_ids                   = array();
    protected $store_view_ids                       = array();
    protected $website_ids                          = array();
    protected $format_configurable_attributes       = array();

    protected $category_enabled_attribute_is_global = false;
    protected $product_enabled_attribute_is_global  = false;

    protected $product_type_simple                  = 'simple';
    protected $product_type_grouped                 = 'grouped';
    protected $product_type_configurable            = 'configurable';
    protected $product_type_virtual                 = 'virtual';
    protected $product_type_downloadable            = 'downloadable';
    protected $status_enabled                       = 1;
    protected $status_disabled                      = 2;
    protected $visibility_both                      = 4;
    protected $visibility_not_visible               = 1;
    protected $category_entity                      = 'catalog_category';
    protected $product_entity                       = 'catalog_product';
    protected $scope_global                         = 1;
    protected $product_link_type_grouped            = '';
    protected $product_link_type_related            = '';
    protected $product_link_type_upsell             = '';
    protected $product_link_type_crosssell          = '';

    protected $category_model;
    protected $product_model;
    protected $format_model;

    protected $products_not_synced                  = array();
    protected $deleted_stored_categories_ids        = array();

    protected $media_field_names                    = array();

    protected $sql_to_insert                        = array();
    protected $sql_to_insert_limit                  = 1;

    protected $saleslayer_multiconn_table           = 'saleslayer_synccatalog_multiconn';
    protected $saleslayer_syncdata_table            = 'saleslayer_synccatalog_syncdata';
    protected $saleslayer_syncdata_flag_table       = 'saleslayer_synccatalog_syncdata_flag';
    protected $saleslayer_indexers_table            = 'saleslayer_synccatalog_indexers';
    protected $sl_multiconn_table_data;
 
    protected $catalog_category_product_table       = 'catalog_category_product';

    protected $sl_logs_path                         = BP.'/var/log/sl_logs/';
    protected $sl_logs_folder_checked               = false;

    protected $tax_class_collection_loaded          = false;
    protected $tax_class_collection                 = array();

    protected $config_manage_stock                  = '';
    protected $config_default_product_tax_class     = '';

    /**
     * Function __construct
     * @param context                             $context                             \Magento\Framework\Model\Context
     * @param registry                            $registry                            \Magento\Framework\Registry
     * @param SalesLayerConn                      $salesLayerConn                      Saleslayer\Synccatalog\Model\SalesLayerConn
     * @param synccatalogDataHelper               $synccatalogDataHelper               Saleslayer\Synccatalog\Helper\Data
     * @param galleryProcessor                    $galleryProcessor                    \Magento\Catalog\Model\Product\Gallery\Processor
     * @param fileIo                              $fileIo                              \Magento\Framework\Filesystem\Io\File
     * @param directoryListFilesystem             $directoryListFilesystem             \Magento\Framework\Filesystem\DirectoryList
     * @param categoryModel                       $categoryModel                       \Magento\Catalog\Model\Category
     * @param categoryLinkManagementInterface     $categoryLinkManagementInterface     \Magento\Catalog\Api\CategoryLinkManagementInterface
     * @param productModel                        $productModel                        \Magento\Catalog\Model\Product
     * @param productConfigurableType             $productConfigurableType             \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable
     * @param attribute                           $attribute                           \Magento\Eav\Model\Entity\Attribute
     * @param attribute_set                       $attribute_set                       \Magento\Eav\Model\Entity\Attribute\Set
     * @param productAttributeManagementInterface $productAttributeManagementInterface \Magento\Catalog\Api\ProductAttributeManagementInterface
     * @param productLinkInterface                $productLinkInterface                \Magento\Catalog\Api\Data\ProductLinkInterface
     * @param productLinkRepositoryInterface      $productLinkRepositoryInterface      \Magento\Catalog\Api\ProductLinkRepositoryInterface
     * @param storeModel                          $storeModel                          \Magento\Store\Model\Store
     * @param storeSystemModel                    $storeSystemModel                    \Magento\Store\Model\System\Store
     * @param indexer                             $indexer                             \Magento\Indexer\Model\Indexer
     * @param indexerCollection                   $indexerCollection                   \Magento\Indexer\Model\Indexer\Collection
     * @param resourceConnection                  $resourceConnection                  \Magento\Framework\App\ResourceConnection
     * @param collectionOption                    $collectionOption                    \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection
     * @param cronSchedule                        $cronSchedule                        \Magento\Cron\Model\Schedule
     * @param scopeConfigInterface                $scopeConfigInterface                \Magento\Framework\App\Config\ScopeConfigInterface
     * @param tax_class_model                     $tax_class_model                     \Magento\Tax\Model\ClassModel
     * @param resource|null                       $resource                            \Magento\Framework\Model\ResourceModel\AbstractResource
     * @param resourceCollection|null             $resourceCollection                  \Magento\Framework\Data\Collection\AbstractDb
     * @param array                               $data                                
     */
    public function __construct(
        context $context,
        registry $registry,
        SalesLayerConn $salesLayerConn,
        synccatalogDataHelper $synccatalogDataHelper,
        synccatalogConfigHelper $synccatalogConfigHelper,
        galleryProcessor $galleryProcessor,
        fileIo $fileIo,
        directoryListFilesystem  $directoryListFilesystem,
        categoryModel $categoryModel,
        categoryLinkManagementInterface $categoryLinkManagementInterface,
        productModel $productModel,
        productConfigurableType $productConfigurableType,
        attribute $attribute,
        attribute_set $attribute_set,
        productAttributeManagementInterface $productAttributeManagementInterface,
        productLinkInterface $productLinkInterface,
        productLinkRepositoryInterface $productLinkRepositoryInterface,
        storeModel $storeModel,
        storeSystemModel $storeSystemModel,
        indexer $indexer,
        indexerCollection $indexerCollection,
        resourceConnection $resourceConnection,
        collectionOption $collectionOption,
        cronSchedule $cronSchedule,
        scopeConfigInterface $scopeConfigInterface,
        tax_class_model $tax_class_model,
        resource $resource = null,
        resourceCollection $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
        $this->salesLayerConn                           = $salesLayerConn;
        $this->synccatalogDataHelper                    = $synccatalogDataHelper;
        $this->synccatalogConfigHelper                  = $synccatalogConfigHelper;
        $this->galleryProcessor                         = $galleryProcessor;
        $this->fileIo                                   = $fileIo;
        $this->directoryListFilesystem                  = $directoryListFilesystem;
        $this->categoryModel                            = $categoryModel;
        $this->categoryLinkManagementInterface          = $categoryLinkManagementInterface;
        $this->productModel                             = $productModel;
        $this->productConfigurableType                  = $productConfigurableType;
        $this->attribute                                = $attribute;
        $this->attribute_set                            = $attribute_set;
        $this->productAttributeManagementInterface      = $productAttributeManagementInterface;
        $this->productLinkInterface                     = $productLinkInterface;
        $this->productLinkRepositoryInterface           = $productLinkRepositoryInterface;
        $this->storeModel                               = $storeModel;
        $this->storeSystemModel                         = $storeSystemModel;
        $this->indexer                                  = $indexer;
        $this->indexerCollection                        = $indexerCollection;
        $this->resourceConnection                       = $resourceConnection;
        $this->collectionOption                         = $collectionOption;
        $this->cronSchedule                             = $cronSchedule;
        $this->scopeConfigInterface                     = $scopeConfigInterface;
        $this->tax_class_model                          = $tax_class_model;
        $this->connection                               = $this->resourceConnection->getConnection();
        $this->saleslayer_multiconn_table               = $this->resourceConnection->getTableName($this->saleslayer_multiconn_table);
        $this->saleslayer_syncdata_table                = $this->resourceConnection->getTableName($this->saleslayer_syncdata_table);
        $this->saleslayer_syncdata_flag_table           = $this->resourceConnection->getTableName($this->saleslayer_syncdata_flag_table);
        $this->catalog_category_product_table           = $this->resourceConnection->getTableName($this->catalog_category_product_table);
        $this->saleslayer_indexers_table                = $this->resourceConnection->getTableName($this->saleslayer_indexers_table);

    }

    /**
     * Function to initialize resource model
     *
     * @return void
     */
    protected function _construct(){

        $this->_init('Saleslayer\Synccatalog\Model\ResourceModel\Synccatalog');
        $this->sl_time_ini_process = microtime(1);
        
    }

    /**
     * Function to initialize config parameters
     *
     * @return void
     */
    public function loadConfigParameters(){

        $this->sl_DEBBUG = $this->synccatalogConfigHelper->getDebugerLevel();
        $this->sql_to_insert_limit = $this->synccatalogConfigHelper->getSqlToInsertLimit();
        $this->manage_indexers = $this->synccatalogConfigHelper->getManageIndexers();
        $this->avoid_images_updates = $this->synccatalogConfigHelper->getAvoidImagesUpdates();
        $this->sync_data_hour_from = $this->synccatalogConfigHelper->getSyncDataHourFrom();
        $this->sync_data_hour_until = $this->synccatalogConfigHelper->getSyncDataHourUntil();
        $this->config_manage_stock = $this->scopeConfigInterface->getValue('cataloginventory/item_options/manage_stock');
        $this->config_default_product_tax_class = $this->scopeConfigInterface->getValue('tax/classes/default_product_tax_class');

    }

    /**
     * Function to debbug into a Sales Layer log.
     * @param  string $msg      message to save
     * @param  string $type     type of message to save
     * @return void
     */
    public function debbug($msg, $type = ''){

        if (!$this->sl_logs_folder_checked){

            $this->sl_logs_path = $this->directoryListFilesystem->getPath('log').'/sl_logs/';

            if (!file_exists($this->sl_logs_path)) {
                
                mkdir($this->sl_logs_path, 0777, true);
            
            }

            $this->sl_logs_folder_checked = true;

        }
        
        if ($this->sl_DEBBUG > 0){

            $error_write = false;
            if (strpos($msg, '## Error.') !== false){
                $error_write = true;
                $error_file = $this->sl_logs_path.'_error_debbug_log_saleslayer_'.date('Y-m-d').'.dat';
            }

            switch ($type) {
                case 'timer':
                    $file = $this->sl_logs_path.'_debbug_log_saleslayer_timers_'.date('Y-m-d').'.dat';
                    break;

                case 'autosync':
                    $file = $this->sl_logs_path.'_debbug_log_saleslayer_auto_sync_'.date('Y-m-d').'.dat';
                    break;

                case 'syncdata':
                    $file = $this->sl_logs_path.'_debbug_log_saleslayer_sync_data_'.date('Y-m-d').'.dat';
                    break;

                case 'indexers':
                    $file = $this->sl_logs_path.'_debbug_log_saleslayer_indexers_'.date('Y-m-d').'.dat';
                    break;

                default:
                    $file = $this->sl_logs_path.'_debbug_log_saleslayer_'.date('Y-m-d').'.dat';
                    break;
            }

            $new_file = false;
            if (!file_exists($file)){ $new_file = true; }

            if ($this->sl_DEBBUG > 1){

                $mem = sprintf("%05.2f", (memory_get_usage(true)/1024)/1024);

                $pid = getmypid();

                $time_end_process = round(microtime(true) - $this->sl_time_ini_process);

                $srv = 'NonEx';

                if (function_exists('sys_getloadavg')) {
                    
                    $srv = sys_getloadavg();
                    
                    if (is_array($srv) && isset($srv[0])){

                        $srv = $srv[0];

                    }
                    
                }
               
                $msg = "pid:{$pid} - mem:{$mem} - time:{$time_end_process} - srv:{$srv} - $msg";
            
            }

            file_put_contents($file, "$msg\r\n", FILE_APPEND);

            if ($new_file){ chmod($file, 0777); }

            if ($error_write){

                $new_error_file = false;
                
                if (!file_exists($error_file)){ $new_error_file = true; }

                file_put_contents($error_file, "$msg\r\n", FILE_APPEND);

                if ($new_error_file){ chmod($error_file, 0777); }

            }

        }

    }

    /**
     * Function to update a connector's field value.
     * @param  string   $connector_id                   Sales Layer connector id
     * @param  string   $field_name                     connector field name
     * @return string   $field_value                    field value
     */
    public function get_conn_field($connector_id, $field_name) {

        if (is_null($connector_id) || $connector_id === '') {
        
            $this->debbug('## Error. Invalid Sales Layer Connector ID.');
            throw new \InvalidArgumentException('Invalid Sales Layer Connector ID.');
            
        }else{
        
            $config_record = $this->load($connector_id, 'connector_id');

            if (!$config_record) {
        
                $this->debbug('## Error. Sales Layer master data corrupted.');
                throw new \InvalidArgumentException('Sales Layer master data corrupted.');
        
            }

            $conn_data = $config_record->getData();

            $field_value = '';

            switch ($field_name) {
                case 'languages':
                    
                    $field_value = explode(',', $conn_data[$field_name]);
                    $field_value = reset($field_value);

                    if (!$field_value) { $field_value = $conn_data['default_language']; }

                    break;
                case 'last_update':

                    (isset($conn_data[$field_name]) && $conn_data[$field_name] != '0000-00-00 00:00:00') ? $field_value = $conn_data[$field_name] : $field_value = null;

                    break;
                case 'avoid_stock_update':

                    $field_value = $conn_data[$field_name];
                    if ($field_value != '1'){ $field_value = '0'; }

                    break;
                case 'category_is_anchor':
                    
                    $field_value = $conn_data[$field_name];
                    if ($field_value != '1'){ $fild_value = '0'; }

                    break;
                case 'default_cat_id':

                    $field_value = $conn_data['default_cat_id'];
                    
                    if (!empty($this->categories_collection)){

                        if (!isset($this->categories_collection[$field_value])){

                            //Search Sales Layer root category and update the connector.
                            if ($this->saleslayer_root_category_id != ''){
                                
                                $field_value = $this->saleslayer_root_category_id; 
                                $config_record->setDefaultCatId($field_value);
                                $config_record->save();

                            }

                        }

                    }
                    
                    if (is_null($field_value) || $field_value == ''){                

                        $this->debbug('## Error. Sales Layer master data corrupted. No default category.');
                        throw new \InvalidArgumentException('Sales Layer master data corrupted. No default category.');

                    }

                    break;
                default:

                    if (isset($conn_data[$field_name])){ $field_value = $conn_data[$field_name]; }
                    break;

            }

            if ($this->sl_DEBBUG > 1) $this->debbug('Connector field: '.$field_name.' - field_value: '.$field_value);
            return $field_value;
        
        }

    }

    /**
     * Function to update a connector's field value.
     * @param  string   $connector_id               Sales Layer connector id
     * @param  string   $field_name                 connector field name
     * @param  string   $field_value                connector field value
     * @return  boolean                             result of update
     */
    public function update_conn_field($connector_id, $field_name, $field_value) {

        if (in_array($field_name, array('id', 'connector_id', 'secret_key', 'comp_id', 'default_language', 'last_update', 'languages', 'updater_version', ''))){ return false; }

        if (in_array($field_name, array('store_view_ids', 'format_configurable_attributes')) && $field_value !== null){ 
            $field_value = json_encode($field_value); 
        }

        $config_record   = $this->load($connector_id, 'connector_id');
        $conn_data      = $config_record->getData();
        
        $boolean_fields = array('avoid_stock_update' => 1,'products_previous_categories' => 0, 'category_is_anchor' => 1);
        
        if (isset($boolean_fields[$field_name])){
            
            if (is_null($field_value) || $field_value != $boolean_fields[$field_name]){
            
                ($boolean_fields[$field_name] == 0) ? $field_value = 1 : $field_value = 0;

            }

        }
       
        $mandatory_fields = array('default_cat_id' => 0, 'auto_sync' => 0, 'category_is_anchor' => null); 

        if (isset($mandatory_fields[$field_name]) && (($field_name == 'auto_sync' && (is_null($field_value) || $field_value === '')) || ($field_name != 'auto_sync' && (is_null($field_value) || $field_value == '')))){
            
            $this->debbug('## Error. Updating connector: $connector_id field: $field_name field_value: $field_value - Empty value for mandatory field.');
            return false;

        }

        if ($conn_data[$field_name] != $field_value){

            try{

                $config_record->setData($field_name, $field_value);
                $config_record->save();
                if ($this->sl_DEBBUG > 1) $this->debbug('Connector field: '.$field_name.' updated to: '.$field_value);

            }catch(\Exception $e){
            
                $this->debbug('## Error. Updating connector: $connector_id field: $field_name to: $field_value - '.$e->getMessage());
                return false;

            }

        }

        return true;

    }

    /**
     * Function to load the connector's store view ids.
     * @param  string $connector_id             Sales Layer connector id
     * @return void
     */
    private function load_store_view_ids ($connector_id) {

        $store_view_ids = $this->get_conn_field($connector_id, 'store_view_ids');
        
        if (!is_null($store_view_ids)){

            $this->store_view_ids = json_decode($store_view_ids,1);
            $store_collection = $this->storeSystemModel->getStoreCollection();
            foreach ($store_collection as $store) {
            
                if (in_array(0, $this->store_view_ids) && !in_array($store['store_id'], $this->store_view_ids)){

                    $this->store_view_ids[] = $store['store_id'];

                }

            }
            
            asort($this->store_view_ids);

            $store_model = $this->storeModel;
            
            foreach ($this->store_view_ids as $store_view_id) {
                $store = $store_model->load($store_view_id);
                $website_id = $store->getWebsiteId();
                if (!in_array($website_id, $this->website_ids) && $website_id != 0){
                    array_push($this->website_ids, $website_id);
                }
            }

            if ($this->sl_DEBBUG > 1) $this->debbug("Configuration store view ids: ".print_r($this->store_view_ids,1));
            if ($this->sl_DEBBUG > 1) $this->debbug("Configuration website ids: ".print_r($this->website_ids,1));
        }
    }

    /**
     * Function to load all store view ids.
     * @return void
     */
    private function load_all_store_view_ids(){

        $this->all_store_view_ids = array(0);

        $store_collection = $this->storeSystemModel->getStoreCollection();
        foreach ($store_collection as $store) {
        
            $this->all_store_view_ids[] = $store['store_id'];

        }

    }

    /**
     * Function to get the connector's format configurable attributes.
     * @param  string $connector_id             Sales Layer connector id
     * @return void
     */
    private function load_format_configurable_attributes ($connector_id) {

        $format_configurable_attributes = $this->get_conn_field($connector_id, 'format_configurable_attributes');

        if (!is_null($format_configurable_attributes)){
        
            $this->format_configurable_attributes = json_decode($format_configurable_attributes,1);
            
            if ($this->sl_DEBBUG > 1) $this->debbug("Format configurable attributes ids: ".print_r($this->format_configurable_attributes,1));
        
        }

    }

    /**
     * Function to get the connector's products previous categories option.
     * @param  string $connector_id             Sales Layer connector id
     * @return void
     */
    private function load_products_previous_categories ($connector_id) {

        $products_previous_categories = $this->get_conn_field($connector_id, 'products_previous_categories');

        if (is_null($products_previous_categories)){

            $products_previous_categories = 0;
        
        }

        $this->products_previous_categories = $products_previous_categories;
        
        if ($this->sl_DEBBUG > 1) $this->debbug("Products previous categories option: ".print_r($this->products_previous_categories,1));

    }

    /**
     * Function to load Magento variables into local class variables.
     * @return void
     */
    private function load_magento_variables(){

        $this->product_type_simple          = \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE;
        $this->product_type_configurable    = \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE;
        $this->product_type_grouped         = \Magento\GroupedProduct\Model\Product\Type\Grouped::TYPE_CODE;
        $this->product_type_virtual         = \Magento\Catalog\Model\Product\Type::TYPE_VIRTUAL;
        $this->product_type_downloadable    = \Magento\Downloadable\Model\Product\Type::TYPE_DOWNLOADABLE;
        $this->status_enabled               = \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED;
        $this->status_disabled              = \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED;
        $this->visibility_both              = \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH;
        $this->visibility_not_visible       = \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE;
        $this->category_entity              = \Magento\Catalog\Model\Category::ENTITY;
        $this->product_entity               = \Magento\Catalog\Model\Product::ENTITY;
        $this->scope_global                 = \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL;
        $this->product_link_type_grouped    = 'associated';
        $this->product_link_type_related    = 'related';
        $this->product_link_type_upsell     = 'upsell';
        $this->product_link_type_crosssell  = 'crosssell';

    }

    /**
     * Function to login into Sales Layer with the connector credentials.
     * @param  string $connector_id             Sales Layer connector id
     * @param  string $secretKey                Sales Layer connector secret key
     * @return timestamp $get_response_time     response time from the connection
     */
    public function login_saleslayer ($connector_id, $secretKey) {

        $this->debbug('Process login...');

        $this->load_models();
        $this->load_categories_collection();
        $this->getSalesLayerRootCategory();

        $slconn = $this->connect_saleslayer($connector_id, $secretKey);
        $configRecord = $this->load($connector_id, 'connector_id');
        $data = $configRecord->getData();
        
        if (!isset($data['id']) || is_null($data['id'])){
            $this->createConn($connector_id, $secretKey);
        }

        $this->updateConn($connector_id, $slconn);

        $get_response_time = $slconn->get_response_time('timestamp');

        return $get_response_time;

    }

    /**
     * Function to create the connector in the Sales Layer table.
     * @param  string $connector_id             Sales Layer connector id
     * @param  string $secretKey                Sales Layer connector secret key
     * @return void
     */
    private function createConn($connector_id, $secretKey){

        $this->load_models();
        $this->load_categories_collection();
        $this->getSalesLayerRootCategory();
        $category_id = $this->saleslayer_root_category_id;
        if (is_null($category_id)){ $category_id = 1; }
        
        $this->addData(array('connector_id' => $connector_id, 'secret_key' => $secretKey, 'default_cat_id' => $category_id, 'store_view_ids' => 0, null));
        $this->save();
    }

    /**
     * Function to update the connector data in the Sales Layer table.
     * @param  string $connector_id             Sales Layer connector id
     * @param  array $slconn                    Sales Layer connector object
     * @param  timestamp $last_update           last update from the connector
     * @return void
     */
    private function updateConn($connector_id, $slconn, $last_update = null){

        if ($this->sl_DEBBUG > 1) $this->debbug("Updating connector...");
        if ($this->sl_DEBBUG > 1) $this->debbug("Last update...".$last_update);
        
        $configRecord = $this->load($connector_id, 'connector_id');
        
        if ($slconn->get_response_languages_used()) {

            $get_response_default_language = $slconn->get_response_default_language();
            $get_response_languages_used   = $slconn->get_response_languages_used();
            $get_response_languages_used   = implode(',', $get_response_languages_used);

            $configRecord->setDefault_language($get_response_default_language);
            $configRecord->setLanguages       ($get_response_languages_used);
        }
        
        $configRecord->setComp_id($slconn->get_response_company_ID());
        
        $get_response_api_version   = $slconn->get_response_api_version();
        
        $configRecord->setUpdater_version($get_response_api_version);

        if (!is_null($last_update)){ $configRecord->setLast_update($last_update); }

        $configRecord->save();

    }

    /**
     * Function to get the data schema from the connector.
     * @param  array $slconn                    Sales Layer connector object
     * @return array $schema                    schema data
     */
    private function get_data_schema ($slconn) {

        $info = $slconn->get_response_table_information();
        $schema = array();

        if (is_array($info) && !empty($info)){

            foreach ($info as $table => $data) {

                if (isset($data['table_joins'])) {

                    $schema[$table]['table_joins']=$data['table_joins'];
                }

                if (isset($data['fields'])) {

                    foreach ($data['fields'] as $field => $struc) {

                        if (isset($struc['has_multilingual']) and $struc['has_multilingual']) {

                            if (!isset($schema[$table][$field])) {

                                $schema[$table]['fields'][$struc['basename']] = array(

                                    'type'            =>$struc['type'],
                                    'has_multilingual'=>1,
                                    'multilingual_name' => $field
                                );

                                if ($struc['type']=='image') {

                                    $schema[$table]['fields'][$struc['basename']]['image_sizes']=$struc['image_sizes'];
                                }
                            }

                        } else {

                            $schema[$table]['fields'][$field]=$struc;
                        }
                    }
                }
            }

            if ($this->sl_DEBBUG > 2) $this->debbug('Schema: '.print_r($schema,1));
            
            return $schema;
        
        }else{

            return false;

        }


    }

    /**
     * Function to connect to Sales Layer with the connector credentials.
     * @param  string $connector_id             Sales Layer connector id
     * @param  string $secretKey                Sales Layer connector secret key
     * @return array $slconn                    Sales Layer connector object
     */
    private function connect_saleslayer ($connector_id, $secretKey) {

        $slconn = new SalesLayerConn ($connector_id, $secretKey);

        $slconn->set_API_version(self::sl_API_version);

        $last_date_update = $this->get_conn_field($connector_id, 'last_update');
        
        $this->debbug('Connecting with API... (last update: '.$last_date_update.')');

        if (preg_match('/^\d{4}-/', $last_date_update)) $last_date_update = strtotime($last_date_update);

        $slconn->set_group_multicategory(true);
        $slconn->get_info($last_date_update);

        if ($slconn->has_response_error()) {
            throw new \InvalidArgumentException($slconn->get_response_error_message());
        }

        if ($response_connector_schema = $slconn->get_response_connector_schema()) {

            $response_connector_type = $response_connector_schema['connector_type'];

            if ($response_connector_type != self::sl_connector_type) {
                throw new \InvalidArgumentException('Invalid Sales Layer connector type');
            }
        }

        return $slconn;
    } 

    /**
     * Function to store connector's data to synchronize.
     * @param  string $connector_id             Sales Layer connector id
     * @return array $arrayReturn               array with stored synchronization data
     */
    public function store_sync_data ($connector_id, $last_sync = null) {
        
        $this->loadConfigParameters();

        $items_processing = $this->connection->query(" SELECT count(*) as count FROM ".$this->saleslayer_syncdata_table)->fetch();

        if (isset($items_processing['count']) && $items_processing['count'] > 0){

           $this->debbug("There are still ".$items_processing['count']." items to process, wait until they have finished and synchronize again.");
           return "There are still ".$items_processing['count']." items to process, wait until they have finished and synchronize again.";

       }

       $indexers_processing = $this->connection->query(" SELECT count(*) as count FROM ".$this->saleslayer_indexers_table)->fetch();

       if (isset($indexers_processing['count']) && $indexers_processing['count'] > 0){

           $this->debbug("There are still ".$indexers_processing['count']." indexers to process, wait until they have finished and synchronize again.");
           return "There are still ".$indexers_processing['count']." indexers to process, wait until they have finished and synchronize again.";

       }
               
        $this->debbug("\r\n==== Store Sync Data INIT ====\r\n");

        if ($last_sync == null){ $last_sync = date('Y-m-d H:i:s'); }

        $config_record = $this->load($connector_id, 'connector_id');
        $config_record->setLastSync($last_sync);
        $config_record->save();

        $sync_params = $arrayReturn = array();
        
        $secretKey = $this->get_conn_field($connector_id, 'secret_key');

        $slconn = $this->connect_saleslayer($connector_id, $secretKey);

        $this->updateConn($connector_id, $slconn, $slconn->get_response_time());
        
        $sync_params['conn_params']['comp_id'] = $slconn->get_response_company_ID();
        $sync_params['conn_params']['connector_id'] = $connector_id;
        
        $get_response_table_data  = $slconn->get_response_table_data();
        $get_response_time        = $slconn->get_response_time('timestamp');
        
        if ($slconn->get_response_languages_used()){

            $get_response_default_language = $slconn->get_response_default_language();
            $get_response_languages_used   = $slconn->get_response_languages_used();
            $get_response_language         = (is_array($get_response_languages_used) ? reset($get_response_languages_used) : $get_response_default_language);
        
            $this->sl_language = $get_response_language;
            
        }

        $get_data_schema = $this->get_data_schema($slconn);
        
        if (!$get_data_schema){

            $this->debbug("\r\n==== Store Sync Data END ====\r\n");

            return "The information is being prepared by the API. Please try again in a few minutes.";

        }else{

            $this->sl_data_schema = json_encode($get_data_schema);

        }

        unset($get_data_schema);

        $this->debbug('Update new date: '.$get_response_time.' ('.date('Y-m-d H:i:s', $get_response_time).')');

        if ($get_response_table_data) {

            $this->load_store_view_ids($connector_id);
            $sync_params['conn_params']['store_view_ids'] = $this->store_view_ids;
            $sync_params['conn_params']['website_ids'] = $this->website_ids;
            $this->load_models();
            $this->load_categories_collection();
            $this->default_category_id = $this->get_conn_field($connector_id, 'default_cat_id');
            $this->load_products_previous_categories($connector_id);
            $this->avoid_stock_update = $this->get_conn_field($connector_id, 'avoid_stock_update');
            $this->load_format_configurable_attributes($connector_id);
            $this->load_media_field_names();
            $this->category_is_anchor = $this->get_conn_field($connector_id, 'category_is_anchor');
            $this->category_page_layout = $this->get_conn_field($connector_id, 'category_page_layout');

            if (!$this->sl_language) { $this->sl_language = $this->get_conn_field($connector_id, 'languages'); }

            $time_ini_all_store_process = microtime(1);
            
            $products_ids = array();

            foreach ($get_response_table_data as $nombre_tabla => $data_tabla) {

                if (count($data_tabla['deleted']) > 0) {

                    $deleted_data = $data_tabla['deleted'];

                    if (count($deleted_data) > 0) {

                        $sync_type = 'delete';
                        $time_ini_store_items_delete = microtime(1);

                        switch ($nombre_tabla) {
                            case 'catalogue':
                                
                                $item_type = 'category';

                                $this->debbug('Total count of delete categories to store: '.count($deleted_data));
                                if ($this->sl_DEBBUG > 1) $this->debbug('Delete categories data to store: '.print_r($deleted_data,1));
                                $arrayReturn['categories_to_delete'] = count($deleted_data);

                                foreach ($deleted_data as $delete_category_id) {
                                    
                                    $item_data['sl_id'] = $delete_category_id;
                                    $this->sql_to_insert[] = "('".$sync_type."', '".$item_type."', '".addslashes(json_encode($item_data))."', '".addslashes(json_encode($sync_params))."')";
                                    $this->insert_syncdata_sql();

                                }

                                break;
                            case 'products':

                                $item_type = 'product';

                                $this->debbug('Total count of delete products to store: '.count($deleted_data));
                                if ($this->sl_DEBBUG > 1) $this->debbug('Delete products data to store: '.print_r($deleted_data,1));
                                $arrayReturn['products_to_delete'] = count($deleted_data);

                                foreach ($deleted_data as $delete_product_id) {
                                    
                                    $item_data['sl_id'] = $delete_product_id;
                                    $this->sql_to_insert[] = "('".$sync_type."', '".$item_type."', '".addslashes(json_encode($item_data))."', '".addslashes(json_encode($sync_params))."')";
                                    $this->insert_syncdata_sql();

                                }

                                break;
                            case 'product_formats':

                                $item_type = 'product_format';
                                
                                $this->debbug('Total count of delete product formats to store: '.count($deleted_data));
                                if ($this->sl_DEBBUG > 1) $this->debbug('Delete product formats data to store: '.print_r($deleted_data,1));
                                $arrayReturn['product_formats_to_delete'] = count($deleted_data);

                                foreach ($deleted_data as $delete_product_format_id) {
                                     
                                    $item_data['sl_id'] = $delete_product_format_id;
                                    $this->sql_to_insert[] = "('".$sync_type."', '".$item_type."', '".addslashes(json_encode($item_data))."', '".addslashes(json_encode($sync_params))."')";
                                    $this->insert_syncdata_sql();

                                }

                                break;
                            default:

                                $this->debbug('## Error. Deleting, table '.$nombre_tabla.' not recognized.');

                                break;
                        }

                        $this->debbug('#### time_store_items_delete - '.$item_type.': '.(microtime(1) - $time_ini_store_items_delete).' seconds.');

                        unset($get_response_table_data[$nombre_tabla]['deleted']);

                    }

                    $this->insert_syncdata_sql(true);


                }


                $modified_data = $data_tabla['modified'];
                
                $sql_updates_to_insert = array();
                $sync_type = 'update';
                $time_ini_store_items_update = microtime(1);

                switch ($nombre_tabla) {
                    case 'catalogue':

                        $item_type = 'category';

                        $this->debbug('Total count of sync categories to store: '.count($modified_data));
                        $this->debbug('Sync categories data to store: '.print_r($modified_data,1));
                        $arrayReturn['categories_to_sync'] = count($modified_data);

                        if (count($modified_data) > 0){

                            $category_data_to_store = $this->prepare_category_data_to_store($modified_data);

                            unset($modified_data);

                            if (isset($category_data_to_store['category_data']) && !empty($category_data_to_store['category_data'])){

                                $categories_to_sync = $category_data_to_store['category_data'];
                                unset($category_data_to_store['category_data']);

                                $category_params = array_merge($category_data_to_store, $sync_params);

                                foreach ($categories_to_sync as $KCTS => $category_to_sync) {
                                    
                                    $item_data_to_insert = html_entity_decode(json_encode($category_to_sync));
                                    $sync_params_to_insert = json_encode($category_params);

                                    $this->sql_to_insert[] = "('".$sync_type."', '".$item_type."', '".addslashes($item_data_to_insert)."', '".addslashes($sync_params_to_insert)."')";
                                    $this->insert_syncdata_sql();
                                    
                                    unset($categories_to_sync[$KCTS]);

                                }
                                
                            }

                            unset($get_response_table_data[$nombre_tabla]['modified']);

                        }


                        break;
                    case 'products':

                        $item_type = 'product';

                        $this->debbug('Total count of sync products to store: '.count($modified_data));
                        $this->debbug('Sync products data to store: '.print_r($modified_data,1));
                        $arrayReturn['products_to_sync'] = count($modified_data);

                        if (count($modified_data) > 0){

                            $product_data_to_store = $this->prepare_product_data_to_store($modified_data);

                            unset($modified_data);

                            if (isset($product_data_to_store['product_data']) && !empty($product_data_to_store['product_data'])){

                                $arrayReturn['products_to_sync'] = count($product_data_to_store['product_data']);

                                $products_to_sync = $product_data_to_store['product_data'];
                                unset($product_data_to_store['product_data']);
                                $product_params = array_merge($product_data_to_store, $sync_params);

                                foreach ($products_to_sync as $KPTS => $product_to_sync) {

                                    $item_data_to_insert = html_entity_decode(json_encode($product_to_sync));
                                    $sync_params_to_insert = json_encode($product_params);

                                    $this->sql_to_insert[] = "('".$sync_type."', '".$item_type."', '".addslashes($item_data_to_insert)."', '".addslashes($sync_params_to_insert)."')";
                                    $this->insert_syncdata_sql();
                                    
                                    unset($products_to_sync[$KPTS]);

                                }
                                
                            }else{

                                $arrayReturn['products_to_sync'] = 0;

                            }

                            unset($get_response_table_data[$nombre_tabla]['modified']);

                        }


                        break;
                    case 'product_formats':
                        
                        $item_type = 'product_format'; 

                        if (!empty($this->products_not_synced) && count($modified_data) > 0){

                            foreach ($modified_data as $keyForm => $format) {
                        
                                if (isset($this->products_not_synced[$format['products_id']])){

                                    $this->debbug('## Error. The Format with SL ID '.$format['id'].' has no product parent to synchronize.');
                                    unset($modified_data[$keyForm]);
                                    
                                }

                            }

                        }

                        $this->debbug('Total count of sync product formats to store: '.count($modified_data));
                        $this->debbug('Product formats data: '.print_r($modified_data,1));
                        $arrayReturn['product_formats_to_sync'] = count($modified_data);
                        
                        if (count($modified_data) > 0){

                            $product_format_data_to_store = $this->prepare_product_format_data_to_store($modified_data);

                            unset($modified_data);

                            if (isset($product_format_data_to_store['product_format_data']) && !empty($product_format_data_to_store['product_format_data'])){

                                $product_formats_to_sync = $product_format_data_to_store['product_format_data'];
                                unset($product_format_data_to_store['product_format_data']);

                                $product_format_params = array_merge($product_format_data_to_store, $sync_params);

                                foreach ($product_formats_to_sync as $KPFTS => $product_format_to_sync) {
                                    
                                    $item_data_to_insert = html_entity_decode(json_encode($product_format_to_sync));
                                    $sync_params_to_insert = json_encode($product_format_params);
                                    
                                    $this->sql_to_insert[] = "('".$sync_type."', '".$item_type."', '".addslashes($item_data_to_insert)."', '".addslashes($sync_params_to_insert)."')";
                                    $this->insert_syncdata_sql();

                                    unset($product_formats_to_sync[$KPFTS]);

                                }
                                
                            }else{

                                $arrayReturn['product_formats_to_sync'] = 0;
                                
                            }

                            unset($get_response_table_data[$nombre_tabla]['modified']);

                        }

                        break;
                    default:

                        $item_type = '';
                        $this->debbug('## Error. Synchronizing, table '.$nombre_tabla.' not recognized.');

                        break;
                }

                
                $this->insert_syncdata_sql(true);

                $this->debbug('#### time_store_items_update - '.$item_type.': '.(microtime(1) - $time_ini_store_items_update).' seconds.');

            }

            if ($this->manage_indexers){

                $stop_indexers = false;

                if (empty($arrayReturn)){

                    $stop_indexers = true;
                
                }else{

                    foreach ($arrayReturn as $idx => $count) {
                
                        if ($count > 0){
                
                            $stop_indexers = true;
                            break;

                        }
                
                    }
                
                } 

                if ($stop_indexers){

                    $indexer_collection_ids = $this->indexerCollection->getAllIds();

                    foreach ($indexer_collection_ids as $indexer_id) {

                        $indexer_sql_to_insert = '';
                        $indexer = clone $this->indexer;
                        $indexer = $indexer->load($indexer_id);

                        try{

                            $time_ini_indexer = microtime(1);

                            $indexer_sql_to_insert = "('".$indexer_id."', '".$indexer->getTitle()."', '".$indexer->getState()->getStatus()."')";

                            $indexer->getState()->setStatus(\Magento\Framework\Indexer\StateInterface::STATUS_WORKING);
                            $indexer->getState()->save();


                            $this->debbug('## time_indexer to working: '.(microtime(1) - $time_ini_indexer).' seconds.', 'timer');

                        }catch(\Exception $e){

                            $indexer_sql_to_insert = '';
                            $this->debbug('## Error. Updating indexer '.$indexer_id.' to working status: '.$e->getMessage(), 'syncdata');

                        }
                        
                        if ($indexer_sql_to_insert != ''){

                            try{

                                $indexer_sql_query_to_insert = " INSERT INTO ".$this->saleslayer_indexers_table.
                                                                 " ( indexer_id, indexer_title, indexer_status ) VALUES ".
                                                                 $indexer_sql_to_insert;

                                $this->connection->query($indexer_sql_query_to_insert);

                            }catch(\Exception $e){

                                $this->debbug('## Error. Insert indexer SQL message: '.$e->getMessage());
                                $this->debbug('## Error. Insert indexer SQL query: '.$sql_query_to_insert);

                            }

                        }

                    }

                }

            }

        }

        $this->debbug('##### time_all_store_process: '.(microtime(1) - $time_ini_all_store_process).' seconds.');

        $this->debbug("\r\n==== Store Sync Data END ====\r\n");

        return $arrayReturn;
    
    }

    /**
     * Function to insert sync data into the database.
     * @param  boolean $force_insert             forces sql to be inserted
     * @return void
     */
    private function insert_syncdata_sql($force_insert = false){

        if (!empty($this->sql_to_insert) && (count($this->sql_to_insert) >= $this->sql_to_insert_limit || $force_insert)){

            $sql_to_insert = implode(',', $this->sql_to_insert);
            
            try{

                $sql_query_to_insert = " INSERT INTO ".$this->saleslayer_syncdata_table.
                                                 " ( sync_type, item_type, item_data, sync_params ) VALUES ".
                                                 $sql_to_insert;

                $this->connection->query($sql_query_to_insert);

            }catch(\Exception $e){

                $this->debbug('## Error. Insert syncdata SQL message: '.$e->getMessage());
                $this->debbug('## Error. Insert syncdata SQL query: '.$sql_query_to_insert);

            }

            $this->sql_to_insert = array();
            
        }

    }
 
    /**
     * Function to insert multiconn data into the database
     * @param  string   $query                  query to execute  
     * @param  array    $params                 params for query
     * @return void
     */
    // private function execute_multiconn_sql($query, $params){

    //     try{

    //         $this->connection->query($query, $params);

    //     }catch(\Exception $e){

    //         $this->debbug('## Error. Insert syncdata SQL query: '.$query.' - params: '.print_r($params,1));
    //         $this->debbug('## Error. Insert syncdata SQL message: '.$e->getMessage());

    //     }

    // }

    /**
    * Function to store Sales Layer categories data.
    * @param  array $arrayCatalogue                 categories data to organize
    * @return array $categories_data_to_store       categories data to store
    */
    private function prepare_category_data_to_store ($arrayCatalogue) {

        $data_schema              = json_decode($this->sl_data_schema, 1);
        $schema                   = $data_schema['catalogue'];

        $category_data_to_store = array();

        $category_data_to_store['default_category_id'] = $this->default_category_id;
        $category_data_to_store['category_is_anchor'] = $this->category_is_anchor;
        $category_data_to_store['category_page_layout'] = $this->category_page_layout;
        $category_data_to_store['avoid_images_updates'] = $this->avoid_images_updates;

        if ($schema['fields'][$this->category_field_name]['has_multilingual']) {

            $this->category_field_name        .= '_'.$this->sl_language;

        }

        $category_data_to_store['category_fields']['category_field_name'] = $this->category_field_name;

        if ($schema['fields'][$this->category_field_description]['has_multilingual']) {

            $this->category_field_description .= '_'.$this->sl_language;

        }

        $category_data_to_store['category_fields']['category_field_description'] = $this->category_field_description;

        if ($schema['fields'][$this->category_field_image]['has_multilingual']) {

            $this->category_field_image       .= '_'.$this->sl_language;

        }

        $category_data_to_store['category_fields']['category_field_image'] = $this->category_field_image;

        if (isset($schema['fields'][$this->category_field_meta_title]) && $schema['fields'][$this->category_field_meta_title]['has_multilingual']) {

            $this->category_field_meta_title .= '_'.$this->sl_language;

        }

        $category_data_to_store['category_fields']['category_field_meta_title'] = $this->category_field_meta_title;

        if (isset($schema['fields'][$this->category_field_meta_keywords]) && $schema['fields'][$this->category_field_meta_keywords]['has_multilingual']) {

            $this->category_field_meta_keywords .= '_'.$this->sl_language;

        }

        $category_data_to_store['category_fields']['category_field_meta_keywords'] = $this->category_field_meta_keywords;

        if (isset($schema['fields'][$this->category_field_meta_description]) && $schema['fields'][$this->category_field_meta_description]['has_multilingual']) {

            $this->category_field_meta_description .= '_'.$this->sl_language;

        }

        $category_data_to_store['category_fields']['category_field_meta_description'] = $this->category_field_meta_description;

        $this->category_images_sizes = array();

        if (!empty($schema['fields']['section_image']['image_sizes'])) {

            $category_field_images_sizes = $schema['fields']['section_image']['image_sizes'];
            $ordered_image_sizes = $this->order_array_img($category_field_images_sizes);

            foreach ($ordered_image_sizes as $img_size => $img_dimensions) {

                $this->category_images_sizes[] = $img_size;

            }

        } else if (!empty($schema['fields']['image_sizes'])) {

            $category_field_images_sizes = $schema['fields']['image_sizes'];
            $ordered_image_sizes = $this->order_array_img($category_field_images_sizes);

            foreach ($ordered_image_sizes as $img_size => $img_dimensions) {

                $this->category_images_sizes[] = $img_size;

            }

        } else {

            $this->category_images_sizes[] = 'IMD';
            $this->category_images_sizes[] = 'THM';
            $this->category_images_sizes[] = 'TH';

        }

        $category_data_to_store['category_fields']['category_images_sizes'] = $this->category_images_sizes;

        if (isset($this->media_field_names['catalogue']) && !empty($this->media_field_names['catalogue'])){

            $category_data_to_store['catalogue_media_field_names'] = $this->media_field_names['catalogue'];

        }

        if (!empty($arrayCatalogue)){

            $time_ini_reorganizeCategories = microtime(1);
            $arrayCatalogue = $this->reorganizeCategories($arrayCatalogue);
            $this->debbug('### reorganizeCategories: '.(microtime(1) - $time_ini_reorganizeCategories).' seconds.');
            
            $category_data_to_store['category_data'] = $arrayCatalogue;

        }

        return $category_data_to_store;

    }

    /**
    * Function to store Sales Layer products data.
    * @param  array $arrayProducts              products data to organize
    * @return array $products_data_to_store     products data to store
    */
    private function prepare_product_data_to_store($arrayProducts){

        $product_data_to_store = array();

        $product_data_to_store['avoid_stock_update'] = $this->avoid_stock_update;
        $product_data_to_store['products_previous_categories'] = $this->products_previous_categories;
        $product_data_to_store['avoid_images_updates'] = $this->avoid_images_updates;

        $attribute_set_collection = $this->attribute_set->getCollection()->setEntityTypeFilter($this->entity_type);
        foreach ($attribute_set_collection as $attribute_model) {

            $this->attribute_set_collection[$attribute_model->getId()] = array('id' => $attribute_model->getId(),
                                                                            'attribute_set_name' => $attribute_model->getAttributeSetName());

        }

        $product_data_to_store['attribute_set_collection'] = $this->attribute_set_collection;

        foreach ($this->attribute_set_collection as $attribute_set_id => $attribute_set){
            
            if (!$this->default_attribute_set_id){
         
                //The first attribute set is set in case the 'Default' attribute set doesn't exist.
                $this->default_attribute_set_id = $attribute_set_id;
                
            }

            if ($attribute_set['attribute_set_name'] == 'Default'){

                //We set the 'Default' attribute and break the search.
                $this->default_attribute_set_id = $attribute_set_id;
                break;
            }

        }

        $product_data_to_store['default_attribute_set_id'] = $this->default_attribute_set_id;

        $data_schema = json_decode($this->sl_data_schema, 1);
        $schema      = $data_schema['products'];

        if (@$schema['fields'][$this->product_field_name]['has_multilingual']) {

            $this->product_field_name        .= '_' . $this->sl_language;

        }

        $product_data_to_store['product_fields']['product_field_name'] = $this->product_field_name;

        if (@$schema['fields'][$this->product_field_description]['has_multilingual']) {

            $this->product_field_description .= '_' . $this->sl_language;

        }

        $product_data_to_store['product_fields']['product_field_description'] = $this->product_field_description;

        if (isset($schema['fields'][$this->product_field_description_short]) && @$schema['fields'][$this->product_field_description_short]['has_multilingual']) {

            $this->product_field_description_short .= '_' . $this->sl_language;

        }

        $product_data_to_store['product_fields']['product_field_description_short'] = $this->product_field_description_short;

        if (@$schema['fields'][$this->product_field_price]['has_multilingual']) {

            $this->product_field_price       .= '_' . $this->sl_language;

        }

        $product_data_to_store['product_fields']['product_field_price'] = $this->product_field_price;

        if (@$schema['fields'][$this->product_field_image]['has_multilingual']) {

            $this->product_field_image       .= '_' . $this->sl_language;

        }

        $product_data_to_store['product_fields']['product_field_image'] = $this->product_field_image;

        if (isset($schema['fields'][$this->product_field_meta_title]) && $schema['fields'][$this->product_field_meta_title]['has_multilingual']) {

            $this->product_field_meta_title .= '_'.$this->sl_language;

        }

        $product_data_to_store['product_fields']['product_field_meta_title'] = $this->product_field_meta_title;

        if (isset($schema['fields'][$this->product_field_meta_keywords]) && $schema['fields'][$this->product_field_meta_keywords]['has_multilingual']) {

            $this->product_field_meta_keywords .= '_'.$this->sl_language;

        }

        $product_data_to_store['product_fields']['product_field_meta_keywords'] = $this->product_field_meta_keywords;

        if (isset($schema['fields'][$this->product_field_meta_description]) && $schema['fields'][$this->product_field_meta_description]['has_multilingual']) {

            $this->product_field_meta_description .= '_'.$this->sl_language;

        }

        $product_data_to_store['product_fields']['product_field_meta_description'] = $this->product_field_meta_description;

        if (isset($schema['fields'][$this->product_field_tax_class_id]) && $schema['fields'][$this->product_field_tax_class_id]['has_multilingual']) {

            $this->product_field_tax_class_id .= '_'.$this->sl_language;

        }

        $product_data_to_store['product_fields']['product_field_tax_class_id'] = $this->product_field_tax_class_id;

        $size_fields = array('product_field_length', 'product_field_width', 'product_field_height', 'product_field_weight');
        
        foreach ($size_fields as $size_field) {
            
            if (isset($product_data_to_store['product_fields'][$size_field]) && $product_data_to_store['product_fields'][$size_field]['has_multilingual']) {

                $this->$size_field .= '_'.$this->sl_language;

            }

            $product_data_to_store['product_fields'][$size_field] = $this->$size_field;

        }

        $this->product_images_sizes = array();

        if (!empty($schema['fields']['product_image']['image_sizes'])) {

            $product_field_images_sizes = $schema['fields']['product_image']['image_sizes'];
            $ordered_image_sizes = $this->order_array_img($product_field_images_sizes);

            foreach ($ordered_image_sizes as $img_size => $img_dimensions) {
                $this->product_images_sizes[] = $img_size;
            }

        } else if (!empty($schema['fields']['image_sizes'])) {

            $product_field_images_sizes = $schema['fields']['image_sizes'];
            $ordered_image_sizes = $this->order_array_img($product_field_images_sizes);

            foreach ($ordered_image_sizes as $img_size => $img_dimensions) {

                $this->product_images_sizes[] = $img_size;
            }

        } else {

            $this->product_images_sizes[] = 'IMD';
            $this->product_images_sizes[] = 'THM';
            $this->product_images_sizes[] = 'TH';
        }

        if ($this->sl_DEBBUG > 1) $this->debbug('Product image sizes: '.implode(', ', (array)$this->product_images_sizes));

        $product_data_to_store['product_fields']['product_images_sizes'] = $this->product_images_sizes;

        $total_extensions = count($this->product_images_sizes);
        switch ($total_extensions) {
            case 3:
                $this->image_extension = '_'.$this->product_images_sizes[0];
                $this->small_image_extension = '_'.$this->product_images_sizes[$total_extensions - 2];
                $this->thumbnail_extension = '_'.$this->product_images_sizes[$total_extensions - 1];
                break;
            case 2:
                $this->image_extension = $this->small_image_extension = '_'.$this->product_images_sizes[0];
                $this->thumbnail_extension = '_'.$this->product_images_sizes[$total_extensions - 1];
                break;
            case 1:
                $this->image_extension = $this->small_image_extension = $this->thumbnail_extension = '_'.$this->product_images_sizes[0];
                break;
        }

        $product_data_to_store['product_fields']['image_extensions'] = array('image_extension' => $this->image_extension, 'small_image_extension' => $this->small_image_extension, 'thumbnail_extension' => $this->thumbnail_extension);
        
        //Check of sku field case sensitive
        if (!empty($schema['fields'][strtolower($this->product_field_sku)])) {
            $this->product_field_sku = strtolower($this->product_field_sku);
        }else if (!empty($schema['fields'][strtoupper($this->product_field_sku)])) {
            $this->product_field_sku = strtoupper($this->product_field_sku);
        }

        if (!empty($schema['fields'][$this->product_field_sku])) {

            $this->has_product_field_sku = true;

            if ($schema['fields'][$this->product_field_sku]['has_multilingual']) {

                $this->product_field_sku .= '_'.$this->sl_language;
            }
        }

        $product_data_to_store['product_fields']['has_product_field_sku'] = $this->has_product_field_sku;
        $product_data_to_store['product_fields']['product_field_sku'] = $this->product_field_sku;

        if (!empty($schema['fields'][$this->product_field_qty])) {

            $this->has_product_field_qty = true;

            if ($schema['fields'][$this->product_field_qty]['has_multilingual']) {

                $this->product_field_qty .= '_'.$this->sl_language;
            }
        }

        $product_data_to_store['product_fields']['has_product_field_qty'] = $this->has_product_field_qty;
        $product_data_to_store['product_fields']['product_field_qty'] = $this->product_field_qty;

        $fixed_product_fields = array('ID', 'ID_catalogue', 'product_name', 'product_description', 'product_description_short', 'product_price', 'product_image', 'image_sizes', 'sku', 'qty',
                                        'attribute_set_id', 'product_meta_title', 'product_meta_keywords', 'product_meta_description', 'product_length', 'product_width', 
                                        'product_height', 'product_weight', 'related_products_references', 'crosssell_products_references', 'upsell_products_references', 'product_tax_class_id',
                                        'product_status', 'product_inventory_backorders');

        if (!empty($schema['fields'])){
        
            foreach ($schema['fields'] as $field_name => $field_props){

                if (!in_array($field_name, $fixed_product_fields)){

                    if ($field_props['has_multilingual']){

                        $product_data_to_store['product_additional_fields'][$field_name] = $field_name.'_'.$this->sl_language;

                    } else {

                        $product_data_to_store['product_additional_fields'][$field_name] = $field_name;

                    }

                }

            }

        }

        if ($this->sl_DEBBUG > 1 and count($product_data_to_store['product_additional_fields'])){

            $this->debbug("Product additional fields:\n".print_r($product_data_to_store['product_additional_fields'],1));

        }

        if (isset($this->media_field_names['products']) && !empty($this->media_field_names['products'])){

            $product_data_to_store['products_media_field_names'] = $this->media_field_names['products'];

        }
        
        if (!empty($arrayProducts)){

            foreach ($arrayProducts as $keyProd => $product) {

                if (empty($product['catalogue_id'])){

                    $this->debbug('## Error. Product '.$product['data'][$this->product_field_name].' with SL ID '.$product['id'].' has no categories.');
                    $this->products_not_synced[$product['id']] = 0;
                    unset($arrayProducts[$keyProd]);

                }

            }

            if (!empty($arrayProducts)){

                $product_data_to_store['product_data'] = $arrayProducts;

            }

        }
        
        return $product_data_to_store;

    }

    /**
    * Function to store Sales Layer product formats data.
    * @param  array $arrayFormats               product formats data to organize
    * @return array $product_format_data_to_store     product formats data to store
    */
    private function prepare_product_format_data_to_store($arrayFormats){

        $product_format_data_to_store = array();

        $product_format_data_to_store['avoid_stock_update'] = $this->avoid_stock_update;
        $product_format_data_to_store['format_configurable_attributes'] = $this->format_configurable_attributes;
        $product_format_data_to_store['avoid_images_updates'] = $this->avoid_images_updates;

        $data_schema = json_decode($this->sl_data_schema, 1);
        $schema      = $data_schema['product_formats'];

        $arrayFormats = $this->organizeTablesIndex($arrayFormats, $schema['fields']);

        $this->format_images_sizes = array();

        $parent_all_data = array();

        if (!empty($schema['fields'][$this->format_field_image]['image_sizes'])) {

            $format_field_images_sizes = $schema['fields'][$this->format_field_image]['image_sizes'];
            $ordered_image_sizes = $this->order_array_img($format_field_images_sizes);

            foreach ($ordered_image_sizes as $img_size => $img_dimensions) {
                $this->format_images_sizes[] = $img_size;
            }

        } else if (!empty($schema['fields']['image_sizes'])) {

            $format_field_images_sizes = $schema['fields']['image_sizes'];
            $ordered_image_sizes = $this->order_array_img($format_field_images_sizes);

            foreach ($ordered_image_sizes as $img_size => $img_dimensions) {

                $this->format_images_sizes[] = $img_size;
            }

        } else {

            $this->format_images_sizes[] = 'IMD';
            $this->format_images_sizes[] = 'THM';
            $this->format_images_sizes[] = 'TH';
        }

        if ($this->sl_DEBBUG > 1) $this->debbug('Format image sizes: '.implode(', ', (array)$this->format_images_sizes));

        $product_format_data_to_store['product_format_fields']['format_images_sizes'] = $this->format_images_sizes;

        $total_extensions = count($this->format_images_sizes);
        switch ($total_extensions) {
            case 3:
                $this->image_extension = '_'.$this->format_images_sizes[0];
                $this->small_image_extension = '_'.$this->format_images_sizes[$total_extensions - 2];
                $this->thumbnail_extension = '_'.$this->format_images_sizes[$total_extensions - 1];
                break;
            case 2:
                $this->image_extension = $this->small_image_extension = '_'.$this->format_images_sizes[0];
                $this->thumbnail_extension = '_'.$this->format_images_sizes[$total_extensions - 1];
                break;
            case 1:
                $this->image_extension = $this->small_image_extension = $this->thumbnail_extension = '_'.$this->format_images_sizes[0];
                break;
        }
        
        $product_format_data_to_store['product_format_fields']['image_extensions'] = array('image_extension' => $this->image_extension, 'small_image_extension' => $this->small_image_extension, 'thumbnail_extension' => $this->thumbnail_extension);

        if (isset($this->media_field_names['product_formats']) && !empty($this->media_field_names['product_formats'])){

            $product_format_data_to_store['product_formats_media_field_names'] = $this->media_field_names['product_formats'];

        }

        if (!empty($arrayFormats)){

            foreach ($arrayFormats as $keyForm => $format) {

                if ($format['products_id'] == 0){

                    $this->debbug('## Error. Format '.$format['data']['format_name'].' with SL ID '.$format['id'].' has no product parent.');
                    unset($arrayFormats[$keyForm]);

                }

            }

            if (!empty($arrayFormats)){

                $product_format_data_to_store['product_format_data'] = $arrayFormats;

            }

        }

        return $product_format_data_to_store;

    }

    /**
     * Function to synchronize Sales Layer stored category.
     * @param  array $category              category to synchronize
     * @return string                       category updated or not
     */
    protected function sync_stored_category($category){

        if ($this->sl_DEBBUG > 2) $this->debbug('Synchronizing stored category: '.print_R($category,1));

        $time_ini_check_category = microtime(1);
        if ($this->check_category($category)){
            $this->debbug('## check_category: '.(microtime(1) - $time_ini_check_category).' seconds.', 'timer');
            $syncCat = true;
            $time_ini_sync_category_core_data = microtime(1);
            if (!$this->sync_category_core_data($category)){
                $syncCat = false;
            }
            $this->debbug('## sync_category_core_data: '.(microtime(1) - $time_ini_sync_category_core_data).' seconds.', 'timer');
            if (!$syncCat){ 
                return 'item_not_updated'; 
            }
            if (!empty($this->store_view_ids)){
                foreach ($this->store_view_ids as $store_view_id) {
                    $time_ini_sync_category_data = microtime(1);
                    if (!$this->sync_category_data($category, $store_view_id)){
                        $syncCat = false;
                        break;
                    }
                    $this->debbug('## sync_category_data store_view_id: '.$store_view_id.': '.(microtime(1) - $time_ini_sync_category_data).' seconds.', 'timer');
                }
            }else{
                $time_ini_sync_category_data = microtime(1);
                $syncCat = $this->sync_category_data($category);
                $this->debbug('## sync_category_data no store_view_id: '.(microtime(1) - $time_ini_sync_category_data).' seconds.', 'timer');
            }

            if ($syncCat){
                
                return 'item_updated';

            }else{
                
                return 'item_not_updated';
            }

        }else{

            return 'item_not_updated';

        }

    }

    /**
     * Function to check if Sales Layer category exists.
     * @param  array $category                  category to synchronize
     * @return boolean                          result of category check
     */
    private function check_category($category){

        $sl_id        = $category['id'];
        $sl_parent_id = $category['catalogue_parent_id'];
        
        if ($sl_parent_id != '0') {
           $parentCategory = $this->findSaleslayerCategoryId($sl_parent_id, $this->comp_id);
           if(is_null($parentCategory)){
                $this->debbug('## Error. Category has no parent.');
                return false;
           }
        }

        $cat = $this->findSaleslayerCategoryId($sl_id, $this->comp_id);
        
        $sl_data        = $category['data'];
        $sl_name        = $sl_data[$this->category_field_name];
        
        if (is_null($cat)){
            
            $category_id_found = 0;
            
            if (!empty($this->categories_collection)){

                if (isset($this->categories_collection_names[$sl_name])){

                    foreach ($this->categories_collection_names[$sl_name] as $category_id) {

                        $category_col = $this->categories_collection[$category_id];

                        if ((isset($category_col['saleslayer_id']) && !in_array($category_col['saleslayer_id'], array(0, '', null))) && (isset($category_col['saleslayer_comp_id']) && !in_array($category_col['saleslayer_comp_id'], array(0, '', null)))){
                            
                            continue;
                            
                        }
                        
                        $path = $category_col['path'];
                        $path_ids = explode('/', $path);

                        if (isset($path_ids[1]) && isset($this->categories_collection[$path_ids[1]]) && $this->categories_collection[$path_ids[1]]['parent_id'] == 1){
                            
                            $category_id_found = $category_col['entity_id'];
                            break;

                        }

                    }

                }

            }
        
            if ($category_id_found !== 0){
                
                $update_category = $this->load_category_model($category_id_found);
                $update_category->setSaleslayerId($sl_id);
                $update_category->setSaleslayerCompId($this->comp_id);

                if ($update_category->getIsActive() == 0){
                    $update_category->setIsActive(1);
                    $this->categories_collection[$category_id_found]['is_active'] = 1;
                }
                
                try {
                    $update_category->save();
                    $this->debbug("Updated existing category Sales Layer credentials!");
                    $this->categories_collection[$category_id_found]['saleslayer_id'] = $sl_id;
                    $this->categories_collection[$category_id_found]['saleslayer_comp_id'] = $this->comp_id;
                    return true;
                } catch (\Exception $e) {
                    $this->debbug("## Error. Updating existing category ".$sl_name." Sales Layer credentials: ".$e->getMessage());
                    return false;
                }
            }else{
                if ($this->create_category($category)){
                    return true;
                }else{
                    return false;
                }
            }
        }else{
            
            if (in_array($cat->getSaleslayerCompId(), array(0,'',null))){
                $cat->setSaleslayerCompId($this->comp_id);
                try {
                    $cat->save();
                    $this->debbug("Updated existing category Sales Layer company id credential!");
                    $this->categories_collection[$cat->getEntityId()]['saleslayer_comp_id'] = $this->comp_id;
                    return true;
                } catch (\Exception $e) {
                    $this->debbug("## Error. Updating existing category Sales Layer company id credential: ".$e->getMessage());
                    $this->debbug('## Error. '.$sl_name.' - '.$e->getMessage());
                    return false;
                }

            }

        }

        return true;
    }

    /**
     * Function to create Sales Layer category.
     * @param  array $category                  category to create
     * @return boolean                          result of category creation
     */
    private function create_category ($category) {

        $sl_id        = $category['id'];
        $sl_parent_id = $category['catalogue_parent_id'];
        
        $sl_data        = $category['data'];
        $sl_name        = $sl_data[$this->category_field_name];
        $sl_description = $sl_data[$this->category_field_description];

        $parentCategory_path = '';
        $parentCategoryId = 0;

        if ($sl_parent_id != '0') {

            $parentCategory = $this->findSaleslayerCategoryId($sl_parent_id, $this->comp_id);
            $parentCategory_path = $parentCategory->getPath();
            $parentCategoryId = $parentCategory->getEntityId();
        
        }else{
            
            $parentCategory_path = '1/'.$this->default_category_id;
            $parentCategoryId = $this->default_category_id;
        }

        $new_category = $this->load_category_model();

        $this->debbug(" > Creating category ID: $sl_id (parent: $sl_parent_id: $parentCategory_path)");
        if ($this->sl_DEBBUG > 1) $this->debbug(" Name ({$this->category_field_name}): $sl_name");

        $new_category->setName        ($sl_name);
        $new_category->setUrlKey      ($sl_name);
        
        $new_category->setIsActive(1)
                    ->setDisplayMode('PRODUCTS_AND_PAGE')
                    ->setIsAnchor($this->category_is_anchor)
                    ->setPageLayout($this->category_page_layout);
        
        $new_category->setSaleslayerId($sl_id);
        $new_category->setSaleslayerCompId($this->comp_id);

        $new_category->setParentId($parentCategoryId);
        
        if (isset($sl_data[$this->category_field_meta_title]) && $sl_data[$this->category_field_meta_title] != ''){
            $new_category->setMetaTitle($sl_data[$this->category_field_meta_title]);
        }

        if (isset($sl_data[$this->category_field_meta_keywords]) && $sl_data[$this->category_field_meta_keywords] != ''){
            $new_category->setMetaKeywords($sl_data[$this->category_field_meta_keywords]);
        }

        if (isset($sl_data[$this->category_field_meta_description]) && $sl_data[$this->category_field_meta_description] != ''){
            $new_category->setMetaDescription($sl_data[$this->category_field_meta_description]);
        }

        $data = array();
        $data['description'] = $sl_description;
       
       if ($this->avoid_images_updates){

           $this->debbug(" > Avoiding update of category image in creation.");

       }else{
            if (!empty($sl_data[$this->category_field_image])) {
                $section_image = $sl_data[$this->category_field_image];
                if(count($section_image) > 0) {
                    $image = reset($section_image);
                    foreach ($this->category_images_sizes as $img_format) {
                        if (!empty($image[$img_format])) {
                            $image_url = $image[$img_format];
                            $img_filename  = $this->prepareImage($image_url, $this->category_path_base, false);
                            if ($img_filename) {
                                $data['image'] = $img_filename;
                                break;
                            }
                        }
                    }
                }
            }
        }

        $new_category->addData($data);

        try {
            if ($new_category->save()){
                if ($this->sl_DEBBUG > 1) $this->debbug("Category created!");
                
                if ($new_category->getEntityId()){

                    $parentCategory_path .= '/'.$new_category->getEntityId();

                }

                $new_category->setPath($parentCategory_path);
                $new_category->save();

                $this->categories_collection[$new_category->getEntityId()] = array('entity_id' => $new_category->getEntityId(),
                                                                                'name' => $sl_name,
                                                                                'is_active' => 1,
                                                                                'parent_id' => $new_category->getParentId(),
                                                                                'path'      => $new_category->getPath(),
                                                                                'saleslayer_id' => $sl_id,
                                                                                'saleslayer_comp_id' => $this->comp_id);
                
                $this->categories_collection_names[$sl_name][] = $new_category->getEntityId();
                
                return true;
            }else{
                $this->debbug('## Error. Creating new category '.$sl_name.'.');
                return false;
            }
        } catch (\Exception $e) {
            $this->debbug("## Error. Creating new category ".$sl_name.' : '.$e->getMessage());
            return false;
        }
    }

    /**
     * Function to synchronize Sales Layer category core data.
     * @param  array $category                  category to synchronize
     * @return boolean                          result of category data synchronization
     */
    private function sync_category_core_data($category) {

        $sl_id        = $category['id'];
        $sl_parent_id = $category['catalogue_parent_id'];
        
        $parentCategory_path = '';
        $parentCategoryId = 0;

        if ($sl_parent_id != '0'){

            $parentCategory = $this->findSaleslayerCategoryId($sl_parent_id, $this->comp_id);
            $parentCategoryId = $parentCategory->getEntityId();
            $parentCategory_path = $parentCategory->getPath();

        }else{

            $parentCategoryId = $this->default_category_id;
            $parentCategory_path = '1/'.$parentCategoryId;

        }

        $update_category = $this->findSaleslayerCategoryId($sl_id, $this->comp_id);
        
        if ($update_category->getEntityId()) {

            $parentCategory_path .= '/'.$update_category->getEntityId();

        }

        $sl_data        = $category['data'];
        $sl_name        = $sl_data[$this->category_field_name];
        
        $this->debbug(" > Updating category core data ID: $sl_id (parent: $sl_parent_id: $parentCategory_path)");

        if ($update_category->getName() != $sl_name){

            $update_category->setName($sl_name);
            
            if ($this->categories_collection[$update_category->getEntityId()]['name'] != $sl_name){
                $old_sl_name = $this->categories_collection[$update_category->getEntityId()]['name'];
                unset($this->categories_collection_names[$old_sl_name][$update_category->getEntityId()]);
                if (count($this->categories_collection_names[$old_sl_name]) == 0 || empty($this->categories_collection_names[$old_sl_name])){
                    unset($this->categories_collection_names[$old_sl_name]);
                }
                $this->categories_collection_names[$sl_name][] = $update_category->getEntityId();
                $this->categories_collection[$update_category->getEntityId()]['name'] = $sl_name;
            }
            
            $update_category->setUrlKey($sl_name);

        }

        if (isset($sl_data[$this->category_field_active])){

            $sl_category_active = $sl_data[$this->category_field_active];
            $sl_category_active_bool = $this->sl_validate_status_value($sl_category_active);

            if (!$sl_category_active_bool && $update_category->getIsActive() != 0){

                $update_category->setIsActive(0);
                $this->categories_collection[$update_category->getEntityId()]['is_active'] = 0;

            }else if ($sl_category_active_bool && $update_category->getIsActive() != 1){

                $update_category->setIsActive(1);
                $this->categories_collection[$update_category->getEntityId()]['is_active'] = 1;

            }

        }else{

            if ($update_category->getIsActive() != 1){

                $update_category->setIsActive(1);
                $this->categories_collection[$update_category->getEntityId()]['is_active'] = 1;

            }

        }

        if ($update_category->getIsAnchor() != $this->category_is_anchor){

            $update_category->setIsAnchor($this->category_is_anchor);

        }

        if ($update_category->getPageLayout() != $this->category_page_layout){

            $update_category->setPageLayout($this->category_page_layout);

        }

        try {

            $update_category->save();
            if ($this->sl_DEBBUG > 1) $this->debbug("Category core data updated!");

        } catch (\Exception $e) {

            $this->debbug("## Error. Updating core category ".$sl_name." data: ".$e->getMessage());
            return false;

        }

        try{

            $refresh_stats = true;
            $modified_stats = false;
            if ($update_category->getParentId() != $parentCategoryId || $parentCategory_path != $update_category->getPath()){

                try{

                    $update_category->setParentId($parentCategoryId);
                    $update_category->setPath($parentCategory_path);
                    $update_category->move($update_category->getParentId(), $parentCategoryId);
                    $update_category->save();
                    $refresh_stats = true;

                    if ($this->categories_collection[$update_category->getEntityId()]['parent_id'] != $parentCategoryId){
                        $this->categories_collection[$update_category->getEntityId()]['parent_id'] = $parentCategoryId;
                    }

                    if ($this->categories_collection[$update_category->getEntityId()]['path'] != $update_category->getPath()){
                        $this->categories_collection[$update_category->getEntityId()]['path'] = $parentCategory_path;
                    }

                }catch(\Exception $e){
                    $this->debbug('## Error. Reorganizing the category: '.$e->getMessage());
                    return false;
                }

            }

            if ($refresh_stats){

                $category_level = count(explode('/', $update_category->getPath())) - 1;
                if ($update_category->getLevel() != $category_level){

                    $update_category->setLevel($category_level);
                    $modified_stats = true;

                }

                if ($update_category->getChildrenCount() != count($update_category->getChildrenCategories())){
                    
                    $update_category->setChildrenCount(count($update_category->getChildrenCategories()));
                    $modified_stats = true;

                }

                if ($modified_stats){

                    $update_category->save();

                }

            }
        
        } catch (\Exception $e) {

            $this->debbug("## Error. Updating core category ".$sl_name." path data: ".$e->getMessage());
            return false;

        }

        if ($this->avoid_images_updates){

            $this->debbug(" > Avoiding update of category images in update core data.");

        }else{

            try{

                if (isset($sl_data[$this->category_field_image]) && !empty($sl_data[$this->category_field_image])) {
                    
                    $section_image = $sl_data[$this->category_field_image];
                    
                    if(count($section_image) > 0) {

                        $update_category_image = $this->load_category_model($update_category->getEntityId());
                        $image = reset($section_image);

                        foreach ($this->category_images_sizes as $img_format) {
                            if (!empty($image[$img_format])) {
                                $image_url = $image[$img_format];

                                $md5_image = $this->verify_md5_image_url($image_url);
                                
                                if (!$md5_image){ continue; }

                                $image_info = pathinfo($image_url);
                                $image_filename = $image_info['filename'].'.'.$image_info['extension'];
                                
                                $category_image_name = $update_category_image->getImage();
                                $category_image_path = '';
                                if ($category_image_name != ''){
                                    $category_image_path = $this->category_path_base.$category_image_name;
                                }
                               
                                if ($category_image_path != '' && $image_filename == $category_image_name && file_exists($category_image_path) && $md5_image == md5_file($category_image_path)){
                                
                                    break;
                                
                                }else{

                                    if ($category_image_path != '' && file_exists($category_image_path)){
                                        
                                        unlink($category_image_path);
                                    
                                    }

                                    $img_filename = $this->prepareImage($image_url, $this->category_path_base, false);
                                    
                                    if ($img_filename) {
                                    
                                        $update_category_image->setImage($img_filename);
                                        $update_category_image->save();
                                        
                                        break;

                                    }

                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {

                $this->debbug("## Error. Updating core category ".$sl_name." images data: ".$e->getMessage());
                
            }

        }

        try{

            $conn_insert = true;
            if (isset($this->sl_multiconn_table_data['category'][$sl_id]) && !empty($this->sl_multiconn_table_data['category'][$sl_id]['sl_connectors'])){

                $conn_found = array_search($this->processing_connector_id, $this->sl_multiconn_table_data['category'][$sl_id]['sl_connectors']);


                if (!is_numeric($conn_found)){

                    $this->sl_multiconn_table_data['category'][$sl_id]['sl_connectors'][] = $this->processing_connector_id;
                    
                    $new_connectors_data = json_encode($this->sl_multiconn_table_data['category'][$sl_id]['sl_connectors']);
                    
                    $query_update  = " UPDATE ".$this->saleslayer_multiconn_table." SET sl_connectors = ?  WHERE id = ? ";
                    
                    $this->sl_connection_query($query_update, array($new_connectors_data , $this->sl_multiconn_table_data['category'][$sl_id]['id']));

                }

                $conn_insert = false;

            }

            if ($conn_insert){

                $connectors_data = json_encode(array($this->processing_connector_id));

                $query_insert = " INSERT INTO ".$this->saleslayer_multiconn_table."(`item_type`,`sl_id`,`sl_comp_id`,`sl_connectors`) values ( ? , ? , ? , ? );";

                $this->sl_connection_query($query_insert, array('category', $sl_id, $this->comp_id, $connectors_data));
                
            }

        } catch (\Exception $e) {

            $this->debbug('## Error. Updating core category '.$sl_name.' SL data.');
            return false;

        }

        return true;

    }

    /**
     * Function to synchronize Sales Layer category data.
     * @param  array $category                  category to synchronize
     * @param  string $store_view_id            store view id to synchronize 
     * @return boolean                          result of category data synchronization
     */
    private function sync_category_data ($category, $store_view_id = null) {

        $sl_id        = $category['id'];
        $sl_parent_id = $category['catalogue_parent_id'];
        
        $parentCategory_path = '';
        $parentCategoryId = 0;

        if ($sl_parent_id != '0'){

            $parentCategory = $this->findSaleslayerCategoryId($sl_parent_id, $this->comp_id);
            $parentCategoryId = $parentCategory->getEntityId();
            
        }else{

            $parentCategoryId = $this->default_category_id;
            
        }

        $update_category = $this->findSaleslayerCategoryId($sl_id, $this->comp_id, $store_view_id);
        
        if ($update_category->getEntityId()) {

            $parentCategory_path .= '/'.$update_category->getEntityId();

        }

        $sl_data        = $category['data'];
        $sl_name        = $sl_data[$this->category_field_name];
        $sl_description = $sl_data[$this->category_field_description];

        $this->debbug(" > Updating category ID: $sl_id (parent: $sl_parent_id: $parentCategory_path)");
        if (!is_null($store_view_id)) $this->debbug(" > In store view id: ".$store_view_id);
        if ($this->sl_DEBBUG > 1) $this->debbug(" Name ({$this->category_field_name}): $sl_name");

        $update_category->setName($sl_name);
        if (is_null($store_view_id) || $store_view_id == 0){
            if ($this->categories_collection[$update_category->getEntityId()]['name'] != $sl_name){
                $old_sl_name = $this->categories_collection[$update_category->getEntityId()]['name'];
                unset($this->categories_collection_names[$old_sl_name][$update_category->getEntityId()]);
                if (count($this->categories_collection_names[$old_sl_name]) == 0 || empty($this->categories_collection_names[$old_sl_name])){
                    unset($this->categories_collection_names[$old_sl_name]);
                }
                $this->categories_collection_names[$sl_name][] = $update_category->getEntityId();
                $this->categories_collection[$update_category->getEntityId()]['name'] = $sl_name;
            }
        }

        $update_category->setUrlKey($sl_name);
        
        if ($update_category->getPageLayout() == 'one_column'){
            $update_category->setPageLayout  ('1column');
        }

        $update_category->setSaleslayerId($sl_id);
        $update_category->setSaleslayerCompId($this->comp_id);

        if ($this->categories_collection[$update_category->getEntityId()]['parent_id'] != $parentCategoryId){
            $this->categories_collection[$update_category->getEntityId()]['parent_id'] = $parentCategoryId;
        }
        
        if (isset($sl_data[$this->category_field_meta_title]) && $sl_data[$this->category_field_meta_title] != ''){
            $update_category->setMetaTitle($sl_data[$this->category_field_meta_title]);
        }

        if (isset($sl_data[$this->category_field_meta_keywords]) && $sl_data[$this->category_field_meta_keywords] != ''){
            $update_category->setMetaKeywords($sl_data[$this->category_field_meta_keywords]);
        }

        if (isset($sl_data[$this->category_field_meta_description]) && $sl_data[$this->category_field_meta_description] != ''){
            $update_category->setMetaDescription($sl_data[$this->category_field_meta_description]);
        }

        if (!$this->category_enabled_attribute_is_global){

            if (isset($sl_data[$this->category_field_active])){

                $sl_category_active = $sl_data[$this->category_field_active];
                $sl_category_active_bool = $this->sl_validate_status_value($sl_category_active);
                
                if (!$sl_category_active_bool && $update_category->getIsActive() != 0){

                    $update_category->setIsActive(0);
                    $this->categories_collection[$update_category->getEntityId()]['is_active'] = 0;

                }else if ($sl_category_active_bool && $update_category->getIsActive() != 1){

                    $update_category->setIsActive(1);
                    $this->categories_collection[$update_category->getEntityId()]['is_active'] = 1;

                }

            }else{

                if ($update_category->getIsActive() != 1){

                    $update_category->setIsActive(1);
                    $this->categories_collection[$update_category->getEntityId()]['is_active'] = 1;

                }

            }

        }

        $sl_description = $this->sl_check_html_text($sl_description);
        $update_category->setDescription($sl_description);

        if ($update_category->getImage() != '' && $update_category->getStoreId() != 0){
            $update_category->setImage('');
        }

        try {
            if ($update_category->save()){
                if ($this->sl_DEBBUG > 1) $this->debbug("Category updated!");
                return true;
            }else{
                $this->debbug('## Error. Updating category '.$sl_name.' data.');
                return false;
            }
        } catch (\Exception $e) {

            $this->debbug("## Error. Updating category ".$sl_name." data: ".$e->getMessage());
            return false;

        }

    }

    /**
     * Function to reorganize category parent ids after two synchronize tries.
     * @param  array $category                  category to reorganize parent ids
     * @return array $category                  category with parent ids reorganized
     */
    protected function reorganize_category_parent_ids($category){

        $all_categories_sl_ids = array();

        foreach ($this->categories_collection as $keyCat => $category_col) {
            
            if (isset($category_col['saleslayer_id'])){
            
                $all_categories_sl_ids[] = $category_col['saleslayer_id'];

            }

        }

        $all_categories_sl_ids = array_flip($all_categories_sl_ids);

        if (!empty($all_categories_sl_ids)){

            if (!is_array($category['catalogue_parent_id'])){
                                   
                $category_parent_ids = array($category['catalogue_parent_id']);
               
            }else{
               
                $category_parent_ids = array($category['catalogue_parent_id']);
               
            }

            $has_any_parent = false;

            foreach ($category_parent_ids as $category_parent_id) {
                   
                if ($category_parent_id == 0 || isset($all_categories_sl_ids[$category_parent_id])){
            
                    $has_any_parent = true;
                    break;

                } 

            }

            if (!$has_any_parent){

                $category['catalogue_parent_id'] = 0;
                
            }

        }

        return $category; 

    }

    /**
     * Function to synchronize Sales Layer stored product.
     * @param  array $product               product to synchronize
     * @return string                       product updated or not
     */
    protected function sync_stored_product($product){

        if ($this->sl_DEBBUG > 2) $this->debbug('Synchronizing stored product: '.print_R($product,1));
        
        $time_ini_check_product = microtime(1);
        
        if ($this->check_product($product)){
            $this->debbug('## check_product: '.(microtime(1) - $time_ini_check_product).' seconds.', 'timer');
            $syncProd = true;
            $time_ini_check_configurable_product = microtime(1);
            $this->check_configurable_product($product);
            $this->debbug('## check_configurable_product: '.(microtime(1) - $time_ini_check_configurable_product).' seconds.', 'timer');
            $time_ini_sync_product_core_data = microtime(1);
            if (!$this->sync_product_core_data($product)){
                $syncProd = false;
            }
            $this->debbug('## sync_product_core_data: '.(microtime(1) - $time_ini_sync_product_core_data).' seconds.', 'timer');
            if (!$syncProd){ 
                return 'item_not_updated'; 
            }
            if (!empty($this->store_view_ids)){
                foreach ($this->store_view_ids as $store_view_id) {
                    $time_ini_sync_product_data = microtime(1);
                    if (!$this->sync_product_data($product, $store_view_id)){
                        $syncProd = false;
                        break;
                    }
                    $this->debbug('## sync_product_data for store_view_id: '.$store_view_id.': '.(microtime(1) - $time_ini_sync_product_data).' seconds.', 'timer');
                }
            }else{
                $time_ini_sync_product_data = microtime(1);
                $syncProd = $this->sync_product_data($product);
                $this->debbug('## sync_product_data no store_view_id: '.(microtime(1) - $time_ini_sync_product_data).' seconds.', 'timer');
            }
            
            if ($syncProd){

                if ($this->avoid_images_updates){

                    $this->debbug(" > Avoiding update of product images in update.");

                }else{

                    $time_ini_sync_product_images = microtime(1);
                    $this->prepare_product_images_to_store($product);
                    $this->debbug('## sync_product_images: '.(microtime(1) - $time_ini_sync_product_images).' seconds.', 'timer');
                
                }
                
                $this->syncedProducts++;

                if ($this->syncedProducts % 20 == 0){
                    
                    $this->debbug('$$$ product model reload call: '.$this->syncedProducts, 'timer');
                    $this->load_models(false, true, false);

                }

            }

            if ($syncProd){

                return 'item_updated';

            }else{

                return 'item_not_updated';

            }


        }else{

            return 'item_not_updated';

        }

    }

    /**
     * Function to check if Sales Layer product exists.
     * @param  array $product                   product to synchronize
     * @return boolean                          result of product check
     */
    private function check_product($product){

        $sl_id = $product['id'];
        $sl_data = $product['data'];
        $sl_name = $sl_data[$this->product_field_name];
        $this->debbug(" > Checking if product ID: $sl_id exists");

        $existing_product = $this->findSaleslayerProductId($sl_id, $this->comp_id);
        $is_new_product = true;
        $useSKU = false;

        if (!$this->checkDuplicatedName('product', $sl_name, $sl_id, $this->comp_id)){
            if ($existing_product == null) {
                //If the product id is null we have to search a existing one by SKU.
                if ($this->has_product_field_sku) {
                    $sl_sku = $sl_data[$this->product_field_sku];
                    if ($sl_sku){
                        if (!$this->checkDuplicatedSKU('product', $sl_sku, $sl_id, $this->comp_id)){
                            $existing_product_id = $this->get_product_id_by_sku($sl_sku);
                            if($existing_product_id) {
                                $update_product = $this->load_product_model($existing_product_id);
                                $update_product->setSaleslayerId($sl_id);        
                                $update_product->setSaleslayerCompId($this->comp_id);

                                if ($update_product->getStatus() == $this->status_disabled){
                                    $update_product->setStatus($this->status_enabled);
                                    $this->products_collection[$existing_product_id]['status'] = $this->status_enabled;
                                }

                                try {
                                    $update_product->save();
                                    $this->products_collection[$existing_product_id]['saleslayer_id'] = $sl_id;
                                    $this->products_collection[$existing_product_id]['saleslayer_comp_id'] = $this->comp_id;
                                    if ($this->sl_DEBBUG > 1) $this->debbug("Updated existing product Sales Layer credentials!");
                                    return true;
                                } catch (\Exception $e) {
                                    $this->debbug('## Error. Updating existing product: '.$sl_name.' - Sales Layer credentials: '.$e->getMessage());
                                    return false;
                                }
                            }
                        }else{
                            return false;
                        }
                    }
                }

                if ($this->create_product($product)){
                    return true;
                }else{
                    return false;
                }

            } else {

                if ($this->has_product_field_sku) {
                    $sl_sku = $sl_data[$this->product_field_sku];
                    if ($sl_sku) {
                        if (!$this->checkDuplicatedSKU('product', $sl_sku, $sl_id, $this->comp_id)){
                            $existing_product_id = $this->get_product_id_by_sku($sl_sku);
                            if($existing_product_id) {
                                $update_product = $this->load_product_model($existing_product_id);
                                if (in_array($update_product->getSaleslayerCompId(), array(0, '', null))){

                                    if ($update_product->getStatus() == $this->status_disabled){
                                        $update_product->setStatus($this->status_enabled);
                                        $this->products_collection[$existing_product_id]['status'] = $this->status_enabled;
                                    }

                                    $update_product->setSaleslayerCompId($this->comp_id);
                                    try {
                                        $update_product->save();
                                        $this->products_collection[$existing_product_id]['saleslayer_comp_id'] = $this->comp_id;
                                        if ($this->sl_DEBBUG > 1) $this->debbug("Updated existing product Sales Layer credentials!");
                                        return true;
                                    } catch (\Exception $e) {
                                        $this->debbug('## Error. Updating existing product: '.$sl_name.' - Sales Layer credentials: '.$e->getMessage());
                                        return false;
                                    }

                                }

                            }

                            return true;

                        }else{
                            return false;
                        }
                    }
                }
            }
        }

        return false;

    }

    /**
     * Function to check if a configurable product children are well configured.
     * @param  array $product                   product to check
     * @return void
     */
    private function check_configurable_product($product){

        $sl_id = $product['id'];
        
        $this->debbug(" > Checking configurable product ID: $sl_id");
            
        $update_product = $this->findSaleslayerProductId($sl_id, $this->comp_id);
        
        if ($update_product->getTypeId() == $this->product_type_configurable){
            $update_product_id = $update_product->getEntityId();
            $update_product_attribute_set_id = $update_product->getAttributeSetId();

            $usedProductAttributeIds = $update_product->getTypeInstance()->getUsedProductAttributeIds($update_product);
            $childrenIds = $this->productConfigurableType->getChildrenIds($update_product_id);
            
            $this->load_attributes_by_attribute_set_id($update_product_attribute_set_id);

            if (count($childrenIds[0]) > 0 && count($usedProductAttributeIds) > 0){
                
                $modified = false;
                $all_children_attributes = array();

                foreach ($childrenIds[0] as $children_id) {
                        
                    $form_product = $this->load_product_model($children_id);
                    $children_attributes = array();

                    foreach ($usedProductAttributeIds as $usedProductAttributeId) {
                        
                        if (isset($this->attributes_collection[$update_product_attribute_set_id][$usedProductAttributeId])){

                            $attribute_name = $this->attributes_collection[$update_product_attribute_set_id][$usedProductAttributeId]['attribute_code'];

                            if ($form_product->getData($attribute_name) == 0){

                                $modified = true;
                                unset($childrenIds[0][$children_id]);
                                continue;

                            }else{

                                $children_attributes[$attribute_name] = $form_product->getData($attribute_name);

                            }

                        }

                    }

                    $duplicated = false;
                    if (!empty($all_children_attributes)){
                        foreach ($all_children_attributes as $ea_children_attributes) {
                            if ($ea_children_attributes == $children_attributes){
                                unset($childrenIds[0][$children_id]);
                                $modified = true;
                                $duplicated = true;
                            }
                        }
                    }
                    if (!$duplicated){
                        array_push($all_children_attributes, $children_attributes);    
                    }

                }

                $update_product = $this->load_product_model($update_product_id);

                if ($modified){
                
                    $this->productConfigurableType->saveProducts($update_product, $childrenIds[0]);
                
                }

            }
            
            if (count($childrenIds[0]) == 0){
                $update_product->setTypeId($this->product_type_simple);
                $this->products_collection[$update_product->getEntityId()]['type_id'] = $this->product_type_simple;
                $update_product->save();
            }
        }

    }

    /**
     * Function to check if a configurable product has duplicated formats.
     * @param  string $product_id               product id to check
     * @param  string $format_id                format id id to check
     * @return boolean                          result of check duplicated formats
     */
    private function check_configurable_product_duplicated_formats($product_id, $format_id){

        $this->debbug(" > Checking configurable product ID: $product_id duplicated formats ID: $format_id");
              
        $conf_product = $this->load_product_model($product_id);

        if ($conf_product->getTypeId() == $this->product_type_configurable){
            
            $usedProductAttributeIds = $conf_product->getTypeInstance()->getUsedProductAttributeIds($conf_product);
            
            $childrenIds = $this->productConfigurableType->getChildrenIds($conf_product->getEntityId());
            if (count($childrenIds[0]) > 0 && count($usedProductAttributeIds) > 0){
                
                $form_product = $this->load_format_model($format_id);
                $conf_product_attribute_set_id = $conf_product->getAttributeSetId();
                $this->load_attributes_by_attribute_set_id($conf_product_attribute_set_id);
                if (isset($this->attributes_collection[$conf_product_attribute_set_id]) && !empty($this->attributes_collection[$conf_product_attribute_set_id])){

                    foreach ($usedProductAttributeIds as $usedProductAttributeId) {
                    
                        if (isset($this->attributes_collection[$conf_product_attribute_set_id][$usedProductAttributeId])){

                            $attribute_code = $this->attributes_collection[$conf_product_attribute_set_id][$usedProductAttributeId]['attribute_code'];
                            $form_product_attributes[$attribute_code] = $form_product->getData($attribute_code);

                        }

                    }

                }

                if (!empty($form_product_attributes)){
                    foreach ($childrenIds[0] as $children_id) {
                        if ($children_id == $format_id){ continue; }

                        $form_product_check = $this->load_format_model($children_id);
                        $form_product_check_attributes = array();

                        if (isset($this->attributes_collection[$conf_product_attribute_set_id]) && !empty($this->attributes_collection[$conf_product_attribute_set_id])){

                           foreach ($usedProductAttributeIds as $usedProductAttributeId) {
                                              
                                if (isset($this->attributes_collection[$conf_product_attribute_set_id][$usedProductAttributeId])){

                                  $attribute_code = $this->attributes_collection[$conf_product_attribute_set_id][$usedProductAttributeId]['attribute_code'];

                                    if ($form_product_check->getData($attribute_code) != 0){

                                        $form_product_check_attributes[$attribute_code] = $form_product_check->getData($attribute_code);

                                    }

                                }

                            }
                        
                        }

                        if ($form_product_check_attributes == $form_product_attributes){
                            return false;
                        }
                    }
                }
            }
        }
        return true;
    }

    /**
     * Function to create Sales Layer product.
     * @param  array $product                   product to synchronize
     * @return boolean                          result of product creation
     */
    private function create_product ($product) {

        $categoryIds = $this->findProductCategoryIds($product['catalogue_id']);
        if (empty($categoryIds)){
            $this->debbug('## Error. Product '.$product['data'][$this->product_field_name].' with SL ID '.$product['id'].' has no valid categories.');
            return false;
        }

        $sl_id = $product['id'];
        $sl_data = $product['data'];
        $sl_name = $sl_data[$this->product_field_name];
        $this->debbug(" > Creating product ID: $sl_id (parent IDs: ".print_r($categoryIds,1).')');
        if ($this->sl_DEBBUG > 1) $this->debbug(" Name ({$this->product_field_name}): $sl_name");
       
        $new_product = $this->load_product_model();
        
        $sl_sku = '';
        if ($this->has_product_field_sku){
            $sl_sku = $sl_data[$this->product_field_sku];
            $new_product->setSku($sl_sku);
        }

        //If the product has an attribute_set_id we find the existing by name or id, otherwise we use the default one.
        $sl_attribute_set_id = '';
        if (isset($sl_data[$this->product_field_attribute_set_id])){
            $sl_attribute_set_id_value = '';
            if (is_array($sl_data[$this->product_field_attribute_set_id]) && !empty($sl_data[$this->product_field_attribute_set_id])){
                $sl_attribute_set_id_value = reset($sl_data[$this->product_field_attribute_set_id]);
            }else if (!is_array($sl_data[$this->product_field_attribute_set_id])){
                $sl_attribute_set_id_value = $sl_data[$this->product_field_attribute_set_id];
            }
            if (!is_null($sl_attribute_set_id_value) && $sl_attribute_set_id_value != ''){
                foreach ($this->attribute_set_collection as $attribute_set_collection_id => $attribute_set) {
                    if (is_numeric($sl_attribute_set_id_value)){
                        if ($attribute_set_collection_id == $sl_attribute_set_id_value){
                            $sl_attribute_set_id = $attribute_set_collection_id;
                            break;
                        }
                    }else{
                        if (strtolower($attribute_set['attribute_set_name']) == strtolower($sl_attribute_set_id_value)){
                            $sl_attribute_set_id = $attribute_set_collection_id;
                            break;
                        }
                    }
                }
            }
        }

        if (is_null($sl_attribute_set_id) || $sl_attribute_set_id == ''){
            $sl_attribute_set_id = $this->default_attribute_set_id;
        }

        $sl_description = $sl_data[$this->product_field_description];
        if (isset($sl_data[$this->product_field_description_short]) && $sl_data[$this->product_field_description_short] != ''){
            $sl_description_short = $sl_data[$this->product_field_description_short];
        }else{
            $sl_description_short = $sl_description;
        }

        $sl_description = $this->sl_check_html_text($sl_description);
        $sl_description_short = $this->sl_check_html_text($sl_description_short);

        $url_key = $sl_name;
        if ($sl_sku != ''){ $url_key.= '-'.$sl_sku; }

        $isInStock = $sl_qty = 0;
        $manage_stock = $this->config_manage_stock;
        $use_config_manage_stock = 1;

        if ($this->has_product_field_qty) {

            $sl_qty = $sl_data[$this->product_field_qty];

            if ($sl_qty) {

                $manage_stock = 1;
                $isInStock = 1;
                
            }

            if ($manage_stock !== $this->config_manage_stock){

                $use_config_manage_stock = 0;

            }

        }

        $sl_tax_class_id_value = '';

        if (isset($sl_data[$this->product_field_tax_class_id])){

            $sl_tax_class_id_value = $sl_data[$this->product_field_tax_class_id];

        }

        $sl_tax_class_id_found = $this->findTaxClassId($sl_tax_class_id_value);

        $new_product->setTaxClassId($sl_tax_class_id_found);

        $new_product->setAttributeSetId($sl_attribute_set_id)
                    ->setName($sl_name)
                    ->setUrlKey($url_key)
                    ->setCategoryIds($categoryIds)
                    ->setDescription($sl_description)
                    ->setShortDescription($sl_description_short)
                    ->setStockData(array(
                                        'manage_stock'            => $manage_stock,
                                        'is_in_stock'             => $isInStock,
                                        'qty'                     => $sl_qty,
                                        'use_config_manage_stock' => $use_config_manage_stock))
                    ->setCreatedAt(strtotime('now'))
                    ->setStatus($this->status_enabled)
                    ->setTypeId($this->product_type_simple)
                    ->setVisibility($this->visibility_both);
                    // ->setTaxClassId(0); //tax class (0 - none, 1 - default, 2 - taxable, 4 - shipping);

        $new_product->setSaleslayerId($sl_id);
        $new_product->setSaleslayerCompId($this->comp_id);
        
        if (!empty($this->website_ids)){
            $new_product->setWebsiteIds($this->website_ids);
        }else{
            $new_product->setWebsiteIds(array(1));
        }

        if (!empty($sl_data[$this->product_field_price])) {
            $product_price = $sl_data[$this->product_field_price];
            $new_product->setPrice($product_price);
        }else{
            $new_product->setPrice(0);
        }

        if (isset($sl_data[$this->product_field_meta_title]) && $sl_data[$this->product_field_meta_title] != ''){
            $new_product->setMetaTitle($sl_data[$this->product_field_meta_title]);
        }

        if (isset($sl_data[$this->product_field_meta_keywords]) && $sl_data[$this->product_field_meta_keywords] != ''){
            $new_product->setMetaKeyword($sl_data[$this->product_field_meta_keywords]);
        }

        if (isset($sl_data[$this->product_field_meta_description]) && $sl_data[$this->product_field_meta_description] != ''){
            $new_product->setMetaDescription($sl_data[$this->product_field_meta_description]);
        }

        if (isset($sl_data[$this->product_field_length]) && is_numeric($sl_data[$this->product_field_length])){
            $new_product->setLength($sl_data[$this->product_field_length]);
        }else{
            $new_product->setLength(1);
        }  

        if (isset($sl_data[$this->product_field_width]) && is_numeric($sl_data[$this->product_field_width])){
            $new_product->setWidth($sl_data[$this->product_field_width]);
        }else{
            $new_product->setWidth(1);
        }  

        if (isset($sl_data[$this->product_field_height]) && is_numeric($sl_data[$this->product_field_height])){
            $new_product->setHeight($sl_data[$this->product_field_height]);
        }else{
            $new_product->setHeight(1);
        }  

        if (isset($sl_data[$this->product_field_weight]) && is_numeric($sl_data[$this->product_field_weight])){
            $new_product->setWeight($sl_data[$this->product_field_weight]);
        }else{
            $new_product->setWeight(1);
        }    

        try {
            if ($new_product->save()){
                if ($this->sl_DEBBUG > 1) $this->debbug("Product created!");

                    $product_id = $new_product->getEntityId();

                    $this->products_collection[$product_id] = array('entity_id' => $product_id,
                                                                                'name' => $sl_name,
                                                                                'status' => $this->status_enabled,
                                                                                'sku' => $sl_sku,
                                                                                'type_id' => $this->product_type_simple,
                                                                                'saleslayer_id' => $sl_id,
                                                                                'saleslayer_comp_id' => $this->comp_id,
                                                                                'saleslayer_format_id' => '');
                    $this->products_collection_skus[$sl_sku][$product_id] = $product_id;
                    $this->products_collection_names[$sl_name][$product_id] = $product_id;

                return true;
            }else{
                $this->debbug('## Error. Creating new product.');
                return false;
            }
        } catch (\Exception $e) {
            $this->debbug('## Error. Creating new product: '.$sl_name.' - '.$e->getMessage());
            return false;
        }
    }
    
    /**
     * Function to synchronize Sales Layer product core data(not by store_view_id).
     * @param  array $product                   product to synchronize
     * @return boolean                          result of product data synchronization
     */
    private function sync_product_core_data($product){
        
        $categoryIds = $this->findProductCategoryIds($product['catalogue_id']);
        if (empty($categoryIds)){
            $this->debbug('## Error. Product '.$product['data'][$this->product_field_name].' with SL ID '.$product['id'].' has no valid categories.');
            return false;
        }

        $product_modified = false;

        $sl_id = $product['id'];
        $sl_data = $product['data'];
        $sl_name = $sl_data[$this->product_field_name];
      
        $this->debbug(" > Updating product core data ID: $sl_id (parent IDs: ".print_r($categoryIds,1).')');

        $update_product = $this->findSaleslayerProductId($sl_id, $this->comp_id);
        
        $sl_sku = '';
        if ($this->has_product_field_sku){

            $sl_sku = $sl_data[$this->product_field_sku];

            if ($update_product->getSku() != $sl_sku){
                
                $update_product->setSku($sl_sku);
                $product_modified = true;

                $old_sl_sku = $this->products_collection[$update_product->getEntityId()]['sku'];
                if ($old_sl_sku != $sl_sku){
                    unset($this->products_collection_skus[$old_sl_sku][$update_product->getEntityId()]);
                    if (count($this->products_collection_skus[$old_sl_sku]) == 0 || empty($this->products_collection_skus[$old_sl_sku])){
                        unset($this->products_collection_skus[$old_sl_sku]);
                    }
                    $this->products_collection_skus[$sl_sku][$update_product->getEntityId()] = $update_product->getEntityId();
                }

                $this->products_collection[$update_product->getEntityId()]['sku'] = $sl_sku;

            }

        }

        if ($update_product->getName() != $sl_name){

            $update_product->setName($sl_name);
            $url_key = $sl_name;
            if ($sl_sku != ''){ $url_key.= '-'.$sl_sku; }
            $update_product->setUrlKey($url_key);
            $product_modified = true;
            
            if ($this->products_collection[$update_product->getEntityId()]['name'] != $sl_name){

                $old_sl_name = $this->products_collection[$update_product->getEntityId()]['name'];
                unset($this->products_collection_names[$old_sl_name][$update_product->getEntityId()]);
                if (count($this->products_collection_names[$old_sl_name]) == 0 || empty($this->products_collection_names[$old_sl_name])){
                    unset($this->products_collection_names[$old_sl_name]);
                }
                $this->products_collection_names[$sl_name][$update_product->getEntityId()] = $update_product->getEntityId();
                $this->products_collection[$update_product->getEntityId()]['name'] = $sl_name;  
                
            }

        }

        if (isset($sl_data[$this->product_field_status])){

            $product_status = $sl_data[$this->product_field_status];
            $product_status_bool = $this->sl_validate_status_value($product_status);

            if (!$product_status_bool && $update_product->getStatus() != $this->status_disabled){

                $update_product->setStatus($this->status_disabled);
                $product_modified = true;

            }else if ($product_status_bool && $update_product->getStatus() != $this->status_enabled){

                $update_product->setStatus($this->status_enabled);
                $product_modified = true;

            }

        }else{

            if ($update_product->getStatus() != $this->status_enabled){

                $update_product->setStatus($this->status_enabled);
                $product_modified = true;

            }

        }

        $isInStock = $sl_qty = 0;
        $manage_stock = $this->config_manage_stock;
        $use_config_manage_stock = 1;

        if ($this->avoid_stock_update == '0'){

            if ($this->has_product_field_qty) {

                $sl_qty = $sl_data[$this->product_field_qty];

                if ($sl_qty) {

                    $manage_stock = 1;
                    $isInStock = 1;

                }

                if ($manage_stock !== $this->config_manage_stock){

                    $use_config_manage_stock = 0;

                }

                if ($update_product->getExtensionAttributes()->getStockItem()->getQty() != $sl_qty){

                    $update_product->setStockData(array(
                                                    'manage_stock'            => $manage_stock,
                                                    'is_in_stock'             => $isInStock,
                                                    'qty'                     => $sl_qty,
                                                    'use_config_manage_stock' => $use_config_manage_stock));
                    
                    $product_modified = true;

                }

            }

        }

        //If the product has an attribute_set_id we find the existing by name or id, otherwise we use the default one.
        $sl_attribute_set_id = '';
        if (isset($sl_data[$this->product_field_attribute_set_id])){
            $sl_attribute_set_id_value = '';
            if (is_array($sl_data[$this->product_field_attribute_set_id]) && !empty($sl_data[$this->product_field_attribute_set_id])){
                $sl_attribute_set_id_value = reset($sl_data[$this->product_field_attribute_set_id]);
            }else if (!is_array($sl_data[$this->product_field_attribute_set_id])){
                $sl_attribute_set_id_value = $sl_data[$this->product_field_attribute_set_id];
            }
            if (!is_null($sl_attribute_set_id_value) && $sl_attribute_set_id_value != ''){
                foreach ($this->attribute_set_collection as $attribute_set_collection_id => $attribute_set) {
                    if (is_numeric($sl_attribute_set_id_value)){
                        if ($attribute_set_collection_id == $sl_attribute_set_id_value){
                            $sl_attribute_set_id = $attribute_set_collection_id;
                            break;
                        }
                    }else{
                        if (strtolower($attribute_set['attribute_set_name']) == strtolower($sl_attribute_set_id_value)){
                            $sl_attribute_set_id = $attribute_set_collection_id;
                            break;
                        }
                    }
                }
            }
        }

        if (is_null($sl_attribute_set_id) || $sl_attribute_set_id == ''){

            $sl_attribute_set_id = $this->default_attribute_set_id;

        }

        if ($update_product->getAttributeSetId() != $sl_attribute_set_id){

            $update_product ->setAttributeSetId($sl_attribute_set_id);
            $product_modified = true;

        }
                        
        if (!empty($sl_data[$this->product_field_price])){

            $product_price = $sl_data[$this->product_field_price];
            if ($update_product->getPrice() != $product_price){

                $update_product->setPrice($product_price);
                $product_modified = true;
            
            }

        }else if (!$update_product->getPrice()){

            $update_product->setPrice(0);
            $product_modified = true;

        }

        $website_ids = array(1);
        if (!empty($this->website_ids)){ $website_ids = $this->website_ids; }
        if ($update_product->getWebsiteIds() != $website_ids){
            $website_ids = array_unique(array_merge($update_product->getWebsiteIds(), $website_ids));
            $update_product->setWebsiteIds($website_ids);
            $product_modified = true;            

        } 

        if (isset($sl_data[$this->product_field_length]) && is_numeric($sl_data[$this->product_field_length])){

            if ($update_product->getLength() != $sl_data[$this->product_field_length]){

                $update_product->setLength($sl_data[$this->product_field_length]);
                $product_modified = true;
                
            }

        }

        if (isset($sl_data[$this->product_field_width]) && is_numeric($sl_data[$this->product_field_width])){

            if ($update_product->getWidth() != $sl_data[$this->product_field_width]){

                $update_product->setWidth($sl_data[$this->product_field_width]);
                $product_modified = true;
                
            }

        }


        if (isset($sl_data[$this->product_field_height]) && is_numeric($sl_data[$this->product_field_height])){

            if ($update_product->getHeight() != $sl_data[$this->product_field_height]){

                $update_product->setHeight($sl_data[$this->product_field_height]);
                $product_modified = true;
                
            }

        }


        if (isset($sl_data[$this->product_field_weight]) && is_numeric($sl_data[$this->product_field_weight])){

            if ($update_product->getWeight() != $sl_data[$this->product_field_weight]){

                $update_product->setWeight($sl_data[$this->product_field_weight]);
                $product_modified = true;
                
            }

        }

        $array_grouping_product = preg_grep('/grouping_product_reference_\+?\d+$/', array_keys($sl_data));

        $processed_grouping_ids = array();

        $linked_product_data = array();

        if (!empty($array_grouping_product)){

            foreach ($array_grouping_product as $grouping_product) {
                
                $grouping_id = str_replace('grouping_product_reference_', '', $grouping_product);
                $grouping_quantity = 0;
                $grouping_product_ref = '';

                if (is_array($sl_data[$grouping_product]) && !empty($sl_data[$grouping_product])){
                    
                    $grouping_product_ref = reset($sl_data[$grouping_product]);

                }else if (!is_array($sl_data[$grouping_product]) && $sl_data[$grouping_product] != ''){

                    if (strpos($sl_data[$grouping_product], ',')){
                    
                        $grouping_field_data = explode(',', $sl_data[$grouping_product]);
                        $grouping_product_ref = $grouping_field_data[0];

                    }else{
                    
                        $grouping_product_ref = $sl_data[$grouping_product];
                    
                    }

                }

                if (isset($processed_grouping_ids[$grouping_id]) || $grouping_product_ref == ''){ 
                    
                    continue;

                }else{

                    if ($grouping_product_ref == $update_product->getSku()){

                        $this->debbug('## Error. Product reference '.$grouping_product_ref.' is the same as the current product: '.$update_product->getSku());
                        continue;

                    }

                    if (isset($sl_data['grouping_product_quantity_'.$grouping_id]) && is_numeric($sl_data['grouping_product_quantity_'.$grouping_id])){

                        $grouping_quantity = $sl_data['grouping_product_quantity_'.$grouping_id];

                    }

                    $linked_product_data[$update_product->getEntityId()][] = array('linked_type' => 'associated', 'linked_reference' => $grouping_product_ref, 'linked_qty' => $grouping_quantity);
                    $processed_grouping_ids[$grouping_id] = 0;

                }

            }

        }else{

            if ($update_product->getTypeId() == $this->product_type_grouped){

                $this->clean_associated_product($update_product->getEntityId());

            }

        }

        $linked_fields = array($this->product_field_related_references => 'related', $this->product_field_upsell_references => 'upsell', $this->product_field_crosssell_references => 'crosssell');
        
        foreach ($linked_fields as $field_sales => $linked_type) {

            if (isset($sl_data[$field_sales])){

                $linked_references = array();

                if (is_array($sl_data[$field_sales]) && !empty($sl_data[$field_sales])){
                    
                    $linked_references = $sl_data[$field_sales];

                }else if (!is_array($sl_data[$field_sales]) && $sl_data[$field_sales] != ''){

                    if (strpos($sl_data[$field_sales], ',')){
                    
                        $linked_references = explode(',', $sl_data[$field_sales]);

                    }else{
                    
                        $linked_references = array($sl_data[$field_sales]);
                    
                    }

                }

                foreach ($linked_references as $linked_reference) {
                    
                    $linked_product_data[$update_product->getEntityId()][] = array('linked_type' => $linked_type, 'linked_reference' => $linked_reference);

                }

            }

        }

        if (!empty($linked_product_data)){

            $sql_query_to_insert = " INSERT INTO ".$this->saleslayer_syncdata_table.
                                    " ( sync_type, item_type, item_data, sync_params ) VALUES ".
                                    " ('update', 'product_links', '".json_encode($linked_product_data)."', '')";

            $this->sl_connection_query($sql_query_to_insert);

        }

        $conn_insert = true;

        if (isset($this->sl_multiconn_table_data['product'][$sl_id]) && !empty($this->sl_multiconn_table_data['product'][$sl_id]['sl_connectors'])){

            $conn_found = array_search($this->processing_connector_id, $this->sl_multiconn_table_data['product'][$sl_id]['sl_connectors']);

            if (!is_numeric($conn_found)){

                $this->sl_multiconn_table_data['product'][$sl_id]['sl_connectors'][] = $this->processing_connector_id;

                $new_connectors_data = json_encode($this->sl_multiconn_table_data['product'][$sl_id]['sl_connectors']);

                $query_update  = " UPDATE ".$this->saleslayer_multiconn_table." SET sl_connectors = ?  WHERE id = ? ";

                // $this->execute_multiconn_sql($query_update,array($new_connectors_data,$this->sl_multiconn_table_data['product'][$sl_id]['id']));

                $this->sl_connection_query($query_update,array($new_connectors_data,$this->sl_multiconn_table_data['product'][$sl_id]['id']));

            }

            $conn_insert = false;

        }

        if ($conn_insert){

            $connectors_data = json_encode(array($this->processing_connector_id));

            $query_insert = " INSERT INTO ".$this->saleslayer_multiconn_table."(`item_type`,`sl_id`,`sl_comp_id`,`sl_connectors`) values (?,?,?,?);";

            // $this->execute_multiconn_sql($query_insert,array('product', $sl_id, $this->comp_id, $connectors_data));

            $this->sl_connection_query($query_insert,array('product', $sl_id, $this->comp_id, $connectors_data));

        }

        if ($product_modified){

            try {

                $update_product->save();
                if ($this->sl_DEBBUG > 1) $this->debbug("Updated product core data!");
            
            } catch (\Exception $e) {

                $this->debbug('## Error. Updating product '.$sl_name.' core data: '.$e->getMessage());
                return false;
            }

        }

        if ($update_product->getCategoryIds() != $categoryIds){

            // $error_assign = false;
            
            // try {
                
            //     $this->categoryLinkManagementInterface->assignProductToCategories($update_product->getSku(), $categoryIds);
            
            // }catch (\Exception $e) {

            //     $this->debbug("## Error. Updating product categories automatically: ".$e->getMessage());
            //     $error_assign = true;

            // }

            // if ($error_assign){

                $excess_categories = array_diff($update_product->getCategoryIds(), $categoryIds);
                $missing_categories = array_diff($categoryIds, $update_product->getCategoryIds());

                $error_cat = false;

                if (!empty($excess_categories)){
                    
                    try{

                        $query_delete = " DELETE FROM ".$this->catalog_category_product_table." WHERE product_id = ".$update_product->getEntityId()." AND category_id IN (".implode(',', array_values($excess_categories)).")";

                        $this->sl_connection_query($query_delete);
                    
                    }catch(\Exception $e){
                    
                        $error_cat = true;
                        $this->debbug('## Error. Removing product categories manually: '.$e->getMessage());

                    }
                    
                }

                if (!empty($missing_categories)){

                    foreach ($missing_categories as $missing_category) {
                        
                        try{
     
                            $query_insert = " INSERT INTO ".$this->catalog_category_product_table."(`category_id`,`product_id`,`position`) values ( ? , ? , ? );";

                            $this->sl_connection_query($query_insert, array($missing_category, $update_product->getEntityId(), 1));
                            
                        }catch(\Exception $e){
                            
                            $error_cat = true;
                            $this->debbug('## Error. Adding product categories manually: '.$e->getMessage());
                            
                        }

                    }
                    
                }

                if ($error_cat){

                    return false;
                    
                }

            // }

        }

        return true;

    }

    /**
     * Function to synchronize Sales Layer product data.
     * @param  array $product                   product to synchronize
     * @param  string $store_view_id            store view id to synchronize 
     * @return boolean                          result of product data synchronization
     */
    private function sync_product_data ($product, $store_view_id = null) {

        $product_modified = false;

        $sl_id = $product['id'];
        $sl_data = $product['data'];
        $sl_name = $sl_data[$this->product_field_name];
        
        $this->debbug(" > Updating product data ID: $sl_id In store view id: ".$store_view_id);
        if ($this->sl_DEBBUG > 1) $this->debbug("Name ({$this->product_field_name}): $sl_name");
        
        $update_product = $this->findSaleslayerProductId($sl_id, $this->comp_id, false, $store_view_id);
        
        $sl_description = $sl_data[$this->product_field_description];
        if (isset($sl_data[$this->product_field_description_short]) && $sl_data[$this->product_field_description_short] != ''){
            $sl_description_short = $sl_data[$this->product_field_description_short];
        }else{
            $sl_description_short = $sl_description;
        }

        if ($update_product->getName() != $sl_name){

            $url_key = $sl_name.'-'.$update_product->getSku();
            $update_product->setName($sl_name)
                           ->setUrlKey($url_key);
            $product_modified = true;

        }

        $sl_description = $this->sl_check_html_text($sl_description);
        if ($update_product->getDescription() != $sl_description){

            $update_product->setDescription($sl_description);
            $product_modified = true;

        }

        $sl_description_short = $this->sl_check_html_text($sl_description_short);
        if ($update_product->getShortDescription() != $sl_description_short){

            $update_product->setShortDescription($sl_description_short);
            $product_modified = true;
            
        }

        if (isset($sl_data[$this->product_field_meta_title]) && $sl_data[$this->product_field_meta_title] != ''){

            if ($update_product->getMetaTitle() != $sl_data[$this->product_field_meta_title]){

                $update_product->setMetaTitle($sl_data[$this->product_field_meta_title]);
                $product_modified = true;

            }
        }

        if (isset($sl_data[$this->product_field_meta_keywords]) && $sl_data[$this->product_field_meta_keywords] != ''){

            if ($update_product->getMetaKeyword() != $sl_data[$this->product_field_meta_keywords]){

                $update_product->setMetaKeyword($sl_data[$this->product_field_meta_keywords]);
                $product_modified = true;

            }

        }

        if (isset($sl_data[$this->product_field_meta_description]) && $sl_data[$this->product_field_meta_description] != ''){

            if ($update_product->getMetaDescription() != $sl_data[$this->product_field_meta_description]){

                $update_product->setMetaDescription($sl_data[$this->product_field_meta_description]);
                $product_modified = true;

            }

        }

        if (isset($sl_data[$this->product_field_tax_class_id]) && $sl_data[$this->product_field_tax_class_id] != ''){

            $sl_tax_class_id_found = $this->findTaxClassId($sl_data[$this->product_field_tax_class_id]);
            
            if ($update_product->getTaxClassId() != $sl_tax_class_id_found){

                $update_product->setTaxClassId($sl_tax_class_id_found);
                $product_modified = true;

            }

        }

        if (!$this->product_enabled_attribute_is_global){

            if (isset($sl_data[$this->product_field_status])){
        
                $product_status = $sl_data[$this->product_field_status];
                $product_status_bool = $this->sl_validate_status_value($product_status);
                
                if (!$product_status_bool && $update_product->getStatus() != $this->status_disabled){

                    $update_product->setStatus($this->status_disabled);
                    $product_modified = true;

                }else if ($product_status_bool && $update_product->getStatus() != $this->status_enabled){

                    $update_product->setStatus($this->status_enabled);
                    $product_modified = true;

                }
            
            }else{

                if ($update_product->getStatus() != $this->status_enabled){

                    $update_product->setStatus($this->status_enabled);
                    $product_modified = true;

                }

            }

        }

        $sl_attribute_set_id = $update_product->getAttributeSetId();
        $this->load_attributes_by_attribute_set_id($sl_attribute_set_id);
        
        if (count($this->product_additional_fields) > 0) {
            
            foreach($this->product_additional_fields as $field_name => $field_name_value) {
                
                $attribute_id = $attribute_type = '';

                if (isset($this->attributes_collection[$sl_attribute_set_id])){

                    foreach ($this->attributes_collection[$sl_attribute_set_id] as $attribute_col) {
                        
                        if ($attribute_col['attribute_code'] == $field_name){

                            $attribute_id = $attribute_col['attribute_id'];
                            $attribute_type = $attribute_col['frontend_input'];
                            break;

                        }

                    }

                }

                if (!$attribute_id){

                    continue;

                }
                
                if (!isset($sl_data[$field_name_value])){

                    continue;

                }else if (isset($sl_data[$field_name_value]) && ((is_array($sl_data[$field_name_value]) && empty($sl_data[$field_name_value])) || (!is_array($sl_data[$field_name_value]) && $sl_data[$field_name_value] == ''))){

                    if ($attribute_type != 'media_image'){

                        if ($update_product->getData($field_name) != ''){
                
                            $update_product->setData($field_name, '');
                            $product_modified = true;

                        }

                    }

                    continue;

                }

                switch ($attribute_type) {
                    case 'media_image':
                        if (!isset($this->product_additional_fields_images[$sl_id][$field_name_value])){
                            
                            $media = $this->get_media_field_value('products', $field_name_value, $sl_data[$field_name_value]);
                            if ($media){

                                $this->product_additional_fields_images[$sl_id][$field_name_value] = $media;

                            }

                        }

                        break;
                    case 'multiselect':
                        $value_to_update = $sl_options = '';

                        (is_array($sl_data[$field_name_value])) ? $sl_options = $sl_data[$field_name_value] : $sl_options = array($sl_data[$field_name_value]);

                        foreach ($sl_options as $additional_field_value) {

                            $value_found = $this->find_attribute_option_value($sl_attribute_set_id, $attribute_id, $additional_field_value, $store_view_id);
                           
                            if ($value_found){

                                if ($value_to_update == ''){

                                    $value_to_update = $value_found;

                                }else{

                                    $value_to_update .= ','.$value_found;

                                }

                            }

                        }

                        if ($value_to_update != ''){

                            if ($update_product->getData($field_name) != $value_to_update){
                        
                                $update_product->setData($field_name, $value_to_update);
                                $product_modified = true;

                            }

                        }

                        break;
                    case 'select':
                        $additional_field_value = '';
                        if (is_array($sl_data[$field_name_value])){
                            $additional_field_value = $sl_data[$field_name_value][0];
                        }else{
                            $additional_field_value = $sl_data[$field_name_value];
                        }

                        $attribute_value_id = $this->find_attribute_option_value($sl_attribute_set_id, $attribute_id, $additional_field_value, $store_view_id);
                                                
                        if ($attribute_value_id){

                            if ($update_product->getData($field_name) != $attribute_value_id){
                            
                                $update_product->setData($field_name, $attribute_value_id);
                                $product_modified = true;
                            
                            }
                        
                        }

                        break;
                    case 'price':
                        $additional_field_value = '';
                        if (is_array($sl_data[$field_name_value])){
                            $additional_field_value = $sl_data[$field_name_value][0];
                        }else{
                            $additional_field_value = $sl_data[$field_name_value];
                        }
                        
                        if (!is_numeric($additional_field_value) && filter_var($additional_field_value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)){
                            $value_to_update = filter_var($additional_field_value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                        }else{
                            $value_to_update = $additional_field_value;
                        }
                        
                        if ($update_product->getData($field_name) != $value_to_update){
                        
                            $update_product->setData($field_name, $value_to_update);
                            $product_modified = true;
                        
                        }

                        break;
                    case 'boolean':
                        $additional_field_value = '';
                        if (is_array($sl_data[$field_name_value])){
                            $additional_field_value = $sl_data[$field_name_value][0];
                        }else{
                            $additional_field_value = $sl_data[$field_name_value];
                        }

                        $value_to_update = filter_var($additional_field_value, FILTER_VALIDATE_BOOLEAN);

                        if ($update_product->getData($field_name) != $value_to_update){
                        
                            $update_product->setData($field_name, $value_to_update);
                            $product_modified = true;
                        
                        }

                        break;
                    case 'date':
                        $additional_field_value = '';
                        if (is_array($sl_data[$field_name_value])){
                            $additional_field_value = $sl_data[$field_name_value][0];
                        }else{
                            $additional_field_value = $sl_data[$field_name_value];
                        }

                        if ($update_product->getData($field_name) != $additional_field_value){
                        
                            $update_product->setData($field_name, $additional_field_value);
                            $product_modified = true;
                            
                        }
                        
                        break;
                    case 'weee':
                        break;
                    default:
                        $additional_field_value = '';
                        if (is_array($sl_data[$field_name_value])){
                            $additional_field_value = implode(', ', array_filter($sl_data[$field_name_value], array($this, 'array_filter_empty_value')));
                        }else{
                            $additional_field_value = $sl_data[$field_name_value];
                        }

                        $additional_field_value = $this->sl_check_html_text($additional_field_value);
                        if ($update_product->getData($field_name) !== $additional_field_value){
                        
                            $update_product->setData($field_name, $additional_field_value);
                            $product_modified = true;
                            
                        }

                        break;
                }
            }
        }

        if ($product_modified){

            try {
                if ($update_product->save()){
                    if ($this->sl_DEBBUG > 1) $this->debbug("Updated product data!");
                    return true;
                }else{
                    $this->debbug("## Error. Updating product ".$sl_name." data.");
                    return false;
                }
            } catch (\Exception $e) {
                $this->debbug("## Error. Updating product ".$sl_name." data: ".$e->getMessage());
                return false;
            }

        }else{

            if ($this->sl_DEBBUG > 1) $this->debbug("Updated product data!");
            return true;

        }
    }

    /**
     * Function to load tax classes collection into a class variable.
     * @return void
     */
    private function loadTaxClassesCollection(){

        if (!$this->tax_class_collection_loaded){

            $tax_class_collection = $this->tax_class_model->getCollection();
            
            foreach ($tax_class_collection as $tax_class_model) {

                $this->tax_class_collection[$tax_class_model->getClassId()] = array('class_id' => $tax_class_model->getClassId(),
                                                                                'class_name' => $tax_class_model->getClassName());

            }

            $this->tax_class_collection_loaded = true;
            
        }

    }

    /**
     * Function to find tax class id by Sales Layer value.
     * @param  int|string $sl_tax_class_id_value            Sales Layer tax class id or name value
     * @return int $sl_tax_class_id_found                   If found, tax class id, if not, default tax class id
     */
    private function findTaxClassId($sl_tax_class_id_value = ''){

        $sl_tax_class_id_found = '';
        
        if (!is_null($sl_tax_class_id_value) && $sl_tax_class_id_value != ''){

            $this->loadTaxClassesCollection();

            foreach ($this->tax_class_collection as $tax_id => $tax) {
            
                if (is_numeric($sl_tax_class_id_value)){
            
                    if ($tax_id == $sl_tax_class_id_value){
            
                        $sl_tax_class_id_found = $tax_id;
                        break;
            
                    }
            
                }else{
            
                    if (strtolower($tax['class_name']) == strtolower($sl_tax_class_id_value)){
            
                        $sl_tax_class_id_found = $tax_id;
                        break;
            
                    }
            
                }
            
            }
        
        }

        if (is_null($sl_tax_class_id_found) || $sl_tax_class_id_found == ''){

            $sl_tax_class_id_found = $this->config_default_product_tax_class;

        }

        return $sl_tax_class_id_found;

    }

    /**
     * Function to prepare product images to store.
     * @param  array $product                   product data
     * @return string                           product images to store
     */
    private function prepare_product_images_to_store($product){

        $product_modified = false;

        $sl_id = $product['id'];

        $this->debbug(" > Storing product images SL ID: $sl_id");
        
        $update_product = $this->findSaleslayerProductId($sl_id, $this->comp_id);

        // $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        // $time_ini_clear_cache = microtime(1);
        // try{
        //     $image_model = $objectManager->create('Magento\Catalog\Model\Product\Image');
        //     $image_model->clearCache();
        // }catch(\Exception $e){
        //     $this->debbug('## Error deleting image cache: '.$e->getMessage());
        // }
        // $this->debbug('# time_clear_cache: '.(microtime(1) - $time_ini_clear_cache).' seconds.', 'timer');
        
        $sl_product_images = $final_images = $existing_images = array();
        if (isset($product['data'][$this->product_field_image]) && !empty($product['data'][$this->product_field_image])){
            $sl_product_images = $product['data'][$this->product_field_image];
        }

        $time_ini_load_sl_images = microtime(1);
        if (!empty($sl_product_images)){
        
            $main_image = $small_image = $thumbnail = false;

            foreach ($sl_product_images as $image_key => $images) {

                foreach ($this->product_images_sizes as $img_format) {
                    
                    if (!empty($images[$img_format])){

                        $media_attribute = '';

                        $image_url = $images[$img_format];
                        
                        $image_url_info = pathinfo($image_url);
                        $image_filename = $image_url_info['filename'].'.'.$image_url_info['extension'];

                        $disabled = 0;

                        if (!$main_image && '_'.$img_format == $this->image_extension){
                            $main_image = true;
                            $media_attribute = array('image');
                        }

                        if ('_'.$img_format == $this->small_image_extension){

                            if (!$small_image){

                                $small_image = true;

                                if (is_array($media_attribute)){

                                    $media_attribute[] = 'small_image';

                                }else{

                                    $media_attribute = array('small_image');

                                }

                            }

                            if ($this->image_extension != $this->small_image_extension){

                                $disabled = 1;

                            }

                        }

                        if ('_'.$img_format == $this->thumbnail_extension){

                            if (!$thumbnail){

                                $thumbnail = 1;

                                if (is_array($media_attribute)){

                                    $media_attribute[] = 'thumbnail';

                                }else{

                                    $media_attribute = array('thumbnail');

                                }

                            }

                            if ($this->image_extension != $this->thumbnail_extension){

                                $disabled = 1;

                            }

                        }

                        $final_images[$image_filename] = array('url' => $image_url, 'media_attribute' => $media_attribute, 'disabled' => $disabled);

                    }

                }

            }

        }

        $this->debbug('# time_load_sl_images: '.(microtime(1) - $time_ini_load_sl_images).' seconds.', 'timer');
        
        $time_load_additional_images = microtime(1);
        if (isset($this->product_additional_fields_images[$sl_id]) && !empty($this->product_additional_fields_images[$sl_id])){

            foreach ($this->product_additional_fields_images[$sl_id] as $field_name_value => $media){
                
                foreach ($media as $media_image) {

                    $media_info = pathinfo($media_image);
                    $media_image_filename = $media_info['filename'].'.'.$media_info['extension'];

                    $final_images[$media_image_filename] = array('url' => $media_image, 'media_attribute' => array($field_name_value), 'disabled' => 0);
                }

                unset($this->product_additional_fields_images[$sl_id][$field_name_value]);
                if (empty($this->product_additional_fields_images[$sl_id])){
                    unset($this->product_additional_fields_images[$sl_id]);
                }

            }

        }
        
        $this->debbug('# time_load_additional_images: '.(microtime(1) - $time_load_additional_images).' seconds.', 'timer');
        
        $main_image_to_process = array();
        $main_image_processed = true;
        
        foreach ($final_images as $keyIMG => $final_image) {
        
            if (is_array($final_image['media_attribute']) && in_array('image', $final_image['media_attribute'])){
                $main_image_to_process = $final_image;
                $main_image_to_process['image_name'] = $keyIMG;
                $main_image_processed = false;
                break;
            }

        }


        $time_ini_check_existing = microtime(1);
        $existing_items = $update_product->getMediaGalleryEntries();
        $items_modified = false;
        $existing_images_to_modify = array('delete' => array(), 'update' => array());
        
        if (!empty($existing_items)){
            
            foreach ($existing_items as $keyItem => $item) {

                $time_ini_item_check = microtime(1);
                $item_data = $item->getData();
                if (!is_array($item_data['types'])){ $item_data['types'] = array(); }
                if (!empty($item_data['types'])){ asort($item_data['types']); }
                $this->debbug('# time_item_get_data: '.(microtime(1) - $time_ini_item_check).' seconds.', 'timer');
            
                $time_ini_item_parse = microtime(1);
                $parse_url_item = pathinfo($item_data['file']);
                $item_url = $this->product_path_base.$item_data['file'];
                $this->debbug('# time_item_parse: '.(microtime(1) - $time_ini_item_parse).' seconds.', 'timer');
                
                $time_ini_item_md5 = microtime(1);
                $md5_item = $this->verify_md5_image_url($item_url);
                $this->debbug('# time_item_md5: '.(microtime(1) - $time_ini_item_md5).' seconds.', 'timer');
                $item_filename = $parse_url_item['filename'].'.'.$parse_url_item['extension'];
        
                if ($md5_item){ 
                    
                    if (isset($final_images[$item_filename])){
        
                        $time_ini_image_md5 = microtime(1);
                        $md5_image = $this->verify_md5_image_url($final_images[$item_filename]['url']);
                        $this->debbug('# time_image_md5: '.(microtime(1) - $time_ini_image_md5).' seconds.', 'timer');
                        
                        if ($md5_image && $md5_image == $md5_item){
        
                            $image_media_attribute = $final_images[$item_filename]['media_attribute'];
                            if (!is_array($image_media_attribute)){ 
                                if ($image_media_attribute == ''){
                                    $image_media_attribute = array();
                                }else{
                                    $image_media_attribute = array($image_media_attribute); 
                                }
                            }
                            if (!empty($image_media_attribute)){ asort($image_media_attribute); }
        
                            $time_ini_mod_item = microtime(1);

                            if ($item_data['types'] != $image_media_attribute){

                                if (!$main_image_processed && $main_image_to_process['image_name'] == $item_filename){

                                    $this->galleryProcessor->removeImage($update_product, $item_data['file']);
                                    $items_modified = true;


                                }else{

                                    $existing_images_to_modify['delete'][] = $item_data['id'];

                                }
                                
                                $this->debbug('# time_item_check: '.(microtime(1) - $time_ini_item_check).' seconds.', 'timer');
                                continue;

                            }

                            if ($item_data['disabled'] != $final_images[$item_filename]['disabled']){

                                if (!$main_image_processed && $main_image_to_process['image_name'] == $item_filename){
                                    
                                    $this->galleryProcessor->updateImage($update_product, $item_data['file'], array('disabled' => $final_images[$item_filename]['disabled']));
                                    $main_image_processed = true;
                                    $main_image_to_process = array();
                                    $items_modified = true;

                                }else{

                                    $existing_images_to_modify['update'][$item_data['id']]['disabled'] = $final_images[$item_filename]['disabled'];

                                }

                            }

                            if (!$main_image_processed && $main_image_to_process['image_name'] == $item_filename){
                                
                                $main_image_processed = true;

                            }

                            $this->debbug('# time_item_check: '.(microtime(1) - $time_ini_item_check).' seconds.', 'timer');
                            $this->debbug('# time_mod_item: '.(microtime(1) - $time_ini_mod_item).' seconds.', 'timer');
                            unset($final_images[$item_filename]);
                            continue;

                        }

                    }

                }
                
                $existing_images_to_modify['delete'][] = $item_data['id'];
                $items_modified = true;
                $this->debbug('# time_item_check: '.(microtime(1) - $time_ini_item_check).' seconds.', 'timer');

            }

        }
        
        $this->debbug('# time_check_existing: '.(microtime(1) - $time_ini_check_existing).' seconds.', 'timer');
        
        if ($items_modified){
        
            try{
            
                $update_product->save();
                $update_product = $this->load_product_model($update_product->getEntityId());
            
            }catch(\Exception $e){
            
                $this->debbug('## Error. Updating product modified items: '.$e->getMessage());
            
            }

        }

        if (!$main_image_processed){

            $image_filename = $main_image_to_process['image_name'];
            $time_ini_check_waste = microtime(1);
            $check_waste = $this->product_path_base.substr($image_filename, 0,1).'/'.substr($image_filename, 1,1).'/'.$image_filename;
            if (file_exists($check_waste)){ unlink($check_waste); }
            $this->debbug('# time_check_waste: '.(microtime(1) - $time_ini_check_waste).' seconds.', 'timer');

            $time_ini_prepare_image = microtime(1);
            $this->fileIo->checkAndCreateFolder($this->product_tmp_path_base.substr($image_filename, 0,1).'/'.substr($image_filename, 1,1).'/');
            // $new_file_name = $this->product_path_base.baseName($main_image_to_process['url']);
            $new_file_name = $this->product_tmp_path_base.substr($image_filename, 0,1).'/'.substr($image_filename, 1,1).'/'.$image_filename;
            $result = $this->fileIo->read($main_image_to_process['url'], $new_file_name);
            $exclude = $main_image_to_process['disabled'];
            
            if ($result) {
            
                try{

                    $update_product->addImageToMediaGallery($new_file_name, $main_image_to_process['media_attribute'], true, $exclude);
               
                } catch (\Exception $e) {
                               
                    $this->debbug('## Error. Adding main product image: '.$e->getMessage());
               
                }

            }

            $this->debbug('# time_prepare_image: '.(microtime(1) - $time_ini_prepare_image).' seconds.', 'timer');

            $time_ini_save_product_images = microtime(1);
            
            try{
            
                $update_product->save();
                unset($final_images[$main_image_to_process['image_name']]);
                $main_image_to_process = array();
            
            } catch (\Exception $e) {
            
                $this->debbug('## Error. Updating product main image: '.$e->getMessage());
            
            }

            $this->debbug('# time_save_product_images: '.(microtime(1) - $time_ini_save_product_images).' seconds.', 'timer');

        }
        
        if (empty($existing_images_to_modify['delete']) && empty($existing_images_to_modify['update'])){

            $existing_images_to_modify = array();

        }

        if (!empty($final_images) || !empty($existing_images_to_modify)){

            $images_data['product_id'] = $update_product->getEntityId();

            if (!empty($final_images)){
                $images_data['final_images'] = $final_images;   
            }

            if (!empty($existing_images_to_modify)){
                $images_data['existing_images_to_modify'] = $existing_images_to_modify;   
            }

            try{

                $sql_query_to_insert = " INSERT INTO ".$this->saleslayer_syncdata_table.
                                                 " ( sync_type, item_type, item_data, sync_params ) VALUES ".
                                                 "('update', 'product__images', '".json_encode($images_data)."', '')";

                $this->connection->query($sql_query_to_insert);

            }catch(\Exception $e){

                $this->debbug('## Error. Insert syncdata SQL query: '.$sql_query_to_insert);
                $this->debbug('## Error. Insert syncdata SQL message: '.$e->getMessage());

            }

        }

    }

    /**
    * Function to synchronize Sales Layer stored product images.
    * @param  array $images_data            product to synchronize
    * @param  string $item_index            index of product or format
    * @return void
    */
    protected function sync_stored_product_images($images_data, $item_index){

        if ($item_index == 'product'){

            $item_id = $images_data['product_id'];

        }else{
            
            $item_id = $images_data['format_id'];
        
        }

        $this->debbug(" > Updating stored ".$item_index." images ID: ".$item_id);
        
        if ($item_index == 'format'){

            $update_product = $this->load_format_model($item_id);

        }else{

            $update_product = $this->load_product_model($item_id);

        }

        $existing_links = $this->productLinkRepositoryInterface->getList($update_product);
        
        if (isset($images_data['existing_images_to_modify']) && !empty($images_data['existing_images_to_modify'])){
        
            $time_ini_mod_all_items = microtime(1);
            $existing_items = $update_product->getMediaGalleryEntries();
            $items_modified = false;

            if (!empty($existing_items)){

                foreach ($existing_items as $keyItem => $item) {
                    
                    $time_ini_item_get_data = microtime(1);
                    $item_data = $item->getData();
                    $this->debbug('# time_item_get_data: '.(microtime(1) - $time_ini_item_get_data).' seconds.', 'timer');

                    $time_ini_mod_item = microtime(1);

                    if (in_array($item_data['id'], $images_data['existing_images_to_modify']['delete'])){

                        $this->galleryProcessor->removeImage($update_product, $item_data['file']);
                        $items_modified = true;

                    }

                    if (in_array($item_data['id'], $images_data['existing_images_to_modify']['update'])){

                        $this->galleryProcessor->updateImage($update_product, $item_data['file'], array('disabled' => $images_data['existing_images_to_modify']['update'][$item_data['id']]['disabled']));
                        $items_modified = true;

                    }

                    $this->debbug('# time_mod_item: '.(microtime(1) - $time_ini_mod_item).' seconds.', 'timer');

                }
       
            }
            
            $this->debbug('# time_mod_all_items: '.(microtime(1) - $time_ini_mod_all_items).' seconds.', 'timer');
            
            $time_ini_update_items = microtime(1);
            
            if ($items_modified){

                try{
                
                    $update_product->save();
                    if ($item_index == 'format'){

                        $update_product = $this->load_format_model($item_id);

                    }else{

                        $update_product = $this->load_product_model($item_id);

                    }
                
                }catch(\Exception $e){
                
                    $this->debbug('## Error. Updating '.$item_index.' modified items: '.$e->getMessage());
                
                }

            }
            
            $this->debbug('# time_update_items: '.(microtime(1) - $time_ini_update_items).' seconds.', 'timer');

        }
        

        $time_ini_process_final_images = microtime(1);

        if (isset($images_data['final_images']) && !empty($images_data['final_images'])){

            $product_modified = false;

            foreach ($images_data['final_images'] as $image_filename => $image_info) {
                
                $time_ini_check_waste = microtime(1);
                $check_waste = $this->product_path_base.substr($image_filename, 0,1).'/'.substr($image_filename, 1,1).'/'.$image_filename;
                if (file_exists($check_waste)){ unlink($check_waste); }
                $this->debbug('# time_check_waste: '.(microtime(1) - $time_ini_check_waste).' seconds.', 'timer');

                $time_ini_prepare_image = microtime(1);
                $this->fileIo->checkAndCreateFolder($this->product_tmp_path_base.substr($image_filename, 0,1).'/'.substr($image_filename, 1,1).'/');
                // $new_file_name = $this->product_path_base.baseName($image_info['url']);
                $new_file_name = $this->product_tmp_path_base.substr($image_filename, 0,1).'/'.substr($image_filename, 1,1).'/'.$image_filename;
                $result = $this->fileIo->read($image_info['url'], $new_file_name);
                $exclude = $image_info['disabled'];
                
                if ($result) {
                
                    try{

                        $update_product->addImageToMediaGallery($new_file_name, $image_info['media_attribute'], true, $exclude);
                    
                    } catch (\Exception $e) {
                
                        $this->debbug('## Error. Adding '.$item_index.' image: '.$e->getMessage());
                    
                    }

                }

                $product_modified = true;
                $this->debbug('# time_prepare_image: '.(microtime(1) - $time_ini_prepare_image).' seconds.', 'timer');

            }

            $this->debbug('# time_process_final_images: '.(microtime(1) - $time_ini_process_final_images).' seconds.', 'timer');

            $time_ini_save_product_images = microtime(1);
            if ($product_modified){

                try{
                
                    $update_product->save();
                
                } catch (\Exception $e) {
                
                    $this->debbug('## Error. Updating '.$item_index.' final images: '.$e->getMessage());
                
                }

            }

            $this->debbug('# time_save_'.$item_index.'_images: '.(microtime(1) - $time_ini_save_product_images).' seconds.', 'timer');

        }

        foreach ($existing_links as $existing_link) {
        
            try{

                $this->productLinkRepositoryInterface->save($existing_link);

            }catch(\Exception $e){

                $this->debbug('## Error. Re-saving links: '.$e->getMessage());

            }
            
        }

    }

    /**
     * Function to clean associated items from a product.
     * @param  string $product_id               product id from product to clean associated items
     * @return void
     */
    private function clean_associated_product($product_id){

        $time_ini_clean_associated_product = microtime(1);

        $update_product = $this->load_product_model($product_id);
        $existing_links = $this->productLinkRepositoryInterface->getList($update_product);

        if (!empty($existing_links)){

            foreach ($existing_links as $linkKey => $existing_link) {
            
                if ($existing_link->getLinkType() == $this->product_link_type_grouped){

                    $this->productLinkRepositoryInterface->delete($existing_link);
                    unset($existing_links[$linkKey]);
                    continue;

                }

            }


        }

        if ($update_product->getTypeId() == $this->product_type_grouped){

            $update_product->setTypeId($this->product_type_simple);
            $this->products_collection[$product_id]['type_id'] = $this->product_type_simple;
            $update_product->save();

        }

        $this->debbug('# time_clean_associated_product: '.(microtime(1) - $time_ini_clean_associated_product).' seconds.', 'timer');

    }

    /**
    * Function to synchronize Sales Layer stored product links.
    * @param  array $all_linked_product_data            product links to synchronize
    * @return string                                    product links updated or not
    */
    protected function sync_stored_product_links($all_linked_product_data){

        try{

            $time_ini_update_links = microtime(1);
            $link_type_positions = array($this->product_link_type_grouped => 0, $this->product_link_type_related => 0, $this->product_link_type_upsell => 0, $this->product_link_type_crosssell => 0);

            foreach ($all_linked_product_data as $product_id => $linked_product_data) {
               
                $this->debbug(" > Updating stored product links ID: ".$product_id);

                $time_ini_link_product = microtime(1);
                $update_product = $this->load_product_model($product_id);
                $update_product_sku = $update_product->getSku();
                $existing_links = $this->productLinkRepositoryInterface->getList($update_product);
                $existing_links_data = array();

                $time_ini_existing_links_load = microtime(1);

                if (!empty($existing_links)){

                    foreach ($existing_links as $linkKey => $existing_link) {

                        $existing_links_data[$existing_link->getLinkType()][$existing_link->getLinkedProductSku()]['link_key'] = $linkKey;
                        
                        if ($existing_link->getLinkType() == $this->product_link_type_grouped){

                            $existing_links_data[$existing_link->getLinkType()][$existing_link->getLinkedProductSku()]['qty'] = $existing_link->getExtensionAttributes()->getQty();

                        }

                        if ($existing_link->getPosition() > $link_type_positions[$existing_link->getLinkType()]){

                            $link_type_positions[$existing_link->getLinkType()] = $existing_link->getPosition();

                        }

                    }

                }
                
                $this->debbug('# time_existing_links_load: '.(microtime(1) - $time_ini_existing_links_load).' seconds.', 'timer');

                $time_ini_link_all_data_update = microtime(1);

                foreach ($linked_product_data as $link_data) {

                    $time_ini_link_data_update = microtime(1);
                    
                    $link_type = $link_data['linked_type'];
                    $link_reference = $link_data['linked_reference'];

                    if (!empty($this->products_collection) && isset($this->products_collection_skus[$link_reference])){

                        $link_qty = 0;

                        if ($link_type == $this->product_link_type_grouped){

                            $link_product_id = $this->get_product_id_by_sku($link_reference);

                            if ($link_product_id && !in_array($this->products_collection[$link_product_id]['type_id'], array($this->product_type_simple, $this->product_type_virtual, $this->product_type_downloadable))){

                                $this->debbug('## Error. Product reference '.$link_reference.' type not valid: '.$this->products_collection[$link_product_id]['type_id']);
                                continue;

                            }
                            
                            $link_qty = $link_data['linked_qty'];

                            if ($update_product->getTypeId() != $this->product_type_grouped){

                                $update_product->setTypeId($this->product_type_grouped);
                                $update_product->save();

                                $this->products_collection[$product_id]['type_id'] = $this->product_type_grouped;

                            }

                        }

                        if (!empty($existing_links_data) && isset($existing_links_data[$link_type][$link_reference])){

                            if ($link_type == $this->product_link_type_grouped && $existing_links_data[$link_type][$link_reference]['qty'] != $link_qty){

                                $link = $existing_links[$existing_links_data[$link_type][$link_reference]['link_key']];
                                $link->getExtensionAttributes()->setQty($link_qty);
                                try{

                                    $this->productLinkRepositoryInterface->save($link);

                                }catch(\Exception $e){

                                    $this->debbug('## Error. Updating linked product qty: '.$e->getMessage());

                                }

                            }
                            
                            unset($existing_links[$existing_links_data[$link_type][$link_reference]['link_key']]);

                        }else{
                            
                            $new_link = clone $this->productLinkInterface;
                            
                            $link_type_positions[$link_type]++;
                            
                            $new_link->setSku($update_product_sku)
                                    ->setLinkedProductSku($link_reference)
                                    ->setLinkType($link_type)
                                    ->setPosition($link_type_positions[$link_type]);
                                    
                            if ($link_type == $this->product_link_type_grouped){

                                $new_link->setQty($link_qty);
                                
                            }
                            
                            try{

                                $this->productLinkRepositoryInterface->save($new_link);
                                
                            }catch(\Exception $e){
                        
                                $this->debbug('## Error. Saving new_link: '.$e->getMessage());
                        
                            }

                        }

                    }

                    $this->debbug('# time_link_data_update: '.(microtime(1) - $time_ini_link_data_update).' seconds.', 'timer');

                }
                
                $this->debbug('# time_link_all_data_update: '.(microtime(1) - $time_ini_link_all_data_update).' seconds.', 'timer');

                $time_ini_delete_links = microtime(1);
                
                if (!empty($existing_links)){

                    foreach ($existing_links as $existing_link) {
                        
                        $this->productLinkRepositoryInterface->delete($existing_link);

                    }

                }

                $this->debbug('# time_delete_links: '.(microtime(1) - $time_ini_delete_links).' seconds.', 'timer');

                $this->debbug('## time_link_product: '.(microtime(1) - $time_ini_link_product).' seconds.', 'timer');

            }
            
            $this->debbug('# time_update_links: '.(microtime(1) - $time_ini_update_links).' seconds.', 'timer');
        
        }catch(\Exception $e){

            $this->debbug('## Error. Updating linked_product_data: '.$e->getMessage());
            return 'item_not_updated';

        }

        return 'item_updated';

    }

    /**
     * Function to check if image attributes are global, if not, change them.
     * @return void
     */
    private function checkImageAttributes(){
        
        $image_attributes = array('image', 'small_image', 'thumbnail');

        foreach ($image_attributes as $image_attribute) {
            
            $attribute_id = $this->attribute->getIdByCode($this->product_entity, $image_attribute);
            $attribute = $this->attribute;
            $attribute->load($attribute_id);

            if ($attribute->getIsGlobal() != $this->scope_global){
                $attribute->setIsGlobal($this->scope_global);
                $attribute->save();
            }
        }
    }

    /**
     * Function to check if active attributes are global or not and store them in class variables.
     * @return void
     */
    private function checkActiveAttributes(){
        
        $attribute_id = $this->attribute->getIdByCode($this->category_entity, 'is_active');
        $attribute = $this->attribute;
        $attribute->load($attribute_id);
        if ($attribute->getIsGlobal() == $this->scope_global){ $this->category_enabled_attribute_is_global = true; }

        $attribute_id = $this->attribute->getIdByCode($this->product_entity, 'status');
        $attribute = $this->attribute;
        $attribute->load($attribute_id);
        if ($attribute->getIsGlobal() == $this->scope_global){ $this->product_enabled_attribute_is_global = true; }

    }

    /**
     * Function to find product category parent ids.
     * @param  array $product_catalogue_ids     categories to find parents 
     * @return array $categoryIds               result of categories and its parents
     */
    private function findProductCategoryIds($product_catalogue_ids){
        
        $categoryIds = array();
        
        if (!is_array($product_catalogue_ids)){ $product_catalogue_ids = array($product_catalogue_ids); }

        if (!empty($product_catalogue_ids)){
        
            foreach ($product_catalogue_ids as $product_catalogue_id){

                if (intval($product_catalogue_id) != 0){
                     
                    $category = $this->findSaleslayerCategoryId($product_catalogue_id, $this->comp_id);

                    if (!is_null($category)){
                        
                        if ($this->products_previous_categories == 1){
                        
                            $category_path = explode("/", $category->getPath());
                            if (!empty($category_path) && count($category_path) > 1){

                                unset($category_path[0]);

                                foreach ($category_path as $category_id) {
                                    
                                    if (!in_array($category_id, $categoryIds)){ 

                                        array_push($categoryIds, $category_id);
                                    
                                    }

                                }

                            }

                        }else{

                            $category_id = $category->getEntityId();
                            
                            if (!in_array($category_id, $categoryIds)){ 

                                array_push($categoryIds, $category_id);
                            
                            }

                        }

                    }

                }
        
            }

        }

        if (!empty($categoryIds)){

            $categoryIds = array_unique($categoryIds);
        
        }

        return $categoryIds;

    }

    /**
     * Function to find an option value inside class attribute array
     * @param  string $attribute_set_id       id of attribute set to search
     * @param  string $attribute_id           id of attribute to search
     * @param  string $attribute_option_value option value to search for
     * @param  string $store_view_id          id of store view to search first
     * @return string                         id of attribute option value found
     */
    private function find_attribute_option_value($attribute_set_id, $attribute_id, $attribute_option_value, $store_view_id){

       $attribute_option_value_lower = strtolower($attribute_option_value);
       $attribute_option_value_special = htmlspecialchars($attribute_option_value_lower);

       if ($store_view_id != 0){

           if (isset($this->attributes_collection[$attribute_set_id][$attribute_id]['options'][$store_view_id][$attribute_option_value_lower])){
       
               return $this->attributes_collection[$attribute_set_id][$attribute_id]['options'][$store_view_id][$attribute_option_value_lower];

           }else if (isset($this->attributes_collection[$attribute_set_id][$attribute_id]['options'][$store_view_id][$attribute_option_value_special])){
       
               return $this->attributes_collection[$attribute_set_id][$attribute_id]['options'][$store_view_id][$attribute_option_value_special];

           }

       }

       if (isset($this->attributes_collection[$attribute_set_id][$attribute_id]['options'][0][$attribute_option_value_lower])){
       
           if ($store_view_id != 0){ $this->updateAttributeOption($attribute_set_id, $attribute_id, $this->attributes_collection[$attribute_set_id][$attribute_id]['options'][0][$attribute_option_value_lower], $attribute_option_value, 0); }
           return $this->attributes_collection[$attribute_set_id][$attribute_id]['options'][0][$attribute_option_value_lower];

       }else if (isset($this->attributes_collection[$attribute_set_id][$attribute_id]['options'][0][$attribute_option_value_special])){
       
           if ($store_view_id != 0){ $this->updateAttributeOption($attribute_set_id, $attribute_id, $this->attributes_collection[$attribute_set_id][$attribute_id]['options'][0][$attribute_option_value_special], $attribute_option_value, 0); }
           return $this->attributes_collection[$attribute_set_id][$attribute_id]['options'][0][$attribute_option_value_special];

       }        

       $rest_stores = array_diff($this->store_view_ids, array(0, $store_view_id));
       
       if (!empty($rest_stores)){

           foreach ($rest_stores as $rest_store_id){

               if (isset($this->attributes_collection[$attribute_set_id][$attribute_id]['options'][$rest_store_id][$attribute_option_value_lower])){
                   
                   $this->updateAttributeOption($attribute_set_id, $attribute_id, $this->attributes_collection[$attribute_set_id][$attribute_id]['options'][$rest_store_id][$attribute_option_value_lower], $attribute_option_value, $rest_store_id);
                   return $this->attributes_collection[$attribute_set_id][$attribute_id]['options'][$rest_store_id][$attribute_option_value_lower];

               }else if (isset($this->attributes_collection[$attribute_set_id][$attribute_id]['options'][$rest_store_id][$attribute_option_value_special])){
                   
                   $this->updateAttributeOption($attribute_set_id, $attribute_id, $this->attributes_collection[$attribute_set_id][$attribute_id]['options'][$rest_store_id][$attribute_option_value_special], $attribute_option_value, $rest_store_id);
                   return $this->attributes_collection[$attribute_set_id][$attribute_id]['options'][$rest_store_id][$attribute_option_value_special];

               }

           }

       }
       
       return $this->addAttributeOption($attribute_set_id, $attribute_id, $attribute_option_value);

   }

   /**
    * Function to create an attribute option that don't exist.
    * @param string $attribute_set_id     attribute set id to store new option id. 
    * @param string $attribute_id         attribute id to add option.
    * @param string $attribute_option     attribute option name.
    * @return string $option_id           new option id
    */
    private function addAttributeOption($attribute_set_id, $attribute_id, $attribute_option){
        
        $option_id = $this->synccatalogDataHelper->createOrGetId($attribute_id, $attribute_option, $this->store_view_ids);        

        if ($option_id){

            foreach ($this->store_view_ids as $store_view_id) {

                if (!isset($this->attributes_collection[$attribute_set_id][$attribute_id]['options'][$store_view_id])){

                    $this->attributes_collection[$attribute_set_id][$attribute_id]['options'][$store_view_id] = array();

                }

                $this->attributes_collection[$attribute_set_id][$attribute_id]['options'][$store_view_id][strtolower($attribute_option)] = $option_id;

            }

        }

        return $option_id;

    }

    /**
     * Function to find an option value inside class attribute array
     * @param  string $attribute_set_id       id of attribute set to search
     * @param  string $attribute_id           id of attribute to search
     * @param  string $attribute_option_value option value to search for
     * @param  string $store_view_id          id of store view to search first
     * @return string                         id of attribute option value found
     */
    private function updateAttributeOption($attribute_set_id, $attribute_id, $attribute_option_id, $attribute_option_value, $store_view_id_found){

        $attribute_option_value_lower = strtolower($attribute_option_value);
        $attribute_option_value_special = htmlspecialchars($attribute_option_value_lower);

        $store_views_to_update = array();

        foreach ($this->all_store_view_ids as $all_stores_view_id) {

            $optioncollection = clone $this->collectionOption;

            $option = $optioncollection
                        ->setAttributeFilter($attribute_id)
                        ->addFieldToFilter('main_table.option_id', $attribute_option_id)
                        ->setStoreFilter($all_stores_view_id, false)
                        ->getFirstItem();

            if (!empty($option->getData()) ){

                $store_views_to_update[$all_stores_view_id] = $option->getValue();

            }else{

                if (in_array($all_stores_view_id, $this->store_view_ids)){

                    $option_value_found = false;

                    if (isset($this->attributes_collection[$attribute_set_id][$attribute_id]['options'][$all_stores_view_id])){

                        $option_value_found = array_search($attribute_option_id, $this->attributes_collection[$attribute_set_id][$attribute_id]['options'][$all_stores_view_id]);

                    }

                    if (!$option_value_found){ $option_value_found = $attribute_option_value; }

                    $store_views_to_update[$all_stores_view_id] = $option_value_found;

                }

            }

        }

        if (!empty($store_views_to_update)){

            try{

                $result_update = $this->synccatalogDataHelper->updateAttributeOption($attribute_id, $attribute_option_id, $attribute_option_value, $store_views_to_update);

            }catch(\Exception $e){

                $result_update = false;
                $this->debbug('## Error. Updating attribute option: '.$e->getMessage());

            }
            
            if ($result_update) {

                foreach ($store_views_to_update as $store_view_to_update => $store_view_option_value) {

                    if (!isset($this->attributes_collection[$attribute_set_id][$attribute_id]['options'][$store_view_to_update])){

                        $this->attributes_collection[$attribute_set_id][$attribute_id]['options'][$store_view_to_update] = array();

                    }

                    $this->attributes_collection[$attribute_set_id][$attribute_id]['options'][$store_view_to_update][strtolower($attribute_option_value)] = $attribute_option_id;

                }

            }

        }

    }

    /**
     * Function to synchronize Sales Layer stored product format.
     * @param  array $format                product format to synchronize
     * @return string                       product format updated or not
     */
    protected function sync_stored_product_format($format){

        if ($this->sl_DEBBUG > 2) $this->debbug('Synchronizing stored product format: '.print_R($format,1));

        $arrayExclude = array('product_reference','format_reference', 'format_name', 'format_price', 'format_sku', 'format_quantity', $this->format_field_image, 'format_tax_class_id');

        $parent_all_data = array();

        $time_ini_format_process = microtime(1);

        $format['data']['format_name'] = $format['id'].'_'.$format['data']['format_name'];
        $sl_product_id = $format['products_id'];
        $sl_format_id = $format['id'];

        $this->debbug(" > Updating format data ID: $sl_format_id product ID: $sl_product_id ");
        if ($this->sl_DEBBUG > 1) $this->debbug("Name: ".$format['data']['format_name']);

        $conf_product = $this->findSaleslayerProductId($sl_product_id, $this->comp_id, true);
        if (is_null($conf_product)){ $conf_product = $this->findSaleslayerProductId($sl_product_id, $this->comp_id, false); }

        if ($conf_product){

            $conf_product_id = $conf_product->getEntityId();
            $conf_product_attribute_set_id = $conf_product->getAttributeSetId();

            $sl_data = $format['data'];

            $form_product = $this->findSaleslayerProductFormatId($sl_product_id, $this->comp_id, $sl_format_id);

            $new_product_format = false;

            $time_ini_check_duplicated_name_format = microtime(1);

            if (!$this->checkDuplicatedName('product_format', $sl_data['format_name'], $sl_product_id, $this->comp_id)){

                $this->debbug('## time_check_duplicated_name_format: '.(microtime(1) - $time_ini_check_duplicated_name_format).' seconds.', 'timer');

                if (is_null($form_product)){

                    $time_ini_create_format = microtime(1);

                    if (!isset($sl_data['format_sku']) || $sl_data['format_sku'] == ''){
                        if (isset($sl_data['format_name']) && $sl_data['format_name'] != ''){
                            $sl_data['format_sku'] = 'sku_'.$sl_data['format_name'];
                        }else{
                            $this->debbug("## Error. Format doesn't has name.");
                            return 'item_not_updated';
                        }
                    }
                    $found = false;

                    if (isset($sl_data['format_sku']) && $sl_data['format_sku'] != ''){
                        $sl_sku = $sl_data['format_sku'];

                        $time_ini_check_duplicated_sku_format = microtime(1);

                        if (!$this->checkDuplicatedSKU('product_format', $sl_sku, $sl_product_id, $this->comp_id)){

                            $this->debbug('## time_check_duplicated_sku_format: '.(microtime(1) - $time_ini_check_duplicated_sku_format).' seconds.', 'timer');
                            //The products exists. 
                            $existing_format_id = $this->get_product_id_by_sku($sl_sku);
                            if($existing_format_id){
                                $form_product = $this->load_format_model($existing_format_id);
                                if (isset($sl_data['format_name']) && $sl_data['format_name'] != ''){
                                    $form_product->setName($sl_data['format_name']);
                                }
                                if (isset($sl_data['format_price']) && is_numeric($sl_data['format_price'])){
                                    $form_product->setPrice($sl_data['format_price']);
                                }
                                $form_product->setSaleslayerId($sl_product_id);
                                $form_product->setSaleslayerCompId($this->comp_id);
                                $form_product->setSaleslayerFormatId($sl_format_id);
                                $form_product->setVisibility($this->visibility_not_visible);
                                if ($form_product->getStatus() == $this->status_disabled){
                                    $form_product->setStatus($this->status_enabled);
                                    $this->products_collection[$existing_format_id]['status'] = $this->status_enabled;
                                }
                                $form_product->save();
                                $this->products_collection[$existing_format_id]['saleslayer_id'] = $sl_product_id;
                                $this->products_collection[$existing_format_id]['saleslayer_comp_id'] = $this->comp_id;
                                $this->products_collection[$existing_format_id]['saleslayer_format_id'] = $sl_format_id;
                                $found = true;
                            }
                        }else{
                            return 'item_not_updated';
                        }        
                    }

                    if (!$found){
                       
                        $form_product = $this->load_format_model();

                        if (!isset($sl_data['format_name']) || $sl_data['format_name'] == ''){
                            $this->debbug("## Error. Format doesn't has name.");
                            return 'item_not_updated';
                        }else{

                            $form_product->setName($sl_data['format_name']);
                            if (!isset($sl_data['format_sku']) || $sl_data['format_sku'] == ''){
                                $form_product->setSku($sl_data['format_name']);
                            }else{
                                $form_product->setSku($sl_data['format_sku']);
                            }
                            if (isset($sl_data['format_price']) && is_numeric($sl_data['format_price'])){
                                $form_product->setPrice($sl_data['format_price']);
                            }else{
                                $form_product->setPrice(0);
                            }

                            $isInStock = $sl_qty = 0;
                            $manage_stock = $this->config_manage_stock;
                            $use_config_manage_stock = 1;

                            if (isset($sl_data['format_quantity']) && is_numeric($sl_data['format_quantity'])){
                                
                                $sl_qty = $sl_data['format_quantity'];

                                if ($sl_qty) {

                                    $manage_stock = 1;
                                    $isInStock = 1;

                                }

                            }

                            if ($manage_stock !== $this->config_manage_stock){

                                $use_config_manage_stock = 0;

                            }

                            $form_product->setStockData(array(
                                'manage_stock'            => $manage_stock,
                                'is_in_stock'             => $isInStock,
                                'qty'                     => $sl_qty,
                                'use_config_manage_stock' => $use_config_manage_stock));

                            $form_product->setSaleslayerId($sl_product_id);
                            $form_product->setSaleslayerCompId($this->comp_id);
                            $form_product->setSaleslayerFormatId($sl_format_id);

                            $conf_categoryIds = $conf_product->getCategoryIds();
                            $form_product->setCategoryIds($conf_categoryIds);

                            $sl_tax_class_id_value = '';

                            if (isset($sl_data['format_tax_class_id'])){

                                $sl_tax_class_id_value = $sl_data['format_tax_class_id'];

                            }

                            $sl_tax_class_id_found = $this->findTaxClassId($sl_tax_class_id_value);

                            $form_product->setTaxClassId($sl_tax_class_id_found);

                            $form_product->setWeight(1)
                                        ->setCreatedAt(strtotime('now'))
                                        ->setStatus($this->status_enabled)
                                        ->setVisibility($this->visibility_not_visible)
                                        // ->setTaxClassId(0) //tax class (0 - none, 1 - default, 2 - taxable, 4 - shipping)
                                        ->setAttributeSetId($conf_product_attribute_set_id)
                                        ->setTypeId($this->product_type_simple);
                            if (!empty($this->website_ids)){
                                $form_product->setWebsiteIds($this->website_ids);
                            }else{
                                $form_product->setWebsiteIds(array(1));
                            }

                            $form_product->save();

                            $format_id = $form_product->getEntityId();

                            $this->products_collection[$format_id] = array('entity_id' => $format_id,
                                                                                        'name' => $sl_data['format_name'],
                                                                                        'status' => $this->status_enabled,
                                                                                        'sku' => $form_product->getSku(),
                                                                                        'type_id' => $this->product_type_simple,
                                                                                        'saleslayer_id' => $sl_product_id,
                                                                                        'saleslayer_comp_id' => $this->comp_id,
                                                                                        'saleslayer_format_id' => $sl_format_id);
                            $this->products_collection_skus[$form_product->getSku()][$format_id] = $format_id;
                            $this->products_collection_names[$sl_data['format_name']][$format_id] = $format_id;
                            $new_product_format = true;
                        }
                    }
                   
                    $this->debbug('## time_create_format: '.(microtime(1) - $time_ini_create_format).' seconds.', 'timer');

                }else{

                    $time_ini_update_basic_format_data = microtime(1);

                    $modified_name = $modified_sku = false;
                    $old_sl_sku = $old_sl_name = $sl_sku = $sl_name = '';
                    if (isset($sl_data['format_name']) && $sl_data['format_name'] != ''){
                        $old_sl_name = $form_product->getName();
                        $sl_name = $sl_data['format_name'];
                        $form_product->setName($sl_name);
                        $modified_name = true;
                    } 
                    if (isset($sl_data['format_sku']) && $sl_data['format_sku'] != ''){
                        $old_sl_sku = $form_product->getSku();
                        $sl_sku = $sl_data['format_sku'];
                        $form_product->setSku($sl_sku);
                        $modified_sku = true;
                    }
                    if (isset($sl_data['format_price']) && is_numeric($sl_data['format_price'])){
                        $form_product->setPrice($sl_data['format_price']);
                    }else{
                        $form_product_price = $form_product->getPrice();
                        if (is_null($form_product_price)){ 
                            $form_product->setPrice('0'); 
                        }
                    }

                    $website_ids = array(1);
                    if (!empty($this->website_ids)){ $website_ids = $this->website_ids; }
                    if ($form_product->getWebsiteIds() != $website_ids){
                        $website_ids = array_unique(array_merge($form_product->getWebsiteIds(), $website_ids));
                        $form_product->setWebsiteIds($website_ids);
                        
                    }

                    if (isset($sl_data['format_tax_class_id']) && $sl_data['format_tax_class_id'] != ''){

                        $sl_tax_class_id_found = $this->findTaxClassId($sl_data['format_tax_class_id']);

                        if ($form_product->getTaxClassId() != $sl_tax_class_id_found){

                            $form_product->setTaxClassId($sl_tax_class_id_found);

                        }

                    }

                    if ($this->avoid_stock_update == '0'){

                        $isInStock = $sl_qty = 0;
                        $manage_stock = $this->config_manage_stock;
                        $use_config_manage_stock = 1;

                        if (isset($sl_data['format_quantity']) && is_numeric($sl_data['format_quantity'])){
                            
                            $sl_qty = $sl_data['format_quantity'];

                            if ($sl_qty) {

                                $manage_stock = 1;
                                $isInStock = 1;

                            }
                            
                        }

                        if ($manage_stock !== $this->config_manage_stock){

                            $use_config_manage_stock = 0;

                        }

                        $form_product->setStockData(array(
                            'manage_stock'            => $manage_stock,
                            'is_in_stock'             => $isInStock,
                            'qty'                     => $sl_qty,
                            'use_config_manage_stock' => $use_config_manage_stock));

                    }


                    $form_product->save();

                    if ($modified_name){

                        if ($old_sl_name != $sl_name){
                           
                            unset($this->products_collection_names[$old_sl_name][$form_product->getEntityId()]);
                            if (count($this->products_collection_names[$old_sl_name]) == 0 || empty($this->products_collection_names[$old_sl_name])){
                                unset($this->products_collection_names[$old_sl_name]);
                            }
                            $this->products_collection_names[$sl_name][$form_product->getEntityId()] = $form_product->getEntityId();

                        }
                       

                        $this->products_collection[$form_product->getEntityId()]['name'] = $sl_name;

                    }

                    if ($modified_sku){

                        if ($old_sl_sku != $sl_sku){
                           
                            unset($this->products_collection_skus[$old_sl_sku][$form_product->getEntityId()]);
                            if (count($this->products_collection_skus[$old_sl_sku]) == 0 || empty($this->products_collection_skus[$old_sl_sku])){
                                unset($this->products_collection_skus[$old_sl_sku]);
                            }
                            $this->products_collection_skus[$sl_sku][$form_product->getEntityId()] = $form_product->getEntityId();
                           
                        }

                        $this->products_collection[$form_product->getEntityId()]['sku'] = $sl_sku;

                    }

                    $conf_categoryIds = $conf_product->getCategoryIds();
                   
                    if ($form_product->getCategoryIds() != $conf_categoryIds){

                        try {
                                                    
                            $this->categoryLinkManagementInterface->assignProductToCategories($form_product->getSku(), $conf_categoryIds);
                        
                        }catch (\Exception $e) {

                            $this->debbug("## Error. Updating product format categories: ".$e->getMessage());

                        }

                    }

                    $this->debbug('## time_update_basic_format_data: '.(microtime(1) - $time_ini_update_basic_format_data).' seconds.', 'timer');

                }
            }else{
                return 'item_not_updated';
            }

            if ($form_product->getEntityId()){

                $conn_insert = true;
                if (isset($this->sl_multiconn_table_data['format'][$sl_format_id]) && !empty($this->sl_multiconn_table_data['format'][$sl_format_id]['sl_connectors'])){

                    $conn_found = array_search($this->processing_connector_id, $this->sl_multiconn_table_data['format'][$sl_format_id]['sl_connectors']);

                    if (!is_numeric($conn_found)){

                        $this->sl_multiconn_table_data['format'][$sl_format_id]['sl_connectors'][] = $this->processing_connector_id;

                        $new_connectors_data = json_encode($this->sl_multiconn_table_data['format'][$sl_format_id]['sl_connectors']);

                        $query_update  = " UPDATE ".$this->saleslayer_multiconn_table." SET sl_connectors = ? WHERE id = ? ";

                        // $this->execute_multiconn_sql($query_update, array($new_connectors_data, $this->sl_multiconn_table_data['format'][$sl_format_id]['id']));

                        $this->sl_connection_query($query_update, array($new_connectors_data, $this->sl_multiconn_table_data['format'][$sl_format_id]['id']));
                        
                    }

                    $conn_insert = false;

                }

                if ($conn_insert){

                    $connectors_data = json_encode(array($this->processing_connector_id));

                    $query_insert = " INSERT INTO ".$this->saleslayer_multiconn_table."(`item_type`,`sl_id`,`sl_comp_id`,`sl_connectors`) values ( ? , ? , ? , ? );";

                    // $this->execute_multiconn_sql($query_insert, array('format', $sl_format_id, $this->comp_id, $connectors_data));

                    $this->sl_connection_query($query_insert, array('format', $sl_format_id, $this->comp_id, $connectors_data));

                }

                if (isset($sl_data[$this->format_field_image])) {

                    if ($this->avoid_images_updates){

                        $this->debbug(" > Avoiding update of format images in update.");

                    }else{

                        $time_ini_sync_format_images = microtime(1);
                        $this->debbug(" > Updating format images ID: ".$sl_format_id);
                        $this->prepare_product_format_images_to_store($form_product, $sl_data[$this->format_field_image]);
                        $this->debbug('## sync_format_images: '.(microtime(1) - $time_ini_sync_format_images).' seconds.', 'timer');
                    
                    }


                    unset($sl_data[$this->format_field_image]);

                }

                $time_ini_update_rest_format_data = microtime(1);
               
                $linkable_form = true;
                $error_format_message = '';
                $configurableProductsData = array();
                $usedProductAttributeNames = array();
                $usedProductAttributeIds = array();
                $configurableAttributesData = array();

                $form_product_id = $form_product->getEntityId();
                $form_product_name = $form_product->getName();
                $form_product_price = $form_product->getPrice();

                $time_ini_format_attributes = microtime(1);
                if (!empty($this->store_view_ids)){
                    if ($new_product_format){
                        if (!in_array(0, $this->store_view_ids)){
                            array_push($this->store_view_ids, 0);
                            asort($this->store_view_ids);
                        }   
                    }
                   
                    $first_round = true;
                    foreach ($this->store_view_ids as $store_view_id) {

                        $format_modified = false;

                        $time_ini_format_attribute_shop = microtime(1);

                        $form_product = $this->load_format_model($form_product_id, $store_view_id);

                        if ($store_view_id !== reset($this->store_view_ids)){
                            $first_round = false;
                        }                    

                        $sl_format_title_name = $sl_format_option_name = $format_attribute_title_id = $format_attribute_value_id = $format_attribute_value_name = '';

                        foreach ($sl_data as $sl_format_title => $sl_format_option) {
                            if (in_array($sl_format_title, $arrayExclude)){
                                continue; 
                            }

                            $sl_format_title_name = strtolower(str_replace(' ', '_', $sl_format_title));
                            $this->load_attributes_by_attribute_set_id($conf_product_attribute_set_id);

                            if (isset($this->attributes_collection[$conf_product_attribute_set_id])){

                                foreach ($this->attributes_collection[$conf_product_attribute_set_id] as $attribute_col) {
                                    
                                    if ($attribute_col['attribute_code'] == $sl_format_title_name){

                                        $format_attribute_title_id = $attribute_col['attribute_id'];
                                        $format_frontend_input = $attribute_col['frontend_input'];
                                        break;

                                    }

                                }

                            }

                            if (!$format_attribute_title_id){

                                continue;

                            }
                            
                            if ((is_array($sl_format_option) && empty($sl_format_option)) || (!is_array($sl_format_option) && $sl_format_option == '')){

                                if ($format_frontend_input != 'media_image'){

                                    if ($form_product->getData($sl_format_title_name) != ''){

                                        $form_product->setData($sl_format_title_name, '');
                                        $format_modified = true;

                                    }

                                }

                                continue;

                            }

                            $format_attribute = $form_product->getResource()->getAttribute($sl_format_title_name);
                               
                            switch ($format_frontend_input) {

                                case 'media_image':
                                    
                                    break;

                                case 'select':
                                    if ($format_attribute->usesSource()){
                                        if (is_array($sl_format_option)){
                                            $sl_format_option_name = $sl_format_option[0];
                                        }else{
                                            $sl_format_option_name = $sl_format_option;
                                        }

                                        $format_attribute_value_id = $this->find_attribute_option_value($conf_product_attribute_set_id, $format_attribute_title_id, $sl_format_option_name, $store_view_id);

                                        $format_attribute_value_name = $format_attribute->getSource()->getOptionText($format_attribute_value_id);
                                       
                                        if (is_object($format_attribute_value_name)){
                                            $format_attribute_value_name = $format_attribute_value_name->getText();
                                        }

                                        if ($format_attribute_value_id){

                                            if ($form_product->getData($sl_format_title_name) != $format_attribute_value_id){

                                                $form_product->setData($sl_format_title_name, $format_attribute_value_id);
                                                $format_modified = true;

                                            }
                                           
                                            if (!empty($this->format_configurable_attributes) && in_array($format_attribute_title_id, $this->format_configurable_attributes) && $first_round){
                                                
                                                $format_data = array(
                                                        'label' => $format_attribute_value_name,
                                                        'attribute_id' => $format_attribute_title_id,
                                                        'value_index' => $format_attribute_value_id,
                                                        'is_percent'    => 0,
                                                        'pricing_value' => $form_product_price,
                                                    )
                                                ;
                                                if (!isset($configurableProductsData[$form_product_id])){ $configurableProductsData[$form_product_id] = array(); }
                                                array_push($configurableProductsData[$form_product_id], $format_data);
                                                array_push($usedProductAttributeNames, $sl_format_title_name);
                                                $configurableAttributesData[] = $format_data;
                                                $usedProductAttributeIds[] = $format_attribute_title_id;
                                            }
                                        }else{
                                            if (!empty($this->format_configurable_attributes) && in_array($format_attribute_title_id, $this->format_configurable_attributes) && $first_round){
                                                if ($error_format_message != ''){ $error_format_message .= "\n"; }
                                                $error_format_message .= "## Error. The product format with SKU ".$sl_data['format_sku']." hasn't been assigned because the attribute ".$sl_format_title_name." with the value ".$sl_format_option_name." that doesn't exists.";
                                                $linkable_form = false;
                                            }
                                        }
                                    }

                                    break;
                                case 'multiselect':
                                    $value_to_update = $sl_options = '';
                                   
                                    (is_array($sl_format_option)) ? $sl_options = $sl_format_option : $sl_options = array($sl_format_option);
                                   
                                    foreach ($sl_options as $additional_field_value) {

                                        $value_found = $this->find_attribute_option_value($conf_product_attribute_set_id, $format_attribute_title_id, $additional_field_value, $store_view_id);
                                       
                                        if ($value_found){
                                       
                                            if ($value_to_update == ''){
                                       
                                                $value_to_update = $value_found;
                                       
                                            }else{
                                       
                                                $value_to_update .= ','.$value_found;
                                       
                                            }
                                       
                                        }

                                    }
                                    
                                    if ($value_to_update != ''){

                                        if ($form_product->getData($sl_format_title_name) != $value_to_update){

                                            $form_product->setData($sl_format_title_name, $value_to_update);
                                            $format_modified = true;

                                        }

                                    }

                                    break;
                                case 'price':
                                    $additional_field_value = '';
                                    if (is_array($sl_format_option)){
                                        $additional_field_value = $sl_format_option[0];                                                    
                                    }else{
                                        $additional_field_value = $sl_format_option;
                                    }

                                    if (!is_numeric($additional_field_value) && filter_var($additional_field_value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)){
                                        $value_to_update = filter_var($additional_field_value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                                    }else{
                                        $value_to_update = $additional_field_value;
                                    }
                                    
                                    if ($form_product->getData($sl_format_title_name) != $value_to_update){
                                       
                                        $form_product->setData($sl_format_title_name, $value_to_update);
                                        $format_modified = true;

                                    }

                                    break;
                                case 'boolean':
                                    $additional_field_value = '';
                                    if (is_array($sl_format_option)){
                                        $additional_field_value = $sl_format_option[0];
                                    }else{
                                        $additional_field_value = $sl_format_option;
                                    }

                                    $value_to_update = filter_var($additional_field_value, FILTER_VALIDATE_BOOLEAN);
                                   
                                    if ($form_product->getData($sl_format_title_name) != $value_to_update){
                                       
                                        $form_product->setData($sl_format_title_name, $value_to_update);
                                        $format_modified = true;

                                    }

                                    break;
                                case 'date':
                                    $additional_field_value = '';
                                    if (is_array($sl_format_option)){
                                        $additional_field_value = $sl_format_option[0];                                                    
                                    }else{
                                        $additional_field_value = $sl_format_option;
                                    }
                                    
                                    if ($form_product->getData($sl_format_title_name) != $additional_field_value){
                                   
                                        $form_product->setData($sl_format_title_name, $additional_field_value);
                                        $format_modified = true;

                                    }
                                   
                                    break;
                                case 'weee':
                                    break;
                                default:
                                    $additional_field_value = '';
                                    if (is_array($sl_format_option)){
                                        $additional_field_value = implode(', ', array_filter($sl_format_option, array($this, 'array_filter_empty_value')));
                                    }else{
                                        $additional_field_value = $sl_format_option;
                                    }

                                    $additional_field_value = $this->sl_check_html_text($additional_field_value);
                                    if ($form_product->getData($sl_format_title_name) !== $additional_field_value){
                                   
                                        $form_product->setData($sl_format_title_name, $additional_field_value);
                                        $format_modified = true;

                                    }

                                    break;
                            }
                            
                        }

                        $time_ini_format_save = microtime(1);

                        if ($format_modified){

                            try {

                                if ($form_product->save()){

                                    if ($this->sl_DEBBUG > 1) $this->debbug("Updated format data!");
                                }

                            } catch (\Exception $e) {

                                $this->debbug("## Error. Updating format ".$form_product_name." data: ".$e->getMessage());
                                
                            }

                        }

                        $this->debbug('## time_format_save: '.(microtime(1) - $time_ini_format_save).' seconds.', 'timer');

                        $this->debbug('## time_format_attribute_shop: '.(microtime(1) - $time_ini_format_attribute_shop).' seconds.', 'timer');
                      
                    }
                }
                $this->debbug('## time_format_attributes: '.(microtime(1) - $time_ini_format_attributes).' seconds.', 'timer');

                $time_ini_format_store_data = microtime(1);

                if (!empty($configurableProductsData) && $linkable_form){
                    
                    if (!self::check_configurable_product_duplicated_formats($conf_product_id, $form_product_id)){

                        $this->debbug("## Error. The product format with SKU ".$sl_data['format_sku']." hasn't been assigned because there's another product format with the same values assigned to the product.");
                        return 'item_updated';

                    }else{

                        if (!isset($parent_all_data[$conf_product_id])){ $parent_all_data[$conf_product_id] = array(); }
                        $parent_all_data[$conf_product_id]['attribute_set_id'] = $conf_product_attribute_set_id;

                        if (!isset($parent_all_data[$conf_product_id]['usedAttributeIds'])){ $parent_all_data[$conf_product_id]['usedAttributeIds'] = array(); }
                        $parent_all_data[$conf_product_id]['usedAttributeIds'] = array_unique(array_merge($parent_all_data[$conf_product_id]['usedAttributeIds'], $usedProductAttributeIds));
                        
                        $parent_all_data[$conf_product_id]['configurableProductsData'][$form_product_id] = $configurableProductsData[$form_product_id];

                        if (!isset($parent_all_data[$conf_product_id]['configurableAttributesData']) || empty($parent_all_data[$conf_product_id]['configurableAttributesData'])){
                            $parent_all_data[$conf_product_id]['configurableAttributesData'] = $configurableAttributesData;
                        }else{
                            foreach ($configurableAttributesData as $keyCAD => $configurableAttributeData) {
                                $found = false;
                                foreach ($parent_all_data[$conf_product_id]['configurableAttributesData'] as $keyECAD => $parent_configurable_attribute_data) {
                                    if ($parent_configurable_attribute_data['attribute_id'] == $configurableAttributeData['attribute_id'] && $parent_configurable_attribute_data['value_index'] == $configurableAttributeData['value_index']){
                                        $found = true;
                                    }
                                }
                                
                                if (!$found){
                                    $parent_all_data[$conf_product_id]['configurableAttributesData'][] = $configurableAttributeData;
                                }
                            }

                        }
                        $parent_all_data[$conf_product_id]['form_product_ids'][] = $form_product_id;

                    }

                }else{
                    
                    $childrenIds = $this->productConfigurableType->getChildrenIds($conf_product_id);
                   
                    if (in_array($form_product_id, $childrenIds[0])) {
                    
                        unset($childrenIds[0][$form_product_id]);
                        $this->productConfigurableType->saveProducts($conf_product, $childrenIds[0]);
                        
                        if (empty($childrenIds[0])){

                            if ($conf_product->getTypeId() != $this->product_type_simple){
                               
                                $conf_product->setTypeId($this->product_type_simple);
                                
                                try{
                                
                                    $conf_product->save();
                                    $this->products_collection[$conf_product_id]['type_id'] = $this->product_type_simple;
                                   
                                }catch(\Exception $e){
                                   
                                    $this->debbug('## Error. Updating conf product to simple: '.$e->getMessage());
                                
                                }

                            }

                        }

                    }

                    if ($error_format_message == ''){

                        $error_format_message .= "## Error. The product format with SKU ".$sl_data['format_sku']." hasn't been assigned because it doesn't has any configurable attributes.";
                    
                    }

                    if ($error_format_message != ''){

                        $this->debbug($error_format_message);

                    }
                    return 'item_updated';
                
                }

                $this->debbug('## time_format_store_data: '.(microtime(1) - $time_ini_format_store_data).' seconds.', 'timer');
               
                $this->debbug('## time_update_rest_format_data: '.(microtime(1) - $time_ini_update_rest_format_data).' seconds.', 'timer');

            }else{

                $this->debbug('## Error. Format not properly generated.');
                return 'item_not_updated';

            }
        }else{
            $this->debbug("## Error. Format parent product doesn't exist.");
            return 'item_not_updated';
        }

        $this->debbug('### format_process: '.(microtime(1) - $time_ini_format_process).' seconds.', 'timer');
        
        $this->assign_product_formats($parent_all_data);

        return 'item_updated';


    }

    /**
     * Function to assign formats to a product.
     * @param  array $parent_all_data Data of the relationship between the product and its formats
     * @return void
     */
    private function assign_product_formats($parent_all_data){

        foreach ($parent_all_data as $parent_product_id => $parent_data) {
                
            $conf_product = $this->load_product_model_configurable($parent_product_id);

            if ($conf_product->getTypeId() != $this->product_type_configurable){
               
                $conf_product->setTypeId($this->product_type_configurable);
                $conf_product->setStockData(array(
                    'use_config_manage_stock' => 0,
                    'manage_stock' => 1,
                    'is_in_stock' => 1,
                    )
                );
                
                try{
                
                    $conf_product->save();
                    $this->products_collection[$parent_product_id]['type_id'] = $this->product_type_configurable;
                   
                }catch(\Exception $e){
                   
                    $this->debbug('## Error. Updating product to configurable: '.$e->getMessage());
               
                }

            }

            $confAttributeIds = array();

            if (isset($this->attributes_collection[$parent_data['attribute_set_id']]) && !empty($this->attributes_collection[$parent_data['attribute_set_id']])){

                foreach ($parent_data['usedAttributeIds'] as $usedAttributeId) {
                
                    if (isset($this->attributes_collection[$parent_data['attribute_set_id']][$usedAttributeId])){

                        $confAttributeIds[] = $usedAttributeId;

                    }

                }

            }

            asort($confAttributeIds);
            
            $existingProductAttributeIds = $conf_product->getTypeInstance()->getUsedProductAttributeIds($conf_product);
            
            $deleted = false;
            if (!empty($existingProductAttributeIds)){
                
                asort($existingProductAttributeIds);
                
                if (array_values($existingProductAttributeIds) !== array_values($confAttributeIds)){
                
                    try{
                    
                        $conf_product->setTypeId($this->product_type_simple);
                        $conf_product->save();
                        $conf_product = $this->load_product_model($parent_product_id);

                    }catch(\Exception $e){
                    
                        $this->debbug('## Error. Updating product to simple: '.$e->getMessage());
                    
                    }

                    try{
                        
                        $conf_product->setTypeId($this->product_type_configurable);
                        $conf_product->setStockData(array(
                            'use_config_manage_stock' => 0,
                            'manage_stock' => 1,
                            'is_in_stock' => 1,
                            )
                        );
                        $conf_product->save();
                        $conf_product = $this->load_product_model($parent_product_id);
                        $deleted = true;
                       
                    }catch(\Exception $e){
                        
                        $this->debbug('## Error. Updating product to configurable: '.$e->getMessage());
                    
                    }
                
                }
            
            }
                
            if (empty($existingProductAttributeIds) || $deleted){
            
                $conf_product->getTypeInstance()->setUsedProductAttributeIds($confAttributeIds, $conf_product);
            
            }

            // $existingProductAttributeIds = $conf_product->getTypeInstance()->getUsedProductAttributeIds($conf_product);
            
            $conf_product->setConfigurableProductsData($parent_data['configurableProductsData']);

            $existingConfigurableAttributesData = $conf_product->getTypeInstance()->getConfigurableAttributesAsArray($conf_product);
            
            foreach ($existingConfigurableAttributesData as $keyECAD => $existingConfigurableAttributeData) {
            
                $existingConfigurableAttributesData[$keyECAD]['use_default'] = 0; 
                $existingConfigurableAttributesData[$keyECAD]['position'] = 0;   
            
            }

            if (!empty($parent_data['configurableAttributesData'])){
            
                foreach ($parent_data['configurableAttributesData'] as $configurableAttributeData) {

                    $data_attribute = array(
                        'value_index' => $configurableAttributeData['value_index'],
                        'label' => $configurableAttributeData['label'],
                        'is_percent' => $configurableAttributeData['is_percent'],
                        'pricing_value' => $configurableAttributeData['pricing_value'],
                        // 'default_label' => $configurableAttributeData['default_label'],
                        // 'store_label' => $configurableAttributeData['store_label'],
                        // 'use_default_value' => $configurableAttributeData['use_default_value']
                        );

                    $found = false;
                    $found_ECAD = '';
                    foreach ($existingConfigurableAttributesData as $keyECAD => $existingConfigurableAttributeData) {
                        if ($existingConfigurableAttributeData['attribute_id'] == $configurableAttributeData['attribute_id']){
                            $found_ECAD = $keyECAD;
                            if (!empty($existingConfigurableAttributeData['values'])){
                                foreach ($existingConfigurableAttributeData['values'] as $keyECAV => $attribute_values) {
                                    if ($attribute_values['value_index'] == $configurableAttributeData['value_index']){
                                        $found = true;
                                    }
                                }
                            }
                        }
                    }
                    
                    if (!$found){
                        $existingConfigurableAttributesData[$found_ECAD]['values'][] = $data_attribute;
                    }

                }
            
            }

            try{

                $conf_product->setConfigurableAttributesData($existingConfigurableAttributesData);
                $conf_product->setCanSaveConfigurableAttributes(true);
                $conf_product->save();
            
            }catch(\Exception $e){
            
                $this->debbug('## Error. Updating conf_product: '.$e->getMessage());
            
            }
            
            try{

                $old_childrenIds = $this->productConfigurableType->getChildrenIds($parent_product_id);
            
            }catch(\Exception $e){
            
                $this->debbug('## Error. Getting product childrens: '.$e->getMessage());
            }
                
            if (!empty($parent_data['form_product_ids'])){
            
                $children_modified = false;
                foreach ($parent_data['form_product_ids'] as $key => $form_product_id) {
                    if (!in_array($form_product_id, $old_childrenIds[0])) {
                        $old_childrenIds[0][] = $form_product_id;
                        $children_modified = true;
                    }
                }

                if ($children_modified){
                    
                    try{

                        $this->productConfigurableType->saveProducts($conf_product, $old_childrenIds[0]);
                        
                    }catch(\Exception $e){
                    
                        $this->debbug('## Error. Updating product childrens: '.$e->getMessage());
                    
                    }
                    
                }

            }

            if (empty($old_childrenIds)){

                if ($conf_product->getTypeId() != $this->product_type_simple){
                   
                    $conf_product->setTypeId($this->product_type_simple);
                    
                    try{
                    
                        $conf_product->save();
                        $this->products_collection[$parent_product_id]['type_id'] = $this->product_type_simple;
                       
                    }catch(\Exception $e){
                       
                        $this->debbug('## Error. Updating product to simple: '.$e->getMessage());
                    
                    }

                }

            }

        }

    }

    /**
     * Function to prepare product format images to store.
     * @param  object $update_format                format object
     * @param  $array $sl_format_images             array of format images to organize
     * @return string                               format images to store
     */
    private function prepare_product_format_images_to_store ($update_format, $sl_format_images) {

        $format_modified = false;

        $update_format_id = $update_format->getEntityId();
        $update_format_sl_id = $this->products_collection[$update_format_id]['saleslayer_format_id'];

        $this->debbug(" > Storing product format images SL ID: $update_format_sl_id");
        
        $final_images = $existing_images = array();
        
        $time_ini_load_sl_images = microtime(1);
        
        $main_image = $small_image = $thumbnail = false;
        
        if (is_array($sl_format_images) && !empty($sl_format_images)){

            foreach ($sl_format_images as $image_key => $images) {

                foreach ($this->format_images_sizes as $img_format) {
                    
                    if (!empty($images[$img_format])){

                        $media_attribute = '';

                        $image_url = $images[$img_format];
                        
                        $image_url_info = pathinfo($image_url);
                        $image_filename = $image_url_info['filename'].'.'.$image_url_info['extension'];

                        $disabled = 0;

                        if (!$main_image && '_'.$img_format == $this->image_extension){
                            $main_image = true;
                            $media_attribute = array('image');
                        }

                        if ('_'.$img_format == $this->small_image_extension){

                            if (!$small_image){

                                $small_image = true;

                                if (is_array($media_attribute)){

                                    $media_attribute[] = 'small_image';

                                }else{

                                    $media_attribute = array('small_image');

                                }

                            }

                            if ($this->image_extension != $this->small_image_extension){

                                $disabled = 1;

                            }

                        }

                        if ('_'.$img_format == $this->thumbnail_extension){

                            if (!$thumbnail){

                                $thumbnail = 1;

                                if (is_array($media_attribute)){

                                    $media_attribute[] = 'thumbnail';

                                }else{

                                    $media_attribute = array('thumbnail');

                                }

                            }

                            if ($this->image_extension != $this->thumbnail_extension){

                                $disabled = 1;

                            }

                        }

                        $final_images[$image_filename] = array('url' => $image_url, 'media_attribute' => $media_attribute, 'disabled' => $disabled);

                    }

                }

            }

        }

        $this->debbug('# time_load_sl_images: '.(microtime(1) - $time_ini_load_sl_images).' seconds.', 'timer');

        $main_image_to_process = array();
        $main_image_processed = true;
        foreach ($final_images as $keyIMG => $final_image) {

            if (is_array($final_image['media_attribute']) && in_array('image', $final_image['media_attribute'])){

                $main_image_to_process = $final_image;
                $main_image_to_process['image_name'] = $keyIMG;
                $main_image_processed = false;
                break;
            }
        }
        
        $time_ini_check_existing = microtime(1);
        $existing_items = $update_format->getMediaGalleryEntries();
        $items_modified = false;
        $existing_images_to_modify = array('delete' => array(), 'update' => array());

        if (!empty($existing_items)){

            foreach ($existing_items as $keyItem => $item) {
                
                $time_ini_item_check = microtime(1);
                
                $item_data = $item->getData();
                if (!is_array($item_data['types'])){ $item_data['types'] = array(); }
                if (!empty($item_data['types'])){ asort($item_data['types']); }
                $this->debbug('# time_item_get_data: '.(microtime(1) - $time_ini_item_check).' seconds.', 'timer');
            
                $time_ini_item_parse = microtime(1);
                $parse_url_item = pathinfo($item_data['file']);
                $item_url = $this->product_path_base.$item_data['file'];
                $this->debbug('# time_item_parse: '.(microtime(1) - $time_ini_item_parse).' seconds.', 'timer');
                
                $time_ini_item_md5 = microtime(1);
                $md5_item = $this->verify_md5_image_url($item_url);
                $this->debbug('# time_item_md5: '.(microtime(1) - $time_ini_item_md5).' seconds.', 'timer');
                $item_filename = $parse_url_item['filename'].'.'.$parse_url_item['extension'];

                if ($md5_item){ 
                    
                    if (isset($final_images[$item_filename])){
                        
                        $time_ini_image_md5 = microtime(1);
                        $md5_image = $this->verify_md5_image_url($final_images[$item_filename]['url']);
                        $this->debbug('# time_image_md5: '.(microtime(1) - $time_ini_image_md5).' seconds.', 'timer');
                        
                        if ($md5_image && $md5_image == $md5_item){

                            $image_media_attribute = $final_images[$item_filename]['media_attribute'];
                            if (!is_array($image_media_attribute)){ 
                                if ($image_media_attribute == ''){
                                    $image_media_attribute = array();
                                }else{
                                    $image_media_attribute = array($image_media_attribute); 
                                }
                            }
                            if (!empty($image_media_attribute)){ asort($image_media_attribute); }
                            
                            $time_ini_mod_item = microtime(1);


                            if ($item_data['types'] != $image_media_attribute){

                                if (!$main_image_processed && $main_image_to_process['image_name'] == $item_filename){

                                    $this->galleryProcessor->removeImage($update_format, $item_data['file']);
                                    $items_modified = true;


                                }else{

                                    $existing_images_to_modify['delete'][] = $item_data['id'];

                                }
                                
                                $this->debbug('# time_item_check: '.(microtime(1) - $time_ini_item_check).' seconds.', 'timer');
                                continue;

                            }

                            if ($item_data['disabled'] != $final_images[$item_filename]['disabled']){

                                if (!$main_image_processed && $main_image_to_process['image_name'] == $item_filename){
                                    
                                    $this->galleryProcessor->updateImage($update_format, $item_data['file'], array('disabled' => $final_images[$item_filename]['disabled']));
                                    $main_image_processed = true;
                                    $main_image_to_process = array();
                                    $items_modified = true;

                                }else{

                                    $existing_images_to_modify['update'][$item_data['id']]['disabled'] = $final_images[$item_filename]['disabled'];

                                }

                            }

                            $this->debbug('# time_item_check: '.(microtime(1) - $time_ini_item_check).' seconds.', 'timer');
                            $this->debbug('# time_mod_item: '.(microtime(1) - $time_ini_mod_item).' seconds.', 'timer');
                            unset($final_images[$item_filename]);
                            continue;

                        }

                    }

                }    
                
                $existing_images_to_modify['delete'][] = $item_data['id'];
                $items_modified = true;
                $this->debbug('# time_item_check: '.(microtime(1) - $time_ini_item_check).' seconds.', 'timer');

            }

        }

        $this->debbug('# time_check_existing: '.(microtime(1) - $time_ini_check_existing).' seconds.', 'timer');
        
        $time_ini_update_items = microtime(1);
        if ($items_modified){

            try{
            
                $update_format->save();
                $update_format = $this->load_format_model($update_format_id);
            
            }catch(\Exception $e){
            
                $this->debbug('## Error. Updating formats updated items: '.$e->getMessage());
            
            }

        }
        
        $this->debbug('# time_update_items: '.(microtime(1) - $time_ini_update_items).' seconds.', 'timer');

        if (!$main_image_processed){
            
            $image_filename = $main_image_to_process['image_name'];

            $time_ini_check_waste = microtime(1);
            $check_waste = $this->product_path_base.substr($image_filename, 0,1).'/'.substr($image_filename, 1,1).'/'.$image_filename;
            if (file_exists($check_waste)){ unlink($check_waste); }
            $this->debbug('# time_check_waste: '.(microtime(1) - $time_ini_check_waste).' seconds.', 'timer');

            $time_ini_prepare_image = microtime(1);
            $this->fileIo->checkAndCreateFolder($this->product_tmp_path_base.substr($image_filename, 0,1).'/'.substr($image_filename, 1,1).'/');
            // $new_file_name = $this->product_path_base.baseName($main_image_to_process['url']);
            $new_file_name = $this->product_tmp_path_base.substr($image_filename, 0,1).'/'.substr($image_filename, 1,1).'/'.$image_filename;
            $result = $this->fileIo->read($main_image_to_process['url'], $new_file_name);
            $exclude = $main_image_to_process['disabled'];
            
            if ($result) {
            
                try{
                                                
                    $update_format->addImageToMediaGallery($new_file_name, $main_image_to_process['media_attribute'], true, $exclude);
                
                } catch (\Exception $e) {
                
                    $this->debbug('## Error. Adding main format image: '.$e->getMessage());
                
                }

            }

            $this->debbug('# time_prepare_image: '.(microtime(1) - $time_ini_prepare_image).' seconds.', 'timer');

            $time_ini_save_product_format_images = microtime(1);
            
            try{
            
                $update_format->save();
                unset($final_images[$main_image_to_process['image_name']]);
            
            } catch (\Exception $e) {
            
                $this->debbug('## Error. Updating product format: '.$e->getMessage());
            
            }

            $this->debbug('# time_save_product_format_images: '.(microtime(1) - $time_ini_save_product_format_images).' seconds.', 'timer');

        }
        
        if (empty($existing_images_to_modify['delete']) && empty($existing_images_to_modify['update'])){

            $existing_images_to_modify = array();

        }

        if (!empty($final_images) || !empty($existing_images_to_modify)){

            $images_data['format_id'] = $update_format->getEntityId();

            if (!empty($final_images)){
                $images_data['final_images'] = $final_images;   
            }

            if (!empty($existing_images_to_modify)){
                $images_data['existing_images_to_modify'] = $existing_images_to_modify;   
            }

            try{

                $sql_query_to_insert = " INSERT INTO ".$this->saleslayer_syncdata_table.
                                                     " ( sync_type, item_type, item_data, sync_params ) VALUES ".
                                                     "('update', 'product__images', '".json_encode($images_data)."', '')";

                $this->connection->query($sql_query_to_insert);

            }catch(\Exception $e){

                $this->debbug('## Error. Insert syncdata SQL query: '.$sql_query_to_insert);
                $this->debbug('## Error. Insert syncdata SQL message: '.$e->getMessage());

            }

        }

    }

    /**
     * Function to get media field value
     * @param  string $type             type of table
     * @param  string $field_name       field name to extact media from data
     * @param  array $data              array containing media data
     * @return array or boolean         array or media values
     */
    protected function get_media_field_value($type, $field_name, $data){
        
        $media = array();

        if (in_array($field_name, $this->media_field_names[$type])){
            foreach ($data as $hash) {
                foreach ($hash as $file) {
                    array_push($media, $file);
                }
            }
        }

        if (!empty($media)){
            return $media;
        }else{
            return false;
        }

    }

    /**
     * Function to store media data from Sales Layer product additional attributes into variable.
     * @return void
     */
    protected function load_media_field_names(){
        
        $data_schema = json_decode($this->sl_data_schema, 1);

        foreach ($data_schema as $type => $type_schema) {
            
            foreach ($type_schema['fields'] as $field_name => $field_info) {
                
                if ($field_info['type'] == 'image'){

                    $this->media_field_names[$type][] = $field_name;

                }

            }

        }

    }

    /**
     * Function to organize Sales Layer tables if they're multilingual.
     * @param  array $tables                tables to organize
     * @param  array $tableStructure        structure of the tables
     * @return array $tables                tables organized
     */
    protected function organizeTablesIndex($tables, $tableStructure){
        
        foreach ($tableStructure as $keyStruct => $fieldStruct) {
            if (isset($fieldStruct['multilingual_name'])){
                foreach ($tables as $keyTab => $fieldTable) {
                    if (array_key_exists($fieldStruct['multilingual_name'], $fieldTable['data'])){
                        $tables[$keyTab]['data'][$keyStruct] = $tables[$keyTab]['data'][$fieldStruct['multilingual_name']];
                        unset($tables[$keyTab]['data'][$fieldStruct['multilingual_name']]);
                    }
                }   
            }
        }
    
        return $tables;
    
    }

    /**
     * Function to check ir the url exists.
     * @param  string $url      of the image
     * @return boolean            if the url exists
     */
    protected function url_exists ($url) {

        $handle = @fopen($url, 'r');

        if ($handle === false) { return false; }

        fclose($handle);
        return true;
    }

    /**
     * Function that stores the url image.
     * @param  string $image_url of the image
     * @param  string $path_base  of the image
     * @param  string $returnFilepath
     * @return string            local path of the image
     */
    protected function prepareImage ($image_url, $path_base, $returnFilepath = true) {

        //Replace https to http.
        $image_url  = str_replace('https://', 'http://', $image_url); 

        if ($this->sl_DEBBUG > 2) $this->debbug(" > Importing image: $image_url");

        if ($this->url_exists($image_url)) {

            $image_content_str = @file_get_contents(trim($image_url));

            if ($image_content_str) {

                if (!file_exists($path_base)) {
                    mkdir($path_base, 0777, true);
                }

                $parse_image_url = pathinfo($image_url);
                $filename = $parse_image_url['filename'].'.'.$parse_image_url['extension'];

                $filepath  = $path_base.$filename;
                //Store the image from external url to the temp storage folder.
                file_put_contents($filepath, $image_content_str); 

                if ($this->sl_DEBBUG > 2) $this->debbug(" Image saved in: $filepath");

                return ($returnFilepath) ? $filepath : $filename;
            
            }

        }

        return null;
    }

    /**
     * Function that returns the Sales Layer category from a Magento category.
     * @param  int $saleslayer_id               id of the category that you want to find
     * @param  int $saleslayer_comp_id          id of the company that you want to find 
     * @param  string $store_view_id            store view id to synchronize 
     * @return Mage_Catalog_Model_Category      Magento category that corresponds to the Sales Layer id category wanted
     */
    protected function findSaleslayerCategoryId ($saleslayer_id, $saleslayer_comp_id = null, $store_view_id = null) {

        if (!empty($this->categories_collection)){

            $category_id = $category_id_temp = '';

            foreach ($this->categories_collection as $category_col) {
                
                if (isset($category_col['saleslayer_id']) && $category_col['saleslayer_id'] == $saleslayer_id){

                    if (isset($category_col['saleslayer_comp_id'])){

                        $category_saleslayer_comp_id = $category_col['saleslayer_comp_id'];
                    
                    }else{

                        $category_saleslayer_comp_id = '';

                    }
                    
                    if (!in_array($category_saleslayer_comp_id, array(0, '', null))){

                        if ($category_saleslayer_comp_id != $saleslayer_comp_id){
        
                            //The category belongs to another company.
                            continue;

                        }else{
        
                            //The category matches;
                            $category_id = $category_col['entity_id'];
                            break;
                            
                        }

                    }else{

                        //The category matches the identificator and it's without company.
                        $category_id_temp = $category_col['entity_id'];
                        continue;

                    }

                }
                
            }
            
            if ($category_id == '' && $category_id_temp != ''){

                $category_id = $category_id_temp;

            }

            if ($category_id != ''){

                $category = $this->load_category_model($category_id, $store_view_id);
                return $category;

            }

        }

        return null;
       
    }

    /**
     * Function that returns a product that corresponds to a Sales Layer id.
     * @param  int $saleslayer_id               id of the product that you want to find 
     * @param  int $saleslayer_comp_id          id of the company that you want to find 
     * @param  boolean $configurable          find if the product is configurable or not
     * @param  string $store_view_id            store view id to synchronize 
     * @return Mage_Catalog_Model_Product   Product found
     */
    protected function findSaleslayerProductId ($saleslayer_id, $saleslayer_comp_id = null, $configurable = false, $store_view_id = null) {

        if (!empty($this->products_collection)){

            $product_id = $product_id_temp = '';

            foreach ($this->products_collection as $product_col) {

                if (isset($product_col['saleslayer_format_id']) && !in_array($product_col['saleslayer_format_id'], array(0, '', null))){

                    continue;

                }

                if ($configurable && $product_col['type_id'] != $this->product_type_configurable){
        
                    continue; 
        
                }
                
                if (isset($product_col['saleslayer_id']) && $product_col['saleslayer_id'] == $saleslayer_id){

                    if (isset($product_col['saleslayer_comp_id'])){

                        $product_saleslayer_comp_id = $product_col['saleslayer_comp_id'];
                    
                    }else{

                        $product_saleslayer_comp_id = '';

                    }
                    
                    if (!in_array($product_saleslayer_comp_id, array(0, '', null))){

                        if ($product_saleslayer_comp_id != $saleslayer_comp_id){
        
                            //The product belongs to another company.
                            continue;

                        }else{
        
                            //The product matches;
                            $product_id = $product_col['entity_id'];
                            break;
                            
                        }

                    }else{

                        //The product matches the identificator and it's without company.
                        $product_id_temp = $product_col['entity_id'];
                        continue;

                    }

                }
                
            }
            
            if ($product_id == '' && $product_id_temp != ''){

                $product_id = $product_id_temp;

            }

            if ($product_id != ''){

                $product = $this->load_product_model($product_id, $store_view_id);
                return $product;

            }

        }

        return null;
       
    }

    /**
     * Function that returns a product that corresponds to a Sales Layer id.
     * @param  int $saleslayer_id           if of the product that you want to find
     * @param  int $saleslayer_comp_id      id of the company that you want to find
     * @param  int $saleslayer_format_id    id of the product format that you want to find
     * @return Mage_Catalog_Model_Product   Product found
     */
    protected function findSaleslayerProductFormatId ($saleslayer_id = null, $saleslayer_comp_id = null, $saleslayer_format_id = false, $store_view_id = null) {

        if (!empty($this->products_collection)){

            $format_id = $format_id_temp = '';

            foreach ($this->products_collection as $product_col) {

                if ($product_col['type_id'] != $this->product_type_simple && $product_col['type_id'] != $this->product_type_virtual){
                
                    //If the product type is not simple, we skip it.
                    continue;
                
                }
                
                // if (isset($product_col['saleslayer_id']) && $product_col['saleslayer_id'] == $saleslayer_id && isset($product_col['saleslayer_format_id']) && $product_col['saleslayer_format_id'] == $saleslayer_format_id){
                if (isset($product_col['saleslayer_format_id']) && $product_col['saleslayer_format_id'] == $saleslayer_format_id){

                    if (!is_null($saleslayer_id)){
                        
                        if (!isset($product_col['saleslayer_id']) || (isset($product_col['saleslayer_id']) && $product_col['saleslayer_id'] != $saleslayer_id)){
                        
                            continue;

                        } 

                    }

                    if (isset($product_col['saleslayer_comp_id'])){

                        $format_saleslayer_comp_id = $product_col['saleslayer_comp_id'];
                    
                    }else{

                        $format_saleslayer_comp_id = '';

                    }
                    
                    if (!in_array($format_saleslayer_comp_id, array(0, '', null))){

                        if ($format_saleslayer_comp_id != $saleslayer_comp_id){
                
                            //The product format belongs to another company.
                            continue;

                        }else{
                
                            //The product format matches;
                            $format_id = $product_col['entity_id'];
                            break;
                            
                        }

                    }else{

                        //The product format matches the identificator and it's without company.
                        $format_id_temp = $product_col['entity_id'];
                        continue;

                    }

                }
                
            }
            
            if ($format_id == '' && $format_id_temp != ''){

                $format_id = $format_id_temp;

            }

            if ($format_id != ''){

                $format = $this->load_format_model($format_id, $store_view_id);
                return $format;

            }

        }

        return null;
       
    }

    /**
     * Function to check if SKU already exists on another product or product format than the one we're synchronizing.
     * @param  string $type                     product or product format
     * @param  string $sl_sku                   SKU of the product that we want to check
     * @param  int $saleslayer_id               id of the product that you want to check 
     * @param  int $saleslayer_comp_id          id of the company that you want to check
     * @return boolean                          if the SKU already exists on another product
     */
    private function checkDuplicatedSKU ($type, $sl_sku, $saleslayer_id, $saleslayer_comp_id) {
        
        if (!empty($this->products_collection)){

            if (isset($this->products_collection_skus[$sl_sku])){

                foreach ($this->products_collection_skus[$sl_sku] as $product_id){

                    $product_col = $this->products_collection[$product_id];
                    
                    $existing_product_saleslayer_id = $existing_product_saleslayer_comp_id = 0;
                    if (isset($product_col['saleslayer_id'])){ $existing_product_saleslayer_id = $product_col['saleslayer_id']; }
                    if (in_array($existing_product_saleslayer_id, array('', null))){ $existing_product_saleslayer_id = 0; }
                    if (isset($product_col['saleslayer_comp_id'])){ $existing_product_saleslayer_comp_id = $product_col['saleslayer_comp_id']; }
                    if (in_array($existing_product_saleslayer_comp_id, array('', null))){ $existing_product_saleslayer_comp_id = 0; }

                    if (($existing_product_saleslayer_id != 0 && $saleslayer_id != $existing_product_saleslayer_id) || ($existing_product_saleslayer_comp_id != 0 && $saleslayer_comp_id != $existing_product_saleslayer_comp_id)){
                        
                        if ($type == 'product'){
                            
                            $this->debbug("## Error. The product with SKU ".$sl_sku." hasn't been synchronized because the same SKU is already in use.");
                            
                        }else{
                        
                            $this->debbug("## Error. The product format with SKU ".$sl_sku." hasn't been synchronized because the same SKU is already in use.");
                            
                        }
                        
                        return true;

                    }

                }

            }

        }

        return false;

    }

    /**
     * Function to check if the name already exists on another product or product format than the one we're synchronizing.
     * @param  string $type                     product or product format
     * @param  string $sl_name                  name of the product that we want to check
     * @param  int $saleslayer_id               id of the product that you want to check 
     * @param  int $saleslayer_comp_id          id of the company that you want to check
     * @return boolean                          if the name already exists on another product
     */
    private function checkDuplicatedName ($type, $sl_name, $saleslayer_id, $saleslayer_comp_id) {
        
        if (!empty($this->products_collection)){

            if (isset($this->products_collection_names[$sl_name])){

                foreach ($this->products_collection_names[$sl_name] as $product_id){

                    $product_col = $this->products_collection[$product_id];
                
                    $existing_product_saleslayer_id = $existing_product_saleslayer_comp_id = 0;
                    if (isset($product_col['saleslayer_id'])){ $existing_product_saleslayer_id = $product_col['saleslayer_id']; }
                    if (in_array($existing_product_saleslayer_id, array('', null))){ $existing_product_saleslayer_id = 0; }
                    if (isset($product_col['saleslayer_comp_id'])){ $existing_product_saleslayer_comp_id = $product_col['saleslayer_comp_id']; }
                    if (in_array($existing_product_saleslayer_comp_id, array('', null))){ $existing_product_saleslayer_comp_id = 0; }

                    if (($existing_product_saleslayer_id != 0 && $saleslayer_id != $existing_product_saleslayer_id) || ($existing_product_saleslayer_comp_id != 0 && $saleslayer_comp_id != $existing_product_saleslayer_comp_id)){

                        if ($type == 'product'){

                            $this->debbug("## Error. The product with name ".$sl_name." hasn't been synchronized because the same name is already in use.");
                        
                        }else{
                        
                            $this->debbug("## Error. The product format with name ".$sl_name." hasn't been synchronized because the same name is already in use.");
                        
                        }
                        
                        return true;

                    }

                }

            }

        }

        return false;

    }

    /**
     * Function to delete a stored category.
     * @param  string $sl_id        category id to delete
     * @return string               category deleted or not
     */
    protected function delete_stored_category ($sl_id) {

        $this->debbug('Deleting category with SL id: '.$sl_id.' comp_id: '.$this->comp_id);
        $category = $this->findSaleslayerCategoryId($sl_id, $this->comp_id);
            
        if (!is_null($category)){

            $deletedMessage = "The category with title ".$category->getName();

            try{

                $delete_category = true;

                if (isset($this->sl_multiconn_table_data['category'][$sl_id]) && !empty($this->sl_multiconn_table_data['category'][$sl_id]['sl_connectors'])){

                    $conn_found = array_search($this->processing_connector_id, $this->sl_multiconn_table_data['category'][$sl_id]['sl_connectors']);

                    if (is_numeric($conn_found)){

                        unset($this->sl_multiconn_table_data['category'][$sl_id]['sl_connectors'][$conn_found]);

                        if (empty($this->sl_multiconn_table_data['category'][$sl_id]['sl_connectors'])){

                            $query_delete = " DELETE FROM ".$this->saleslayer_multiconn_table." WHERE id = ? ";

                            // $this->execute_multiconn_sql($query_delete, array($this->sl_multiconn_table_data['category'][$sl_id]['id']));

                            $this->sl_connection_query($query_delete, array($this->sl_multiconn_table_data['category'][$sl_id]['id']));

                        }else{

                            $new_connectors_data = json_encode($this->sl_multiconn_table_data['category'][$sl_id]['sl_connectors']);

                            $query_update  = " UPDATE ".$this->saleslayer_multiconn_table." SET sl_connectors = ?  WHERE id = ? ";

                            // $this->execute_multiconn_sql($query_update, array($new_connectors_data, $this->sl_multiconn_table_data['category'][$sl_id]['id']));

                            $this->sl_connection_query($query_update, array($new_connectors_data, $this->sl_multiconn_table_data['category'][$sl_id]['id']));

                            $delete_category = false;
                        }

                    }else{

                        $delete_category = false;

                    }

                }

                if (!$delete_category){

                    $this->debbug("## Error. ".$deletedMessage. " couldn't been deleted because is being used by another connector.");
                
                }else{

                    $category->setData('saleslayer_id', '');
                    $category->setData('saleslayer_comp_id', '');
                    $category->setIsActive(0);
                    $category->save();

                    $this->deleted_stored_categories_ids[] = $category->getEntityId();
                    $this->categories_collection[$category->getEntityId()]['is_active'] = 0;
                    
                    if (!empty($this->all_store_view_ids)){

                        $category_id = $category->getEntityId();

                        foreach ($this->all_store_view_ids as $store_view_id){

                            if ($store_view_id == 0){ continue; }
                            
                            $category = $this->load_category_model($category_id, $store_view_id);
                            $category->setData('saleslayer_id', '');
                            $category->setData('saleslayer_comp_id', '');
                            if (!$this->category_enabled_attribute_is_global){
                            
                                $category->setIsActive(0);
                            
                            }

                            $category->save();
                            
                        }

                    }
                    
                    $this->debbug($deletedMessage." has been deleted.");
                    
                }

                return 'item_deleted';

            }catch(\Exception $e){

                $this->debbug("## Error. ".$deletedMessage. " couldn't been deleted - ".$e->getMessage());
                return 'item_not_deleted';

            }

        }else{

            $this->debbug("## Error. The category doesn't exist.");
            return 'item_not_found';

        }

        
    }

    /**
     * Function to rorganize categories path and parents after delete.
     * @param  string $sl_id        product id to delete
     * @return string               product deleted or not
     */
    protected function reorganize_categories_after_delete(){

        //Process to reorganize the category tree avoiding disabled categories just eliminated.
        if (!empty($this->deleted_stored_categories_ids)){

            foreach ($this->categories_collection as $category_col) {
                
                if (in_array($category_col['parent_id'], $this->deleted_stored_categories_ids) && $category_col['is_active'] == 1){

                    $path_ids = explode('/', $category_col['path']);
                    $new_path = $parent_id = '';

                    foreach ($path_ids as $path_id){
                        
                        if (in_array($path_id, $this->deleted_stored_categories_ids)){

                            continue;

                        }

                        $new_path .= $path_id;
                    
                        if ($path_id != end($path_ids)){ 

                            $new_path .= '/'; 
                            $parent_id = $path_id;

                        }

                    }

                    try{

                        $category = $this->load_category_model($category_col['entity_id']);
                        $category->setPath($new_path);
                        $category->setParentId($parent_id);
                        $category->save();
                        $this->categories_collection[$category_col['entity_id']]['path'] = $new_path;
                        $this->categories_collection[$category_col['entity_id']]['parent_id'] = $parent_id;

                    }catch(\Exception $e){

                        $this->debbug('## Error. Reorganizing category tree: '.$e->getMessage());

                    }

                }

            }

        }


    }

    /**
     * Function to delete a stored product.
     * @param  string $sl_id        product id to delete
     * @return string               product deleted or not
     */
    protected function delete_stored_product($sl_id){

        $this->debbug('Deleting product with SL id: '.$sl_id.' comp_id: '.$this->comp_id);
        $product = $this->findSaleslayerProductId($sl_id, $this->comp_id);
        
        if (!is_null($product)){

            $deletedMessage = "The product with SKU ".$product->getSku()." and title: ".$product->getName();

            try{

                $delete_product = true;

                if (isset($this->sl_multiconn_table_data['product'][$sl_id]) && !empty($this->sl_multiconn_table_data['product'][$sl_id]['sl_connectors'])){

                    $conn_found = array_search($this->processing_connector_id, $this->sl_multiconn_table_data['product'][$sl_id]['sl_connectors']);

                    if (is_numeric($conn_found)){

                        unset($this->sl_multiconn_table_data['product'][$sl_id]['sl_connectors'][$conn_found]);

                        if (empty($this->sl_multiconn_table_data['product'][$sl_id]['sl_connectors'])){

                            $query_delete = " DELETE FROM ".$this->saleslayer_multiconn_table." WHERE id = ?";

                            // $this->execute_multiconn_sql($query_delete, array($this->sl_multiconn_table_data['product'][$sl_id]['id']));

                            $this->sl_connection_query($query_delete, array($this->sl_multiconn_table_data['product'][$sl_id]['id']));

                        }else{

                            $new_connectors_data = json_encode($this->sl_multiconn_table_data['product'][$sl_id]['sl_connectors']);

                            $query_update  = " UPDATE ".$this->saleslayer_multiconn_table." SET sl_connectors = ?  WHERE id = ? ";

                            // $this->execute_multiconn_sql($query_update, array($new_connectors_data, $this->sl_multiconn_table_data['product'][$sl_id]['id']));

                            $this->sl_connection_query($query_update, array($new_connectors_data, $this->sl_multiconn_table_data['product'][$sl_id]['id']));

                            $delete_product = false;

                        }

                    }else{

                        $delete_product = false;

                    }

                }

                if (!$delete_product){

                    $this->debbug("## Error. ".$deletedMessage. " couldn't been deleted because is being used by another connector.");
                
                }else{

                    $product->setData('saleslayer_id', '');
                    $product->setData('saleslayer_comp_id', '');
                    $product->setStatus($this->status_disabled);
                    $product->save();

                    $this->products_collection[$product->getEntityId()]['status'] = $this->status_disabled;
                    
                    if (!empty($this->all_store_view_ids)){

                        $product_id = $product->getEntityId();
                        
                        foreach ($this->all_store_view_ids as $store_view_id){

                            if ($store_view_id == 0){ continue; }
                            
                            $product = $this->load_product_model($product_id, $store_view_id);
                            $product->setData('saleslayer_id', '');
                            $product->setData('saleslayer_comp_id', '');
                            if (!$this->product_enabled_attribute_is_global){
                            
                                $product->setStatus($this->status_disabled);
                            
                            }

                            $product->save();
                            
                        }

                    }

                    $this->debbug($deletedMessage." has been deleted.");
                    
                }

                return 'item_deleted';
                
            }catch(\Exception $e){

                $this->debbug("## Error. ".$deletedMessage. " couldn't been deleted - ".$e->getMessage());
                return 'item_not_deleted';

            }

        }else{

            $this->debbug("## Error. The product doesn't exist.");
            return 'item_not_found';

        }

    }

    /**
     * Function to delete a stored product format.
     * @param  string $sl_id        format id to delete
     * @return string               format deleted or not
     */
    protected function delete_stored_product_format($sl_id){

        $this->debbug('Deleting product format with SL id: '.$sl_id.' comp_id: '.$this->comp_id);
        $format = $this->findSaleslayerProductFormatId(null, $this->comp_id, $sl_id);
        
        if (!is_null($format)){

            $deletedMessage = "The product format with SKU ".$format->getSku()." and title: ".$format->getName();

            try{

                $delete_format = true;

                if (isset($this->sl_multiconn_table_data['format'][$sl_id]) && !empty($this->sl_multiconn_table_data['format'][$sl_id]['sl_connectors'])){

                    $conn_found = array_search($this->processing_connector_id, $this->sl_multiconn_table_data['format'][$sl_id]['sl_connectors']);

                    if (is_numeric($conn_found)){

                        unset($this->sl_multiconn_table_data['format'][$sl_id]['sl_connectors'][$conn_found]);

                        if (empty($this->sl_multiconn_table_data['format'][$sl_id]['sl_connectors'])){

                            $query_delete = " DELETE FROM ".$this->saleslayer_multiconn_table." WHERE id = ?";

                            // $this->execute_multiconn_sql($query_delete, array($this->sl_multiconn_table_data['format'][$sl_id]['id']));

                            $this->sl_connection_query($query_delete, array($this->sl_multiconn_table_data['format'][$sl_id]['id']));

                        }else{

                            $new_connectors_data = json_encode($this->sl_multiconn_table_data['format'][$sl_id]['sl_connectors']);

                            $query_update  = " UPDATE ".$this->saleslayer_multiconn_table." SET sl_connectors =  ?  WHERE id =  ? ";

                            // $this->execute_multiconn_sql($query_update, array($new_connectors_data, $this->sl_multiconn_table_data['format'][$sl_id]['id']));

                            $this->sl_connection_query($query_update, array($new_connectors_data, $this->sl_multiconn_table_data['format'][$sl_id]['id']));

                            $delete_format = false;

                        }
                    
                    }else{

                        $delete_format = false;

                    }

                }

                if (!$delete_format){

                    $this->debbug("## Error. ".$deletedMessage. " couldn't been deleted because is being used by another connector.");
                
                }else{

                    $format->setData('saleslayer_id', '');
                    $format->setData('saleslayer_comp_id', '');
                    $format->setData('saleslayer_format_id', '');
                    $format->setStatus($this->status_disabled);
                    $format->save();

                    $format_id = $format->getEntityId();

                    $this->products_collection[$format_id]['status'] = $this->status_disabled;
                    
                    if (!empty($this->all_store_view_ids)){

                        foreach ($this->all_store_view_ids as $store_view_id){

                            if ($store_view_id == 0){ continue; }
                            
                            $format = $this->load_format_model($format_id, $store_view_id);
                            $format->setData('saleslayer_id', '');
                            $format->setData('saleslayer_comp_id', '');
                            $format->setData('saleslayer_format_id', '');
                            if (!$this->product_enabled_attribute_is_global){
                            
                                $format->setStatus($this->status_disabled);
                            
                            }

                            $format->save();
                            
                        }

                    }

                    $parent_ids = $this->productConfigurableType->getParentIdsByChild($format_id);
                    
                    if (!empty($parent_ids)){
                        
                        foreach ($parent_ids as $parent_id) {
                            
                            $childrenIds = $this->productConfigurableType->getChildrenIds($parent_id);
                            
                            if (in_array($format_id, $childrenIds[0])) {
                            
                                unset($childrenIds[0][$format_id]);
                                $conf_product = $this->load_product_model($parent_id);
                                
                                if (count($childrenIds[0]) == 0){
                                
                                    $conf_product->setTypeId($this->product_type_simple);
                                    $conf_product->save();
                                    
                                }else{

                                    $this->productConfigurableType->saveProducts($conf_product, $childrenIds[0]);

                                }
                            
                            }

                        }

                    }

                    $this->debbug($deletedMessage." has been deleted.");

                }
                
                return 'item_deleted';
                
            }catch(\Exception $e){

                $this->debbug("## Error. ".$deletedMessage. " couldn't been deleted - ".$e->getMessage());
                return 'item_not_deleted';

            }

        }else{

            $this->debbug("## Error. The product format doesn't exist.");
            return 'item_not_found';

        }

    }

    /**
     * Function to sort images by dimension.
     * @param array $img_a      first image to sort
     * @param array $img_b      second image to sort
     * @return array            comparative of the images
     */
    protected function sortByDimension ($img_a, $img_b) {

        $area_a = $img_a['width'] * $img_a['height'];
        $area_b = $img_b['width'] * $img_b['height'];

        return strnatcmp($area_b, $area_a);
    }

    /**
     * Function to order an array of images.
     * @param array $array_img      images to order
     * @return array            array of ordered images
     */
    protected function order_array_img ($array_img) {

        $has_ORG = false;
            
        if (isset($array_img['ORG'])){
        
            if (count($array_img) == 1){
        
                return $array_img;

            }
        
            $has_ORG = true;
            unset($array_img['ORG']);
        
        }
        
        if (!empty($array_img) && count($array_img) > 1){
    
            uasort($array_img, array($this, "sortByDimension"));

        }
        
        if ($has_ORG){
        
            $array_img = array('ORG' => array()) + $array_img;
        
        }
        
        return $array_img;

    }

    /**
     * Function to verify an image url.
     * @param string $url         image url to validate
     * @return $md5_image         md5 of image url or false
     */
    protected function verify_md5_image_url($url){

        $md5_image = false;

        try{

            $md5_image = md5_file($url);

        }catch(\Exception $e){

            $this->debbug("## Error. The image with URL ".$url." couldn't been synchronized.");

        }

        return $md5_image;

    }

    /**
     * Function to get Sales Layer root Category.
     * @return void
     */
    protected function getSalesLayerRootCategory(){

        if (!empty($this->categories_collection)){

            if (isset($this->categories_collection_names['Sales Layer'])){

                $this->saleslayer_root_category_id = $this->categories_collection[reset($this->categories_collection_names['Sales Layer'])]['entity_id'];
                
            }

        }

    }

    /**
     * Function to load the categories collection into a class variable.
     * @return void
     */
    public function load_categories_collection(){

        $categories_collection = $this->category_model->getCollection()
                                                        ->addAttributeToSelect(array('name', 'is_active', 'saleslayer_id', 'saleslayer_comp_id'))
                                                        ->setStoreId(0);

        if (!empty($categories_collection)){

            foreach ($categories_collection as $category) {
                
                $category_id = $category->getEntityId();

                $this->categories_collection[$category_id] = array('entity_id' => $category_id,
                                                                    'name' => $category->getName(),
                                                                    'parent_id' => $category->getParentId(),
                                                                    'path'      => $category->getPath(),
                                                                    'is_active' => $category->getIsActive(),
                                                                    'saleslayer_id' => $category->getSaleslayerId(),
                                                                    'saleslayer_comp_id' => $category->getSaleslayerCompId());

                $this->categories_collection_names[$category->getName()][$category_id] = $category_id;
        
            }

        }

    }

    /**
     * Function to load the products collection into a class variable.
     * @return void
     */
    public function load_products_collection(){

        $products_collection = $this->product_model->getCollection()
                                                        ->addAttributeToSelect(array('name', 'saleslayer_id', 'saleslayer_comp_id', 'saleslayer_format_id'))
                                                        ->setStoreId(0);
        
        if (!empty($products_collection)){

            foreach ($products_collection as $product) {
                
                $product_id = $product->getEntityId();

                $this->products_collection[$product_id] = array('entity_id' => $product_id,
                                                                'name' => $product->getName(),
                                                                'status' => $product->getStatus(),
                                                                'sku' => $product->getSku(),
                                                                'type_id' => $product->getTypeId(),
                                                                'saleslayer_id' => $product->getSaleslayerId(),
                                                                'saleslayer_comp_id' => $product->getSaleslayerCompId(),
                                                                'saleslayer_format_id' => $product->getSaleslayerFormatId());

                $this->products_collection_skus[$product->getSku()][$product_id] = $product_id;
                $this->products_collection_names[$product->getName()][$product_id] = $product_id;
        
            }

        }

    }
    
    /**
     * Function to create category, product and format models and set them into class variables.
     * @param  boolean $force_category  forces the clone of the category model
     * @param  boolean $force_product   forces the clone of the product model
     * @param  boolean $force_format    forces the clone of the format model
     * @return void
     */
    protected function load_models($force_category = true, $force_product = true, $force_format = true){

        if ($force_category){

            $this->category_model = clone $this->categoryModel;

        }

        if ($force_product){

            $this->product_model = clone $this->productModel;

            if ($this->entity_type == ''){

                $this->entity_type = $this->product_model->getResource()->getTypeId();

            }

            $this->product_model->setIsMassupdate(true);
            $this->product_model->setExcludeUrlRewrite(true);

        }

        if ($force_format){

            $this->format_model = clone $this->productModel;
            
        }

    }

    /**
     * Function to load the category model by id and store.
     * @param category_id               id of the category to load
     * @param store_view_id             id of the store to load
     * @return category                 model loaded
     */
    private function load_category_model($category_id = null, $store_view_id = null){

        if (is_null($store_view_id)){ 

            $store_view_id = 0;

        }

        $this->category_model->unsetData();
        $category = $this->category_model;
        // $category->clearInstance();
        $category->setStoreId($store_view_id);

        if (!is_null($category_id)){

            $category->load($category_id);

        }

        return $category;

    }

    /**
     * Function to load the product model by id and store.
     * @param product_id                id of the product to load
     * @param store_view_id             id of the store to load
     * @return product                  model loaded
     */
    private function load_product_model($product_id = null, $store_view_id = null){

        if (is_null($store_view_id)){ 

            $store_view_id = 0;

        }
        
        $this->product_model->unsetData();
        $product = $this->product_model;
        // $product->clearInstance();
        // $product->clearCache();
        $product->setData('ignore_links_flag', true);
        $product->setStoreId($store_view_id);
        
        if ($this->get_product_type_id($product_id) == $this->product_type_configurable){
        
            $product->setTypeId($this->product_type_configurable);
        
        }
        
        if (!is_null($product_id)){

            $product->load($product_id);
        
        }

        return $product;

    }

    /**
     * Function to load the configurable product model by id and store.
     * @param product_id                id of the product to load
     * @param store_view_id             id of the store to load
     * @return product                  model loaded
     */
    private function load_product_model_configurable($product_id = null, $store_view_id = null){

        if (is_null($store_view_id)){ 

            $store_view_id = 0;

        }
        
        $this->product_model->unsetData();
        // $this->product_model->clearInstance();
        $product = $this->product_model;
        // $product->clearInstance();
        $product->cleanCache();
        $product->setData('ignore_links_flag', true);
        $product->setStoreId($store_view_id);
        
        if ($this->get_product_type_id($product_id) == $this->product_type_configurable){
        
            $product->setTypeId($this->product_type_configurable);
        
        }
        
        if (!is_null($product_id)){

            $product->load($product_id);
        
        }

        return $product;

    }

    /**
     * Function to load the format model by id and store.
     * @param format_id                 id of the format to load
     * @param store_view_id             id of the store to load
     * @return format                   model loaded
     */
    private function load_format_model($format_id = null, $store_view_id = null){

        if (is_null($store_view_id)){ 

            $store_view_id = 0;

        }

        $this->format_model->unsetData();
        $format = $this->format_model;
        // $format->clearInstance();
        $format->setStoreId($store_view_id);

        if (!is_null($format_id)){

            $format->load($format_id);

        }

        return $format;

    }

    /**
     * Function to get product id by sku.
     * @param sku                   product sku
     * @return product_id           product id or false
     */
    private function get_product_id_by_sku($sku){

        if (!empty($this->products_collection)){

            if (isset($this->products_collection_skus[$sku])){

                return reset($this->products_collection_skus[$sku]);

            }

        }

        return false;

    }

    /**
     * Function to get product type by id.
     * @param product_id_find           product id
     * @return product_type_id          product type id or false
     */
    private function get_product_type_id($product_id_find){

        if (!empty($this->products_collection)){

            if (isset($this->products_collection[$product_id_find])){

                return $this->products_collection[$product_id_find]['type_id'];

            }

        }

        return false;

    }

    /**
     * Function to load attributes by attribute set id into a class variable.
     * @param attribute_set_id          attribute set id to load its attributes
     * @return void
     */
    private function load_attributes_by_attribute_set_id($attribute_set_id){

        if (!isset($this->attributes_collection[$attribute_set_id])){

            $this->attributes_collection[$attribute_set_id] = array();

            $attributes = $this->productAttributeManagementInterface->getAttributes($attribute_set_id);
            
            foreach ($attributes as $attribute) {

                $this->attributes_collection[$attribute_set_id][$attribute->getId()] = array('attribute_id' => $attribute->getId(),
                                                                                            'attribute_code' => $attribute->getAttributeCode(),
                                                                                            'frontend_input' => $attribute->getFrontendInput());
                
                if (in_array($attribute->getFrontendInput(), array('select', 'multiselect'))){
                
                    if (!empty($this->store_view_ids)){

                        $store_view_ids = $this->store_view_ids;
                        if (!in_array(0, $store_view_ids)){ $store_view_ids[] = 0; }

                        foreach ($store_view_ids as $store_view_id) {
                
                            $optioncollection = clone $this->collectionOption;

                            $options = $optioncollection
                                ->setAttributeFilter($attribute->getAttributeId())
                                ->setStoreFilter($store_view_id,false)
                                ->load();

                            if (!empty($options->getData())){   

                                $this->attributes_collection[$attribute_set_id][$attribute->getId()]['options'][$store_view_id] = array();

                                foreach ($options->getData() as $option) {

                                    $this->attributes_collection[$attribute_set_id][$attribute->getId()]['options'][$store_view_id][strtolower($option['value'])] = $option['option_id'];

                                }

                            }

                        }

                    }

                }else{
                
                    continue; 
                
                }
                
            }

        }

    }

    /**
     * Function to reorganize categories by its parents
     *
     * @param array $categories         data to reorganize
     * @return array $new_categories    reorganized data
     */
    private function reorganizeCategories($categories){
            
        $new_categories = array();

        if (count($categories) > 0){

            $counter = 0;
            $first_level = $first_clean = true;
            $categories_loaded = array();
            
            do{

                $level_categories = $this->get_level_categories($categories, $categories_loaded, $first_level);
            
                if (!empty($level_categories)){

                    $counter = 0;
                    $first_level = false;

                    foreach ($categories as $keyCat => $category) {
                        
                        if (isset($level_categories[$category['id']])){
                            
                            array_push($new_categories, $category);
                            $categories_loaded[$category['id']] = 0;
                            unset($categories[$keyCat]);

                        }

                    }

                }else{

                    $counter++;

                }

                if ($counter == 3){
            
                    if ($first_clean && !empty($categories)){

                        $categories_not_loaded_ids = array_flip(array_column($categories, 'id'));
            
                        foreach ($categories as $keyCat => $category) {
                            
                            if (!is_array($category['catalogue_parent_id'])){
                            
                                $category_parent_ids = array($category['catalogue_parent_id']);
                            
                            }else{
                            
                                $category_parent_ids = array($category['catalogue_parent_id']);
                            
                            }

                            $has_any_parent = false;
                            
                            foreach ($category_parent_ids as $category_parent_id) {
                                
                                if (isset($categories_not_loaded_ids[$category_parent_id])){

                                    $has_any_parent = true;
                                    break;

                                } 

                            }

                            if (!$has_any_parent){

                                $category['catalogue_parent_id'] = 0;

                                array_push($new_categories, $category);
                                $categories_loaded[$category['id']] = 0;
                                unset($categories[$keyCat]);

                                $counter = 0;
                                $first_level = $first_clean = false;

                            }

                        }

                    }else{

                        break;

                    }

                }

            }while (count($categories) > 0);    
        
        }

        return $new_categories;

    }

    /**
     * Function to get categories by its root level
     *
     * @param  array $categories        categories to obtain by level
     * @param  array $categories_loaded categories already loaded
     * @param  boolean $first           first time checking this level
     * @return array $level_categories  categories that own to that level
     */
    private function get_level_categories($categories, $categories_loaded, $first = false){

        $level_categories = array();

        if ($first){

            foreach ($categories as $category) {
                
                if (!is_array($category['catalogue_parent_id']) && $category['catalogue_parent_id'] == 0){

                    $level_categories[$category['id']] = 0;
                
                }

            }

        }else{

            foreach ($categories as $category) {
                
                if (!is_array($category['catalogue_parent_id'])){
                    $category_parent_ids = array($category['catalogue_parent_id']);
                }else{
                    $category_parent_ids = array($category['catalogue_parent_id']);
                }

                $parents_loaded = true;
                foreach ($category_parent_ids as $category_parent_id) {
                    
                    if (!isset($categories_loaded[$category_parent_id])){

                        $parents_loaded = false;
                        break;
                    } 
                }

                if ($parents_loaded){

                    $level_categories[$category['id']] = 0;

                }

            }

        }

        return $level_categories;

    }

    /**
     * Function to validate if a text contains html tags, if not, adds line break tags to avoid auto-compress.
     * @param  string $text_check       text to check
     * @return string                   original text or corrected
     */
    protected function sl_check_html_text($text_check){
        
        if (is_array($text_check)){ 
            
            if (!empty($text_check)){

                $text_check = reset($text_check);
            }else{
                $text_check = '';

            }
        }

        if (preg_match('/<[^<]+>/s', $text_check)){
        
            return $text_check;

        }else{

            return nl2br($text_check);

        }

    }

    /**
     * Function to validate status value and return true/false.
     * @param boolean/integer/string $value         value to check
     * @return boolean                              boolean value
     */
    public function sl_validate_status_value($value){
        
        if ( is_bool( $value ) && $value === false){
        
            return false;
        
        }

        if ( ( is_string( $value ) && in_array( strtolower( $value ), array('false', '0', '2' , 'no', 'disabled', 'disable') ) )
             || ( is_numeric( $value ) && ($value === 0 || $value === 2 ) ) ) {
            
            return false;
        
        }

        return true;

    }

    /**
     * Function to execute all Sales Layer functions that pre-load class variables.
     * @return void
     */
    public function execute_slyr_load_functions(){

        $this->load_magento_variables();
        $this->load_models();
        $this->load_categories_collection();
        $this->load_products_collection();
        $this->checkImageAttributes(); 
        $this->checkActiveAttributes();
        $this->getSalesLayerRootCategory();
        $this->load_all_store_view_ids();
    
    }

    /**
     * Function to get all Sales Layer connectors.
     * @return array                   array of connectors
     */
    public function getConnectors(){

        $all_connectors = $this->getCollection();

        $connectors = array();
        
        if (count($all_connectors) > 0){

            foreach ($all_connectors as $connector) {

                $connector_data = $connector->getData();
                if (isset($connector_data['avoid_stock_update']) && $connector_data['avoid_stock_update'] !== '1'){ $connector_data['avoid_stock_update'] = '0'; }
                $connectors[] = $connector_data;

            }
            
        }
        
        return $connectors;

    }

    /**
     * Function to delete Sales Layer logs.
     * @return void
     */
    public function deleteSLLogs(){

        $log_dir_path = $this->directoryListFilesystem->getPath('log').'/sl_logs/';

        $log_folder_files = scandir($log_dir_path);

        if (!empty($log_folder_files)){

            foreach ($log_folder_files as $log_folder_file) {

                if (strpos($log_folder_file, '_debbug_log_saleslayer_') !== false){

                    $file_path = $log_dir_path.$log_folder_file;

                    if (file_exists($file_path)){

                        unlink($file_path);

                    }

                }

            }

        }

    }

    /**
     * Function to download Sales Layer logs.
     * @return void
     */
    public function downloadSLLogs(){

        $this->loadConfigParameters();

        $files = array();

        $log_dir_path = $this->directoryListFilesystem->getPath('log').'/sl_logs/';

        $log_folder_files = scandir($log_dir_path);

        if (!empty($log_folder_files)){

            foreach ($log_folder_files as $log_folder_file) {

                if (strpos($log_folder_file, '_debbug_log_saleslayer_') !== false){

                    $files[] = $log_folder_file;

                }

            }

        }else{
            $this->debbug('## Error. Logs files not found in: '.$log_dir_path.'. Found files: '.print_r($log_folder_files,1));
        }

        
        $zipname = $log_dir_path.'sl_logs_'.date('Y-m-d H-i-s').'.zip';
        $zip = new \ZipArchive();

        $zip->open($zipname, $zip::CREATE);

        $files_found = false;

        foreach ($files as $file) {

            $file_path = $log_dir_path . $file;

            if (file_exists($file_path)) {

                $files_found = true;
                $zip->addFile($file_path, $file);

            }

        }

        $zip->close();

        if (!$files_found) {

            unlink($zipname);
            $this->debbug('## Error. SL logs zip not found.');

        } else {

            if (file_exists($zipname)) {

                try{

                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename='.basename($zipname));
                    header('Content-Transfer-Encoding: binary');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($zipname));
                    
                    ob_clean();
                    flush();

                    chmod($zipname, 0777);

                    readfile($zipname);

                    unlink($zipname);

                    return true;

                }catch(\Exception $e){
                    
                    $this->debbug('## Error. Downloading SL logs zip: '.$e->getMessage());

                }

            }else{

                $this->debbug('## Error. SL logs zip does not exist.');

            }

        }

        return false;

    }

    /**
     * Function to unlink old items in Magento that don't exist already in Sales Layer.
     * @return void
     */
    public function unlinkOldItems(){

        $connectors = $this->getConnectors();

        $sl_connectors_data = array();
        $this->load_models();

        if (!empty($connectors)){

            $this->loadConfigParameters();

            foreach ($connectors as $connector) {

                $connector_id = $connector['connector_id'];
                $secret_key = $connector['secret_key'];

                $slconn = new SalesLayerConn($connector_id, $secret_key);

                $slconn->set_API_version(self::sl_API_version);
                $slconn->set_group_multicategory(true);
                $slconn->get_info();

                if ($slconn->has_response_error()) { continue; }

                if ($response_connector_schema = $slconn->get_response_connector_schema()) {

                    $response_connector_type = $response_connector_schema['connector_type'];

                    if ($response_connector_type != self::sl_connector_type) { continue; }

                }

                $comp_id = $slconn->get_response_company_ID();

                $get_response_table_data  = $slconn->get_response_table_data();

                $get_data_schema = self::get_data_schema($slconn);

                if (!$get_data_schema){ continue; }

                $products_schema = $get_data_schema['products'];

                if (!empty($products_schema['fields'][strtolower($this->product_field_sku)])){

                    $this->product_field_sku = strtolower($this->product_field_sku);

                }else if (!empty($products_schema['fields'][strtoupper($this->product_field_sku)])){

                    $this->product_field_sku = strtoupper($this->product_field_sku);

                }

                if ($get_response_table_data) {

                    if (!isset($sl_connectors_data[$comp_id])){ $sl_connectors_data[$comp_id] = array(); }

                    foreach ($get_response_table_data as $nombre_tabla => $data_tabla) {

                        $modified_data = $data_tabla['modified'];

                        switch ($nombre_tabla) {
                            case 'catalogue':

                                // $this->debbug('Count total categories: '.count($modified_data));
                                foreach ($modified_data as $keyCat => $category) {

                                    $sl_name = '';
                                    if (isset($category['data'][$this->category_field_name]) && $category['data'][$this->category_field_name] !== ''){
                                        $sl_name = $category['data'][$this->category_field_name];
                                    }
                                    
                                    $sl_connectors_data[$comp_id]['category'][$category['id']] = array();

                                    if ($sl_name !== ''){
                                        $sl_connectors_data[$comp_id]['category'][$category['id']]['name'] = $sl_name;
                                    }
                                    
                                }

                                break;
                            case 'products':

                                // $this->debbug('Count total products: '.count($modified_data));
                                foreach ($modified_data as $keyProd => $product) {

                                    $sl_name = $sl_sku = '';
                                    if (isset($product['data'][$this->product_field_name]) && $product['data'][$this->product_field_name] !== ''){
                                        $sl_name = $product['data'][$this->product_field_name];
                                    }
                                    if (isset($product['data'][$this->product_field_sku]) && $product['data'][$this->product_field_sku] !== ''){
                                        $sl_sku = $product['data'][$this->product_field_sku];
                                    }

                                    $sl_connectors_data[$comp_id]['product'][$product['id']] = array();

                                    if ($sl_name !== ''){
                                        $sl_connectors_data[$comp_id]['product'][$product['id']]['name'] = $sl_name;
                                    }
                                    if ($sl_sku !== ''){
                                        $sl_connectors_data[$comp_id]['product'][$product['id']]['sku'] = $sl_sku;
                                    }

                                }

                                break;
                            case 'product_formats':

                                // $this->debbug('Count total product formats: '.count($modified_data));
                                foreach ($modified_data as $keyForm => $format) {

                                    $sl_name = $sl_sku = '';
                                    if (isset($format['data']['format_name']) && $format['data']['format_name'] !== ''){
                                        $sl_name = $format['data']['format_name'];
                                    }
                                    if (isset($format['data']['format_sku']) && $format['data']['format_sku'] !== ''){
                                        $sl_sku = $format['data']['format_name'];
                                    }else{
                                        if (isset($format['data']['format_name']) && $format['data']['format_name'] !== ''){
                                            $sl_sku = 'sku_'.$format['data']['format_name'];
                                        }
                                    }

                                    $sl_connectors_data[$comp_id]['format'][$format['id']] = array();
                                    if ($sl_name !== ''){
                                        $sl_connectors_data[$comp_id]['format'][$format['id']]['name'] = $sl_name;
                                    }
                                    if ($sl_sku !== ''){
                                        $sl_connectors_data[$comp_id]['format'][$format['id']]['sku'] = $sl_sku;
                                    }

                                }

                                break;
                            default:

                                $this->debbug('## Error. Synchronizing, table '.$nombre_tabla.' not recognized.');

                                break;
                        }

                    }

                }

            }

            $unlinked_items = $duplicated_items = array();
            if (!empty($sl_connectors_data)){

                $empty_value = array(null => 0, '' => 0, null => 0);

                $this->load_categories_collection();

                if (!empty($this->categories_collection)){

                    foreach ($this->categories_collection as $keyCat => $category) {

                        if ($category['parent_id'] == 0 || $category['parent_id'] == 1){
                            continue;
                        }

                        $category_saleslayerid = $category['saleslayer_id'];
                        $category_saleslayercompid = $category['saleslayer_comp_id'];

                        $unlink = true;

                        if (!isset($empty_value[$category_saleslayerid]) && !isset($empty_value[$category_saleslayercompid])){

                            if (isset($sl_connectors_data[$category_saleslayercompid]['category'][$category_saleslayerid])){

                                if (isset($unlinked_items[$category_saleslayercompid]['category'][$category_saleslayerid])){

                                    $this->debbug('### category already unlinked SL id: '.$category_saleslayerid.' SL comp_id: '.$category_saleslayercompid);
                                    $this->debbug('### MG unlinked id: '.$unlinked_items[$category_saleslayercompid]['category'][$category_saleslayerid].' MG new unlink id: '.$category['entity_id']);

                                    foreach ($unlinked_items[$category_saleslayercompid]['category'][$category_saleslayerid] as  $dup_to_reg) {
                                        $duplicated_items['category'][$dup_to_reg['id']] = $dup_to_reg['name'];
                                    }
                                    $duplicated_items['category'][$category['entity_id']] = $category['name'];
                                }

                                $unlinked_items[$category_saleslayercompid]['category'][$category_saleslayerid][] = array('id' => $category['entity_id'], 'name' => $category['name']);
                                $unlink = false;

                            }

                        }

                        if ($unlink){

                            try{

                                $this->debbug('### category unlink id: '.$category['entity_id'].' name: '.$category['name']);
                                $this->debbug('### category unlink category_saleslayerid: '.print_r($category_saleslayerid,1));
                                $this->debbug('### category unlink category_saleslayercompid: '.print_r($category_saleslayercompid,1));

                                $category_update = $this->load_category_model($category['entity_id']);
                                $category_update->setData('saleslayer_id', '');
                                $category_update->setData('saleslayer_comp_id', '');
                                $category_update->setIsActive(0);
                                $category_update->save();

                                $deleted_categories_ids[] = $category['entity_id'];
                                $this->categories_collection[$category['entity_id']]['is_active'] = 0;

                            } catch (\Exception $e) {

                                $this->debbug('## Error. Unlinking category: '.$e->getMessage());

                            }

                        }

                    }

                    //Process to reorganize the category tree avoiding disabled categories just eliminated.
                    if (!empty($deleted_categories_ids)){

                        foreach ($this->categories_collection as $category_col) {

                            if (in_array($category_col['parent_id'], $deleted_categories_ids) && $category_col['is_active'] == 1){

                                $path_ids = explode('/', $category_col['path']);
                                $new_path = $parent_id = '';

                                foreach ($path_ids as $path_id){

                                    if (in_array($path_id, $deleted_categories_ids)){
                                        continue;
                                    }

                                    $new_path .= $path_id;

                                    if ($path_id != end($path_ids)){

                                        $new_path .= '/';
                                        $parent_id = $path_id;

                                    }

                                }

                                try{

                                    $category = $this->load_category_model($category_col['entity_id']);
                                    $category->setPath($new_path);
                                    $category->setParentId($parent_id);
                                    $category->save();

                                    $this->categories_collection[$category_col['entity_id']]['path'] = $new_path;
                                    $this->categories_collection[$category_col['entity_id']]['parent_id'] = $parent_id;

                                }catch(\Exception $e){

                                    $this->debbug('## Error. Reorganizing category tree: '.$e->getMessage());

                                }

                            }

                        }

                    }

                }

                $this->load_products_collection();

                if (!empty($this->products_collection)){

                    foreach ($this->products_collection as $keyProd => $product) {

                        $product_saleslayerid = $product['saleslayer_id'];
                        $product_saleslayercompid = $product['saleslayer_comp_id'];
                        $product_saleslayerformatid = $product['saleslayer_format_id'];

                        $unlink = true;

                        if (!isset($empty_value[$product_saleslayerid]) && !isset($empty_value[$product_saleslayercompid])){

                            // if (empty(Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($product->getId()))){}
                            if (!isset($empty_value[$product_saleslayerformatid])){

                                if (isset($sl_connectors_data[$product_saleslayercompid]['format'][$product_saleslayerformatid])){

                                    if (isset($unlinked_items[$product_saleslayercompid]['format'][$product_saleslayerformatid])){
                                        $this->debbug('### format already unlinked SL format_id: '.$product_saleslayerformatid.' SL comp_id: '.$product_saleslayercompid.' SL prod_id: '.$product_saleslayerid);
                                        $this->debbug('### MG unlinked id: '.$unlinked_items[$product_saleslayercompid]['format'][$product_saleslayerformatid].' MG new unlink id: '.$product['entity_id']);
                                    }

                                    $unlinked_items[$product_saleslayercompid]['format'][$product_saleslayerformatid] = $product['entity_id'];

                                    $unlink = false;

                                }

                            }else{

                                if (isset($sl_connectors_data[$product_saleslayercompid]['product'][$product_saleslayerid])){

                                    if (isset($unlinked_items[$product_saleslayercompid]['product'][$product_saleslayerid])){

                                        $this->debbug('### product already unlinked SL id: '.$product_saleslayerid.' SL comp_id: '.$product_saleslayercompid);
                                        $this->debbug('### MG unlinked data: '.print_r($unlinked_items[$product_saleslayercompid]['product'][$product_saleslayerid],1));
                                        $this->debbug('### MG new unlink id: '.$product['entity_id']);

                                        foreach ($unlinked_items[$product_saleslayercompid]['product'][$product_saleslayerid] as  $dup_to_reg) {
                                            $duplicated_items['product'][$dup_to_reg['id']] = $dup_to_reg['sku'];
                                        }

                                        $duplicated_items['product'][$product['entity_id']] = $product['sku'];

                                    }

                                    $unlinked_items[$product_saleslayercompid]['product'][$product_saleslayerid][] = array('id' => $product['entity_id'], 'sku' => $product['sku']);
                                    $unlink = false;

                                }

                            }

                        }

                        if ($unlink){

                            try {

                                $this->debbug('### product unlink id: '.$product['entity_id'].' sku: '.$product['sku'].' name: '.$product['name']);
                                $this->debbug('### product unlink product_saleslayerid: '.print_R($product_saleslayerid,1));
                                $this->debbug('### product unlink product_saleslayercompid: '.print_r($product_saleslayercompid,1));
                                $this->debbug('### product unlink product_saleslayerformatid: '.print_r($product_saleslayerformatid,1));

                                $product_update = $this->load_product_model($product['entity_id']);
                                $product_update->setData('saleslayer_id', '');
                                $product_update->setData('saleslayer_comp_id', '');
                                $product_update->setData('saleslayer_format_id', '');
                                $product_update->setStatus($this->status_disabled);
                                $product_update->save();

                                $deleted_products_ids[] = $product['entity_id'];
                                $this->products_collection[$product['entity_id']]['status'] = $this->status_disabled;

                            } catch (\Exception $e) {

                                $this->debbug('### Error. Unlinking product: '.$e->getMessage());

                            }

                        }

                    }

                }

                if (!empty($duplicated_items)){

                    foreach ($duplicated_items as $type => $items) {

                        foreach ($items as $item_id => $item_data) {

                            if ($type == 'category'){

                                $dup_category = $this->load_category_model($item_id);

                                try{

                                    $this->debbug('### duplicated category unlink id: '.$dup_category->getId().' name: '.$dup_category->getName());
                                    $this->debbug('### duplicated category unlink category_saleslayerid: '.print_r($dup_category->getSaleslayerId(),1));
                                    $this->debbug('### duplicated category unlink category_saleslayercompid: '.print_r($dup_category->getSaleslayerCompId(),1));

                                    $dup_category->setData('saleslayer_id', '');
                                    $dup_category->setData('saleslayer_comp_id', '');
                                    $dup_category->setIsActive(0);
                                    $dup_category->save();

                                } catch (\Exception $e) {

                                    $this->debbug('### Error. Unlinking duplicated category: '.$e->getMessage());

                                }

                            }else{

                                $dup_product = $this->load_product_model($item_id);

                                try{

                                    $this->debbug('### duplicated product unlink id: '.$dup_product->getId().' sku: '.$dup_product->getSku().' name: '.$dup_product->getName());
                                    $this->debbug('### duplicated product unlink product_saleslayerid: '.print_R($dup_product->getSaleslayerId(),1));
                                    $this->debbug('### duplicated product unlink product_saleslayercompid: '.print_r($dup_product->getSaleslayerCompId(),1));
                                    $this->debbug('### duplicated product unlink product_saleslayerformatid: '.print_r($dup_product->getSaleslayerFormatId(),1));

                                    $dup_product->setData('saleslayer_id', '');
                                    $dup_product->setData('saleslayer_comp_id', '');
                                    $dup_product->setData('saleslayer_format_id', '');
                                    $dup_product->setStatus($this->status_disabled);
                                    $dup_product->save();

                                } catch (\Exception $e) {

                                    $this->debbug('### Error. Unlinking duplicated product: '.$e->getMessage());
                                }

                            }

                        }

                    }

                }

            }

        }

    }

    /**
     * Function to load multi-connector items in SL Multiconn table.
     * @return void
     */
    public function loadMulticonnItems(){

        $connectors = $this->getConnectors();
        $sl_data = array();

        if (!empty($connectors)){

            $this->loadConfigParameters();

            foreach ($connectors as $connector) {

                $connector_id = $connector['connector_id'];
                $secret_key = $connector['secret_key'];

                $slconn = new SalesLayerConn ($connector_id, $secret_key);

                $slconn->set_API_version(self::sl_API_version);
                $slconn->set_group_multicategory(true);
                $slconn->get_info();

                if ($slconn->has_response_error()) { continue; }

                if ($response_connector_schema = $slconn->get_response_connector_schema()) {

                    $response_connector_type = $response_connector_schema['connector_type'];
                    if ($response_connector_type != self::sl_connector_type) { continue; }

                }

                $comp_id = $slconn->get_response_company_ID();

                $get_response_table_data  = $slconn->get_response_table_data();

                $get_data_schema = self::get_data_schema($slconn);

                if (!$get_data_schema){ continue; }

                $products_schema = $get_data_schema['products'];

                if (!empty($products_schema['fields'][strtolower($this->product_field_sku)])){
                    $this->product_field_sku = strtolower($this->product_field_sku);
                }else if (!empty($products_schema['fields'][strtoupper($this->product_field_sku)])){
                    $this->product_field_sku = strtoupper($this->product_field_sku);
                }

                if ($get_response_table_data) {

                    if (!isset($sl_data[$comp_id])){ $sl_data[$comp_id] = array(); }

                    foreach ($get_response_table_data as $nombre_tabla => $data_tabla) {

                        $modified_data = $data_tabla['modified'];

                        switch ($nombre_tabla) {
                            case 'catalogue':

                                // $this->debbug('Count total categories: '.count($modified_data));
                                foreach ($modified_data as $keyCat => $category) {

                                    if (!isset($sl_data[$comp_id]['category'][$category['id']])){
                                        $sl_data[$comp_id]['category'][$category['id']] = array();
                                    }

                                    $sl_data[$comp_id]['category'][$category['id']][] = $connector_id;

                                }

                                break;
                            case 'products':

                                // $this->debbug('Count total products: '.count($modified_data));
                                foreach ($modified_data as $keyProd => $product) {

                                    if (!isset($sl_data[$comp_id]['product'][$product['id']])){
                                        $sl_data[$comp_id]['product'][$product['id']] = array();
                                    }

                                    $sl_data[$comp_id]['product'][$product['id']][] = $connector_id;

                                }

                                break;
                            case 'product_formats':

                                // $this->debbug('Count total product formats: '.count($modified_data));
                                foreach ($modified_data as $keyForm => $format) {

                                    if (!isset($sl_data[$comp_id]['format'][$format['id']])){
                                        $sl_data[$comp_id]['format'][$format['id']] = array();
                                    }

                                    $sl_data[$comp_id]['format'][$format['id']][] = $connector_id;

                                }

                                break;
                            default:

                                $this->debbug('## Error. Updating multiconn table, table '.$nombre_tabla.' not recognized.');

                                break;
                        }
                    }
                }
            }

            $sl_multiconn_table_data = $this->connection->fetchAll(" SELECT * FROM ".$this->saleslayer_multiconn_table);

            foreach ($sl_data as $comp_id => $sl_data_regs) {

                foreach ($sl_data_regs as $item_type => $sl_item_data) {

                    foreach ($sl_item_data as $item_id => $sl_item_connectors) {

                        $found = false;

                        if (!empty($sl_multiconn_table_data)){

                            foreach ($sl_multiconn_table_data as $sl_multiconn_reg) {

                                if ($sl_multiconn_reg['item_type'] == $item_type && $sl_multiconn_reg['sl_comp_id'] == $comp_id && $sl_multiconn_reg['sl_id'] == $item_id){

                                    $found = true;

                                    try{

                                        $connectors_data = json_decode($sl_multiconn_reg['sl_connectors'],1);

                                        if (!is_array($connectors_data) || (is_array($connectors_data) && empty($connectors_data))){ $connectors_data = array(); }

                                        $new_connectors_data = json_encode(array_unique(array_merge($connectors_data, $sl_item_connectors)));

                                        if ($new_connectors_data != $connectors_data){

                                            $query_update  = " UPDATE ".$this->saleslayer_multiconn_table.
                                                " SET sl_connectors =  ? ".
                                                " WHERE id =  ? ";

                                            // $this->execute_multiconn_sql($query_update, array($new_connectors_data, $sl_multiconn_reg['id']));

                                            $this->sl_connection_query($query_update, array($new_connectors_data, $sl_multiconn_reg['id']));
                                            
                                        }

                                    }catch(\Exception $e){

                                        $this->debbug('## Error. Updating multiconn table: '.$e->getMessage());
                                    
                                    }

                                    break;
                                }
                            }
                        }

                        if (!$found){

                            try{

                                $connectors_data = json_encode($sl_item_connectors);

                                $query_insert = " INSERT INTO ".$this->saleslayer_multiconn_table.
                                    "(`item_type`,`sl_id`,`sl_comp_id`,`sl_connectors`) ".
                                    "values ( ? , ? , ? , ? );";

                                // $this->execute_multiconn_sql($query_insert, array($item_type, $item_id, $comp_id, $connectors_data));

                                $this->sl_connection_query($query_insert, array($item_type, $item_id, $comp_id, $connectors_data));

                            }catch(\Exception $e){
                            
                                $this->debbug('## Error. Inserting multiconn table: '.$e->getMessage());
                            
                            }

                        }

                    }

                }

            }

        }

    }

    /**
     * Function to populate multiconn table data.
     * @return void
     */
    public function load_sl_multiconn_table_data(){

        $sl_multiconn_table_data = $this->connection->fetchAll(" SELECT * FROM ".$this->saleslayer_multiconn_table." WHERE sl_comp_id = ".$this->comp_id);

        if (!empty($sl_multiconn_table_data)){

            foreach ($sl_multiconn_table_data as $sl_multiconn_reg) {

                if ($sl_multiconn_reg['sl_connectors'] !== ''){
                
                    $sl_multiconn_reg['sl_connectors'] = json_decode($sl_multiconn_reg['sl_connectors'],1);
                
                }

                if (!isset($this->sl_multiconn_table_data[$sl_multiconn_reg['item_type']])){ $this->sl_multiconn_table_data[$sl_multiconn_reg['item_type']] = array(); }
                
                $this->sl_multiconn_table_data[$sl_multiconn_reg['item_type']][$sl_multiconn_reg['sl_id']] = array('id' => $sl_multiconn_reg['id'], 'sl_connectors' => $sl_multiconn_reg['sl_connectors']);

            }

        }

    }

    /**
     * Function to execute a sql and commit it.
     * @param  string $sql_to_execute               sql to execute
     * @return void
     */
    public function sl_connection_query($query, $params = array()){

        $this->connection->beginTransaction();
        
        try{

            if (!empty($params)){

                $this->connection->query($query, $params);

            }else{

                $this->connection->query($query);

            }

            $this->connection->commit();

        }catch(\Exception $e) {
            
            if (!empty($params)){

                $this->debbug('## Error. SL SQL query: '.$query.' - params: '.print_r($params,1));
                
            }else{

                $this->debbug('## Error. SL SQL query: '.$query);
                
            }

            $this->debbug('## Error. SL SQL error message: '.$e->getMessage());

        }

    }

    /**
     * Function to delete Sales Layer log file.
     * @param array $files_to_delete        log files to delete from system
     * @return boolean
     */
    public function deleteSLLogFile($files_to_delete){

        $log_dir_path = $this->directoryListFilesystem->getPath('log').'/sl_logs/';

        if (!is_array($files_to_delete)){ $files_to_delete = array($files_to_delete); }

        if (empty($files_to_delete)){ return false; }

        foreach ($files_to_delete as $file_to_delete) {

        	$file_array = explode('/',$file_to_delete);
	        $file_to_delete = end($file_array);

            if(preg_match('/[A-Za-z0-9]*.[A-Za-z0-9]{3}/',$file_to_delete)){
	            $file_path = $log_dir_path.$file_to_delete;

	            if (file_exists($file_path)){

		            unlink($file_path);

	            }
            }

        }

        return true;

    }

    /**
     * Function to show content log file.
     * @param string $logfile               log file which we want to show content
     * @return array
     */
    public function showContentFile($logfile){

        $logfile = html_entity_decode($logfile);
        $response = array();
        $response[1] = array();
        $log_dir_path = $this->directoryListFilesystem->getPath('log').'/sl_logs/';
	    $elements_array =  explode('/',$logfile);
	    $logfile = end($elements_array);

        $exportlines = '';

        if(preg_match('/[A-Za-z0-9]*.[A-Za-z0-9]{3}/',$logfile) &&  file_exists( $log_dir_path.$logfile)){
            $file = file($log_dir_path.$logfile);
            $listed = 0;
            $warnings = 0;
            $numerrors = 0;
            if(sizeof( $file)>=1){
                $spacingarray = array();
                foreach ( $file as  $line){

                    if(count($spacingarray)>=1 &&  stripos($line,")") !== false){
                        array_pop($spacingarray);
                    }

                    if(count($spacingarray)>=1){
                        if(stripos($line,"(") !== false ){
                            array_pop($spacingarray);
                            $spacing = implode('',$spacingarray);
                            $spacingarray[] = '&emsp;&emsp;';
                        }else{
                            $spacing = implode('',$spacingarray);
                        }
                    }else{
                        $spacing = '';
                    }
                    $listed ++;
                    if (stripos($line,"## Error.") !== false ||stripos($line, "error") !== false) {
                        $iderror = 'id="iderror'.$numerrors.'"';
                        $exportlines .='<span class="alert-danger col-xs-12" '.$iderror.'><i class="fas fa-times text-danger mar-10"></i>  '.$spacing.$line.'</span><br>';
                        $numerrors++;
                    }elseif(stripos($line, "warning") !== false) {
                        $idwarning = 'id="idwarning'.$warnings.'"';
                        $exportlines .='<span class="alert-warning col-xs-12" '.$idwarning.'><i class="fas fa-exclamation-circle text-warning mar-10"></i>  '.$spacing.$line.'</span><br>';
                        $warnings++;
                    }elseif(stripos($line, "Saleslayer_Synccatalog") !== false) {
                        $exportlines .='<span class="alert-info col-xs-12"><i class="fas fa-info-circle text-info mar-10"></i>  '.$spacing.$line.'</span><br>';
                    }else{
                        $exportlines .='<span class="col-xs-12">'.$spacing.$line.'</span><br>';
                    }
                    if(stripos($line,"Array") !== false){
                        $spacingarray[] = '&emsp;&emsp;';
                    }
                }
            }else{
                $exportlines .= '';
            }

            $response[0] = 1;
            $response[1] = $exportlines;
            $response[2] = $listed;
            $response[3] = $warnings;
            $response[4] = $numerrors;

        }else{
            $response[0] = 1;
            $response[1] = array('Log file does not exist.');
            $response[2] = 0;
            $response[3] = 0;
            $response[4] = 0;

        }

        $response['function'] = 'showlogfilecontent';

        return $response;

    }

    /**
     * Function to check files Sales Layer logs.
     * @return array
     */
   public function checkFilesLogs(){
       
       $files = array();

       $response = array();
       $response[1] = array();
       $log_dir_path = $this->directoryListFilesystem->getPath('log').'/sl_logs/';

       $log_folder_files = scandir($log_dir_path);

       if (!empty($log_folder_files)){

           foreach ($log_folder_files as $log_folder_file) {
               if (strpos($log_folder_file, '_saleslayer_') !== false){//_debbug_log_saleslayer_
                   $files[] = $log_folder_file;
               }
           }

           if(sizeof($files)>=1){
               $files_found = false;
               $table = array('file'=>array(),'lines'=>array(),'warnings'=>array(),'errors'=>array());

               foreach ($files as $file) {
                   $errors = 0;
                   $warnings = 0;
                   $lines = 0;
                   $file_path = $log_dir_path . $file;

                   if (file_exists($file_path)) {
                       $table['file'][] = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');
                       $fileopened = file($log_dir_path.$file);
                       if(sizeof( $fileopened)>=1){
                           $errors = 0;
                           $warnings = 0;
                           $lines = 0;
                           foreach ( $fileopened as  $line){
                               $lines++;
                               if (stripos($line,"## Error.") !== false ||stripos($line, "error") !== false) {
                                   $errors++;
                               }elseif(stripos($line, "warning") !== false) {
                                   $warnings++;
                               }
                           }
                       }
                       $table['lines'][]     =  $lines;
                       $table['warnings'][] =  $warnings;
                       $table['errors'][]   =  $errors;
                   }
                   $files_found = true;
               }

               if($files_found){
                   $response[0] = 1;
                   $response[1] = $table;
               }else{
                   $response[0] = 0;
                   $response[1] = 'No log files to show.';
               }
           }else{
               $response[0] = 0;
               $response[1] = 'No log files to show.';
           }
       }else{
           $response[0] = 0;
           $response[1] = 'No log files to show.';
       }
       $response['function'] = 'showlogfiles';

       return $response;

   }

    /**
     * Function to delete Sales Layer regs.
     * @return void
     */
    public function deleteSLRegs(){

        $items_to_process = $this->connection->query(" SELECT count(*) as count FROM ".$this->saleslayer_syncdata_table)->fetch();

        if (isset($items_to_process['count']) && $items_to_process['count'] > 0){

            $this->debbug("Deleting ".$items_to_process['count']." items to process...");

            try{

                $sql_query_delete = " DELETE FROM ".$this->saleslayer_syncdata_table;
                $this->sl_connection_query($sql_query_delete);
              
            }catch(\Exception $e){
             
                $this->debbug('## Error. Delete syncdata SQL message: '.$e->getMessage());
                $this->debbug('## Error. Delete syncdata SQL query: '.$sql_query_delete);

            }

        }

        $indexers_data = $this->connection->fetchAll(" SELECT * FROM ".$this->saleslayer_indexers_table);
      
        if (!empty($indexers_data)){
          
            $this->debbug("Deleting ".count($indexers_data)." indexers to process...");

            foreach ($indexers_data as $indexer_data) { 

                $this->debbug('Updating indexer '.$indexer_data['indexer_title'].' with id '.$indexer_data['indexer_id'].' status back...');

                $time_ini_indexer = microtime(1);

                try{
                  
                    $indexer = clone $this->indexer;
                    $indexer->load($indexer_data['indexer_id']);
                    $indexer->getState()->setStatus($indexer_data['indexer_status']);
                    $indexer->getState()->save();
                  
                    $this->debbug('Indexer '.$indexer_data['indexer_title'].' with id '.$indexer_data['indexer_id'].' status updated back to: '.$indexer_data['indexer_status']);
                    $this->debbug('## time_indexer '.$indexer_data['indexer_title'].' to original value: '.(microtime(1) - $time_ini_indexer).' seconds.');
                    $this->debbug('## time_indexer '.$indexer_data['indexer_title'].' to original value: '.(microtime(1) - $time_ini_indexer).' seconds.', 'timer');

                }catch(\Exception $e){

                    $this->debbug('## Error. Updating indexer '.$indexer_data['indexer_title'].' with id '.$indexer_data['indexer_id'].' status back. Message: '.$e->getMessage());
                  
                }

                try{

                   $sql_query_delete = " DELETE FROM ".$this->saleslayer_indexers_table.
                               " WHERE id = ".$indexer_data['id'];

                   $this->sl_connection_query($sql_query_delete);
                   
                }catch(\Exception $e){
                  
                    $this->debbug('## Error. Delete indexers SQL message: '.$e->getMessage());
                    $this->debbug('## Error. Delete indexers SQL query: '.$sql_query_delete);

                }
           
            }
              
        }

    }

    /**
     * Function to filter empty and null values
     * @param  string or integer    $value     value to filter
     * @return integer         returns 1 if value is not empty or null
     */
    public function array_filter_empty_value($value){

        return !(trim($value) === "" || $value === null);

    }

    /**
     * Search the pid and return if it's still running or not
     * @param  integer  $pid  pid to search
     * @return boolean        status of pid running
     */
    public function has_pid_alive ($pid) {

        if ($pid) {

            if (strtolower(substr(PHP_OS, 0, 3)) == 'win') {

                $wmi = new \COM('winmgmts://');
                $prc = $wmi->ExecQuery("SELECT ProcessId FROM Win32_Process WHERE ProcessId='$pid'");

                if (count($prc) > 0) { $i = 0; foreach ($prc as $a) { ++$i; }}

                if ($this->sl_DEBBUG > 2){ $this->debbug("Searching active process pid '$pid' by Windows. Is active? ".($i > 0 ? 'Yes' : 'No')); }

                return ($i > 0 ? true : false);

            } else if (function_exists('posix_getpgid')) {

                if ($this->sl_DEBBUG > 2) { $this->debbug("Searching active process pid '$pid' by posix_getpgid. Is active? ".(posix_getpgid($pid) ? 'Yes' : 'No')); }

                return (posix_getpgid($pid) ? true : false);

            } else {

                if ($this->sl_DEBBUG > 2) { $this->debbug("Searching active process pid '$pid' by ps -p. Is active? ".(shell_exec("ps -p $pid | wc -l") > 1 ? 'Yes' : 'No')); }

                if (shell_exec("ps -p $pid | wc -l") > 1) { return true; }

            }
        }

        return false;
        
    }

}