<?php
/**
 * Description of class
 *
 * @author croydon
 */
class ISPConfigDebianOS extends ISPConfigBaseOS {
	public function getPackageVersion($package) {
		$cmd = 'dpkg --list ' . $package . ' 2>&1';
		$result = $this->exec($cmd);
		$version = false;
		$matches = array();
		if(preg_match_all('/^ii\s+\S+\s+(\S+)(?:\s|$)/m', $result, $matches, PREG_SET_ORDER)) {
			for($i = 0; $i < count($matches); $i++) {
				$tmp_version = $matches[$i][1];
				if(!$version || ISPProtectFunctions::version_compare($version, $tmp_version, '<')) {
					$version = $tmp_version;
				}
			}
		}

		return $version;
	}

	public function getPackageAlias($package) {
		switch($package) {
			case 'libssl':
				$package = 'libssl[0-9]*';
				break;
			case 'kernel':
				$package = 'linux-image-[0-9]*';
				break;
		}

		return $package;
	}

	public function getUpdateCommand($mode = 'update') {
		$cmd = false;

		if($mode == 'prepare') {
			$cmd = 'DEBIAN_FRONTEND="noninteractive" apt-get update -qq -y';
		} elseif($mode == 'update') {
			// for updating all updateable packages
			$cmd = 'DEBIAN_FRONTEND="noninteractive" apt-get dist-upgrade -o Dpkg::Options::="--force-overwrite" -qq -y';
		} elseif($mode == 'install' || $mode == 'partly_update') {
			// for installing / updating specific packages
			$cmd = 'DEBIAN_FRONTEND="noninteractive" apt-get install -o Dpkg::Options::="--force-overwrite" -qq -y';
			$cmd .= ' <PACKAGES>';
		}

		$cmd = 'while fuser /var/lib/dpkg/lock >/dev/null 2>&1 || fuser /var/lib/apt/lists/lock >/dev/null 2>&1 ; do sleep 2; done; ' . $cmd . ' 2>&1';

		return $cmd;
	}

	public function getUpdatePackageRegex() {
		$regex = '^\w+\s+(?P<package>\S+)\s+(?:\[(?P<oldversion>\S+)\]\s*)?(?:\((?P<newversion>\S+))?(?:\s|$)';

		return $regex;
	}

	public function getInstallPackageRegex($mode = '') {
		if($mode == 'oldversion') {
			$regex = '(?P<package>\S+)\s+(?:(?P<oldversion>\d\S+)\s+)?\(.*\.deb';
		} elseif($mode == 'newversion') {
			$regex = '(?:^|\s+)(?P<package>\S+)\s+\((?P<newversion>\d\S*)\)\s+';
		} else {
			$regex = ''; // not on debian!
		}

		return $regex;
	}

	public function getRestartServiceCommand($service, $command = 'restart') {
		if($command != 'start' && $command != 'stop' && $command != 'status') {
			$command = 'restart';
		}

		switch($service) {
			case 'apache':
				$service = 'apache2';
				break;
			case 'pureftpd':
				$service = 'pure-ftpd-mysql';
				break;
		}

		return 'service ' . escapeshellarg($service) . ' ' . $command . ' 2>&1';
	}

	protected function updateMySQLConfig($mysql_root_pw) {
		ISPConfigLog::info('Writing MySQL config files.', true);
		$this->replaceContents('/etc/mysql/debian.cnf', array('/^password\s*=.*$/m' => 'password = ' . $mysql_root_pw));
		$this->replaceContents('/etc/mysql/mariadb.conf.d/50-server.cnf', array('/^bind-address/m' => '#bind-address', '/^sql-mode\s*=.*?$/m' => 'sql-mode = "NO_ENGINE_SUBSTITUTION"'), true, 'mysqld');
	}

	protected function getMySQLUserQueries($mysql_root_pw) {
		$escaped_pw = preg_replace('/[\'\\\\]/', '\\$1', $mysql_root_pw);
		$queries = array(
			'DELETE FROM mysql.user WHERE User=\'\';',
			'DELETE FROM mysql.user WHERE User=\'root\' AND Host NOT IN (\'localhost\', \'127.0.0.1\', \'::1\');',
			'DROP DATABASE IF EXISTS test;',
			'DELETE FROM mysql.db WHERE Db=\'test\' OR Db=\'test\\_%\';',
			'UPDATE mysql.user SET Password=PASSWORD(\'' . $escaped_pw . '\') WHERE User=\'root\';',
			'UPDATE mysql.user SET plugin = \'mysql_native_password\' WHERE User=\'root\';',
			'FLUSH PRIVILEGES;'
		);

		return $queries;
	}

	protected function getPackagesToInstall($section) {
		if($section === 'first') {
			$packages = array(
				'dbconfig-common',
				'postfix',
				'postfix-mysql',
				'postfix-doc',
				'mariadb-client',
				'mariadb-server',
				'openssl',
				'getmail4',
				'rkhunter',
				'binutils',
				'sudo'
			);
		} elseif($section === 'mail') {
			$packages = array(
				'software-properties-common',
				'update-inetd',
				'dnsutils',
				'resolvconf',
				'clamav',
				'clamav-daemon',
				'clamav-docs',
				'zip',
				'unzip',
				'bzip2',
				'xz-utils',
				'lzip',
				'rar',
				'borgbackup',
				'arj',
				'nomarch',
				'lzop',
				'cabextract',
				'apt-listchanges',
				'libnet-ldap-perl',
				'libauthen-sasl-perl',
				'daemon',
				'libio-string-perl',
				'libio-socket-ssl-perl',
				'libnet-ident-perl',
				'libnet-dns-perl',
				'libdbd-mysql-perl'
			);

			if(ISPConfig::shallInstall('local-dns')) {
				if(ISPConfig::wantsUnbound()) {
					$packages[] = 'unbound';
				} else {
					$packages[] = 'bind9';
				}
			}

			if(ISPConfig::shallInstall('mail')) {
				if(ISPConfig::wantsAmavis()) {
					$packages[] = 'amavisd-new';
					$packages[] = 'spamassassin';
				} else {
					$packages[] = 'rspamd';
					$packages[] = 'redis-server';
				}
				$packages[] = 'postgrey';
			}
		} elseif($section === 'ftp_stats') {
			$packages = array(
				'pure-ftpd-common',
				'pure-ftpd-mysql',
				'webalizer',
				'awstats',
				'goaccess'
			);
		} elseif($section === 'base') {
			$packages = array(
				'php-pear',
				'php-memcache',
				'php-imagick',
				'php-gettext',
				'mcrypt',
				'imagemagick',
				'libruby',
				'memcached',
				'php-apcu'
			);
		}

		return $packages;
	}

	protected function getApacheModulesToDisable() {
		$modules = array(
			'mpm_prefork',
			'php8.0'
		);

		return $modules;
	}

	protected function getApacheModulesToEnable() {
		$modules = array('suexec', 'rewrite', 'ssl', 'actions', 'include', 'dav_fs', 'dav', 'auth_digest', 'cgi', 'headers', 'proxy_fcgi', 'proxy_http',  'alias', 'http2', 'mpm_event');

		return $modules;
	}

	protected function setDefaultPHP() {
		ISPConfigLog::info('Setting default system PHP version.', true);
		$cmd = 'update-alternatives --set php /usr/bin/php' . $this->getSystemPHPVersion();
		$result = $this->exec($cmd);
		if($result === false) {
			throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
		}

		if(ISPConfig::shallInstall('web')) {
			$cmd = 'update-alternatives --set php-cgi /usr/bin/php-cgi' . $this->getSystemPHPVersion() . ' ; update-alternatives --set php-cgi-bin /usr/lib/cgi-bin/php' . $this->getSystemPHPVersion();
			$result = $this->exec($cmd);
			if($result === false) {
				throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
			}
			// When --use-php=system is given, there is no alternative for php-fpm.sock and it throws an error.
			if(ISPConfig::wantsPHP() !== 'system') {
				$cmd = 'update-alternatives --set php-fpm.sock /run/php/php' . $this->getSystemPHPVersion() . '-fpm.sock';
				$result = $this->exec($cmd);
				if($result === false) {
					throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
				}
			}
		}
	}

	protected function installPHPMyAdmin($mysql_root_pw) {
		if(!ISPConfig::shallInstall('web') || !ISPConfig::shallInstall('pma')) {
			return;
		}

		ISPConfigLog::info('Installing phpMyAdmin', true);

		if(!is_dir('/usr/share/phpmyadmin')) {
			mkdir('/usr/share/phpmyadmin', 0755, true);
		}
		if(!is_dir('/etc/phpmyadmin')) {
			mkdir('/etc/phpmyadmin', 0755);
		}
		if(!is_dir('/var/lib/phpmyadmin/tmp')) {
			mkdir('/var/lib/phpmyadmin/tmp', 0770, true);
		}
		touch('/etc/phpmyadmin/htpasswd.setup');

		// Get latest download URL for phpMyAdmin
		$versionfile = file_get_contents("https://www.phpmyadmin.net/home_page/version.txt");
		preg_match("/[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{1,3}/", $versionfile, $matches);
		$latestversion = $matches[0];

		// Download and unpack phpMyAdmin
		$cmd = 'chown -R www-data:www-data ' . escapeshellarg('/var/lib/phpmyadmin') . ' ; cd /tmp ; rm -f phpMyAdmin-' . $latestversion . '-all-languages.tar.gz ; wget https://files.phpmyadmin.net/phpMyAdmin/' . $latestversion . '/phpMyAdmin-' . $latestversion . '-all-languages.tar.gz 2>/dev/null && tar xfz  phpMyAdmin-' . $latestversion . '-all-languages.tar.gz && cp -a phpMyAdmin-' . $latestversion . '-all-languages/* /usr/share/phpmyadmin/ && rm -f phpMyAdmin-' . $latestversion . '-all-languages.tar.gz && rm -rf phpMyAdmin-' . $latestversion . '-all-languages';
		$result = $this->exec($cmd);
		if($result === false) {
			throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
		}

		copy('/usr/share/phpmyadmin/config.sample.inc.php', '/usr/share/phpmyadmin/config.inc.php');

		$replacements = array(
			'/^(?:\s*\/\/)?\s*\$cfg\[\'blowfish_secret\'\]\s*=.*$/m' => '$cfg[\'blowfish_secret\'] = \'' . substr(sha1(uniqid('pre', true)), 0, 32) . '\';',
			'/^(?:\s*\/\/)?\s*\$cfg\[\'TempDir\'\]\s*=.*$/m' => '$cfg[\'TempDir\'] = \'/var/lib/phpmyadmin/tmp\';'
		);
		$this->replaceContents('/usr/share/phpmyadmin/config.inc.php', $replacements, true);

		$contents = '# phpMyAdmin default Apache configuration

Alias /phpmyadmin /usr/share/phpmyadmin

<Directory /usr/share/phpmyadmin>
 Options FollowSymLinks
 DirectoryIndex index.php

 <IfModule mod_php7.c>
 AddType application/x-httpd-php .php

 php_flag magic_quotes_gpc Off
 php_flag track_vars On
 php_flag register_globals Off
 php_value include_path .
 </IfModule>

</Directory>

# Authorize for setup
<Directory /usr/share/phpmyadmin/setup>
 <IfModule mod_authn_file.c>
 AuthType Basic
 AuthName "phpMyAdmin Setup"
 AuthUserFile /etc/phpmyadmin/htpasswd.setup
 </IfModule>
 Require valid-user
</Directory>

# Disallow web access to directories that don\'t need it
<Directory /usr/share/phpmyadmin/libraries>
 Order Deny,Allow
 Deny from All
</Directory>
<Directory /usr/share/phpmyadmin/setup/lib>
 Order Deny,Allow
 Deny from All
</Directory>';
		if(ISPConfig::$WEBSERVER === ISPC_WEBSERVER_APACHE) {
			file_put_contents('/etc/apache2/conf-available/phpmyadmin.conf', $contents);

			$cmd = 'a2enconf phpmyadmin';
			$result = $this->exec($cmd);
			if($result === false) {
				throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
			}

			$this->restartService('apache2');
		}

		$pma_pass = ISPConfigFunctions::generatePassword(15);
		$pma_pass_enc = preg_replace('/[\'\\\\]/', '\\$1', $pma_pass);

		$queries = array(
			'CREATE DATABASE phpmyadmin;',
			'CREATE USER \'pma\'@\'localhost\' IDENTIFIED BY \'' . $pma_pass_enc . '\';',
			'GRANT ALL PRIVILEGES ON phpmyadmin.* TO \'pma\'@\'localhost\' IDENTIFIED BY \'' . $pma_pass_enc . '\' WITH GRANT OPTION;',
			'FLUSH PRIVILEGES;'
		);

		foreach($queries as $query) {
			$cmd = 'mysql --defaults-file=/etc/mysql/debian.cnf -e ' . escapeshellarg($query) . ' 2>&1';
			$result = $this->exec($cmd);
			if($result === false) {
				ISPConfigLog::warn('Query ' . $query . ' failed.', true);
			}
		}

		$cmd = 'mysql --defaults-file=/etc/mysql/debian.cnf -D phpmyadmin < /usr/share/phpmyadmin/sql/create_tables.sql';
		$result = $this->exec($cmd);
		if($result === false) {
			ISPConfigLog::warn('Command ' . $cmd . ' failed.', true);
		}

		$uncomment = array(
			array(
				'first_line' => '/^(?:\s*\/\/)?\s*\$cfg\[\'Servers\'\]\[\$i\]/',
				'last_line' => '/####nomatch###/',
				'search' => '/^(?:\s*\/\/)?\s*\$cfg\[\'Servers\'\]\[\$i\]/'
			)
		);
		$this->uncommentLines('/usr/share/phpmyadmin/config.inc.php', $uncomment, '//');

		$replacements = array(
			'/^(?:\s*\/\/)?\s*(\$cfg\[\'Servers\'\]\[\$i\]\[\'controlhost\'\])\s*=.*$/m' => '$1 = \'localhost\';',
			'/^(?:\s*\/\/)?\s*(\$cfg\[\'Servers\'\]\[\$i\]\[\'controlport\'\])\s*=.*$/m' => '$1 = \'\';',
			'/^(?:\s*\/\/)?\s*(\$cfg\[\'Servers\'\]\[\$i\]\[\'controluser\'\])\s*=.*$/m' => '$1 = \'pma\';',
			'/^(?:\s*\/\/)?\s*(\$cfg\[\'Servers\'\]\[\$i\]\[\'controlpass\'\])\s*=.*$/m' => '$1 = \'' . $pma_pass_enc . '\';',
		);
		$this->replaceContents('/usr/share/phpmyadmin/config.inc.php', $replacements, false);

		// Add script to keep phpMyAdmin up-to-date automatically
		$cmd = 'curl -s https://git.ispconfig.org/ispconfig/tools/-/raw/master/auto_update_phpmyadmin.sh -L -o /etc/cron.daily/auto_update_phpmyadmin && chmod +x /etc/cron.daily/auto_update_phpmyadmin';
		$result = $this->exec($cmd);
		if($result === false) {
			ISPConfigLog::warn('Command ' . $cmd . ' failed.', true);
		}
	}

	protected function fixDbconfigCommon() {
		ISPConfigLog::info('Fixing dbconfig-common if neccessary');
		$replacements = array(
			'/_dbc_nodb="yes" dbc_mysql_exec/' => '_dbc_nodb="yes"; dbc_mysql_exec'
		);
		$this->replaceContents('/usr/share/dbconfig-common/internal/mysql', $replacements, false);
	}

	protected function setPHPTimezone() {
		if(!is_file('/etc/timezone')) {
			return;
		}
		$tz = trim(file_get_contents('/etc/timezone'));
		if(!in_array($tz, timezone_identifiers_list())) {
			return;
		}

		// set in all php inis
		$ini_files = array(
			'/etc/php/5.6/cgi/php.ini',
			'/etc/php/5.6/cli/php.ini',
			'/etc/php/5.6/fpm/php.ini',
			'/etc/php/5.6/apache2/php.ini',
			'/etc/php/7.0/cgi/php.ini',
			'/etc/php/7.0/cli/php.ini',
			'/etc/php/7.0/fpm/php.ini',
			'/etc/php/7.0/apache2/php.ini',
			'/etc/php/7.1/cgi/php.ini',
			'/etc/php/7.1/cli/php.ini',
			'/etc/php/7.1/fpm/php.ini',
			'/etc/php/7.1/apache2/php.ini',
			'/etc/php/7.2/cgi/php.ini',
			'/etc/php/7.2/cli/php.ini',
			'/etc/php/7.2/fpm/php.ini',
			'/etc/php/7.2/apache2/php.ini',
			'/etc/php/7.3/cgi/php.ini',
			'/etc/php/7.3/cli/php.ini',
			'/etc/php/7.3/fpm/php.ini',
			'/etc/php/7.3/apache2/php.ini',
			'/etc/php/7.4/cgi/php.ini',
			'/etc/php/7.4/cli/php.ini',
			'/etc/php/7.4/fpm/php.ini',
			'/etc/php/7.4/apache2/php.ini',
			'/etc/php/8.0/cgi/php.ini',
			'/etc/php/8.0/cli/php.ini',
			'/etc/php/8.0/fpm/php.ini',
			'/etc/php/8.0/apache2/php.ini'
		);

		$replace = array(
			'/^;?\s*date\.timezone\s+=.*$/' => 'date.timezone = ' . $tz
		);

		foreach($ini_files as $ini) {
			if(is_file($ini)) {
				$this->replaceContents($ini, $replace);
			}
		}
	}

	protected function configureApt() {
		// enable contrib and non-free
		ISPConfigLog::info('Enabling contrib and non-free repositories.', true);
		$replacements = array(
			'/^(deb.*\s+?main)\b(.*?)$/m' => [
				'replace' => '$1 $2 contrib',
				'ifnot' => ' contrib'
			],
			'/^(deb.*\s+?main\b)(.*?)$/m' => [
				'replace' => '$1 $2 non-free',
				'ifnot' => ' non-free'
			]
		);

		$this->replaceContents('/etc/apt/sources.list', $replacements);
	}

	protected function addSuryRepo() {
		ISPConfigLog::info('Activating sury php repository.', true);
		$cmd = 'wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg >/dev/null 2>&1 ; echo "deb https://packages.sury.org/php/ $(lsb_release -c -s) main" > /etc/apt/sources.list.d/php.list';
		$result = $this->exec($cmd);
		if($result === false) {
			throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
		}
	}

	protected function addGoAccessRepo() {
		ISPConfigLog::info('Activating GoAccess repository.', true);
		$cmd = 'echo "deb https://deb.goaccess.io/ $(lsb_release -cs) main" | tee /etc/apt/sources.list.d/goaccess.list >/dev/null 2>&1 ; wget -O - https://deb.goaccess.io/gnugpg.key 2>&1 | apt-key --keyring /etc/apt/trusted.gpg.d/goaccess.gpg add - 2>&1';
		$result = $this->exec($cmd);
		if($result === false) {
			throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
		}
	}

	protected function installMonit() {
		ISPConfigLog::info('Installing Monit', true);

		$packages = array(
			'monit'
		);
		$this->installPackages($packages);

		// Stop Monit to prevent it from interfering with other services being down on purpose
		$this->stopService('monit');

		ISPConfigLog::info('Configuring Monit.', true);
		// Set up main config
		$replacements = array(
			'set daemon 120' => 'set daemon 60'
		);
		$this->replaceContents('/etc/monit/monitrc', $replacements, true);

		// Set up config files for each service that shall be monitored
		$servicesInstalled = array(
			'mariadb',
			'memcached',
			'pure-ftpd-mysql',
			'fail2ban',
			'sshd',
			'crond',
			'filesystem',
			'resources'
		);

		// Get all PHP versions to be installed
		if(ISPConfig::wantsPHP() === 'system') {
			$php_versions = array($this->getSystemPHPVersion());
		} else {
			//ISPConfig::run() validations prevent sending here null values
			$php_versions = ISPConfig::wantsPHP();
			if (!in_array($this->getSystemPHPVersion(), $php_versions)) {
				$php_versions[] = $this->getSystemPHPVersion();
			}
		}

		// Add all services that are installed and we want to monitor to services array
		foreach($php_versions as $curver) {
			$phpfpm = 'php' . $curver . '-fpm';
			array_push($servicesInstalled, $phpfpm);
		}

		if(ISPConfig::shallInstall('web')) {
			if(ISPConfig::$WEBSERVER === ISPC_WEBSERVER_APACHE) {
				array_push($servicesInstalled, "apache2");
			} elseif(ISPConfig::$WEBSERVER === ISPC_WEBSERVER_NGINX) {
				array_push($servicesInstalled, "nginx");
			}
		}

		if(ISPConfig::shallInstall('local-dns') && !ISPConfig::wantsUnbound()) {
			array_push($servicesInstalled, "named");
		}

		if (ISPConfig::shallInstall('mail')) {
			array_push($servicesInstalled, "postfix", "dovecot", "rspamd");
		}

		if(!ISPConfig::wantsAmavis()) {
			array_push($servicesInstalled, "redis-server");
		}

		$services = array_unique($servicesInstalled);

		// Set config directories
		$confAvailableDir = '/etc/monit/conf-available/';
		$confEnabledDir = '/etc/monit/conf-enabled/';
		// Put config files in conf available directory
		foreach ($services as $service) {
			if ($service == 'apache2') {
				$conf='check process apache with pidfile /var/run/apache2/apache2.pid
	group apache
	start program = "/usr/bin/systemctl start apache2" with timeout 60 seconds
	stop program  = "/usr/bin/systemctl stop apache2"
	if failed port 80 protocol http then restart
	if failed port 443 then restart
	if 5 restarts within 5 cycles then timeout
	depend apache_bin
	depend apache_rc
		
check file apache_bin with path /usr/sbin/apache2
	group apache
	include /etc/monit/templates/rootbin
	
check file apache_rc with path /etc/init.d/apache2
	group apache
	include /etc/monit/templates/rootbin';
			}

			if ($service == 'nginx') {
				$conf='check process nginx with pidfile /var/run/nginx.pid
	group nginx
	start program = "/usr/bin/systemctl start nginx" with timeout 60 seconds
	stop program  = "/usr/bin/systemctl stop nginx"
	if failed port 80 protocol http then restart
	if failed port 443 then restart
	if 5 restarts within 5 cycles then timeout
	depend nginx_bin
	depend nginx_rc
 
check file nginx_bin with path /usr/sbin/nginx
	group nginx
	include /etc/monit/templates/rootbin

check file nginx_rc with path /etc/init.d/nginx
	group nginx
	include /etc/monit/templates/rootbin';
			}
				
			if ($service == 'mariadb') {
				if (file_exists('/etc/init.d/mariadb') && file_exists('/usr/sbin/mariadbd')) {
					$conf='check process mariadb with pidfile /var/run/mysqld/mysqld.pid
	group mysql
	start program = "/usr/bin/systemctl start mariadb" with timeout 60 seconds
	stop program = "/usr/bin/systemctl stop mariadb"
	if failed host 127.0.0.1 port 3306 protocol mysql then restart
	if failed unixsocket /var/run/mysqld/mysqld.sock protocol mysql for 3 times within 4 cycles then restart
	if 5 restarts within 5 cycles then timeout
	
	depend mysql_bin
	depend mysql_rc
	
check file mysql_bin with path /usr/sbin/mariadbd
	group mysql
   	include /etc/monit/templates/rootbin
		
check file mysql_rc with path /etc/init.d/mariadb
	group mysql
	include /etc/monit/templates/rootbin';
				} elseif (file_exists('/etc/init.d/mysql') && file_exists('/usr/sbin/mysqld')) {
					$conf='check process mariadb with pidfile /var/run/mysqld/mysqld.pid
	group mysql
	start program = "/usr/bin/systemctl start mariadb" with timeout 60 seconds
	stop program = "/usr/bin/systemctl stop mariadb"
	if failed host 127.0.0.1 port 3306 protocol mysql then restart
	if failed unixsocket /var/run/mysqld/mysqld.sock protocol mysql for 3 times within 2 cycles then restart
	if 5 restarts within 5 cycles then timeout
	
	depend mysql_bin
	depend mysql_rc
	
check file mysql_bin with path /usr/sbin/mysqld
	group mysql
   	include /etc/monit/templates/rootbin
		
check file mysql_rc with path /etc/init.d/mysql
	group mysql
	include /etc/monit/templates/rootbin';
				} else {
					$conf='check process mariadb with pidfile /var/run/mysqld/mysqld.pid
	group mysql
	start program = "/usr/bin/systemctl start mariadb" with timeout 60 seconds
	stop program = "/usr/bin/systemctl stop mariadb"
	if failed host 127.0.0.1 port 3306 protocol mysql then restart
	if failed unixsocket /var/run/mysqld/mysqld.sock protocol mysql for 3 times within 2 cycles then restart
	if 5 restarts within 5 cycles then timeout';
				}
			}

			foreach($php_versions as $curver) {
				$phpfpm = 'php' . $curver . '-fpm';
				if ($service == $phpfpm) {
					$conf='check process ' . $phpfpm . ' with pidfile /var/run/php/' . $phpfpm . '.pid
	group php-fpm
	start program = "/usr/bin/systemctl start ' . $phpfpm .'" with timeout 60 seconds
	stop program  = "/usr/bin/systemctl stop ' . $phpfpm . '"
	if failed unixsocket /var/run/php/' . $phpfpm . '.sock then restart
	if 5 restarts within 5 cycles then timeout';
				}
			}

			if ($service == 'memcached') {
				$conf='check process memcached with pidfile /var/run/memcached/memcached.pid
	group memcached
	start program = "/usr/bin/systemctl start memcached"
	stop program = "/usr/bin/systemctl stop memcached"
	if failed host 127.0.0.1 port 11211 protocol memcache then restart
	if 5 restarts within 5 cycles then timeout
	
	depend memcache_bin
	depend memcache_rc
	
check file memcache_bin with path /usr/bin/memcached
	group memcached
	include /etc/monit/templates/rootbin
   
check file memcache_rc with path /etc/init.d/memcached
	group memcached
	include /etc/monit/templates/rootbin';
			}
				
			if ($service == 'pure-ftpd-mysql') {
				$conf='check process pure-ftpd-mysql with pidfile /var/run/pure-ftpd/pure-ftpd.pid
	start program = "/usr/bin/systemctl start pure-ftpd-mysql" with timeout 60 seconds
	stop program  = "/usr/bin/systemctl stop pure-ftpd-mysql"
	if failed port 21 protocol ftp then restart
	if 5 restarts within 5 cycles then timeout';
			}

			if ($service == 'fail2ban') {
				$conf='check process fail2ban with pidfile /var/run/fail2ban/fail2ban.pid
	start program = "/usr/bin/systemctl start fail2ban" with timeout 60 seconds
    stop  program = "/usr/bin/systemctl stop fail2ban"
    if failed unixsocket /var/run/fail2ban/fail2ban.sock then restart
    if 5 restarts within 5 cycles then timeout

check file fail2ban_log with path /var/log/fail2ban.log
	if match "ERROR|WARNING" then alert';
			}

				
			if ($service == 'sshd') {
				$conf='check process sshd with pidfile /var/run/sshd.pid
	group sshd
	start program = "/etc/init.d/ssh start"
	stop  program = "/etc/init.d/ssh stop"
	if failed host localhost port 22 with proto ssh then restart
	if 5 restarts with 5 cycles then timeout
	depend on sshd_bin
	depend on sftp_bin
	depend on sshd_rc
	depend on sshd_rsa_key
	depend on sshd_dsa_key
			 
check file sshd_bin with path /usr/sbin/sshd
	group sshd
	include /etc/monit/templates/rootbin

check file sftp_bin with path /usr/lib/openssh/sftp-server
	group sshd
	include /etc/monit/templates/rootbin
			 
check file sshd_rsa_key with path /etc/ssh/ssh_host_rsa_key
	group sshd
	include /etc/monit/templates/rootstrict

check file sshd_dsa_key with path /etc/ssh/ssh_host_dsa_key
	group sshd
	include /etc/monit/templates/rootstrict
			 
check file sshd_rc with path /etc/ssh/sshd_config
	group sshd
	include /etc/monit/templates/rootrc';
			}

			if ($service == 'crond') {
				$conf='check process crond with pidfile /var/run/crond.pid
	group system
	group crond
	start program = "/usr/bin/systemctl start cron" with timeout 60 seconds
	stop  program = "/usr/bin/systemctl stop cron"
	if 5 restarts with 5 cycles then timeout
	depend cron_bin
	depend cron_rc
	depend cron_spool
			 
check file cron_bin with path /usr/sbin/cron
	group crond
	include /etc/monit/templates/rootbin
		 
check file cron_rc with path "/etc/init.d/cron"
	group crond
	include /etc/monit/templates/rootbin
			 
check directory cron_spool with path /var/spool/cron/crontabs
	group crond
	if failed permission 1730 then unmonitor
	if failed uid root        then unmonitor
	if failed gid crontab     then unmonitor';
			}

			if ($service == 'named') {
				$conf='check process named with pidfile /var/run/named/named.pid
	start program = "/usr/bin/systemctl start named" with timeout 60 seconds
	stop program  = "/usr/bin/systemctl stop named"
	if failed port 53 use type udp protocol dns then restart
	if 5 restarts within 5 cycles then timeout';
			}
				
			if ($service == 'postfix') {
				$conf='check process postfix with pidfile /var/spool/postfix/pid/master.pid
	group mail
	group postfix
	start program = "/usr/bin/systemctl start postfix" with timeout 60 seconds
	stop  program = "/usr/bin/systemctl stop postfix"
	if failed host localhost port 25 with protocol smtp for 2 times within 2 cycles then restart
	if 5 restarts with 5 cycles then timeout
	depend master_bin
	depend postfix_rc
	depend postdrop_bin
	depend postqueue_bin
	depend master_cf
	depend main_cf
			 
check file master_bin with path /usr/lib/postfix/sbin/master
	group postfix
	include /etc/monit/templates/rootbin
			 
check file postdrop_bin with path /usr/sbin/postdrop
	group postfix
	if failed checksum        then unmonitor
	if failed permission 2555 then unmonitor
	if failed uid root        then unmonitor
	if failed gid postdrop    then unmonitor
			 
check file postqueue_bin with path /usr/sbin/postqueue
	group postfix
	if failed checksum        then unmonitor
	if failed permission 2555 then unmonitor
	if failed uid root        then unmonitor
	if failed gid postdrop    then unmonitor
			 
check file master_cf with path /etc/postfix/master.cf
	group postfix
	include /etc/monit/templates/rootrc
			 
check file main_cf with path /etc/postfix/main.cf
	group postfix
	include /etc/monit/templates/rootrc
			 
check file postfix_rc with path /etc/init.d/postfix
	group postfix
	include /etc/monit/templates/rootbin';
			}
				
			if ($service == 'dovecot') {
				$conf='check process dovecot with pidfile /var/run/dovecot/master.pid
	group mail
	start program = "/usr/bin/systemctl start dovecot" with timeout 60 seconds
	stop program = "/usr/bin/systemctl stop dovecot"
	#if failed host mail.yourdomain.tld port 993 type tcpssl sslauto protocol imap then restart
	if failed port 143 protocol imap then restart
	if 5 restarts within 5 cycles then timeout';
			}
				
			if ($service == 'rspamd') {
				$conf='check process rspamd
	matching \'rspamd: main process\'
	start program = "/usr/bin/systemctl start rspamd" with timeout 60 seconds
	stop program = "/usr/bin/systemctl start rspamd"
					
	if cpu is greater than 40% then alert
	if cpu > 60% for 4 cycles then alert
	if memory > 80% for 4 cycles then alert
	if totalmem > 1024 MB for 4 cycles then alert';
			}

			if ($service == 'redis-server') {
				$conf='check process redis-server with pidfile "/var/run/redis/redis-server.pid"
				start program = "/usr/bin/systemctl start redis-server"
				stop program = "/usr/bin/systemctl stop redis-server"
				if failed host 127.0.0.1 port 6379 protocol redis then restart
				if 5 restarts within 5 cycles then timeout';
			}
				
			if ($service == 'filesystem') {
				$conf='check filesystem rootfs with path /
	if space usage > 90% then alert
	if inode usage > 80% then alert';
			}
				
			if ($service == 'resources') {
				$conf='check system $HOST
	if loadavg (5min) > 3 then alert
    if loadavg (15min) > 1 then alert
    if memory usage > 80% for 4 cycles then alert
    if swap usage > 20% for 6 cycles then alert
    # Test the user part of CPU usage 
    if cpu usage (user) > 80% for 6 cycles then alert
    # Test the system part of CPU usage 
    if cpu usage (system) > 20% for 6 cycles then alert
    # Test the i/o wait part of CPU usage 
    if cpu usage (wait) > 80% for 4 cycles then alert
    # Test CPU usage including user, system and wait. Note that 
    # multi-core systems can generate 100% per core
    # so total CPU usage can be more than 100%
    if cpu usage > 200% for 4 cycles then alert';
			}

			file_put_contents($confAvailableDir . $service, $conf);
		}

		// Set up alert config
		$monitEmail = ISPConfig::getMonitAlertEmail();
		$service = 'alerts';
		$conf = '#set mailserver localhost
#set mailserver smtp.example.com port 587
	#username "user@example.com" password "welcome"
				
#set alert admin@example.com

#set mail-format {
#	from:    Monit <monit@$HOST>
#	subject: Monit alert on $HOST -- $EVENT $SERVICE
#	message: $EVENT Service $SERVICE
#
#Date:        $DATE
#Action:      $ACTION
#Host:        $HOST
#Description: $DESCRIPTION
#  
#Your faithful employee,
#Monit
#}';
		if (!empty($monitEmail)) {
			if (filter_var($monitEmail, FILTER_VALIDATE_EMAIL)) {
				$conf = 'set mailserver localhost
#set mailserver smtp.example.com port 587
	#username "user@example.com" password "welcome"
	#using tls

set alert ' . $monitEmail . '

#set mail-format {
#	from:    Monit <monit@$HOST>
#	subject: Monit alert on $HOST -- $EVENT $SERVICE
#	message: $EVENT Service $SERVICE
#
#Date:        $DATE
#Action:      $ACTION
#Host:        $HOST
#Description: $DESCRIPTION
#  
#Your faithful employee,
#Monit
#}';
			} else {
				ISPConfigLog::warn('E-mail address for Monit alerts is invalid. Set up alerts manually in /etc/monit/conf-available/alerts', true);
			};
		}
		file_put_contents($confAvailableDir . $service, $conf);
		$services[] = $service;
		
		// Configure main config with UI and admin + pass
		$service = "webui";
		$monitpw = '';
		$monitpw = ISPConfigFunctions::generatePassword(12);
		$conf='set httpd port 2812 and
	#SSL ENABLE
	#PEMFILE /usr/local/ispconfig/interface/ssl/ispserver.pem
	allow admin:' . $monitpw;
			
		file_put_contents($confAvailableDir . $service, $conf);
		$services[] = $service;

		foreach ($services as $confFile) {
			$cmd = 'ln -s ' . $confAvailableDir . $confFile . ' ' . $confEnabledDir;
			$result = $this->exec($cmd);
			if($result === false) {
				throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
			}
		}

		return $monitpw;
	}

	protected function configureSecureShell() {
		ISPConfigLog::info('Configuring SSHd', true);

		$secureShellCustomConfig = '# Created by the ISPConfig autoinstaller on ' . date("Y-m-d");

		// Set Port
		$sshOption = ISPConfig::getSecureShellPort();
		if ($sshOption != '') {
			if (!($sshOption > 0 && $sshOption < 65536)) {
				ISPConfigLog::warn($sshOption . 'is not a valid option for --ssh-port. The port number must be between 1 and 65536. Ignoring option.');
			} else
			$invalidOptions = array(
				'68', // dhclient
				'123', // ntpd
				'3306', // MySQL/MariaDB
				'4190', // dovecot
				'6379', // redis
				'10023', // postgrey
				'11333', // rspamd
				'11333', // rspamd
				'11334', // rspamd
				'20', // ftp
				'21', // ftp
				'25', // postfix
				'80', // httpd
				'443', // httpd
				'110', // dovecot
				'143', // dovecot
				'465', // postfix
				'587', // postfix
				'993', // dovecot
				'995', // dovecot
				'53', // named
				'8080', // ISPConfig UI
				'8081' // ISPConfig apps vhost
			);
			if (!in_array($sshOption, $invalidOptions)) {
				$secureShellCustomConfig .= '
Port ' . $sshOption;
				ISPConfigLog::info('Configuring custom port for the SSH daemon. After install, you can login through SSH on port ' . $sshOption);
			} else {
				ISPConfigLog::warn($sshOption . ' is not a valid option for --ssh-port. These ports are not allowed as they are used for other services: ' . implode(", ", $invalidOptions) . '. Ignoring option.');
			}
		}

		// Set PermitRootLogin
		$sshOption = ISPConfig::getSecureShellPermitRoot();
		if ($sshOption != '') {
			$validOptions = array(
				'yes',
				'without-password',
				'no'
			);
			if (in_array($sshOption, $validOptions)) {
				$secureShellCustomConfig .= '
PermitRootLogin ' . $sshOption;
			} else {
				ISPConfigLog::warn($sshOption . ' is not a valid option for --ssh-permit-root. Allowed values: ' . implode(", ", $validOptions) . '. Ignoring option.');
			}
		}

		// Set PasswordAuthentication
		$sshOption = ISPConfig::getSecureShellPasswordAuthentication();
		if ($sshOption != '') {
			$validOptions = array(
				'yes',
				'no'
			);
			if (in_array($sshOption, $validOptions)) {
				$secureShellCustomConfig .= '
PasswordAuthentication ' . $sshOption;
			} else {
				ISPConfigLog::warn($sshOption . ' is not a valid option for --ssh-password-authentication. Allowed values: ' . implode(", ", $validOptions) . '. Ignoring option.');
			}
		}

		// Harden SSHd config
		if (ISPConfig::wantsSecureShellHardened()) {
			$secureShellCustomConfig .= '
HostKey /etc/ssh/ssh_host_ed25519_key
KexAlgorithms curve25519-sha256@libssh.org
Ciphers chacha20-poly1305@openssh.com,aes256-gcm@openssh.com,aes128-gcm@openssh.com,aes256-ctr,aes192-ctr,aes128-ctr
MACs hmac-sha2-512-etm@openssh.com,hmac-sha2-256-etm@openssh.com,umac-128-etm@openssh.com
PermitEmptyPasswords no
X11Forwarding no';
		}

		// Put the config file in place
		file_put_contents('/etc/ssh/sshd_config.d/custom.conf', $secureShellCustomConfig);

		// It's safe to restart the SSH daemon as the existing session will be kept alive.
		$this->restartService('sshd');
	}

	protected function installUnattendedUpgrades() {
		ISPConfigLog::info('Installing UnattendedUpgrades', true);

		$packages = array(
			'unattended-upgrades',
			'apt-listchanges'
		);
		$this->installPackages($packages);

		// Enable UnattendUpgrades to run every day
		$unattendedupgrades = 'APT::Periodic::Update-Package-Lists "1";' . "\n" . 'APT::Periodic::Unattended-Upgrade "1";';
		file_put_contents('/etc/apt/apt.conf.d/20auto-upgrades', $unattendedupgrades);

		// Enable extra options if set in the arguments
		$unattendedupgrades_options = ISPConfig::getUnattendedUpgradesOptions();
		if (!empty($unattendedupgrades_options)) {
			if (in_array("autoclean", $unattendedupgrades_options)) {
				$unattendedupgrades = "\n" . 'APT::Periodic::AutocleanInterval "7";'  . "\n" . 'Unattended-Upgrade::Remove-Unused-Kernel-Packages "true";'  . "\n" . 'Unattended-Upgrade::Remove-Unused-Dependencies "true";';
				file_put_contents('/etc/apt/apt.conf.d/20auto-upgrades', $unattendedupgrades, FILE_APPEND | LOCK_EX);
			}
			if (in_array("reboot", $unattendedupgrades_options)) {
				$unattendedupgrades = "\n" . 'Unattended-Upgrade::Automatic-Reboot "true";' . "\n" . 'Unattended-Upgrade::Automatic-Reboot-Time "03:30";';
				file_put_contents('/etc/apt/apt.conf.d/20auto-upgrades', $unattendedupgrades, FILE_APPEND | LOCK_EX);
			}
		}

		// Enable sury repo for unattended upgrades if sury repo is used
		if(ISPConfig::wantsPHP() !== 'system') {
			$replacements = array(
				'Unattended-Upgrade::Origins-Pattern {' => 'Unattended-Upgrade::Origins-Pattern {
	"site=packages.sury.org";'
			);
			$result = $this->replaceContents('/etc/apt/apt.conf.d/50unattended-upgrades', $replacements);
		}
	}

	protected function shallCompileJailkit() {
		return true;
	}

	protected function getFail2BanJail() {
		$jk_jail = '[pure-ftpd]
enabled = true
port = ftp
filter = pure-ftpd
logpath = /var/log/syslog
maxretry = 3

[dovecot]
enabled = true
filter = dovecot
logpath = /var/log/mail.log
maxretry = 5

[postfix-sasl]
enabled = true
port = smtp
filter = postfix-sasl
logpath = /var/log/mail.log
maxretry = 3';
		return $jk_jail;
	}

	protected function installMailman($host_name) {
		if(!ISPConfig::shallInstall('mail') || !ISPConfig::shallInstall('mailman')) {
			return;
		}

		ISPConfigLog::info('Installing Mailman', true);

		$cmd = 'echo "mailman mailman/site_languages multiselect de (German), en (English)" | debconf-set-selections 2>&1' . "\n";
		if(isset($_GET['lang']) && $_GET['lang'] === 'de') {
			$cmd .= 'echo "mailman mailman/default_server_language select de (German)" | debconf-set-selections 2>&1';
		} else {
			$cmd .= 'echo "mailman mailman/default_server_language select en (English)" | debconf-set-selections 2>&1';
		}
		$result = $this->exec($cmd);
		if($result === false) {
			throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
		}

		$package = 'mailman';
		$this->installPackages($package);

		$listpw = '';
		if(!is_dir('/var/lib/mailman/lists/mailman')) {
			$listpw = ISPConfigFunctions::generatePassword(12);
			$cmd = 'newlist -q -e ' . escapeshellarg($host_name) . ' mailman ' . escapeshellarg('root@' . $host_name) . ' ' . escapeshellarg($listpw);
			$result = $this->exec($cmd);
			if($result === false) {
				throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
			}
		}

		$add_content = '## mailman mailing list
mailman:              "|/var/lib/mailman/mail/mailman post mailman"
mailman-admin:        "|/var/lib/mailman/mail/mailman admin mailman"
mailman-bounces:      "|/var/lib/mailman/mail/mailman bounces mailman"
mailman-confirm:      "|/var/lib/mailman/mail/mailman confirm mailman"
mailman-join:         "|/var/lib/mailman/mail/mailman join mailman"
mailman-leave:        "|/var/lib/mailman/mail/mailman leave mailman"
mailman-owner:        "|/var/lib/mailman/mail/mailman owner mailman"
mailman-request:      "|/var/lib/mailman/mail/mailman request mailman"
mailman-subscribe:    "|/var/lib/mailman/mail/mailman subscribe mailman"
mailman-unsubscribe:  "|/var/lib/mailman/mail/mailman unsubscribe mailman"';
		$fp = fopen('/etc/aliases', 'r+');
		if(!$fp) {
			throw new ISPConfigOSException('Opening /etc/aliases failed.');
		}
		$found = false;
		while(!feof($fp)) {
			$line = trim(fgets($fp));
			if($line === '## mailman mailing list') {
				$found = true;
				break;
			}
		}
		if($found === false) {
			fseek($fp, SEEK_END);
			fwrite($fp, "\n\n" . $add_content);
		}
		fclose($fp);

		$cmd = 'newaliases';
		$result = $this->exec($cmd);
		if($result === false) {
			throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
		}

		if(ISPConfig::$WEBSERVER === ISPC_WEBSERVER_APACHE) {
			if(!is_link('/etc/apache2/conf-enabled/mailman.conf') && !is_file('/etc/apache2/conf-enabled/mailman.conf')) {
				symlink('/etc/mailman/apache.conf', '/etc/apache2/conf-enabled/mailman.conf');
			}
		}

		$this->restartService('postfix');
		$this->restartService('mailman');
		if(ISPConfig::$WEBSERVER === ISPC_WEBSERVER_APACHE) {
			$this->restartService('apache2');
		}

		return $listpw;
	}

	protected function getRoundcubePackages() {
		return array(
			'roundcube',
			'roundcube-core',
			'roundcube-mysql',
			'roundcube-plugins'
		);
	}

	protected function installRoundcube($mysql_root_pw) {
		ISPConfigLog::info('Installing roundcube.', true);

		$cmd = 'APP_PASS="' . ISPConfigFunctions::generatePassword(15) . '"' . "\n";
		$cmd .= 'ROOT_PASS="' . $mysql_root_pw . '"' . "\n";
		$cmd .= 'APP_DB_PASS="' . ISPConfigFunctions::generatePassword(15) . '"' . "\n";
		$cmd .= 'echo "roundcube-core roundcube/dbconfig-install boolean true" | debconf-set-selections 2>&1' . "\n";
		$cmd .= 'echo "roundcube-core roundcube/database-type select mysql" | debconf-set-selections 2>&1' . "\n";
		$cmd .= 'echo "roundcube-core roundcube/mysql/admin-user string root" | debconf-set-selections 2>&1' . "\n";
		$cmd .= 'echo "roundcube-core roundcube/mysql/admin-pass password $ROOT_PASS" | debconf-set-selections 2>&1' . "\n";
		$cmd .= 'echo "roundcube-core roundcube/mysql/app-pass password $APP_DB_PASS" | debconf-set-selections 2>&1' . "\n";
		$cmd .= 'echo "roundcube-core roundcube/reconfigure-webserver multiselect apache2" | debconf-set-selections 2>&1' . "\n";
		$result = $this->exec($cmd);
		if($result === false) {
			throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
		}

		$packages = $this->getRoundcubePackages();
		$this->installPackages($packages);

		$replacements = array(
			'/^\s*\$config\s*\[["\']default_host["\']\]\s*=.*$/m' => '$config[\'default_host\'] = \'localhost\';',
			'/^\s*\$config\s*\[["\']smtp_server["\']\]\s*=.*$/m' => '$config[\'smtp_server\'] = \'%h\';',
			'/^\s*\$config\s*\[["\']smtp_port["\']\]\s*=.*$/m' => '$config[\'smtp_port\'] = 25;',
			'/^\s*\$config\s*\[["\']smtp_user["\']\]\s*=.*$/m' => '$config[\'smtp_user\'] = \'%u\';',
			'/^\s*\$config\s*\[["\']smtp_pass["\']\]\s*=.*$/m' => '$config[\'smtp_pass\'] = \'%p\';'
		);
		$result = $this->replaceContents('/etc/roundcube/config.inc.php', $replacements);

		if(ISPConfig::$WEBSERVER === ISPC_WEBSERVER_APACHE) {
			$replacements = array(
				'/^\s*#*\s*Alias\s+\/roundcube\s+\/var\/lib\/roundcube\/public\_html\s*$/m' => 'Alias /webmail /var/lib/roundcube/public_html',
				'/^\s*#*\s*Alias\s+\/roundcube\s+\/var\/lib\/roundcube\s*$/m' => 'Alias /webmail /var/lib/roundcube'
			);
			$result = $this->replaceContents('/etc/apache2/conf-enabled/roundcube.conf', $replacements);
		} elseif(ISPConfig::$WEBSERVER === ISPC_WEBSERVER_NGINX) {
			symlink('/usr/share/roundcube', '/usr/share/squirrelmail');
		}
	}

	protected function installPackages($packages) {
		if(is_string($packages)) {
			$packages = array($packages);
		}
		ISPConfigLog::info('Installing packages ' . implode(', ', $packages), true);
		$result = parent::installPackages($packages);
		if($result !== false) {
			ISPConfigLog::info('Installed packages ' . implode(', ', $packages), true);
		} else {
			throw new ISPConfigOSException('Installing packages failed.');
		}

		return $result;
	}

	public function runPerfectSetup() {
		$log_filename = 'setup-' . strftime('%Y%m%d%H%M%S', time()) . '.log';
		ISPConfigLog::setLogFile($log_filename);

		if(is_file('/usr/local/ispconfig/server/lib/config.inc.php')) {
			ISPConfigLog::error('The server already has ISPConfig installed. Aborting.', true);
			return false;
		}

		if(!$this->isStableSupported() && ISPConfig::getISPConfigChannel() !== 'dev') {
			ISPConfigLog::error('This OS is not yet supported by ISPConfig stable version. Please use --channel=dev to install the current development version.', true);
			return false;
		}

		if(ISPConfig::wantsUnbound() && ISPConfig::shallInstall('dns')) {
			ISPConfigLog::error('You can only use --use-unbound together with --no-dns as ISPConfig requires Bind when dns is enabled.', true);
			return false;
		}

		ISPConfigLog::info('Checking hostname.', true);

		$host_name = false;
		$cmd = 'hostname -f 2>&1';
		$check = $this->exec($cmd);
		if($check === false) {
			throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
		} else {
			$host_name = $check;
		}/* elseif(trim($check) !== $host_name) {
			ISPConfigLog::warn('Hostname mismatch: ' . $check . ' != ' . $host_name);
		}*/

		$cmd = 'hostname 2>&1';
		$check = $this->exec($cmd);
		if($check === false) {
			throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
		}/* elseif(trim($check) !== $short_hostname) {
			ISPConfigLog::warn('Short hostname mismatch: ' . $check . ' != ' . $short_hostname);
		}*/

		if($host_name == '') {
			ISPConfigLog::error('Could not read the host name of your server. Please check it is correctly set.', true);
			throw new ISPConfigOSException('Invalid host name or host name not found.');
		} elseif(substr_count($host_name, '.') < 2) {
			ISPConfigLog::error('The host name ' . $host_name . ' of your server is no fully qualified domain name (xyz.domain.com). Please check it is correctly set.', true);
			throw new ISPConfigOSException('Host name is no FQDN.');
		}

		$this->configureApt();
		$this->updatePackageList();

		ISPConfigLog::info('Updating packages', true);
		$cmd = $this->getUpdateCommand('update');
		$result = $this->exec($cmd);
		if($result !== false) {
			ISPConfigLog::info('Updated packages', true);
		} else {
			throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
		}

		try {
			$this->beforePackageInstall();
		} catch (Exception $ex) {
			throw $ex;
		}

		$packages = array(
			'ssh',
			'openssh-server',
			'nano',
			'vim-nox',
			'lsb-release',
			'apt-transport-https',
			'ca-certificates',
			'wget',
			'git',
			'gnupg',
			'software-properties-common',
			'curl',
			'cron'
		);
		if(ISPConfig::shallInstall('ntp')) {
			$packages[] = 'ntp';
		}
		$this->installPackages($packages);

		if(ISPConfig::shallInstall('mail') && !ISPConfig::wantsAmavis()) {
			ISPConfigLog::info('Activating rspamd repository.', true);
			$cmd = 'mkdir -p /etc/apt/keyrings ; wget -q -O- https://rspamd.com/apt-stable/gpg.key | gpg --dearmor | tee /etc/apt/keyrings/rspamd.gpg > /dev/null ; echo "deb [arch=amd64 signed-by=/etc/apt/keyrings/rspamd.gpg] http://rspamd.com/apt-stable/ $(lsb_release -c -s) main" | tee /etc/apt/sources.list.d/rspamd.list ; echo "deb-src [arch=amd64 signed-by=/etc/apt/keyrings/rspamd.gpg] http://rspamd.com/apt-stable/ $(lsb_release -c -s) main"  | tee -a /etc/apt/sources.list.d/rspamd.list';
			$result = $this->exec($cmd);
			if($result === false) {
				throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
			}
		}

		if(ISPConfig::wantsPHP() !== 'system') {
			$this->addSuryRepo();
		}

		if(ISPConfig::shallInstall('web')) {
			$this->addGoAccessRepo();
		}

		$this->updatePackageList();

		ISPConfigLog::info('Updating packages (after enabling 3rd party repos).', true);
		$cmd = $this->getUpdateCommand('update');
		$result = $this->exec($cmd);
		if($result !== false) {
			ISPConfigLog::info('Updated packages', true);
		} else {
			throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
		}

		/*$hostname_changed = false;

		ISPConfigLog::info('Setting hostname to ' . $host_name, true);
		$dotpos = strpos($host_name, '.');
		if($dotpos !== false) {
			$short_hostname = substr($host_name, 0, $dotpos);
		} else {
			$short_hostname = '';
		}
		$hosts_entry = $this->ip_address . "\t" . $host_name . ($short_hostname ? ' ' . $short_hostname : '');
		if(is_file('/etc/cloud/templates/hosts.tmpl')) {
			$use_hosts_file = '/etc/cloud/templates/hosts.tmpl';
		} else {
			$use_hosts_file = '/etc/hosts';
		}

		$content = file_get_contents($use_hosts_file);
		if(preg_match('/^\s*' . preg_quote($this->ip_address, '/') . ' (.*?)$/m', $content, $matches)) {
			ISPConfigLog::info('Hostname is currently set to ' . $matches[1]);
			$content = str_replace($matches[0], $hosts_entry, $content);
			if($matches[0] != $hosts_entry) {
				$hostname_changed = true;
			}
		} else {
			ISPConfigLog::info('Hostname not found in hosts file.');
			$content .= "\n" . $hosts_entry;
			$hostname_changed = true;
		}
		file_put_contents($use_hosts_file, $content);

		$content = trim(file_get_contents('/etc/hostname'));
		if($content != $short_hostname) {
			ISPConfigLog::info('/etc/hostname is currently set to ' . $content, true);
			$hostname_changed = true;
			file_put_contents('/etc/hostname', $short_hostname);
		}

		ISPConfigLog::info('Hostname saved.', true);

		if($hostname_changed) {
			ISPConfigLog::info('Rebooting server.', true);
			$ok = $this->exec('shutdown -r now >/dev/null 2>&1', array(0, 255));
			if($ok === false) {
				throw new ISPConfigOSException('Command for server reboot failed.');
			}

			$ok = $this->waitForReboot(30, 1200);
			if(!$ok) {
				throw new ISPConfigOSException('Timeout waiting for server to come up.');
			}

			ISPConfigLog::info('Server online again.', true);
		}*/


		$cmd = 'readlink /bin/sh 2>&1';
		$check = trim($this->exec($cmd));
		if($check === false) {
			throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
		} elseif($check !== 'bash') {
			//debconf-show dash

			ISPConfigLog::info('Default shell is currently ' . $check . '.', true);

			ISPConfigLog::info('Setting bash as default shell.', true);
			$cmd = 'echo "dash dash/sh boolean false" | debconf-set-selections && DEBIAN_FRONTEND=noninteractive dpkg-reconfigure dash 2>&1';
			$result = $this->exec($cmd);
			if($result === false) {
				throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
			}

			$cmd = 'readlink /bin/sh 2>&1';
			$check = trim($this->exec($cmd));
			ISPConfigLog::info('Default shell is now ' . $check . '.', true);
		}

		$cmd = 'echo "postfix postfix/mailname string ' . $host_name . '" | debconf-set-selections 2>&1' . "\n";
		$cmd .= 'echo "postfix postfix/main_mailer_type select Internet Site" | debconf-set-selections 2>&1';
		$result = $this->exec($cmd);
		if($result === false) {
			throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
		}

		$packages = $this->getPackagesToInstall('first');
		$this->installPackages($packages);

		if(ISPConfig::shallInstall('mail')) {
			$packages = array(
				'dovecot-imapd',
				'dovecot-pop3d',
				'dovecot-mysql',
				'dovecot-sieve',
				'dovecot-managesieved',
				'dovecot-lmtpd'
			);
			$this->installPackages($packages);
		} else {
			$cmd = 'postconf -e "inet_interfaces = loopback-only"';
			$result = $this->exec($cmd);
			if($result === false) {
				ISPConfigLog::warn('Command ' . $cmd . ' failed.', true);
			}
		}


		ISPConfigLog::info('Generating MySQL password.', true);
		// generate random password
		$mysql_root_pw = ISPConfigFunctions::generatePassword(20);
		$queries = $this->getMySQLUserQueries($mysql_root_pw);

		foreach($queries as $query) {
			$cmd = 'mysql --defaults-file=/etc/mysql/debian.cnf -e ' . escapeshellarg($query) . ' 2>&1';
			$result = $this->exec($cmd);
			if($result === false) {
				ISPConfigLog::warn('Query ' . $query . ' failed.', true);
			}
		}

		$this->updateMySQLConfig($mysql_root_pw);

		if(ISPConfig::shallInstall('mail')) {
			ISPConfigLog::info('Configuring postfix.', true);
			$entries = array(
				array(
					'first_line' => '/^submission\s+inet/',
					'last_line' => '/^[a-z]/',
					'skip_last_line' => true,
					'search' => '/^\s+-o/'
				),
				array(
					'first_line' => '/^smtps\s+inet/',
					'last_line' => '/^[a-z]/',
					'skip_last_line' => true,
					'search' => '/^\s+-o/'
				)
			);
			$this->commentLines('/etc/postfix/master.cf', $entries);

			$entries = array(
				array(
					'first_line' => '/^#?submission\s+inet/',
					'last_line' => null,
					'search' => null,
					'add_lines' => array(
						' -o syslog_name=postfix/submission',
						' -o smtpd_tls_security_level=encrypt',
						' -o smtpd_sasl_auth_enable=yes',
						' -o smtpd_client_restrictions=permit_sasl_authenticated,reject'
					)
				),
				array(
					'first_line' => '/^#?smtps\s+inet/',
					'last_line' => null,
					'search' => null,
					'add_lines' => array(
						' -o syslog_name=postfix/smtps',
						' -o smtpd_tls_wrappermode=yes',
						' -o smtpd_sasl_auth_enable=yes',
						' -o smtpd_client_restrictions=permit_sasl_authenticated,reject'
					)
				)
			);
			$this->uncommentLines('/etc/postfix/master.cf', $entries);
		}

		ISPConfigLog::info('Restarting postfix', true);
		$this->restartService('postfix');

		$replacements = array(
			'/^mysql\s+soft\s+nofile\s+.*/' => 'mysql soft nofile 65535',
			'/^mysql\s+hard\s+nofile\s+.*/' => 'mysql hard nofile 65535'
		);
		$this->replaceContents('/etc/security/limits.conf', $replacements, true);

		if(!is_dir('/etc/systemd/system/mysql.service.d/')) {
			mkdir('/etc/systemd/system/mysql.service.d/', 0777, true);
		}

		$replacements = array(
			'/^\s*LimitNOFILE\s*=.*?$/m' => 'LimitNOFILE=infinity'
		);
		$this->replaceContents('/etc/systemd/system/mysql.service.d/limits.conf', $replacements, true, 'Service');

		$this->exec('systemctl daemon-reload 2>&1');
		$this->restartService('mysql');

		$packages = $this->getPackagesToInstall('mail');
		$this->installPackages($packages);

		if(ISPConfig::shallInstall('mail') && !ISPConfig::wantsAmavis()) {
			ISPConfigLog::info('Stopping Rspamd.', true);
			$this->stopService('rspamd');
		}

		if(ISPConfig::wantsUnbound()) {
			ISPConfigLog::info('(Re)starting unbound.', true);
			$this->restartService('unbound');
		} else {
			ISPConfigLog::info('(Re)starting Bind.', true);
			$this->restartService('bind9');
		}

		ISPConfigLog::info('Disabling spamassassin daemon.', true);
		$this->stopService('spamassassin');
		$this->exec('systemctl disable spamassassin 2>&1');

		$this->afterPackageInstall('mail');

		//$cmd = 'sudo -u unbound unbound-anchor -a /var/lib/unbound/root.key';
		/*$result = $this->exec($cmd);
		if($result === false) {
			throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
		}
		$this->restartService('unbound');
		*/

		if(ISPConfig::shallInstall('local-dns')) {
			if(!is_dir('/etc/resolvconf/resolv.conf.d')) {
				mkdir('/etc/resolvconf/resolv.conf.d', 0755);
			}
			$this->addLines('/etc/resolvconf/resolv.conf.d/head', 'nameserver 127.0.0.1', false);
			$cmd = 'resolvconf -u 2>&1';
			$result = $this->exec($cmd);
			if($result === false) {
				throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
			}

			ISPConfigLog::info('Checking local dns resolver.', true);
			$cmd = 'nslookup denic.de | grep Server';
			$result = $this->exec($cmd);
			if($result === false) {
				throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
			} elseif(strpos($result, '127.0.0.1') === false) {
				ISPConfigLog::warn('Unexpected resolver response: ' . $result, true);
			}
		}

		if(ISPConfig::shallInstall('web')) {
			$this->stopService('apache2');
			$this->stopService('nginx');

			if(ISPConfig::$WEBSERVER === ISPC_WEBSERVER_APACHE) {
				$packages = array(
					'apache2',
					'apache2-doc',
					'apache2-utils',
					'libapache2-mod-fcgid',
					'apache2-suexec-pristine',
					'libapache2-mod-python',
					'libapache2-mod-passenger'
				);
			} elseif(ISPConfig::$WEBSERVER === ISPC_WEBSERVER_NGINX) {
				$packages = array(
					'nginx-full',
					'fcgiwrap'
				);
			}
			$this->installPackages($packages);

			if(ISPConfig::$WEBSERVER === ISPC_WEBSERVER_NGINX) {
				$this->stopService('apache2');
				$cmd = 'systemctl disable apache2 >/dev/null 2>&1';
				$this->exec($cmd); // ignore if this fails
				$this->startService('nginx');
			}
		}

		$packages = $this->getPackagesToInstall('base');

		if(ISPConfig::wantsPHP() === 'system') {
			$php_versions = array($this->getSystemPHPVersion());
		} else {
			//ISPConfig::run() validations prevent sending here null values
			$php_versions = ISPConfig::wantsPHP();
			if (!in_array($this->getSystemPHPVersion(), $php_versions)) {
				$php_versions[] = $this->getSystemPHPVersion();
			}
		}

		$php_modules = array(
			'common',
			'gd',
			'mysql',
			'imap',
			'cli',
			'mcrypt',
			'curl',
			'intl',
			'pspell',
			'recode',
			'sqlite3',
			'tidy',
			'xmlrpc',
			'xsl',
			'zip',
			'mbstring',
			'soap',
			'opcache'
		);
		if(ISPConfig::shallInstall('web')) {
			$php_modules[] = 'cgi';
			$php_modules[] = 'fpm';
		}

		foreach($php_versions as $curver) {
			$packages[] = 'php' . $curver;
			reset($php_modules);
			foreach($php_modules as $curmod) {
				if(version_compare($curver, '7.2', '>=') && in_array($curmod, array('mcrypt'), true)) {
					continue;
				} elseif(version_compare($curver, '7.4', '>=') && in_array($curmod, array('mcrypt', 'recode'), true)) {
					continue;
				} elseif(version_compare($curver, '8.0', '>=') && in_array($curmod, array('mcrypt', 'recode', 'json', 'xmlrpc'), true)) {
					continue;
				}
				$packages[] = 'php' . $curver . '-' . $curmod;
			}
		}
		$this->installPackages($packages);

		if(ISPConfig::shallInstall('web') && ISPConfig::$WEBSERVER === ISPC_WEBSERVER_APACHE) {
			// Disable conflicting modules so mpm_event can be used with http2
			ISPConfigLog::info('Disabling conflicting apache modules.', true);
			$modules = $this->getApacheModulesToDisable();
			$cmd = 'a2dismod ' . implode(' ', $modules) . ' 2>&1';
			$result = $this->exec($cmd);
			if($result === false) {
				// throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
			}

			ISPConfigLog::info('Enabling apache modules.', true);
			$modules = $this->getApacheModulesToEnable();
			$cmd = 'a2enmod ' . implode(' ', $modules) . ' 2>&1';
			$result = $this->exec($cmd);
			if($result === false) {
				throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
			}

			ISPConfigLog::info('Enabling default PHP-FPM config.', true);
			$conf = 'php' . $this->getSystemPHPVersion() . '-fpm';
			$cmd = 'a2enconf ' . $conf . ' 2>&1';
			$result = $this->exec($cmd);
			if($result === false) {
				throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
			}

			$this->restartService('apache2');
		}

		try {
			$this->setPHPTimezone();
			$this->setDefaultPHP();
		} catch (Exception $ex) {
			throw $ex;
		}

		foreach($php_versions as $curver) {
			$this->restartService('php' . $curver . '-fpm');
		}

		try{
			$this->installPHPMyAdmin($mysql_root_pw);
		} catch(Exception $ex) {
			throw $ex;
		}

		if(ISPConfig::shallInstall('web') && ISPConfig::$WEBSERVER === ISPC_WEBSERVER_APACHE) {
			ISPConfigLog::info('HTTPoxy config.', true);
			$httpoxy = '<IfModule mod_headers.c>' . "\n" . '    RequestHeader unset Proxy early' . "\n" . '</IfModule>';
			file_put_contents('/etc/apache2/conf-available/httpoxy.conf', $httpoxy);
			$cmd = 'a2enconf httpoxy 2>&1';
			$result = $this->exec($cmd);
			if($result === false) {
				throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
			}

			$this->restartService('apache2');
		}

		if (ISPConfig::wantsCertbot()) {
			ISPConfigLog::info('Installing Certbot (Let\'s Encrypt).', true);
			$this->installPackages('certbot');
		} else {
			ISPConfigLog::info('Installing acme.sh (Let\'s Encrypt).', true);
			$cmd = 'cd /tmp ; wget -O -  https://get.acme.sh 2>/dev/null | sh 2>/dev/null';
			$result = $this->exec($cmd);
			if($result === false) {
				ISPConfigLog::warn('Installation of acme.sh (Let\'s Encrypt) failed.', true);
			} else {
				ISPConfigLog::info('acme.sh (Let\'s Encrypt) installed.', true);
			}
		}

		$mailman_password = '';
		if(ISPConfig::shallInstall('mailman')) {
			$mailman_password = $this->installMailman($host_name);
		}

		$packages = array(
			'quota',
			'quotatool',
			'haveged',
			'geoip-database',
			'libclass-dbi-mysql-perl',
			'libtimedate-perl',
			'build-essential',
			'autoconf',
			'automake',
			'libtool',
			'flex',
			'bison',
			'debhelper',
			'binutils'
		);
		$this->installPackages($packages);

		if(ISPConfig::shallInstall('quota')) {
			// check kernel if it is virtual
			$check = $this->getPackageVersion('linux-image-virtual');
			if($check) {
				ISPConfigLog::info('Installing extra quota package for virtual kernel.', true);
				$this->installPackages('linux-image-extra-virtual');

				// check kernel version from dpkg vs version running
				$check = $this->getPackageVersion('linux-image-extra-virtual');
				$running_version = php_uname('r');
				if(!is_dir('/lib/modules/' . $running_version . '/kernel/fs/quota/') || !is_file('/lib/modules/' . $running_version . '/kernel/fs/quota/quota_v2.ko')) {
					$running_version = preg_replace('/^([0-9\.]+(?:-\d+)?)(?:-.*?)?$/', '$1', $running_version);
					try {
						$this->installPackages('linux-image-extra-virtual=' . $running_version . '*');
					} catch (Exception $ex) {
						// ignore it
					}

					// check if quota module is available
					if(!$this->exec('modinfo quota_v1 quota_v2 2>&1')) {
						ISPConfigLog::error('The running kernel version (' . $running_version . ') does not match your installed kernel modules (' . $check . '). Currently there is no quota available! Please reboot your server to load the new kernel and run the autoinstaller again or start it with --no-quota to disable quota completely.', true);
						throw new ISPConfigOSException('Installation aborted due to missing dependencies.');
					}
				}

				ISPConfigLog::info('Enabling quota modules for virtual kernel.', true);
				$cmd = 'modprobe quota_v2 quota_v1 2>&1';
				$result = $this->exec($cmd);
				if($result === false) {
					throw new ISPConfigOSException('Enabling quota modules failed.');
				}
			}

			ISPConfigLog::info('Adding quota to fstab.', true);
			$replacements = array(
				'/^(\S+\s+\/\s+ext\d)\s+(\S+)\s+(\d\s+\d)\s*$/m' => array(
					'replace' => '$1 $2,usrjquota=quota.user,grpjquota=quota.group,jqfmt=vfsv0 $3',
					'ifnot' => 'usrjquota='
				)
			);
			$this->replaceContents('/etc/fstab', $replacements);

			$cmd = 'mount -o remount / 2>&1 && quotaoff -avug 2>&1 && quotacheck -avugm 2>&1 && quotaon -avug 2>&1';
			$result = $this->exec($cmd);
			if($result === false) {
				throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
			}
		}

		if(ISPConfig::shallInstall('web')) {
			$cmd = 'echo "pure-ftpd-common pure-ftpd/standalone-or-inetd select standalone" | debconf-set-selections 2>&1' . "\n";
			$cmd .= 'echo "pure-ftpd-common pure-ftpd/virtualchroot boolean true" | debconf-set-selections 2>&1';
			$result = $this->exec($cmd);
			if($result === false) {
				throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
			}

			$packages = $this->getPackagesToInstall('ftp_stats');
			$this->installPackages($packages);

			ISPConfigLog::info('Enabling TLS for pureftpd', true);
			if(!is_dir('/etc/pure-ftpd/conf')) {
				mkdir('/etc/pure-ftpd/conf', 0755);
			}
			file_put_contents('/etc/pure-ftpd/conf/TLS', '1');
			if(!is_dir('/etc/ssl/private')) {
				mkdir('/etc/ssl/private', 0755, true);
			}

			$ssl_subject = '/C=DE/ST=None/L=None/O=IT/CN=' . $host_name;
			$cmd = 'openssl req -x509 -nodes -days 7300 -newkey rsa:2048 -subj ' . escapeshellarg($ssl_subject) . ' -keyout /etc/ssl/private/pure-ftpd.pem -out /etc/ssl/private/pure-ftpd.pem > /dev/null 2>&1';
			$result = $this->exec($cmd);
			if($result === false) {
				throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
			}
			chmod('/etc/ssl/private/pure-ftpd.pem', 0600);

			// set passive port range if needed
			$ftp_ports = ISPConfig::getFTPPassivePorts();
			if($ftp_ports) {
				file_put_contents('/etc/pure-ftpd/conf/PassivePortRange', $ftp_ports['from'] . ' ' . $ftp_ports['to']);
			}

			$this->restartService('pure-ftpd-mysql');

			ISPConfigLog::info('Disabling awstats cron.', true);
			$entries = array(
				array(
					'first_line' => '/.*/',
					'last_line' => '/####nomatch###/',
					'search' => '/.*/'
				)
			);
			$this->commentLines('/etc/cron.d/awstats', $entries);

			if($this->shallCompileJailkit()) {
				$cmd = 'cd /tmp ; ( wget -O jailkit-2.23.tar.gz "http://olivier.sessink.nl/jailkit/jailkit-2.23.tar.gz" > /dev/null 2>&1 && tar xzf jailkit-2.23.tar.gz 2>&1 ) && ( cd jailkit-2.23 ; echo 5 > debian/compat ; ./debian/rules binary 2>&1 ) && ( cd /tmp ; dpkg -i jailkit_2.23-1_*.deb 2>&1 ; rm -rf jailkit-2.23* )';
				$result = $this->exec($cmd, array(), 3);
				if($result === false) {
					throw new ISPConfigOSException('Command ' . $cmd . ' failed.');

				}
			}
		}

		$packages = array(
			'fail2ban'
		);
		if(ISPConfig::shallInstall('firewall')) {
			$packages[] = 'ufw';
		}

		$this->installPackages($packages);

		$jk_jail = $this->getFail2BanJail();
		file_put_contents('/etc/fail2ban/jail.local', $jk_jail);
		unset($jk_jail);

		$this->restartService('fail2ban');

		$this->fixDbconfigCommon();

		$monit_password = '';
		if(ISPConfig::wantsMonit()) {
			$monit_password = $this->installMonit();
		}

		$this->configureSecureShell();

		if(ISPConfig::wantsUnattendedUpgrades()) {
			$this->installUnattendedUpgrades();
		}

		if ((ISPConfig::shallInstall('mail') && ISPConfig::shallInstall('roundcube')) || (ISPConfig::wantsRoundcube())) {
			$this->installRoundcube($mysql_root_pw);
		}

		if(ISPConfig::shallInstall('web')) {
			if(ISPConfig::$WEBSERVER === ISPC_WEBSERVER_APACHE) {
				$this->restartService('apache2');
			} else {
				$this->restartService('nginx');
			}
		}

		ISPConfigLog::info('Installing ISPConfig3.', true);

		$ispconfig_admin_pw = ISPConfigFunctions::generatePassword(15);

		if(!ISPConfig::wantsInteractive()) {
			$autoinstall = '[install]
	language=' . (isset($_GET['lang']) && $_GET['lang'] === 'de' ? 'de' : 'en') . '
	install_mode=expert
	hostname=' . $host_name . '
	mysql_hostname=localhost
	mysql_port=3306
	mysql_root_user=root
	mysql_root_password=' . $mysql_root_pw . '
	mysql_database=dbispconfig
	mysql_charset=utf8
	http_server=' . (ISPConfig::$WEBSERVER === ISPC_WEBSERVER_APACHE ? 'apache' : 'nginx') . '
	ispconfig_port=8080
	ispconfig_use_ssl=y
	ispconfig_admin_password=' . $ispconfig_admin_pw . '
	create_ssl_server_certs=y
	ignore_hostname_dns=n
	ispconfig_postfix_ssl_symlink=y
	ispconfig_pureftpd_ssl_symlink=y

	[ssl_cert]
	ssl_cert_country=DE
	ssl_cert_state=None
	ssl_cert_locality=None
	ssl_cert_organisation=None
	ssl_cert_organisation_unit=IT
	ssl_cert_common_name=' . $host_name . '
	ssl_cert_email=

	[expert]
	mysql_ispconfig_user=ispconfig
	mysql_ispconfig_password=' . ISPConfigFunctions::generatePassword(15) . '
	join_multiserver_setup=n
	mysql_master_hostname=
	mysql_master_root_user=
	mysql_master_root_password=
	mysql_master_database=
	configure_mail=' . (ISPConfig::shallInstall('mail') ? 'y' : 'n') . '
	configure_jailkit=' . (ISPConfig::shallInstall('web') ? 'y' : 'n') . '
	configure_ftp=' . (ISPConfig::shallInstall('web') ? 'y' : 'n') . '
	configure_dns=' . (ISPConfig::shallInstall('dns') ? 'y' : 'n') . '
	configure_apache=' . (ISPConfig::shallInstall('web') && ISPConfig::$WEBSERVER === ISPC_WEBSERVER_APACHE ? 'y' : 'n') . '
	configure_nginx=' . (ISPConfig::shallInstall('web') && ISPConfig::$WEBSERVER === ISPC_WEBSERVER_NGINX ? 'y' : 'n') . '
	configure_firewall=' . (ISPConfig::shallInstall('firewall') ? 'y' : 'n') . '
	configure_webserver=' . (ISPConfig::shallInstall('web') ? 'y' : 'n') . '
	install_ispconfig_web_interface=' . (ISPConfig::shallInstall('web') ? 'y' : 'n') . '

	[update]
	do_backup=yes
	mysql_root_password=' . $mysql_root_pw . '
	mysql_master_hostname=
	mysql_master_root_user=
	mysql_master_root_password=
	mysql_master_database=
	reconfigure_permissions_in_master_database=no
	reconfigure_services=yes
	ispconfig_port=8080
	create_new_ispconfig_ssl_cert=no
	reconfigure_crontab=yes
	create_ssl_server_certs=y
	ignore_hostname_dns=n
	ispconfig_postfix_ssl_symlink=y
	ispconfig_pureftpd_ssl_symlink=y

	; These are for service-detection (defaulting to old behaviour where alle changes were automatically accepted)
	svc_detect_change_mail_server=yes
	svc_detect_change_web_server=yes
	svc_detect_change_dns_server=yes
	svc_detect_change_xmpp_server=yes
	svc_detect_change_firewall_server=yes
	svc_detect_change_vserver_server=yes
	svc_detect_change_db_server=yes';
			if(!ISPConfig::shallInstall('local-dns')) {
				$autoinstall = str_replace("configure_dns=y", "configure_dns=n", $autoinstall);
			}
			file_put_contents('/tmp/ispconfig.autoinstall.ini', $autoinstall);
			$ai_argument = '--autoinstall=/tmp/ispconfig.autoinstall.ini';
		} else {
			$ai_argument = '';
		}

		if(ISPConfig::wantsInteractive()) {
			ISPConfigLog::info('Your MySQL root password is: ' . $mysql_root_pw, true);
		}

		$cmd = 'cd /tmp ; rm -rf ispconfig3_install 2>&1';
		if(ISPConfig::getISPConfigChannel() === 'dev') {
			$cmd .= ' ; wget -O ispconfig.tar.gz "https://git.ispconfig.org/ispconfig/ispconfig3/-/archive/develop/ispconfig3-develop.tar.gz" >/dev/null 2>&1 ; tar xzf ispconfig.tar.gz ; mv ispconfig3-develop ispconfig3_install';
		} else {
			$cmd .= ' ; wget -O ispconfig.tar.gz "https://www.ispconfig.org/downloads/ISPConfig-3-stable.tar.gz" >/dev/null 2>&1 ; tar xzf ispconfig.tar.gz';
		}
		$cmd .= ' ; cd ispconfig3_install ; cd install ; php -q install.php ' . $ai_argument . ' 2>&1 ; cd /tmp ; rm -rf ispconfig3_install 2>&1';
		if(ISPConfig::wantsInteractive()) {
			$result = $this->passthru($cmd);
		} else {
			$result = $this->exec($cmd);
		}
		if($result === false) {
			throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
		}

		if(!ISPConfig::wantsInteractive() && is_file('/tmp/ispconfig.autoinstall.ini')) {
			unlink('/tmp/ispconfig.autoinstall.ini');
		}

		if(ISPConfig::shallInstall('web')) {
			ISPConfigLog::info('Adding PHP version(s) to ISPConfig.', true);

			$server_id = 0;
			$ispc_config = ISPConfigConnector::getLocalConfig();
			if(!$ispc_config || !isset($ispc_config['server_id']) || !$ispc_config['server_id']) {
				throw new ISPConfigOSException('Could not read ISPConfig settings file.');
			}
			$server_id = $ispc_config['server_id'];

			foreach($php_versions as $curver) {
				$qry = 'INSERT IGNORE INTO `' . $ispc_config['db_database'] . '`.`server_php` (`sys_userid`, `sys_groupid`, `sys_perm_user`, `sys_perm_group`, `sys_perm_other`, `server_id`, `client_id`, `name`, `php_fastcgi_binary`, `php_fastcgi_ini_dir`, `php_fpm_init_script`, `php_fpm_ini_dir`, `php_fpm_pool_dir`, `active`) VALUES (1, 1, \'riud\', \'riud\', \'\', ' . intval($server_id) . ', 0, \'PHP ' . $curver . '\', \'/usr/bin/php-cgi' . $curver . '\', \'/etc/php/' . $curver . '/cgi\', \'/etc/init.d/php' . $curver . '-fpm\', \'/etc/php/' . $curver . '/fpm\', \'/etc/php/' . $curver . '/fpm/pool.d\', \'y\')';
				if($server_id == "0" || $server_id == "1") {
					$cmd = 'mysql --defaults-file=/etc/mysql/debian.cnf -e ' . escapeshellarg($qry);
				} else {
					// Hotfix for https://git.ispconfig.org/ispconfig/ispconfig3/-/issues/6466
					// $cmd = 'mysql --host=' . $ispc_config['dbmaster_host'] . ' --user=' . $ispc_config['dbmaster_user'] . ' --password=' . $ispc_config['dbmaster_password'] . ' -e ' . escapeshellarg($qry);
					$cmd = 'mysql --defaults-file=/etc/mysql/debian.cnf -e ' . escapeshellarg($qry);
				}

				$result = $this->exec($cmd);
				if($result === false) {
					throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
				}
			}
		}

		// Configure Roundcube permissions after ISPConfig installation
		if(ISPConfig::$WEBSERVER === ISPC_WEBSERVER_NGINX && file_exists('/etc/roundcube/config.inc.php')) {
			$cmd = 'chown root:ispapps /etc/roundcube/debian-db.php ; chmod 640 /etc/roundcube/debian-db.php ; chown root:ispapps /etc/roundcube/config.inc.php ; chmod 640 /etc/roundcube/config.inc.php ; chown -R ispapps:adm /var/log/roundcube ; chmod -R 750 /var/log/roundcube ; chown -R ispapps:ispapps /var/lib/roundcube/temp ; chmod -R 750 /var/lib/roundcube/temp';
			$result = $this->exec($cmd);
			if($result === false) {
				throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
			}
		}


		$this->restartService('clamav-daemon');
		if(ISPConfig::shallInstall('mail')) {
			if(ISPConfig::wantsAmavis()) {
				$this->restartService('amavis');
			} else {
				$this->startService('rspamd');
			}
		}


		ISPConfigLog::info('Checking all services are running.', true);
		$check_services = array(
			'mysql',
			'clamav-daemon',
			'postfix',
		);

		if(ISPConfig::shallInstall('local-dns')) {
			if(ISPConfig::wantsUnbound()) {
				$check_services[] = 'unbound';
			} else {
				$check_services[] = 'bind9';
			}
		}
		if(ISPConfig::shallInstall('web')) {
			$check_services[] = 'pureftpd';
			if(ISPConfig::$WEBSERVER === ISPC_WEBSERVER_APACHE) {
				$check_services[] = 'apache2';
			} elseif(ISPConfig::$WEBSERVER === ISPC_WEBSERVER_NGINX) {
				$check_services[] = 'nginx';
			}
		}
		if(ISPConfig::shallInstall('mail')) {
			if(!ISPConfig::wantsAmavis()) {
				$check_services[] = 'rspamd';
				$check_services[] = 'redis-server';
			} else {
				$check_services[] = 'amavis';
			}
			$check_services[] = 'dovecot';
		}
		if(ISPConfig::wantsMonit()) {
			// Start Monit service
			$this->startService('monit');
			$check_services[] = 'monit';
		}

		foreach($check_services as $service) {
			$status = $this->isServiceRunning($service);
			ISPConfigLog::info($service . ': ' . ($status ? '<green>OK</green>' : '<lightred>FAILED</lightred>'), true);
			if(!$status) {
				ISPConfigLog::warn($service . ' seems not to be running!', true);
			}
		}


		ISPConfigLog::info('Installation ready.', true);

		if(ISPConfig::shallInstall('mailman') && $mailman_password != '') {
			ISPConfigLog::info('Your Mailman password is: ' . $mailman_password, true);
		}
		if(ISPConfig::wantsMonit() && $monit_password != '') {
			ISPConfigLog::info('Your Monit password is: ' . $monit_password, true);
		}
		if(ISPConfig::shallInstall('web') && !ISPConfig::wantsInteractive()) {
			ISPConfigLog::info('Your ISPConfig admin password is: ' . $ispconfig_admin_pw, true);
		}
		ISPConfigLog::info('Your MySQL root password is: ' . $mysql_root_pw, true);

		return true;
	}

	protected function getSystemPHPVersion() {
		return '7.0';
	}
}
