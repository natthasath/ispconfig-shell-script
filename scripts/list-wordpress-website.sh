#!/bin/bash

# Define the output file
output_file="website.txt"

# Clear the output file if it exists
> "$output_file"

# Find domain name from the path
domain=$(find /var/www -type l | grep -v '/var/www/clients/' | grep -v '/var/www/ispconfig' | sed 's|^/var/www/||')

echo "$domain" >> "$output_file"
