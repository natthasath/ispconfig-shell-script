# 🎉 ISPConfig Shell Script
ISPConfig is a web hosting control panel for Linux servers. A shell script can be used to automate common tasks like creating email accounts and websites, managing FTP users, and configuring SSL certificates.

![version](https://img.shields.io/badge/version-1.0-blue)
![rating](https://img.shields.io/badge/rating-★★★★★-yellow)
![uptime](https://img.shields.io/badge/uptime-100%25-brightgreen)

### 🐲 Use Apache

```shell
curl https://get.ispconfig.org | sh
```

### 🦄 Use NginX

```shell
curl https://get.ispconfig.org | sh -s -- --use-nginx
```

### 🧩 Check Version

```shell
grep 'def.*VERS' /usr/local/ispconfig/server/lib/config.inc.php
```

### ⌚ Set Timezone

```shell
timedatectl
timedatectl list-timezones
timedatectl set-timezone Asia/Bangkok
```

### ⌛ Set NTP

```shell
vi /etc/ntp.conf
---
server time.navy.mi.th iburst
server time1.nimt.or.th iburst
server clock.nectec.or.th iburst
```

```shell
sudo systemctl restart ntp
sudo systemctl status ntp
ntpq -p
```

### 🦕 Change Port ISPConfig

- Apache

```shell
vi /etc/apache2/sites-available/ispconfig.vhost
systemctl restart apache2
```

- Nginx

```shell
vi /etc/nginx/sites-available/ispconfig.vhost
systemctl restart apache2
```

### 🦩 Change Password Root MySQL

```sql
use mysql
alter user 'root'@'localhost' identified by 'changeme';
flush privileges;
quit;
```

### 🦩 Update Password Root MySQL connect via phpMyAdmin

```shell
vi /usr/local/ispconfig/server/lib/mysql_clientdb.conf
systemctl restart apache2
```

### 🦚 Change Password Admin ISPConfig

```sql
use dbispconfig;
update sys_user set passwort = md5('changeme') where username = 'admin';
flush privileges;
quit;
```

### 🦠 Change Docker Network Subnet

```shell
vi /etc/docker/daemon.json
```

```json
{
  "bip": "172.18.0.1/16"
}
```

```shell
systemctl restart docker
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

- Command Run

```shell
sudo -u web1 php /usr/local/bin/wp core version
wp core version --path=/var/www/clients/client1/web1/web
```

- Sort by priority update

```shell
sudo -u web1 wp plugin list --path=/var/www/clients/client1/web1/web
sudo -u web1 wp plugin update --all --path=/var/www/clients/client1/web1/web
sudo -u web1 wp theme list --path=/var/www/clients/client1/web1/web
sudo -u web1 wp theme update --all --path=/var/www/clients/client1/web1/web
sudo -u web1 wp core version --path=/var/www/clients/client1/web1/web
sudo -u web1 wp core update --path=/var/www/clients/client1/web1/web
```

- Another Command

```shell
sudo -u web1 wp user list --path=/var/www/clients/client1/web1/web
sudo -u web1 wp db check --path=/var/www/clients/client1/web1/web
sudo -u web1 wp menu item list main-menu --path=/var/www/clients/client1/web1/web
```

### 🫂 ZST Compress Command

```shell
sudo apt install zstd -y
tar -czvf - input/ | zstd -o output.tar.zst
```

### ✨ Add PHP Latest Version

```shell
sudo apt install -y php8.2 php8.2-cli php8.2-fpm php8.2-mysql php8.2-curl php8.2-gd php8.2-intl php8.2-mbstring php8.2-xml php8.2-zip php8.2-soap php8.2-bcmath
sudo apt install -y php8.3 php8.3-cli php8.3-fpm php8.3-mysql php8.3-curl php8.3-gd php8.3-intl php8.3-mbstring php8.3-xml php8.3-zip php8.3-soap php8.3-bcmath
sudo apt install -y php8.4 php8.4-cli php8.4-fpm php8.4-mysql php8.4-curl php8.4-gd php8.4-intl php8.4-mbstring php8.4-xml php8.4-zip php8.4-soap php8.4-bcmath

sudo systemctl enable php8.2-fpm
sudo systemctl start php8.2-fpm
sudo systemctl status php8.2-fpm

sudo systemctl enable php8.3-fpm
sudo systemctl start php8.3-fpm
sudo systemctl status php8.3-fpm

sudo systemctl enable php8.4-fpm
sudo systemctl start php8.4-fpm
sudo systemctl status php8.4-fpm

php8.2 -v
php8.3 -v
php8.4 -v
```