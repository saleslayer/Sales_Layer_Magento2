<?php
namespace Saleslayer\Synccatalog\Model;

/*
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);
umask(0);
*/

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

/**
 * Class Saleslayer_Synccatalog_Model_Syncdatacron
 */
class Syncdatacron extends Synccatalog{
    
    protected       $sl_time_ini_sync_data_process;
    protected       $max_execution_time                 = 290;
    protected       $end_process;
    protected       $initialized_vars                   = false;
    protected       $sql_items_delete                   = array();
    protected       $category_fields                    = array();
    protected       $product_fields                     = array();
    protected       $product_format_fields              = array();
    protected       $indexers_status                    = 'default';
    protected       $indexer_collection_ids             = array();
    protected       $indexers_info                      = array();
    protected       $syncdata_pid;

    /**
     * Sales Layer Syncdata constructor.
     * @return void
     */
    public function __construct(
                context $context,
                registry $registry,
                SalesLayerConn $salesLayerConn,
                synccatalogDataHelper $synccatalogDataHelper,
                synccatalogConfigHelper $synccatalogConfigHelper,
                galleryProcessor $galleryProcessor,
                fileIo $fileIo,
                directoryListFilesystem $directoryListFilesystem,
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
                resource $resource = null,
                resourceCollection $resourceCollection = null,
                array $data = []) {
        parent::__construct($context,
                            $registry, 
                            $salesLayerConn, 
                            $synccatalogDataHelper, 
                            $synccatalogConfigHelper, 
                            $galleryProcessor, 
                            $fileIo,
                            $directoryListFilesystem,
                            $categoryModel, 
                            $categoryLinkManagementInterface,
                            $productModel,
                            $productConfigurableType,
                            $attribute,
                            $attribute_set,
                            $productAttributeManagementInterface,
                            $productLinkInterface,
                            $productLinkRepositoryInterface,
                            $storeModel,
                            $storeSystemModel,
                            $indexer,
                            $indexerCollection,
                            $resourceConnection,
                            $collectionOption,
                            $cronSchedule,
                            $resource,
                            $resourceCollection,
                            $data);

    }

    /**
     * Function to check current process time to avoid exceding the limit.
     * @return void
     */
    private function check_process_time(){

        $current_process_time = microtime(1) - $this->sl_time_ini_sync_data_process;
        
        if ($current_process_time >= $this->max_execution_time){

            $this->end_process = true;

        }

    }

    /**
     * Function to initialize catalogue vars to load before synchronizing.
     * @return void
     */
    private function initialize_vars(){

        if (!$this->initialized_vars){

            $this->execute_slyr_load_functions();
            
            $this->category_fields = array('category_field_name', 'category_field_description', 'category_field_image', 'category_field_meta_title', 'category_field_meta_keywords', 'category_field_meta_description', 'category_images_sizes');
            $this->product_fields = array('product_field_name', 'product_field_description', 'product_field_description_short', 'product_field_price', 'product_field_image', 'product_field_meta_title', 'product_field_meta_keywords', 'product_field_meta_description', 'product_images_sizes', 'has_product_field_sku', 'product_field_sku', 'has_product_field_qty', 'product_field_qty', 
                'image_extensions');
            $this->product_format_fields = array('format_images_sizes', 'image_extensions');

            $this->initialized_vars = true;

        }

    }

    /**
     * Function to check sql rows to delete from sync data table.
     * @return void
     */
    private function check_sql_items_delete($force_delete = false){

        if (count($this->sql_items_delete) >= 20 || ($force_delete && count($this->sql_items_delete) > 0)){
            
            $sql_items_to_delete = implode(',', $this->sql_items_delete);

            $sql_delete = " DELETE FROM ".$this->saleslayer_syncdata_table.
                                " WHERE id IN (".$sql_items_to_delete.")";

            $this->sl_connection_query($sql_delete);

            $this->sql_items_delete = array();

        }

    }

    /**
     * Function to check sync data pid flag in database and delete kill it if the process is stuck.
     * @return void
     */
    private function check_sync_data_flag(){

        $items_to_process = $this->connection->query(" SELECT count(*) as count FROM ".$this->saleslayer_syncdata_table)->fetch();
        
        if (isset($items_to_process['count']) && $items_to_process['count'] > 0){

            $current_flag = $this->connection->query(" SELECT * FROM ".$this->saleslayer_syncdata_flag_table." ORDER BY id DESC LIMIT 1")->fetch();
            $now = strtotime('now');
            $date_now = date('Y-m-d H:i:s', $now);

            if (!empty($current_flag)){
                
                if ($current_flag['syncdata_pid'] == 0){
                
                    $sl_query_flag_to_update = " UPDATE ".$this->saleslayer_syncdata_flag_table.
                                            " SET syncdata_pid = ".$this->syncdata_pid.", syncdata_last_date = '".$date_now."'".
                                            " WHERE id = ".$current_flag['id'];
                
                    $this->sl_connection_query($sl_query_flag_to_update);

                }else{

                    $interval  = abs($now - strtotime($current_flag['syncdata_last_date']));
                    $minutes   = round($interval / 60);
                    
                    if ($minutes < 10){
                    
                        $this->debbug('Data is already being processed.', 'syncdata');
                        $this->end_process = true;

                    }else{
                        
                        if ($this->syncdata_pid === $current_flag['syncdata_pid']){

                            $this->debbug('Pid is the same as current.', 'syncdata');

                        }

                        $flag_pid_is_alive = $this->has_pid_alive($current_flag['syncdata_pid']);
                        
                        if ($flag_pid_is_alive){
                        
                            try{

                                $this->debbug('Killing pid: '.$current_flag['syncdata_pid'].' with user: '.get_current_user(), 'syncdata');
                                
                                $result_kill = posix_kill($current_flag['syncdata_pid'], 0);

                                if (!$result_kill){

                                    $this->debbug('## Error. Could not kill pid '.$current_flag['syncdata_pid'], 'syncdata');

                                }

                            }catch(\Exception $e){
                        
                                $this->debbug('## Error. Exception killing pid '.$current_flag['syncdata_pid'].': '.print_r($e->getMessage(),1), 'syncdata');
                        
                            }
                                                        
                        }

                        $sl_query_flag_to_update = " UPDATE ".$this->saleslayer_syncdata_flag_table.
                                                " SET syncdata_pid = ".$this->syncdata_pid.", syncdata_last_date = '".$date_now."'".
                                                " WHERE id = ".$current_flag['id'];

                        $this->sl_connection_query($sl_query_flag_to_update);
                       
                    }

                }
                

            }else{

                //Just to avoid having duplicated flags
                try{

                    $sql_delete = " DELETE FROM ".$this->saleslayer_syncdata_flag_table;

                    $this->sl_connection_query($sql_delete);

                }catch(\Exception $e){
                
                    $this->debbug('## Error. Cleaning sync_data_flag table before inserting: '.print_r($e->getMessage(),1), 'syncdata');
                
                }
                
                $sl_query_flag_to_insert = " INSERT INTO ".$this->saleslayer_syncdata_flag_table.
                                         " ( syncdata_pid, syncdata_last_date) VALUES ".
                                         "('".$this->syncdata_pid."', '".$date_now."')";
                
                $this->sl_connection_query($sl_query_flag_to_insert);

            }

        }

    }

    /**
    * Function to disable sync data pid flag in database.
    * @return void
    */
    private function disable_sync_data_flag(){

        $current_flag = $this->connection->query(" SELECT * FROM ".$this->saleslayer_syncdata_flag_table." ORDER BY id DESC LIMIT 1")->fetch();

        if (!empty($current_flag)){

            $sl_query_flag_to_update = " UPDATE ".$this->saleslayer_syncdata_flag_table.
                                    " SET syncdata_pid = 0".
                                    " WHERE id = ".$current_flag['id'];
            $this->sl_connection_query($sl_query_flag_to_update);

        }

    }

    /**
     * Function to synchronize Sales Layer connectors data stored in sync data table.
     * @return void
     */
    public function sync_data_connectors(){

        $this->loadConfigParameters();

        $this->sl_time_ini_sync_data_process = microtime(1);

        $this->debbug("==== Sync Data INIT ".date('Y-m-d H:i:s')." ====", 'syncdata');
        
        try{

            //Clear exceeded attemps
            
            $sql_delete = " DELETE FROM ".$this->saleslayer_syncdata_table." WHERE sync_tries >= 3";

            $this->sl_connection_query($sql_delete);

        }catch(\Exception $e){

            $this->debbug('## Error. Clearing exceeded attemps: '.$e->getMessage(), 'syncdata');

        }

        $this->syncdata_pid = getmypid();

        $this->end_process = false;

        if (!in_array($this->sync_data_hour_from, array('', null, 0)) || !in_array($this->sync_data_hour_until, array('', null, 0))){

            $hour_from = $this->sync_data_hour_from.':00';
            $hour_from_time = strtotime($hour_from);
            $hour_until = $this->sync_data_hour_until.':00';
            $hour_until_time = strtotime($hour_until);
            $hour_now = date('H').':00';
            $hour_now_time = strtotime($hour_now);
        
            if (($hour_from_time < $hour_until_time && $hour_now_time >= $hour_from_time && $hour_now_time <= $hour_until_time) || ($hour_from_time > $hour_until_time && ($hour_now_time >= $hour_from_time || $hour_now_time <= $hour_until_time)) || $hour_from_time == $hour_until_time){
                
                $this->debbug('Current hour '.$hour_now.' for sync data process.', 'syncdata');
            
            } else {
            
                $this->end_process = true;
                $this->debbug('Current hour '.$hour_now.' is not set between hour from '.$hour_from.' and hour until '.$hour_until.'. Finishing sync data process.', 'syncdata');
            
            }

        }

        if (!$this->end_process){

            $this->check_sync_data_flag();

            if (!$this->end_process){
                
                try {

                    $items_to_delete = $this->connection->fetchAll(" SELECT * FROM ".$this->saleslayer_syncdata_table." WHERE sync_type = 'delete' ORDER BY item_type ASC, sync_tries ASC, id ASC");
                    
                    if (!empty($items_to_delete)){
                        
                        $this->initialize_vars();

                        foreach ($items_to_delete as $item_to_delete) {
                            
                            $this->check_process_time();
                            $this->check_sql_items_delete();

                            if ($this->end_process){
                                $this->debbug('Breaking syncdata process due to time limit.', 'syncdata');
                                break;

                            }else{

                                $sync_tries = $item_to_delete['sync_tries'];

                                $sync_params = json_decode(stripslashes($item_to_delete['sync_params']),1);
                                $this->processing_connector_id = $sync_params['conn_params']['connector_id'];
                                ($this->comp_id != $sync_params['conn_params']['comp_id']) ? $load_sl_multiconn_table_data = true : $load_sl_multiconn_table_data = false;
                                $this->comp_id = $sync_params['conn_params']['comp_id'];
                                if ($load_sl_multiconn_table_data){ $this->load_sl_multiconn_table_data(); }
                                $this->store_view_ids = $sync_params['conn_params']['store_view_ids'];
                                
                                $item_data = json_decode(stripslashes($item_to_delete['item_data']),1);
                                $sl_id = $item_data['sl_id'];
                                
                                switch ($item_to_delete['item_type']) {
                                    case 'category':
                                        
                                        $result_delete = $this->delete_stored_category($sl_id);
                                        break;
                                    
                                    case 'product':
                                        
                                        $result_delete = $this->delete_stored_product($sl_id);
                                        break;

                                    case 'product_format':
                                        
                                        $result_delete = $this->delete_stored_product_format($sl_id);
                                        break;

                                    default:
                                        
                                        $this->debbug('## Error. Incorrect item: '.print_R($item_to_delete,1), 'syncdata');
                                        break;
                                }

                                switch ($result_delete) {
                                    case 'item_not_deleted':
                                        
                                        $sync_tries++;

                                        $sql_update = " UPDATE ".$this->saleslayer_syncdata_table.
                                                                " SET sync_tries = ".$sync_tries.
                                                                " WHERE id = ".$item_to_delete['id'];

                                        $this->sl_connection_query($sql_update);

                                        break;
                                    
                                    default:
                                        
                                        $this->sql_items_delete[] = $item_to_delete['id'];
                                        break;

                                }

                            }

                        }

                        $this->reorganize_categories_after_delete();

                    }

                } catch (\Exception $e) {

                    $this->debbug('## Error. Deleting syncdata process: '.$e->getMessage(), 'syncdata');

                }

                $indexes = array('category', 'product', 'product_format', 'product_links', 'product__images');

                foreach ($indexes as $index) {
                    
                    $sql_check_try = 0;

                    do{

                        $items_to_update = $this->connection->fetchAll(" SELECT * FROM ".$this->saleslayer_syncdata_table." WHERE sync_type = 'update' and item_type = '".$index."' and sync_tries <= 2 ORDER BY item_type ASC, sync_tries ASC, id ASC LIMIT 1");

                        if (!empty($items_to_update) && isset($items_to_update[0])){

                            $this->initialize_vars();

                            $this->check_process_time();

                            if ($this->end_process){
                            
                                $this->debbug('Breaking syncdata process due to time limit.', 'syncdata');
                                break 2;

                            }else{

                                $this->update_item($items_to_update[0]);

                            }

                        }else{

                            break;

                        }
                        
                        if ($this->end_process){

                            break 2;

                        }

                    }while(!empty($items_to_update)); 
                    
                }
                
            }

            $this->check_sql_items_delete(true);

            if (!$this->end_process){

                try{

                    //Clear exceeded attemps
                    
                    $sql_delete = " DELETE FROM ".$this->saleslayer_syncdata_table." WHERE sync_tries >= 3";

                    $this->sl_connection_query($sql_delete);

                }catch(\Exception $e){

                    $this->debbug('## Error. Clearing exceeded attemps: '.$e->getMessage(), 'syncdata');

                }

                $items_processing = $this->connection->query(" SELECT count(*) as count FROM ".$this->saleslayer_syncdata_table." WHERE sync_type in('delete','update') and sync_tries <= 2")->fetch();
            
                if (isset($items_processing['count']) && $items_processing['count'] == 0){

                    $indexer_data = $this->connection->query(" SELECT * FROM ".$this->saleslayer_syncdata_table." WHERE sync_type = 'reindex'")->fetch();

                    if (!empty($indexer_data)){
                        
                        $indexers_info = json_decode(stripslashes($indexer_data['item_data']),1);

                        foreach ($indexers_info as $indexer_id => $status) {
                            
                            $indexer = clone $this->indexer;
                            $indexer->load($indexer_id);

                            try{
                                
                                $time_ini_indexer = microtime(1);
                                $indexer->getState()->setStatus($status['status']);
                                $indexer->getState()->save();
                                $indexer->reindexAll();
                                
                                $this->debbug('## time_indexer to original value: '.(microtime(1) - $time_ini_indexer).' seconds.', 'timer');

                            }catch(\Exception $e){

                                $this->debbug('## Error. Updating indexer '.$indexer_id.' status back and reindexing: '.$e->getMessage(), 'syncdata');

                            }
                        
                        }

                        $this->sql_items_delete[] = $indexer_data['id'];
                        $this->check_sql_items_delete(true);

                    }

                }

            }
            
            try{

                $this->disable_sync_data_flag();
            
            }catch(\Exception $e){

                $this->debbug('## Error. Deleting sync_data_flag: '.$e->getMessage(), 'syncdata');

            }

        }

        $this->debbug('### time_all_syncdata_process: '.(microtime(1) - $this->sl_time_ini_sync_data_process).' seconds.', 'syncdata');

        $this->debbug("==== Sync Data END ====", 'syncdata');

    }

    /**
     * Function to update item depending on type.
     * @param  $item_to_update  item date to update in MG
     * @return void
     */
    private function update_item($item_to_update){
         
        $sync_tries = $item_to_update['sync_tries'];
        
        if ($item_to_update['sync_params'] != ''){

            $sync_params = json_decode($item_to_update['sync_params'],1);
            $this->processing_connector_id = $sync_params['conn_params']['connector_id'];
            ($this->comp_id != $sync_params['conn_params']['comp_id']) ? $load_sl_multiconn_table_data = true : $load_sl_multiconn_table_data = false;
            $this->comp_id = $sync_params['conn_params']['comp_id'];
            if ($load_sl_multiconn_table_data){ $this->load_sl_multiconn_table_data(); }
            $this->store_view_ids = $sync_params['conn_params']['store_view_ids'];
            $this->website_ids = $sync_params['conn_params']['website_ids'];

        }

        $item_data = json_decode($item_to_update['item_data'],1);
        
        if ($item_data == ''){
        
            $this->debbug("## Error. Decoding item's data: ".print_R($item_to_update['item_data'],1), 'syncdata');
            $result_update = '';
        
        }else{
            
            switch ($item_to_update['item_type']) {
                case 'category':
                    
                    $this->default_category_id = $sync_params['default_category_id'];
                    $this->category_is_anchor = $sync_params['category_is_anchor'];
                    $this->category_page_layout = $sync_params['category_page_layout'];
                    
                    foreach ($this->category_fields as $category_field) {
                        
                        if (isset($sync_params['category_fields'][$category_field])){

                            $this->$category_field = $sync_params['category_fields'][$category_field];

                        }

                    }

                    if (isset($sync_params['catalogue_media_field_names']) && !empty($sync_params['catalogue_media_field_names'])){

                        $this->media_field_names['catalogue'] = $sync_params['catalogue_media_field_names'];

                    }
                    
                    $time_ini_sync_stored_category = microtime(1);
                    $this->debbug(' >> Category synchronization initialized << ');
                    $result_update = $this->sync_stored_category($item_data);
                    $this->debbug(' >> Category synchronization finished << ');
                    $this->debbug('#### time_sync_stored_category: '.(microtime(1) - $time_ini_sync_stored_category).' seconds.', 'timer');
                    break;
                
                case 'product':
                    
                    $this->attribute_set_collection = $sync_params['attribute_set_collection'];
                    $this->default_attribute_set_id = $sync_params['default_attribute_set_id'];
                    $this->avoid_stock_update = $sync_params['avoid_stock_update'];
                    $this->products_previous_categories = $sync_params['products_previous_categories'];
                    
                    foreach ($this->product_fields as $product_field) {
                        
                        if (isset($sync_params['product_fields'][$product_field])){

                            if ($product_field == 'image_extensions'){

                                foreach ($sync_params['product_fields'][$product_field] as $extension_name => $extension_value) {
                                
                                    $this->$extension_name = $extension_value;

                                }
                                continue;

                            }

                            $this->$product_field = $sync_params['product_fields'][$product_field];
                            
                        }

                    }

                    if (isset($sync_params['product_additional_fields']) && !empty($sync_params['product_additional_fields'])){

                        foreach ($sync_params['product_additional_fields'] as $field_name => $field_name_value) {
                            
                            $this->product_additional_fields[$field_name] = $field_name_value;

                        }

                    }

                    if (isset($sync_params['products_media_field_names']) && !empty($sync_params['products_media_field_names'])){

                        $this->media_field_names['products'] = $sync_params['products_media_field_names'];

                    }
                    
                    $time_ini_sync_stored_product = microtime(1);
                    $this->debbug(' >> Product synchronization initialized << ');
                    $result_update = $this->sync_stored_product($item_data);
                    $this->debbug(' >> Product synchronization finished << ');
                    $this->debbug('#### time_sync_stored_product: '.(microtime(1) - $time_ini_sync_stored_product).' seconds.', 'timer');
                    break;

                case 'product_format':
                    
                    $this->avoid_stock_update = $sync_params['avoid_stock_update'];
                    $this->format_configurable_attributes = $sync_params['format_configurable_attributes'];

                    foreach ($this->product_format_fields as $product_format_field) {
                        
                        if (isset($sync_params['product_format_fields'][$product_format_field])){

                            if ($product_format_field == 'image_extensions'){

                                foreach ($sync_params['product_format_fields'][$product_format_field] as $extension_name => $extension_value) {
                                    
                                    $this->$extension_name = $extension_value;

                                }

                                continue;

                            }
                            
                            $this->$product_format_field = $sync_params['product_format_fields'][$product_format_field];

                        }

                    }

                    if (isset($sync_params['product_formats_media_field_names']) && !empty($sync_params['product_formats_media_field_names'])){

                        $this->media_field_names['product_formats'] = $sync_params['product_formats_media_field_names'];

                    }
                    
                    $time_ini_sync_stored_product_format = microtime(1);
                    $this->debbug(' >> Format synchronization initialized << ');
                    $result_update = $this->sync_stored_product_format($item_data);
                    $this->debbug(' >> Format synchronization finished << ');
                    $this->debbug('#### time_sync_stored_product_format: '.(microtime(1) - $time_ini_sync_stored_product_format).' seconds.', 'timer');
                    break;

                case 'product_links':
                    
                    $time_ini_sync_stored_product_links = microtime(1);
                    $this->debbug(' >> Product links synchronization initialized << ');
                    $this->sync_stored_product_links($item_data);
                    $this->debbug(' >> Product links synchronization finished << ');
                    $result_update = 'item_updated';
                    $this->debbug('#### time_sync_stored_product_links: '.(microtime(1) - $time_ini_sync_stored_product_links).' seconds.', 'timer');
                    break;

                case 'product__images':
                
                    $result_update = 'item_updated';
                    
                    if (!isset($item_data['product_id']) && !isset($item_data['format_id'])){

                        $this->debbug('## Error. Updating item images - Unknown index: '.print_R($item_data,1), 'syncdata');

                    }else{

                        $item_index = 'product';

                        if (isset($item_data['format_id'])){

                            $item_index = 'format';

                        }

                        $time_ini_sync_stored_product_images = microtime(1);
                        $this->debbug(' >> '.ucfirst($item_index).' images synchronization initialized << ');
                        $this->sync_stored_product_images($item_data, $item_index);
                        $this->debbug(' >> '.ucfirst($item_index).' images synchronization finished << ');
                        $this->debbug('#### time_sync_stored_product_images: '.(microtime(1) - $time_ini_sync_stored_product_images).' seconds.', 'timer');
                    }

                    break;

                default:
                    
                    $this->debbug('## Error. Incorrect item: : '.print_R($item_to_update,1), 'syncdata');
                    break;
            }

        }

        switch ($result_update) {
            case 'item_not_updated':
                
                $sync_tries++;
                
                if ($sync_tries == 2 && $item_to_update['item_type'] == 'category'){

                    $resultado = $this->reorganize_category_parent_ids($item_data);

                    $sql_update = " UPDATE ".$this->saleslayer_syncdata_table.
                                            " SET sync_tries = ".$sync_tries.", ".
                                            " item_data = '".json_encode($resultado)."'".
                                            " WHERE id = ".$item_to_update['id'];

                    $this->sl_connection_query($sql_update);

                }else{
                    
                    $sql_update = " UPDATE ".$this->saleslayer_syncdata_table.
                                            " SET sync_tries = ".$sync_tries.
                                            " WHERE id = ".$item_to_update['id'];

                    $this->sl_connection_query($sql_update);

                }

                break;
            
            default:
                
                $this->sql_items_delete[] = $item_to_update['id'];
                break;

        }

        $this->check_sql_items_delete(true);

    }

}