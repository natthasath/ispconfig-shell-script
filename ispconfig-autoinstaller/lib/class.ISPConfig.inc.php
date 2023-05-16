<?php


if(function_exists('spl_autoload_register')) {
	spl_autoload_register('ISPConfig::autoload');
}

define('ISPC_WEBSERVER_NONE', 0);
define('ISPC_WEBSERVER_APACHE', 1);
define('ISPC_WEBSERVER_NGINX', 2);

/**
 * Main controller class
 *
 * @author croydon
 */
class ISPConfig {
	private static $is_cli_run = false;
	private static $cli_script = false;

	private static $autoload_files = array(
		'PXBashColor' => LIB_DIR . '/libbashcolor.inc.php'
	);

	public static $WEBSERVER = ISPC_WEBSERVER_APACHE;

	private static function init() {
		if(!isset($_GET)) {
			$_GET = array();
		}
		if(php_sapi_name() == 'cli') {
			self::$is_cli_run = true;

			$argc = 0;
			$argv = array();
			if(isset($_SERVER['argc'])) {
				$argc = $_SERVER['argc'];
			}
			if(isset($_SERVER['argv'])) {
				$argv = $_SERVER['argv'];
			}

			if(isset($argv[0])) {
				self::$cli_script = basename($argv[0]);
			}

			for($a = 1; $a < $argc; $a++) {
				if(substr($argv[$a], 0, 2) == '--') {
					$sArg = substr($argv[$a], 2);
					if(strpos($sArg, '=') !== false) {
						list($sKey, $sValue) = explode('=', $sArg);
					} else {
						$sKey = $sArg;
						$sValue = true;
					}
					$_GET[$sKey] = $sValue;
				} else {
					self::printHelp();
					exit;
				}
			}
		}

		if(!self::shallInstall('web')) {
			self::$WEBSERVER = ISPC_WEBSERVER_NONE;
		} elseif(isset($_GET['use-nginx']) && $_GET['use-nginx']) {
			self::$WEBSERVER = ISPC_WEBSERVER_NGINX;
		} else {
			self::$WEBSERVER = ISPC_WEBSERVER_APACHE;
		}
	}

	private static function input() {
		$input = fgets(STDIN);
		return rtrim($input);
	}

	public static function ask($prompt) {
		print $prompt . ': ';
		return self::input();
	}

	/**
	 * @param string $class_name
	 * @throws ISPConfigClassException
	 */
	public static function autoload($class_name) {
		if(preg_match('/^\w+$/', $class_name) === false) {
			throw new ISPConfigClassException($class_name . ' is not a valid class name.');
		}

		$class_dir = LIB_DIR;
		if(preg_match('/Exception$/', $class_name)) {
			$class_dir .= '/exceptions';
		} elseif(preg_match('/Module$/', $class_name)) {
			$class_dir .= '/modules';
		} elseif(preg_match('/API$/', $class_name)) {
			$class_dir .= '/api';
		} elseif(preg_match('/OS$/', $class_name)) {
			$class_dir .= '/os';
		}

		$use_file = null;
		if(isset(self::$autoload_files[$class_name])) {
			$use_file = self::$autoload_files[$class_name];
		} elseif(file_exists($class_dir . '/class.' . $class_name . '.inc.php')) {
			$use_file = $class_dir . '/class.' . $class_name . '.inc.php';
		} elseif(file_exists($class_dir . '/class.' . strtolower($class_name) . '.inc.php')) {
			$use_file = $class_dir . '/class.' . strtolower($class_name) . '.inc.php';
		} elseif(preg_match('/^ISPConfig\w+Exception$/', $class_name)) {
			$use_file = LIB_DIR . '/exceptions/class.ISPConfigException.inc.php';
		} else {
			throw new ISPConfigClassException('No class file for ' . $class_name . ' found.');
		}

		if($class_name != 'ISPConfigLog') {
			ISPConfigLog::debug('Trying to autoload class file "' . $use_file . '" for class "' . $class_name . '"');
		}

		if(!file_exists($use_file)) {
			throw new ISPConfigClassException('File ' . $use_file . ' not found for class ' . $class_name . '.');
		}

		include_once $use_file;
		if(!class_exists($class_name)) {
			throw new ISPConfigClassException($class_name . ' not found in file ' . LIB_DIR . '/class.' . $class_name . '.inc.php.');
		}
	}

	/**
	 * @return boolean
	 */
	public static function isCLI() {
		return self::$is_cli_run;
	}

	/**
	 * @return string
	 */
	public static function getScriptName() {
		return self::$cli_script;
	}

	public static function shallInstall($what) {
		if(isset($_GET['no-'.$what]) && $_GET['no-'.$what]) {
			return false;
		} else {
			return true;
		}
	}

	public static function wantsInteractive() {
		if(isset($_GET['interactive']) && $_GET['interactive']) {
			return true;
		} else {
			return false;
		}
	}

	public static function wantsUnbound() {
		if(isset($_GET['use-unbound']) && $_GET['use-unbound']) {
			return true;
		} else {
			return false;
		}
	}

	public static function wantsAmavis() {
		if(isset($_GET['use-amavis']) && $_GET['use-amavis']) {
			return true;
		} else {
			return false;
		}
	}

	public static function wantsCertbot() {
		if(isset($_GET['use-certbot']) && $_GET['use-certbot']) {
			return true;
		} else {
			return false;
		}
	}

	public static function wantsPHP() {
		// If a new version is added, the getApacheModulesToDisable function should be updated to disable the latest version (this part could be improved)
		$available_php_versions = array(
			'5.6',
			'7.0',
			'7.1',
			'7.2',
			'7.3',
			'7.4',
			'8.0',
			'8.1'
		);
		if(isset($_GET['use-php']) && $_GET['use-php']) {
			if ($_GET['use-php'] === 'system') {
				return $_GET['use-php'];
			} else {
				$use_php = explode(',',$_GET['use-php']);
				$php_versions = array_intersect($use_php, $available_php_versions);
				if(!empty($php_versions)) {
					return $php_versions;
				} else {
					return false;
				}
			}
		} else {
			return $available_php_versions;
		}
	}

	public static function wantsRoundcube() {
		if(isset($_GET['roundcube']) && $_GET['roundcube']) {
			return true;
		} else {
			return false;
		}
	}

	public static function wantsMonit() {
		if(isset($_GET['monit']) && $_GET['monit']) {
			return true;
		} else {
			return false;
		}
	}

	public static function getMonitAlertEmail() {
		if(isset($_GET['monit-alert-email']) && $_GET['monit-alert-email']) {
			return $_GET['monit-alert-email'];
		} else {
			return '';
		}
	}

	public static function getSecureShellPort() {
		if(isset($_GET['ssh-port']) && $_GET['ssh-port']) {
			return $_GET['ssh-port'];
		} else {
			return '';
		}
	}

	public static function getSecureShellPermitRoot() {
		if(isset($_GET['ssh-permit-root']) && $_GET['ssh-permit-root']) {
			return $_GET['ssh-permit-root'];
		} else {
			return '';
		}
	}

	public static function getSecureShellPasswordAuthentication() {
		if(isset($_GET['ssh-password-authentication']) && $_GET['ssh-password-authentication']) {
			return $_GET['ssh-password-authentication'];
		} else {
			return '';
		}
	}

	public static function wantsSecureShellHardened() {
		if(isset($_GET['ssh-harden']) && $_GET['ssh-harden']) {
			return true;
		} else {
			return false;
		}
	}

	public static function wantsUnattendedUpgrades() {
		if(isset($_GET['unattended-upgrades']) && $_GET['unattended-upgrades']) {
			return true;
		} else {
			return false;
		}
	}

	public static function getUnattendedUpgradesOptions() {
		if(isset($_GET['unattended-upgrades']) && $_GET['unattended-upgrades']) {
			if ($_GET['unattended-upgrades'] === '') {
				return $_GET['unattended-upgrades'];
			} else {
				$unattendedupgrades_options = explode(',',$_GET['unattended-upgrades']);
				if(!empty($unattendedupgrades_options)) {
					return $unattendedupgrades_options;
				} else {
					return false;
				}
			}
		}
	}

	public static function getISPConfigChannel() {
		if(isset($_GET['channel']) && $_GET['channel']) {
			return $_GET['channel'];
		} else {
			return 'stable';
		}
	}

	public static function getFTPPassivePorts() {
		if(isset($_GET['use-ftp-ports'])) {
			list($from, $to) = explode('-', $_GET['use-ftp-ports']);
			return array(
				'from' => intval($from),
				'to' => intval($to)
			);
		}
		return false;
	}

	private static function printHelp() {
		$message = '

{FW}*
ISPConfig 3 Autoinstaller
{FW}*


Usage: ispc3-ai.sh [<argument>] [...]

This script automatically installs all needed packages for an ISPConfig 3 setup using the guidelines from the "Perfect Server Setup" howtos on www.howtoforge.com.

Possible arguments are:
	--help			->Show this help page
	--debug			->Enable verbose logging (logs each command with the exit code)
	--channel		->Choose the channel to use for ISPConfig. --channel=<stable|dev>
					->"stable" is the latest ISPConfig release available on www.ispconfig.org
					->"dev" is the latest dev-branch from the ISPConfig git repository: https://git.ispconfig.org/ispconfig/ispconfig3/tree/develop
					-> The dev channel might contain bugs and less-tested features and should only be used in production by very experienced users.
	--lang			->Use language for ISPConfig installation. Specify with --lang=en|de (only en (English) and de (German) supported currently).
	--interactive	->Don\'t install ISPConfig in non-interactive mode. This is needed if you want to use expert mode, e. g. to install a slave server that shall be integrated into an existing multiserver setup.
	--use-nginx		->Use nginx webserver instead of apache2
	--use-amavis	->Use amavis instead of rspamd for mail filtering
	--use-unbound	->Use unbound instead of bind9 for local resolving. Only allowed if --no-dns is set.
	--use-php		->Use specific PHP versions, comma separated, instead of installing multiple PHP, e.g. --use-php=7.4,8.0 (5.6, 7.0, 7.1, 7.2, 7.3, 7.4, 8.0 and 8.1 available).
					->--use-php=system disables the sury repository and just installs the system\'s default PHP version.
					->ommiting the argument (use all versions)
	--use-ftp-ports ->This option sets the passive port range for pure-ftpd. You have to specify the port range separated by hyphen, e. g. --use-ftp-ports=40110-40210.
					->If not provided the passive port range will not be configured.
	--use-certbot	->Use Certbot instead of acme.sh for issuing Let\'s Encrypt certificates. Not adviced unless you are migrating from a old server that uses Certbot.
	--no-web		->Do not use ISPConfig on this server to manage webserver setting and don\'t install nginx/apache or pureftpd. This will also prevent installing an ISPConfig UI and implies --no-roundcube as well as --no-pma
	--no-mail		->Do not use ISPConfig on this server to manage mailserver settings. This will install postfix for sending system mails, but not dovecot and not configure any settings for ISPConfig mail. It implies --no-mailman.
	--no-dns		->Do not use ISPConfig on this server to manage DNS entries. Bind will be installed for local DNS caching / resolving only.
	--no-local-dns	->Do not install local DNS caching / resolving via bind.
	--no-firewall	->Do not install ufw and tell ISPConfig to not manage firewall settings on this server.
	--no-roundcube	->Do not install roundcube webmail.
	--roundcube		->Install Roundcube even when --no-mail is used. Manual configuration of Roundcube config is needed.
	--no-pma		->Do not install PHPMyAdmin on this server.
	--no-mailman	->Do not install Mailman mailing list manager.
	--no-quota		->Disable file system quota
	--no-ntp		->Disable NTP setup
	--monit			->Install Monit and set it up to monitor installed services. Supported services: Apache2, NGINX, MariaDB, pure-ftpd-mysql, php-fpm, ssh, named, Postfix, Dovecot, rspamd.
	--monit-alert-email
					->Set up alerts for Monit to be send to given e-mail address. e.g. --monit-alert-email=me@example.com
	--ssh-port		-> Configure the SSH server to listen on a non-default port. Port number must be between 1 and 65535 and can not be in use by other services. e.g. --ssh-port=64
	--ssh-permit-root
					-> Configure the SSH server wether or not to allow root login. Available options: yes | without-password | no - e.g. --ssh-permit-root=without-password
	--ssh-password-authentication
					->  Configure the SSH server wether or not to allow password authentication. Available options:  yes | no - e.g. -ssh-password-authentication=no
	--ssh-harden	-> Configure the SSH server to have a stronger security config.
	--unattended-upgrades
					->Install UnattendedUpgrades. You can add extra arguments for automatic cleanup and automatic reboots when necessary with --unattended-upgrades=autoclean,reboot (or only one of them).
	--i-know-what-i-am-doing
					->Prevent the autoinstaller to ask for confirmation before continuing to reconfigure the server.
';

		ISPConfigLog::print($message);
		exit;
	}

	/**
	 * @throws ISPConfigModuleException
	 */
	public static function run() {
		self::init();

		$pmatch = null;

		$valid_args = array(
			'help', 'debug', 'interactive',
			'use-nginx', 'use-amavis', 'use-php', 'use-unbound', 'use-ftp-ports', 'use-certbot',
			'unattended-upgrades', 'roundcube', 'monit', 'monit-alert-email',
			'ssh-port', 'ssh-permit-root', 'ssh-password-authentication', 'ssh-harden',
			'channel', 'lang',
			'no-web', 'no-mail', 'no-dns', 'no-firewall', 'no-roundcube', 'no-pma', 'no-mailman', 'no-quota', 'no-ntp', 'no-local-dns',
			'i-know-what-i-am-doing'
		);

		reset($_GET);
		foreach(array_keys($_GET) as $key) {
			if(!in_array($key, $valid_args, true)) {
				self::printHelp();
				exit;
			}
		}

		if(isset($_GET['help']) && $_GET['help']) {
			self::printHelp();
			exit;
		} elseif(isset($_GET['channel']) && !in_array($_GET['channel'], array('stable', 'dev'), true)) {
			self::printHelp();
			exit;
		} elseif(isset($_GET['lang']) && !in_array($_GET['lang'], array('de', 'en'), true)) {
			self::printHelp();
			exit;
		} elseif(isset($_GET['use-php']) && !self::wantsPHP()) {
			self::printHelp();
			exit;
		} elseif(isset($_GET['use-ftp-ports']) && (!preg_match('/^([1-9][0-9]+)-([1-9][0-9]+)$/', $_GET['use-ftp-ports'], $pmatch) || intval($pmatch[1]) >= intval($pmatch[2]))) {
			self::printHelp();
			exit;
		}

		if(!isset($_GET['i-know-what-i-am-doing']) || !$_GET['i-know-what-i-am-doing']) {
			print PXBashColor::getString('<lightred>WARNING!</lightred> This script will reconfigure your complete server!') . "\n";
			print 'It should be run on a freshly installed server and all current configuration that you have done will most likely be lost!' . "\n";
			$ok = ISPConfig::ask('Type \'yes\' if you really want to continue');
			if($ok !== 'yes') {
				print PXBashColor::getString('<lightred>ABORTED</lightred>') . "\n";
				exit;
			}
		}

		if(isset($_GET['debug']) && $_GET['debug']) {
			ISPConfigLog::setLogPriority(ISPConfigLog::PRIO_DEBUG);
		}

		// get operating system
		try {
			$os = ISPConfigBaseOS::getOSVersion();

			ISPConfigLog::info('Starting perfect server setup for ' . $os['NAME'], true);
			$installer = ISPConfigBaseOS::getOSInstance();
			$installer->runPerfectSetup();
			ISPConfigLog::info('<lightred>Warning:</lightred> Please delete the log files in ' . LOG_DIR . '/setup-* once you don\'t need them anymore because they contain your passwords!', true);
		} catch(Exception $ex) {
			throw $ex;
		}

		exit;
	}

}
