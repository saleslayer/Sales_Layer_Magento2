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
use \Magento\Framework\App\Config\ScopeConfigInterface as scopeConfigInterface;
use \Magento\Tax\Model\ClassModel as tax_class_model;
use \Magento\Downloadable\Api\Data\LinkInterfaceFactory as linkInterfaceFactory;
use \Magento\Downloadable\Api\Data\SampleInterfaceFactory as sampleInterfaceFactory;
use \Magento\Downloadable\Api\Data\File\ContentInterfaceFactory as contentInterfaceFactory;

/**
 * Class Saleslayer_Synccatalog_Model_Indexerscron
 */
class Indexerscron extends Synccatalog{
    
    protected $sl_time_ini_indexers_process;
    protected $max_indexers_execution_time    = 590;
    protected $end_idx_process                = false;

    /**
     * Sales Layer Autosync constructor.
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
                scopeConfigInterface $scopeConfigInterface,
                tax_class_model $tax_class_model,
                linkInterfaceFactory $linkInterfaceFactory,
                sampleInterfaceFactory $sampleInterfaceFactory,
                contentInterfaceFactory $contentInterfaceFactory,
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
                            $scopeConfigInterface,
                            $tax_class_model,
                            $linkInterfaceFactory,
                            $sampleInterfaceFactory,
                            $contentInterfaceFactory,
                            $resource,
                            $resourceCollection,
                            $data);

    }

    /**
     * Function to check current indexers process time to avoid exceding the limit.
     * @return void
     */
    private function check_indexers_process_time(){

        $current_process_time = microtime(1) - $this->sl_time_ini_indexers_process;
        
        if ($current_process_time >= $this->max_indexers_execution_time){

            $this->end_idx_process = true;

        }

    }

    /**
     * Function to process Magento indexers stored in indexers table.
     * @return void
     */
    public function sync_indexers(){

        $this->loadConfigParameters();

        $this->sl_time_ini_indexers_process = microtime(1);

        $this->debbug("==== Indexers INIT ".date('Y-m-d H:i:s')." ====", 'indexers');

        $indexers_to_process = $this->connection->query(" SELECT count(*) as count FROM ".$this->saleslayer_indexers_table)->fetch();

        if (isset($indexers_to_process['count']) && $indexers_to_process['count'] == 0){

            $this->debbug("No indexers to process, finishing indexer CRON.", 'indexers');

        }else{

            $items_processing = $this->connection->query(" SELECT count(*) as count FROM ".$this->saleslayer_syncdata_table." WHERE sync_type in('delete','update') and sync_tries <= 2")->fetch();

            if (isset($items_processing['count']) && $items_processing['count'] == 0){
                
                if (!in_array($this->sync_data_hour_from, array('', null, 0)) || !in_array($this->sync_data_hour_until, array('', null, 0))){

                    $hour_from = $this->sync_data_hour_from.':00';
                    $hour_from_time = strtotime($hour_from);
                    $hour_until = $this->sync_data_hour_until.':00';
                    $hour_until_time = strtotime($hour_until);
                    $hour_now = date('H').':00';
                    $hour_now_time = strtotime($hour_now);
                
                    if (($hour_from_time < $hour_until_time && $hour_now_time >= $hour_from_time && $hour_now_time <= $hour_until_time) || ($hour_from_time > $hour_until_time && ($hour_now_time >= $hour_from_time || $hour_now_time <= $hour_until_time)) || $hour_from_time == $hour_until_time){
                        
                        $this->debbug('Current hour '.$hour_now.' for indexers process.', 'indexers');
                    
                    } else {
                    
                        $this->end_idx_process = true;
                        $this->debbug('Current hour '.$hour_now.' is not set between hour from '.$hour_from.' and hour until '.$hour_until.'. Finishing indexers process.', 'indexers');
                    
                    }

                }

                if (!$this->end_idx_process){

                    do{

                        try{

                            //Clear indexers exceeded attemps
                            
                            $sql_delete = " DELETE FROM ".$this->saleslayer_indexers_table." WHERE sync_tries >= 3";

                            $this->sl_connection_query($sql_delete);

                        }catch(\Exception $e){

                            $this->debbug('## Error. Clearing indexers exceeded attemps: '.$e->getMessage(), 'indexers');

                        }

                        $indexers_data = $this->connection->fetchAll(" SELECT * FROM ".$this->saleslayer_indexers_table);
                        
                        if (!empty($indexers_data)){
                            
                            foreach ($indexers_data as $indexer_data) {
                                
                                $indexer_processed = true;
                                $time_ini_indexer = microtime(1);
                                $sync_tries = $indexer_data['sync_tries']; 

                                $this->debbug('Processing indexer '.$indexer_data['indexer_title'].' with id '.$indexer_data['indexer_id'].'...', 'indexers');

                                try{
                                    
                                    $indexer = clone $this->indexer;
                                    $indexer->load($indexer_data['indexer_id']);
                                    $indexer->getState()->setStatus($indexer_data['indexer_status']);
                                    $indexer->getState()->save();
                                    $indexer->reindexAll();
                                    
                                    $this->debbug('Indexer '.$indexer_data['indexer_title'].' with id '.$indexer_data['indexer_id'].' status updated back to: '.$indexer_data['indexer_status'].' and reindexed.', 'indexers');
                                    $this->debbug('## time_indexer '.$indexer_data['indexer_title'].' to original value: '.(microtime(1) - $time_ini_indexer).' seconds.', 'indexers');
                                    $this->debbug('## time_indexer '.$indexer_data['indexer_title'].' to original value: '.(microtime(1) - $time_ini_indexer).' seconds.', 'timer');

                                }catch(\Exception $e){

                                    $indexer_processed = false;
                                    $sync_tries++;

                                    $this->debbug('## Error. Updating indexer '.$indexer_data['indexer_title'].' with id '.$indexer_data['indexer_id'].' status back and reindexing. Try number '.$sync_tries.'. Message: '.$e->getMessage(), 'indexers');
                                    
                                }

                                if ($indexer_processed){

                                    $indexer_sql = " DELETE FROM ".$this->saleslayer_indexers_table.
                                                        " WHERE id = ".$indexer_data['id'];


                                }else{

                                    $indexer_sql = " UPDATE ".$this->saleslayer_indexers_table.
                                                            " SET sync_tries = ".$sync_tries.
                                                            " WHERE id = ".$indexer_data['id'];
                                                            
                                }
                                
                                $this->sl_connection_query($indexer_sql);

                                $this->check_indexers_process_time();

                                if ($this->end_idx_process){

                                    $this->debbug('Breaking syncdata process due to time limit.', 'indexers');
                                    break 2;

                                }
                            
                            }

                            $sl_query_indexers_table_reset_ai = " ALTER TABLE ".$this->saleslayer_indexers_table. " AUTO_INCREMENT = 1";
                            
                            $this->connection->rawQuery($sl_query_indexers_table_reset_ai);

                        }

                    }while(!empty($indexers_data)); 

                }

            }else{

                $this->debbug("There are still ".$items_processing['count']." items to process, finishing indexer CRON.", 'indexers');

            }

        }        

        $this->debbug('### time_all_indexers_process: '.(microtime(1) - $this->sl_time_ini_indexers_process).' seconds.', 'indexers');

        $this->debbug("==== Indexers END ====", 'indexers');

    }

}