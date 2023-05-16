<?php
/**
 * Description of class
 *
 * @author croydon
 */
class ISPConfigUbuntuOS extends ISPConfigDebianOS {
	protected function configureApt() {
		// enable contrib and non-free
		ISPConfigLog::info('Configuring apt repositories.', true);

		$contents = '# created by ISPConfig auto installer
deb http://de.archive.ubuntu.com/ubuntu/ bionic main restricted
deb http://de.archive.ubuntu.com/ubuntu/ bionic-updates main restricted
deb http://de.archive.ubuntu.com/ubuntu/ bionic universe
deb http://de.archive.ubuntu.com/ubuntu/ bionic-updates universe
deb http://de.archive.ubuntu.com/ubuntu/ bionic multiverse
deb http://de.archive.ubuntu.com/ubuntu/ bionic-updates multiverse
deb http://de.archive.ubuntu.com/ubuntu/ bionic-backports main restricted universe multiverse
deb http://security.ubuntu.com/ubuntu bionic-security main restricted
deb http://security.ubuntu.com/ubuntu bionic-security universe
deb http://security.ubuntu.com/ubuntu bionic-security multiverse
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

	protected function afterPackageInstall($section = '') {
		if($section === 'mail') {
			$this->stopService('clamav-freshclam');
			$cmd = 'freshclam';
			$result = $this->exec($cmd, array(62));
			if($result === false) {
				//throw new ISPConfigOSException('Command ' . $cmd . ' failed.');
			}

			$this->startService('clamav-freshclam');
			$this->startService('clamav-daemon');
		}
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
		return '7.2';
	}

	protected function installUnattendedUpgrades() {
		parent::installUnattendedUpgrades();
		// Enable normal updates
		$replacements = array(
			'/^\/\/\s*"\$\{distro_id\}:\$\{distro_codename\}\-updates";/m' => '        "${distro_id}:${distro_codename}-updates";'
		);
		$result = $this->replaceContents('/etc/apt/apt.conf.d/50unattended-upgrades', $replacements);
	}
}
