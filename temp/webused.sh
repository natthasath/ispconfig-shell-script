#!/bin/bash

reportdate=$(date +"%Y%m%d")
reportfile="webused-$reportdate.txt"

echo $reportdate > $reportfile
du --max-depth=0 /var/www/12rc.nida.ac.th/web >> $reportfile