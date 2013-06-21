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

for file in `find "$1" -type f -name application_top.php -path "*/includes/*"`; do
	if [ ! "`grep PROJECT_VERSION "$file" | grep -c 'osCommerce'`" = "0" ]; then
		version=`grep PROJECT_VERSION_MAJOR "$file" | cut -d \' -f 4 | sed 's|osCommerce Online Merchant ||'`
		echo "oscommerce "$version" "$file
	fi
done
