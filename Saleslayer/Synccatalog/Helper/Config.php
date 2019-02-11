<?php

namespace Saleslayer\Synccatalog\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;

class Config extends AbstractHelper
{
    /** Config params */
    const CONFIG_SYNCCATALOG_GENERAL_ACTIVATE_DEBUG_LOGS  = 'synccatalog/general/activate_debug_logs';
    const CONFIG_SYNCCATALOG_GENERAL_MANAGE_INDEXERS = 'synccatalog/general/manage_indexers';
    const CONFIG_SYNCCATALOG_GENERAL_SQL_TO_INSERT_LIMIT  = 'synccatalog/general/sql_to_insert_limit';

    /**
     * Activate debug logs debuger levels
     * 0 and 1 will be only visible at the moment.
     */
    const ADL_DEBUGER_LEVEL_DISABLED = 0;
    const ADL_DEBUGER_LEVEL_ENABLED = 1;
    const ADL_DEBUGER_LEVEL_CONF_FIELDS = 2;
    const ADL_DEBUGER_LEVEL_ALL_DATA = 3;

    /**
     * Retrieve debuger level option
     * @return integer
     */
    public function getDebugerLevel(){

        return (int) $this->scopeConfig->getValue(self::CONFIG_SYNCCATALOG_GENERAL_ACTIVATE_DEBUG_LOGS);
    
    }

    /**
     * Retrieve manage indexers option
     * @return int
     */
    public function getManageIndexers(){

        return (int) $this->scopeConfig->getValue(self::CONFIG_SYNCCATALOG_GENERAL_MANAGE_INDEXERS);
    
    }

    /**
     * Retrieve SQL to insert limit option
     * @return int
     */
    public function getSqlToInsertLimit(){

        return (int) $this->scopeConfig->getValue(self::CONFIG_SYNCCATALOG_GENERAL_SQL_TO_INSERT_LIMIT);
    
    }

}