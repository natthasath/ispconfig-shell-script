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

### 🦚 Change Password Admin ISPConfig

```sql
use dbispconfig;
update sys_user set passwort = md5('changeme') where username = 'admin';
flush privileges;
quit;
```

### 🦩 Change Password Root MySQL

```sql
use mysql
alter user 'root'@'localhost' identified by 'changeme';
flush privileges;
quit;
```

### ⛔ Disable Roundcube Service

```shell
a2disconf roundcube
systemctl reload apache2
```

### 🙈 Allow Network Access for phpMyAdmin

```shell
vi /etc/apache2/conf-available/phpmyadmin.conf
vi /etc/apache2/conf-enabled/phpmyadmin.conf
```

```
<Directory /usr/share/phpmyadmin>
    Options SymLinksIfOwnerMatch
    DirectoryIndex index.php
    AllowOverride None

    # Allow only specific networks
    Require ip 127.0.0.1 ::1
    Require ip 10.0.0.0/8
    Require ip 10.10.0.0/16
    Require ip 10.10.10.0/24
</Directory>
```

```shell
vi /etc/apache2/sites-available/ispconfig.conf
vi /etc/apache2/sites-enabled/000-ispconfig.conf
```

```
#<Directory /usr/share/phpmyadmin>
#       Require all granted
#</Directory>
```

### 🐿️ Enable General Log MySQL

```sql
SHOW VARIABLES LIKE 'datadir';
SHOW VARIABLES LIKE 'general_log';
SHOW VARIABLES LIKE 'general_log_file';
SHOW VARIABLES LIKE 'slow_query_log';
SHOW VARIABLES LIKE 'log_output'; 
SET GLOBAL general_log = 'ON';
SET GLOBAL general_log_file = '/var/log/mysql/general.log';
```

### 🐦‍🔥 Config Log Rotation

```shell
vi /etc/logrotate.d/mysql-general-log
```

```
/var/log/mysql/general.log {
    daily
    rotate 7
    compress
    missingok
    notifempty
    create 660 mysql adm
    postrotate
        systemctl reload mysql > /dev/null 2>&1 || true
    endscript
}
```

```shell
logrotate -f /etc/logrotate.d/mysql-general-log
```

### 🦩 Update Password Root MySQL connect via phpMyAdmin

```shell
vi /usr/local/ispconfig/server/lib/mysql_clientdb.conf
systemctl restart apache2
```

### ⭕ Upgrade OS Ubuntu 22.04 to Ubuntu 24.04

```shell
lsb_release -a
uname -r
df -h
lsblk
apt-mark showhold
grep -E 'jammy|noble' /etc/apt/sources.list /etc/apt/sources.list.d/*.list 2>/dev/null
apt update
apt full-upgrade
cat /etc/update-manager/release-upgrades
apt install -y ubuntu-release-upgrader-core
apt install -y screen
screen -S upgrade-24
do-release-upgrade
mariadb-upgrade -u root -p
systemctl restart mariadb
```

### 🦔 Upgrade PHP 8.2+ for phpMyAdmin

```shell
ls /etc/php
systemctl status php8.4-fpm --no-pager
a2enmod proxy_fcgi setenvif
vi /etc/phpmyadmin/apache.conf
```

```
# phpMyAdmin default Apache configuration

Alias /[alias_phpmyadmin] /usr/share/phpmyadmin

<Directory /usr/share/phpmyadmin>
    Options SymLinksIfOwnerMatch
    DirectoryIndex index.php
    AllowOverride None
    Require all granted

    # Limit libapache2-mod-php to files and directories required by phpMyAdmin (legacy for mod_php7)
    <IfModule mod_php7.c>
        php_admin_value upload_tmp_dir /var/lib/phpmyadmin/tmp
        php_admin_value open_basedir /usr/share/phpmyadmin/:/usr/share/doc/phpmyadmin/:/etc/phpmyadmin/:/var/lib/phpmyadmin/:/usr/share/php/:/usr/share/javascript/
    </IfModule>

    # Use PHP 8.4-FPM for phpMyAdmin
    <IfModule proxy_fcgi_module>
        <FilesMatch "\.php$">
            SetHandler "proxy:unix:/run/php/php8.4-fpm.sock|fcgi://localhost/"
        </FilesMatch>
    </IfModule>
</Directory>

# Disallow web access to directories that do not need it
<Directory /usr/share/phpmyadmin/templates>
    Require all denied
</Directory>
<Directory /usr/share/phpmyadmin/libraries>
    Require all denied
</Directory>

```

```shell
systemctl reload apache2
```

### 🦉 Change Version PHP-CLI

```shell
php -v
update-alternatives --display php
update-alternatives --install /usr/bin/php php /usr/bin/php8.x 8x
update-alternatives --config php
php -v
```

### 🐙 Change Path phpMyAdmin

```shell
vi /etc/apache2/conf-enabled/phpmyadmin.conf
```

```
Alias /hide /usr/share/phpmyadmin
```

### 🦠 Change Docker Network Subnet

- Create file

```shell
vi /etc/docker/daemon.json
```

- Add configuration

```json
{
  "bip": "172.18.0.1/16"
}
```

- Restart Docker

```shell
systemctl restart docker
```

### ⚠️ Remove Manual Apache

```shell
grep -R "Alias /manual" /etc/apache2 -n
a2disconf apache2-doc
systemctl reload apache2
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
sudo -u web1 wp db repair --path=/var/www/clients/client1/web1/web
sudo -u web1 wp db optimize --path=/var/www/clients/client1/web1/web
sudo -u web1 wp menu item list main-menu --path=/var/www/clients/client1/web1/web
sudo -u web1 wp transient delete --all --path=/var/www/clients/client1/web1/web
sudo -u web1 wp cli update --nightly --path=/var/www/clients/client1/web1/web
```

- Create User

```shell
sudo -u web1 php /usr/local/bin/wp user create john john@example.com --role=administrator --user_pass=changeme --path=/var/www/clients/client1/web1/web
```

### 🫂 ZST Compress Command

```shell
sudo apt install zstd -y
tar -czvf - input/ | zstd -o output.tar.zst
```

### 🧊 Update PPA

```shell
cat /etc/apt/sources.list.d/ondrej-ubuntu-php*.list
sudo sed -i 's|https://ppa.launchpadcontent.net|http://ppa.launchpad.net|g' /etc/apt/sources.list.d/ondrej-ubuntu-php*.list
sudo rm -f /var/lib/apt/lists/*ondrej*php*
sudo apt clean
sudo apt update
```

### ✨ Add PHP Latest Version (PHP-FPM + Fast-CGI)

```shell
sudo apt install -y php8.2 php8.2-cli php8.2-fpm php8.2-cgi php8.2-mysql php8.2-curl php8.2-gd php8.2-intl php8.2-mbstring php8.2-xml php8.2-zip php8.2-soap php8.2-bcmath
sudo apt install -y php8.3 php8.3-cli php8.3-fpm php8.3-cgi php8.3-mysql php8.3-curl php8.3-gd php8.3-intl php8.3-mbstring php8.3-xml php8.3-zip php8.3-soap php8.3-bcmath
sudo apt install -y php8.4 php8.4-cli php8.4-fpm php8.4-cgi php8.4-mysql php8.4-curl php8.4-gd php8.4-intl php8.4-mbstring php8.4-xml php8.4-zip php8.4-soap php8.4-bcmath

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

### ISPConfig Scripts

| Script                              | Category              | Purpose                                                                                                        |
| ----------------------------------- | --------------------- | -------------------------------------------------------------------------------------------------------------- |
| `handle_mailbox_soft_deleted.sh`    | Mail management       | Processes mailboxes marked as soft-deleted (cleanup, retention handling, or final removal after grace period). |
| `vlogger`                           | Logging / diagnostics | Logging utility used by services (commonly web servers) to record or process logs, often per virtual host.     |
| `update_stable.sh`                  | Update system         | Initiates update from the stable release channel.                                                              |
| `update_runner.sh`                  | Update system         | Main update execution wrapper that coordinates the update workflow.                                            |
| `update_from_svn.sh`                | Update system         | Updates installation from an SVN repository source.                                                            |
| `update_from_dev_stable.sh`         | Update system         | Updates from a development branch considered relatively stable (testing channel).                              |
| `update_from_dev.sh`                | Update system         | Updates directly from the development branch (bleeding-edge code).                                             |
| `ispconfig_update.sh`               | Update system         | Shell wrapper that prepares environment and triggers the main update process.                                  |
| `ispconfig_update.php`              | Update system         | Core update logic (PHP) handling version checks, file updates, and database migrations.                        |
| `ispconfig_patch`                   | Update system         | Applies patches or hotfixes to an existing installation.                                                       |
| `ispconfig_htaccess.php`            | Web configuration     | Generates or updates `.htaccess` rules for panel or hosted environments.                                       |
| `letsencrypt_pre_hook.sh`           | SSL / Let’s Encrypt   | Runs before certificate issuance or renewal (prepare services, validation setup, etc.).                        |
| `letsencrypt_post_hook.sh`          | SSL / Let’s Encrypt   | Runs after certificate issuance or renewal (deploy certs, reload services).                                    |
| `letsencrypt_renew_hook.sh`         | SSL / Let’s Encrypt   | Executes during renewal lifecycle to manage service behavior tied to renewal.                                  |
| `create_jailkit_user.sh`            | Jailkit environment   | Creates a jailed system user inside a Jailkit environment.                                                     |
| `create_jailkit_programs.sh`        | Jailkit environment   | Installs or enables specific programs inside the jail environment.                                             |
| `create_jailkit_chroot.sh`          | Jailkit environment   | Builds or prepares the chroot jail filesystem structure.                                                       |
| `create_daily_nginx_access_logs.sh` | Logging / web server  | Creates or rotates daily Nginx access logs (per site or system-wide depending on config).                      |


### ⌚ Crontab

```shell
0 5 * * * /root/ispconfig-shell-script/scripts/list-wordpress-backup.sh
0 6 * * * /root/ispconfig-shell-script/scripts/delete-wordpress-backups.sh
```