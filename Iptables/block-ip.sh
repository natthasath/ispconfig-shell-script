#!/bin/bash

# Path to the banlist file
BANLIST="banlist.txt"

# Check if banlist file exists
if [ ! -f "$BANLIST" ]; then
    echo "Error: $BANLIST file not found!"
    exit 1
fi

# Read each IP/subnet from the banlist and add an iptables rule to block it
while IFS= read -r entry; do
    # Check if the entry is already blocked (IP or subnet)
    iptables -C INPUT -s "$entry" -j DROP 2>/dev/null

    if [ $? -ne 0 ]; then
        # Block the entry if not already blocked
        iptables -A INPUT -s "$entry" -j DROP
        echo "Blocked: $entry"
    else
        echo "Already blocked: $entry"
    fi
done < "$BANLIST"

echo "All entries from $BANLIST have been processed."
