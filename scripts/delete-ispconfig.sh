#!/bin/bash

for i in /var/backup/*; do
    for j in $i/*; do
            # Delete File Older 1 Month
            find $j -mtime +30 -type f -delete
    done
done