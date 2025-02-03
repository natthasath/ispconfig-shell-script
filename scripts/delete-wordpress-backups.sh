#!/bin/bash

# Input file containing the list of backup files
INPUT_FILE="report-wpress.txt"

# Check if the input file exists
if [ ! -f "$INPUT_FILE" ]; then
    echo "Error: Input file $INPUT_FILE not found."
    exit 1
fi

# Temporary file to store the latest backup for each site
TEMP_FILE=$(mktemp)

# Read the input file and group files by website
declare -A site_files
while IFS= read -r line; do
    # Extract website name and file path
    website_name=$(echo "$line" | awk -F ': ' '{print $1}')
    file_path=$(echo "$line" | awk -F ': ' '{print $2}' | awk '{print $1}')
    
    # Add the file to the website's list
    site_files["$website_name"]+="$file_path"$'\n'
done < "$INPUT_FILE"

# Process each website's files
for website in "${!site_files[@]}"; do
    # Get the list of files for the current website
    files=$(echo "${site_files[$website]}" | sort | tr -s '\n' | sed '/^$/d')
    
    # Get the latest file (last in the sorted list)
    latest_file=$(echo "$files" | tail -n 1)
    
    # Save the latest file to the temporary file
    echo "$website: $latest_file" >> "$TEMP_FILE"
    
    # Delete all other files except the latest one
    echo "$files" | while read -r file; do
        if [ "$file" != "$latest_file" ]; then
            echo "Deleting $file"
            rm -f "$file"
        fi
    done
done

# Print completion message
echo "Old backups deleted. Latest backups saved in $TEMP_FILE"