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
            # Get the owner and group of the web directory
            owner_group=$(stat -c "%U:%G" "$web_dir")

            # Check if robots.txt exists
            if [ -f "$robots_file" ]; then
                # Check if sitemap entry already exists
                if grep -Fxq "$sitemap_entry" "$robots_file"; then
                    echo "Sitemap entry already exists in robots.txt for $website_name."
                else
                    echo "$sitemap_entry" >> "$robots_file"
                    echo "Added sitemap entry to robots.txt for $website_name."
                fi
            else
                echo "$sitemap_entry" > "$robots_file"
                echo "Created robots.txt for $website_name and added sitemap entry."
            fi

            # Change ownership of robots.txt to match web directory
            chown "$owner_group" "$robots_file"
        fi
    fi
done
