<?php

define('APP_DIR', realpath(dirname(__FILE__)));
define('LIB_DIR', APP_DIR . '/lib');
define('LOG_DIR', APP_DIR . '/var/log');
define('TMP_DIR', APP_DIR . '/var/tmp');
define('CACHE_DIR', APP_DIR . '/var/cache');

require_once LIB_DIR . '/class.ISPConfig.inc.php';

try {
	ISPConfigLog::setLogPriority(ISPConfigLog::PRIO_INFO);
	ISPConfig::run();
} catch(Exception $e) {
	ISPConfigLog::error('Exception occured: ' . get_class($e) . ' -> ' . $e->getMessage(), true);
	//var_dump($e);
	exit;
}