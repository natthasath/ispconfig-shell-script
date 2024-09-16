#!/bin/bash

mkdir -p /home/itcadmin/export/log
mkdir -p /home/itcadmin/export/web
mkdir -p /home/itcadmin/export/db

# Set the base directory where websites are stored
WEB_DIR="/var/www"
MYSQL_USER="root"

# Prompt for MariaDB root password
echo -n "Enter MariaDB root password: "
read -s MYSQL_PASSWORD
echo

# Loop through each directory in /var/www
for site in "$WEB_DIR"/*; do
    if [ -d "$site" ]; then
        # Extract the website name (the directory name)
        website_name=$(basename "$site")

        # Define the log and web directories for this website
        log_dir="$site/log"
        web_dir="$site/web"
        db_dir="$site/db"

        # Check if the log directory exists
        if [ -d "$log_dir" ]; then
            # Define the backup file name
            log_backup_file="${website_name}-log.tar.gz"

            # Create a backup of the log directory
            tar -czvf "/home/itcadmin/export/log/$log_backup_file" -C "$log_dir" .

            # Output success message
            echo "Backup created: $log_backup_file"
        fi

        # Check if the web directory exists
        if [ -d "$web_dir" ]; then
            # Define the backup file for web
            web_backup_file="${website_name}-web.tar.gz"

            # Create a backup of the web directory
            tar -czvf "/home/itcadmin/export/web/$web_backup_file" -C "$web_dir" .

            # Output success message for web backup
            echo "Web backup created: $web_backup_file"
        fi
    fi
done

# Get list of databases except system databases
databases=$(mysql -u$MYSQL_USER -p$MYSQL_PASSWORD -e "SHOW DATABASES;" | grep -Ev "(Database|information_schema|performance_schema|mysql|sys)")

# Export each database
for DB in $DATABASES; do
    BACKUP_FILE="$BACKUP_DIR/${DB}.sql"
    echo "Backing up database $DB to $BACKUP_FILE"
    mysqldump -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$DB" > "$BACKUP_FILE"
done

echo "Backup completed. All databases have been exported to $BACKUP_DIR"

tar -czvf "export.tar.gz" "/home/itcadmin/export/*"