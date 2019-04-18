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
 * Class Saleslayer_Synccatalog_Model_Autosynccron
 */
class Autosynccron extends Synccatalog{
    
    protected       $sl_time_ini_auto_sync_process;
    protected       $cron_schedule_table            = 'cron_schedule';
    protected       $cronSchedule;


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
        $this->cron_schedule_table           = $this->resourceConnection->getTableName($this->cron_schedule_table);

    }
    
    /**
     * Function to sort connectors by unix_to_update or auto_sync values.
     * @param  array $conn_a                first connector to sort
     * @param  array $conn_b                second connector to sort
     * @return integer                      comparative of connectors
     */
    private function sort_by_unix_to_update($conn_a, $conn_b) {

        $unix_a = $conn_a['unix_to_update'];
        $unix_b = $conn_b['unix_to_update'];

        if ($unix_a == $unix_b) {
             
            $auto_a = $conn_a['auto_sync'];
            $auto_b = $conn_b['auto_sync'];
           
            if ($auto_a == $auto_b){
                
                return 0;

            }   

            return ($auto_a > $auto_b) ? -1 : 1;

        }
      
        return ($unix_a < $unix_b) ? -1 : 1;
    
    }   

    private function check_sync_data_crons(){

        $now = strtotime('now');
        $date_now = date('Y-m-d H:i:s', $now);
        $current_flag = $this->connection->query(" SELECT * FROM ".$this->saleslayer_syncdata_flag_table." ORDER BY id DESC LIMIT 1")->fetch();

        if (!empty($current_flag)){

            if ($current_flag['syncdata_pid'] !== 0){
            
                $interval  = abs($now - strtotime($current_flag['syncdata_last_date']));
                
                if ($interval >= 480){

                    $flag_pid_is_alive = $this->has_pid_alive($current_flag['syncdata_pid']);
                
                    if ($flag_pid_is_alive){
                    
                        try{

                            $this->debbug('Killing pid: '.$current_flag['syncdata_pid'], 'autosync');
                            shell_exec("kill -9 ".$current_flag['syncdata_pid']);
                    
                        }catch(\Exception $e){
                    
                            $this->debbug('## Error. Exception killing pid '.$current_flag['syncdata_pid'].': '.print_r($e->getMessage(),1), 'autosync');
                    
                        }
                
                    }

                    $sl_query_flag_to_update = " UPDATE ".$this->saleslayer_syncdata_flag_table.
                                            " SET syncdata_pid = 0, syncdata_last_date = '".$date_now."'".
                                            " WHERE id = ".$current_flag['id'];

                    $this->sl_connection_query($sl_query_flag_to_update);

                }

            }

        }

        $running_crons = $this->connection->fetchAll(" SELECT * FROM ".$this->cron_schedule_table." WHERE job_code = 'Saleslayer_Synccatalog_Syncdatacron' AND status = 'running' AND executed_at IS NOT NULL");
        
        foreach ($running_crons as $keyRC => $running_cron) {
            
            $cron = $this->cronSchedule->load($running_cron['schedule_id']);
            $interval  = abs($now - strtotime($running_cron['executed_at']));

            if ($interval >= 480){

                $this->debbug('Killing cron job '.$running_cron['job_code'].' with schedule_id '.$running_cron['schedule_id'].'. Scheduled at '.$running_cron['scheduled_at'].', executed at '.$running_cron['executed_at'].' with time interval of '.$interval.' seconds.', 'autosync');

                try{

                    $cron->setStatus($cron::STATUS_ERROR);
                    $cron->setFinishedAt(date('Y-m-d H:i:s'));
                    $cron->setMessages('Sales Layer job terminated by Autosync verification check due to time limit.');
                    $cron->save();

                }catch(\Exception $e){

                    $this->debbug('## Error. Exception killing cron job '.$running_cron['schedule_id'].': '.print_r($e->getMessage(),1), 'autosync');

                }
         
            }

        }

    }


    /**
     * Function to check and synchronize Sales Layer connectors with auto-synchronization enabled.
     * @return void
     */
    public function auto_sync_connectors(){

        $this->loadConfigParameters();

        $this->debbug("==== AUTOSync INIT ".date('Y-m-d H:i:s')." ====", 'autosync');

        $this->check_sync_data_crons();

        $this->sl_time_ini_auto_sync_process = microtime(1);
        
        $items_processing = $this->connection->query(" SELECT count(*) as count FROM ".$this->saleslayer_syncdata_table)->fetch();

        if (isset($items_processing['count']) && $items_processing['count'] > 0){

            $this->debbug("There are still ".$items_processing['count']." items processing, wait until is finished and synchronize again.", 'autosync');
           
        }else{

            try {

                $all_connectors = $this->getConnectors();
                
                $now = strtotime('now');
                
                if (!empty($all_connectors)){

                    $connectors_to_check = array();

                    foreach ($all_connectors as $idx_conn => $connector) {

                        if ($connector['auto_sync'] > 0){
                            
                            $connector_last_sync = $connector['last_sync'];
                            $connector_last_sync_unix = strtotime($connector_last_sync);
                            
                            $unix_to_update = $now - ($connector['auto_sync'] * 3600);
                            
                            if ($connector_last_sync_unix == ''){

                                $connector['unix_to_update'] = $unix_to_update;
                                $connectors_to_check[] = $connector;

                            }else{
                                
                                if ($connector['auto_sync'] >= 24){
                                    
                                    $unix_to_update_hour = mktime($connector['auto_sync_hour'],0,0,date('m', $unix_to_update),date('d', $unix_to_update),date('Y', $unix_to_update));
                                    
                                    if ($connector_last_sync_unix < $unix_to_update_hour){
                                    
                                        $connector['unix_to_update'] = $unix_to_update_hour;
                                        $connectors_to_check[] = $connector;

                                    }


                                }else if ($connector_last_sync_unix < $unix_to_update){

                                    $connector['unix_to_update'] = $unix_to_update;
                                    $connectors_to_check[] = $connector;

                                }

                            }

                        }

                    }

                    if ($connectors_to_check){

                        uasort($connectors_to_check, array($this, 'sort_by_unix_to_update'));

                        foreach ($connectors_to_check as $connector) {

                            if ($connector['auto_sync'] >= 24){

                                $last_sync_time = mktime($connector['auto_sync_hour'],0,0,date('m', $now),date('d', $now),date('Y', $now));
                                $last_sync = date('Y-m-d H:i:s', $last_sync_time);
                            
                            }else{
                            
                                $last_sync = date('Y-m-d H:i:s');
                            
                            }

                            $connector_id = $connector['connector_id'];

                            $this->debbug("Connector to auto-synchronize: " . $connector_id, 'autosync');

                            $time_ini_cron_sync = microtime(1);

                            $time_random = rand(10, 20);
                            sleep($time_random);
                            $this->debbug("#### time_random: " . $time_random . ' seconds.', 'autosync');
                            
                            $data_return = $this->store_sync_data($connector_id, $last_sync);
                            
                            $this->debbug("#### time_cron_sync: " . (microtime(1) - $time_ini_cron_sync - $time_random) . ' seconds.', 'autosync');
                            
                            if (is_array($data_return)){ break; }
                            
                        }

                    }else{

                        $this->debbug("Currently there aren't connectors to synchronize.", 'autosync');

                    }
              
                }else{

                    $this->debbug("There aren't any configured connectors.", 'autosync');

                }
            } catch (\Exception $e) {

                $this->debbug('## Error. Autosync process: '.$e->getMessage(), 'autosync');

            }

        }

        $this->debbug('##### time_all_autosync_process: '.(microtime(1) - $this->sl_time_ini_auto_sync_process).' seconds.', 'autosync');

        $this->debbug("==== AUTOSync END ====", 'autosync');

    }
}