#!/bin/bash

# Set the base directory where websites are stored
WEB_DIR="/var/www"
OUTPUT_FILE="report-wpbackup.txt"

# Define the file extensions to look for
FILE_EXTENSIONS=".wpress .zip .jpa .tar .gz .sql"

# Clear the output file before writing
> "$OUTPUT_FILE"

# Loop through each directory in /var/www
for site in "$WEB_DIR"/*; do
    if [ -d "$site" ]; then
        # Extract the website name (the directory name)
        website_name=$(basename "$site")

        # Define the backup directories for this website
        backup_dirs=(
            "$site/web/wp-content/ai1wm-backups"
            "$site/web/wp-content/updraft"
            "$site/web/wp-content/uploads/backwpup"
            "$site/web/wp-snapshots"
            "$site/web/wp-content/wpvividbackups"
            "$site/web/wp-content/uploads/backupbuddy_backups"
            "$site/web/wp-content/akeeba-backups"
            "$site/web/wp-content/backup-db"
        )

        # Loop through each backup directory
        for backup_dir in "${backup_dirs[@]}"; do
            # Check if the backup directory exists
            if [ -d "$backup_dir" ]; then
                # Loop through each file extension
                for ext in $FILE_EXTENSIONS; do
                    # Find all files with the current extension in the directory
                    find "$backup_dir" -type f -name "*$ext" | while read -r file; do
                        echo "$website_name: $file" >> "$OUTPUT_FILE"
                    done
                done
            fi
        done
    fi
done

# Print completion message
echo "Scan complete. Report saved to $OUTPUT_FILE"