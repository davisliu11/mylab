#!/bin/sh

BASEDIR="/opt/webhost/badphp"

usage="Usage: "$0" module webroot|--list"

if [ "$1" = "" ] || [ "$2" = "" ] ; then
	echo
	echo "$usage"
	echo
	exit
fi

if [ ! -f "$BASEDIR/module/$1.sh" ]; then
	echo
	echo "Module \"$1\" does not exist"
	echo
	exit
fi

if [ "$2" = "--list" ]; then
	echo "Listing $1 module results"
	$BASEDIR/module/$1.sh $2 $BASEDIR/log/$1.log
	exit
else
	echo "Running $1 module"
	$BASEDIR/module/$1.sh $2 > $BASEDIR/log/$1.log 2> $BASEDIR/log/$1.err
	exit
fi
