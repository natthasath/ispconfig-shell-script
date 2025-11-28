<?php

require __DIR__ . '/class.ISPConfigDebian10OS.inc.php';

/**
 * Description of class
 *
 * @author croydon
 */
class ISPConfigDebian11OS extends ISPConfigDebian10OS {
	protected function addBusterBackportsRepo() {
		// not in bullseye
	}

	protected function getRoundcubePackages() {
		return array(
			'roundcube',
			'roundcube-core',
			'roundcube-mysql',
			'roundcube-plugins'
		);
	}

	protected function getPackagesToInstall($section) {
		$packages = parent::getPackagesToInstall($section);

		if($section === 'base') {
			$key = array_search('php-gettext', $packages, true);
			if($key !== false) {
				unset($packages[$key]);
			}
			$packages[] = 'jailkit';
		} elseif($section === 'ftp_stats') {
			// prepare paths
			ISPConfigLog::info('Symlinking webalizer to use awffull.');
			mkdir('/etc/webalizer', 0755);
			chmod('/etc/webalizer', 0755);
			symlink('/etc/awffull/awffull.conf', '/etc/webalizer/webalizer.conf');
			symlink('/usr/bin/awffull', '/usr/bin/webalizer');

			$key = array_search('webalizer', $packages, true);
			if($key !== false) {
				unset($packages[$key]);
			}
			$packages[] = 'awffull';
		}

		return $packages;
	}

	protected function isStableSupported() {
		return true;
	}

	protected function shallCompileJailkit() {
		return false;
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
		ISPConfigLog::info('ISPConfig does not yet support mailman3 and mailman2 is no longer available in Debian 11.', true);
		return;
	}

	protected function getSystemPHPVersion() {
		return '7.4';
	}
}
