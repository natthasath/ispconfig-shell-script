#!/bin/bash

# Prompt the user to enter a command
read -p "Enter the command: " command

# Prompt the user to enter the cron schedule
read -p "Enter the cron schedule (e.g. '0 0 * * *'): " schedule

# Add the command to the crontab with the specified schedule
(crontab -l 2>/dev/null; echo "$schedule $command") | crontab -