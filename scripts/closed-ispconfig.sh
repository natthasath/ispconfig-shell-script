#!/bin/bash

mkdir -p export/log
mkdir -p export/web

# Set the base directory where websites are stored
WEB_DIR="/var/www"
MYSQL_USER="root"

# Loop through each directory in /var/www
for site in "$WEB_DIR"/*; do
    if [ -d "$site" ]; then
        # Extract the website name (the directory name)
        website_name=$(basename "$site")

        # Define the log and web directories for this website
        log_dir="$site/log"
        web_dir="$site/web"

        # Check if the log directory exists
        if [ -d "$log_dir" ]; then
            # Define the backup file name
            log_backup_file="${website_name}-log.tar.gz"

            # Create a backup of the log directory
            tar -czvf "export/log/$log_backup_file" -C "$log_dir" .

            # Output success message
            echo "Backup created: $log_backup_file"
        fi

        # Check if the web directory exists
        if [ -d "$web_dir" ]; then
            # Define the backup file for web
            web_backup_file="${website_name}-web.tar.gz"

            # Create a backup of the web directory
            tar -czvf "export/web/$web_backup_file" -C "$web_dir" .

            # Output success message for web backup
            echo "Web backup created: $web_backup_file"
        fi
    fi
done