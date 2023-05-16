<?php

/**
 * Main controller class
 *
 * @author croydon
 */
class ISPConfigConnector {
	
	private static $db_objects = array();
	private static $ispc_config = null;
	
	public static function parseConfig($code) {
		$ispc_config = array();
		$matches = array();
		preg_match_all('/(?:^|;\s*)\$conf\[(["\'])(.*?)\\1\]\s*=\s*("(?:\\\\.|[^\\\\"])*"|\'(?:\\\\.|[^\\\\\'])*\'|\d+(?:\.\d+)?|[a-zA-Z_]+|\$\w+|\w+\s*\()/is', $code, $matches, PREG_SET_ORDER);
		for($i = 0; $i < count($matches); $i++) {
			$key = $matches[$i][2];
			$val = $matches[$i][3];
			
			if(substr($val, 0, 1) === '"' || substr($val, 0, 1) === "'") {
				$val = substr($val, 1, -1);
			}
			$ispc_config[$key] = $val;
		}
		unset($code);
		
		return $ispc_config;
	}
	
	/**
	 * This function is far from being a php parser, it is optimized for parsing the config.inc.php of ISPConfig
	 * 
	 * @return array|boolean
	 */
	public static function getLocalConfig() {
		if(!file_exists('/usr/local/ispconfig/server/lib/config.inc.php')) {
			return false;
		}
		
		if(is_array(self::$ispc_config) && !empty(self::$ispc_config)) return self::$ispc_config;
		
		$code = php_strip_whitespace('/usr/local/ispconfig/server/lib/config.inc.php');
		
		$ispc_config = self::parseConfig($code);
		
		if(is_array($ispc_config) && !empty($ispc_config)) {
			self::$ispc_config = $ispc_config;
		}
		
		return $ispc_config;
	}
	
	/**
	 * @return ISPConfigDatabase
	 * @throws ISPConfigDatabaseException
	 */
	public static function getDatabaseInstance() {
		$conf = self::getLocalConfig();
		
		if(empty($conf)) {
			throw new ISPConfigDatabaseException('Database config could not be read from local instance config.');
		} elseif(!isset($conf['db_host']) || !$conf['db_host']) {
			throw new ISPConfigDatabaseException('Database config is missing db_host setting.');
		} elseif(!isset($conf['db_database']) || !$conf['db_database']) {
			throw new ISPConfigDatabaseException('Database config is missing db_database setting.');
		} elseif(!isset($conf['db_user']) || !$conf['db_user']) {
			throw new ISPConfigDatabaseException('Database config is missing db_user setting.');
		} elseif(!isset($conf['db_password']) || !$conf['db_password']) {
			throw new ISPConfigDatabaseException('Database config is missing db_password setting.');
		}
		
		if(!isset($conf['db_port']) || !$conf['db_port']) {
			$conf['db_port'] = 3306;
		}
		
		$ident = sha1(implode('::', array($conf['db_host'], $conf['db_database'], $conf['db_user'], $conf['db_password'], $conf['db_port'])));
		
		if(!isset(self::$db_objects[$ident]) || !is_object(self::$db_objects[$ident])) {
			self::$db_objects[$ident] = new ISPConfigDatabase($conf['db_database'], $conf['db_host'], $conf['db_user'], $conf['db_password'], 0, $conf['db_port']);
		}
		
		return self::$db_objects[$ident];
	}
	
	/**
	 * @param string $username
	 * @return int|boolean
	 */
	public static function getClientIdByUsername($username) {
		$DB = self::getDatabaseInstance();
		
		$qrystr = 'SELECT `client_id` FROM `client` WHERE `username` = ?';
		$client = $DB->query_one($qrystr, $username);
		if(!$client) {
			return false;
		} else {
			return $client['client_id'];
		}
	}
	
	public static function generateKeyPair() {
		if(!@is_dir(TMP_DIR)) {
			if(!@mkdir(TMP_DIR, 0777, true)) {
				throw new ISPConfigLogException('Temp path ' . TMP_DIR . ' could not be created.');
			}
		}
		
		if(!function_exists('openssl_pkey_get_private')) {
			throw new ISPConfigException('OpenSSL extension missing. Cannot generate keys.');
		}
		
		$program = explode("\n", shell_exec('which ssh-keygen'));
		$program = reset($program);
		if(!$program || !is_executable($program)) {
			throw new ISPConfigException('Could not generate key pair. Missing ssh-keygen.');
		}
		
		$trys = 0;
		while(true) {
			$trys++;
			$file_name = TMP_DIR . '/' . sha1(uniqid('ssh-', true));
			if(file_exists($file_name)) {
				if($trys < 25) {
					continue;
				}
				throw new ISPConfigException('Could not generate key pair unique file name.');
			}
			break;
		}
		
		$out = null;
		$retval = 0;
		exec('echo | ' . $program . ' -b 4096 -t rsa -f ' . escapeshellarg($file_name) . ' -q -N "" >/dev/null 2>&1', $out, $retval);
		if($retval != 0) {
			throw new ISPConfigException('Could not generate key pair. ssh-keygen returned non-zero code: ' . $retval);
		} elseif(!file_exists($file_name) || !file_exists($file_name . '.pub')) {
			throw new ISPConfigException('Could not generate key pair. Key files missing.');
		}
		
		$fprint = trim(shell_exec($program . ' -E md5 -lf ' . escapeshellarg($file_name . '.pub') . ' | awk \'{print $2}\''));
		if(substr($fprint, 0, 4) === 'MD5:') {
			$fprint = substr($fprint, 4);
		}
		
		$key = array(
			'private' => trim(file_get_contents($file_name)),
			'public' => trim(file_get_contents($file_name . '.pub')),
			'fingerprint' => $fprint
		);
		if(!$key['private']) {
			throw new ISPConfigException('Could not read private key.');
		}
		
		$res = openssl_pkey_get_private($key['private']);
		if(!$res) {
			throw new ISPConfigException('Could not verify private key.');
		}
		
		openssl_pkey_free($res);
		
		if(!$key['public']) {
			throw new ISPConfigException('Could not read public key.');
		}
		
		unlink($file_name);
		unlink($file_name . '.pub');
		
		return $key;
	}
}