#!/bin/bash

mkdir -p export/db

MYSQL_USER="root"
BACKUP_DIR="export/db"

# Get list of databases except system databases
DATABASES=$(mysql -u $MYSQL_USER -p -e "SHOW DATABASES;" | grep -Ev "(Database|information_schema|performance_schema|mysql|sys)")

# Export each database
for DB in $DATABASES; do
    BACKUP_FILE="$BACKUP_DIR/${DB}.sql"
    echo "Backing up database $DB to $BACKUP_FILE"
    mysqldump -u $MYSQL_USER -p "$DB" > "$BACKUP_FILE"
done

echo "Backup completed. All databases have been exported to $BACKUP_DIR"