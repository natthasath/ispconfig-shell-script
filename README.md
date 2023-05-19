# 🎉 ispconfig-shell-script
ISPConfig is a web hosting control panel for Linux servers. A shell script can be used to automate common tasks like creating email accounts and websites, managing FTP users, and configuring SSL certificates.

![version](https://img.shields.io/badge/version-1.0-blue)
![rating](https://img.shields.io/badge/rating-★★★★★-yellow)
![uptime](https://img.shields.io/badge/uptime-100%25-brightgreen)

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

