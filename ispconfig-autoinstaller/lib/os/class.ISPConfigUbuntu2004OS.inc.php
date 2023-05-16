<?php
/**
 * Description of class
 *
 * @author croydon
 */
class ISPConfigUbuntu2004OS extends ISPConfigUbuntuOS {
	protected function configureApt() {
		// enable contrib and non-free
		ISPConfigLog::info('Configuring apt repositories.', true);

		$contents = '# created by ISPConfig auto installer
deb http://de.archive.ubuntu.com/ubuntu/ focal main restricted
deb http://de.archive.ubuntu.com/ubuntu/ focal-updates main restricted
deb http://de.archive.ubuntu.com/ubuntu/ focal universe
deb http://de.archive.ubuntu.com/ubuntu/ focal-updates universe
deb http://de.archive.ubuntu.com/ubuntu/ focal multiverse
deb http://de.archive.ubuntu.com/ubuntu/ focal-updates multiverse
deb http://de.archive.ubuntu.com/ubuntu/ focal-backports main restricted universe multiverse
deb http://security.ubuntu.com/ubuntu focal-security main restricted
deb http://security.ubuntu.com/ubuntu focal-security universe
deb http://security.ubuntu.com/ubuntu focal-security multiverse
		';
		file_put_contents('/etc/apt/sources.list', $contents);
	}

	protected function beforePackageInstall($section = '') {
		$this->stopService('apparmor');
		$this->stopService('sendmail');

		$cmd = 'update-rc.d -f apparmor remove ; update-rc.d -f sendmail remove ; apt-get -y -qq remove apparmor apparmor-utils';
		$result = $this->exec($cmd);
		if($result === false) {
			throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
		}
	}

	protected function getPackagesToInstall($section) {
		$packages = parent::getPackagesToInstall($section);

		if($section === 'mail') {
			$packages[] = 'p7zip';
			$packages[] = 'p7zip-full';
			$packages[] = 'unrar-free';
			$packages[] = 'lrzip';
		} elseif($section === 'base') {
			$packages = array(
				'php-pear',
				'php-memcache',
				'php-imagick',
				'mcrypt',
				'imagemagick',
				'libruby',
				'memcached',
				'php-apcu',
				'jailkit'
			);
		}

		return $packages;
	}

	protected function shallCompileJailkit() {
		return false;
	}

	protected function addSuryRepo() {
		ISPConfigLog::info('Activating sury php repository.', true);

		$cmd = 'add-apt-repository -y ppa:ondrej/php';
		$result = $this->exec($cmd);
		if($result === false) {
			throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
		}
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
action = iptables-multiport[name=dovecot-pop3imap, port="pop3,pop3s,imap,imaps", protocol=tcp]
logpath = /var/log/mail.log
maxretry = 5

[postfix-sasl]
enabled = true
port = smtp
filter = postfix
logpath = /var/log/mail.log
maxretry = 3';
		return $jk_jail;
	}

	protected function getSystemPHPVersion() {
		return '7.4';
	}

}
