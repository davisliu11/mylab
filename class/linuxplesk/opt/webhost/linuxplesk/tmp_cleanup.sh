#!/bin/bash

`which find` /tmp -mtime +3 \! -user root -exec `which rm` -rf {} \;

tmp_size="`du -s /tmp | awk '{print $1}'`"

if [[ $tmp_size -gt 475000 ]] ; then

	echo "/tmp getting too full ${tmp_size}"
	`which find` /tmp -mtime +2 \! -user root -exec `which rm` -rf {} \;

fi
