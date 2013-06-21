#!/bin/bash

# This script will optimize all tables in the server

echo "`date +'%Y-%m-%d %H:%M:%S'` Initializing." >> $0.log

for db in `mysql -Ne 'show databases;' | egrep -v '^mysql$|^information_schema$'`; do
  tbls="`mysql $db -Ne 'show tables;'`"

  for tbl in $tbls; do
      echo "`date +'%Y-%m-%d %H:%M:%S'` Optimizing $db.$tbl" >> $0.log
      mysql $db -e "optimize table $tbl;" >> $0.log
      mysql $db -e "repair table $tbl;" >> $0.log
  done

done

echo "`date +'%Y-%m-%d %H:%M:%S'` Finished" >> $0.log
