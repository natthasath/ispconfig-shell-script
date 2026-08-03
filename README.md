# 🎉 ISPConfig Shell Script

A collection of shell scripts and operational runbooks for running an [ISPConfig](https://www.ispconfig.org/) hosting server — installation, WordPress fleet maintenance, SSL monitoring, malware scanning, and firewall management.

![platform](https://img.shields.io/badge/platform-linux-lightgrey)
![license](https://img.shields.io/github/license/natthasath/ispconfig-shell-script)
![last commit](https://img.shields.io/github/last-commit/natthasath/ispconfig-shell-script)

### ✨ Features

- ISPConfig installer for Apache or Nginx
- WordPress fleet maintenance: discover sites, report versions/pending updates, prune stale backups
- SSL certificate expiry monitoring with LINE alerting
- IP blocklist enforcement via iptables
- Malware and suspicious-file scanning via ISPProtect
- WP-CLI reference commands for bulk WordPress operations
- `robots.txt` / sitemap upkeep across hosted sites

### 🧊 Folder Structure

```
.
├── Iptables/                  IP blocklist scripts and firewall rule reference
├── ispconfig-autoinstaller/   ISPConfig 3 installer (Apache/Nginx, Ubuntu/Debian)
├── ispprotect/                ISPProtect malware scanner
├── scripts/                   WordPress & ISPConfig maintenance automation
└── temp/                      One-off cleanup and disk-usage reporting scripts
```

### ✅ Requirements

- Ubuntu 18.04 / 20.04 / 22.04 / 24.04 or Debian 9 / 10 / 11 (x86_64 only)
- Root or sudo access
- ISPConfig 3.2 already installed, or use the bundled [autoinstaller](ispconfig-autoinstaller/README.md)

### 🚀 Installation

- Apache

```shell
curl https://get.ispconfig.org | sh
```

- Nginx

```shell
curl https://get.ispconfig.org | sh -s -- --use-nginx
```

- Verify installed version

```shell
grep 'def.*VERS' /usr/local/ispconfig/server/lib/config.inc.php
```

> For custom install flags (`--debug`, `--no-mailman`, etc.), see [`ispconfig-autoinstaller/README.md`](ispconfig-autoinstaller/README.md).

### ⚙️ Configuration

- Timezone & NTP

```shell
timedatectl list-timezones
timedatectl set-timezone Asia/Bangkok
```

```shell
vi /etc/ntp.conf
```

```
server time.navy.mi.th iburst
server time1.nimt.or.th iburst
server clock.nectec.or.th iburst
```

```shell
sudo systemctl restart ntp
sudo systemctl status ntp
ntpq -p
```

- Change the ISPConfig panel port

| Web server | Config file |
| --- | --- |
| Apache | `/etc/apache2/sites-available/ispconfig.vhost` |
| Nginx | `/etc/nginx/sites-available/ispconfig.vhost` |

```shell
systemctl restart apache2
```

- Disable Roundcube webmail

```shell
a2disconf roundcube
systemctl reload apache2
```

- Restrict phpMyAdmin to specific networks

```shell
vi /etc/apache2/conf-available/phpmyadmin.conf
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
```

```
#<Directory /usr/share/phpmyadmin>
#       Require all granted
#</Directory>
```

- Change the phpMyAdmin URL path

```shell
vi /etc/apache2/conf-enabled/phpmyadmin.conf
```

```
Alias /hide /usr/share/phpmyadmin
```

- Upgrade phpMyAdmin to PHP 8.2+ (PHP-FPM handler)

```shell
a2enmod proxy_fcgi setenvif
vi /etc/phpmyadmin/apache.conf
```

```
Alias /[alias_phpmyadmin] /usr/share/phpmyadmin

<Directory /usr/share/phpmyadmin>
    Options SymLinksIfOwnerMatch
    DirectoryIndex index.php
    AllowOverride None
    Require all granted

    <IfModule mod_php7.c>
        php_admin_value upload_tmp_dir /var/lib/phpmyadmin/tmp
        php_admin_value open_basedir /usr/share/phpmyadmin/:/usr/share/doc/phpmyadmin/:/etc/phpmyadmin/:/var/lib/phpmyadmin/:/usr/share/php/:/usr/share/javascript/
    </IfModule>

    <IfModule proxy_fcgi_module>
        <FilesMatch "\.php$">
            SetHandler "proxy:unix:/run/php/php8.4-fpm.sock|fcgi://localhost/"
        </FilesMatch>
    </IfModule>
</Directory>

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

- Install PHP 8.2 / 8.3 / 8.4

```shell
sudo apt install -y php8.2 php8.2-cli php8.2-fpm php8.2-cgi php8.2-mysql php8.2-curl php8.2-gd php8.2-intl php8.2-mbstring php8.2-xml php8.2-zip php8.2-soap php8.2-bcmath
sudo apt install -y php8.3 php8.3-cli php8.3-fpm php8.3-cgi php8.3-mysql php8.3-curl php8.3-gd php8.3-intl php8.3-mbstring php8.3-xml php8.3-zip php8.3-soap php8.3-bcmath
sudo apt install -y php8.4 php8.4-cli php8.4-fpm php8.4-cgi php8.4-mysql php8.4-curl php8.4-gd php8.4-intl php8.4-mbstring php8.4-xml php8.4-zip php8.4-soap php8.4-bcmath

sudo systemctl enable --now php8.2-fpm
sudo systemctl enable --now php8.3-fpm
sudo systemctl enable --now php8.4-fpm

php8.2 -v
php8.3 -v
php8.4 -v
```

- Switch the default PHP-CLI version

```shell
php -v
update-alternatives --display php
update-alternatives --install /usr/bin/php php /usr/bin/php8.x 8x
update-alternatives --config php
```

- Change the Docker network subnet

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

- Fix the Ondrej PPA mirror when `launchpadcontent.net` is unreachable

```shell
cat /etc/apt/sources.list.d/ondrej-ubuntu-php*.list
sudo sed -i 's|https://ppa.launchpadcontent.net|http://ppa.launchpad.net|g' /etc/apt/sources.list.d/ondrej-ubuntu-php*.list
sudo rm -f /var/lib/apt/lists/*ondrej*php*
sudo apt clean
sudo apt update
```

### 🔑 Credentials

- Change the ISPConfig admin password

```sql
use dbispconfig;
update sys_user set passwort = md5('changeme') where username = 'admin';
flush privileges;
quit;
```

- Change the MySQL root password

```sql
use mysql;
alter user 'root'@'localhost' identified by 'changeme';
flush privileges;
quit;
```

- Sync phpMyAdmin's stored MySQL credentials after a root password change

```shell
vi /usr/local/ispconfig/server/lib/mysql_clientdb.conf
systemctl restart apache2
```

### 🏆 Usage

Automation scripts under [`scripts/`](scripts):

| Script | Purpose |
| --- | --- |
| `list-wordpress-website.sh` | Discover every WordPress site under `/var/www` → `website.txt` |
| `list-wordpress-version.sh` | Report the WordPress core version per site → `version.txt` |
| `list-wordpress-update.sh` | Report pending plugin/theme updates per site → `update.txt` |
| `list-wordpress-backup.sh` | Find backup archives (`.wpress`, `.zip`, `.sql`, ...) per site → `report-wpress.txt` |
| `delete-wordpress-backups.sh` | Keep only the latest backup per site, remove the rest |
| `delete-phpinfo.sh` | Find and remove stray `phpinfo.php` / `info.php` files under `/var/www` |
| `delete-ispconfig.sh` | Purge ISPConfig backup files older than 30 days |
| `backup-ispconfig.sh` | Rsync ISPConfig backups from a remote server |
| `closed-database.sh` | Dump every non-system MySQL database to `export/db/` |
| `closed-ispconfig.sh` | Archive site logs and web content for offboarded clients |
| `robots-sitemap.sh` | Ensure every hosted site's `robots.txt` references its sitemap |
| `monitor-acme-certificate.sh` | Check ACME.sh SSL renewal status, alert via LINE on failure |
| `create-crontab.sh` | Interactively add a command to root's crontab |
| `create-ascii-art.sh` | Add an ASCII-art login banner to a user's `.bashrc` |

ISPConfig's own core scripts, for reference when troubleshooting the installed system:

| Script | Category | Purpose |
| --- | --- | --- |
| `handle_mailbox_soft_deleted.sh` | Mail management | Processes mailboxes marked as soft-deleted (cleanup, retention handling, or final removal after grace period). |
| `vlogger` | Logging / diagnostics | Logging utility used by services (commonly web servers) to record or process logs, often per virtual host. |
| `update_stable.sh` | Update system | Initiates update from the stable release channel. |
| `update_runner.sh` | Update system | Main update execution wrapper that coordinates the update workflow. |
| `update_from_svn.sh` | Update system | Updates installation from an SVN repository source. |
| `update_from_dev_stable.sh` | Update system | Updates from a development branch considered relatively stable (testing channel). |
| `update_from_dev.sh` | Update system | Updates directly from the development branch (bleeding-edge code). |
| `ispconfig_update.sh` | Update system | Shell wrapper that prepares environment and triggers the main update process. |
| `ispconfig_update.php` | Update system | Core update logic (PHP) handling version checks, file updates, and database migrations. |
| `ispconfig_patch` | Update system | Applies patches or hotfixes to an existing installation. |
| `ispconfig_htaccess.php` | Web configuration | Generates or updates `.htaccess` rules for panel or hosted environments. |
| `letsencrypt_pre_hook.sh` | SSL / Let's Encrypt | Runs before certificate issuance or renewal (prepare services, validation setup, etc.). |
| `letsencrypt_post_hook.sh` | SSL / Let's Encrypt | Runs after certificate issuance or renewal (deploy certs, reload services). |
| `letsencrypt_renew_hook.sh` | SSL / Let's Encrypt | Executes during renewal lifecycle to manage service behavior tied to renewal. |
| `create_jailkit_user.sh` | Jailkit environment | Creates a jailed system user inside a Jailkit environment. |
| `create_jailkit_programs.sh` | Jailkit environment | Installs or enables specific programs inside the jail environment. |
| `create_jailkit_chroot.sh` | Jailkit environment | Builds or prepares the chroot jail filesystem structure. |
| `create_daily_nginx_access_logs.sh` | Logging / web server | Creates or rotates daily Nginx access logs (per site or system-wide depending on config). |

- Manual backup

```shell
mkdir /home/backup
chmod 700 /home/backup
cd /home/backup

mysqldump -u root -p dbispconfig > dbispconfig.sql
tar pcfz ispconfig_software.tar.gz /usr/local/ispconfig
tar pcfz etc.tar.gz /etc
```

- OS update
  - Enable Maintenance Mode: `System → Main Config → Misc → Maintenance Mode`
  - Update the Master server first, then Slave servers
  - Update from the Stable channel only

- ISPConfig update (backs up `/usr/local/ispconfig` and the database to `/var/backup` first)

```shell
cd /usr/local/bin/
ispconfig_update.sh
ispconfig_update.sh --force
```

- [WP-CLI](https://developer.wordpress.org/cli/commands/)

```shell
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
php wp-cli.phar --info
chmod +x wp-cli.phar
sudo mv wp-cli.phar /usr/local/bin/wp
```

```shell
sudo -u web1 wp core version --path=/var/www/clients/client1/web1/web
sudo -u web1 wp plugin update --all --path=/var/www/clients/client1/web1/web
sudo -u web1 wp theme update --all --path=/var/www/clients/client1/web1/web
sudo -u web1 wp core update --path=/var/www/clients/client1/web1/web
sudo -u web1 wp db repair --path=/var/www/clients/client1/web1/web
sudo -u web1 wp db optimize --path=/var/www/clients/client1/web1/web
sudo -u web1 wp transient delete --all --path=/var/www/clients/client1/web1/web
sudo -u web1 php /usr/local/bin/wp user create john john@example.com --role=administrator --user_pass=changeme --path=/var/www/clients/client1/web1/web
```

- Compress a directory with zstd

```shell
sudo apt install zstd -y
tar -czvf - input/ | zstd -o output.tar.zst
```

### 📅 Schedule / Cron

```shell
0 5 * * * /root/ispconfig-shell-script/scripts/list-wordpress-backup.sh
0 6 * * * /root/ispconfig-shell-script/scripts/delete-wordpress-backups.sh
```

### ⚠️ Troubleshooting

- Enable the MySQL general query log for debugging

```sql
SHOW VARIABLES LIKE 'datadir';
SHOW VARIABLES LIKE 'general_log';
SHOW VARIABLES LIKE 'general_log_file';
SHOW VARIABLES LIKE 'slow_query_log';
SHOW VARIABLES LIKE 'log_output';
SET GLOBAL general_log = 'ON';
SET GLOBAL general_log_file = '/var/log/mysql/general.log';
```

- Rotate the general query log

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

- Upgrade Ubuntu 22.04 (Jammy) to 24.04 (Noble)

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
apt install -y ubuntu-release-upgrader-core screen
screen -S upgrade-24
do-release-upgrade
mariadb-upgrade -u root -p
systemctl restart mariadb
```

### 🛡️ Security

- IP blocklist enforcement via iptables — full rule reference in [`Iptables/iptables.md`](Iptables/iptables.md)

```shell
cd Iptables
vi banlist.txt
./block-ip.sh
```

- Malware and suspicious-file scanning via [ISPProtect](https://www.ispprotect.com/)

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

- Remove the public Apache manual (reduces information disclosure)

```shell
grep -R "Alias /manual" /etc/apache2 -n
a2disconf apache2-doc
systemctl reload apache2
```

### 📜 License

This project is licensed under the [MIT License](LICENSE).

### ✉️ Contact

**Natthasath Saksupanara** — Computer Technical Officer, NIDA  

natthasath.sak@gmail.com
