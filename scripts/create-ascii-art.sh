#!/bin/bash

# Prompt the user to enter the username for which ASCII Art should be added
read -p "Enter the username: " USER

# Check if the user exists in the system
if id "$USER" &>/dev/null; then
    USER_HOME="/home/$USER"
    ASCII_FILE="$USER_HOME/.ascii-art"
    BASHRC_FILE="$USER_HOME/.bashrc"

    # ASCII Art Content
    ASCII_ART='
 ___ ____ _____   _   _ ___ ____    _
|_ _|  _ \_   _| | \ | |_ _|  _ \  / \
 | || | | || |   |  \| || || | | |/ _ \
 | || |_| || |   | |\  || || |_| / ___ \
|___|____/ |_|   |_| \_|___|____/_/   \_\
'

    # Create the ASCII Art file
    echo "$ASCII_ART" > "$ASCII_FILE"

    # Add a command to display ASCII Art in .bashrc if it's not already added
    if ! grep -q "cat $ASCII_FILE" "$BASHRC_FILE"; then
        echo "cat $ASCII_FILE" >> "$BASHRC_FILE"
    fi

    # Set correct ownership and permissions
    chown "$USER:$USER" "$ASCII_FILE" "$BASHRC_FILE"
    chmod 644 "$ASCII_FILE"

    echo "✅ ASCII Art has been added for user '$USER'! Open a new terminal to see it."
else
    echo "❌ User '$USER' not found in the system."
fi
