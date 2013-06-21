#!/bin/bash

echo "show tables;" | mysql asys | grep -v ^Tables_in_asys | \
        awk '{print "SELECT |"$1"|,COUNT(*) FROM `"$1"`;"}' | tr \| \' | mysql asys | \
        grep -v COUNT\( | sort -n -k 2
