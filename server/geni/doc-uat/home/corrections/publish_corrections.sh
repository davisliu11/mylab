#!/bin/sh

echo "Publishing corrections.govt.nz"

FILE_MIN=3000

if [ "`find /var/www/corrections.govt.nz/htdocs/ -type f | wc -l`" -lt "$FILE_MIN" ]; then
  echo "=================================================="
  echo
  echo "Error: There are less than $FILE_MIN files in the site.  Aborting update."
  echo  
  echo "=================================================="
else
  /usr/bin/rsync --delete -var -e ssh \
	/var/www/corrections.govt.nz/htdocs/ 172.17.0.33:/var/www/corrections.govt.nz/htdocs/

  echo 
  echo "=================================================="
  echo
  echo "Running site index update in the background.  This will take a few minutes to complete."

  ssh 172.17.0.33 /var/www/corrections.govt.nz/htdig_private/rundig.sh -c /var/www/corrections.govt.nz/htdig_private/site.conf 2>/dev/null >/dev/null &

  echo
  echo "=================================================="
fi