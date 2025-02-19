#!/bin/bash

# Set the base directory where websites are stored
WEB_DIR="/var/www"

# Loop through each directory in /var/www
for site in "$WEB_DIR"/*; do
    if [ -d "$site" ]; then
        # Extract the website name (the directory name)
        website_name=$(basename "$site")
        web_dir="$site/web"
        robots_file="$web_dir/robots.txt"
        domain="https://$website_name"
        sitemap_entry="Sitemap: $domain/sitemap.xml"

        # Check if the web directory exists
        if [ -d "$web_dir" ]; then
            # If robots.txt doesn't exist, create it and add sitemap entry
            if [ ! -f "$robots_file" ]; then
                echo "$sitemap_entry" > "$robots_file"
                echo "Created robots.txt for $website_name and added sitemap entry."
            else
                # Check if sitemap entry already exists
                if ! grep -q "^Sitemap: .*sitemap.xml" "$robots_file"; then
                    echo "$sitemap_entry" >> "$robots_file"
                    echo "Added sitemap entry to robots.txt for $website_name."
                else
                    echo "Sitemap entry already exists in robots.txt for $website_name."
                fi
            fi
        fi
    fi
done