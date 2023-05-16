<?php
/**
 * Description of class
 *
 * @author croydon
 */
class ISPConfigDebian10OS extends ISPConfigDebianOS {
	protected function updateMySQLConfig($mysql_root_pw) {
		ISPConfigLog::info('Writing MySQL config files.', true);
		$this->replaceContents('/etc/mysql/debian.cnf', array('/^password\s*=.*$/m' => 'password = ' . $mysql_root_pw));
		$this->replaceContents('/etc/mysql/mariadb.conf.d/50-server.cnf', array('/^bind-address/m' => '#bind-address'), true, 'mysqld');
	}

	public function getRestartServiceCommand($service, $command = 'restart') {
		if($command != 'start' && $command != 'stop' && $command != 'status') {
			$command = 'restart';
		}

		switch($service) {
			case 'mysql':
			case 'mariadb':
				$service = 'mariadb';
				break;
			case 'pureftpd':
				$service = 'pure-ftpd-mysql';
				break;
		}

		return 'systemctl ' . $command . ' ' . escapeshellarg($service) . ' 2>&1';
	}

	protected function getPackagesToInstall($section) {
		$packages = parent::getPackagesToInstall($section);

		if($section === 'first') {
			$packages[] = 'getmail';
			$key = array_search('getmail4', $packages, true);
			if($key !== false) {
				unset($packages[$key]);
			}
		} elseif($section === 'mail') {
			$packages[] = 'p7zip';
			$packages[] = 'p7zip-full';
			$packages[] = 'unrar-free';
			$packages[] = 'lrzip';
		} elseif($section === 'base') {
			//$packages[] = 'jailkit';
		}

		return $packages;
	}

	protected function shallCompileJailkit() {
		return true;
	}

	protected function getRoundcubePackages() {
		return array(
			'roundcube/buster-backports',
			'roundcube-core/buster-backports',
			'roundcube-mysql/buster-backports',
			'roundcube-plugins/buster-backports'
		);
	}

	protected function addBusterBackportsRepo() {
		ISPConfigLog::info('Activating Buster Backports repository.', true);
		$cmd = 'echo "deb http://deb.debian.org/debian buster-backports main" > /etc/apt/sources.list.d/buster-backports.list';
		$result = $this->exec($cmd);
		if($result === false) {
			throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
		}
	}

	protected function installMonit() {
		$this->addBusterBackportsRepo();
		$this->updatePackageList();
		return parent::installMonit();
	}

	protected function installRoundcube($mysql_root_pw) {
		$this->addBusterBackportsRepo();
		$this->updatePackageList();
		parent::installRoundcube($mysql_root_pw);
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
filter = postfix[mode=auth]
logpath = /var/log/mail.log
maxretry = 3';
		return $jk_jail;
	}

	protected function getSystemPHPVersion() {
		return '7.3';
	}
}
