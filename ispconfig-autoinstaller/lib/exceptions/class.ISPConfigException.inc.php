<?php

/**
 * Basic exception
 *
 * @author croydon
 */
class ISPConfigException extends Exception {
	public function __construct($message = "", $code = 0, $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}

class ISPConfigClassException extends ISPConfigException {}
class ISPConfigLogException extends ISPConfigException {}
class ISPConfigModuleException extends ISPConfigException {}
class ISPConfigDatabaseException extends ISPConfigException {}
class ISPConfigOSException extends ISPConfigException {}