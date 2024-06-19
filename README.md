# 🎉 ISPConfig Shell Script
ISPConfig is a web hosting control panel for Linux servers. A shell script can be used to automate common tasks like creating email accounts and websites, managing FTP users, and configuring SSL certificates.

![version](https://img.shields.io/badge/version-1.0-blue)
![rating](https://img.shields.io/badge/rating-★★★★★-yellow)
![uptime](https://img.shields.io/badge/uptime-100%25-brightgreen)

### 🐲 Use Apache

```
curl https://get.ispconfig.org | sh
```

### 🦄 Use NginX

```
curl https://get.ispconfig.org | sh -s -- --use-nginx
```

### 🧩 Check Version

```
grep 'def.*VERS' /usr/local/ispconfig/server/lib/config.inc.php
```

### ⌚ Set Date Time

```
timedatectl
timedatectl list-timezones
timedatectl set-timezone Asia/Bangkok
```

### 🥏 Manual Backup

- Create a backup folder 

```shell
mkdir /home/backup
chmod 700 /home/backup
cd /home/backup
```

- Backup the database

```shell
mysqldump -u root -p dbispconfig > dbispconfig.sql
```

- Backup the ISPConfig software

```shell
tar pcfz ispconfig_software.tar.gz /usr/local/ispconfig
```

- Backup the configuration files in /etc

```shell
tar pcfz etc.tar.gz /etc
```

### 🧁 OS Update

- Enable Maintenance Mode [System -> Main Config -> Misc -> Maintenance Mode]
- Update Master server first and Slave server
- Update from Stable only

### 🍧 ISPConfig Update

- ISPConfig Backup: site backup in /usr/local/ispconfig and database backup in /var/backup after run script

```shell
cd /usr/local/bin/
ispconfig_update.sh
ispconfig_update.sh --force
```

### 🧦 ISPProtect

ISPProtect is a powerful security tool designed to scan and protect web servers against malware and suspicious files. It offers comprehensive monitoring, detection, and removal of threats, ensuring enhanced security for server environments.

```shell
mkdir -p /usr/local/ispprotect
chown -R root:root /usr/local/ispprotect
chmod -R 750 /usr/local/ispprotect
cd /usr/local/ispprotect
wget https://www.ispprotect.com/download/ispp_scan.tar.gz
tar xzf ispp_scan.tar.gz
rm -f ispp_scan.tar.gz
ln -s /usr/local/ispprotect/ispp_scan /usr/local/bin/ispp_scan
```

```shell
ispp_scan
```

### 🪬 WP-CLI

[WP-CLI](https://developer.wordpress.org/cli/commands/) is a command line interface for WordPress. You can update plugins, configure multisite installations, and much more, without using a web browser. Efficient for developers and administrators, it simplifies many WordPress tasks through a simple command-line interface.

```shell
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
php wp-cli.phar --info
chmod +x wp-cli.phar
sudo mv wp-cli.phar /usr/local/bin/wp
```

- Sort by priority update

```
sudo -u web1 php /usr/local/bin/wp plugin list
sudo -u web1 php /usr/local/bin/wp plugin update --all
sudo -u web1 php /usr/local/bin/wp theme list
sudo -u web1 php /usr/local/bin/wp theme update --all
sudo -u web1 php /usr/local/bin/wp core version
sudo -u web1 php /usr/local/bin/wp core update
```

- Another Command

```
sudo -u web1 php /usr/local/bin/wp user list
sudo -u web1 php /usr/local/bin/wp db check
sudo -u web1 php /usr/local/bin/wp menu item list main-menu
```