<?php
/**
 * Description of class
 *
 * @author croydon
 */
class ISPConfigUbuntu2204OS extends ISPConfigUbuntu2004OS {

	protected function configureApt() {
		// enable contrib and non-free
		ISPConfigLog::info('Configuring apt repositories.', true);

		$contents = '# created by ISPConfig auto installer
deb http://archive.ubuntu.com/ubuntu/ jammy main restricted
deb http://archive.ubuntu.com/ubuntu/ jammy-updates main restricted
deb http://archive.ubuntu.com/ubuntu/ jammy universe
deb http://archive.ubuntu.com/ubuntu/ jammy-updates universe
deb http://archive.ubuntu.com/ubuntu/ jammy multiverse
deb http://archive.ubuntu.com/ubuntu/ jammy-updates multiverse
deb http://archive.ubuntu.com/ubuntu/ jammy-backports main restricted universe multiverse
deb http://security.ubuntu.com/ubuntu jammy-security main restricted
deb http://security.ubuntu.com/ubuntu jammy-security universe
deb http://security.ubuntu.com/ubuntu jammy-security multiverse
		';
		file_put_contents('/etc/apt/sources.list', $contents);
	}

	protected function getPackagesToInstall($section) {
		$packages = parent::getPackagesToInstall($section);

		if($section === 'first') {
			$packages[] = 'getmail6';
			$key = array_search('getmail4', $packages, true);
			if($key !== false) {
				unset($packages[$key]);
			}
		}

		return $packages;
	}

	protected function setDefaultPHP() {
		ISPConfigLog::info('Setting default system PHP version.', true);
		$cmd = 'update-alternatives --set php /usr/bin/php8.1';
		$result = $this->exec($cmd);
		if($result === false) {
			throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
		}

		if(ISPConfig::shallInstall('web')) {
			// When --use-php-system is used, there is no alternative for php-fpm.sock.
			if(ISPConfig::wantsPHP() === 'system') {
				$cmd = 'update-alternatives --set php-cgi /usr/bin/php-cgi8.1';
			} else {
				$cmd = 'update-alternatives --set php-cgi /usr/bin/php-cgi8.1 ; update-alternatives --set php-fpm.sock /run/php/php8.1-fpm.sock';
			}
			$result = $this->exec($cmd);
			if($result === false) {
				throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
			}
		}
	}

	protected function getMySQLUserQueries($mysql_root_pw) {
		$escaped_pw = preg_replace('/[\'\\\\]/', '\\$1', $mysql_root_pw);
		$queries = array(
			'DELETE FROM mysql.user WHERE User=\'\';',
			'DELETE FROM mysql.user WHERE User=\'root\' AND Host NOT IN (\'localhost\', \'127.0.0.1\', \'::1\');',
			'DROP DATABASE IF EXISTS test;',
			'DELETE FROM mysql.db WHERE Db=\'test\' OR Db=\'test\\_%\';',
			'SET PASSWORD FOR \'root\'@\'localhost\' = PASSWORD(\'' . $escaped_pw . '\');',
			'FLUSH PRIVILEGES;'
		);

		return $queries;
	}

	protected function installMailman($host_name) {
		ISPConfigLog::info('ISPConfig does not yet support mailman3 and mailman2 is no longer available in Ubuntu 22.04.', true);
		return;
	}

	protected function getSystemPHPVersion() {
		return '8.1';
	}

}
