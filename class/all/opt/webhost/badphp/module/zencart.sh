#!/bin/sh

usage="Usage: "$0" webroot"

if [ "$1" = "" ]; then
	echo
	echo $usage
	echo
	exit
fi

if [ ! -d "$1" ]; then
	echo
	echo "No such directory \""$1"\""
	echo
	exit
fi

OLD_IFS=$IFS
IFS=$'\n'

for file in `find "$1" -type f -name version.php -path "*/includes/*"`; do
	if [ ! "`grep PROJECT_VERSION_NAME "$file" | grep -c 'Zen Cart'`" = "0" ]; then
		vMajor=`grep PROJECT_VERSION_MAJOR "$file" | cut -d \' -f 4`
		vMinor=`grep PROJECT_VERSION_MINOR "$file" | cut -d \' -f 4`
		echo "zencart "$vMajor"."$vMinor" "$file
	fi
done
