#!/bin/bash

# Input file containing paths and users
input_file="website.txt"

# Output file for versions
output_file="update.txt"

# Clear the output file if it exists
> "$output_file"

# Loop through each line in the input file
while IFS= read -r line; do

    path=$(readlink -f /var/www/"$line")

    # Split the line into user and path
    user=$(echo "$path" | cut -d '/' -f 6)

    # Append '/web' to the path
    path="$path/web"

    # Construct the command
    update=$(sudo -u $user wp core update --path=$path)
    command="sudo -u $user wp core version --path=$path"

    # Execute the command and save the output to the output file
    version=$($command)
    
    echo "$line : $version" >> "$output_file"

done < "$input_file"
