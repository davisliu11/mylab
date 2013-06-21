#!/bin/bash

# This script is used to truncate search tables from phpbb databases.
# They usually get polluted with spam, causing massive loads with some big queries.

echo "`date +'%Y-%m-%d %H:%M:%S'` Initializing." >> $0.log

for db in `mysql -Ne 'show databases;' | egrep -v '^mysql$|^information_schema$'`; do
  tbls="`mysql $db -Ne 'show tables;'`"

  for tbl in $tbls; do
    if [ "`echo $tbl | egrep -c 'search_wordmatch$|search_wordlist$'`" -gt 0 ]; then
      echo "`date +'%Y-%m-%d %H:%M:%S'` Truncating $db.$tbl" >> $0.log
      mysql $db -e "truncate table $tbl;" >> $0.log
      PHPBB="true"
    else
      PHPBB="false"
    fi
  done

  if [ "$PHPBB" = "false" ]; then
    echo "`date +'%Y-%m-%d %H:%M:%S'` No phpbb tables found on $db." >> $0.log
  fi

done

echo "`date +'%Y-%m-%d %H:%M:%S'` Finished" >> $0.log
