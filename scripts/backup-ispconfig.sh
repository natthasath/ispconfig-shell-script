#!/bin/bash

# Rsync backup all website in ispconfig
rsync -av root@192.168.1.100:/var/backup/* /var/backup/192.168.1.100/