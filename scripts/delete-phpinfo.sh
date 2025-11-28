#!/bin/bash

# Search for all files named phpinfo.php or info.php
php_files=$(find /var/www -type f \( -name "phpinfo.php" -o -name "info.php" \))

# Check if any files were found
if [[ -z "$php_files" ]]; then
    echo "No phpinfo.php or info.php files found."
    exit 0
fi

echo "Found the following phpinfo.php and info.php files:"

# Display the list of found files
echo "$php_files"
echo

# Ask for confirmation to delete each file
for file in $php_files; do
    echo "Do you want to delete $file? (y/n): "
    read -r confirm
    if [[ "$confirm" == "y" ]]; then
        rm "$file"
        echo "Deleted: $file"
    else
        echo "Skipped: $file"
    fi
done
